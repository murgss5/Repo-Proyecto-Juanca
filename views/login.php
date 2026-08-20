<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Control de Asistencia</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">

    <div class="container">

        <div class="row justify-content-center align-items-center min-vh-100">

            <div class="col-12 col-sm-10 col-md-6 col-lg-4">

                <div class="card shadow">

                    <div class="card-body p-4">

                        <div class="text-center mb-4">

                            <h2 class="fw-bold">
                                Control de Asistencia
                            </h2>

                            <p class="text-muted">
                                Inicia sesión para continuar
                            </p>

                        </div>

                        <?php if (isset($_GET["error"])): ?>

                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($_GET["error"]); ?>
                            </div>

                        <?php endif; ?>


                        <form action="../controllers/login.php" method="POST">

                            <div class="mb-3">

                                <label
                                    for="nombre_usuario"
                                    class="form-label"
                                >
                                    Usuario
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="nombre_usuario"
                                    name="nombre_usuario"
                                    required
                                >

                            </div>


                            <div class="mb-3">

                                <label
                                    for="contrasena"
                                    class="form-label"
                                >
                                    Contraseña
                                </label>

                                <input
                                    type="password"
                                    class="form-control"
                                    id="contrasena"
                                    name="contrasena"
                                    required
                                >

                            </div>


                            <button
                                type="submit"
                                class="btn btn-success w-100"
                            >
                                Iniciar sesión
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>