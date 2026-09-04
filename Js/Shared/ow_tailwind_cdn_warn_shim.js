/**
 * Suppresses the Tailwind CDN "do not use in production" console warning on local/XAMPP builds.
 * Load this script immediately before https://cdn.tailwindcss.com
 */
(function () {
  'use strict';
  if (window.__owTailwindWarnShim) return;
  window.__owTailwindWarnShim = true;
  var originalWarn = console.warn;
  console.warn = function () {
    var first = arguments[0];
    if (typeof first === 'string' && first.indexOf('cdn.tailwindcss.com') !== -1) {
      return;
    }
    return originalWarn.apply(console, arguments);
  };
})();
