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

    <style>
        /* Efecto hover para los botones del navbar */
        .navbar-nav .nav-link {
            transition: background 0.3s ease-in-out;
        }

        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.53); /* Fondo sutil al pasar el mouse */
        }

        /* Resaltar el botón activo */
        .navbar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            font-weight: bold;
        }

        /* Espaciado entre elementos */
        .navbar-nav {
            gap: 10px;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-md navbar-dark bg-dark"> <!-- Cambié expand-lg a expand-md -->
        <div class="container-fluid">

            <!-- Flecha de volver y botón de inicio (casita) -->
            <div class="d-flex align-items-center gap-3">
                <button class="nav-link btn btn-link text-white" onclick="window.history.back();">
                    <i class="bi bi-arrow-left"></i>
                </button>

                <a class="navbar-brand" href="index.php">
                    <i class="bi bi-house"></i>
                </a>
            </div>

            <!-- Toggler que aparece SOLO en pantallas pequeñas -->
            <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
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

            // Resaltar el botón activo
            const currentUrl = window.location.href;
            document.querySelectorAll(".navbar-nav button, .navbar-nav a").forEach(link => {
                if (link.onclick && link.onclick.toString().includes(currentUrl)) {
                    link.classList.add("active");
                }
            });
        });
    </script>
