/**
 * Unified Google Sign-In (OAuth 2.0) for all roles — Applicant, Employee, Admin, HR, etc.
 *
 * Configure before load:
 *   window.UNIFIED_GOOGLE_SIGNIN = {
 *     googleConfigApi: 'Api/config/google_oauth_public.php',
 *     loginHandlerApi: 'Api/Login/google_login_handler.php',
 *     callbackPage: 'Html/Login/unified_google_callback.html',
 *     redirectUriPage: null // defaults to current page without query/hash
 *   };
 */
(function (global) {
  'use strict';

  var defaults = {
    googleConfigApi: 'Api/config/google_oauth_public.php',
    loginHandlerApi: 'Api/Login/google_login_handler.php',
    callbackPage: 'Html/Login/unified_google_callback.html',
    policyAcceptApi: 'Api/Applicant/accept_applicant_policy.php',
    redirectUriPage: null,
    buttonId: 'btnSignInGoogle',
    storageRedirectKey: 'onazul_google_redirect_uri',
    storageStateKey: 'google_oauth_state'
  };

  var cfg = Object.assign({}, defaults, global.UNIFIED_GOOGLE_SIGNIN || {});
  var googleClientId = '';
  var popup = null;
  var popupDone = false;

  function $(id) {
    return document.getElementById(id);
  }

  function resolveUrl(relativePath) {
    try {
      return new URL(relativePath, global.location.href).href;
    } catch (e) {
      return relativePath;
    }
  }

  function redirectUriForOAuth() {
    if (cfg.redirectUriPage) {
      return resolveUrl(cfg.redirectUriPage).replace(/[?#].*$/, '');
    }
    // Use the dedicated OAuth callback page — must match Google Console "Authorized redirect URIs" exactly.
    if (cfg.callbackPage) {
      return resolveUrl(cfg.callbackPage).replace(/[?#].*$/, '');
    }
    return global.location.href.replace(/[?#].*$/, '');
  }

  function callbackPageUrl() {
    return resolveUrl(cfg.callbackPage);
  }

  function showToast(message, type) {
    if (global.UnifiedGoogleSignin && typeof global.UnifiedGoogleSignin.onError === 'function') {
      global.UnifiedGoogleSignin.onError(message, type);
      return;
    }
    alert(message);
  }

  function loadGoogleConfig() {
    return fetch(resolveUrl(cfg.googleConfigApi), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.success && d.client_id) {
          googleClientId = d.client_id;
          return true;
        }
        showToast((d && d.message) ? d.message : 'Google sign-in is not configured.', 'danger');
        return false;
      })
      .catch(function () {
        showToast('Could not load Google sign-in configuration.', 'danger');
        return false;
      });
  }

  function openGoogleOAuth() {
    if (!googleClientId) {
      showToast('Google sign-in is not configured yet.', 'danger');
      return;
    }

    var oauthState = String(Date.now()) + '_' + Math.random().toString(16).slice(2);
    var redirectUri = redirectUriForOAuth();

    try {
      sessionStorage.setItem(cfg.storageStateKey, oauthState);
      sessionStorage.setItem(cfg.storageRedirectKey, redirectUri);
    } catch (e) {}

    var url = 'https://accounts.google.com/o/oauth2/v2/auth' +
      '?client_id=' + encodeURIComponent(googleClientId) +
      '&redirect_uri=' + encodeURIComponent(redirectUri) +
      '&response_type=code' +
      '&scope=' + encodeURIComponent('openid email profile') +
      '&prompt=select_account' +
      '&state=' + encodeURIComponent(oauthState);

    popupDone = false;
    popup = global.open(url, 'google_oauth', 'width=600,height=720');
  }

  function resolveRedirectUrl(targetHref) {
    var href = String(targetHref || '').trim();
    if (!href) return global.location.origin + '/Employee_Tracking/index.html';
    if (/^https?:\/\//i.test(href)) return href;
    if (href.charAt(0) === '/') return global.location.origin + href;
    try {
      return new URL(href, global.location.origin + '/Employee_Tracking/').href;
    } catch (e) {
      return global.location.origin + '/Employee_Tracking/index.html';
    }
  }

  function navigateAfterAuth(targetHref) {
    global.location.href = resolveRedirectUrl(targetHref);
  }

  function bindMessageListener() {
    global.addEventListener('message', function (ev) {
      if (ev.origin !== global.location.origin) return;
      var d = ev.data || {};
      if (d.type === 'onazul_google_oauth' && d.redirect) {
        popupDone = true;
        navigateAfterAuth(d.redirect);
      }
    });

    global.setInterval(function () {
      if (!popup || popup.closed) {
        if (popup && !popupDone) {
          // Popup closed without message — user may have completed auth in popup tab.
        }
        popup = null;
      }
    }, 500);
  }

  function bindButton() {
    var btn = $(cfg.buttonId);
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      openGoogleOAuth();
    });
  }

  function init() {
    bindMessageListener();
    bindButton();
    loadGoogleConfig();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  global.UnifiedGoogleSignin = {
    open: openGoogleOAuth,
    getRedirectUri: redirectUriForOAuth
  };
})(typeof window !== 'undefined' ? window : this);
