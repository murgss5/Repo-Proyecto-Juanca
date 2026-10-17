/* ============================================================
   SCRIPTS PERSONALIZADOS
   Sistema de Control de Ingreso de Aprendices - SENA
   - Tablas con DataTables (paginación, búsqueda y filtros)
   - Tooltips
   - Gráficas con Chart.js
   ============================================================ */

(function ($) {
  "use strict";

  // Ruta base del proyecto (leída desde <body data-base="...">)
  var baseUrl = $("body").data("base") || "";

  // ---------- Tooltips ----------
  $(function () {
    $('[data-toggle="tooltip"]').tooltip();
  });

  // ---------- Modales: nombres / datos dinámicos ----------
  $(document).on("show.bs.modal", "#modalEliminar, #modalAprobar, #modalRechazar, #modalVer, #modalVerInstructor, #modalVerFicha", function (event) {
    var boton = $(event.relatedTarget);
    var nombre = boton.data("nombre");
    var ficha = boton.data("ficha");
    if (nombre !== undefined && nombre !== "") {
      $(this).find(".nombre-aprendiz").text(nombre);
      $(this).find(".nombre-instructor").text(nombre);
    }
    if (boton.data("id-excusa") !== undefined) {
      $(this).find(".id-excusa").val(boton.data("id-excusa"));
    }
    if (ficha !== undefined && ficha !== "") {
      $(this).find(".ficha-numero").text(ficha);
      $(this).find(".ficha-aprendiz").text(ficha);
    }
    if ($(this).is("#modalVer")) {
      $(this).find(".documento-aprendiz").text(boton.data("documento") || "");
      $(this).find(".telefono-aprendiz").text(boton.data("telefono") || "Sin registro");
      $(this).find(".rfid-aprendiz").text(boton.data("rfid") || "");
    }
  });

  // ---------- Label del campo de archivo (formularios) ----------
  $(document).on("change", ".custom-file-input", function () {
    var nombreArchivo = $(this).val().split("\\").pop();
    $(this).next(".custom-file-label").text(nombreArchivo ? nombreArchivo : "Seleccione un archivo");
  });

  // ---------- DataTables: tablas con clase .tabla-datos ----------
  $(function () {
    $(".tabla-datos").each(function () {
      var mensajeVacio = $(this).data("empty-message") || "No hay datos para mostrar.";
      var tabla = $(this).DataTable({
        autoWidth: false,
        pageLength: 10,
        order: [],
        lengthMenu: [5, 10, 25, 50],
        language: {
          url: baseUrl + "assets/plugins/datatables/es-ES.json",
          emptyTable: mensajeVacio
        }
      });

      // Buscador visual personalizado (input .buscador-tabla)
      var buscador = $(this).closest(".card").find(".buscador-tabla");
      if (buscador.length) {
        buscador.on("keyup", function () {
          tabla.search($(this).val()).draw();
        });
      }

      // Filtros visuales por columna (select .filtro-tabla)
      var filtros = $(this).closest(".card").find(".filtro-tabla");
      filtros.each(function () {
        var columna = parseInt($(this).data("col"), 10);
        var exacto = $(this).data("exacto") === true || $(this).data("exacto") === "true";
        $(this).on("change", function () {
          var valor = $(this).val();
          if (valor === "") {
            tabla.column(columna).search("").draw();
          } else if (exacto) {
            // Filtro exacto (útil para badges de estado)
            var escapeRegex = function (texto) {
              return texto.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
            };
            tabla.column(columna).search("^" + escapeRegex(valor) + "$", true, false).draw();
          } else {
            tabla.column(columna).search(valor).draw();
          }
        });
      });
    });

    // Ocultar el buscador por defecto de DataTables (usamos el propio)
    $(".dataTables_filter").hide();
  });

  // ---------- Gráficas con Chart.js (datos de demostración) ----------
  $(function () {
    if (typeof Chart === "undefined") {
      return;
    }

    // Gráfica: Asistencia de la semana (Panel administrador)
    if (document.getElementById("graficoAsistenciaSemanal")) {
      var etiquetasSemana = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];
      var datosGraficaAdminSemana = window.datosGraficaAdminSemana || {
        labels: etiquetasSemana,
        presentes: [0, 0, 0, 0, 0, 0], retardos: [0, 0, 0, 0, 0, 0], inasistencias: [0, 0, 0, 0, 0, 0]
      };
      new Chart(document.getElementById("graficoAsistenciaSemanal"), {
        type: "bar",
        data: {
          labels: datosGraficaAdminSemana.labels,
          datasets: [
            {
              label: "Presentes",
              data: datosGraficaAdminSemana.presentes,
              backgroundColor: "#39a900"
            },
            {
              label: "Retardos",
              data: datosGraficaAdminSemana.retardos,
              backgroundColor: "#f39c12"
            },
            {
              label: "Inasistencias",
              data: datosGraficaAdminSemana.inasistencias,
              backgroundColor: "#dc3545"
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: "top" } },
          scales: {
            y: { beginAtZero: true, ticks: { stepSize: 5 } }
          }
        }
      });
    }

    // Gráfica de dona: Distribución de asistencia de hoy (Panel administrador)
    if (document.getElementById("graficoAsistenciaHoy")) {
      var datosGraficaAdminHoy = window.datosGraficaAdminHoy || [0, 0, 0, 0];
      new Chart(document.getElementById("graficoAsistenciaHoy"), {
        type: "doughnut",
        data: {
          labels: ["Presentes", "Retardos", "Inasistencias", "Salidas tempranas"],
          datasets: [{
            data: datosGraficaAdminHoy,
            backgroundColor: ["#39a900", "#f39c12", "#dc3545", "#007bff"]
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: "bottom" } }
        }
      });
    }

    // Gráfica: Asistencia semanal del instructor
    if (document.getElementById("graficoSemanaInstructor")) {
      var datosSemanaInstructor = window.datosGraficaSemanaInstructor || {
        labels: etiquetasSemana,
        puntuales: [0, 0, 0, 0, 0, 0],
        retardos: [0, 0, 0, 0, 0, 0]
      };

      new Chart(document.getElementById("graficoSemanaInstructor"), {
        type: "line",
        data: {
          labels: datosSemanaInstructor.labels,
          datasets: [
            {
              label: "Puntuales",
              data: datosSemanaInstructor.puntuales,
              borderColor: "#39a900",
              backgroundColor: "rgba(57,169,0,0.15)",
              fill: true,
              tension: 0.3
            },
            {
              label: "Retardos",
              data: datosSemanaInstructor.retardos,
              borderColor: "#f39c12",
              backgroundColor: "rgba(243,156,18,0.12)",
              fill: true,
              tension: 0.3
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: "top" } },
          scales: { y: { beginAtZero: true, ticks: { stepSize: 2 } } }
        }
      });
    }

    // Gráfica de dona: Asistencia personal del aprendiz
    if (document.getElementById("graficoMiAsistencia")) {
      var datosGraficaAprendiz = window.datosGraficaAprendiz || [0, 0, 0, 0];
      new Chart(document.getElementById("graficoMiAsistencia"), {
        type: "doughnut",
        data: {
          labels: ["Asistencias", "Retardos", "Inasistencias", "Salidas tempranas"],
          datasets: [{
            data: datosGraficaAprendiz,
            backgroundColor: ["#39a900", "#f39c12", "#dc3545", "#007bff"]
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: "bottom" } }
        }
      });
    }
  });

  // ---------- Barra de transición de página (Loading bar) ----------
  function iniciarTransicion() {
    var bar = document.getElementById("pageTransitionBar");
    if (!bar) {
      bar = document.createElement("div");
      bar.id = "pageTransitionBar";
      bar.className = "page-transition-bar";
      document.body.prepend(bar);
    }
    bar.classList.remove("page-transition-finish");
    bar.classList.remove("page-transition-loading");
    void bar.offsetWidth; // Forzar reflow para reiniciar la animación
    bar.classList.add("page-transition-loading");
  }

  function finalizarTransicion() {
    var bar = document.getElementById("pageTransitionBar");
    if (bar) {
      bar.classList.remove("page-transition-loading");
      bar.classList.add("page-transition-finish");
      setTimeout(function () {
        if (bar && bar.classList.contains("page-transition-finish")) {
          bar.classList.remove("page-transition-finish");
        }
      }, 350);
    }
  }

  // Finalizar barra cuando el DOM y la página estén listos o se recupere de bfcache
  if (document.readyState === "complete") {
    finalizarTransicion();
  } else {
    document.addEventListener("DOMContentLoaded", finalizarTransicion);
    window.addEventListener("load", finalizarTransicion);
  }
  window.addEventListener("pageshow", finalizarTransicion);

  // Interceptar clics en enlaces internos de navegación
  document.addEventListener("click", function (e) {
    var link = e.target && e.target.closest ? e.target.closest("a") : null;
    if (!link) return;

    var href = link.getAttribute("href");
    if (!href) return;

    // Omitir anclas, scripts, correo, teléfono, pestañas nuevas y descargas
    if (
      href.trim() === "" ||
      href.startsWith("#") ||
      href.startsWith("javascript:") ||
      href.startsWith("mailto:") ||
      href.startsWith("tel:") ||
      link.target === "_blank" ||
      link.hasAttribute("download") ||
      e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.which === 2 || e.button === 1
    ) {
      return;
    }

    // Omitir widgets de AdminLTE / Bootstrap (modales, dropdowns, toggle de sidebar)
    if (
      link.hasAttribute("data-toggle") ||
      link.hasAttribute("data-widget") ||
      (link.getAttribute("role") === "button" && href === "#")
    ) {
      return;
    }

    // Verificar mismo origen
    if (link.hostname && link.hostname !== window.location.hostname) {
      return;
    }

    iniciarTransicion();
  });

  // Interceptar envíos de formularios legítimos (no prevenidos)
  document.addEventListener("submit", function (e) {
    if (e.defaultPrevented) return;
    var form = e.target;
    if (form && form.target === "_blank") return;
    iniciarTransicion();
  });

  // Exponer globalmente si se necesita activar manualmente
  window.iniciarTransicionPagina = iniciarTransicion;
  window.finalizarTransicionPagina = finalizarTransicion;

  // ---------- Revalidación de sesión ante restauración de historial / bfcache ----------
  window.addEventListener("pageshow", function (event) {
    var esRestaurado = !!event.persisted;
    if (!esRestaurado && window.performance && window.performance.getEntriesByType) {
      var navEntries = window.performance.getEntriesByType("navigation");
      if (navEntries.length > 0 && navEntries[0].type === "back_forward") {
        esRestaurado = true;
      }
    }

    if (esRestaurado) {
      var wrapper = document.querySelector(".wrapper");
      if (wrapper) {
        wrapper.style.visibility = "hidden";
      }

      $.ajax({
        url: baseUrl + "controllers/ajax/verificar_sesion.php",
        type: "GET",
        cache: false,
        headers: { "Cache-Control": "no-cache" },
        dataType: "json",
        success: function (data) {
          if (data && data.autenticado) {
            if (wrapper) {
              wrapper.style.visibility = "visible";
            }
          } else {
            window.location.replace(baseUrl + "views/auth/login.php");
          }
        },
        error: function () {
          window.location.replace(baseUrl + "views/auth/login.php");
        }
      });
    }
  });

})(jQuery);
