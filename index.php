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
        $isAdmin = ($role === 'admin'); // Definimos $isAdmin aquí
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

// Obtener productos relevantes
$products = $pdo->query("SELECT relevance, name, price, description FROM products ORDER BY relevance DESC, name ASC")->fetchAll();

// Consultar ventas (solo para la tabla que no se usa actualmente)
$stmt = $pdo->query("SELECT * FROM sales ORDER BY created_at DESC");
$sales = $stmt->fetchAll();

// Definir constantes por horas semanales (ajustables)
$user_constants = [
    'Sheyla' => 0.83,
    'Magaly' => 1.11,
    'Sonaly' => 1.11,
    'Frank' => 0.77,
    'Esther' => 1.33,
];

// Inicializar estructura de resultados
$puntos_usuarios = [];

// Calcular ventas válidas (última semana, >= 29.90)
$fecha_inicio = date('Y-m-d H:i:s', strtotime('-7 days'));
$fecha_fin = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    SELECT u.username, COUNT(c.id) AS total_ventas
    FROM commissions c
    JOIN users u ON c.user_id = u.id
    WHERE c.created_at BETWEEN :inicio AND :fin
    
    GROUP BY u.username
");

$stmt->execute(['inicio' => $fecha_inicio, 'fin' => $fecha_fin]);
$ventas_comisionadas = $stmt->fetchAll();

foreach ($ventas_comisionadas as $row) {
    $nombre = $row['username'];
    $ventas = (int) $row['total_ventas'];
    $puntos_base = $ventas * 100;
    $constante = $user_constants[$nombre] ?? 1;
    $puntos_norm = round($puntos_base * $constante);

    $puntos_usuarios[] = [
        'nombre' => $nombre,
        'ventas' => $ventas,
        'puntos_base' => $puntos_base,
        'constante' => $constante,
        'puntos_normalizados' => $puntos_norm
    ];
}




$stmt_ventas = $pdo->prepare("
    SELECT 
        u.username, 
        COUNT(s.id) AS total_ventas,
        SUM(s.price * s.quantity) AS monto_total,
        s.currency
    FROM sales s
    JOIN users u ON s.user_id = u.id
    WHERE s.created_at BETWEEN :inicio AND :fin
    AND (
        (s.currency = 'MXN' AND s.price >= 149) OR
        (s.currency = 'PEN' AND s.price >= 29.80)
    )
    GROUP BY u.username, s.currency
");
$stmt_ventas->execute(['inicio' => $fecha_inicio, 'fin' => $fecha_fin]);
$ventas_validas = $stmt_ventas->fetchAll();

// Procesamiento de ventas no promocionales
$ventas_por_usuario = [];
foreach ($ventas_validas as $venta) {
    $username = $venta['username'];

    if (!isset($ventas_por_usuario[$username])) {
        $ventas_por_usuario[$username] = [
            'ventas_mxn' => 0,
            'monto_mxn' => 0,
            'ventas_pen' => 0,
            'monto_pen' => 0
        ];
    }

    if ($venta['currency'] === 'MXN') {
        $ventas_por_usuario[$username]['ventas_mxn'] = (int) $venta['total_ventas'];
        $ventas_por_usuario[$username]['monto_mxn'] = (float) $venta['monto_total'];
    } else {
        $ventas_por_usuario[$username]['ventas_pen'] = (int) $venta['total_ventas'];
        $ventas_por_usuario[$username]['monto_pen'] = (float) $venta['monto_total'];
    }
}

// Cálculo de puntos para ventas no promocionales
// Cálculo CORREGIDO de puntos para ventas no promocionales
$puntos_ventas = [];
foreach ($ventas_por_usuario as $username => $datos) {
    $constante = $user_constants[$username] ?? 1;

    // Calculamos puntos según la fórmula mostrada (no por rangos)
    $puntos_mxn = $datos['monto_mxn'] / 100;
    $puntos_pen = ($datos['monto_pen'] * 5) / 100; // Conversión 1 PEN = 5 MXN
    $puntos_base = round($puntos_mxn + $puntos_pen);

    $puntos_ventas[] = [
        'nombre' => $username,
        'ventas_mxn' => $datos['ventas_mxn'],
        'monto_mxn' => number_format($datos['monto_mxn'], 2),
        'ventas_pen' => $datos['ventas_pen'],
        'monto_pen' => number_format($datos['monto_pen'], 2),
        'puntos_base' => $puntos_base,
        'constante' => $constante,
        'puntos_normalizados' => round($puntos_base * $constante)
    ];
}
// Ordenar ambos arrays por puntos normalizados (descendente)
usort($puntos_usuarios, fn($a, $b) => $b['puntos_normalizados'] - $a['puntos_normalizados']);
usort($puntos_ventas, fn($a, $b) => $b['puntos_normalizados'] - $a['puntos_normalizados']);
include('header.php');
?>

<div class="container mt-5">
    <div id="liveAlertPlaceholder"></div>

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
        <h1 class="text-center">Bienvenido(a), <?= htmlspecialchars($username) ?> 👋</h1>
        <hr>
    </div>

    <!-- Tabla de productos relevantes -->
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

        <?php if (!empty($puntos_usuarios)): ?>
            <section class="card p-3 mt-4">
                <h2 class="text-center">Puntaje Semanal por Ventas con Comisión</h2>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>Ventas con comisión</th>
                            <th>Puntos base</th>
                            <th>Constante</th>
                            <th>Puntos normalizados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($puntos_usuarios as $pu): ?>
                            <tr>
                                <td><?= htmlspecialchars($pu['nombre']) ?></td>
                                <td><?= $pu['ventas'] ?></td>
                                <td><?= $pu['puntos_base'] ?></td>
                                <td><?= $pu['constante'] ?></td>
                                <td><strong><?= $pu['puntos_normalizados'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <!-- Botón para mostrar/ocultar gráficos -->
        <div class="text-center mb-3">
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#graphsContainer"
                aria-expanded="false" aria-controls="graphsContainer" id="toggleGraphsBtn">
                Mostrar Gráficos
            </button>
        </div>
        <?php if (!empty($puntos_ventas)): ?>
            <section class="card p-3 mt-4">
                <h2 class="text-center">Puntaje por Ventas No Promocionales</h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Vendedor</th>
                                <th colspan="2" class="text-center">Ventas en MXN (≥149)</th>
                                <th colspan="2" class="text-center">Ventas en PEN (≥29.80)</th>
                                <th>Puntos Base</th>
                                <th>Constante</th>
                                <th>Puntos Finales</th>
                            </tr>
                            <tr>
                                <th></th>
                                <th>Cantidad</th>
                                <th>Monto Total</th>
                                <th>Cantidad</th>
                                <th>Monto Total</th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($puntos_ventas as $pv): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pv['nombre']) ?></td>
                                    <td><?= $pv['ventas_mxn'] ?></td>
                                    <td>$<?= $pv['monto_mxn'] ?> MXN</td>
                                    <td><?= $pv['ventas_pen'] ?></td>
                                    <td>S/<?= $pv['monto_pen'] ?></td>
                                    <td><?= $pv['puntos_base'] ?></td>
                                    <td><?= $pv['constante'] ?></td>
                                    <td><strong class="text-primary"><?= $pv['puntos_normalizados'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <strong>Fórmula:</strong>
                    <ul>
                        <li>Puntos MXN = Monto Total MXN / 100</li>
                        <li>Puntos PEN = (Monto Total PEN × 5) / 100</li>
                        <li>Puntos Base = Puntos MXN + Puntos PEN</li>
                        <li>Puntos Finales = Puntos Base × Constante Individual</li>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <!-- Contenedor colapsable para los gráficos -->
        <div class="collapse" id="graphsContainer">
            <?php include('graphs.php'); ?>
        </div>
    </div>
</div>

<!-- Script para DataTables de la tabla de productos -->
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

        // Cambiar el texto del botón cuando se muestra/ocultan los gráficos
        $('#graphsContainer').on('show.bs.collapse', function () {
            $('#toggleGraphsBtn').text('Ocultar Gráficos');
        }).on('hide.bs.collapse', function () {
            $('#toggleGraphsBtn').text('Mostrar Gráficos');
        });
    });
</script>

<!-- Script para mantener la sesión activa -->
<script>
    setInterval(() => {
        fetch('keep-alive.php')
            .then(response => console.log('Sesión actualizada'));
    }, 300000); // 5 minutos
</script>

<?php include('footer.php'); ?>