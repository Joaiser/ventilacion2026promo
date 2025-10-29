export class NavigationManager {
  constructor(config, stateManager) {
    this.config = config;
    this.state = stateManager;
    this.restoreGroupCalled = false;
  }

  /**
   * Restaurar grupo original del cliente
   */
  restoreOriginalGroup() {
    if (this.restoreGroupCalled || this.state.validNavigation) return;
    this.restoreGroupCalled = true;

    console.log('🔙 Restaurando grupo original...');
    fetch(this.config.restoreGroupUrl + '&token=' + encodeURIComponent(this.config.static_token), {
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

  /**
   * Manejar evento beforeunload
   */
  handleBeforeUnload(e) {
    if (!this.state.validNavigation) {
      this.restoreOriginalGroup();
    }
  }

  /**
   * Manejar clicks en enlaces
   */
  handleLinkClick(e) {
    const target = e.target.closest('a');

    // Ignorar clicks en el botón de añadir al carrito
    if (e.target.id === 'add-to-cart-btn' ||
      e.target.classList.contains('add-to-cart-btn') ||
      e.target.closest('.button-container')) {
      return;
    }

    // Manejar clicks en enlaces externos
    if (target && target.href &&
      !target.href.includes('ventilacion2026promo') &&
      !target.href.includes('updategroup') &&
      target.target !== '_blank') {
      e.preventDefault();
      this.restoreOriginalGroup();
      setTimeout(() => {
        window.location.href = target.href;
      }, 100);
    }
  }

  /**
   * Manejar logout
   */
  handleLogout(e) {
    this.restoreOriginalGroup();
  }

  /**
   * Inicializar todos los event listeners de navegación
   */
  initializeNavigationListeners() {
    console.log('🧭 Inicializando listeners de navegación...');

    // Event listener para beforeunload
    window.addEventListener('beforeunload', this.handleBeforeUnload.bind(this));

    // Event listener para clicks en enlaces
    window.addEventListener('click', this.handleLinkClick.bind(this));

    // Event listeners para logout
    const logoutLinks = document.querySelectorAll('a[href*="mylogout"], a[href*="logout"]');
    logoutLinks.forEach(link => {
      link.addEventListener('click', this.handleLogout.bind(this));
    });

    console.log('✅ Listeners de navegación inicializados');
  }
}