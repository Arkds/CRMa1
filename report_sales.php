<?php
require_once 'db.php';

if (isset($_COOKIE['user_session'])) {
    $user_data = json_decode(base64_decode($_COOKIE['user_session']), true);

    if ($user_data) {
        $user_id = $user_data['user_id'];
        $username = $user_data['username'];
        $role = $user_data['role'];
    } else {
        header("Location: login.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}


$isAdmin = isset($role) && $role === 'admin';

// Variables para los filtros
$date = $_GET['date'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$selectedUser = $_GET['user_id'] ?? null;

// Construir la consulta SQL seg��n los filtros
$query = "SELECT 
    s.id, s.product_name, s.price, s.quantity, s.currency,
    s.created_at, s.sale_type, s.client_phone, s.observations,
    u.username
    FROM sales s 
    JOIN users u ON s.user_id = u.id 
    WHERE 1=1";
$params = [];
if (!empty($_GET['currency'])) {
    $placeholders = implode(',', array_fill(0, count($_GET['currency']), '?'));
    $query .= " AND s.currency IN ($placeholders)";
    $params = array_merge($params, $_GET['currency']);
}
// Filtrar por fecha exacta
if ($date) {
    $query .= " AND s.created_at >= ? AND s.created_at < ? + INTERVAL 1 DAY";
    $params[] = $date;
    $params[] = $date;
}

// Filtrar por rango de fechas
if ($startDate && $endDate) {
    $query .= " AND s.created_at >= ? AND s.created_at < ? + INTERVAL 1 DAY";
    $params[] = $startDate;
    $params[] = $endDate;
}

// Filtrar por usuario
if ($selectedUser) {
    $query .= " AND s.user_id = ?";
    $params[] = $selectedUser;
}

// Ejecutar consulta
$query .= " ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Calcular el total
$totalQuantity = 0;
$totalAmount = 0.0;

foreach ($sales as $sale) {
    $totalQuantity += $sale['quantity'];
    $totalAmount += $sale['price'] * $sale['quantity'];
}

// Obtener la lista de usuarios para el filtro
$usersQuery = "SELECT id, username FROM users";
$usersStmt = $pdo->query($usersQuery);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
include('header.php')

    ?>


<body>

    <div class="container mt-5">
        <div id="liveAlertPlaceholder"></div>
        <!--<button type="button" class="btn btn-outline-dark float-end" id="liveAlertBtn">Ayuda</button>-->

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

            const alertTrigger = document.getElementById('liveAlertBtn')
            if (alertTrigger) {
                alertTrigger.addEventListener('click', () => {
                    // Lista numerada de instrucciones
                    const message = `
                <ol>
                    <li>Filtra los reportes de ventas utilizando las opciones de fecha y usuario.</li>
                    <li>Los totales de productos vendidos y el monto total se calculan automáticamente.</li>
                    <li>Haz clic en los encabezados de la tabla para ordenar las ventas por diferentes criterios.</li>
                    <li>Utiliza la barra de búsqueda para encontrar ventas específicas rápidamente.</li>
                </ol>
            `;
                    appendAlert(message, 'success');
                })
            }
        </script>
        <h1 class="text-center">Reportes de Ventas</h1>



        <!--<button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>-->

        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-3">
                    <label for="date" class="form-label">Filtrar por fecha exacta</label>
                    <input type="date" class="form-control" id="date" name="date"
                        value="<?= htmlspecialchars($date) ?>">
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Desde</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                        value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Hasta</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                        value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Usuario</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Todos los Usuarios</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $selectedUser == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- Filtro de monedas mejor integrado -->
            <div class="col-md-3 mt-2">
                <div class="border p-2 rounded">
                    <label class="form-label d-block mb-1">Moneda</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="currency[]" value="MXN" id="currencyMXN"
                            <?= (empty($_GET['currency']) || in_array('MXN', $_GET['currency'] ?? [])) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="currencyMXN">MXN</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="currency[]" value="PEN" id="currencyPEN"
                            <?= in_array('PEN', $_GET['currency'] ?? []) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="currencyPEN">PEN</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="currency[]" value="USD" id="currencyUSD"
                            <?= in_array('USD', $_GET['currency'] ?? []) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="currencyUSD">USD</label>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </div>
        </form>
        <!-- Resumen de Totales -->
        <div class="mt-1">
            <h4>Resumen de Totales</h4>
            <?php
            $totalsByCurrency = [];
            foreach ($sales as $sale) {
                $currency = $sale['currency'];
                if (!isset($totalsByCurrency[$currency])) {
                    $totalsByCurrency[$currency] = [
                        'quantity' => 0,
                        'amount' => 0.0
                    ];
                }
                $totalsByCurrency[$currency]['quantity'] += $sale['quantity'];
                $totalsByCurrency[$currency]['amount'] += $sale['price'] * $sale['quantity'];
            }
            ?>

            <div class="row">
                <?php foreach ($totalsByCurrency as $currency => $totals): ?>
                    <div class="col-md-3 mb-1">
                        <div class="card">
                            <div class="card-body small">
                                <h6 class="card-title mb-1 text-uppercase"><?= $currency ?></h6>
                                <p class="card-text mb-0">
                                    <strong>Productos:</strong> <?= $totals['quantity'] ?><br>
                                    <strong>Monto Total:</strong> <?= $currency ?>
                                    <?= number_format($totals['amount'], 2) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>

            <!-- Total general -->
            <div class="alert alert-primary mt-3">
                <strong>Total General de Productos:</strong> <?= $totalQuantity ?><br>
                <?php if ($isAdmin): ?>
                    <strong>Monto Total General:</strong> $ <?= number_format($totalAmount, 2) ?>
                <?php endif; ?>
            </div>
        </div>
        <!-- Tabla de Ventas -->
        <h2>Ventas Registradas</h2>
        <table id="salesTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Moneda</th>
                    <th>Cantidad</th>
                    <th>Total</th>
                    <th>Vendedor</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($sales) > 0): ?>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?= $sale['id'] ?></td>
                            <td><?= htmlspecialchars($sale['product_name']) ?></td>
                            <td><?= htmlspecialchars($sale['price']) ?></td>
                            <td><?= strtoupper($sale['currency']) ?></td>

                            <td><?= htmlspecialchars($sale['quantity']) ?></td>
                            <td><?= number_format($sale['price'] * $sale['quantity'], 2) ?></td>
                            <td><?= htmlspecialchars($sale['username']) ?></td>
                            <td><?= htmlspecialchars($sale['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No se encontraron ventas para los criterios seleccionados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>


    </div>

    <!-- Inicializaci��n de DataTables -->
    <script>
        $(document).ready(function () {
            $('#salesTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[0, 'desc']],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });
        });



    </script>


</body>
<?php
unset($stmt);
unset($usersStmt);
unset($pdo);
?>

</html>