<?php
// services/PromoGroupHelper.php

require_once dirname(__FILE__) . '/../helpers/Constants.php';
require_once dirname(__FILE__) . '/../helpers/Logger.php';
require_once dirname(__FILE__) . '/DatabaseManager.php';

class PromoGroupHelper
{
  /**
   * Determina el grupo promocional según el grupo original del cliente
   */
  public static function determinePromoGroup($originalGroup)
  {
    // Logger::log("🎯 Determinar grupo promocional - Grupo original: {$originalGroup}");

    // Si viene del grupo Canarias (33), usar grupo promocional Canarias (71)
    if ($originalGroup === Constants::CANARIAS_GROUP_ID) {
      // Logger::log("📍 Cliente CANARIAS detectado - Usando grupo promocional 71");
      return Constants::PROMO_GROUP_ID_71;
    }

    // Para cualquier otro caso, usar el grupo promocional normal (70)
    // Logger::log("📍 Cliente PENINSULAR detectado - Usando grupo promocional 70");
    return Constants::PROMO_GROUP_ID;
  }

  /**
   * Determina el grupo promocional basado en el cliente (USANDO GRUPO ORIGINAL)
   */
  public static function determinePromoGroupFromCustomer($customer)
  {
    // ✅ CORREGIDO: Obtener grupo ORIGINAL (último grupo guardado o grupo actual)
    $databaseManager = new DatabaseManager();
    $lastGroup = $databaseManager->getLastGroup($customer->id);
    $originalGroup = $lastGroup ?: (int)$customer->id_default_group;

    // Logger::log("🎯 Determinar grupo desde cliente - ID: {$customer->id}, Grupo ORIGINAL: {$originalGroup}, Último grupo: " . ($lastGroup ?: 'NULL'));

    return self::determinePromoGroup($originalGroup);
  }

  /**
   * Determina el grupo promocional basado en el último grupo guardado
   */
  public static function determinePromoGroupFromLastGroup($customerId, $databaseManager)
  {
    $lastGroup = $databaseManager->getLastGroup($customerId);
    $originalGroup = $lastGroup ?: (int)Context::getContext()->customer->id_default_group;

    // Logger::log("🎯 Determinar grupo desde último grupo - Cliente: {$customerId}, Último grupo: " . ($lastGroup ?: 'NULL') . ", Grupo original: {$originalGroup}");

    return self::determinePromoGroup($originalGroup);
  }

  /**
   * ✅ NUEVO: Determina el grupo promocional para uso en PromoCalculator
   */
  public static function determinePromoGroupForCalculator($customerId = null)
  {
    $context = Context::getContext();
    $customer = $context->customer;

    if (!$customerId && $customer->id) {
      $customerId = $customer->id;
    }

    $databaseManager = new DatabaseManager();

    if ($customerId) {
      return self::determinePromoGroupFromLastGroup($customerId, $databaseManager);
    } else {
      // Fallback para cuando no hay cliente (poco probable)
      // Logger::log("⚠️  No hay cliente ID - usando grupo por defecto 70");
      return Constants::PROMO_GROUP_ID;
    }
  }
}
