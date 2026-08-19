<?php
session_start();

//verificar que el usuario haya iniciado sesion

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../login.php");
    exit;
}

//verificar que el usuario sea instructor

if ($_SESSION["fk_rol"] != 2) {
    header("Location: ../login.php");
    exit;
}

//datos del usuario

$nombre = $_SESSION["nombre"];
$apellido = $_SESSION["apellido"];
$nombre_rol = $_SESSION["nombre_rol"];

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Panel del Instructor | Control de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/estilos.css">

</head>

<body>

    <aside class="sidebar" id="sidebar">

        <div class="sidebar-header">

            <div class="logo">

                <i class="bi bi-person-check-fill"></i>

            </div>

            <div>

                <h5>Control</h5>

                <span>Asistencia</span>

            </div>

        </div>


        <hr>

        <nav class="sidebar-menu">

            <a href="#" class="menu-item active">
                <i class="bi bi-speedometer2"></i>
                <span>Inicio</span>
            </a>

            <a href="#" class="menu-item">
                <i class="bi bi-people-fill"></i>
                <span>Aprendices</span>
            </a>

            <a href="#" class="menu-item">
                <i class="bi bi-calendar-check"></i>
                <span>Asistencia</span>
            </a>

            <a href="#" class="menu-item">
                <i class="bi bi-file-earmark-text"></i>
                <span>Excusas</span>
            </a>

            <a href="#" class="menu-item">
                <i class="bi bi-bar-chart-fill"></i>
                <span>Reportes</span>
            </a>

            <a href="#" class="menu-item">
                <i class="bi bi-clock-fill"></i>
                <span>Horarios</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../../controllers/logout.php" class="menu-item logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar sesión</span>
            </a>
        </div>
    </aside>

    <main class="main-content">

        <nav class="top-navbar">
            <button class="btn btn-menu d-lg-none" id="menuButton">
                <i class="bi bi-list"></i>
            </button>

            <div class="navbar-title">

                <h5>Panel del Instructor</h5>

            </div>

            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                </div>

                <div class="user-details d-none d-sm-block">
                    <strong>
                        <?php echo htmlspecialchars($nombre . " " . $apellido); ?>
                    </strong>

                    <small>
                        <?php echo htmlspecialchars($nombre_rol); ?>
                    </small>
                </div>
            </div>
        </nav>

        <div class="container-fluid dashboard-container">
            <div class="welcome-section">
                <div>
                    <h2>
                        Bienvenido, <?php echo htmlspecialchars($nombre); ?>
                    </h2>
                    <p>
                        Aquí puedes consultar y gestionar la asistencia de tus aprendices.
                    </p>
                </div>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="dashboard-card">
                        <div class="card-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>

                        <div class="card-information">
                            <span>Aprendices</span>
                            <h3>0</h3>
                            <small>
                                Aprendices registrados
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="dashboard-card">
                        <div class="card-icon">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                        <div class="card-information">
                            <span>Presentes</span>
                            <h3>0</h3>
                            <small>
                                Asistencias de hoy
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="dashboard-card">
                        <div class="card-icon">
                            <i class="bi bi-clock-history"></i>
                        </div>

                        <div class="card-information">
                            <span>Retardos</span>
                            <h3>0</h3>
                            <small>
                                Retardos de hoy
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="dashboard-card">
                        <div class="card-icon">
                            <i class="bi bi-person-x-fill"></i>
                        </div>

                        <div class="card-information">
                            <span>Inasistencias</span>
                            <h3>0</h3>
                            <small>
                                Inasistencias de hoy
                            </small>

                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-lg-4">
                    <div class="dashboard-panel">
                        <div class="panel-header">
                            <div>
                                <h5>Excusas pendientes</h5>
                                <small>
                                    Requieren revisión
                                </small>
                            </div>
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="empty-state">
                            <i class="bi bi-check-circle"></i>
                            <p>
                                No hay excusas pendientes.
                            </p>
                        </div>
                        <button class="btn btn-primary w-100">
                            Ver excusas
                        </button>
                    </div>
                </div>
                <div class="col-12 col-lg-8">
                    <div class="dashboard-panel">
                        <div class="panel-header">
                            <div>
                                <h5>Acciones rápidas</h5>
                                <small>
                                    Accede rápidamente a las funciones principales
                                </small>
                            </div>
                            <i class="bi bi-lightning-fill"></i>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-12 col-md-6">
                                <button class="quick-action">
                                    <i class="bi bi-people-fill"></i>
                                    <div>
                                        <strong>
                                            Ver aprendices
                                        </strong>
                                        <span>
                                            Consultar aprendices de la ficha
                                        </span>
                                    </div>
                                </button>
                            </div>
                            <div class="col-12 col-md-6">
                                <button class="quick-action">
                                    <i class="bi bi-calendar-check"></i>
                                    <div>
                                        <strong>
                                            Consultar asistencia
                                        </strong>
                                        <span>
                                            Revisar registros de asistencia
                                        </span>
                                    </div>
                                </button>
                            </div>
                            <div class="col-12 col-md-6">
                                <button class="quick-action">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    <div>
                                        <strong>
                                            Generar reporte
                                        </strong>
                                        <span>
                                            Crear reporte de asistencia
                                        </span>
                                    </div>
                                </button>
                            </div>
                            <div class="col-12 col-md-6">
                                <button class="quick-action">
                                    <i class="bi bi-clock"></i>
                                    <div>
                                        <strong>
                                            Ver horario
                                        </strong>
                                        <span>
                                            Consultar horario de la ficha
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-panel">
                <div class="panel-header">
                    <div>

                        <h5>Asistencia de hoy</h5>

                        <small>
                            Últimos registros de asistencia
                        </small>
                    </div>

                    <a href="#" class="btn btn-outline-primary btn-sm">
                        Ver todas

                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table attendance-table">
                        <thead>
                            <tr>
                                <th>Aprendiz</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Estado</th>
                                <th>Retardo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-table">
                                        <i class="bi bi-calendar-x"></i>
                                        <span>
                                            No hay registros de asistencia para mostrar.
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>

</body>

</html>