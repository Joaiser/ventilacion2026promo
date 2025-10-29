// Configuración y constantes
export class Config {
  static init() {
    const promoPage = document.querySelector('.ventilacion-promo-page');
    return {
      minBudget: 3000,
      promoAjaxUrl: promoPage?.dataset.promoAjaxUrl || '',
      orderUrl: promoPage?.dataset.orderUrl,
      ajaxUrl: promoPage?.dataset.ajaxUrl,
      static_token: promoPage?.dataset.staticToken,
      restoreGroupUrl: promoPage?.dataset.restoreGroupUrl,
      updateGroupUrl: promoPage?.dataset.updateGroupUrl
    };
  }
}