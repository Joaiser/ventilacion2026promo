<?php

class Logger
{
  public static function log($message)
  {
    $logFile = _PS_MODULE_DIR_ . 'ventilacion2026promo/logs/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $msg = "[{$timestamp}] {$message}\n";
    $dir = dirname($logFile);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
  }
}
