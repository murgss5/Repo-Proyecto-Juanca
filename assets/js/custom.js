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

})(jQuery);
