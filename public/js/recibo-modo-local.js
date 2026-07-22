/******/ (() => { // webpackBootstrap
/*!*******************************************!*\
  !*** ./resources/js/recibo-modo-local.js ***!
  \*******************************************/
/**
 * Modo de recibo desde localStorage (clave reciboModo):
 * con_grafico | sin_grafico | sin_grafico_linea
 */
var STORAGE_KEY = 'reciboModo';
var MODOS_VALIDOS = ['con_grafico', 'sin_grafico', 'sin_grafico_linea'];
function leerModo() {
  try {
    var v = localStorage.getItem(STORAGE_KEY);
    if (v === 'sin_grafico' || v === 'sin_grafico_linea') {
      return v;
    }
    return 'con_grafico';
  } catch (e) {
    return 'con_grafico';
  }
}
function aplicarModo(modo) {
  var m = MODOS_VALIDOS.includes(modo) ? modo : 'con_grafico';
  document.querySelectorAll('.recibo-modo-wrapper').forEach(function (w) {
    w.setAttribute('data-recibo-modo', m);
  });
  document.querySelectorAll('a.js-recibo-pdf-link').forEach(function (a) {
    var base = a.getAttribute('data-pdf-base');
    if (!base) return;
    var sep = base.includes('?') ? '&' : '?';
    a.setAttribute('href', base + sep + 'recibo_modo=' + encodeURIComponent(m));
  });
}
function init() {
  aplicarModo(leerModo());
  window.addEventListener('storage', function (e) {
    if (e.key === STORAGE_KEY) aplicarModo(leerModo());
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
/******/ })()
;