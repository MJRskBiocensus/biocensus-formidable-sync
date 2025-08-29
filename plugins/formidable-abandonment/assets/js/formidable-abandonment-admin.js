/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@wordpress/dom-ready/build-module/index.js":
/*!*****************************************************************!*\
  !*** ./node_modules/@wordpress/dom-ready/build-module/index.js ***!
  \*****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (/* binding */ domReady)\n/* harmony export */ });\n/**\n * @typedef {() => void} Callback\n *\n * TODO: Remove this typedef and inline `() => void` type.\n *\n * This typedef is used so that a descriptive type is provided in our\n * automatically generated documentation.\n *\n * An in-line type `() => void` would be preferable, but the generated\n * documentation is `null` in that case.\n *\n * @see https://github.com/WordPress/gutenberg/issues/18045\n */\n\n/**\n * Specify a function to execute when the DOM is fully loaded.\n *\n * @param {Callback} callback A function to execute after the DOM is ready.\n *\n * @example\n * ```js\n * import domReady from '@wordpress/dom-ready';\n *\n * domReady( function() {\n * \t//do something after DOM loads.\n * } );\n * ```\n *\n * @return {void}\n */\nfunction domReady(callback) {\n  if (typeof document === 'undefined') {\n    return;\n  }\n  if (document.readyState === 'complete' ||\n  // DOMContentLoaded + Images/Styles/etc loaded, so we call directly.\n  document.readyState === 'interactive' // DOMContentLoaded fires at this point, so we call directly.\n  ) {\n    return void callback();\n  }\n\n  // DOMContentLoaded has not fired yet, delay callback until then.\n  document.addEventListener('DOMContentLoaded', callback);\n}\n//# sourceMappingURL=index.js.map\n\n//# sourceURL=webpack://formidable-abandonment/./node_modules/@wordpress/dom-ready/build-module/index.js?");

/***/ }),

/***/ "./assets/src/admin/action.js":
/*!************************************!*\
  !*** ./assets/src/admin/action.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* global frmGlobal, frmDom, ajaxurl, jQuery */\n\n/**\n * Email action events.\n *\n * @since 1.1\n */\nconst emailActionEntities = {\n  abandoned: 'abandoned',\n  create: 'abd-edit-link',\n  draft: 'abd-draft'\n};\n\n/**\n * Class for creating and managing a form action.\n *\n * @since 1.0\n */\nconst createAction = {\n  /**\n   * Last action ID in dom uses as a count.\n   *\n   * @since 1.0\n   */\n  lastActionId: 0,\n  /**\n   * Creates a form action\n   *\n   * @since 1.0\n   */\n  init() {\n    for (const key in emailActionEntities) {\n      if (emailActionEntities.hasOwnProperty(key)) {\n        const htmlId = emailActionEntities[key];\n        const element = document.getElementById(`${htmlId}-email-action`);\n        if (element) {\n          element.addEventListener('click', () => {\n            this.createAction(key);\n          });\n        }\n      }\n    }\n  },\n  /**\n   * Creates a form action\n   *\n   * @param {string} $type\n   * @since 1.0\n   */\n  async createAction($type) {\n    const {\n      div\n    } = frmDom;\n    const actionId = this.getNewActionId();\n    const formId = document.getElementById('form_id').value;\n    const formData = new FormData();\n    formData.append('action', 'frm_add_form_action');\n    formData.append('type', 'email');\n    formData.append('list_id', actionId);\n    formData.append('form_id', formId);\n    formData.append('abandonment_form_action', $type);\n    formData.append('nonce', frmGlobal.nonce);\n    let response = '';\n    try {\n      response = await fetch(ajaxurl, {\n        method: 'POST',\n        body: formData\n      });\n    } catch (err) {\n      return;\n    }\n    const html = await response.text();\n    document.querySelector(`.frm-form-setting-tabs li a[href=\"#email_settings\"]`).dispatchEvent(new Event('click'));\n    document.querySelectorAll('.frm_form_action_settings.open').forEach(setting => setting.classList.remove('open'));\n    const newActionContainer = div();\n    newActionContainer.innerHTML = html;\n    const widgetTop = newActionContainer.querySelector('.widget-top');\n    const actionsList = document.getElementById('frm_notification_settings');\n    Array.from(newActionContainer.children).forEach(child => actionsList.appendChild(child));\n    const newAction = document.getElementById(`frm_form_action_${actionId}`);\n    newAction.classList.add('open');\n    document.getElementById('post-body-content').scroll({\n      top: newAction.offsetTop + 10,\n      left: 0,\n      behavior: 'smooth'\n    });\n\n    // Check if icon should be active\n    document.querySelectorAll('.frm_email_action').forEach(trigger => {\n      if (trigger.querySelector('.frm_show_upgrade')) {\n        // Prevent disabled action becoming active.\n        return;\n      }\n      trigger.classList.remove('frm_inactive_action', 'frm_already_used');\n      trigger.classList.add('frm_active_action');\n    });\n    this.showInputIcon(`#frm_form_action_${actionId}`);\n    jQuery('#frm_form_action_' + actionId + ' .frm_multiselect').hide().each(frmDom.bootstrap.multiselect.init);\n    frmDom.autocomplete.initAutocomplete('page', newAction);\n    if (widgetTop) {\n      jQuery(widgetTop).trigger('frm-action-loaded');\n    }\n  },\n  /**\n   * Returns a new action ID.\n   *\n   * @since 1.0\n   *\n   * @return {number} The new action ID.\n   */\n  getNewActionId() {\n    const actionSettings = document.querySelectorAll('.frm_form_action_settings');\n    let len = this.getNewRowId(actionSettings, 'frm_form_action_');\n    if (document.getElementById(`frm_form_action_${len}`)) {\n      len += 100;\n    }\n    if (this.lastActionId >= len) {\n      len = this.lastActionId + 1;\n    }\n    this.lastActionId = len;\n    return len;\n  },\n  /**\n   * Returns a new row ID.\n   *\n   * @since 1.0\n   *\n   * @param {Array}  rows             - The rows.\n   * @param {string} replace          - The string to replace.\n   * @param {any}    [defaultValue=0] - The default value if rows are empty.\n   * @return {number} The new row ID.\n   */\n  getNewRowId(rows, replace, defaultValue = 0) {\n    if (!rows.length) {\n      return defaultValue;\n    }\n    return parseInt(rows[rows.length - 1].id.replace(replace, ''), 10) + 1;\n  },\n  /**\n   * Displays the input icon.\n   *\n   * @since 1.0\n   *\n   * @param {string} parentClass The parent class.\n   */\n  showInputIcon(parentClass = '') {\n    this.maybeAddFieldSelection(parentClass);\n    const selectors = document.querySelectorAll(`${parentClass} .frm_has_shortcodes:not(.frm-with-right-icon) input,${parentClass} .frm_has_shortcodes:not(.frm-with-right-icon) textarea`);\n    selectors.forEach(selector => {\n      const span = document.createElement('span');\n      span.classList.add('frm-with-right-icon');\n      selector.parentNode.insertBefore(span, selector);\n      span.appendChild(selector);\n      span.insertAdjacentHTML('afterbegin', '<svg class=\"frmsvg frm-show-box\"><use xlink:href=\"#frm_more_horiz_solid_icon\"/></svg>');\n    });\n  },\n  /**\n   * Checks for fields that were using the old sidebar and adds class if necessary.\n   *\n   * @since 1.0\n   *\n   * @param {string} parentClass The parent class.\n   */\n  maybeAddFieldSelection(parentClass) {\n    const missingClassSelectors = document.querySelectorAll(`${parentClass} :not(.frm_has_shortcodes) .frm_not_email_message, ${parentClass} :not(.frm_has_shortcodes) .frm_not_email_to, ${parentClass} :not(.frm_has_shortcodes) .frm_not_email_subject`);\n    missingClassSelectors.forEach(selector => {\n      selector.parentNode.classList.add('frm_has_shortcodes');\n    });\n  }\n};\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (createAction);\n\n//# sourceURL=webpack://formidable-abandonment/./assets/src/admin/action.js?");

/***/ }),

/***/ "./assets/src/admin/index.js":
/*!***********************************!*\
  !*** ./assets/src/admin/index.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/dom-ready */ \"./node_modules/@wordpress/dom-ready/build-module/index.js\");\n/* harmony import */ var _action__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./action */ \"./assets/src/admin/action.js\");\n/* harmony import */ var _token_helper__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./token-helper */ \"./assets/src/admin/token-helper.js\");\n/**\n * WordPress dependencies\n */\n\n\n/**\n * Internal dependencies\n */\n\n\n\n/**\n * Load admin side js on dom ready.\n */\n(0,_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2__[\"default\"])(() => {\n  _action__WEBPACK_IMPORTED_MODULE_0__[\"default\"].init();\n  _token_helper__WEBPACK_IMPORTED_MODULE_1__[\"default\"].init();\n});\n\n//# sourceURL=webpack://formidable-abandonment/./assets/src/admin/index.js?");

/***/ }),

/***/ "./assets/src/admin/token-helper.js":
/*!******************************************!*\
  !*** ./assets/src/admin/token-helper.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var _global_helper__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../global-helper */ \"./assets/src/global-helper.js\");\n/* harmony import */ var _components_clipboard__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../components/clipboard */ \"./assets/src/components/clipboard.js\");\n/* global frm_js */\n\n\n\n/**\n * Token helper.\n *\n * @since 1.1\n */\nconst tokenHelper = {\n  /**\n   * Entry ID.\n   *\n   * @since 1.1\n   */\n  entryID: null,\n  /**\n   * Listener added on reset link.\n   *\n   * @since 1.1\n   * @type {boolean}\n   */\n  listening: false,\n  /**\n   * Trigger entry detail page JS.\n   *\n   * @since 1.1\n   */\n  init() {\n    // Bail if this is entry detail page.\n    const resetLink = document.getElementById('frm-entry-detail-reset-token');\n    if (!resetLink) {\n      return;\n    }\n    resetLink.addEventListener('click', e => {\n      e.preventDefault();\n      this.entryID = e.target.getAttribute('data-entry-id');\n    });\n    this.watchConfirmLink();\n    (0,_components_clipboard__WEBPACK_IMPORTED_MODULE_1__[\"default\"])('frm-abandonment-copy-link-btn', 'frm-abandonment-link-btn', 'href');\n  },\n  watchConfirmLink() {\n    wp.hooks.addAction('frmAdmin.beforeOpenConfirmModal', 'frmAbdnReset', args => {\n      if (this.listening || !args.link || !args.link.getAttribute('data-frmreset')) {\n        return;\n      }\n      const confirmLink = document.getElementById('frm-confirmed-click');\n      if (!confirmLink) {\n        return;\n      }\n      const handleClick = e => {\n        if (!e.target.getAttribute('data-frmreset')) {\n          return;\n        }\n        e.preventDefault();\n        this.resetLink();\n\n        // Remove the event listener after it has been handled\n        confirmLink.removeEventListener('click', handleClick);\n        this.listening = false;\n      };\n      confirmLink.addEventListener('click', handleClick);\n      this.listening = true;\n    });\n  },\n  /**\n   * Try to reset link.\n   *\n   * @since 1.1\n   */\n  resetLink() {\n    const formData = new FormData();\n    formData.append('action', 'frm_abandoned_reset_token');\n    formData.append('nonce', frm_js.nonce); // eslint-disable-line camelcase\n    formData.append('entry_id', this.entryID);\n    (0,_global_helper__WEBPACK_IMPORTED_MODULE_0__.doAjax)(formData).then(response => {\n      if (response.success) {\n        const tokenLink = response.data.token_link;\n        document.getElementById('frm-abandonment-link-btn').href = tokenLink;\n        document.querySelector('.frm-abandon-short-token').textContent = response.data.token_label;\n      }\n    });\n  }\n};\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (tokenHelper);\n\n//# sourceURL=webpack://formidable-abandonment/./assets/src/admin/token-helper.js?");

/***/ }),

/***/ "./assets/src/components/clipboard.js":
/*!********************************************!*\
  !*** ./assets/src/components/clipboard.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (/* binding */ copyClipBoard)\n/* harmony export */ });\n/**\n * Copy link to clipboard on click and display the tooltip.\n *\n * @param {string} elID      Element id of copy link button.\n * @param {string} elValueID Element id of value.\n * @param {string} param     Element attribute.\n *\n * @since 1.1\n */\nfunction copyClipBoard(elID, elValueID, param) {\n  document.addEventListener('click', e => {\n    const copyEl = document.getElementById(elID);\n    if (!copyEl || !e.target.id.match(elID) && !copyEl.contains(e.target)) {\n      // Only continue if the click is on the exact element or child.\n      return;\n    }\n    e.preventDefault();\n    copyToClipboard(document.getElementById(elValueID), param);\n\n    // If .frm-abandonment-copy-success exists, remove it.\n    const success = document.querySelector('.frm-abandonment-copy-success');\n    if (success) {\n      success.remove();\n    }\n    const message = document.createElement('span');\n    message.setAttribute('class', 'frm-abandonment-copy-success');\n    message.innerHTML = '<svg width=\"16px\" height=\"16px\" class=\"frmsvg\" viewBox=\"0 0 10 8\"><path d=\"M9.2 1c.2.3.2.7 0 1l-5 5c-.3.2-.6.2-.9 0L.8 4.3a.6.6 0 0 1 .9-.8l2 2 4.6-4.5c.3-.3.6-.3.9 0Z\"/></svg>';\n    message.style.margin = '0 5px';\n    copyEl.parentNode.insertBefore(message, copyEl.nextSibling);\n    setTimeout(() => {\n      message.remove();\n    }, 6000);\n  });\n}\n\n/**\n * Copy link to clipboard.\n *\n * @param {string} element Element.\n * @param {string} param   Element attribute.\n *\n * @since 1.1\n */\nasync function copyToClipboard(element, param) {\n  if (!element) {\n    return;\n  }\n  if (navigator.clipboard) {\n    try {\n      await navigator.clipboard.writeText(element.getAttribute(param));\n    } catch (err) {\n      // eslint-disable-next-line no-console\n      console.log(err);\n    }\n  }\n}\n\n//# sourceURL=webpack://formidable-abandonment/./assets/src/components/clipboard.js?");

/***/ }),

/***/ "./assets/src/global-helper.js":
/*!*************************************!*\
  !*** ./assets/src/global-helper.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   doAjax: () => (/* binding */ doAjax)\n/* harmony export */ });\n/* global frm_js */\n\n/**\n * Handling Ajax.\n *\n * @param {Object} args\n * @since 1.1\n * @return {string|Object} Success object or message on failure.\n */\nconst doAjax = async args => {\n  if (!args) {\n    return {\n      success: false,\n      message: 'No data to send.'\n    };\n  }\n  const response = await fetch(frm_js.ajax_url,\n  // eslint-disable-line camelcase\n  {\n    method: 'POST',\n    credentials: 'same-origin',\n    headers: {\n      'Cache-Control': 'no-cache'\n    },\n    body: args\n  });\n  return Promise.resolve(response.json());\n};\n\n//# sourceURL=webpack://formidable-abandonment/./assets/src/global-helper.js?");

/***/ })

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
/************************************************************************/
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
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval devtool is used.
/******/ 	var __webpack_exports__ = __webpack_require__("./assets/src/admin/index.js");
/******/ 	
/******/ })()
;