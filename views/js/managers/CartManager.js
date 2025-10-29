export class CartManager {
  constructor(stateManager, config, toastManager) {
    this.state = stateManager;
    this.config = config;
    this.toast = toastManager;
    this.elements = {};
  }

  setElements(elements) {
    this.elements = elements;
  }

  addToCart() {
    const productsToAdd = [];

    console.log('🛒 Iniciando añadir al carrito...');
    console.log('selectedCombinations:', this.state.selectedCombinations);

    // Recopilar TODAS las combinaciones con cantidad > 0
    for (const productId in this.state.selectedCombinations) {
      for (const combinationId in this.state.selectedCombinations[productId]) {
        const item = this.state.selectedCombinations[productId][combinationId];
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
      this.toast.show('No hay productos seleccionados', 'warning');
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
      `\n\nTotal: ${this.state.currentBudget.toFixed(2)}€\n\n¿Continuar?`;

    if (confirm(confirmMessage)) {
      this.elements.addToCartBtn.disabled = true;
      this.elements.addToCartBtn.textContent = 'Añadiendo...';
      this.state.validNavigation = true;
      this.addProductsToCart(productsToAdd, 0);
    }
  }

  addProductsToCart(products, index) {
    if (index >= products.length) {
      this.forcePromoGroup().then(() => {
        this.toast.show('¡Productos añadidos al carrito correctamente!', 'success');
        setTimeout(() => {
          window.location.href = this.config.orderUrl;
        }, 1500);
      });
      return;
    }

    const product = products[index];
    let body = `add=1&id_product=${product.id}&qty=${product.quantity}&token=${this.config.static_token}`;
    if (product.id_product_attribute > 0) {
      body += `&id_product_attribute=${product.id_product_attribute}`;
    }

    console.log(`🚀 Añadiendo al carrito: Producto ${product.id}, Comb ${product.id_product_attribute}, Cantidad: ${product.quantity}`);

    fetch(this.config.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    })
      .finally(() => {
        this.addProductsToCart(products, index + 1);
      });
  }

  async forcePromoGroup() {
    try {
      console.log("🚀 Forzando grupo promocional...");

      const formData = new URLSearchParams();
      formData.append('token', this.config.static_token);
      formData.append('force_promo', '1');

      console.log("📤 Enviando a:", this.config.updateGroupUrl);

      const res = await fetch(this.config.updateGroupUrl, {
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
}