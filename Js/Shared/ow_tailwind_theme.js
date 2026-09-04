/**
 * Shared Tailwind brand tokens — matches Admin Command Center palette.
 * Load before tailwind.config on Tailwind-based dashboards.
 */
(function (global) {
  'use strict';

  global.OW_TAILWIND_BRAND = {
    colors: {
      brand: {
        sidebar: '#0f172a',
        'sidebar-hover': '#1e293b',
        primary: '#0056b3',
        dark: '#003366',
        navy: '#0f172a',
        avatar: '#0A2A5E',
        surface: '#f8fafc',
        accent: '#0284c7',
        'accent-dark': '#0369a1',
        orange: '#f97316',
        'orange-dark': '#ea580c',
      },
    },
    boxShadow: {
      card: '0 1px 3px rgba(0, 0, 0, 0.06)',
    },
    fontFamily: {
      sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
    },
  };
})(window);
