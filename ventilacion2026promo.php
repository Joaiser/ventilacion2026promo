<?php
if (!defined('_PS_VERSION_')) {
  exit;
}

require_once dirname(__FILE__) . '/helpers/Constants.php';
require_once dirname(__FILE__) . '/helpers/Logger.php';
require_once dirname(__FILE__) . '/services/DatabaseManager.php';
require_once dirname(__FILE__) . '/services/GroupManager.php';
require_once dirname(__FILE__) . '/services/PromoCalculator.php';
require_once dirname(__FILE__) . '/services/PromoHookCalculator.php';

class Ventilacion2026Promo extends Module
{
  private $databaseManager;
  private $groupManager;
  private $hookCalculator;

  public function __construct()
  {
    $this->name = 'ventilacion2026promo';
    $this->tab = 'front_office_features';
    $this->version = '1.0.0';
    $this->author = 'Aitor 🚀';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

    parent::__construct();

    $this->displayName = $this->l('Ventilación 2026 Promo');
    $this->description = $this->l('Gestión del grupo promocional durante checkout.');

    // Inicializar servicios
    $this->initializeServices();
  }

  private function initializeServices()
  {
    $this->databaseManager = new DatabaseManager();
    $this->groupManager = new GroupManager($this->databaseManager);
    $this->hookCalculator = new PromoHookCalculator();
  }

  public function install()
  {
    return parent::install()
      && $this->registerHook('actionCartSave')
      && $this->registerHook('actionValidateOrder')
      && $this->registerHook('actionFrontControllerSetMedia')
      && $this->registerHook('displayHeader')
      && $this->databaseManager->createLastGroupTable();
  }

  public function uninstall()
  {
    return parent::uninstall()
      && $this->databaseManager->dropLastGroupTable();
  }

  /* =========================================================
       ⚙️ HOOKS - LIMPIOS Y SIMPLES
    ========================================================= */
  public function hookActionCartSave($params)
  {
    $context = Context::getContext();
    $customer = $context->customer;
    $cart = $context->cart;

    if (!$customer || !$customer->id || !$cart || !$cart->id) {
      Logger::log("❌ hookActionCartSave - Cliente o carrito inválido");
      return;
    }

    Logger::log("🔄 hookActionCartSave - Cliente: {$customer->id}, Carrito: {$cart->id}");

    // Recargar datos frescos
    $freshCart = new Cart($cart->id);
    $freshCustomer = new Customer($customer->id);

    // ✅ CALCULAR TOTAL PRIMERO
    $totalPromo = $this->hookCalculator->getPromoTotalForHook($freshCart);

    // ✅ SIEMPRE EVALUAR, INCLUSO CUANDO HAY PRODUCTOS
    Logger::log("💰 TOTAL CALCULADO: {$totalPromo}€ - EVALUANDO GRUPO...");
    $this->evaluatePromoGroupWithTotal($freshCustomer, $freshCart, $totalPromo);
  }

  public function hookActionValidateOrder($params)
  {
    $order = $params['order'] ?? null;
    if (!$order) return;

    $customerId = $order->id_customer;
    $this->databaseManager->deleteLastGroup($customerId);
    Logger::log("🎉 Pedido completado - eliminado último grupo cliente {$customerId}");
  }

  /* =========================================================
       🎯 LÓGICA PRINCIPAL
    ========================================================= */

  /**
   * ✅ NUEVO: Evaluación del grupo promocional con total ya calculado
   */
  private function evaluatePromoGroupWithTotal($customer, $cart, $totalPromo)
  {
    if (!$customer->id || !$cart) return;

    $currentGroup = $this->groupManager->getCustomerDefaultGroup($customer->id);
    $lastGroup = $this->databaseManager->getLastGroup($customer->id);

    Logger::log("🧮 HOOK Evaluación: cliente {$customer->id}, grupo actual {$currentGroup}, último grupo " . ($lastGroup ?: 'NULL') . ", total {$totalPromo}€");

    // Determinar el grupo promocional CORRECTO según el origen
    $correctPromoGroup = $this->getCorrectPromoGroup($lastGroup ?: $currentGroup);

    Logger::log("🎯 Grupo promocional correcto para cliente: {$correctPromoGroup}");

    // Cumple promoción → mover a grupo promocional CORRECTO
    if ($totalPromo >= Constants::PROMO_MIN_TOTAL && $currentGroup != $correctPromoGroup) {
      Logger::log("🎯 CLIENTE CUMPLE PROMO - Mover a grupo promocional {$correctPromoGroup} (Total: {$totalPromo}€ >= " . Constants::PROMO_MIN_TOTAL . "€)");
      $this->groupManager->promoteToPromoGroup($customer, $currentGroup, $totalPromo);
      return;
    }

    // No cumple promoción → restaurar grupo original CORRECTO
    if ($totalPromo < Constants::PROMO_MIN_TOTAL && $currentGroup == $correctPromoGroup) {
      $restoreGroup = $lastGroup ?: Constants::DEFAULT_GROUP_ID;
      Logger::log("🎯 CLIENTE NO CUMPLE PROMO - Restaurar grupo original {$restoreGroup} (Total: {$totalPromo}€ < " . Constants::PROMO_MIN_TOTAL . "€)");
      $this->groupManager->restoreOriginalGroup($customer, $lastGroup, $totalPromo);
    } else {
      Logger::log("ℹ️  No se requiere cambio de grupo (Actual: {$currentGroup}, Cumple: " . ($totalPromo >= Constants::PROMO_MIN_TOTAL ? 'SI' : 'NO') . ")");
    }
  }

  /**
   * ✅ Determina el grupo promocional correcto según el grupo original
   */
  private function getCorrectPromoGroup($originalGroup)
  {
    // Si viene del grupo Canarias (33), usar grupo promocional Canarias (71)
    if ($originalGroup === Constants::CANARIAS_GROUP_ID) {
      return Constants::PROMO_GROUP_ID_71;
    }
    // Para cualquier otro caso, usar el grupo promocional normal (70)
    return Constants::PROMO_GROUP_ID;
  }

  /**
   * ✅ MODIFICADO: Restaurar grupo SOLO cuando el cliente sale a páginas no críticas
   */
  function hookActionFrontControllerSetMedia($params)
  {
    error_log("🎯 HOOK EJECUTADO: actionFrontControllerSetMedia para módulo " . $this->name);

    $this->context->controller->addJquery();

    // ✅ SIMPLIFICAR: Solo usar addJsDef
    $baseUrl = $this->context->link->getBaseLink() . 'modules/ventilacion2026promo/views/js/';

    $jsVars = [
      'ventilacionPromoVars' => [
        'baseUrl' => $baseUrl,
        'moduleUrl' => $this->_path,
        'ajaxUrl' => $this->context->link->getModuleLink($this->name, 'ajax'),
        'staticToken' => Tools::getToken(false)
      ]
    ];

    error_log("🎯 JS VARS - BaseUrl: " . $baseUrl);
    Media::addJsDef($jsVars);

    // Registrar CSS
    $this->context->controller->registerStylesheet(
      'module-ventilacion2026promo-style',
      'modules/' . $this->name . '/views/css/ventilacion2026promo.css',
      ['media' => 'all', 'priority' => 150]
    );

    // ✅ CAMBIAR: Usar 'defer' en lugar de 'module' temporalmente
    $this->context->controller->registerJavascript(
      'module-ventilacion2026promo-script',
      'modules/' . $this->name . '/views/js/ventilacion2026promo.js',
      [
        'position' => 'bottom',
        'priority' => 150,
        'attributes' => 'defer' // ← Cambiar a defer
      ]
    );

    error_log("🎯 HOOK COMPLETADO");
  }
  /**
   * ✅ Procesar peticiones AJAX
   */
  function hookDisplayHeader()
  {
    // Solo procesar si es una petición AJAX de nuestro módulo
    if (Tools::getValue('ajax') && Tools::getValue('module') == $this->name) {
      $action = Tools::getValue('action');

      if ($action === 'force_promo') {
        $this->ajaxProcessForcePromoGroup();
      } elseif ($action === 'restore') {
        $this->ajaxProcessRestoreGroup();
      }
    }
  }

  /**
   * ✅ Endpoint AJAX para forzar grupo promocional
   */
  private function ajaxProcessForcePromoGroup()
  {
    try {
      $context = Context::getContext();
      $customer = $context->customer;

      if (!$customer || !$customer->id) {
        die(json_encode(['success' => false, 'error' => 'Cliente no identificado']));
      }

      Logger::log("🎯 AJAX - Forzando grupo promocional para cliente {$customer->id}");

      // Obtener grupo actual y último grupo
      $currentGroup = $this->groupManager->getCustomerDefaultGroup($customer->id);
      $lastGroup = $this->databaseManager->getLastGroup($customer->id);

      // Determinar grupo promocional correcto
      $correctPromoGroup = $this->getCorrectPromoGroup($lastGroup ?: $currentGroup);

      // Mover al grupo promocional
      if ($currentGroup != $correctPromoGroup) {
        $this->groupManager->promoteToPromoGroup($customer, $currentGroup, 0);
        Logger::log("✅ AJAX - Cliente {$customer->id} movido a grupo {$correctPromoGroup}");
      }

      die(json_encode(['success' => true]));
    } catch (Exception $e) {
      Logger::log("❌ AJAX ERROR force_promo: " . $e->getMessage());
      http_response_code(500);
      die(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
  }

  /**
   * ✅ Endpoint AJAX para restaurar grupo original
   */
  private function ajaxProcessRestoreGroup()
  {
    try {
      $context = Context::getContext();
      $customer = $context->customer;

      if (!$customer || !$customer->id) {
        die(json_encode(['success' => false, 'error' => 'Cliente no identificado']));
      }

      Logger::log("🔄 AJAX - Restaurando grupo original para cliente {$customer->id}");

      $currentGroup = $this->groupManager->getCustomerDefaultGroup($customer->id);
      $lastGroup = $this->databaseManager->getLastGroup($customer->id);

      // Si está en grupo promocional, restaurar
      $isInPromoGroup = in_array($currentGroup, [Constants::PROMO_GROUP_ID, Constants::PROMO_GROUP_ID_71]);

      if ($isInPromoGroup && $lastGroup) {
        $this->groupManager->restoreOriginalGroup($customer, $lastGroup, 0);
        Logger::log("✅ AJAX - Cliente {$customer->id} restaurado a grupo {$lastGroup}");
      }

      die(json_encode(['success' => true]));
    } catch (Exception $e) {
      Logger::log("❌ AJAX ERROR restore: " . $e->getMessage());
      http_response_code(500);
      die(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
  }

  /**
   * ✅ Obtener clave segura para validación AJAX
   */
  public function getSecureKey()
  {
    // Usar el token estático de PrestaShop o generar uno
    return Tools::getToken(false);
  }
}
