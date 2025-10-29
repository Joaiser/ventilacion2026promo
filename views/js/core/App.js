import { Config } from './Config.js';
import { StateManager } from '../managers/StateManager.js';
import { ToastManager } from '../managers/ToastManager.js';
import { StockManager } from '../managers/StockManager.js';
import { ProductManager } from '../managers/ProductManager.js';
import { BudgetManager } from '../managers/BudgetManager.js';
import { CartManager } from '../managers/CartManager.js';
import { NavigationManager } from '../managers/NavigationManager.js';

export class VentilacionPromoApp {
  constructor() {
    this.config = Config.init();
    this.state = new StateManager();
    this.toast = ToastManager;

    // Inicializar managers
    this.stockManager = new StockManager(this.config);
    this.budgetManager = new BudgetManager(this.state, this.config, this.stockManager, this.toast);
    this.cartManager = new CartManager(this.state, this.config, this.toast);
    this.productManager = new ProductManager(this.state, this.stockManager, this.toast, this.budgetManager);
    this.navigationManager = new NavigationManager(this.config, this.state);

    this.init();
  }

  init() {
    console.log('🚀 Inicializando Ventilación Promo App');

    // Cachear elementos DOM
    this.cacheElements();

    // Configurar managers que necesitan elementos
    this.budgetManager.setElements(this.elements);
    this.cartManager.setElements(this.elements);

    // Inicializar managers
    this.productManager.initializeProducts();
    this.navigationManager.initializeNavigationListeners();

    // Bind events
    this.bindEvents();

    // Verificación inicial de stock
    this.initializeStockCheck();

    console.log('🔧 Aplicación inicializada completada');
    this.state.logState();
  }

  cacheElements() {
    this.elements = {
      totalDisplay: document.getElementById('budget-total'),
      progressBar: document.getElementById('budget-progress'),
      budgetBar: document.getElementById('budget-bar'),
      addToCartBtn: document.getElementById('add-to-cart-btn'),
      budgetMessage: document.getElementById('budget-message')
    };
  }

  bindEvents() {
    this.elements.addToCartBtn.addEventListener('click', () => {
      this.cartManager.addToCart();
    });
  }

  initializeStockCheck() {
    setTimeout(async () => {
      console.log('🔍 Verificando stock inicial...');
      // Implementar verificación de stock inicial si es necesario
    }, 1500);
  }
}