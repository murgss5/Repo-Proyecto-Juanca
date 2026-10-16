/**
 * ============================================================
 * SENA CONTROL DE ASISTENCIA - GESTOR GLOBAL DE TEMAS
 * ------------------------------------------------------------
 * Gestiona la selección, persistencia, sincronización entre
 * pestañas, modo sistema y actualización dinámica de gráficos
 * (Chart.js 3.x) y componentes UI sin recargar la página.
 * ============================================================
 */
(function (window, document) {
  'use strict';

  var STORAGE_KEY = 'sena_ingreso_theme';
  var VALID_THEMES = ['light', 'dark', 'system'];
  var systemMedia = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  var ThemeManager = {
    /**
     * Obtiene la preferencia guardada de forma segura
     * @returns {'light'|'dark'|'system'}
     */
    getPreference: function () {
      try {
        if (window.localStorage) {
          var saved = window.localStorage.getItem(STORAGE_KEY);
          if (VALID_THEMES.indexOf(saved) !== -1) {
            return saved;
          }
        }
      } catch (e) {
        // Almacenamiento local bloqueado o no disponible
      }
      return 'system';
    },

    /**
     * Guarda la preferencia en localStorage de forma segura y aplica el tema
     * @param {'light'|'dark'|'system'} theme
     */
    setPreference: function (theme) {
      if (VALID_THEMES.indexOf(theme) === -1) {
        theme = 'system';
      }
      try {
        if (window.localStorage) {
          window.localStorage.setItem(STORAGE_KEY, theme);
        }
      } catch (e) {
        // Fallback en memoria si el almacenamiento está restringido
      }
      this.applyTheme(theme);
    },

    /**
     * Determina si el tema efectivo es oscuro
     * @param {'light'|'dark'|'system'} [theme]
     * @returns {boolean}
     */
    isEffectiveDark: function (theme) {
      if (typeof theme === 'undefined') {
        theme = this.getPreference();
      }
      if (theme === 'dark') {
        return true;
      }
      if (theme === 'light') {
        return false;
      }
      return !!(systemMedia && systemMedia.matches);
    },

    /**
     * Aplica el tema inmediatamente sin recargar la página
     * @param {'light'|'dark'|'system'} [theme]
     */
    applyTheme: function (theme) {
      if (typeof theme === 'undefined' || VALID_THEMES.indexOf(theme) === -1) {
        theme = this.getPreference();
      }

      var isDark = this.isEffectiveDark(theme);
      var root = document.documentElement;

      // Actualizar atributos en <html>
      root.setAttribute('data-theme', isDark ? 'dark' : 'light');
      root.setAttribute('data-theme-preference', theme);

      // Coordinar con la clase dark-mode de AdminLTE en <body> sin sobrescribir otras clases
      if (document.body) {
        if (isDark) {
          document.body.classList.add('dark-mode');
        } else {
          document.body.classList.remove('dark-mode');
        }
      }

      // Sincronizar clases de la barra superior (navbar) entre modo claro y oscuro
      var navbars = document.querySelectorAll('.main-header.navbar');
      navbars.forEach(function (nav) {
        if (isDark) {
          nav.classList.add('navbar-dark');
          nav.classList.remove('navbar-light', 'navbar-white');
        } else {
          nav.classList.add('navbar-light', 'navbar-white');
          nav.classList.remove('navbar-dark');
        }
      });

      // Actualizar los controles visuales del selector de tema en navbar / login
      this.updateUI(theme, isDark);

      // Actualizar gráficas Chart.js instaladas
      this.updateCharts(isDark);

      // Disparar evento para componentes desacoplados
      try {
        var evento = new CustomEvent('sena-theme-changed', {
          detail: { theme: theme, isDark: isDark }
        });
        window.dispatchEvent(evento);
      } catch (e) {
        // Compatibilidad con navegadores antiguos
      }
    },

    /**
     * Actualiza iconos, etiquetas y estado activo del selector en la interfaz
     * @param {'light'|'dark'|'system'} theme
     * @param {boolean} isDark
     */
    updateUI: function (theme, isDark) {
      var iconClassMap = {
        light: 'fa-sun text-warning',
        dark: 'fa-moon text-primary',
        system: 'fa-desktop text-secondary'
      };
      var labelMap = {
        light: 'Claro',
        dark: 'Oscuro',
        system: 'Sistema'
      };

      // Actualizar iconos y textos en todos los botones selectores (navbar, login)
      var currentIcons = document.querySelectorAll('.sena-theme-icon-current');
      currentIcons.forEach(function (icon) {
        icon.className = 'fas ' + (iconClassMap[theme] || 'fa-desktop') + ' sena-theme-icon-current';
      });

      var currentLabels = document.querySelectorAll('.sena-theme-label-current');
      currentLabels.forEach(function (label) {
        label.textContent = labelMap[theme] || 'Sistema';
      });

      var toggleBtns = document.querySelectorAll('.sena-theme-toggle-btn');
      toggleBtns.forEach(function (btn) {
        btn.setAttribute('aria-label', 'Cambiar apariencia (Tema actual: ' + (labelMap[theme] || 'Sistema') + ')');
      });

      // Actualizar marcas de verificación y estado activo en opciones del dropdown
      var options = document.querySelectorAll('.sena-theme-option');
      options.forEach(function (opt) {
        var optVal = opt.getAttribute('data-theme-value');
        var isActive = (optVal === theme);
        opt.classList.toggle('active', isActive);
        var checkIcon = opt.querySelector('.sena-theme-check');
        if (checkIcon) {
          checkIcon.style.visibility = isActive ? 'visible' : 'hidden';
        }
      });
    },

    /**
     * Actualiza los colores de las gráficas Chart.js sin recrear instancias ni alterar datos
     * @param {boolean} isDark
     */
    updateCharts: function (isDark) {
      if (typeof Chart === 'undefined') {
        return;
      }

      var textColor = isDark ? '#F1F5F9' : '#495057';
      var textSecondary = isDark ? '#CBD5E1' : '#6c757d';
      var gridColor = isDark ? '#3B475B' : 'rgba(0, 0, 0, 0.08)';
      var tooltipBg = isDark ? '#1F2937' : 'rgba(0, 0, 0, 0.8)';
      var tooltipBorder = isDark ? '#3B475B' : 'transparent';

      // Actualizar valores predeterminados para nuevas gráficas
      if (Chart.defaults) {
        Chart.defaults.color = textSecondary;
        Chart.defaults.borderColor = gridColor;
      }

      // Actualizar instancias existentes registradas en Chart.js v3.x
      if (Chart.instances) {
        Object.keys(Chart.instances).forEach(function (key) {
          var chart = Chart.instances[key];
          if (!chart || !chart.options) {
            return;
          }

          // Escalas de ejes X e Y
          if (chart.options.scales) {
            Object.keys(chart.options.scales).forEach(function (scaleKey) {
              var scale = chart.options.scales[scaleKey];
              if (!scale) return;
              if (scale.ticks) {
                scale.ticks.color = textSecondary;
              }
              if (scale.grid) {
                scale.grid.color = gridColor;
                scale.grid.borderColor = gridColor;
              }
            });
          }

          // Plugins: leyenda y tooltip
          if (chart.options.plugins) {
            if (chart.options.plugins.legend && chart.options.plugins.legend.labels) {
              chart.options.plugins.legend.labels.color = textColor;
            }
            if (chart.options.plugins.tooltip) {
              chart.options.plugins.tooltip.backgroundColor = tooltipBg;
              chart.options.plugins.tooltip.titleColor = textColor;
              chart.options.plugins.tooltip.bodyColor = textSecondary;
              chart.options.plugins.tooltip.borderColor = tooltipBorder;
              chart.options.plugins.tooltip.borderWidth = isDark ? 1 : 0;
            }
          }

          // Dona / Pastel: borde de rebanada
          if (chart.data && chart.data.datasets) {
            chart.data.datasets.forEach(function (dataset) {
              if (chart.config && (chart.config.type === 'doughnut' || chart.config.type === 'pie')) {
                dataset.borderColor = isDark ? '#1F2937' : '#ffffff';
              }
            });
          }

          chart.update('none'); // Actualización inmediata sin animaciones de rebote
        });
      }
    },

    /**
     * Inicializa el sistema de temas y listeners
     */
    init: function () {
      var self = this;
      var currentPref = this.getPreference();
      this.applyTheme(currentPref);

      // Escuchar cambios de preferencia del sistema operativo SOLO cuando está seleccionado 'system'
      if (systemMedia) {
        var onSystemChange = function () {
          if (self.getPreference() === 'system') {
            self.applyTheme('system');
          }
        };

        if (systemMedia.addEventListener) {
          systemMedia.addEventListener('change', onSystemChange);
        } else if (systemMedia.addListener) {
          systemMedia.addListener(onSystemChange);
        }
      }

      // Sincronización entre pestañas cuando otra pestaña cambia el tema
      window.addEventListener('storage', function (e) {
        if (e.key === STORAGE_KEY) {
          var newPref = e.newValue || 'system';
          self.applyTheme(newPref);
        }
      });

      // Delegación de eventos para clicks en opciones de tema
      document.addEventListener('click', function (e) {
        var opt = e.target.closest('.sena-theme-option');
        if (opt) {
          e.preventDefault();
          var newTheme = opt.getAttribute('data-theme-value');
          self.setPreference(newTheme);
        }
      });
    }
  };

  // Exponer API global unificada
  window.SenaTheme = ThemeManager;

  // Auto-inicializar cuando el DOM esté disponible
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      ThemeManager.init();
    });
  } else {
    ThemeManager.init();
  }

})(window, document);
