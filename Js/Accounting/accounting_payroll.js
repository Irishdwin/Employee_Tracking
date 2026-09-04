/**
 * @deprecated Use accounting.js — kept for backward compatibility.
 */
(function (global) {
  'use strict';
  if (global.AccountingPayroll) {
    return;
  }
  global.AccountingPayroll = {
    load: function () {
      if (global.Accounting && typeof global.Accounting.load === 'function') {
        return global.Accounting.load();
      }
    }
  };
})(window);
