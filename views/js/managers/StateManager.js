// Estado global de la aplicación
export class StateManager {
  constructor() {
    this.currentBudget = 0;
    this.selectedCombinations = {}; // { productId: { combinationId: { quantity, price } } }
    this.currentSelections = {};    // { productId: currentCombinationId }
    this.validNavigation = false;
  }

  /**
   * Obtener cantidad actual de una combinación
   */
  getCurrentCombinationQuantity(productId) {
    const currentCombinationId = this.currentSelections[productId];

    // Si no hay combinación seleccionada, devolver 0
    if (currentCombinationId === undefined || currentCombinationId === null) {
      return 0;
    }

    // Si no existe la estructura para este producto/combinación, devolver 0
    if (!this.selectedCombinations[productId] || !this.selectedCombinations[productId][currentCombinationId]) {
      return 0;
    }

    return this.selectedCombinations[productId][currentCombinationId].quantity || 0;
  }

  /**
   * Establecer cantidad para una combinación
   */
  setCurrentCombinationQuantity(productId, quantity) {
    const currentCombinationId = this.currentSelections[productId];

    // Si no hay currentCombinationId, inicializar con 0 para productos sin combinaciones
    if (currentCombinationId === undefined) {
      this.currentSelections[productId] = 0;
    }

    const effectiveCombinationId = this.currentSelections[productId];

    // Asegurarse de que existe la estructura
    if (!this.selectedCombinations[productId]) {
      this.selectedCombinations[productId] = {};
    }

    if (!this.selectedCombinations[productId][effectiveCombinationId]) {
      // Obtener el precio del producto desde la tarjeta
      const card = document.querySelector(`.mini-card[data-id="${productId}"]`);
      let price = 0;

      if (card) {
        price = parseFloat(card.dataset.price.replace(',', '.')) || 0;
        console.log(`🎯 Precio obtenido para producto ${productId}:`, price);
      }

      this.selectedCombinations[productId][effectiveCombinationId] = {
        quantity: 0,
        price: price
      };
    }

    // Actualizar la cantidad
    this.selectedCombinations[productId][effectiveCombinationId].quantity = quantity;
    console.log(`✏️ Actualizada cantidad: Producto ${productId}, Comb ${effectiveCombinationId} = ${quantity}`);
  }

  /**
   * Obtener estado actual para debugging
   */
  getState() {
    return {
      currentBudget: this.currentBudget,
      selectedCombinations: this.selectedCombinations,
      currentSelections: this.currentSelections,
      validNavigation: this.validNavigation
    };
  }

  /**
   * Log del estado actual
   */
  logState() {
    console.log('🔧 Estado actual:');
    console.log('currentBudget:', this.currentBudget);
    console.log('currentSelections:', this.currentSelections);
    console.log('selectedCombinations:', this.selectedCombinations);
    console.log('validNavigation:', this.validNavigation);
  }
}