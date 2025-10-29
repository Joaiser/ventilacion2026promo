<?php

require_once dirname(__FILE__) . '/../helpers/Constants.php';
require_once dirname(__FILE__) . '/../helpers/Logger.php';
require_once dirname(__FILE__) . '/PromoGroupHelper.php';

class GroupManager
{
  private $databaseManager;

  public function __construct(DatabaseManager $databaseManager)
  {
    $this->databaseManager = $databaseManager;
  }

  public function getCustomerDefaultGroup($customerId)
  {
    $group = (int)Db::getInstance()->getValue(
      'SELECT id_default_group FROM `' . _DB_PREFIX_ . 'customer` WHERE id_customer=' . (int)$customerId
    );
    Logger::log("👤 GroupManager - Grupo por defecto cliente {$customerId}: {$group}");
    return $group;
  }

  /**
   * Promueve al grupo promocional correspondiente
   */
  public function promoteToPromoGroup($customer, $currentGroup, $totalPromo)
  {
    Logger::log("🚀 PROMOTE - Iniciando promoción para cliente {$customer->id}, grupo actual: {$currentGroup}, total: {$totalPromo}€");

    $lastGroup = $this->databaseManager->getLastGroup($customer->id);
    Logger::log("📋 PROMOTE - Último grupo guardado: " . ($lastGroup ?: 'NULL'));

    // Determinar grupo promocional según origen
    $promoGroupId = $this->determinePromoGroup($currentGroup);
    Logger::log("🎯 PROMOTE - Grupo promocional determinado: {$promoGroupId}");

    if (!$lastGroup) {
      $this->databaseManager->saveLastGroup($customer->id, $currentGroup);
      Logger::log("💾 PROMOTE - Guardado grupo original {$currentGroup}");
    }

    $this->addCustomerToGroup($customer->id, $promoGroupId);
    $customer->id_default_group = $promoGroupId;
    $customer->update();

    Logger::log("✅ PROMOTE - Cliente {$customer->id} movido a grupo {$promoGroupId} - Total: {$totalPromo}€");
  }

  /**
   * Determina el grupo promocional según el grupo original
   */
  private function determinePromoGroup($originalGroup)
  {
    // Si viene del grupo Canarias (33), usar grupo promocional Canarias (71)
    if ($originalGroup === Constants::CANARIAS_GROUP_ID) {
      return Constants::PROMO_GROUP_ID_71;
    }

    // Para cualquier otro caso, usar el grupo promocional normal (70)
    return Constants::PROMO_GROUP_ID;
  }

  /**
   * Restaura al grupo original
   */
  public function restoreOriginalGroup($customer, $lastGroup, $totalPromo)
  {
    Logger::log("🔄 RESTORE - Iniciando restauración para cliente {$customer->id}, último grupo: " . ($lastGroup ?: 'NULL') . ", total: {$totalPromo}€");

    // IMPORTANTE: Usar el último grupo guardado o el grupo por defecto
    $restoreGroup = $lastGroup ?: Constants::DEFAULT_GROUP_ID;
    Logger::log("🎯 RESTORE - Grupo a restaurar: {$restoreGroup}");

    // Eliminar de ambos grupos promocionales por seguridad
    Logger::log("🗑️ RESTORE - Eliminando de grupos promocionales...");
    $this->removeFromGroup($customer->id, Constants::PROMO_GROUP_ID);
    $this->removeFromGroup($customer->id, Constants::PROMO_GROUP_ID_71);

    $this->addCustomerToGroup($customer->id, $restoreGroup);

    $customer->id_default_group = $restoreGroup;
    $customer->update();

    Logger::log("✅ RESTORE - Cliente {$customer->id} restaurado a grupo {$restoreGroup} - Total: {$totalPromo}€");
  }

  public function addCustomerToGroup($customerId, $groupId)
  {
    Logger::log("➕ ADD GROUP - Añadiendo cliente {$customerId} al grupo {$groupId}");

    $exists = Db::getInstance()->getValue(
      'SELECT 1 FROM `' . _DB_PREFIX_ . 'customer_group`
             WHERE id_customer=' . (int)$customerId . ' AND id_group=' . (int)$groupId
    );

    if (!$exists) {
      Db::getInstance()->insert('customer_group', [
        'id_customer' => (int)$customerId,
        'id_group' => (int)$groupId,
      ]);
      Logger::log("✅ ADD GROUP - Cliente {$customerId} añadido al grupo {$groupId}");
    } else {
      Logger::log("ℹ️ ADD GROUP - Cliente {$customerId} ya estaba en grupo {$groupId}");
    }
  }

  public function removeFromGroup($customerId, $groupId)
  {
    Logger::log("➖ REMOVE GROUP - Eliminando cliente {$customerId} del grupo {$groupId}");

    $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'customer_group`
                WHERE id_customer = ' . (int)$customerId . '
                AND id_group = ' . (int)$groupId;

    $result = Db::getInstance()->execute($sql);

    if ($result) {
      Logger::log("✅ REMOVE GROUP - Cliente {$customerId} eliminado del grupo {$groupId}");
    } else {
      Logger::log("❌ REMOVE GROUP - Error eliminando cliente {$customerId} del grupo {$groupId}");
    }

    return $result;
  }
}
