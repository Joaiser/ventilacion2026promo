<?php

// Añadir estos requires al principio
require_once _PS_MODULE_DIR_ . 'ventilacion2026promo/helpers/Constants.php';
require_once _PS_MODULE_DIR_ . 'ventilacion2026promo/helpers/Logger.php';
require_once _PS_MODULE_DIR_ . 'ventilacion2026promo/services/DatabaseManager.php';
require_once _PS_MODULE_DIR_ . 'ventilacion2026promo/services/GroupManager.php';
require_once _PS_MODULE_DIR_ . 'ventilacion2026promo/services/PromoCalculator.php';
require_once _PS_MODULE_DIR_ . 'ventilacion2026promo/services/PromoGroupHelper.php';

class Ventilacion2026PromoUpdateGroupModuleFrontController extends ModuleFrontController
{
  private $databaseManager;
  private $groupManager;
  private $promoCalculator;

  public function __construct()
  {
    parent::__construct();
    $this->databaseManager = new DatabaseManager();
    $this->groupManager = new GroupManager($this->databaseManager);
    $this->ajax = true;
  }

  public function initContent()
  {
    parent::initContent();

    try {
      // ✅ TEMPORAL: Log para debugging
      Logger::log("🎯 UpdateGroup llamado - Parámetros POST: " . json_encode($_POST));
      Logger::log("🎯 UpdateGroup llamado - Parámetros GET: " . json_encode($_GET));

      // Verificar token de seguridad
      $receivedToken = Tools::getValue('token');
      $expectedToken = $this->module->getSecureKey();

      Logger::log("🔐 Token recibido: '{$receivedToken}', esperado: '{$expectedToken}'");

      if (!$receivedToken || $receivedToken !== $expectedToken) {
        Logger::log("❌ Token inválido o faltante");
        throw new Exception('Token de seguridad inválido');
      }

      $context = Context::getContext();
      $cart = $context->cart;
      $customer = $context->customer;

      Logger::log("👤 Cliente ID: " . ($customer->id ?: 'NO ID'));
      Logger::log("🛒 Carrito ID: " . ($cart->id ?: 'NO ID'));

      if (!$customer->id) {
        throw new Exception('Cliente no identificado');
      }

      // 🚀 DETERMINAR GRUPO PROMOCIONAL
      $promoGroupId = PromoGroupHelper::determinePromoGroupFromLastGroup($customer->id, $this->databaseManager);

      // 🚀 OBTENER LASTGROUP UNA VEZ PARA TODO EL MÉTODO
      $lastGroup = $this->databaseManager->getLastGroup($customer->id);
      Logger::log("📋 LastGroup obtenido para cliente {$customer->id}: " . ($lastGroup ?: 'NULL'));

      // ✅ CORREGIDO: Inicializar PromoCalculator CON GRUPO CORRECTO
      $this->promoCalculator = new PromoCalculator($promoGroupId);
      Logger::log("🎯 UpdateGroup - Grupo promocional: {$promoGroupId} (grupo original: " . ($lastGroup ?: $customer->id_default_group) . ")");

      // 🚀 FORZAR GRUPO PROMOCIONAL SOLO SI VIENE EL PARÁMETRO Y CUMPLE EL TOTAL
      $forcePromo = Tools::getValue('force_promo');
      Logger::log("🔍 Force promo parameter: '{$forcePromo}'");

      if ($forcePromo) {
        $totalPromo = $this->promoCalculator->getPromoTotal($cart);
        $cumplePromo = $totalPromo >= Constants::PROMO_MIN_TOTAL;

        Logger::log("🔍 Force promo - Total calculado: {$totalPromo}€, Cumple: " . ($cumplePromo ? 'SÍ' : 'NO') . ", Grupo destino: {$promoGroupId}");

        if ($cumplePromo) {
          $currentGroup = (int)$customer->id_default_group;

          if (!$lastGroup && $currentGroup != $promoGroupId) {
            $this->databaseManager->saveLastGroup($customer->id, $currentGroup);
            Logger::log("💾 Guardado grupo original {$currentGroup} para cliente {$customer->id} (forzado con validación)");
          }

          $this->groupManager->addCustomerToGroup($customer->id, $promoGroupId);
          $customer->id_default_group = $promoGroupId;
          $customer->update();

          Logger::log("🚀 Cliente {$customer->id} FORZADO al grupo promo ({$promoGroupId}) - Total válido: {$totalPromo}€");

          header('Content-Type: application/json');
          echo json_encode([
            'success' => true,
            'group' => 'promo_forced',
            'promo_group_id' => $promoGroupId,
            'total' => $totalPromo,
            'message' => 'Grupo promocional forzado - Total válido'
          ]);
          exit;
        } else {
          Logger::log("❌ Force promo rechazado - Total insuficiente: {$totalPromo}€");
          header('Content-Type: application/json');
          echo json_encode([
            'success' => false,
            'message' => 'Total insuficiente para forzar promoción'
          ]);
          exit;
        }
      }

      // Lógica normal (para otros casos)
      if (!$cart || !$cart->id) {
        throw new Exception('Carrito no encontrado');
      }

      $totalPromo = $this->promoCalculator->getPromoTotal($cart);
      $cumplePromo = $totalPromo >= Constants::PROMO_MIN_TOTAL;

      $currentGroup = (int)$customer->id_default_group;

      Logger::log("🔄 UpdateGroup - Cliente: {$customer->id}, Grupo actual: {$currentGroup}, Grupo promo: {$promoGroupId}, LastGroup: " . ($lastGroup ?: 'NULL') . ", Total promo: {$totalPromo}€, Cumple: " . ($cumplePromo ? 'SÍ' : 'NO'));

      if ($cumplePromo && $currentGroup != $promoGroupId) {
        if (!$lastGroup) {
          $this->databaseManager->saveLastGroup($customer->id, $currentGroup);
          Logger::log("💾 Guardado grupo original {$currentGroup} para cliente {$customer->id}");
        }

        $this->groupManager->addCustomerToGroup($customer->id, $promoGroupId);
        $customer->id_default_group = $promoGroupId;
        $customer->update();

        Logger::log("🚀 Cliente {$customer->id} movido al grupo promo ({$promoGroupId}) - Total: {$totalPromo}€");

        header('Content-Type: application/json');
        echo json_encode([
          'success' => true,
          'group' => 'promo',
          'promo_group_id' => $promoGroupId,
          'total' => $totalPromo,
          'message' => 'Grupo promocional activado'
        ]);
        exit;
      }

      if (!$cumplePromo && ($currentGroup == Constants::PROMO_GROUP_ID || $currentGroup == Constants::PROMO_GROUP_ID_71)) {
        $restoreGroup = $lastGroup ?: Constants::DEFAULT_GROUP_ID;
        // Eliminar de ambos grupos promocionales
        $this->groupManager->removeFromGroup($customer->id, Constants::PROMO_GROUP_ID);
        $this->groupManager->removeFromGroup($customer->id, Constants::PROMO_GROUP_ID_71);
        $this->groupManager->addCustomerToGroup($customer->id, $restoreGroup);
        $customer->id_default_group = $restoreGroup;
        $customer->update();
        $this->databaseManager->deleteLastGroup($customer->id);

        Logger::log("🔙 Cliente {$customer->id} restaurado al grupo {$restoreGroup} - Total: {$totalPromo}€");

        header('Content-Type: application/json');
        echo json_encode([
          'success' => true,
          'group' => 'restored',
          'total' => $totalPromo,
          'message' => 'Grupo original restaurado'
        ]);
        exit;
      }

      Logger::log("ℹ️  Grupo sin cambios para cliente {$customer->id}");

      header('Content-Type: application/json');
      echo json_encode([
        'success' => true,
        'group' => 'unchanged',
        'total' => $totalPromo,
        'message' => 'Grupo sin cambios'
      ]);
      exit;
    } catch (Exception $e) {
      Logger::log("❌ Error en UpdateGroup: " . $e->getMessage());
      header('Content-Type: application/json');
      echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
      ]);
      exit;
    }
  }
}
