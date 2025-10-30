console.log('🔴 STEP 1: Script principal CARGADO');

// Verificar inmediatamente las variables disponibles
console.log('🔴 STEP 2: Variables disponibles:', {
  ventilacionPromoVars: window.ventilacionPromoVars,
  urls: window.urls,
  location: window.location.href
});

// Función simple y directa
const initializeDirect = () => {
  console.log('🔴 STEP 3: Inicialización directa');

  // Calcular baseUrl de forma directa
  let baseUrl = '';

  if (window.ventilacionPromoVars?.baseUrl) {
    baseUrl = window.ventilacionPromoVars.baseUrl;
    console.log('✅ BaseUrl desde vars:', baseUrl);
  } else if (window.urls?.base_url) {
    baseUrl = `${window.location.origin}${window.urls.base_url}modules/ventilacion2026promo/views/js/`;
    console.log('✅ BaseUrl desde urls:', baseUrl);
  } else {
    baseUrl = `${window.location.origin}/modules/ventilacion2026promo/views/js/`;
    console.log('✅ BaseUrl fallback:', baseUrl);
  }

  console.log('🔴 STEP 4: BaseUrl final:', baseUrl);

  // Crear script manualmente
  const script = document.createElement('script');
  script.type = 'module';
  // En la parte del script module, cambia esto:
  script.innerHTML = `
    console.log('🔴 SCRIPT MODULE: Iniciando módulo...');
    import('${baseUrl}core/App.js')
        .then(module => {
            console.log('✅ SCRIPT MODULE: App.js cargado', module);
            const { VentilacionPromoApp } = module;
            console.log('✅ SCRIPT MODULE: VentilacionPromoApp disponible');
            
            // ✅ Función de inicialización
            const initApp = () => {
                console.log('✅ SCRIPT MODULE: Creando instancia de app...');
                window.ventilacionPromoApp = new VentilacionPromoApp();
                console.log('🎉 Ventilación Promo App inicializada correctamente');
            };
            
            // ✅ Verificar estado del DOM
            console.log('🔴 SCRIPT MODULE: Estado del DOM:', document.readyState);
            
            if (document.readyState === 'loading') {
                console.log('⏳ SCRIPT MODULE: DOM cargando, esperando event...');
                document.addEventListener('DOMContentLoaded', () => {
                    console.log('✅ SCRIPT MODULE: DOMContentLoaded disparado');
                    initApp();
                });
            } else {
                console.log('🚀 SCRIPT MODULE: DOM ya listo, inicializando...');
                initApp();
            }
        })
        .catch(error => {
            console.error('❌ SCRIPT MODULE: Error en import:', error);
            console.error('❌ Detalles:', error.message, error.stack);
        });
`;

  console.log('🔴 STEP 5: Añadiendo script module al DOM');
  document.head.appendChild(script);
};

// Ejecutar inmediatamente
console.log('🔴 STEP 6: Ejecutando inicialización directa');
initializeDirect();
console.log('🔴 STEP 7: Script principal terminado');