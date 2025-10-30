export class StockManager {
  constructor(config) {
    this.config = config;
    this.toastManager = null;
  }

  setToastManager(toastManager) {
    this.toastManager = toastManager;
    console.log('✅ StockManager: ToastManager establecido', this.toastManager);
  }

  /**
   * Verificar stock via AJAX
   */
  async checkStock(productId, combinationId, quantity = 1) {
    try {
      console.log(`🔍 Verificando stock: Producto ${productId}, Comb ${combinationId}, Qty ${quantity}`);

      const params = new URLSearchParams({
        action: 'checkStock',
        productId: productId,
        combinationId: combinationId,
        quantity: quantity,
        token: this.config.static_token
      });

      const response = await fetch(this.config.promoAjaxUrl, {
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

  /**
   * Mostrar mensaje de error - AHORA CON TOAST
   * NOTA: Esta función necesita ToastManager, así que la manejaremos diferente
   */
  showStockMessage(stockInfo, productName = '') {
    console.log('🔄 Mostrando mensaje toast de stock:', stockInfo);

    if (!stockInfo.available && this.toastManager) {
      const message = productName
        ? `<strong>${productName}</strong>: ${stockInfo.message}`
        : stockInfo.message;

      // Usar ToastManager
      this.toastManager.show(message, 'warning');
      console.log('📢 Toast mostrado:', message);
    } else if (!stockInfo.available && !this.toastManager) {
      console.warn('⚠️ ToastManager no disponible para mostrar mensaje:', stockInfo.message);
    }
  }

  /**
   * Limpiar mensajes de stock - REHABILITAR BOTONES CUANDO HAY STOCK
   */
  /**
 * Limpiar mensajes de stock - REHABILITAR BOTONES CUANDO HAY STOCK
 */
  clearStockMessage(card) {
    const plusBtn = card.querySelector('.plus-btn');
    if (plusBtn && plusBtn.disabled) {
      const hasCombinations = card.querySelector('.combination-select') !== null;

      // Si no tiene combinaciones, habilitar directamente
      if (!hasCombinations) {
        plusBtn.disabled = false;
        plusBtn.classList.remove('btn-secondary');
        plusBtn.classList.add('btn-primary');
        console.log('✅ Botón rehabilitado para producto sin combinaciones');
      }
      // Si tiene combinaciones, el botón se habilita cuando se selecciona una combinación
      // (eso se maneja en otro lugar)
    }
  }

  /**
   * Muestra advertencias de stock desde el cálculo total
   */
  showStockWarnings(data) {
    if (data.has_stock_issues && data.out_of_stock_products && this.toastManager) {
      console.warn('⚠️ Productos sin stock:', data.out_of_stock_products);

      // Mostrar toast por cada producto sin stock
      data.out_of_stock_products.forEach(product => {
        const key = product.id + '_' + product.id_product_attribute;
        const stockInfo = data.stock_validation[key];
        if (stockInfo) {
          // Obtener nombre del producto
          const card = document.querySelector(`.mini-card[data-id="${product.id}"]`);
          const productName = card ? card.querySelector('h5 a')?.textContent?.trim() : 'Producto';

          this.showStockMessage(stockInfo, productName);
        }
      });

      // Toast general resumen
      if (data.out_of_stock_count > 0) {
        setTimeout(() => {
          this.toastManager.show(`${data.out_of_stock_count} producto(s) sin stock disponible no se incluirán en el cálculo.`, 'info');
          console.log('📢 Toast general mostrado');
        }, 500);
      }
    }
  }
}