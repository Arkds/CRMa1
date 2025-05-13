<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// --- Resumen General (sin filtros) ---
$stmtResumenFijo = $pdo->query("SELECT currency, SUM(quantity) AS total_qty, SUM(quantity * price) AS total_amount FROM sales GROUP BY currency");

$resumenTotalesFijo = $stmtResumenFijo->fetchAll(PDO::FETCH_ASSOC);

$totalQuantityFijo = 0;
$totalAmountFijo = 0.0;
$totalsByCurrencyFijo = [];

foreach ($resumenTotalesFijo as $row) {
    $currency = $row['currency'];
    $totalsByCurrencyFijo[$currency] = [
        'quantity' => (int) $row['total_qty'],
        'amount' => (float) $row['total_amount']
    ];
    $totalQuantityFijo += $row['total_qty'];
    $totalAmountFijo += $row['total_amount'];
}

$isAdmin = isset($role) && $role === 'admin';
if (isset($_GET['draw'])) {
    // --- MODO AJAX para DataTables ---
    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $searchValue = $_GET['search']['value'] ?? '';

    $sqlBase = "FROM sales s JOIN users u ON s.user_id = u.id WHERE 1=1";
    if (isset($_GET['duplicated_phones']) && $_GET['duplicated_phones'] == '1') {
        $sqlBase .= " AND (
        (
            s.sale_type = 'whatsapp' AND EXISTS (
                SELECT 1 FROM (
                    SELECT 
                        REPLACE(REPLACE(REPLACE(client_phone, ' ', ''), '-', ''), '+', '') AS normalized_phone,
                        UPPER(TRIM(product_name)) AS normalized_product,
                        COUNT(*) AS cnt
                    FROM (
                        SELECT * FROM sales 
                        WHERE client_phone IS NOT NULL AND client_phone != '' AND sale_type = 'whatsapp'
                        ORDER BY id DESC
                        LIMIT 5000
                    ) recent_sales
                    GROUP BY normalized_phone, normalized_product
                    HAVING cnt > 1
                ) dup
                WHERE 
                    dup.normalized_product = UPPER(TRIM(s.product_name)) AND
                    dup.normalized_phone = REPLACE(REPLACE(REPLACE(s.client_phone, ' ', ''), '-', ''), '+', '')
            )
        )
        OR
        (
            s.sale_type = 'messenger' AND EXISTS (
                SELECT 1 FROM (
                    SELECT 
                        UPPER(TRIM(client_phone)) AS normalized_name,
                        UPPER(TRIM(SUBSTRING_INDEX(product_name, '|', -1))) AS normalized_product,
                        COUNT(*) AS cnt
                    FROM (
                        SELECT * FROM sales 
                        WHERE client_phone IS NOT NULL AND client_phone != '' AND sale_type = 'messenger'
                        ORDER BY id DESC
                        LIMIT 5000
                    ) recent_sales
                    GROUP BY normalized_name, normalized_product
                    HAVING cnt > 1
                ) dup
                WHERE 
                    dup.normalized_product = UPPER(TRIM(SUBSTRING_INDEX(s.product_name, '|', -1))) AND
                    dup.normalized_name = UPPER(TRIM(s.client_phone))
            )
        )
    )";
    }




    if (isset($_GET['invalid_price']) && $_GET['invalid_price'] == '1') {
        $sqlBase .= " AND (
        (s.currency = 'MXN' AND s.price < 50 AND s.price != 0) OR
        (s.currency = 'PEN' AND s.price > 80)
    )";
    }




    $params = [];

    // Aplicar filtros normales al modo AJAX
    if (isset($_GET['currency']) && is_array($_GET['currency']) && count($_GET['currency']) > 0) {

        $placeholders = implode(',', array_fill(0, count($_GET['currency']), '?'));
        $sqlBase .= " AND s.currency IN ($placeholders)";
        $params = array_merge($params, $_GET['currency']);
    }

    if (!empty($_GET['date'])) {
        $sqlBase .= " AND s.created_at >= ? AND s.created_at < ? + INTERVAL 1 DAY";
        $params[] = $_GET['date'];
        $params[] = $_GET['date'];
    }

    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $sqlBase .= " AND s.created_at >= ? AND s.created_at < ? + INTERVAL 1 DAY";
        $params[] = $_GET['start_date'];
        $params[] = $_GET['end_date'];
    }

    if (!empty($_GET['user_id'])) {
        $sqlBase .= " AND s.user_id = ?";
        $params[] = $_GET['user_id'];
    }

    // Filtro de búsqueda global
    if ($searchValue) {
        $sqlBase .= " AND (s.product_name LIKE ? OR s.client_phone LIKE ? OR s.observations LIKE ? OR u.username LIKE ?)";
        $searchParam = "%$searchValue%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }


    // Total general
    $totalQuery = $pdo->query("SELECT COUNT(*) FROM sales");
    $totalRecords = $totalQuery->fetchColumn();

    // Total filtrado
    $stmtFiltered = $pdo->prepare("SELECT COUNT(*) $sqlBase");
    $stmtFiltered->execute($params);
    $totalFiltered = $stmtFiltered->fetchColumn();
    // Calcular totales por moneda sobre el conjunto filtrado completo (no solo paginado)
    $stmtResumen = $pdo->prepare("SELECT s.currency, SUM(s.quantity) AS total_qty, SUM(s.quantity * s.price) AS total_amount $sqlBase");
    $stmtResumen->execute($params);
    $resumenTotales = $stmtResumen->fetchAll(PDO::FETCH_ASSOC);

    $totalQuantity = 0;
    $totalAmount = 0.0;
    $totalsByCurrency = [];


    foreach ($resumenTotales as $row) {
        $currency = $row['currency'];
        $totalsByCurrency[$currency] = [
            'quantity' => (int) $row['total_qty'],
            'amount' => (float) $row['total_amount']
        ];
        $totalQuantity += $row['total_qty'];
        $totalAmount += $row['total_amount'];
    }

    // Registros paginados
// Mapeo de columnas según DataTables
    $columns = [
        's.id',
        's.product_name',
        's.price',
        's.currency',
        's.quantity',
        '', // ← columna calculada (total), no se usa para orden
        'u.username',
        's.created_at',
        's.client_phone',
        's.observations'
    ];

    // Obtener columna y dirección del ordenamiento
    $orderColumnIndex = $_GET['order'][0]['column'] ?? 0;
    $orderDirection = $_GET['order'][0]['dir'] ?? 'asc';

    // Validar columna y dirección
    $orderBy = isset($columns[$orderColumnIndex]) && $columns[$orderColumnIndex] !== ''
        ? $columns[$orderColumnIndex]
        : 's.created_at';
    $orderDir = strtolower($orderDirection) === 'desc' ? 'DESC' : 'ASC';

    // Consulta con orden dinámico
    $stmt = $pdo->prepare("SELECT s.id, s.product_name, s.price, s.currency, s.quantity, s.created_at, 
                              s.client_phone, s.observations, s.sale_type, u.username 
                       $sqlBase ORDER BY $orderBy $orderDir LIMIT $start, $length");




    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Calcular totales antes de responder



    header('Content-Type: application/json');
    $response = [
        'draw' => intval($_GET['draw']),
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'data' => $data,
        'summary' => [
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
            'by_currency' => $totalsByCurrency
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;





}

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
// Calcular totales por moneda (modo sin AJAX)
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
                    <div class="alert alert-${type} alert-dismissible" role="alert">,
                        <div>${message}</div>,
                        '   <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>',
                        '</div>'
                ].join('')

                alertPlaceholder.append(wrapper)
            }

            const alertTrigger = document.getElementById('liveAlertBtn')
            if (alertTrigger) {
                alertTrigger.addEventListener('click', () => {
                    // Lista numerada de instrucciones
                    const message =
                        <ol>
                            <li>Filtra los reportes de ventas utilizando las opciones de fecha y usuario.</li>
                            <li>Los totales de productos vendidos y el monto total se calculan automáticamente.</li>
                            <li>Haz clic en los encabezados de la tabla para ordenar las ventas por diferentes criterios.</li>
                            <li>Utiliza la barra de búsqueda para encontrar ventas específicas rápidamente.</li>
                        </ol>
                        ;
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
                        value="<?= htmlspecialchars($date ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Desde</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                        value="<?= htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Hasta</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                        value="<?= htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Usuario</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Todos los Usuarios</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $selectedUser == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
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
        <div class="mt-4">
            <h4>Resumen de Totales (sin filtrar)</h4>
            <div class="row">
                <?php foreach ($totalsByCurrencyFijo as $currency => $totals): ?>
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

            <div class="alert alert-primary mt-3">
                <strong>Total General de Productos:</strong> <?= $totalQuantityFijo ?><br>
                <?php if ($isAdmin): ?>
                    <strong>Monto Total General:</strong> $ <?= number_format($totalAmountFijo, 2) ?>
                <?php endif; ?>
            </div>
        </div>






        <!-- Tabla de Ventas -->
        <h2>Ventas Registradas</h2>
        <!-- Botón y collapse de filtros especiales -->
        <button class="btn btn-outline-danger mb-3" type="button" data-bs-toggle="collapse"
            data-bs-target="#errorFilters">
            🔍 Filtros Especiales de Errores
        </button>

        <div class="collapse" id="errorFilters">
            <div class="card card-body border-danger mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="filterDuplicatedPhones">
                    <label class="form-check-label" for="filterDuplicatedPhones">
                        Mostrar solo ventas con teléfono duplicado
                    </label>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="filterInvalidPrice">
                    <label class="form-check-label" for="filterInvalidPrice">
                        Mostrar precios anómalos (MXN < 100 o PEN> 100)
                    </label>
                </div>

            </div>
        </div>

        <table id="salesTable" class="table table-bordered table-striped">
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
                    <th>Teléfono</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
        </table>



    </div>

    <!-- Inicializaci��n de DataTables -->
    <script>
        $(document).ready(function () {
            $('#salesTable').DataTable({
                processing: true,
                serverSide: true,
                order: [[0, 'desc']], // ← esto indica que la primera columna (ID) se ordena descendente

                ajax: {
                    url: 'report_sales.php',
                    data: function (d) {
                        d.duplicated_phones = $('#filterDuplicatedPhones').is(':checked') ? 1 : 0;
                        d.invalid_price = $('#filterInvalidPrice').is(':checked') ? 1 : 0;

                        d.date = $('#date').val();
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                        d.user_id = $('#user_id').val();
                        d.currency = [];
                        $('input[name="currency[]"]:checked').each(function () {
                            d.currency.push($(this).val());
                        });
                    }
                },


                columns: [
                    { data: 'id' },
                    { data: 'product_name' },
                    {
                        data: 'price',
                        render: function (data, type, row) {
                            const precio = parseFloat(data);
                            const esAnomalo =
                                (row.currency === 'MXN' && precio < 50 && precio !== 0) ||
                                (row.currency === 'PEN' && precio > 80);

                            if (esAnomalo) {
                                return '<span class="badge bg-warning text-dark">' + precio.toFixed(2) + '</span>';
                            }
                            return precio.toFixed(2);
                        }
                    },



                    { data: 'currency' },
                    { data: 'quantity' },
                    {
                        data: null,
                        render: function (data) {
                            return (data.price * data.quantity).toFixed(2);
                        }
                    },
                    { data: 'username' },
                    { data: 'created_at' },
                    {
                        data: 'client_phone',
                        render: function (data, type, row) {
                            let icon = '';
                            if (row.sale_type === 'whatsapp') {
                                icon = '<i class="bi bi-whatsapp text-success me-1"></i>';
                            } else if (row.sale_type === 'messenger') {
                                icon = '<i class="bi bi-messenger text-primary me-1"></i>';
                            }
                            return icon + (data ?? '');
                        }
                    }
                    ,
                    { data: 'observations' }
                ],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });

            $('#filterDuplicatedPhones').on('change', function () {
                $('#salesTable').DataTable().ajax.reload();
            });

            $('#filterInvalidPrice').on('change', function () {
                $('#salesTable').DataTable().ajax.reload();
            });




        });
    </script>


    <script>
        document.querySelector('form').addEventListener('submit', function (e) {
            e.preventDefault(); // evita recarga clásica
            $('#salesTable').DataTable().ajax.reload(); // recarga ajax con nuevos filtros
        });
    </script>

</body>
<?php
unset($stmt);
unset($usersStmt);
unset($pdo);
?>

</html>