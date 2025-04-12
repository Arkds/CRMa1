<?php include 'feedback.php'; ?>

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
            background: rgba(255, 255, 255, 0.53);
            /* Fondo sutil al pasar el mouse */
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

        /* Ajustar el ancho de la barra de navegación */
        .navbar {
            padding: 1rem 2rem;
            /* Más espacio vertical y horizontal */
        }

        /* Centrar los elementos de navegación a partir de la casita */
        .navbar-nav {
            flex-grow: 1;
            /* Permite expandirse */
            display: flex;
            justify-content: center;
            /* Centra los elementos */
            gap: 15px;
            /* Espacio entre botones */
        }

        /* Aumentar tamaño de los botones de salir y ayuda */
        .nav-icons {
            font-size: 1.5rem;
            /* Hace los íconos más grandes */
            padding: 10px 15px;
            /* Aumenta área clickeable */
        }

        /* Aumentar tamaño del botón al pasar el mouse */
        .nav-icons:hover {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 5px;
        }

        /* Hover para los botones normales */
        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 5px;
        }

        /* Botón activo resaltado */
        .navbar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            font-weight: bold;
        }

        /* Casita y flecha más grandes */
        .nav-icons-big {
            font-size: 2rem;
            /* Hace los iconos más grandes */
            padding: 10px 15px;
            /* Aumenta el área clickeable */
        }

        /* Efecto hover para la casita y la flecha */
        .nav-icons-big:hover {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 5px;
        }
    </style>
    <!-- Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

    <nav class="navbar navbar-expand-md navbar-dark bg-dark">
        <div class="container-fluid">

            <!-- Flecha de volver y botón de inicio (casita) -->
            <div class="d-flex align-items-center gap-3">
                <button class="nav-link btn btn-link text-white nav-icons-big" onclick="window.history.back();">
                    <i class="bi bi-arrow-left"></i>
                </button>

                <a class="navbar-brand nav-icons-big" href="index.php">
                    <i class="bi bi-house"></i>
                </a>

                <!-- Botón de recargar página -->
                <button class="nav-link btn btn-link text-white nav-icons-big" onclick="window.location.reload();">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>

            <!-- Toggler en pantallas pequeñas -->
            <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menú de navegación centrado -->
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
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="product_crud.php">Gestionar (admin)</a></li>
                            <li><a class="dropdown-item" href="syllabus_crud.php">Temarios</a></li>
                        </ul>
                    </div>

                    <?php if (isset($isAdmin) && $isAdmin): ?>
                        <button class="nav-link btn btn-link text-white" onclick="window.location.href='report_sales.php';">
                            Reportes Ventas
                        </button>
                    <?php endif; ?>
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
                    <button class="nav-link btn btn-link text-white"
                        onclick="window.location.href='certificaciones.php';">
                        Certificaciones
                    </button>
                </div>
                <div class="ms-auto d-flex gap-3 align-items-center">
                    <span class="text-white"><em>Usted esta como: </em><strong> <?= htmlspecialchars($username) ?>
                        </strong></span>

                    <button class="nav-link btn btn-link text-white nav-icons" id="liveAlertBtn">
                        <i class="bi bi-question-circle"></i>
                    </button>

                    <button class="nav-link btn btn-link text-white nav-icons"
                        onclick="window.location.href='logout.php';">
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
                appendAlert(message, 'info');
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
    <script>
        document.addEventListener('keydown', function (event) {
            if (event.ctrlKey && event.key === 'l') {
                event.preventDefault();  // Esto bloquea el atajo Ctrl+L
            }
            // Bloquear la tecla Enter (código 13)
            if (event.key === 'Enter') {
                event.preventDefault();
                // Opcional: puedes mostrar una alerta o feedback al usuario
                // alert('La tecla Enter está deshabilitada en esta página');
            }
        });
    </script>
    <!-- Bootstrap JS (requerido para el modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>