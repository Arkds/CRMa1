
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
        $isAdmin = ($role === 'admin'); // Añade esta línea
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

// Resto del código...

// Obtener todas las ventas diarias para paginación
$salesByDay = $pdo->query("
    SELECT 
        DATE(created_at) AS fecha,
        SUM(quantity) AS total_cantidad,
        SUM(CASE WHEN currency = 'MXN' THEN price * quantity ELSE 0 END) AS total_mxn,
        SUM(CASE WHEN currency = 'PEN' THEN price * quantity ELSE 0 END) AS total_pen
    FROM sales
    GROUP BY fecha
    ORDER BY fecha ASC
")->fetchAll(PDO::FETCH_ASSOC);

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
$salesByUserPerDay = $pdo->query("
    SELECT 
        u.username,
        DATE(s.created_at) AS fecha,
        SUM(s.quantity) AS total_cantidad,
        SUM(CASE WHEN s.currency = 'MXN' THEN s.price * s.quantity ELSE 0 END) AS monto_mxn,
        SUM(CASE WHEN s.currency = 'PEN' THEN s.price * s.quantity ELSE 0 END) AS monto_pen
    FROM sales s
    JOIN users u ON s.user_id = u.id
    GROUP BY u.username, fecha
    ORDER BY fecha ASC
")->fetchAll(PDO::FETCH_ASSOC);

$salesByUserPerDayJson = json_encode($salesByUserPerDay);

    

// Convertir los datos a JSON para usarlos en el gráfico
$productsDataJson = json_encode($productsData);
$pdo = null;

?>


<div class="row g-4">
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
    <?php if ($isAdmin): ?>
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
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <div class="col-md-6">
            <section class="card p-3">
                <h2 class="text-center">Cursos más vendidos según reportes</h2>
                <canvas id="wordsChart"></canvas>
            </section>
        </div>
    <?php endif; ?>

    <br>
    <?php if ($isAdmin): ?>
        <section class="card p-3">
            <h2>Productos Más Vendidos</h2>
            <canvas id="productsChart"></canvas>
        </section>
    <?php endif; ?>
    
<?php if ($isAdmin): ?>
    <div class="col-md-12">
    <section class="card p-3">
        <h2 class="text-center">Ventas por Vendedor - Cantidad y Monto</h2>
        <button id="switchModoVentas" class="btn btn-secondary mb-2">Cambiar entre Cantidad / Monto</button>
        <canvas id="userSalesChart"></canvas>
        <div class="text-center mt-3">
            <button id="prevUserSales" class="btn btn-primary">← 7 días anteriores</button>
            <button id="nextUserSales" class="btn btn-primary" disabled>7 días siguientes →</button>
        </div>
    </section>
</div>

<?php endif; ?>


</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const salesData = <?= $salesDataJson ?>;
    const labels = salesData.map(item => item.fecha);
    const cantidad = salesData.map(item => item.total_cantidad);
    const mxn = salesData.map(item => item.total_mxn);
    const pen = salesData.map(item => item.total_pen);

    let startIndex = Math.max(0, labels.length - 30);
    let endIndex = labels.length;

    const ctx = document.getElementById('salesChart').getContext('2d');
    let salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.slice(startIndex, endIndex),
            datasets: [
                {
                    label: 'Cantidad Vendida',
                    data: cantidad.slice(startIndex, endIndex),
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Total MXN',
                    data: mxn.slice(startIndex, endIndex),
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.3)',
                    fill: false,
                    yAxisID: 'y1'
                },
                {
                    label: 'Total PEN',
                    data: pen.slice(startIndex, endIndex),
                    borderColor: 'rgba(255, 206, 86, 1)',
                    backgroundColor: 'rgba(255, 206, 86, 0.3)',
                    fill: false,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true, position: 'top' }
            },
            scales: {
                x: { title: { display: true, text: 'Fecha' } },
                y: { title: { display: true, text: 'Cantidad Vendida' }, beginAtZero: true },
                y1: {
                    position: 'right',
                    title: { display: true, text: 'Total en MXN / PEN' },
                    beginAtZero: true,
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });

    function updateChart() {
        salesChart.data.labels = labels.slice(startIndex, endIndex);
        salesChart.data.datasets[0].data = cantidad.slice(startIndex, endIndex);
        salesChart.data.datasets[1].data = mxn.slice(startIndex, endIndex);
        salesChart.data.datasets[2].data = pen.slice(startIndex, endIndex);
        salesChart.update();
        document.getElementById('nextBtn').disabled = (endIndex >= labels.length);
        document.getElementById('prevBtn').disabled = (startIndex <= 0);
    }

    document.getElementById('prevBtn').addEventListener('click', function () {
        if (startIndex > 0) {
            startIndex = Math.max(0, startIndex - 30);
            endIndex = Math.max(30, endIndex - 30);
            updateChart();
        }
    });

    document.getElementById('nextBtn').addEventListener('click', function () {
        if (endIndex < labels.length) {
            startIndex = Math.min(labels.length - 30, startIndex + 30);
            endIndex = Math.min(labels.length, endIndex + 30);
            updateChart();
        }
    });
});

</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const clientsData = <?= $clientsByStatusJson ?>;

        const labels = clientsData.map(item => item.status);
        const values = clientsData.map(item => item.total);

        const ctx = document.getElementById('clientsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Clientes Potenciales',
                    data: values,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(46, 204, 113, 0.5)',
                        'rgba(231, 76, 60, 0.5)'
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
                cutout: '50%',
                rotation: -90,
                animation: {
                    animateRotate: true,
                    animateScale: true
                }
            }
        });
    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const clientsByUser = <?= $clientsByUserJson ?>;

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
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(231, 76, 60, 0.5)'
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
                            return context.chart.data.labels[context.dataIndex];
                        },
                        anchor: 'center',
                        align: 'center',
                        offset: function (context) {
                            const meta = context.chart.getDatasetMeta(0);
                            const arc = meta.data[context.dataIndex];
                            return arc ? arc.outerRadius / 2.5 : 0;
                        }
                    }
                },
                scales: {
                    r: {
                        pointLabels: {
                            display: true,
                            centerPointLabels: true,
                            font: { size: 14 }
                        },
                        ticks: { display: true }
                    }
                }
            },
        });
    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const wordsData = <?= $wordsDataJson ?>;

        const labels = wordsData.map(item => item.word);
        const values = wordsData.map(item => item.total_menciones);

        const ctx = document.getElementById('wordsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
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
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
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
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const productsData = <?= $productsDataJson ?>;

        const labels = productsData.map(item => item.normalized_product_name);
        const values = productsData.map(item => item.total_vendidos);

        const ctx = document.getElementById('productsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Productos Más Vendidos',
                    data: values,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(231, 76, 60, 0.6)',
                        'rgba(46, 204, 113, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)'
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
                        stacked: true,
                        title: { display: true, text: 'Productos' }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
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

<script>
document.addEventListener("DOMContentLoaded", function () {
    const rawData = <?= $salesByUserPerDayJson ?>;
    const grouped = {}, usuarios = new Set();

    rawData.forEach(item => {
        if (!grouped[item.fecha]) grouped[item.fecha] = {};
        grouped[item.fecha][item.username] = {
            cantidad: parseInt(item.total_cantidad),
            mxn: parseFloat(item.monto_mxn),
            pen: parseFloat(item.monto_pen)
        };
        usuarios.add(item.username);
    });

    const fechas = Object.keys(grouped).sort();
    const usuariosArray = [...usuarios];
    let start = Math.max(0, fechas.length - 7);
    let end = fechas.length;

    const colorCantidad = ['#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f', '#edc948', '#b07aa1'];
    const colorMXN = ['#9c755f', '#bab0ab', '#ff9da7', '#8cd17d', '#b6992d', '#499894', '#d37295'];
    const colorPEN = ['#fabfd2', '#b5d9ff', '#c9b2f5', '#ffbf80', '#a0cbe8', '#d4a6c8', '#86bcb6'];

    const datasets = [];
    usuariosArray.forEach((user, i) => {
        datasets.push({
            label: `${user} - Cantidad`,
            data: [],
            backgroundColor: colorCantidad[i % colorCantidad.length],
            stack: 'cantidad'
        });
        datasets.push({
            label: `${user} - MXN`,
            data: [],
            backgroundColor: colorMXN[i % colorMXN.length],
            stack: 'mxn'
        });
        datasets.push({
            label: `${user} - PEN`,
            data: [],
            backgroundColor: colorPEN[i % colorPEN.length],
            stack: 'pen'
        });
    });

    const ctx = document.getElementById('userSalesChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'bar',
        data: { labels: [], datasets },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: 'Ventas por Vendedor (Cantidad, MXN y PEN)' },
                legend: { position: 'top' },
                tooltip: {
                    mode: 'nearest',
                    intersect: true,
                    callbacks: {
                        label: function (tooltipItem) {
                            return `${tooltipItem.dataset.label}: ${tooltipItem.raw}`;
                        }
                    }
                }
            },
            scales: {
                x: { stacked: true, title: { display: true, text: 'Fecha' } },
                y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Cantidad / Monto' } }
            }
        }
    });

    function updateChart() {
        const fechasVisibles = fechas.slice(start, end);
        chart.data.labels = fechasVisibles;

        usuariosArray.forEach((user, i) => {
            chart.data.datasets[i * 3].data = fechasVisibles.map(f => grouped[f]?.[user]?.cantidad || 0);
            chart.data.datasets[i * 3 + 1].data = fechasVisibles.map(f => grouped[f]?.[user]?.mxn || 0);
            chart.data.datasets[i * 3 + 2].data = fechasVisibles.map(f => grouped[f]?.[user]?.pen || 0);
        });

        chart.update();
        document.getElementById('prevUserSales').disabled = start <= 0;
        document.getElementById('nextUserSales').disabled = end >= fechas.length;
    }

    document.getElementById('prevUserSales').addEventListener('click', () => {
        if (start > 0) {
            start = Math.max(0, start - 7);
            end = start + 7;
            updateChart();
        }
    });

    document.getElementById('nextUserSales').addEventListener('click', () => {
        if (end < fechas.length) {
            start = Math.min(fechas.length - 7, start + 7);
            end = Math.min(fechas.length, end + 7);
            updateChart();
        }
    });

    // Toggle entre cantidad y monto
    let modo = 'cantidad';
    document.getElementById('switchModoVentas').addEventListener('click', () => {
        modo = (modo === 'cantidad') ? 'monto' : 'cantidad';
        chart.data.datasets.forEach(ds => {
            ds.hidden = modo === 'cantidad'
                ? !ds.label.includes('Cantidad')
                : !(ds.label.includes('MXN') || ds.label.includes('PEN'));
        });
        chart.update();
    });

    // Mostrar solo cantidades al inicio
    chart.data.datasets.forEach(ds => {
        ds.hidden = !ds.label.includes('Cantidad');
    });

    updateChart();
});

</script>



