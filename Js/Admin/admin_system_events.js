/**
 * Admin dashboard alias — see Js/ow_system_events.js for implementation.
 */
(function (global) {
  'use strict';
  if (global.OwSystemEvents) {
    global.AdminSystemEvents = global.OwSystemEvents;
    if (!global.triggerServiceAgreementNotification) {
      global.triggerServiceAgreementNotification = function (expirationDate, options) {
        return global.OwSystemEvents.triggerServiceAgreementNotification(
          (options && (options.document_name || options.contract_number)) || 'Service Agreement',
          (options && (options.start_date || options.contract_start_date)) || '',
          expirationDate,
          options || {}
        );
      };
    }
  }
})(window);
