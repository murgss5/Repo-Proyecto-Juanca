<?php
/**
 * Componente visual reutilizable: Calendario Semanal de Horarios - SENA
 * 
 * Variables esperadas:
 * @var array $estructuraSemana Resultado de HorarioModel::calcularEstructuraSemana()
 * @var array $cuadricula       Resultado de HorarioModel::procesarEventosParaCuadricula()
 * @var int   $rolActualId      Rol del usuario (1=Admin, 2=Instructor, 3=Aprendiz)
 * @var array $fichasFiltro     (Opcional) Fichas para el dropdown de filtro
 * @var int|null $idFichaSeleccionada (Opcional) Ficha filtrada
 * @var array|null $fichaAprendiz (Opcional) Datos de la ficha del aprendiz
 * @var string $urlBaseModulo   URL base de la vista actual (ej. RUTA_BASE . 'views/instructor/horarios/index.php')
 */

$totalClasesSemana = 0;
$totalMinutosSemana = 0;
foreach ($cuadricula['dias_con_eventos'] as $colDia) {
    $totalClasesSemana += count($colDia['eventos']);
    foreach ($colDia['eventos'] as $ev) {
        $totalMinutosSemana += $ev['duracion_min'];
    }
}
$totalHorasSemana = round($totalMinutosSemana / 60, 1);
$totalColumnasDias = count($cuadricula['dias_con_eventos']);
$totalAlturaPx = ($cuadricula['hora_maxima'] - $cuadricula['hora_minima']) * $cuadricula['row_height_px'];

// Construcción de enlaces de navegación conservando filtros
$paramsFiltro = [];
if (!empty($idFichaSeleccionada)) {
    $paramsFiltro['ficha'] = $idFichaSeleccionada;
}

$linkSemanaAnterior = $urlBaseModulo . '?fecha=' . urlencode($estructuraSemana['fecha_semana_anterior']) . (!empty($paramsFiltro) ? '&' . http_build_query($paramsFiltro) : '');
$linkSemanaActual = $urlBaseModulo . '?fecha=' . urlencode($estructuraSemana['fecha_semana_actual']) . (!empty($paramsFiltro) ? '&' . http_build_query($paramsFiltro) : '');
$linkSemanaSiguiente = $urlBaseModulo . '?fecha=' . urlencode($estructuraSemana['fecha_semana_siguiente']) . (!empty($paramsFiltro) ? '&' . http_build_query($paramsFiltro) : '');
?>

<div class="calendario-componente">
    <!-- Barra superior de navegación y controles -->
    <div class="calendario-toolbar">
        <div class="calendario-nav-grupo">
            <a href="<?= $linkSemanaAnterior ?>" class="calendario-btn-nav" title="Semana anterior">
                <i class="fas fa-chevron-left"></i> Anterior
            </a>
            <a href="<?= $linkSemanaActual ?>" class="calendario-btn-nav <?= $estructuraSemana['es_semana_actual'] ? 'active-nav' : '' ?>" title="Ir a la semana actual">
                <i class="fas fa-calendar-day"></i> Hoy
            </a>
            <a href="<?= $linkSemanaSiguiente ?>" class="calendario-btn-nav" title="Semana siguiente">
                Siguiente <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <div class="calendario-titulo-rango">
            <i class="fas fa-calendar-alt"></i>
            <span><?= htmlspecialchars($estructuraSemana['rango_texto']) ?></span>
        </div>

        <div class="calendario-filtros-grupo">
            <?php if ($rolActualId === 2 && !empty($fichasFiltro)): ?>
                <!-- Filtro por ficha para Instructor -->
                <form method="get" action="<?= htmlspecialchars($urlBaseModulo) ?>" class="d-inline-flex align-items-center m-0">
                    <input type="hidden" name="fecha" value="<?= htmlspecialchars($estructuraSemana['fecha_referencia']) ?>">
                    <select name="ficha" class="calendario-select-ficha" onchange="this.form.submit()">
                        <option value="0">Todas mis fichas asignadas</option>
                        <?php foreach ($fichasFiltro as $f): ?>
                            <option value="<?= (int)$f['id_ficha'] ?>" <?= ($idFichaSeleccionada === (int)$f['id_ficha']) ? 'selected' : '' ?>>
                                Ficha #<?= htmlspecialchars($f['id_ficha']) ?> — <?= htmlspecialchars($f['nombre_programa']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php elseif ($rolActualId === 3 && !empty($fichaAprendiz)): ?>
                <!-- Badge fijo y seguro para Aprendiz (sin manipular ficha ajena) -->
                <div class="calendario-badge-info" title="Ficha a la que perteneces">
                    <i class="fas fa-id-badge"></i>
                    <span>Ficha #<?= htmlspecialchars($fichaAprendiz['id_ficha']) ?> &middot; <?= htmlspecialchars($fichaAprendiz['nombre_programa']) ?></span>
                </div>
            <?php endif; ?>

            <div class="calendario-badge-info">
                <i class="fas fa-clock"></i>
                <span><?= $totalClasesSemana ?> clase<?= $totalClasesSemana === 1 ? '' : 's' ?> &middot; <?= $totalHorasSemana ?>h</span>
            </div>
        </div>
    </div>

    <?php if ($totalClasesSemana === 0): ?>
        <div class="calendario-aviso-semana">
            <i class="fas fa-info-circle"></i>
            <span>No hay clases programadas para esta semana. Puedes usar los botones <strong>Anterior</strong> y <strong>Siguiente</strong> para consultar otras semanas.</span>
        </div>
    <?php endif; ?>

    <!-- Cuadrícula semanal visual -->
    <div class="calendario-scroll-wrapper">
        <div class="calendario-grid-contenedor" style="--col-count: <?= $totalColumnasDias ?>; --cal-row-height: <?= (int)$cuadricula['row_height_px'] ?>px;">
            
            <!-- Fila de encabezado con días y números -->
            <div class="calendario-fila-encabezado">
                <div class="calendario-header-hora">HORARIO</div>
                <?php foreach ($estructuraSemana['dias'] as $dia): ?>
                    <div class="calendario-header-dia <?= $dia['es_hoy'] ? 'es-hoy' : '' ?>">
                        <span class="dia-abrev"><?= htmlspecialchars($dia['abrev']) ?></span>
                        <span class="dia-numero"><?= htmlspecialchars($dia['numero']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Cuerpo de la cuadrícula: horas a la izquierda y columnas por cada día -->
            <div class="calendario-cuerpo-grid">
                <!-- Columna vertical de horas -->
                <div class="calendario-columna-horas">
                    <?php foreach ($cuadricula['horas_grid'] as $hGrid): ?>
                        <div class="calendario-celda-hora-label">
                            <?= htmlspecialchars($hGrid['hora_12']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Columnas de cada día con los bloques posicionados -->
                <?php foreach ($cuadricula['dias_con_eventos'] as $colDia): ?>
                    <?php $diaInfo = $colDia['info']; ?>
                    <div class="calendario-columna-dia <?= $diaInfo['es_hoy'] ? 'es-hoy' : '' ?>" style="height: <?= $totalAlturaPx ?>px;">
                        <?php foreach ($colDia['eventos'] as $ev): ?>
                            <?php 
                                $esCorto = ($ev['duracion_min'] <= 60);
                                $jsonEvento = htmlspecialchars(json_encode($ev, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="calendario-bloque-clase <?= $esCorto ? 'bloque-corto' : '' ?>"
                                 style="top: <?= (float)$ev['top_px'] ?>px; height: <?= max(28, (float)$ev['height_px'] - 3) ?>px; width: calc(<?= (float)$ev['width_percent'] ?>% - 6px); left: calc(<?= (float)$ev['left_percent'] ?>% + 3px);"
                                 data-evento="<?= $jsonEvento ?>"
                                 onclick="mostrarDetalleClase(this)"
                                 title="Clic para ver detalles: <?= htmlspecialchars($ev['titulo_bloque']) ?>">
                                
                                <div class="bloque-header">
                                    <i class="far fa-clock"></i>
                                    <span><?= htmlspecialchars($ev['label_bloque']) ?></span>
                                </div>

                                <div class="bloque-titulo">
                                    <?= htmlspecialchars($ev['titulo_bloque']) ?>
                                </div>

                                <div class="bloque-meta">
                                    <?php if ($rolActualId === 3): ?>
                                        <!-- Aprendiz: Importancia máxima al instructor -->
                                        <i class="fas fa-chalkboard-teacher"></i>
                                        <span><?= htmlspecialchars($ev['instructor']) ?></span>
                                    <?php elseif ($rolActualId === 2): ?>
                                        <!-- Instructor: Ficha y Ambiente -->
                                        <i class="fas fa-layer-group"></i>
                                        <span>Ficha #<?= htmlspecialchars($ev['id_ficha']) ?> &bull; <?= htmlspecialchars($ev['ambiente']) ?></span>
                                    <?php else: ?>
                                        <!-- Admin: Instructor y Ficha -->
                                        <i class="fas fa-user-tie"></i>
                                        <span><?= htmlspecialchars($ev['instructor']) ?> (#<?= htmlspecialchars($ev['id_ficha']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</div>

<!-- Modal para consultar el detalle completo de la clase -->
<div class="modal fade modal-horario-detalle" id="modalDetalleClase" tabindex="-1" role="dialog" aria-labelledby="modalDetalleClaseLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalleClaseLabel">
                    <i class="fas fa-calendar-check text-white mr-1"></i> Detalle de la Clase
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="detalle-item">
                    <div class="detalle-icono"><i class="fas fa-graduation-cap"></i></div>
                    <div>
                        <div class="detalle-label">Programa de Formación / Competencia</div>
                        <div class="detalle-valor font-weight-bold" id="modalDetallePrograma">—</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="detalle-item">
                            <div class="detalle-icono"><i class="fas fa-layer-group"></i></div>
                            <div>
                                <div class="detalle-label">Ficha</div>
                                <div class="detalle-valor" id="modalDetalleFicha">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="detalle-item">
                            <div class="detalle-icono"><i class="fas fa-sun"></i></div>
                            <div>
                                <div class="detalle-label">Jornada</div>
                                <div class="detalle-valor" id="modalDetalleJornada">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detalle-item">
                    <div class="detalle-icono"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div>
                        <div class="detalle-label">Instructor</div>
                        <div class="detalle-valor text-sena font-weight-bold" id="modalDetalleInstructor">—</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="detalle-item">
                            <div class="detalle-icono"><i class="far fa-calendar-alt"></i></div>
                            <div>
                                <div class="detalle-label">Día y Fecha</div>
                                <div class="detalle-valor" id="modalDetalleDiaFecha">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="detalle-item">
                            <div class="detalle-icono"><i class="far fa-clock"></i></div>
                            <div>
                                <div class="detalle-label">Horario</div>
                                <div class="detalle-valor" id="modalDetalleHorario">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="detalle-item">
                            <div class="detalle-icono"><i class="fas fa-door-open"></i></div>
                            <div>
                                <div class="detalle-label">Ambiente / Aula</div>
                                <div class="detalle-valor" id="modalDetalleAmbiente">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="detalle-item">
                            <div class="detalle-icono"><i class="fas fa-cubes"></i></div>
                            <div>
                                <div class="detalle-label">Tipo de Bloque</div>
                                <div class="detalle-valor" id="modalDetalleTipoBloque">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detalle-item" id="modalContenedorObservaciones" style="display: none;">
                    <div class="detalle-icono"><i class="fas fa-info-circle"></i></div>
                    <div>
                        <div class="detalle-label">Observaciones</div>
                        <div class="detalle-valor" id="modalDetalleObservaciones">—</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <?php if ($rolActualId === 1): ?>
                    <a href="#" id="modalBtnEditarHorario" class="btn btn-warning btn-sm mr-auto">
                        <i class="fas fa-edit"></i> Editar horario
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function mostrarDetalleClase(elemento) {
    try {
        const raw = elemento.getAttribute('data-evento');
        if (!raw) return;
        const ev = JSON.parse(raw);

        document.getElementById('modalDetallePrograma').textContent = ev.titulo_bloque || ev.nombre_programa || 'Formación SENA';
        document.getElementById('modalDetalleFicha').textContent = '#' + ev.id_ficha + (ev.nombre_programa ? ' (' + ev.nombre_programa + ')' : '');
        document.getElementById('modalDetalleJornada').textContent = ev.jornada || 'No especificada';
        document.getElementById('modalDetalleInstructor').textContent = ev.instructor || 'Instructor no asignado';
        document.getElementById('modalDetalleDiaFecha').textContent = ev.dia_semana + ' (' + ev.fecha_dia + ')';
        document.getElementById('modalDetalleHorario').textContent = ev.hora_inicio_ampm + ' – ' + ev.hora_fin_ampm + ' (' + ev.duracion_horas + ' hrs)';
        document.getElementById('modalDetalleAmbiente').textContent = ev.ambiente || 'Por definir';
        document.getElementById('modalDetalleTipoBloque').textContent = (ev.tipo_bloque === 'partido') ? 'Bloque partido' : 'Bloque continuo';

        const contObs = document.getElementById('modalContenedorObservaciones');
        const txtObs = document.getElementById('modalDetalleObservaciones');
        if (ev.observaciones && ev.observaciones.trim() !== '') {
            txtObs.textContent = ev.observaciones;
            contObs.style.display = 'flex';
        } else {
            contObs.style.display = 'none';
        }

        const btnEditar = document.getElementById('modalBtnEditarHorario');
        if (btnEditar) {
            btnEditar.href = '<?= RUTA_BASE ?>views/admin/horarios/edit.php?id=' + ev.id_horario + '&vista=calendario';
        }

        $('#modalDetalleClase').modal('show');
    } catch (e) {
        console.error('Error al abrir detalle de clase:', e);
    }
}
</script>
