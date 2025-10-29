<?php

require_once dirname(__FILE__) . '/../helpers/Constants.php';
require_once dirname(__FILE__) . '/../helpers/Logger.php';
require_once dirname(__FILE__) . '/PromoGroupHelper.php';

class PromoHookCalculator
{
  private $promoGroupId;

  public function __construct($promoGroupId = null)
  {
    // ✅ USAR grupo proporcionado o determinar automáticamente
    $this->promoGroupId = $promoGroupId ?: PromoGroupHelper::determinePromoGroupForCalculator();

    Logger::log("🎯 PromoHookCalculator inicializado - Grupo: {$this->promoGroupId}");
  }

  /**
   * Calcula el total para validación de hook - SOLO para productos VT-
   * USA LA MISMA LÓGICA ROBUSTA DE PromoCalculator
   */
  public function getPromoTotalForHook($cart)
  {
    try {
      Logger::log("🧩 HOOK - INICIANDO cálculo total para carrito {$cart->id}");

      $products = $cart->getProducts();
      Logger::log("🧩 HOOK - Productos obtenidos: " . count($products));

      $total = 0;
      $vtProductsCount = 0;

      // Agrupar productos VT- por surtido
      $productsBySurtido = $this->groupProductsBySurtido($products);
      Logger::log("🧩 HOOK - Productos VT- agrupados por surtido: " . count($productsBySurtido));

      if (empty($productsBySurtido)) {
        Logger::log("❌ No hay productos VT- en el carrito");
        return 0;
      }

      foreach ($productsBySurtido as $surtidoValue => $surtidoProducts) {
        Logger::log("🎯 PROCESANDO SURTIDO: '{$surtidoValue}' - " . count($surtidoProducts) . " productos");

        // Calcular cantidad TOTAL para este surtido
        $totalSurtidoQuantity = 0;
        foreach ($surtidoProducts as $product) {
          $totalSurtidoQuantity += (int)$product['cart_quantity'];
        }
        Logger::log("   📦 Cantidad total en surtido '{$surtidoValue}': {$totalSurtidoQuantity}");

        // Procesar cada producto del surtido
        foreach ($surtidoProducts as $p) {
          $vtProductsCount++;
          $idProduct = (int)$p['id_product'];
          $idAttr = (int)$p['id_product_attribute'];
          $qty = (int)$p['cart_quantity'];

          Logger::log("   📦 Producto ID{$idProduct}, Attr{$idAttr}, Qty{$qty}, Surtido: {$surtidoValue}");

          // Obtener el precio REAL usando la lógica robusta de PromoCalculator
          $pricePromo = $this->getRealPriceWithSurtido(
            $idProduct,
            $idAttr,
            $qty,
            $totalSurtidoQuantity,
            $surtidoValue
          );

          // Calcular total con impuestos
          $taxRate = isset($p['rate']) ? $p['rate'] : 0;
          $priceWithTax = $pricePromo * (1 + ($taxRate / 100));
          $lineTotal = $priceWithTax * $qty;

          $total += $lineTotal;

          Logger::log("   💵 RESUMEN: {$qty} x {$pricePromo}€ = {$lineTotal}€ (IVA: {$taxRate}%)");
          Logger::log("   📈 ACUMULADO: {$total}€");
        }
      }

      Logger::log("💰 TOTAL HOOK: {$total}€ de {$vtProductsCount} productos VT-");
      Logger::log("🧩 HOOK - FINALIZADO cálculo total");
      return $total;
    } catch (Exception $e) {
      Logger::log("❌ ERROR en getPromoTotalForHook: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Agrupa productos VT- por su valor de surtido
   */
  private function groupProductsBySurtido($products)
  {
    $grouped = [];

    foreach ($products as $p) {
      // Solo productos VT-
      if (strpos($p['reference'], Constants::VT_PRODUCT_PREFIX) !== 0) {
        continue;
      }

      // Obtener valor de surtido
      $surtidoValue = $this->getSurtidoValue($p['id_product']);

      if (!isset($grouped[$surtidoValue])) {
        $grouped[$surtidoValue] = [];
      }

      $grouped[$surtidoValue][] = $p;
    }

    return $grouped;
  }

  /**
   * Obtiene el valor de surtido de un producto
   */
  private function getSurtidoValue($productId)
  {
    $sql = '
        SELECT fvl.value
        FROM ' . _DB_PREFIX_ . 'feature_product fp
        INNER JOIN ' . _DB_PREFIX_ . 'feature f ON fp.id_feature = f.id_feature
        INNER JOIN ' . _DB_PREFIX_ . 'feature_lang fl ON (f.id_feature = fl.id_feature AND fl.id_lang = 1)
        INNER JOIN ' . _DB_PREFIX_ . 'feature_value_lang fvl ON (fp.id_feature_value = fvl.id_feature_value AND fvl.id_lang = 1)
        WHERE fp.id_product = ' . (int)$productId . '
        AND fl.name = "Surtido"
    ';

    $surtidoValue = Db::getInstance()->getValue($sql);
    return $surtidoValue ?: 'SIN_SURTIDO';
  }

  /**
   * Obtiene el precio REAL que se aplicaría CONSIDERANDO SURTIDOS
   * MÉTODO COPIADO DE PromoCalculator (que funciona bien)
   */
  private function getRealPriceWithSurtido($idProduct, $idAttr, $productQty, $totalSurtidoQuantity, $surtidoValue)
  {
    Logger::log("   🔍 Buscando precio REAL CON SURTIDO '{$surtidoValue}' - producto {$idProduct}, total surtido: {$totalSurtidoQuantity}");

    // ✅ PRIMERO: Buscar precio específico para este producto considerando la cantidad TOTAL del surtido
    $surtidoPriceIndividual = $this->findSurtidoPrice($idProduct, $idAttr, $totalSurtidoQuantity);
    if ($surtidoPriceIndividual !== null) {
      Logger::log("   🎯 PRECIO SURTIDO INDIVIDUAL APLICADO: {$surtidoPriceIndividual}€ (surtido '{$surtidoValue}', {$totalSurtidoQuantity} unidades)");
      return $surtidoPriceIndividual;
    }

    // SEGUNDO: Si no hay precio de surtido, buscar precio normal por cantidad del producto individual
    $normalPrice = $this->findNormalPrice($idProduct, $idAttr, $productQty);
    if ($normalPrice !== null) {
      Logger::log("   💰 PRECIO NORMAL APLICADO: {$normalPrice}€ (por {$productQty} unidades individuales)");
      return $normalPrice;
    }

    // TERCERO: Usar precio base como fallback
    $priceBase = $this->getProductBasePrice($idProduct, $idAttr);
    Logger::log("   ⚠️  Sin precios específicos → usando PRECIO BASE: {$priceBase}€");

    return $priceBase;
  }

  /**
   * Busca precio considerando surtidos (cantidad TOTAL del surtido) para un producto específico
   * BUSCA EN TODOS LOS GRUPOS, no solo en el grupo promocional
   * MÉTODO COPIADO DE PromoCalculator (que funciona bien)
   */
  private function findSurtidoPrice($idProduct, $idAttr, $totalSurtidoQuantity)
  {
    $sql = '
        SELECT price, from_quantity, id_group 
        FROM ' . _DB_PREFIX_ . 'specific_price 
        WHERE id_product = ' . (int)$idProduct . '
        AND (id_product_attribute = 0 OR id_product_attribute = ' . (int)$idAttr . ')
        AND (id_group = 0 OR id_group = ' . (int)$this->promoGroupId . ')  -- ✅ BUSCAR EN GRUPO 0 Y GRUPO PROMOCIONAL
        AND from_quantity <= ' . (int)$totalSurtidoQuantity . '
        AND (id_shop = 0 OR id_shop = ' . (int)Context::getContext()->shop->id . ')
        AND (id_currency = 0 OR id_currency = ' . (int)Context::getContext()->currency->id . ')
        AND (id_country = 0 OR id_country = ' . (int)Context::getContext()->country->id . ')
        ORDER BY from_quantity DESC, id_group DESC
    ';

    $specificPrice = Db::getInstance()->getRow($sql);

    if ($specificPrice && !empty($specificPrice['price']) && $specificPrice['price'] != '0.000000') {
      Logger::log("   ✅ PRECIO ESPECÍFICO ENCONTRADO: {$specificPrice['price']}€ para cantidad {$specificPrice['from_quantity']} en grupo {$specificPrice['id_group']}");
      return (float)$specificPrice['price'];
    }

    Logger::log("   ❌ NO SE ENCONTRÓ PRECIO ESPECÍFICO - usando precio base");
    return null;
  }

  /**
   * Busca precio normal (cantidad individual del producto)
   * BUSCA EN TODOS LOS GRUPOS, no solo en el grupo promocional
   * MÉTODO COPIADO DE PromoCalculator (que funciona bien)
   */
  private function findNormalPrice($idProduct, $idAttr, $productQty)
  {
    $sql = '
        SELECT price, from_quantity, id_group 
        FROM ' . _DB_PREFIX_ . 'specific_price 
        WHERE id_product = ' . (int)$idProduct . '
        AND (id_product_attribute = 0 OR id_product_attribute = ' . (int)$idAttr . ')
        AND (id_group = 0 OR id_group = ' . (int)$this->promoGroupId . ')  -- ✅ BUSCAR EN GRUPO 0 Y GRUPO PROMOCIONAL
        AND from_quantity <= ' . (int)$productQty . '
        AND (id_shop = 0 OR id_shop = ' . (int)Context::getContext()->shop->id . ')
        AND (id_currency = 0 OR id_currency = ' . (int)Context::getContext()->currency->id . ')
        AND (id_country = 0 OR id_country = ' . (int)Context::getContext()->country->id . ')
        ORDER BY from_quantity DESC, id_group DESC
    ';

    $specificPrice = Db::getInstance()->getRow($sql);

    if ($specificPrice && !empty($specificPrice['price']) && $specificPrice['price'] != '0.000000') {
      Logger::log("   ✅ PRECIO ESPECÍFICO NORMAL ENCONTRADO: {$specificPrice['price']}€ para cantidad {$specificPrice['from_quantity']} en grupo {$specificPrice['id_group']}");
      return (float)$specificPrice['price'];
    }

    Logger::log("   ❌ NO SE ENCONTRÓ PRECIO ESPECÍFICO NORMAL - usando precio base");
    return null;
  }

  /**
   * Obtiene el precio base del producto o combinación USANDO SQL
   * MÉTODO COPIADO DE PromoCalculator (que funciona bien)
   */
  private function getProductBasePrice($idProduct, $idAttr)
  {
    $context = Context::getContext();

    if ($idAttr > 0) {
      // Precio de combinación
      $sql = '
          SELECT pa.price
          FROM ' . _DB_PREFIX_ . 'product_attribute pa
          WHERE pa.id_product_attribute = ' . (int)$idAttr . '
      ';
      $priceBase = (float)Db::getInstance()->getValue($sql);
      Logger::log("   📊 Precio base combinación {$idAttr}: {$priceBase}€");
    } else {
      // Precio de producto base
      $sql = '
          SELECT p.price
          FROM ' . _DB_PREFIX_ . 'product p
          WHERE p.id_product = ' . (int)$idProduct . '
      ';
      $priceBase = (float)Db::getInstance()->getValue($sql);
      Logger::log("   📊 Precio base producto {$idProduct}: {$priceBase}€");
    }

    // Si el precio base es 0, buscar en product_shop
    if ($priceBase == 0) {
      $sql = '
          SELECT ps.price
          FROM ' . _DB_PREFIX_ . 'product_shop ps
          WHERE ps.id_product = ' . (int)$idProduct . '
          AND ps.id_shop = ' . (int)$context->shop->id . '
      ';
      $shopPrice = (float)Db::getInstance()->getValue($sql);
      if ($shopPrice > 0) {
        Logger::log("   🔄 Usando precio de tienda: {$shopPrice}€");
        return $shopPrice;
      }
    }

    return $priceBase;
  }
}
