export class BudgetManager {
  constructor(stateManager, config, stockManager, toastManager) {
    this.state = stateManager;
    this.config = config;
    this.stockManager = stockManager;
    this.toast = toastManager;
    this.elements = {};
  }

  setElements(elements) {
    this.elements = elements;
  }

  async updateBar() {
    // Calcular el presupuesto considerando surtidos
    if (this.config.promoAjaxUrl) {
      this.state.currentBudget = await this.calculateTotalWithSurtidos();
    } else {
      this.state.currentBudget = this.calculateTotalBudgetFallback();
    }

    console.log('💰 Presupuesto actualizado:', this.state.currentBudget);

    // Actualizar UI
    this.elements.totalDisplay.textContent = this.state.currentBudget.toFixed(2);
    const percent = Math.min((this.state.currentBudget / this.config.minBudget) * 100, 100);
    this.elements.progressBar.style.width = percent + '%';

    if (this.state.currentBudget >= this.config.minBudget) {
      this.elements.addToCartBtn.style.display = 'inline-block';
      this.elements.budgetBar.classList.add('budget-reached');
      this.elements.budgetMessage.textContent = ' - ¡Puedes realizar tu pedido!';
    } else {
      this.elements.addToCartBtn.style.display = 'none';
      this.elements.budgetBar.classList.remove('budget-reached');
      this.elements.budgetMessage.textContent = '';
    }
  }

  /**
   * Calcula el total con surtidos via AJAX - ACTUALIZADA para manejar stock
   */
  async calculateTotalWithSurtidos() {
    const productsToCalculate = [];

    // Preparar datos para enviar al servidor - CORREGIDO: usar this.state
    for (const productId in this.state.selectedCombinations) {
      for (const combinationId in this.state.selectedCombinations[productId]) {
        const item = this.state.selectedCombinations[productId][combinationId];
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

      // CORREGIDO: usar this.config.promoAjaxUrl
      const response = await fetch(this.config.promoAjaxUrl, {
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

      // NUEVO: Manejar validación de stock - CORREGIDO: usar this.stockManager
      if (data.success) {
        if (data.has_stock_issues) {
          this.stockManager.showStockWarnings(data);
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
      // Fallback: calcular sin surtidos - CORREGIDO: usar this.
      return this.calculateTotalBudgetFallback();
    }
  }

  // Fallback: cálculo simple sin surtidos (como el actual)
  calculateTotalBudgetFallback() {
    let total = 0;
    // CORREGIDO: usar this.state.selectedCombinations
    for (const productId in this.state.selectedCombinations) {
      for (const combinationId in this.state.selectedCombinations[productId]) {
        const item = this.state.selectedCombinations[productId][combinationId];
        total += item.price * item.quantity;
      }
    }
    console.log('🔄 Usando cálculo fallback:', total);
    return total;
  }
}