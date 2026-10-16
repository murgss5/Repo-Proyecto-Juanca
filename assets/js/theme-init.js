/**
 * ============================================================
 * SENA CONTROL DE ASISTENCIA - DETECCIÓN TEMPRANA DE TEMA
 * ------------------------------------------------------------
 * Se ejecuta en <head> de forma síncrona antes del primer render
 * para evitar el parpadeo blanco (FOUC).
 * ============================================================
 */
(function () {
  'use strict';
  var STORAGE_KEY = 'sena_ingreso_theme';
  var theme = 'system';

  try {
    if (window.localStorage) {
      var stored = window.localStorage.getItem(STORAGE_KEY);
      if (stored === 'light' || stored === 'dark' || stored === 'system') {
        theme = stored;
      }
    }
  } catch (e) {
    // Almacenamiento local bloqueado o no disponible
    theme = 'system';
  }

  var prefersDark = false;
  if (window.matchMedia) {
    prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  }

  var isDark = (theme === 'dark') || (theme === 'system' && prefersDark);
  var root = document.documentElement;

  // Asignar atributos al elemento <html> inmediatamente
  root.setAttribute('data-theme', isDark ? 'dark' : 'light');
  root.setAttribute('data-theme-preference', theme);

  // Aplicar clase dark-mode en <body> y sincronizar navbar tan pronto como exista
  var syncNavbarEarly = function () {
    var nav = document.querySelector('.main-header.navbar');
    if (nav) {
      if (isDark) {
        nav.classList.add('navbar-dark');
        nav.classList.remove('navbar-light', 'navbar-white');
      } else {
        nav.classList.add('navbar-light', 'navbar-white');
        nav.classList.remove('navbar-dark');
      }
    }
  };

  if (isDark) {
    if (document.body) {
      document.body.classList.add('dark-mode');
      syncNavbarEarly();
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.add('dark-mode');
        syncNavbarEarly();
      });
    }
  }
})();
