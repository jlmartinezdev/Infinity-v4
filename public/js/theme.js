/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/css/app.css"
/*!*******************************!*\
  !*** ./resources/css/app.css ***!
  \*******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./resources/js/theme.js"
/*!*******************************!*\
  !*** ./resources/js/theme.js ***!
  \*******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   applyChartTheme: () => (/* binding */ applyChartTheme),
/* harmony export */   chartAxisTheme: () => (/* binding */ chartAxisTheme),
/* harmony export */   chartLegendTheme: () => (/* binding */ chartLegendTheme),
/* harmony export */   getChartThemeColors: () => (/* binding */ getChartThemeColors),
/* harmony export */   initTheme: () => (/* binding */ initTheme),
/* harmony export */   isDarkTheme: () => (/* binding */ isDarkTheme),
/* harmony export */   toggleTheme: () => (/* binding */ toggleTheme),
/* harmony export */   watchThemeCharts: () => (/* binding */ watchThemeCharts)
/* harmony export */ });
var STORAGE_KEY = 'theme';
function isDarkTheme() {
  return document.documentElement.classList.contains('dark');
}

/** Aplica tema desde localStorage. Oscuro por defecto si no hay preferencia guardada. */
function initTheme() {
  var stored = localStorage.getItem(STORAGE_KEY);
  var isDark = stored !== 'light';
  document.documentElement.classList.toggle('dark', isDark);
  if (stored === null) {
    localStorage.setItem(STORAGE_KEY, 'dark');
  }
  updateThemeColorMeta(isDark);
}
function toggleTheme() {
  var isDark = document.documentElement.classList.toggle('dark');
  localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
  updateThemeColorMeta(isDark);
  window.dispatchEvent(new CustomEvent('theme-change', {
    detail: {
      isDark: isDark
    }
  }));
  return isDark;
}
function updateThemeColorMeta(isDark) {
  var meta = document.querySelector('meta[name="theme-color"]');
  if (!meta) {
    meta = document.createElement('meta');
    meta.name = 'theme-color';
    document.head.appendChild(meta);
  }
  meta.content = isDark ? '#111827' : '#f9fafb';
}
function getChartThemeColors() {
  var dark = isDarkTheme();
  return {
    text: dark ? '#e5e7eb' : '#374151',
    grid: dark ? 'rgba(75, 85, 99, 0.35)' : 'rgba(209, 213, 219, 0.8)'
  };
}

/** Opciones comunes de ejes y leyenda para Chart.js según el tema activo. */
function chartAxisTheme(yTickCallback) {
  var _getChartThemeColors = getChartThemeColors(),
    text = _getChartThemeColors.text,
    grid = _getChartThemeColors.grid;
  var scales = {
    x: {
      ticks: {
        color: text
      },
      grid: {
        color: grid
      }
    },
    y: {
      beginAtZero: true,
      ticks: {
        color: text
      },
      grid: {
        color: grid
      }
    }
  };
  if (typeof yTickCallback === 'function') {
    scales.y.ticks.callback = yTickCallback;
  }
  return scales;
}
function chartLegendTheme() {
  var position = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 'top';
  return {
    position: position,
    labels: {
      color: getChartThemeColors().text
    }
  };
}

/** Aplica colores de tema a un gráfico Chart.js existente. */
function applyChartTheme(chart, yTickCallback) {
  var _chart$options$plugin;
  if (!(chart !== null && chart !== void 0 && chart.options)) return;
  var _getChartThemeColors2 = getChartThemeColors(),
    text = _getChartThemeColors2.text,
    grid = _getChartThemeColors2.grid;
  var legend = (_chart$options$plugin = chart.options.plugins) === null || _chart$options$plugin === void 0 ? void 0 : _chart$options$plugin.legend;
  if (legend !== null && legend !== void 0 && legend.labels) {
    legend.labels.color = text;
  }
  ['x', 'y'].forEach(function (axis) {
    var _chart$options$scales;
    var scale = (_chart$options$scales = chart.options.scales) === null || _chart$options$scales === void 0 ? void 0 : _chart$options$scales[axis];
    if (!scale) return;
    scale.ticks = scale.ticks || {};
    scale.ticks.color = text;
    scale.grid = scale.grid || {};
    scale.grid.color = grid;
    if (axis === 'y' && typeof yTickCallback === 'function') {
      scale.ticks.callback = yTickCallback;
    }
  });
}
function watchThemeCharts(charts) {
  var yTickCallbacks = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : [];
  window.addEventListener('theme-change', function () {
    charts.forEach(function (chart, i) {
      applyChartTheme(chart, yTickCallbacks[i]);
      chart.update();
    });
  });
}
function bindThemeToggle() {
  var _document$getElementB;
  (_document$getElementB = document.getElementById('theme-toggle')) === null || _document$getElementB === void 0 || _document$getElementB.addEventListener('click', toggleTheme);
}
if (typeof window !== 'undefined') {
  window.InfinityTheme = {
    isDarkTheme: isDarkTheme,
    initTheme: initTheme,
    toggleTheme: toggleTheme,
    getChartThemeColors: getChartThemeColors,
    chartAxisTheme: chartAxisTheme,
    chartLegendTheme: chartLegendTheme,
    applyChartTheme: applyChartTheme,
    watchThemeCharts: watchThemeCharts
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindThemeToggle);
  } else {
    bindThemeToggle();
  }
}

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Check if module exists (development only)
/******/ 		if (__webpack_modules__[moduleId] === undefined) {
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"/js/theme": 0,
/******/ 			"css/app": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = self["webpackChunk"] = self["webpackChunk"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	__webpack_require__.O(undefined, ["css/app"], () => (__webpack_require__("./resources/js/theme.js")))
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["css/app"], () => (__webpack_require__("./resources/css/app.css")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;