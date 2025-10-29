<?php

use PrestaShop\PrestaShop\Adapter\Product\ProductDataProvider;


require_once dirname(__FILE__) . '/../../helpers/Constants.php';
require_once dirname(__FILE__) . '/../../helpers/Logger.php';
require_once dirname(__FILE__) . '/../../services/DatabaseManager.php';
require_once dirname(__FILE__) . '/../../services/GroupManager.php';
require_once dirname(__FILE__) . '/../../services/StockManager.php';
require_once dirname(__FILE__) . '/../../services/PromoGroupHelper.php';

class Ventilacion2026PromoVENTILACION2026PROMOModuleFrontController extends ModuleFrontController
{
  private $databaseManager;
  private $groupManager;

  public function __construct()
  {
    parent::__construct();
    $this->databaseManager = new DatabaseManager();
    $this->groupManager = new GroupManager($this->databaseManager);
  }

  public function setMedia()
  {
    //// Logger::log('Ejecutando setMedia');

    parent::setMedia();

    // Registrar JavaScript
    $this->registerJavascript(
      'modules-ventilacion2026promo-js',
      'modules/ventilacion2026promo/views/js/ventilacion2026promo.js',
      [
        'position' => 'bottom',
        'priority' => 200
      ]
    );

    // Registrar CSS
    $this->registerStylesheet(
      'modules-ventilacion2026promo-css',
      'modules/ventilacion2026promo/views/css/ventilacion2026promo.css',
      [
        'media' => 'all',
        'priority' => 200
      ]
    );
  }


  public function initContent()
  {
    parent::initContent();

    $customer = $this->context->customer;

    // 🚀 DETERMINAR GRUPO PROMOCIONAL SEGÚN ORIGEN
    $promoGroupId = PromoGroupHelper::determinePromoGroupFromCustomer($customer);

    // Logger::log("🎯 Grupo promocional determinado: {$promoGroupId} para cliente {$customer->id}");

    // 🚀 FORZAR AL CLIENTE AL GRUPO PROMOCIONAL CORRESPONDIENTE
    if ($customer->id) {
      $currentGroup = (int)$customer->id_default_group;

      // DEBUG DETALLADO
      $lastGroup = $this->databaseManager->getLastGroup($customer->id);
      // $shouldSave = !$lastGroup && $currentGroup != $promoGroupId;

      // Logger::log("🔍 DEBUG Página promo - Cliente: {$customer->id}, Grupo actual: {$currentGroup}, Último grupo: " . ($lastGroup ?: 'NULL') . ", Debe guardar: " . ($shouldSave ? 'SÍ' : 'NO'));

      // Guardar grupo original SOLO si no está ya guardado Y si no es el grupo promocional
      if (!$lastGroup && $currentGroup != $promoGroupId) {
        $this->databaseManager->saveLastGroup($customer->id, $currentGroup);
        // Logger::log("💾 Guardado grupo original {$currentGroup} del cliente {$customer->id}");
      }

      // Mover al grupo promocional si no está ya en él
      if ($currentGroup != $promoGroupId) {
        $this->groupManager->addCustomerToGroup($customer->id, $promoGroupId);
        $customer->id_default_group = $promoGroupId;
        $customer->update();
        // Logger::log("🚀 Cliente {$customer->id} movido al grupo promo ({$promoGroupId}) al entrar en la página");
      }
    }

    // Obtener productos (ya mostrarán precios del grupo promocional porque el cliente está en ese grupo)
    $products = $this->getProductsByReferencePrefix('VT-');

    // Asignar productos al template
    $this->context->smarty->assign([
      'products' => $products,
      'cart_url' => $this->context->link->getPageLink('cart'),
      'static_token' => Tools::getToken(false),
      'promo_ajax_url' => $this->context->link->getModuleLink('ventilacion2026promo', 'VENTILACION2026PROMO'),
      'promo_group_id' => $promoGroupId,
    ]);

    $this->setTemplate('module:ventilacion2026promo/views/templates/front/ventilacion2026promo.tpl');
  }

  public function postProcess()
  {
    parent::postProcess();

    // ✅ PARA checkStock - CORREGIDO
    if (Tools::getValue('action') == 'checkStock') {
      if (Tools::getValue('token') !== Tools::getToken(false)) {
        die(json_encode(['success' => false, 'message' => 'Token inválido']));
      }
      $this->ajaxCheckStock();
    }

    // ✅ PARA calculateTotal
    if (Tools::isSubmit('action') && Tools::getValue('action') == 'calculateTotal') {
      $this->processAjaxCalculateTotal();
    }
  }

  /**
   * Endpoint AJAX para verificar stock - VERSIÓN CORREGIDA
   */
  private function ajaxCheckStock()
  {
    try {
      $productId = (int)Tools::getValue('productId');
      $combinationId = (int)Tools::getValue('combinationId');
      $quantity = (int)Tools::getValue('quantity', 1);

      // Logger::log("🔍 AJAX CHECK STOCK - Producto: {$productId}, Comb: {$combinationId}, Qty: {$quantity}");

      $stockManager = new StockManager();
      $stockInfo = $stockManager->checkStock($productId, $combinationId, $quantity);

      // ✅ RESPONSE CORRECTA
      die(json_encode([
        'success' => true,
        'stock' => $stockInfo
      ]));
    } catch (Exception $e) {
      // Logger::log("❌ ERROR AJAX CHECK STOCK: " . $e->getMessage());
      die(json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'stock' => [
          'available' => false,
          'stock' => 0,
          'message' => 'Error verificando stock'
        ]
      ]));
    }
  }

  private function processAjaxCalculateTotal()
  {
    try {
      // Logger::log("🧮 AJAX - Calculando total desde controlador principal");

      $selectedProductsJson = Tools::getValue('selectedProducts', '[]');
      // Logger::log("📦 AJAX - JSON recibido: " . $selectedProductsJson);

      $selectedProducts = json_decode($selectedProductsJson, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error decodificando JSON: ' . json_last_error_msg());
      }

      if (empty($selectedProducts)) {
        die(json_encode([
          'success' => true,
          'total' => 0,
          'available_products' => [],
          'out_of_stock_count' => 0,
          'has_stock_issues' => false
        ]));
      }

      // Log detallado
      foreach ($selectedProducts as $index => $product) {
        // Logger::log("📦 AJAX - Producto {$index}: ID={$product['id_product']}, Attr={$product['id_product_attribute']}, Qty={$product['quantity']}");
      }

      $lastGroup = $this->databaseManager->getLastGroup($this->context->customer->id);
      $originalGroup = $lastGroup ?: (int)$this->context->customer->id_default_group;

      $promoGroupId = ($originalGroup === Constants::CANARIAS_GROUP_ID)
        ? Constants::PROMO_GROUP_ID_71
        : Constants::PROMO_GROUP_ID;

      // Logger::log("🎯 Calculando con grupo promocional: {$promoGroupId} (grupo original: {$originalGroup})");

      $calculator = new PromoCalculator($promoGroupId);
      $result = $calculator->calculateTotalForSelectedProducts($selectedProducts);

      // Logger::log("💰 AJAX - Resultado completo: " . json_encode($result));

      die(json_encode([
        'success' => $result['success'],
        'total' => $result['total'],
        'available_products' => $result['available_products'] ?? [],
        'out_of_stock_count' => $result['out_of_stock_count'] ?? 0,
        'has_stock_issues' => $result['has_stock_issues'] ?? false,
        'stock_validation' => $result['stock_validation'] ?? [],
        'message' => $result['message'] ?? ''
      ]));
    } catch (Exception $e) {
      // Logger::log("❌ AJAX - Error: " . $e->getMessage());
      die(json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'total' => 0,
        'available_products' => [],
        'out_of_stock_count' => 0,
        'has_stock_issues' => false
      ]));
    }
  }

  /**
   * Obtiene productos cuya referencia empieza por un prefijo concreto.
   */
  private function getProductsByReferencePrefix($prefix)
  {
    $sql = 'SELECT p.id_product
                FROM ' . _DB_PREFIX_ . 'product p
                WHERE p.reference LIKE "' . pSQL($prefix) . '%"
                AND p.active = 1';

    $ids = Db::getInstance()->executeS($sql);
    $products = [];

    foreach ($ids as $row) {
      $product = new Product(
        (int)$row['id_product'],
        false,
        $this->context->language->id,
        $this->context->shop->id
      );

      if (Validate::isLoadedObject($product)) {
        // Obtener combinaciones
        $combinations = $this->getProductCombinations($product->id);

        // Calcular precio final - ahora el cliente está en grupo 70, así que mostrará esos precios
        $final_price = Product::getPriceStatic(
          (int)$product->id,
          true,
          0, // Producto base
          2,
          null,
          false,
          true
        );

        $products[] = [
          'id' => $product->id,
          'name' => $product->name,
          'description_short' => $product->description_short,
          'link' => $this->context->link->getProductLink($product),
          'image' => $this->context->link->getImageLink($product->link_rewrite, $product->getCoverWs()),
          'price' => Tools::displayPrice($final_price),
          'price_raw' => $final_price,
          'combinations' => $combinations,
          'has_combinations' => !empty($combinations),
          'reference' => $product->reference,
        ];
      }
    }

    return $products;
  }

  /**
   * Obtiene las combinaciones de un producto
   */
  private function getProductCombinations($product_id)
  {
    $sql = 'SELECT pa.id_product_attribute, pa.reference, pa.price,
                       GROUP_CONCAT(CONCAT(agl.name, ": ", al.name) SEPARATOR ", ") as attributes
                FROM ' . _DB_PREFIX_ . 'product_attribute pa
                LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute_combination pac ON pa.id_product_attribute = pac.id_product_attribute
                LEFT JOIN ' . _DB_PREFIX_ . 'attribute a ON pac.id_attribute = a.id_attribute
                LEFT JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON (a.id_attribute = al.id_attribute AND al.id_lang = ' . (int)$this->context->language->id . ')
                LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON (a.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . (int)$this->context->language->id . ')
                WHERE pa.id_product = ' . (int)$product_id . '
                GROUP BY pa.id_product_attribute';

    $result = Db::getInstance()->executeS($sql);
    $combinations = [];

    if ($result) {
      foreach ($result as $combination) {
        // Calcular el precio real de la combinación
        $combination_price = Product::getPriceStatic(
          (int)$product_id,
          true,
          (int)$combination['id_product_attribute'],
          2,
          null,
          false,
          true
        );

        $combinations[] = [
          'id_product_attribute' => $combination['id_product_attribute'],
          'reference' => $combination['reference'],
          'attributes' => $combination['attributes'] ?: 'Sin atributos',
          'price_raw' => $combination_price,
          'price' => Tools::displayPrice($combination_price),
        ];
      }
    }

    return $combinations;
  }
}
