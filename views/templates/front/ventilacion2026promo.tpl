{extends file='page.tpl'}

{block name='page_content'}

{if $customer.is_logged}

<div class="ventilacion-promo-page container my-5" data-order-url="{$urls.pages.order}"
  data-ajax-url="{$urls.pages.cart}" data-static-token="{$static_token}"
  data-restore-group-url="{$link->getModuleLink('ventilacion2026promo', 'updategroup', ['action' => 'restore'], true)}"
  data-update-group-url="{$link->getModuleLink('ventilacion2026promo', 'updategroup', ['ajax' => true])}"
  data-promo-ajax-url="{$promo_ajax_url}">

  <h1 class="text-center mb-4">Promoción Ventilación 2026</h1>

  {if $products|@count > 0}
  <div class="mini-grid">
    {foreach from=$products item=product}
    <div class="mini-card shadow-sm" data-price="{$product.price_raw}" data-id="{$product.id}" data-qty="0"
      data-reference="{$product.reference}">
      <a href="{$product.link}">
        <img src="{$product.image}" alt="{$product.name}">
      </a>
      <div class="card-body">
        <h5><a href="{$product.link}" class="text-dark text-decoration-none">
            {$product.name|truncate:20}
          </a></h5>
        <p class="fw-bold text-primary mb-1">{$product.price}</p>

        {if $product.has_combinations}
        <div class="combination-selector">
          <select class="combination-select" data-product="{$product.id}">
            <option value="0" data-price="{$product.price_raw}">-- Seleccionar --</option>
            {foreach from=$product.combinations item=combination}
            <option value="{$combination.id_product_attribute}" data-price="{$combination.price_raw}"
              data-reference="{$combination.reference}">
              {$combination.attributes}
            </option>
            {/foreach}
          </select>
          <div class="combination-price" id="combination-price-{$product.id}" style="display: none;">
          </div>
        </div>
        {/if}

        <div class="controls">
          <button type="button" class="btn btn-sm btn-secondary minus-btn">−</button>
          <span class="qty">0</span>
          <button type="button" class="btn btn-sm btn-primary plus-btn">+</button>
        </div>
      </div>
    </div>
    {/foreach}
  </div>
  {else}
  <div class="alert alert-info text-center">
    No se han encontrado productos que empiecen por "VT-".
  </div>
  {/if}
</div>

<!-- ===== FIXED BAR ===== -->
<div class="budget-bar" id="budget-bar">
  <div class="budget-status">
    Total seleccionado: <span id="budget-total">0.00</span>€ / 3000€
    <span id="budget-message"></span>
  </div>
  <div class="budget-progress">
    <div class="budget-progress-bar" id="budget-progress"></div>
  </div>
  <div class="button-container">
    <button type="button" class="add-to-cart-btn" id="add-to-cart-btn">
      Añadir al carrito
    </button>
  </div>
</div>

{else}
<div class="container my-5 text-center">
  <div class="alert alert-warning">
    Debes iniciar sesión para ver esta promoción.
  </div>
  <a href="{$urls.pages.authentication}" class="btn btn-primary mt-3">
    Iniciar sesión
  </a>
</div>
{/if}

{/block}