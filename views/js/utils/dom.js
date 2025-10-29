export class DomUtils {
  static cacheElements() {
    return {
      totalDisplay: document.getElementById('budget-total'),
      progressBar: document.getElementById('budget-progress'),
      budgetBar: document.getElementById('budget-bar'),
      addToCartBtn: document.getElementById('add-to-cart-btn'),
      budgetMessage: document.getElementById('budget-message'),
      promoPage: document.querySelector('.ventilacion-promo-page')
    };
  }
}