<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="src/bootstrapcss.css" rel="stylesheet">
    <link href="src/datatablescss.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <script src="src/jquery.js"></script>
    <script src="src/datatablesjs.js"></script>
    <script src="src/chartjs.js"></script>
    <script src="src/chartplugin.js"></script>

    <title>CRM</title>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">

            <!-- Flecha de volver y botón de inicio (casita) -->
            <div class="d-flex align-items-center">
                <button class="nav-link btn btn-link text-white mx-2" onclick="window.history.back();">
                    <i class="bi bi-arrow-left"></i>
                </button>

                <a class="navbar-brand mx-2" href="index.php">
                    <i class="bi bi-house"></i>
                </a>
            </div>


            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav">

                    <?php if (isset($isAdmin) && $isAdmin): ?>
                        <button class="nav-link btn btn-link text-white" onclick="window.location.href='user_crud.php';">
                            Gestionar Usuarios
                        </button>
                    <?php endif; ?>

                    <div class="nav-item dropdown">
                        <button class="nav-link btn btn-link text-white dropdown-toggle" id="dropdownGestionarProductos"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Productos
                        </button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item"
                                    onclick="window.location.href='product_crud.php';">Gestionar (admin)</button></li>
                            <li><button class="dropdown-item"
                                    onclick="window.location.href='syllabus_crud.php';">Temarios</button></li>
                        </ul>
                    </div>

                    <button class="nav-link btn btn-link text-white" onclick="window.location.href='report_sales.php';">
                        Reportes Ventas
                    </button>

                    <button class="nav-link btn btn-link text-white" onclick="window.location.href='sales_crud.php';">
                        Registrar Ventas
                    </button>

                    <button class="nav-link btn btn-link text-white" onclick="window.location.href='members_crud.php';">
                        Gestionar Socios
                    </button>

                    <button class="nav-link btn btn-link text-white" onclick="window.location.href='report_crud.php';">
                        Reportes
                    </button>

                    <button class="nav-link btn btn-link text-white" onclick="window.location.href='tracin_crud.php';">
                        Seguimientos
                    </button>
                </div>

                <!-- Íconos de ayuda y cerrar sesión alineados a la derecha -->
                <div class="ms-auto d-flex gap-2">
                    <button class="nav-link btn btn-link text-white" id="liveAlertBtn">
                        <i class="bi bi-question-circle"></i>
                    </button>
                    <button class="nav-link btn btn-link text-white" onclick="window.location.href='logout.php';">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Espacio para mostrar alertas -->
    <div id="liveAlertPlaceholder" class="container mt-2"></div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const alertPlaceholder = document.getElementById('liveAlertPlaceholder');

            const appendAlert = (message, type) => {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <div>${message}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
                alertPlaceholder.append(wrapper);
            };

            document.getElementById('liveAlertBtn').addEventListener('click', () => {
                const message = `<?php echo isset($helpMessage) ? $helpMessage : 'No hay instrucciones disponibles para esta página.'; ?>`;
            });
        });
    </script>