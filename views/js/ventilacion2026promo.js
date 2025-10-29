// Variable global para controlar navegaciones válidas
window.validNavigation = false;

document.addEventListener('DOMContentLoaded', function () {
  let promoAjaxUrl = '';

  // Obtener variables del data attributes
  const promoPage = document.querySelector('.ventilacion-promo-page');
  const orderUrl = promoPage.dataset.orderUrl;
  const ajaxUrl = promoPage.dataset.ajaxUrl;
  const static_token = promoPage.dataset.staticToken;
  const restoreGroupUrl = promoPage.dataset.restoreGroupUrl;
  const updateGroupUrl = promoPage.dataset.updateGroupUrl;

  promoAjaxUrl = promoPage.dataset.promoAjaxUrl || '';

  const minBudget = 3000;
  let currentBudget = 0;
  const totalDisplay = document.getElementById('budget-total');
  const progressBar = document.getElementById('budget-progress');
  const budgetBar = document.getElementById('budget-bar');
  const addToCartBtn = document.getElementById('add-to-cart-btn');
  const budgetMessage = document.getElementById('budget-message');

  // Objetos para almacenar combinaciones
  const selectedCombinations = {}; // { productId: { combinationId: { quantity, price } } }
  const currentSelections = {};    // { productId: currentCombinationId }

  console.log('🔗 URL AJAX generada:', promoAjaxUrl);
  console.log('🔗 Otras URLs:', {
    orderUrl,
    ajaxUrl,
    restoreGroupUrl,
    updateGroupUrl
  });

  // ====================== SISTEMA DE NOTIFICACIONES TOAST ========================

  /**
   * Muestra una notificación toast en la esquina superior derecha
   */
  function showToast(message, type = 'warning') {
    // Crear contenedor de toasts si no existe
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.id = 'toast-container';
      toastContainer.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 350px;
      `;
      document.body.appendChild(toastContainer);
    }

    // Crear toast
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show`;
    toast.style.cssText = `
      margin-bottom: 10px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      animation: slideInRight 0.3s ease-out;
    `;

    toast.innerHTML = `
      ${message}
      <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
    `;

    // Añadir estilos de animación
    if (!document.querySelector('#toast-styles')) {
      const styles = document.createElement('style');
      styles.id = 'toast-styles';
      styles.textContent = `
        @keyframes slideInRight {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
      `;
      document.head.appendChild(styles);
    }

    // Añadir toast al contenedor
    toastContainer.appendChild(toast);

    // Auto-eliminar después de 3 segundos
    setTimeout(() => {
      if (toast.parentNode) {
        toast.remove();
      }
    }, 3000);
  }

  // ====================== Funcion de stock ========================
  /*
  *verificar stock via ajax
  */
  async function checkStock(productId, combinationId, quantity = 1) {
    try {
      console.log(`🔍 Verificando stock: Producto ${productId}, Comb ${combinationId}, Qty ${quantity}`);

      const params = new URLSearchParams({
        action: 'checkStock',
        productId: productId,
        combinationId: combinationId,
        quantity: quantity,
        token: static_token
      });

      const response = await fetch(promoAjaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: params
      });

      console.log('📡 Response status:', response.status);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      console.log('📦 Data recibida:', data);

      if (!data.success) {
        console.error('❌ Error en checkStock:', data.message);
        // Por seguridad, devolver disponible si hay error en la respuesta
        return {
          available: true,
          stock: 999,
          message: 'Error verificando disponibilidad - asumiendo disponible'
        };
      }

      return data.stock;

    } catch (error) {
      console.error('❌ Error en checkStock:', error);
      // Por seguridad, devolver disponible si hay error de conexión
      return {
        available: true,
        stock: 999,
        message: 'Error de conexión - asumiendo disponible'
      };
    }
  }

  /*
  *Mostrar mensaje de error - AHORA CON TOAST
  */
  function showStockMessage(stockInfo, productName = '') {
    console.log('🔄 Mostrando mensaje toast de stock:', stockInfo);

    if (!stockInfo.available) {
      const message = productName
        ? `<strong>${productName}</strong>: ${stockInfo.message}`
        : stockInfo.message;

      showToast(message, 'warning');
    }
  }

  /*
  * Limpiar mensajes de stock - AHORA SOLO DESBLOQUEA BOTONES
  */
  function clearStockMessage(card) {
    // Solo nos encargamos de rehabilitar botones
    const plusBtn = card.querySelector('.plus-btn');
    if (plusBtn && plusBtn.disabled) {
      const hasCombinations = card.querySelector('.combination-select') !== null;
      const currentCombinationId = currentSelections[card.dataset.id];

      // Habilitar si hay combinacion seleccionada
      if (!hasCombinations || (hasCombinations && currentCombinationId && currentCombinationId > 0)) {
        plusBtn.disabled = false;
        plusBtn.classList.remove('btn-secondary');
        plusBtn.classList.add('btn-primary');
      }
    }
  }

  /* 
  * Manejar el click en + con verificación de stock
  */
  async function handlePlusClick(productId, card) {
    const hasCombinations = card.querySelector('.combination-select') !== null;

    if (hasCombinations) {
      const currentCombinationId = currentSelections[productId];
      if (!currentCombinationId || currentCombinationId === 0) {
        showToast('Por favor, selecciona una combinación', 'info');
        return;
      }
    }

    const currentQty = getCurrentCombinationQuantity(productId);
    const newQty = currentQty + 1;
    const combinationId = hasCombinations ? currentSelections[productId] : 0;

    console.log(`⏳ Verificando stock para producto ${productId}, comb ${combinationId}, cantidad ${newQty}`);

    const stockInfo = await checkStock(parseInt(productId), combinationId, newQty);

    if (stockInfo.available) {
      console.log(`✅ Stock OK - Actualizando cantidad`);
      clearStockMessage(card);
      setCurrentCombinationQuantity(productId, newQty);
      updateQuantityDisplay(productId, card);
      updateBar();
    } else {
      console.log(`❌ Sin stock disponible: ${stockInfo.message}`);
      // Obtener nombre del producto para el toast
      const productName = card.querySelector('h5 a')?.textContent?.trim() || 'Producto';
      showStockMessage(stockInfo, productName);
    }
  }

  /**
   * Muestra advertencias de stock desde el cálculo total - AHORA CON TOAST
   */
  function showStockWarnings(data) {
    if (data.has_stock_issues && data.out_of_stock_products) {
      console.warn('⚠️ Productos sin stock:', data.out_of_stock_products);

      // Mostrar toast por cada producto sin stock
      data.out_of_stock_products.forEach(product => {
        const key = product.id + '_' + product.id_product_attribute;
        const stockInfo = data.stock_validation[key];
        if (stockInfo) {
          // Obtener nombre del producto
          const card = document.querySelector(`.mini-card[data-id="${product.id}"]`);
          const productName = card ? card.querySelector('h5 a')?.textContent?.trim() : 'Producto';

          showStockMessage(stockInfo, productName);
        }
      });

      // Toast general resumen
      if (data.out_of_stock_count > 0) {
        setTimeout(() => {
          showToast(
            `${data.out_of_stock_count} producto(s) sin stock disponible no se incluirán en el cálculo.`,
            'info'
          );
        }, 500);
      }
    }
  }

  /**
   * Calcula el total con surtidos via AJAX - ACTUALIZADA para manejar stock
   */
  async function calculateTotalWithSurtidos() {
    const productsToCalculate = [];

    // Preparar datos para enviar al servidor
    for (const productId in selectedCombinations) {
      for (const combinationId in selectedCombinations[productId]) {
        const item = selectedCombinations[productId][combinationId];
        if (item.quantity > 0) {
          productsToCalculate.push({
            id_product: parseInt(productId),
            id_product_attribute: parseInt(combinationId),
            quantity: item.quantity
          });
          console.log(`✅ Añadido al cálculo: Producto ${productId}, Comb ${combinationId}, Cantidad: ${item.quantity}`);
        }
      }
    }

    console.log('📦 Productos a calcular:', productsToCalculate);

    if (productsToCalculate.length === 0) {
      console.log('🔄 No hay productos con cantidad > 0, total: 0');
      return 0;
    }

    try {
      const body = `action=calculateTotal&selectedProducts=${encodeURIComponent(JSON.stringify(productsToCalculate))}`;
      console.log('🚀 Enviando petición AJAX...', body);

      const response = await fetch(promoAjaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body
      });

      if (!response.ok) {
        const errorText = await response.text();
        console.error('❌ Cuerpo del error:', errorText);
        throw new Error('Error en la respuesta del servidor: ' + response.status);
      }

      const data = await response.json();
      console.log('📊 Datos recibidos:', data);

      // NUEVO: Manejar validación de stock
      if (data.success) {
        if (data.has_stock_issues) {
          showStockWarnings(data);
        }

        console.log('💰 Total calculado con surtidos:', data.total);
        return parseFloat(data.total);
      } else {
        // Error de stock o otro error
        console.error('❌ Error del servidor:', data.message);
        throw new Error(data.message || 'Error calculando total');
      }

    } catch (error) {
      console.error('❌ Error calculando total con surtidos:', error);
      // Fallback: calcular sin surtidos
      return calculateTotalBudgetFallback();
    }
  }

  // Fallback: cálculo simple sin surtidos (como el actual)
  function calculateTotalBudgetFallback() {
    let total = 0;
    for (const productId in selectedCombinations) {
      for (const combinationId in selectedCombinations[productId]) {
        const item = selectedCombinations[productId][combinationId];
        total += item.price * item.quantity;
      }
    }
    console.log('🔄 Usando cálculo fallback:', total);
    return total;
  }

  function restoreOriginalGroup() {
    if (window.restoreGroupCalled || window.validNavigation) return;
    window.restoreGroupCalled = true;

    console.log('🔙 Restaurando grupo original...');
    fetch(restoreGroupUrl + '&token=' + encodeURIComponent(static_token), {
      method: 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(response => {
        if (response.ok) console.log('✅ Grupo restaurado correctamente');
      })
      .catch(error => {
        console.error('❌ Error restaurando grupo:', error);
      });
  }

  // Event listeners para navegación
  window.addEventListener('beforeunload', function (e) {
    if (!window.validNavigation) restoreOriginalGroup();
  });

  window.addEventListener('click', function (e) {
    var target = e.target.closest('a');
    if (e.target.id === 'add-to-cart-btn' || e.target.classList.contains('add-to-cart-btn') || e.target.closest('.button-container')) {
      return;
    }
    if (target && target.href && !target.href.includes('ventilacion2026promo') && !target.href.includes('updategroup') && target.target !== '_blank') {
      e.preventDefault();
      restoreOriginalGroup();
      setTimeout(() => { window.location.href = target.href; }, 100);
    }
  });

  var logoutLinks = document.querySelectorAll('a[href*="mylogout"], a[href*="logout"]');
  logoutLinks.forEach(function (link) {
    link.addEventListener('click', (e) => { restoreOriginalGroup(); });
  });

  async function updateBar() {
    // Calcular el presupuesto considerando surtidos
    if (promoAjaxUrl) {
      currentBudget = await calculateTotalWithSurtidos();
    } else {
      currentBudget = calculateTotalBudgetFallback();
    }

    console.log('💰 Presupuesto actualizado:', currentBudget);
    totalDisplay.textContent = currentBudget.toFixed(2);
    const percent = Math.min((currentBudget / minBudget) * 100, 100);
    progressBar.style.width = percent + '%';

    if (currentBudget >= minBudget) {
      addToCartBtn.style.display = 'inline-block';
      budgetBar.classList.add('budget-reached');
      budgetMessage.textContent = ' - ¡Puedes realizar tu pedido!';
    } else {
      addToCartBtn.style.display = 'none';
      budgetBar.classList.remove('budget-reached');
      budgetMessage.textContent = '';
    }
  }

  function getCurrentCombinationQuantity(productId) {
    const currentCombinationId = currentSelections[productId];

    // Si no hay combinación seleccionada, devolver 0
    if (currentCombinationId === undefined || currentCombinationId === null) {
      return 0;
    }

    // Si no existe la estructura para este producto/combinación, devolver 0
    if (!selectedCombinations[productId] || !selectedCombinations[productId][currentCombinationId]) {
      return 0;
    }

    return selectedCombinations[productId][currentCombinationId].quantity || 0;
  }

  function setCurrentCombinationQuantity(productId, quantity) {
    const currentCombinationId = currentSelections[productId];

    // Si no hay currentCombinationId, inicializar con 0 para productos sin combinaciones
    if (currentCombinationId === undefined) {
      currentSelections[productId] = 0;
    }

    const effectiveCombinationId = currentSelections[productId];

    // Asegurarse de que existe la estructura
    if (!selectedCombinations[productId]) {
      selectedCombinations[productId] = {};
    }

    if (!selectedCombinations[productId][effectiveCombinationId]) {
      // Obtener el precio del producto desde la tarjeta
      const card = document.querySelector(`.mini-card[data-id="${productId}"]`);
      let price = 0;

      if (card) {
        price = parseFloat(card.dataset.price.replace(',', '.')) || 0;
        console.log(`🎯 Precio obtenido para producto ${productId}:`, price);
      }

      selectedCombinations[productId][effectiveCombinationId] = {
        quantity: 0,
        price: price
      };
    }

    // Actualizar la cantidad
    selectedCombinations[productId][effectiveCombinationId].quantity = quantity;
    console.log(`✏️ Actualizada cantidad: Producto ${productId}, Comb ${effectiveCombinationId} = ${quantity}`);
  }

  /*
* Maneja cambio de combinación - ACTUALIZADA para verificar stock
*/
  async function handleCombinationChange(productId, combinationId, price, card) {
    // Guardar la selección actual
    currentSelections[productId] = combinationId;

    // Inicializar la combinación si no existe
    if (!selectedCombinations[productId]) {
      selectedCombinations[productId] = {};
    }

    // ✅ NUEVO: Si es una combinación válida (no 0), establecer cantidad a 1 automáticamente
    const shouldAddQuantity = combinationId > 0;

    if (!selectedCombinations[productId][combinationId]) {
      selectedCombinations[productId][combinationId] = {
        quantity: shouldAddQuantity ? 1 : 0, // ✅ Si es combinación válida, cantidad = 1
        price: price
      };
    } else {
      selectedCombinations[productId][combinationId].price = price;
      // ✅ Si es combinación válida y la cantidad es 0, establecer a 1
      if (shouldAddQuantity && selectedCombinations[productId][combinationId].quantity === 0) {
        selectedCombinations[productId][combinationId].quantity = 1;
      }
    }

    console.log(`🔄 Cambio combinación: Producto ${productId} -> Comb ${combinationId}, Precio: ${price}, Cantidad: ${selectedCombinations[productId][combinationId].quantity}`);

    // Verificar stock de la nueva combinación (ahora con cantidad 1 si es combinación válida)
    const quantityToCheck = shouldAddQuantity ? 1 : 0;
    if (combinationId > 0) {
      const stockInfo = await checkStock(parseInt(productId), combinationId, quantityToCheck);
      if (!stockInfo.available) {
        const productName = card.querySelector('h5 a')?.textContent?.trim() || 'Producto';
        showStockMessage(stockInfo, productName);
        // ❌ Si no hay stock, volver a poner cantidad a 0
        selectedCombinations[productId][combinationId].quantity = 0;
      } else {
        clearStockMessage(card);
      }
    } else {
      clearStockMessage(card);
    }

    // Actualizar el precio mostrado
    const priceElement = document.getElementById('combination-price-' + productId);
    if (combinationId > 0 && price > 0) {
      priceElement.textContent = 'Precio: ' + price.toFixed(2) + '€';
      priceElement.style.display = 'block';

      // Habilitar botones
      const plusBtn = card.querySelector('.plus-btn');
      const minusBtn = card.querySelector('.minus-btn');
      plusBtn.disabled = false;
      minusBtn.disabled = false;
      plusBtn.classList.remove('btn-secondary');
      minusBtn.classList.remove('btn-secondary');
      plusBtn.classList.add('btn-primary');
      minusBtn.classList.add('btn-secondary');
    } else {
      priceElement.style.display = 'none';

      // Deshabilitar botones si no hay combinación seleccionada (solo para productos CON combinaciones)
      if (combinationId === 0 && card.querySelector('.combination-select')) {
        const plusBtn = card.querySelector('.plus-btn');
        const minusBtn = card.querySelector('.minus-btn');
        plusBtn.disabled = true;
        minusBtn.disabled = true;
        plusBtn.classList.remove('btn-primary');
        minusBtn.classList.remove('btn-secondary');
        plusBtn.classList.add('btn-secondary');
        minusBtn.classList.add('btn-secondary');
      }
    }

    // Actualizar el display de cantidad para la combinación actual
    updateQuantityDisplay(productId, card);
    updateBar();
  }

  function updateQuantityDisplay(productId, card) {
    const qtyDisplay = card.querySelector('.qty');
    const currentQty = getCurrentCombinationQuantity(productId);
    qtyDisplay.textContent = currentQty;
    console.log(`🔢 Display actualizado: Producto ${productId} = ${currentQty}`);
  }

  function addToCart() {
    const productsToAdd = [];
    let hasInvalidProducts = false;

    console.log('🛒 Iniciando añadir al carrito...');
    console.log('selectedCombinations:', selectedCombinations);

    // Recopilar TODAS las combinaciones con cantidad > 0
    for (const productId in selectedCombinations) {
      for (const combinationId in selectedCombinations[productId]) {
        const item = selectedCombinations[productId][combinationId];
        console.log(`📦 Verificando: Producto ${productId}, Comb ${combinationId}, Cantidad: ${item.quantity}, Precio: ${item.price}`);

        if (item.quantity > 0 && item.price > 0) {
          productsToAdd.push({
            id: parseInt(productId),
            quantity: item.quantity,
            id_product_attribute: parseInt(combinationId)
          });
          console.log(`✅ Añadido: Producto ${productId}, Comb ${combinationId}, Cantidad: ${item.quantity}`);
        }
      }
    }

    if (productsToAdd.length === 0) {
      showToast('No hay productos seleccionados', 'warning');
      return;
    }

    // Mostrar confirmación detallada
    const confirmMessage = 'Vas a añadir al carrito:\n\n' +
      productsToAdd.map(p => {
        let productInfo = `• Producto ${p.id}`;
        if (p.id_product_attribute > 0) {
          productInfo += ` (Combinación: ${p.id_product_attribute})`;
        }
        productInfo += ` - Cantidad: ${p.quantity}`;
        return productInfo;
      }).join('\n') +
      `\n\nTotal: ${currentBudget.toFixed(2)}€\n\n¿Continuar?`;

    if (confirm(confirmMessage)) {
      addToCartBtn.disabled = true;
      addToCartBtn.textContent = 'Añadiendo...';
      window.validNavigation = true;
      addProductsToCart(productsToAdd, 0);
    }
  }

  function addProductsToCart(products, index) {
    if (index >= products.length) {
      forcePromoGroup().then(() => {
        showToast('¡Productos añadidos al carrito correctamente!', 'success');
        setTimeout(() => {
          window.location.href = orderUrl;
        }, 1500);
      });
      return;
    }

    const product = products[index];
    let body = `add=1&id_product=${product.id}&qty=${product.quantity}&token=${static_token}`;
    if (product.id_product_attribute > 0) {
      body += `&id_product_attribute=${product.id_product_attribute}`;
    }

    console.log(`🚀 Añadiendo al carrito: Producto ${product.id}, Comb ${product.id_product_attribute}, Cantidad: ${product.quantity}`);

    fetch(ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    })
      .finally(() => {
        addProductsToCart(products, index + 1);
      });
  }

  async function forcePromoGroup() {
    try {
      console.log("🚀 Forzando grupo promocional...");

      const formData = new URLSearchParams();
      formData.append('token', static_token);
      formData.append('force_promo', '1');

      console.log("📤 Enviando a:", updateGroupUrl);
      console.log("📦 Datos:", {
        token: static_token,
        force_promo: '1'
      });

      const res = await fetch(updateGroupUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });

      console.log("📥 Respuesta status:", res.status);

      if (!res.ok) {
        const errorText = await res.text();
        console.error("❌ Error response body:", errorText);
        throw new Error('HTTP error! status: ' + res.status);
      }

      const data = await res.json();
      console.log("✅ Respuesta JSON:", data);
      return data;

    } catch (err) {
      console.error("❌ Error forzando grupo promocional:", err);
      return { success: false, message: err.message };
    }
  }
  // ==================== EVENT LISTENERS ACTUALIZADOS ====================

  // Event listeners para combinaciones - ACTUALIZADO
  document.querySelectorAll('.combination-select').forEach(select => {
    select.addEventListener('change', function () {
      const productId = this.dataset.product;
      const combinationId = parseInt(this.value);
      const selectedOption = this.options[this.selectedIndex];
      const price = parseFloat(selectedOption.dataset.price) || 0;
      const card = this.closest('.mini-card');

      // ✅ LLAMADA ACTUALIZADA - Ahora es async y verifica stock
      handleCombinationChange(productId, combinationId, price, card);
    });

    // Inicializar (sin cambios)
    const productId = select.dataset.product;
    const card = select.closest('.mini-card');
    const plusBtn = card.querySelector('.plus-btn');
    const minusBtn = card.querySelector('.minus-btn');

    plusBtn.disabled = true;
    minusBtn.disabled = true;
    plusBtn.classList.remove('btn-primary');
    plusBtn.classList.add('btn-secondary');
  });

  addToCartBtn.addEventListener('click', addToCart); // ✅ SIN CAMBIOS

  // Lógica de botones +/- - ACTUALIZADA
  document.querySelectorAll('.mini-card').forEach(card => {
    const plusBtn = card.querySelector('.plus-btn');
    const minusBtn = card.querySelector('.minus-btn');
    const productId = card.dataset.id;
    const hasCombinations = card.querySelector('.combination-select') !== null;
    const originalPrice = parseFloat(card.dataset.price.replace(',', '.')) || 0;

    console.log(`🎯 Inicializando producto ${productId}: hasCombinations=${hasCombinations}, precio=${originalPrice}`);

    // 🚀 INICIALIZAR PRODUCTOS SIN COMBINACIONES (sin cambios)
    if (!hasCombinations) {
      currentSelections[productId] = 0;

      if (!selectedCombinations[productId]) {
        selectedCombinations[productId] = {};
      }
      if (!selectedCombinations[productId][0]) {
        selectedCombinations[productId][0] = {
          quantity: 0,
          price: originalPrice
        };
        console.log(`✅ Producto ${productId} inicializado con precio: ${originalPrice}`);
      }

      plusBtn.disabled = false;
      minusBtn.disabled = false;
      plusBtn.classList.remove('btn-secondary');
      minusBtn.classList.remove('btn-secondary');
      plusBtn.classList.add('btn-primary');
      minusBtn.classList.add('btn-secondary');

      updateQuantityDisplay(productId, card);
    }

    // ✅ BOTÓN + ACTUALIZADO - Ahora usa handlePlusClick que verifica stock
    plusBtn.addEventListener('click', () => {
      console.log(`➕ Click en + para producto ${productId}`);
      handlePlusClick(productId, card); // ✅ NUEVA FUNCIÓN CON STOCK
    });

    // ✅ BOTÓN - ACTUALIZADO - Ahora limpia mensajes de stock
    minusBtn.addEventListener('click', () => {
      console.log(`➖ Click en - para producto ${productId}`);

      const currentQty = getCurrentCombinationQuantity(productId);
      if (currentQty > 0) {
        setCurrentCombinationQuantity(productId, currentQty - 1);
        updateQuantityDisplay(productId, card);
        updateBar();

        // ✅ NUEVO: Limpiar mensaje de stock al reducir cantidad
        clearStockMessage(card);
      }
    });
  });

  // ✅ NUEVO: Verificar stock inicial al cargar la página
  setTimeout(async () => {
    console.log('🔍 Verificando stock inicial...');
    for (const productId in currentSelections) {
      const combinationId = currentSelections[productId];
      const card = document.querySelector(`.mini-card[data-id="${productId}"]`);
      const currentQty = getCurrentCombinationQuantity(productId);

      if (currentQty > 0 && card) {
        const stockInfo = await checkStock(parseInt(productId), combinationId, currentQty);
        if (!stockInfo.available) {
          const productName = card.querySelector('h5 a')?.textContent?.trim() || 'Producto';
          showStockMessage(stockInfo, productName);
        }
      }
    }
  }, 1500);

  // Debug inicial (sin cambios)
  console.log('🔧 Estado inicial completado');
  console.log('currentSelections:', currentSelections);
  console.log('selectedCombinations:', selectedCombinations);
});