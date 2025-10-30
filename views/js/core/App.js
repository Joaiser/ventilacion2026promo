// Obtener la ruta base
const getModuleBaseUrl = () => {
  if (window.ventilacionPromoVars?.baseUrl) {
    return window.ventilacionPromoVars.baseUrl;
  }
  const modulePath = 'modules/ventilacion2026promo/views/js/';
  return `${window.location.origin}${window.urls?.base_url || '/'}${modulePath}`;
};

const baseUrl = getModuleBaseUrl();

export class VentilacionPromoApp {
  constructor() {
    console.log('🔄 VentilacionPromoApp constructor ejecutado');
    this.initialize();
  }

  async initialize() {
    try {
      console.log('🔴 APP: Cargando módulos...');

      // ✅ Cargar todos los módulos de forma secuencial
      const { Config } = await import(`${baseUrl}core/Config.js`);
      const { StateManager } = await import(`${baseUrl}managers/StateManager.js`);
      const { ToastManager } = await import(`${baseUrl}managers/ToastManager.js`);
      const { StockManager } = await import(`${baseUrl}managers/StockManager.js`);
      const { ProductManager } = await import(`${baseUrl}managers/ProductManager.js`);
      const { BudgetManager } = await import(`${baseUrl}managers/BudgetManager.js`);
      const { CartManager } = await import(`${baseUrl}managers/CartManager.js`);
      const { NavigationManager } = await import(`${baseUrl}managers/NavigationManager.js`);

      console.log('✅ APP: Todos los módulos cargados');

      // Inicializar componentes
      this.config = Config.init();
      this.state = new StateManager();
      this.toast = ToastManager;

      // Inicializar managers
      this.stockManager = new StockManager(this.config);
      this.budgetManager = new BudgetManager(this.state, this.config, this.stockManager, this.toast);
      this.cartManager = new CartManager(this.state, this.config, this.toast);
      this.productManager = new ProductManager(this.state, this.stockManager, this.toast, this.budgetManager);
      this.navigationManager = new NavigationManager(this.config, this.state);

      if (this.stockManager.setToastManager) {
        this.stockManager.setToastManager(ToastManager);
        console.log('✅ APP: ToastManager conectado a StockManager');
      }

      console.log('✅ APP: Managers inicializados');

      // Inicializar aplicación
      this.init();

    } catch (error) {
      console.error('❌ APP: Error inicializando:', error);
    }
  }

  init() {
    console.log('🚀 Inicializando Ventilación Promo App');

    // Cachear elementos DOM
    this.cacheElements();
    console.log('✅ APP: Elementos cacheados:', this.elements);

    // Configurar managers que necesitan elementos
    if (this.budgetManager.setElements) {
      this.budgetManager.setElements(this.elements);
    }
    if (this.cartManager.setElements) {
      this.cartManager.setElements(this.elements);
    }

    // Inicializar managers
    if (this.productManager.initializeProducts) {
      this.productManager.initializeProducts();
    }
    if (this.navigationManager.initializeNavigationListeners) {
      this.navigationManager.initializeNavigationListeners();
    }

    // Bind events
    this.bindEvents();

    // Verificación inicial de stock
    // this.initializeStockCheck();

    console.log('🔧 Aplicación inicializada completada');

    if (this.state.logState) {
      this.state.logState();
    }
  }

  cacheElements() {
    this.elements = {
      totalDisplay: document.getElementById('budget-total'),
      progressBar: document.getElementById('budget-progress'),
      budgetBar: document.getElementById('budget-bar'),
      addToCartBtn: document.getElementById('add-to-cart-btn'),
      budgetMessage: document.getElementById('budget-message')
    };

    // Verificar que los elementos existen
    Object.entries(this.elements).forEach(([key, element]) => {
      if (!element) {
        console.warn(`⚠️ APP: Elemento no encontrado: ${key}`);
      }
    });
  }

  bindEvents() {
    if (this.elements.addToCartBtn) {
      this.elements.addToCartBtn.addEventListener('click', () => {
        console.log('🎯 Botón añadir al carrito clickeado');
        if (this.cartManager.addToCart) {
          this.cartManager.addToCart();
        } else {
          console.warn('⚠️ cartManager.addToCart no disponible');
        }
      });
    } else {
      console.warn('⚠️ APP: Botón addToCartBtn no encontrado');
    }
  }

  // initializeStockCheck() {
  //   setTimeout(async () => {
  //     console.log('🔍 Verificando stock inicial...');
  //     // Implementar verificación de stock inicial si es necesario
  //   }, 1500);
  // }
}