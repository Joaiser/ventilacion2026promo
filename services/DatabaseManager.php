<?php

require_once dirname(__FILE__) . '/../helpers/Logger.php';

class DatabaseManager
{
  private const TABLE_LAST_GROUP = 'customer_removed_group';

  public function createLastGroupTable()
  {
    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_LAST_GROUP . '` (
                  `id_customer` INT(10) UNSIGNED NOT NULL,
                  `id_group` INT(10) UNSIGNED NOT NULL,
                  PRIMARY KEY (`id_customer`)
                ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';
    return Db::getInstance()->execute($sql);
  }

  public function dropLastGroupTable()
  {
    return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE_LAST_GROUP . '`');
  }

  public function saveLastGroup($customerId, $groupId)
  {
    $sql = 'REPLACE INTO `' . _DB_PREFIX_ . self::TABLE_LAST_GROUP . '` (id_customer, id_group)
                VALUES (' . (int)$customerId . ', ' . (int)$groupId . ')';
    return Db::getInstance()->execute($sql);
  }

  public function getLastGroup($customerId)
  {
    $sql = 'SELECT id_group FROM `' . _DB_PREFIX_ . self::TABLE_LAST_GROUP . '` WHERE id_customer = ' . (int)$customerId;
    return Db::getInstance()->getValue($sql);
  }

  public function deleteLastGroup($customerId)
  {
    $sql = 'DELETE FROM `' . _DB_PREFIX_ . self::TABLE_LAST_GROUP . '` WHERE id_customer = ' . (int)$customerId;
    return Db::getInstance()->execute($sql);
  }
}
