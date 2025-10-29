<?php

require_once dirname(__FILE__) . '/../helpers/Constants.php';
require_once dirname(__FILE__) . '/../helpers/Logger.php';
require_once dirname(__FILE__) . '/StockManager.php';
require_once dirname(__FILE__) . '/PromoGroupHelper.php';

class PromoCalculator
{
  private $promoGroupId;

  public function __construct($promoGroupId = null)
  {
    // ✅ USAR grupo proporcionado o determinar automáticamente
    $this->promoGroupId = $promoGroupId ?: PromoGroupHelper::determinePromoGroupForCalculator();

    //Logger::log("🎯 PromoCalculator inicializado - Grupo promocional: {$this->promoGroupId}");
  }

  /**
   * Calcula el total de la promo SUMANDO todos los productos VT- con sus precios reales
   * CONSIDERANDO reglas de surtido entre productos con el MISMO valor de surtido
   */
  public function getPromoTotal($cart)
  {
    $products = $cart->getProducts();
    $total = 0;
    $vtProductsCount = 0;

    //Logger::log("🧩 ===== CALCULO TOTAL PROMO INICIADO =====");
    //Logger::log("🧩 Carrito: {$cart->id}, Productos: " . count($products));
    //Logger::log("🎯 Grupo promocional activo: {$this->promoGroupId}");

    // Agrupar productos VT- por su valor de surtido
    $productsBySurtido = $this->groupProductsBySurtido($products);

    foreach ($productsBySurtido as $surtidoValue => $surtidoProducts) {
      $this->debugSurtidoPrices($surtidoValue);
    }

    foreach ($productsBySurtido as $surtidoValue => $surtidoProducts) {
      //Logger::log("🎯 PROCESANDO SURTIDO: '{$surtidoValue}' - " . count($surtidoProducts) . " productos");

      // Calcular cantidad TOTAL para este surtido
      $totalSurtidoQuantity = 0;
      foreach ($surtidoProducts as $product) {
        $totalSurtidoQuantity += (int)$product['cart_quantity'];
      }
      //Logger::log("   📦 Cantidad total en surtido '{$surtidoValue}': {$totalSurtidoQuantity}");

      // Obtener el precio base para TODO el surtido
      $surtidoPrice = $this->findSurtidoPriceForGroup($surtidoValue, $totalSurtidoQuantity);

      // Procesar cada producto del surtido
      foreach ($surtidoProducts as $p) {
        $vtProductsCount++;
        $idProduct = (int)$p['id_product'];
        $idAttr = (int)$p['id_product_attribute'];
        $qty = (int)$p['cart_quantity'];

        //Logger::log("   📦 Producto ID{$idProduct}, Attr{$idAttr}, Qty{$qty}, Surtido: {$surtidoValue}");

        // Obtener el precio REAL considerando el surtido
        $pricePromo = $this->getRealPriceWithSurtido(
          $idProduct,
          $idAttr,
          $qty,
          $totalSurtidoQuantity,
          $surtidoValue,
          $surtidoPrice
        );

        // Calcular total con impuestos
        $taxRate = isset($p['rate']) ? $p['rate'] : 0;
        $priceWithTax = $pricePromo * (1 + ($taxRate / 100));
        $lineTotal = $priceWithTax * $qty;

        $total += $lineTotal;

        //Logger::log("   💵 RESUMEN: {$qty} x {$pricePromo}€ = {$lineTotal}€ (IVA: {$taxRate}%)");
        //Logger::log("   📈 ACUMULADO: {$total}€");
        //Logger::log("   ---");
      }
    }

    //Logger::log("💰 TOTAL FINAL PROMO: {$total}€ de {$vtProductsCount} productos VT-");
    //Logger::log("🎯 OBJETIVO: " . Constants::PROMO_MIN_TOTAL . "€");
    //Logger::log("🧩 ===== CALCULO TOTAL PROMO FINALIZADO =====");

    return $total;
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
   * Busca precio de surtido para CUALQUIER producto del mismo surtido
   * BUSCA EN TODOS LOS GRUPOS, no solo en el grupo promocional
   */
  private function findSurtidoPriceForGroup($surtidoValue, $totalQuantity)
  {
    if ($surtidoValue === 'SIN_SURTIDO') {
      return null;
    }

    // Buscar cualquier producto que tenga este surtido y tenga precios específicos
    $sql = '
        SELECT sp.price, sp.from_quantity, sp.id_group 
        FROM ' . _DB_PREFIX_ . 'specific_price sp
        INNER JOIN ' . _DB_PREFIX_ . 'feature_product fp ON sp.id_product = fp.id_product
        INNER JOIN ' . _DB_PREFIX_ . 'feature f ON fp.id_feature = f.id_feature
        INNER JOIN ' . _DB_PREFIX_ . 'feature_lang fl ON (f.id_feature = fl.id_feature AND fl.id_lang = 1)
        INNER JOIN ' . _DB_PREFIX_ . 'feature_value_lang fvl ON (fp.id_feature_value = fvl.id_feature_value AND fvl.id_lang = 1)
        WHERE fvl.value = "' . pSQL($surtidoValue) . '"
        AND fl.name = "Surtido"
        AND (sp.id_group = 0 OR sp.id_group = ' . (int)$this->promoGroupId . ')  -- ✅ BUSCAR EN GRUPO 0 Y GRUPO PROMOCIONAL
        AND sp.from_quantity <= ' . (int)$totalQuantity . '
        AND (sp.id_shop = 0 OR sp.id_shop = ' . (int)Context::getContext()->shop->id . ')
        AND (sp.id_currency = 0 OR sp.id_currency = ' . (int)Context::getContext()->currency->id . ')
        AND (sp.id_country = 0 OR sp.id_country = ' . (int)Context::getContext()->country->id . ')
        ORDER BY sp.from_quantity DESC, sp.id_group DESC
    ';

    ////Logger::log("   🔍 SQL SURTIDO GRUPAL: " . str_replace(["\n", "  "], " ", $sql));
    $result = Db::getInstance()->executeS($sql);

    if ($result && count($result) > 0) {
      $specificPrice = $result[0]; // Tomamos el primero (mayor from_quantity)
      if (!empty($specificPrice['price']) && $specificPrice['price'] != '0.000000') {
        //Logger::log("   ✅ PRECIO SURTIDO ENCONTRADO: {$specificPrice['price']}€ para cantidad {$specificPrice['from_quantity']} en grupo {$specificPrice['id_group']}");
        return (float)$specificPrice['price'];
      }
    }

    //Logger::log("   ❌ NO SE ENCONTRÓ PRECIO SURTIDO para '{$surtidoValue}' con cantidad {$totalQuantity}");
    return null;
  }

  /**
   * MÉTODO DE DIAGNÓSTICO - Verifica qué precios específicos existen para un surtido
   */
  public function debugSurtidoPrices($surtidoValue)
  {
    //Logger::log("🔍 === DIAGNÓSTICO SURTIDO '{$surtidoValue}' ====");

    // 1. Verificar productos con este surtido
    $sqlProducts = '
        SELECT p.id_product, p.reference, fvl.value as surtido
        FROM ' . _DB_PREFIX_ . 'product p
        INNER JOIN ' . _DB_PREFIX_ . 'feature_product fp ON p.id_product = fp.id_product
        INNER JOIN ' . _DB_PREFIX_ . 'feature f ON fp.id_feature = f.id_feature
        INNER JOIN ' . _DB_PREFIX_ . 'feature_lang fl ON (f.id_feature = fl.id_feature AND fl.id_lang = 1)
        INNER JOIN ' . _DB_PREFIX_ . 'feature_value_lang fvl ON (fp.id_feature_value = fvl.id_feature_value AND fvl.id_lang = 1)
        WHERE fvl.value = "' . pSQL($surtidoValue) . '"
        AND fl.name = "Surtido"
        LIMIT 10
    ';

    $products = Db::getInstance()->executeS($sqlProducts);
    //Logger::log("📦 Productos con surtido '{$surtidoValue}': " . count($products));
    foreach ($products as $product) {
      //Logger::log("   - ID: {$product['id_product']}, Ref: {$product['reference']}");

      // 2. Verificar precios específicos para cada producto
      $sqlPrices = '
            SELECT id_specific_price, from_quantity, price, id_group
            FROM ' . _DB_PREFIX_ . 'specific_price 
            WHERE id_product = ' . (int)$product['id_product'] . '
            AND id_group = ' . (int)$this->promoGroupId . '  -- ✅ USAR GRUPO DINÁMICO
            ORDER BY from_quantity ASC
        ';

      $prices = Db::getInstance()->executeS($sqlPrices);
      //Logger::log("   💰 Precios específicos: " . count($prices));
      foreach ($prices as $price) {
        //Logger::log("     > Cantidad: {$price['from_quantity']}, Precio: {$price['price']}€, Grupo: {$price['id_group']}");
      }
    }

    //Logger::log("🔍 === FIN DIAGNÓSTICO ====");
  }

  /**
   * Obtiene el precio REAL que se aplicaría CONSIDERANDO SURTIDOS
   */
  private function getRealPriceWithSurtido($idProduct, $idAttr, $productQty, $totalSurtidoQuantity, $surtidoValue, $surtidoPrice = null)
  {
    //Logger::log("   🔍 Buscando precio REAL CON SURTIDO '{$surtidoValue}' - producto {$idProduct}, total surtido: {$totalSurtidoQuantity}");

    // ✅ PRIMERO: Buscar precio específico para este producto considerando la cantidad TOTAL del surtido
    $surtidoPriceIndividual = $this->findSurtidoPrice($idProduct, $idAttr, $totalSurtidoQuantity);
    if ($surtidoPriceIndividual !== null) {
      //Logger::log("   🎯 PRECIO SURTIDO INDIVIDUAL APLICADO: {$surtidoPriceIndividual}€ (surtido '{$surtidoValue}', {$totalSurtidoQuantity} unidades)");
      return $surtidoPriceIndividual;
    }

    // SEGUNDO: Si no hay precio de surtido, buscar precio normal por cantidad del producto individual
    $normalPrice = $this->findNormalPrice($idProduct, $idAttr, $productQty);
    if ($normalPrice !== null) {
      //Logger::log("   💰 PRECIO NORMAL APLICADO: {$normalPrice}€ (por {$productQty} unidades individuales)");
      return $normalPrice;
    }

    // TERCERO: Usar precio base como fallback
    $priceBase = $this->getProductBasePrice($idProduct, $idAttr);
    //Logger::log("   ⚠️  Sin precios específicos → usando PRECIO BASE: {$priceBase}€");

    return $priceBase;
  }
  /**
   * Busca precio considerando surtidos (cantidad TOTAL del surtido) para un producto específico
   * BUSCA EN TODOS LOS GRUPOS, no solo en el grupo promocional
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

    ////Logger::log("   🔍 SQL SURTIDO INDIVIDUAL: " . str_replace(["\n", "  "], " ", $sql));
    $specificPrice = Db::getInstance()->getRow($sql);

    if ($specificPrice && !empty($specificPrice['price']) && $specificPrice['price'] != '0.000000') {
      //Logger::log("   ✅ PRECIO ESPECÍFICO ENCONTRADO: {$specificPrice['price']}€ para cantidad {$specificPrice['from_quantity']} en grupo {$specificPrice['id_group']}");
      return (float)$specificPrice['price'];
    }

    //Logger::log("   ❌ NO SE ENCONTRÓ PRECIO ESPECÍFICO - usando precio base");
    return null;
  }

  /**
   * Busca precio normal (cantidad individual del producto)
   * BUSCA EN TODOS LOS GRUPOS, no solo en el grupo promocional
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

    ////Logger::log("   🔍 SQL NORMAL: " . str_replace(["\n", "  "], " ", $sql));
    $specificPrice = Db::getInstance()->getRow($sql);

    if ($specificPrice && !empty($specificPrice['price']) && $specificPrice['price'] != '0.000000') {
      //Logger::log("   ✅ PRECIO ESPECÍFICO NORMAL ENCONTRADO: {$specificPrice['price']}€ para cantidad {$specificPrice['from_quantity']} en grupo {$specificPrice['id_group']}");
      return (float)$specificPrice['price'];
    }

    //Logger::log("   ❌ NO SE ENCONTRÓ PRECIO ESPECÍFICO NORMAL - usando precio base");
    return null;
  }
  /**
   * Obtiene el precio base del producto o combinación USANDO SQL
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
      //Logger::log("   📊 Precio base combinación {$idAttr}: {$priceBase}€");
    } else {
      // Precio de producto base
      $sql = '
          SELECT p.price
          FROM ' . _DB_PREFIX_ . 'product p
          WHERE p.id_product = ' . (int)$idProduct . '
      ';
      $priceBase = (float)Db::getInstance()->getValue($sql);
      //Logger::log("   📊 Precio base producto {$idProduct}: {$priceBase}€");
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
        //Logger::log("   🔄 Usando precio de tienda: {$shopPrice}€");
        return $shopPrice;
      }
    }

    return $priceBase;
  }

  /**
   * Calcula el total para productos seleccionados en la página de promoción
   * CONSIDERANDO reglas de surtido
   */
  public function calculateTotalForSelectedProducts($selectedProducts)
  {
    //Logger::log("🧮 CALCULANDO TOTAL PARA PRODUCTOS SELECCIONADOS: " . count($selectedProducts));
    //Logger::log("🎯 Grupo promocional activo: {$this->promoGroupId}");

    // Debug detallado de los productos recibidos
    foreach ($selectedProducts as $product) {
      //Logger::log("📦 Producto recibido - ID: {$product['id_product']}, Attr: {$product['id_product_attribute']}, Qty: {$product['quantity']}");
    }

    $total = 0;

    // Agrupar productos por surtido
    $productsBySurtido = [];
    foreach ($selectedProducts as $product) {
      $surtidoValue = $this->getSurtidoValue($product['id_product']);
      //Logger::log("🎯 Producto {$product['id_product']} tiene surtido: '{$surtidoValue}'");

      if (!isset($productsBySurtido[$surtidoValue])) {
        $productsBySurtido[$surtidoValue] = [];
      }

      $productsBySurtido[$surtidoValue][] = $product;
    }

    //Logger::log("🎯 Total grupos de surtido: " . count($productsBySurtido));

    // Calcular por cada surtido
    foreach ($productsBySurtido as $surtidoValue => $surtidoProducts) {
      //Logger::log("🎯 PROCESANDO SURTIDO: '{$surtidoValue}' - " . count($surtidoProducts) . " productos");

      // Calcular cantidad total para este surtido
      $totalSurtidoQuantity = 0;
      foreach ($surtidoProducts as $product) {
        $totalSurtidoQuantity += $product['quantity'];
      }
      //Logger::log("   📦 Cantidad total en surtido '{$surtidoValue}': {$totalSurtidoQuantity}");

      // Calcular total para cada producto del surtido
      foreach ($surtidoProducts as $product) {
        $idProduct = (int)$product['id_product'];
        $idAttr = (int)$product['id_product_attribute'];
        $qty = (int)$product['quantity'];

        //Logger::log("   🔍 Calculando precio para producto {$idProduct}, attr {$idAttr}, qty {$qty}");

        $pricePromo = $this->getRealPriceWithSurtido(
          $idProduct,
          $idAttr,
          $qty,
          $totalSurtidoQuantity,
          $surtidoValue
        );

        $lineTotal = $pricePromo * $qty;
        $total += $lineTotal;

        //Logger::log("   💰 Producto {$idProduct}: {$qty} x {$pricePromo}€ = {$lineTotal}€");
      }
    }

    //Logger::log("💰 TOTAL CALCULADO CON SURTIDOS: {$total}€");

    return [
      'success' => true,
      'total' => $total,
      'available_products' => $selectedProducts,
      'out_of_stock_count' => 0,
      'has_stock_issues' => false,
      'stock_validation' => [],
      'message' => ''
    ];
  }
}
