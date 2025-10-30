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
            id_product_attribute: parseInt(combinationId),
            name: item.name || `Producto ${productId}`,
            reference: item.reference || ''
          });
          console.log(`✅ Añadido: ${item.name || 'Producto ' + productId}, Ref: ${item.reference || '—'}`);
        }
      }
    }

    if (productsToAdd.length === 0) {
      this.toast.show('No hay productos seleccionados', 'warning');
      return;
    }

    // Mostrar modal de confirmación (solo nombres + referencias)
    const modalContent = `
    <h3 style="margin-bottom:10px;">Confirmar productos</h3>
    <p>Vas a añadir al carrito:</p>
    <ul style="list-style:none; padding-left:0; margin:10px 0;">
      ${productsToAdd.map(p => `
        <li style="margin:4px 0;">🛍️ <strong>${p.name}</strong> 
          ${p.reference ? `<span style="color:#666;">(Ref: ${p.reference})</span>` : ''}
          — Cant: <strong>${p.quantity}</strong>
        </li>
      `).join('')}
    </ul>
    <p style="font-weight:bold; margin-top:10px;">Total: ${this.state.currentBudget.toFixed(2)} €</p>
  `;

    this.showConfirmModal(modalContent, () => {
      this.elements.addToCartBtn.disabled = true;
      this.elements.addToCartBtn.textContent = 'Añadiendo...';
      this.state.validNavigation = true;
      this.addProductsToCart(productsToAdd, 0);
    });
  }


  /**
   * Crea y muestra un modal de confirmación nativo con JS puro.
   * @param {string} content - HTML que se mostrará dentro del modal.
   * @param {function} onConfirm - Función que se ejecuta al pulsar "Confirmar".
   */
  showConfirmModal(content, onConfirm) {
    // Si ya hay un modal, lo eliminamos para evitar duplicados
    const existingModal = document.getElementById('custom-confirm-modal');
    if (existingModal) existingModal.remove();

    // Crear overlay
    const overlay = document.createElement('div');
    overlay.id = 'custom-confirm-modal';
    overlay.style.cssText = `
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    animation: fadeIn 0.2s ease-in-out;
  `;

    // Crear modal
    const modal = document.createElement('div');
    modal.style.cssText = `
    background: #fff;
    border-radius: 12px;
    padding: 20px 25px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    text-align: left;
    animation: popIn 0.25s ease-out;
  `;
    modal.innerHTML = `
    <div>${content}</div>
    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
      <button id="cancelModalBtn" style="
        padding: 8px 14px;
        background:#ccc;
        border:none;
        border-radius:6px;
        cursor:pointer;
        transition:0.2s;
      ">Cancelar</button>
      <button id="confirmModalBtn" style="
        padding: 8px 14px;
        background:#007bff;
        color:white;
        border:none;
        border-radius:6px;
        cursor:pointer;
        transition:0.2s;
      ">Confirmar</button>
    </div>
  `;

    // Añadir al DOM
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // Añadir animaciones CSS globales si no existen
    if (!document.getElementById('custom-modal-animations')) {
      const style = document.createElement('style');
      style.id = 'custom-modal-animations';
      style.textContent = `
      @keyframes fadeIn { from {opacity:0;} to {opacity:1;} }
      @keyframes popIn { from {transform:scale(0.95); opacity:0;} to {transform:scale(1); opacity:1;} }
    `;
      document.head.appendChild(style);
    }

    // Eventos
    modal.querySelector('#cancelModalBtn').addEventListener('click', () => overlay.remove());
    modal.querySelector('#confirmModalBtn').addEventListener('click', () => {
      overlay.remove();
      if (onConfirm) onConfirm();
    });

    // Cerrar al hacer clic fuera del modal
    overlay.addEventListener('click', e => {
      if (e.target === overlay) overlay.remove();
    });
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