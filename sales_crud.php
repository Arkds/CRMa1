<?php


ob_start();

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
require 'db.php';

// Función para asignar puntos por venta
function asignarPuntosVenta($pdo, $venta_id, $user_id, $price, $currency)
{
    // Verificar si cumple los criterios para puntos
    $es_valida = ($currency == 'MXN' && $price > 150) || ($currency == 'PEN' && $price > 29.90);

    if ($es_valida) {
        $pdo->beginTransaction();

        try {
            // 1. Asignar 180 puntos al usuario
            $stmt = $pdo->prepare("UPDATE users SET puntos_historicos = puntos_historicos + 180 WHERE id = ?");
            $stmt->execute([$user_id]);

            // 2. Registrar en el historial de puntos
            $stmt = $pdo->prepare("INSERT INTO historial_puntos_historicos 
                                 (user_id, puntos, tipo, comentario) 
                                 VALUES (?, 180, 'venta_normal', ?)");
            $comentario = "Venta #$venta_id - " . ($currency == 'MXN' ? "$" . number_format($price, 2) . " MXN" : "S/" . number_format($price, 2));
            $stmt->execute([$user_id, $comentario]);

            // 3. Marcar venta como procesada y guardar puntos asignados
            $stmt = $pdo->prepare("UPDATE sales SET puntos_asignados = TRUE, puntos_venta = 180 WHERE id = ?");
            $stmt->execute([$venta_id]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error asignando puntos por venta ID $venta_id: " . $e->getMessage());
            return false;
        }
    } else {
        // Marcar venta como procesada (aunque no cumpla criterios)
        $pdo->prepare("UPDATE sales SET puntos_asignados = TRUE WHERE id = ?")->execute([$venta_id]);
        return false;
    }
}

$isAdmin = isset($role) && $role === 'admin';

// Consultar productos disponibles
$productsQuery = "SELECT name FROM products";
$stmt = $pdo->query($productsQuery);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
//obtener horarios
$stmt = $pdo->prepare("SELECT shift FROM user_shifts WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_shifts = $stmt->fetchAll(PDO::FETCH_COLUMN);


// Acción actual
$action = $_GET['action'] ?? 'create'; // Por defecto, la acción será 'create'
$id = $_GET['id'] ?? null;

// Registrar o editar venta
// Registrar o editar venta
// Registrar o editar venta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = $_POST['product_name'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $currency = $_POST['currency'] ?? 'MXN';
    $saleType = $_POST['sale_type'] ?? 'messenger';

    // Solo obtener número si es venta por WhatsApp
    $clientPhone = $saleType === 'whatsapp' ? ($_POST['client_phone'] ?? null) : null;

    // Observaciones siempre se envían (pueden estar vacías)
    $observations = $_POST['observations'] ?? null;

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO sales (product_name, price, quantity, user_id, currency, sale_type, client_phone, observations) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$productName, $price, $quantity, $user_id, $currency, $saleType, $clientPhone, $observations]);
        $venta_id = $pdo->lastInsertId();

        // Asignar puntos automáticamente
        asignarPuntosVenta($pdo, $venta_id, $user_id, $price, $currency);
    } elseif ($action === 'edit' && $id) {
        // Obtener datos actuales de la venta
        $stmt = $pdo->prepare("SELECT price, currency, puntos_asignados FROM sales WHERE id = ?");
        $stmt->execute([$id]);
        $venta_actual = $stmt->fetch();

        $stmt = $pdo->prepare("UPDATE sales SET 
                            product_name = ?, 
                            price = ?, 
                            quantity = ?, 
                            currency = ?,
                            sale_type = ?,
                            client_phone = ?,
                            observations = ?
                            WHERE id = ?");
        $stmt->execute([$productName, $price, $quantity, $currency, $saleType, $clientPhone, $observations, $id]);

        // Si cambió el precio o la moneda, y aún no se han asignado puntos
        if (
            !$venta_actual['puntos_asignados'] &&
            ($venta_actual['price'] != $price || $venta_actual['currency'] != $currency)
        ) {
            asignarPuntosVenta($pdo, $id, $user_id, $price, $currency);
        }
    }
    setcookie('success_message', "¡Venta " . ($action === 'edit' ? 'actualizada' : 'registrada') . " en $currency!", time() + 5, "/");
    header('Location: sales_crud.php');
    exit;
}
if (!$isAdmin) {
    // Obtener solo las ventas del usuario en el día actual
    $salesQuery = "SELECT * FROM sales WHERE user_id = ? AND DATE(created_at) = CURDATE() ORDER BY created_at DESC";
    $stmt = $pdo->prepare($salesQuery);
    $stmt->execute([$user_id]);
} else {
    // Obtener todas las ventas del día actual para los administradores
    $salesQuery = "SELECT s.*, u.username FROM sales s JOIN users u ON s.user_id = u.id WHERE DATE(s.created_at) = CURDATE() ORDER BY s.created_at DESC";
    $stmt = $pdo->query($salesQuery);
}


$sales = $stmt->fetchAll();

// Si está en modo edición, obtener datos de la venta
$saleToEdit = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    $saleToEdit = $stmt->fetch();
}
include('header.php')

    ?>

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

        // Evento para mostrar la alerta al hacer clic en el botón
        const alertTrigger = document.getElementById('liveAlertBtn')
        if (alertTrigger) {
            alertTrigger.addEventListener('click', () => {
                // Lista numerada de instrucciones
                const message = `
                <ol>
                    <li>Registra ventas ingresando producto, precio y cantidad (por defecto 1).</li>
                    <li>Escoge si la venta se dio en messenger o wn whatsapp con el switch, por defecto estará en messenger.</li>
                    <li>Tambien puedes escoger si la venta tiene alguna observación.</li>
                    <li>Para guardar en soles debes dar en el boton correspondiente (Guardar PEN).</li>
                    <li>Para guardar en pesos mexicanos deber dar dar en el boton correspondiente (Guardar MXN).</li>
                    <li>Si la venta fue hecha en otra moneda haz click en "Mas opciones de moneda" y seleciona la moneda correspondiente</li>
                    <li>Para editar una venta solo dale al boton "editar", ten cuidado y revisa la información antes de guardar</li>
                    <li>Solo puededes ver/editar las ventas del día.</li>
                    <li>Utiliza la barra de búsqueda para encontrar ventas específicas rápidamente.</li>
                    
                </ol>
            `;
                appendAlert(message, 'success');
            })
        }
    </script>
    <h1 class="text-center">Gestión de Ventas</h1>
    <!--<button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>-->
    <button class="btn btn-outline-primary mb-3"
        onclick="window.location.replace('commissions_crud.php');">Comisiones</button>




    <!-- Mostrar mensaje de éxito si existe -->
    <?php if (isset($_COOKIE['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_COOKIE['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php setcookie('success_message', '', time() - 3600, "/"); ?>
    <?php endif; ?>

    <!-- Formulario de registro o edición de ventas -->
    <!-- Formulario de registro o edición de ventas -->
    <!-- Formulario de registro o edición de ventas -->
    <h2><?= $action === 'edit' ? 'Editar Venta' : 'Registrar Nueva Venta' ?></h2>
    <form method="POST" class="mb-4" action="sales_crud.php?action=<?= $action ?><?= $id ? '&id=' . $id : '' ?>">
        <div class="row g-3">
            <!-- Primera fila: Campos básicos -->
            <div class="col-md-4">
                <label for="product_name" class="form-label">Producto</label>
                <input type="text" class="form-control" id="product_name" name="product_name" list="productList"
                    value="<?= $saleToEdit['product_name'] ?? ' ' ?>" required>
                <datalist id="productList">
                    <?php foreach ($products as $product): ?>
                        <option value="<?= htmlspecialchars($product['name']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-md-2">
                <label for="price" class="form-label">Precio</label>
                <input type="number" step="0.01" class="form-control" id="price" name="price"
                    value="<?= $saleToEdit['price'] ?? '' ?>" required>
            </div>

            <div class="col-md-2">
                <label for="quantity" class="form-label">Cantidad</label>
                <input type="number" class="form-control" id="quantity" name="quantity"
                    value="<?= $saleToEdit['quantity'] ?? '1' ?>" required>
            </div>

            <!-- Columna para los switches -->
            <div class="col-md-4">
                <!-- Switch para tipo de venta -->
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="saleTypeToggle" name="sale_type"
                        value="whatsapp" <?= ($saleToEdit['sale_type'] ?? 'messenger') === 'whatsapp' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="saleTypeToggle">
                        <span
                            id="saleTypeLabel"><?= ($saleToEdit['sale_type'] ?? 'messenger') === 'whatsapp' ? 'WhatsApp' : 'Messenger' ?></span>
                    </label>
                </div>

                <!-- Switch para observaciones -->
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="showObservationsToggle"
                        <?= !empty($saleToEdit['observations']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="showObservationsToggle">
                        Observaciones
                    </label>
                </div>
            </div>

            <!-- WhatsApp Fields (debajo de los switches) -->
            <div class="col-md-12 collapse <?= ($saleToEdit['sale_type'] ?? 'messenger') === 'whatsapp' ? 'show' : '' ?>"
                id="whatsappFields">
                <div class="card mt-2">
                    <div class="card-body p-3">
                        <label for="client_phone" class="form-label">Número de WhatsApp</label>
                        <input type="tel" class="form-control" id="client_phone" name="client_phone"
                            placeholder="Ej: +51987654321"
                            value="<?= ($saleToEdit['sale_type'] ?? '') === 'whatsapp' ? ($saleToEdit['client_phone'] ?? '') : '' ?>">
                        <small class="text-muted">Incluir código de país (Ej: +51 para Perú)</small>
                    </div>
                </div>
            </div>

            <!-- Observations Fields (debajo de los switches) -->
            <div class="col-md-12 collapse <?= !empty($saleToEdit['observations']) ? 'show' : '' ?>"
                id="observationsFields">
                <div class="card mt-2">
                    <div class="card-body p-3">
                        <label for="observations" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observations" name="observations" rows="2"
                            placeholder="Ingrese cualquier observación relevante"><?= $saleToEdit['observations'] ?? '' ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Tercera fila: Botones de acción -->
            <div class="col-md-12">
                <div class="d-flex justify-content-end gap-3 mt-3">
                    <?php if ($action === 'edit'): ?>
                        <a href="sales_crud.php" class="btn btn-danger" style="width: 120px;">
                            Cancelar
                        </a>

                        <?php if ($saleToEdit['currency'] === 'PEN'): ?>
                            <div class="d-flex gap-2">
                                <button type="submit" name="currency" value="MXN" class="btn btn-success" style="width: 180px;">
                                    <i class="bi bi-arrow-repeat"></i> Cambiar a MXN
                                </button>
                                <button type="submit" name="currency" value="PEN" class="btn btn-primary"
                                    style="width: 180px; background-color: #0D47A1;">
                                    <i class="bi bi-check-circle"></i> Actualizar PEN
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="d-flex gap-2">
                                <button type="submit" name="currency" value="MXN" class="btn btn-success" style="width: 180px;">
                                    <i class="bi bi-check-circle"></i> Actualizar MXN
                                </button>
                                <button type="submit" name="currency" value="PEN" class="btn btn-primary"
                                    style="width: 180px; background-color: #0D47A1;">
                                    <i class="bi bi-arrow-repeat"></i> Cambiar a PEN
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="d-flex gap-2">
                            <button type="submit" name="currency" value="PEN" class="btn btn-primary"
                                style="width: 180px; background-color: #0D47A1;">
                                <i class="bi bi-currency-exchange"></i> Guardar PEN
                            </button>
                            <button type="submit" name="currency" value="MXN" class="btn btn-success" style="width: 180px;">
                                <i class="bi bi-currency-exchange"></i> Guardar MXN
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Botón para opciones de moneda adicionales -->
        <div class="text-center mt-3">
            <button class="btn btn-sm btn-outline-secondary" type="button" id="toggleCurrenciesBtn">
                <i class="bi bi-currency-exchange"></i>
                <span id="currencyToggleText">Más opciones de moneda</span>
            </button>
        </div>

        <!-- Sección de monedas adicionales -->
        <div class="collapse mt-3" id="moreCurrencies">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h6 class="mb-0">Otras monedas disponibles</h6>
                    <button type="button" class="btn-close" onclick="hideCurrencyCollapse()"
                        aria-label="Cerrar"></button>
                </div>
                <div class="card-body py-3">
                    <div class="row justify-content-center g-2">
                        <div class="col-auto">
                            <button type="submit" name="currency" value="USD" class="btn btn-outline-secondary">
                                USD <i class="bi bi-currency-dollar"></i>
                            </button>
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="currency" value="EUR" class="btn btn-outline-secondary">
                                EUR <i class="bi bi-currency-euro"></i>
                            </button>
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="currency" value="BRL" class="btn btn-outline-secondary">
                                BRL <i class="bi bi-currency-bitcoin"></i>
                            </button>
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="currency" value="CLP" class="btn btn-outline-secondary">
                                CLP <i class="bi bi-currency-yen"></i>
                            </button>
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="currency" value="COL" class="btn btn-outline-secondary">
                                COL <i class="bi bi-cash-stack"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" name="original_currency" value="<?= $saleToEdit['currency'] ?? 'MXN' ?>">
    </form>

    <!-- Tabla de ventas -->
    <h2>Ventas Registradas</h2>
    <!-- En la sección de la tabla de ventas -->
    <table id="salesTable" class="table table-striped display compact table-bordered ">
        <thead>
            <tr>
                <th>ID</th>
                <th>Producto</th>
                <th>Teléfono</th>
                <th>Precio</th>
                <th>Moneda</th>
                <th>Cantidad</th>
                <th>Total</th>
                <th>Puntos</th> <!-- Nueva columna -->
                <th>Vendedor</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sales as $sale): ?>
                <tr>
                    <td><?= $sale['id'] ?></td>
                    <td><?= htmlspecialchars($sale['product_name']) ?></td>
                    <td><?= $sale['client_phone'] ? htmlspecialchars($sale['client_phone']) : '-' ?></td>
                    <td><?= htmlspecialchars($sale['price']) ?></td>
                    <td><?= strtoupper($sale['currency']) ?></td>
                    <td><?= htmlspecialchars($sale['quantity']) ?></td>
                    <td><?= number_format($sale['price'] * $sale['quantity'], 2) ?></td>
                    <td>
                        <?php if ($sale['puntos_venta'] > 0): ?>
                            <span class="badge bg-success">+<?= $sale['puntos_venta'] ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $isAdmin ? htmlspecialchars($sale['username']) : htmlspecialchars($username) ?></td>
                    <td><?= htmlspecialchars($sale['created_at']) ?></td>
                    <td>
                        <a href="sales_crud.php?action=edit&id=<?= $sale['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

<!-- Inicialización de DataTables -->
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>

<script>
    // Función para inicializar el estado del switch
    function initializeSaleType() {
        const saleTypeToggle = document.getElementById('saleTypeToggle');
        const label = document.getElementById('saleTypeLabel');
        const whatsappFields = document.getElementById('whatsappFields');
        const bsCollapse = new bootstrap.Collapse(whatsappFields, { toggle: false });

        // Actualizar el label según el estado inicial
        label.textContent = saleTypeToggle.checked ? 'WhatsApp' : 'Messenger';

        // Mostrar/ocultar campos según el estado inicial
        if (saleTypeToggle.checked) {
            bsCollapse.show();
        } else {
            bsCollapse.hide();
        }
    }

    // Manejar el cambio del switch
    document.getElementById('saleTypeToggle').addEventListener('change', function () {
        const label = document.getElementById('saleTypeLabel');
        const whatsappFields = document.getElementById('whatsappFields');
        const bsCollapse = new bootstrap.Collapse(whatsappFields, { toggle: false });

        label.textContent = this.checked ? 'WhatsApp' : 'Messenger';

        if (this.checked) {
            bsCollapse.show();
        } else {
            bsCollapse.hide();
        }
    });

    // Inicializar cuando el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', initializeSaleType);
</script>
<script>
    // Función para manejar el tipo de venta
    function handleSaleType() {
        const saleTypeToggle = document.getElementById('saleTypeToggle');
        const label = document.getElementById('saleTypeLabel');
        const whatsappFields = document.getElementById('whatsappFields');
        const clientPhone = document.getElementById('client_phone');
        const bsCollapse = new bootstrap.Collapse(whatsappFields, { toggle: false });

        label.textContent = saleTypeToggle.checked ? 'WhatsApp' : 'Messenger';

        if (saleTypeToggle.checked) {
            bsCollapse.show();
            clientPhone.required = true;
        } else {
            bsCollapse.hide();
            clientPhone.value = '';
            clientPhone.required = false;
        }
    }

    // Función para manejar observaciones
    function handleObservations() {
        const showObservations = document.getElementById('showObservationsToggle');
        const observationsFields = document.getElementById('observationsFields');
        const bsCollapse = new bootstrap.Collapse(observationsFields, { toggle: false });

        if (showObservations.checked) {
            bsCollapse.show();
        } else {
            bsCollapse.hide();
        }
    }

    // Inicialización
    document.addEventListener('DOMContentLoaded', function () {
        // Configurar eventos
        document.getElementById('saleTypeToggle').addEventListener('change', handleSaleType);
        document.getElementById('showObservationsToggle').addEventListener('change', handleObservations);

        // Establecer estado inicial
        handleSaleType();
        handleObservations();
    });
</script>
<script>
    // Función para ocultar el collapse de monedas
    function hideCurrencyCollapse() {
        const collapseElement = document.getElementById('moreCurrencies');
        const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement, { toggle: false });
        bsCollapse.hide();
    }

    // Función para alternar el collapse de monedas
    function toggleCurrencyCollapse() {
        const collapseElement = document.getElementById('moreCurrencies');
        const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement, { toggle: false });
        bsCollapse.toggle();
    }

    // Configurar el botón de monedas
    document.addEventListener('DOMContentLoaded', function () {
        const currencyBtn = document.getElementById('toggleCurrenciesBtn');
        if (currencyBtn) {
            currencyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleCurrencyCollapse();
            });
        }
    });
</script>

</body>

</html>