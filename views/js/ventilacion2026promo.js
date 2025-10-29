import { VentilacionPromoApp } from './core/App.js';

document.addEventListener('DOMContentLoaded', () => {
  // Inicializar la aplicación
  window.ventilacionPromoApp = new VentilacionPromoApp();

  // También exponemos la instancia globalmente por si se necesita para debugging
  console.log('🎉 Ventilación Promo App inicializada correctamente');
});