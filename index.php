<?php
require 'db.php';

// Verificar si la cookie de sesión existe
if (isset($_COOKIE['user_session'])) {
    // Decodificar la cookie
    $user_data = json_decode(base64_decode($_COOKIE['user_session']), true);

    if ($user_data) {
        // Variables disponibles para usar en la página
        $user_id = $user_data['user_id'];
        $username = $user_data['username'];
        $role = $user_data['role'];
    } else {
        // Si hay un problema con la cookie, redirigir al login
        header("Location: login.php");
        exit;
    }
} else {
    // Si no hay cookie, redirigir al login
    header("Location: login.php");
    exit;
}
////////////productos relevantes///////
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar si es una solicitud AJAX para obtener los productos relevantes

$products = $pdo->query("SELECT relevance, name, price, description FROM products ORDER BY relevance DESC, name ASC")->fetchAll();

///////////////////

// Obtener el nombre del usuario desde la cookie
$isAdmin = ($role === 'admin'); // Ahora usamos $role desde la cookie

// Consultar ventas
$stmt = $pdo->query("SELECT * FROM sales ORDER BY created_at DESC");
$sales = $stmt->fetchAll();

?>

<?php
// Obtener todas las ventas diarias para paginación
$salesByDay = $pdo->query("
    SELECT DATE(created_at) AS fecha, SUM(quantity) AS total_cantidad 
    FROM sales 
    GROUP BY fecha 
    ORDER BY fecha ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Convertir datos a JSON para JavaScript
$salesDataJson = json_encode($salesByDay);
// Obtener la cantidad de clientes por estado
$clientsByStatus = $pdo->query("
    SELECT status, COUNT(*) AS total 
    FROM report_clients 
    GROUP BY status 
    ORDER BY FIELD(status, 'Nuevo', 'Interesado', 'Negociación', 'Comprometido', 'Vendido', 'Perdido')
")->fetchAll(PDO::FETCH_ASSOC);

// Convertir los datos a JSON para pasarlos a JavaScript
$clientsByStatusJson = json_encode($clientsByStatus);

// Consulta SQL para obtener la cantidad de clientes ingresados por usuario
$clientsByUser = $pdo->query("
    SELECT u.username, COUNT(rc.id) AS total_clientes
    FROM report_clients rc
    JOIN reports r ON rc.report_id = r.id
    JOIN users u ON r.user_id = u.id
    GROUP BY u.username
    ORDER BY total_clientes DESC;
")->fetchAll(PDO::FETCH_ASSOC);

// Convertir los datos a JSON para usarlos en JavaScript
$clientsByUserJson = json_encode($clientsByUser);




$query = "
WITH words_extracted AS (
    SELECT 
        LOWER(content) AS curso
    FROM report_entries
    WHERE category = 'cursos_mas_vendidos'
)

SELECT word, COUNT(*) AS total_menciones
FROM (
    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(curso, ' ', n), ' ', -1) AS word
    FROM words_extracted
    JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) numbers
) extracted_words
WHERE LENGTH(word) > 3
AND word NOT IN ('wsp', 'tx', 'hz', 'prm', 'curso', 'cursos', 'para', 'en', 'el', 'de', 'la', 'los', 'las', 'y', 'premium', 'hazla')
GROUP BY word
ORDER BY total_menciones DESC
LIMIT 10;
";

$stmt = $pdo->query($query);
$wordsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convertir los datos a JSON para usarlos en el gráfico
$wordsDataJson = json_encode($wordsData);



/// Consulta SQL para obtener los productos más vendidos, normalizados a minúsculas
$query = "
SELECT LOWER(product_name) AS normalized_product_name, COUNT(*) AS total_vendidos
FROM sales
GROUP BY normalized_product_name
ORDER BY total_vendidos DESC
LIMIT 10;
";

$stmt = $pdo->query($query);
$productsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convertir los datos a JSON para usarlos en el gráfico
$productsDataJson = json_encode($productsData);
?>













<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>





    <title>Dashboard</title>
</head>

<body>
    <div class="container mt-5">
        <div id="liveAlertPlaceholder"></div>
        <button type="button" class="btn btn-outline-dark float-end" id="liveAlertBtn">Ayuda</button>

        <script>
            const alertPlaceholder = document.getElementById('liveAlertPlaceholder')
            const appendAlert = (message, type) => {
                const wrapper = document.createElement('div')
                wrapper.innerHTML = [
                    `<div class="alert alert-${type} alert-dismissible" role="alert">`,
                    `   <div>${message}</div>`,
                    '   <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>',
                    '</div>'
                ].join('')

                alertPlaceholder.append(wrapper)
            }

            // Evento para mostrar la alerta al hacer clic en el botón
            const alertTrigger = document.getElementById('liveAlertBtn')
            if (alertTrigger) {
                alertTrigger.addEventListener('click', () => {
                    // Lista numerada de instrucciones
                    const message = `
                <ol>
                    <li>IMPORTANTE: Toma precauciones de tus credenciales, son privados y solo un administrador puede cambiarlos.</li>
                    <li>Los datos de tu cuenta solo deben ser manejados por ti, no compartas tu contraseña.</li>
                    <li>Si algo no funciona, encuentras un error o abuso reportalo de inmediato</li>
                    <li>Reportes de ventas: Ver todas las ventas de todos por filtros.</li>
                    <li>Registrar ventas: Registro de ventas a México</li>
                    <li>Gestionar socios: Gestion de socios a1.</li>
                    <li>Reportes: Gestion de reportes diarios y semanales</li>
                    <li>Seguimientos: Gestión de clientes potenciales y seguimientos</li>
                    <li>Utiliza la barra de búsqueda para encontrar regístros específicos rápidamente.</li> 
                </ol>
            `;
                    appendAlert(message, 'success');
                })
            }
        </script>
        <div class="container">
            <button class="btn btn-outline-danger" onclick="window.location.href='logout.php';">Cerrar Sesión</button>

            <h1 class="text-center">Bienvenido(a), <?= htmlspecialchars($username) ?> 👋</h1>
            <hr>
        </div>

        <!-- Botones de navegación -->
        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-between mb-3">
            <?php if ($isAdmin): ?>
                <a href="user_crud.php" class="btn btn-primary d-flex align-items-center">
                    <i class="bi bi-people-fill me-2"></i> Gestionar Usuarios
                </a>
            <?php endif; ?>
        
            <button class="btn btn-secondary d-flex align-items-center" onclick="window.location.href='product_crud.php';">
                <i class="bi bi-box-seam me-2"></i> Gestionar Productos
            </button>
        
            <button class="btn btn-info d-flex align-items-center" onclick="window.location.href='report_sales.php';">
                <i class="bi bi-bar-chart-line me-2"></i> Reportes Ventas
            </button>
        
            <button class="btn btn-success d-flex align-items-center" onclick="window.location.href='sales_crud.php';">
                <i class="bi bi-cash-stack me-2"></i> Registrar Ventas
            </button>
        
            <button class="btn btn-warning d-flex align-items-center" onclick="window.location.href='members_crud.php';">
                <i class="bi bi-person-badge me-2"></i> Gestionar Socios
            </button>
        
            <button class="btn btn-danger d-flex align-items-center" onclick="window.location.href='report_crud.php';">
                <i class="bi bi-clipboard-data me-2"></i> Reportes
            </button>
        
            <button class="btn btn-dark d-flex align-items-center" onclick="window.location.href='tracin_crud.php';">
                <i class="bi bi-journal-check me-2"></i> Seguimientos
            </button>
        </div>


        <!-- Tabla de ventas 
        <h2>Ventas Registradas</h2>
        <table id="salesTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Cantidad</th>
                    <th>Total</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><?= $sale['id'] ?></td>
                        <td><?= htmlspecialchars($sale['product_name']) ?></td>
                        <td><?= htmlspecialchars($sale['price']) ?></td>
                        <td><?= htmlspecialchars($sale['quantity']) ?></td>
                        <td><?= number_format($sale['price'] * $sale['quantity'], 2) ?></td>
                        <td><?= htmlspecialchars($sale['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>-->
        <div class="container mt-4">
            <div class="col">
                    <section class="card p-3">
                        <h2 class="text-center">Precios de productos</h2>
                        <table id="relevantProductsTable" class="table table-striped display compact">
                            <thead>
                                <tr>
                                    <th>Relevante</th>
                                    <th>Nombre</th>
                                    <th>Precio</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?= $product['relevance'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?>
                                        </td>
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td><?= $product['price'] !== null ? $product['price'] : 'No especificado' ?></td>
                                        <td><?= htmlspecialchars($product['description']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    </section>
                </div>

            <div class="row g-4"> <!-- Grid con separación entre elementos -->
                

                <div class="col-md-6">
                    <section class="card p-3">
                        <h2 class="text-center">Clientes Potenciales por Vendedor</h2>
                        <canvas id="clientsUserChart"></canvas>
                    </section>
                </div>
                <div class="col-md-6">
                    <section class="card p-3">
                        <h2 class="text-center">Clientes Potenciales por Estado</h2>
                        <canvas id="clientsChart"></canvas>
                    </section>
                </div>
                <div class="col-md-6">
                    <section class="card p-3">
                        <h2 class="text-center">Cantidad de Productos Vendidos por Día</h2>
                        <canvas id="salesChart"></canvas>
                        <div class="mt-3 text-center">
                            <button id="prevBtn" class="btn btn-primary">← 30 días anteriores</button>
                            <button id="nextBtn" class="btn btn-primary" disabled>30 días siguientes →</button>
                        </div>
                    </section>
                </div>
                <div class="col-md-6">
                    <section class="card p-3">
                        <h2 class="text-center">Cursos más vendidos según reportes</h2>
                        <canvas id="wordsChart"></canvas>
                    </section>
                </div>


            </div>
        </div>
        <br>
        <section class="card p-3">
            <h2>Productos Más Vendidos</h2>
            <canvas id="productsChart"></canvas>

        </section>


    </div>

    <!-- Inicialización de DataTables 
    <script>
        $(document).ready(function () {
            $('#salesTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[5, 'desc']], // Asegúrate de que el índice 6 corresponda a la columna de fecha
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });
        });
    </script>-->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
    <script>
        setInterval(() => {
            fetch('keep-alive.php')
                .then(response => console.log('Sesión actualizada'));
        }, 300000); // 5 minutos
    </script>



    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const salesData = <?= $salesDataJson ?>; // Datos de ventas desde PHP
            const labels = salesData.map(item => item.fecha);
            const values = salesData.map(item => item.total_cantidad);

            let startIndex = Math.max(0, labels.length - 30); // Mostrar últimos 30 días por defecto
            let endIndex = labels.length;

            const ctx = document.getElementById('salesChart').getContext('2d');
            let salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels.slice(startIndex, endIndex),
                    datasets: [{
                        label: 'Cantidad Vendida por Día',
                        data: values.slice(startIndex, endIndex),
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.2,
                        pointRadius: 6, // Tamaño de los puntos
                        pointHoverRadius: 8, // Tamaño al pasar el mouse
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)', // Color del punto
                        pointBorderColor: 'rgba(255, 255, 255, 1)', // Borde blanco
                        pointBorderWidth: 2 // Grosor del borde del punto
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true, position: 'top' }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Fecha' } },
                        y: { beginAtZero: true, title: { display: true, text: 'Cantidad de Productos Vendidos' } }
                    }
                }
            });

            // Función para actualizar el gráfico
            function updateChart() {
                salesChart.data.labels = labels.slice(startIndex, endIndex);
                salesChart.data.datasets[0].data = values.slice(startIndex, endIndex);
                salesChart.update();

                document.getElementById('nextBtn').disabled = (endIndex >= labels.length);
                document.getElementById('prevBtn').disabled = (startIndex <= 0);
            }

            // Botón para ver 30 días anteriores
            document.getElementById('prevBtn').addEventListener('click', function () {
                if (startIndex > 0) {
                    startIndex = Math.max(0, startIndex - 30);
                    endIndex = Math.max(30, endIndex - 30);
                    updateChart();
                }
            });

            // Botón para ver 30 días siguientes
            document.getElementById('nextBtn').addEventListener('click', function () {
                if (endIndex < labels.length) {
                    startIndex = Math.min(labels.length - 30, startIndex + 30);
                    endIndex = Math.min(labels.length, endIndex + 30);
                    updateChart();
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const clientsData = <?= $clientsByStatusJson ?>;

            // Extraer etiquetas (estados) y valores (cantidad de clientes)
            const labels = clientsData.map(item => item.status);
            const values = clientsData.map(item => item.total);

            const ctx = document.getElementById('clientsChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut', // Cambiado de 'polarArea' a 'doughnut'
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Clientes Potenciales',
                        data: values,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.5)',  // Nuevo
                            'rgba(255, 206, 86, 0.5)',  // Interesado
                            'rgba(75, 192, 192, 0.5)',  // Negociación
                            'rgba(153, 102, 255, 0.5)', // Comprometido
                            'rgba(46, 204, 113, 0.5)',  // Vendido
                            'rgba(231, 76, 60, 0.5)'    // Perdido
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(46, 204, 113, 1)',
                            'rgba(231, 76, 60, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function (tooltipItem) {
                                    return `${tooltipItem.label}: ${tooltipItem.raw} clientes`;
                                }
                            }
                        }
                    },
                    cutout: '50%', // Hace que el centro sea más visible
                    rotation: -90, // Ajusta la rotación para mejor visualización
                    animation: {
                        animateRotate: true,
                        animateScale: true
                    }
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const clientsByUser = <?= $clientsByUserJson ?>; // Datos desde PHP

            // Extraer etiquetas (nombres de usuarios) y valores (cantidad de clientes)
            const labels = clientsByUser.map(item => item.username);
            const values = clientsByUser.map(item => item.total_clientes);

            const ctx = document.getElementById('clientsUserChart').getContext('2d');
            new Chart(ctx, {
                type: 'polarArea',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Clientes Potenciales por Usuario',
                        data: values,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.5)',  // Azul
                            'rgba(255, 206, 86, 0.5)',  // Amarillo
                            'rgba(75, 192, 192, 0.5)',  // Verde
                            'rgba(153, 102, 255, 0.5)', // Púrpura
                            'rgba(231, 76, 60, 0.5)'    // Rojo
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(231, 76, 60, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        datalabels: {
                            color: 'black',
                            font: { size: 14, weight: 'bold' },
                            formatter: (value, context) => {
                                return context.chart.data.labels[context.dataIndex]; // Solo el nombre del usuario
                            },
                            anchor: 'center',
                            align: 'center',
                            offset: function (context) {
                                const meta = context.chart.getDatasetMeta(0);
                                const arc = meta.data[context.dataIndex];
                                return arc ? arc.outerRadius / 2.5 : 0; // Centrar la etiqueta en el arco
                            }
                        }
                    },
                    scales: {
                        r: {
                            pointLabels: {
                                display: true,
                                centerPointLabels: true, // Centra los labels en los arcos
                                font: { size: 14 }
                            },
                            ticks: { display: true } // Oculta los valores de radio
                        }
                    }
                },
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const wordsData = <?= $wordsDataJson ?>; // Datos desde PHP

            // Extraer etiquetas (palabras) y valores (cantidad de menciones)
            const labels = wordsData.map(item => item.word);
            const values = wordsData.map(item => item.total_menciones);

            const ctx = document.getElementById('wordsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar', // Gráfico de barras horizontales
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Número de Menciones',
                        data: values,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y', // Hace que el gráfico sea horizontal
                    plugins: {
                        legend: { display: false }, // Ocultar leyenda innecesaria
                        tooltip: {
                            callbacks: {
                                label: function (tooltipItem) {
                                    return `${tooltipItem.label}: ${tooltipItem.raw} veces`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Cantidad de Menciones' } },
                        y: { title: { display: true, text: ' Cursos' } }
                    }
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const productsData = <?= $productsDataJson ?>; // Datos desde PHP

            // Extraer etiquetas (nombres de productos) y valores (total vendidos)
            const labels = productsData.map(item => item.normalized_product_name);
            const values = productsData.map(item => item.total_vendidos);

            const ctx = document.getElementById('productsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar', // Tipo de gráfico: Barras
                data: {
                    labels: labels, // Etiquetas (productos)
                    datasets: [{
                        label: 'Productos Más Vendidos',
                        data: values, // Datos de ventas
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.6)',  // Color para el primer producto
                            'rgba(255, 206, 86, 0.6)',  // Color para el segundo producto
                            'rgba(75, 192, 192, 0.6)',  // Color para el tercer producto
                            'rgba(153, 102, 255, 0.6)', // Color para el cuarto producto
                            'rgba(231, 76, 60, 0.6)',   // Color para el quinto producto
                            'rgba(46, 204, 113, 0.6)',  // Color para el sexto producto
                            'rgba(255, 99, 132, 0.6)',  // Color para el séptimo producto
                            'rgba(255, 159, 64, 0.6)',  // Color para el octavo producto
                            'rgba(255, 99, 132, 0.6)',  // Color para el noveno producto
                            'rgba(54, 162, 235, 0.6)'   // Color para el décimo producto
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(231, 76, 60, 1)',
                            'rgba(46, 204, 113, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            stacked: true, // Hacer que las barras sean apiladas
                            title: { display: true, text: 'Productos' }
                        },
                        y: {
                            stacked: true, // Hacer que las barras sean apiladas
                            beginAtZero: true, // Comienza desde cero
                            title: { display: true, text: 'Cantidad de Ventas' }
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function (tooltipItem) {
                                    return `${tooltipItem.label}: ${tooltipItem.raw} ventas`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php

    $stmt = $pdo->query("SELECT name, price, description FROM products WHERE relevance = 1");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($products);
    ?>

    <script>
        $(document).ready(function () {
            $('#relevantProductsTable').DataTable({
                paging: true,
                searching: true,
                order: [[0, 'desc']],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });
        });

    </script>




</body>

</html>