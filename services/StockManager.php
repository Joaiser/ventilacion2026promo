<?php

class StockManager
{
  private $db;
  private $context;

  public function __construct()
  {
    $this->db = Db::getInstance();
    $this->context = Context::getContext();
  }

  /**
   * Verifica stock disponible para un producto/combinación - VERSIÓN PRESTASHOP 8
   */
  public function checkStock($productId, $combinationId = 0, $quantity = 1)
  {
    try {
      // Logger::log("🔍 CHECK STOCK INICIADO - Producto: {$productId}, Combinación: {$combinationId}, Cantidad: {$quantity}");

      // Obtener información completa del stock - VERSIÓN CORREGIDA PARA PS8
      $sql = '
            SELECT 
                p.id_product,
                p.reference,
                sa.quantity as available_quantity,
                sa.out_of_stock,
                sa.physical_quantity,
                sa.reserved_quantity,
                ps.advanced_stock_management as manage_stock
            FROM ' . _DB_PREFIX_ . 'product p
            LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa ON (
                p.id_product = sa.id_product 
                AND sa.id_product_attribute = ' . (int)$combinationId . '
                AND sa.id_shop = ' . (int)$this->context->shop->id . '
            )
            LEFT JOIN ' . _DB_PREFIX_ . 'product_shop ps ON (
                p.id_product = ps.id_product 
                AND ps.id_shop = ' . (int)$this->context->shop->id . '
            )
            WHERE p.id_product = ' . (int)$productId;

      //// Logger::log("📝 SQL ejecutado: " . $sql);

      $stockInfo = $this->db->getRow($sql);

      if ($this->db->getNumberError()) {
        // Logger::log("❌ ERROR SQL: " . $this->db->getMsgError());
        throw new Exception("Error SQL: " . $this->db->getMsgError());
      }

      // Logger::log("📊 INFO STOCK COMPLETA: " . json_encode($stockInfo));

      if (!$stockInfo) {
        // Logger::log("❌ Producto no encontrado en BD");
        return [
          'available' => false,
          'stock' => 0,
          'message' => 'Producto no encontrado',
          'manage_stock' => false,
          'out_of_stock' => 1
        ];
      }

      $availableStock = (int)$stockInfo['available_quantity'];
      $physicalStock = (int)$stockInfo['physical_quantity'];
      $outOfStock = (int)$stockInfo['out_of_stock'];
      $manageStock = (bool)$stockInfo['manage_stock'];

      // Logger::log("📦 Stock disponible: {$availableStock}, Gestión stock: " . ($manageStock ? 'SÍ' : 'NO'));

      // LÓGICA de disponibilidad - VERSIÓN MEJORADA
      $available = true;
      $message = '';

      if ($manageStock) {
        // SI se gestiona stock avanzado
        if ($availableStock <= 0) {
          $available = ($outOfStock == 1); // Permitir solo si out_of_stock = 1
          $message = $available ? 'Producto agotado - pedido permitido' : 'Producto agotado';
        } elseif ($quantity > $availableStock) {
          $available = ($outOfStock == 1); // Permitir solo si out_of_stock = 1
          $message = $available ?
            "Stock insuficiente ({$availableStock} disponibles) - pedido permitido" :
            "Solo quedan {$availableStock} unidades";
        } else {
          $message = "Stock suficiente ({$availableStock} disponibles)";
        }
      } else {
        // NO se gestiona stock avanzado - usar lógica simple
        if ($availableStock <= 0) {
          $available = ($outOfStock == 1); // Permitir según configuración
          $message = $available ? 'Producto agotado - pedido permitido' : 'Producto agotado';
        } elseif ($quantity > $availableStock) {
          $available = ($outOfStock == 1); // Permitir según configuración
          $message = $available ?
            "Stock insuficiente ({$availableStock} disponibles) - pedido permitido" :
            "Solo quedan {$availableStock} unidades";
        } else {
          $message = "Stock suficiente ({$availableStock} disponibles)";
        }
      }

      $result = [
        'available' => $available,
        'stock' => $availableStock,
        'message' => $message,
        'manage_stock' => $manageStock,
        'out_of_stock' => $outOfStock,
        'physical_stock' => $physicalStock,
        'requested_quantity' => $quantity
      ];

      // Logger::log("✅ RESULTADO STOCK FINAL: " . json_encode($result));
      return $result;
    } catch (Exception $e) {
      // Logger::log("❌ EXCEPCIÓN EN CHECK STOCK: " . $e->getMessage());
      // Logger::log("❌ TRAZA: " . $e->getTraceAsString());

      // Por seguridad, asumir disponible si hay error
      return [
        'available' => true,
        'stock' => 999,
        'message' => 'Error verificando stock - asumiendo disponible: ' . $e->getMessage(),
        'manage_stock' => false,
        'out_of_stock' => 1
      ];
    }
  }

  /**
   * Verifica stock para múltiples productos (para el cálculo total)
   */
  public function checkMultipleStocks($products)
  {
    $results = [];
    foreach ($products as $productData) {
      $id_product = (int)$productData['id_product'];
      $id_product_attribute = (int)$productData['id_product_attribute'];
      $quantity = (int)$productData['quantity'];

      $results["{$id_product}_{$id_product_attribute}"] = $this->checkStock(
        $id_product,
        $id_product_attribute,
        $quantity
      );
    }

    return $results;
  }

  /**
   * Filtra productos sin stock de la selección
   */
  public function filterAvailableProducts($selectedProducts)
  {
    $availableProducts = [];

    foreach ($selectedProducts as $product) {
      $stockInfo = $this->checkStock(
        $product['id_product'],
        $product['id_product_attribute'],
        $product['quantity']
      );

      if ($stockInfo['available']) {
        $availableProducts[] = $product;
      }
    }

    return $availableProducts;
  }
}
