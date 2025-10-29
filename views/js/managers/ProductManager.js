export class ProductManager {
  constructor(stateManager, stockManager, toastManager, budgetManager) {
    this.state = stateManager;
    this.stockManager = stockManager;
    this.toast = toastManager;
    this.budgetManager = budgetManager;
  }

  /**
   * Inicializar todos los productos y event listeners
   */
  initializeProducts() {
    console.log('🎯 Inicializando productos...');

    // Event listeners para combinaciones
    document.querySelectorAll('.combination-select').forEach(select => {
      this.initializeCombinationSelect(select);
    });

    // Event listeners para botones +/- de cada producto
    document.querySelectorAll('.mini-card').forEach(card => {
      this.initializeProductCard(card);
    });

    // Event listener para botones +18
    document.querySelectorAll('.add-multiple-btn').forEach(btn => {
      this.initializeAddMultipleButton(btn);
    });

    console.log('🔧 Productos inicializados completado');
  }

  /**
   * Inicializar selector de combinaciones
   */
  initializeCombinationSelect(select) {
    select.addEventListener('change', () => {
      const productId = select.dataset.product;
      const combinationId = parseInt(select.value);
      const selectedOption = select.options[select.selectedIndex];
      const price = parseFloat(selectedOption.dataset.price) || 0;
      const card = select.closest('.mini-card');

      this.handleCombinationChange(productId, combinationId, price, card);
    });

    // Inicializar estado del selector
    const productId = select.dataset.product;
    const card = select.closest('.mini-card');
    const plusBtn = card.querySelector('.plus-btn');
    const minusBtn = card.querySelector('.minus-btn');

    plusBtn.disabled = true;
    minusBtn.disabled = true;
    plusBtn.classList.remove('btn-primary');
    plusBtn.classList.add('btn-secondary');
  }

  /**
   * Inicializar tarjeta de producto
   */
  initializeProductCard(card) {
    const plusBtn = card.querySelector('.plus-btn');
    const minusBtn = card.querySelector('.minus-btn');
    const productId = card.dataset.id;
    const hasCombinations = card.querySelector('.combination-select') !== null;
    const originalPrice = parseFloat(card.dataset.price.replace(',', '.')) || 0;

    console.log(`🎯 Inicializando producto ${productId}: hasCombinations=${hasCombinations}, precio=${originalPrice}`);

    // 🚀 INICIALIZAR PRODUCTOS SIN COMBINACIONES
    if (!hasCombinations) {
      this.state.currentSelections[productId] = 0;

      if (!this.state.selectedCombinations[productId]) {
        this.state.selectedCombinations[productId] = {};
      }
      if (!this.state.selectedCombinations[productId][0]) {
        this.state.selectedCombinations[productId][0] = {
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

      this.updateQuantityDisplay(productId, card);
    }

    // ✅ BOTÓN +
    plusBtn.addEventListener('click', () => {
      console.log(`➕ Click en + para producto ${productId}`);
      this.handlePlusClick(productId, card);
    });

    // ✅ BOTÓN -
    minusBtn.addEventListener('click', () => {
      console.log(`➖ Click en - para producto ${productId}`);

      const currentQty = this.state.getCurrentCombinationQuantity(productId);
      if (currentQty > 0) {
        this.state.setCurrentCombinationQuantity(productId, currentQty - 1);
        this.updateQuantityDisplay(productId, card);
        this.budgetManager.updateBar();

        // Limpiar mensaje de stock al reducir cantidad
        this.stockManager.clearStockMessage(card);
      }
    });
  }

  /**
   * Inicializar botón +18 unidades
   */
  initializeAddMultipleButton(btn) {
    btn.addEventListener('click', () => {
      const productId = btn.dataset.product;
      const card = btn.closest('.mini-card');
      const hasCombinations = card.querySelector('.combination-select') !== null;

      if (hasCombinations) {
        const currentCombinationId = this.state.currentSelections[productId];
        if (!currentCombinationId || currentCombinationId === 0) {
          this.toast.show('Por favor, selecciona una combinación primero', 'info');
          return;
        }
      }

      this.state.setCurrentCombinationQuantity(productId, 18);
      this.updateQuantityDisplay(productId, card);
      this.budgetManager.updateBar();
      this.toast.show('18 unidades añadidas', 'success');
    });
  }

  /**
   * Manejar el click en + con verificación de stock
   */
  async handlePlusClick(productId, card) {
    const hasCombinations = card.querySelector('.combination-select') !== null;

    if (hasCombinations) {
      const currentCombinationId = this.state.currentSelections[productId];
      if (!currentCombinationId || currentCombinationId === 0) {
        this.toast.show('Por favor, selecciona una combinación', 'info');
        return;
      }
    }

    const currentQty = this.state.getCurrentCombinationQuantity(productId);
    const newQty = currentQty + 1;
    const combinationId = hasCombinations ? this.state.currentSelections[productId] : 0;

    console.log(`⏳ Verificando stock para producto ${productId}, comb ${combinationId}, cantidad ${newQty}`);

    const stockInfo = await this.stockManager.checkStock(parseInt(productId), combinationId, newQty);

    if (stockInfo.available) {
      console.log(`✅ Stock OK - Actualizando cantidad`);
      this.stockManager.clearStockMessage(card);
      this.state.setCurrentCombinationQuantity(productId, newQty);
      this.updateQuantityDisplay(productId, card);
      this.budgetManager.updateBar();
    } else {
      console.log(`❌ Sin stock disponible: ${stockInfo.message}`);
      const productName = card.querySelector('h5 a')?.textContent?.trim() || 'Producto';
      this.stockManager.showStockMessage(stockInfo, productName);
    }
  }

  /**
   * Maneja cambio de combinación - ACTUALIZADA para verificar stock
   */
  async handleCombinationChange(productId, combinationId, price, card) {
    // Guardar la selección actual
    this.state.currentSelections[productId] = combinationId;

    // Inicializar la combinación si no existe
    if (!this.state.selectedCombinations[productId]) {
      this.state.selectedCombinations[productId] = {};
    }

    // ✅ NUEVO: Si es una combinación válida (no 0), establecer cantidad a 1 automáticamente
    const shouldAddQuantity = combinationId > 0;

    if (!this.state.selectedCombinations[productId][combinationId]) {
      this.state.selectedCombinations[productId][combinationId] = {
        quantity: shouldAddQuantity ? 1 : 0,
        price: price
      };
    } else {
      this.state.selectedCombinations[productId][combinationId].price = price;
      // ✅ Si es combinación válida y la cantidad es 0, establecer a 1
      if (shouldAddQuantity && this.state.selectedCombinations[productId][combinationId].quantity === 0) {
        this.state.selectedCombinations[productId][combinationId].quantity = 1;
      }
    }

    console.log(`🔄 Cambio combinación: Producto ${productId} -> Comb ${combinationId}, Precio: ${price}, Cantidad: ${this.state.selectedCombinations[productId][combinationId].quantity}`);

    // Verificar stock de la nueva combinación
    const quantityToCheck = shouldAddQuantity ? 1 : 0;
    if (combinationId > 0) {
      const stockInfo = await this.stockManager.checkStock(parseInt(productId), combinationId, quantityToCheck);
      if (!stockInfo.available) {
        const productName = card.querySelector('h5 a')?.textContent?.trim() || 'Producto';
        this.stockManager.showStockMessage(stockInfo, productName);
        // ❌ Si no hay stock, volver a poner cantidad a 0
        this.state.selectedCombinations[productId][combinationId].quantity = 0;
      } else {
        this.stockManager.clearStockMessage(card);
      }
    } else {
      this.stockManager.clearStockMessage(card);
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
    this.updateQuantityDisplay(productId, card);
    this.budgetManager.updateBar();
  }

  /**
   * Actualizar display de cantidad
   */
  updateQuantityDisplay(productId, card) {
    const qtyDisplay = card.querySelector('.qty');
    const currentQty = this.state.getCurrentCombinationQuantity(productId);
    qtyDisplay.textContent = currentQty;
    console.log(`🔢 Display actualizado: Producto ${productId} = ${currentQty}`);
  }
}
// getCurrentCombinationQuantity, setCurrentCombinationQuantity podrían ir aquí