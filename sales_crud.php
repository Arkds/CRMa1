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
require_once 'db.php';

// Función para asignar puntos por venta
function asignarPuntosVenta($pdo, $venta_id, $user_id, $price, $currency, $quantity)
{
    // Verificar si cumple los criterios para puntos
    $es_valida = $price >= 1;


    if ($es_valida) {
        $pdo->beginTransaction();

        try {
            $puntos = 50 * intval($quantity); // 50 puntos por unidad

            // 1. Asignar puntos al usuario
            $stmt = $pdo->prepare("UPDATE users SET puntos_historicos = puntos_historicos + ? WHERE id = ?");
            $stmt->execute([$puntos, $user_id]);

            // 2. Registrar en el historial de puntos
            $stmt = $pdo->prepare("INSERT INTO historial_puntos_historicos 
                                 (user_id, puntos, tipo, comentario) 
                                 VALUES (?, ?, 'venta_normal', ?)");
            $comentario = "Venta #$venta_id - {$quantity} unid. - " .
                ($currency == 'MXN' ? "$" . number_format($price, 2) . " MXN" : "S/" . number_format($price, 2));
            $stmt->execute([$user_id, $puntos, $comentario]);

            // 3. Marcar venta como procesada y guardar puntos asignados
            $stmt = $pdo->prepare("UPDATE sales SET puntos_asignados = TRUE, puntos_venta = ? WHERE id = ?");
            $stmt->execute([$puntos, $venta_id]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error asignando puntos por venta ID $venta_id: " . $e->getMessage());
            return false;
        }
    } else {
        // Marcar venta como procesada aunque no cumpla criterio
        $pdo->prepare("UPDATE sales SET puntos_asignados = TRUE WHERE id = ?")->execute([$venta_id]);
        return false;
    }
}

$isAdmin = isset($role) && $role === 'admin';

// Consultar productos disponibles
$productsQuery = "SELECT name, price, channel, estado FROM products";

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
    $currency = $_POST['currency'] ?? $_POST['currency_hidden'] ?? 'MXN';
    $monedas_validas = ['MXN', 'PEN', 'USD', 'EUR', 'BRL', 'CLP', 'COL'];
    if (!in_array($currency, $monedas_validas)) {
        $currency = 'MXN';
    }



    $saleType = $_POST['sale_type'] ?? 'messenger';

    // Solo obtener número si es venta por WhatsApp
    $clientPhone = $_POST['client_phone'] ?? null;

    // Observaciones siempre se envían (pueden estar vacías)
    $observations = $_POST['observations'] ?? null;

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO sales (product_name, price, quantity, user_id, currency, sale_type, client_phone, observations) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$productName, $price, $quantity, $user_id, $currency, $saleType, $clientPhone, $observations]);
        $venta_id = $pdo->lastInsertId();

        asignarPuntosVenta($pdo, $venta_id, $user_id, $price, $currency, $quantity);


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


$ventas_agrupadas_usuario = array_values(array_filter($sales, function ($venta) use ($user_id) {
    return $venta['user_id'] == $user_id;
}));

// Si está en modo edición, obtener datos de la venta
$saleToEdit = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    $saleToEdit = $stmt->fetch();
}
$stmt = null;

include('header.php')

    ?>
<style>
    .border-whatsapp {
        border: 2px solid #25D366 !important;
        /* Verde WhatsApp real */
        box-shadow: 0 0 5px #25D36666;
    }

    .border-messenger {
        border: 2px solid #0084FF !important;
        /* Azul Messenger real */
        box-shadow: 0 0 5px #0084FF66;
    }
</style>


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
    <h2><?= $action === 'edit' ? 'Editar Venta' : 'Registrar Nueva Venta' ?></h2>
    <div id="alertaDuplicado" class="alert alert-warning d-none" role="alert"></div>

    <form method="POST" class="mb-4" action="sales_crud.php?action=<?= $action ?><?= $id ? '&id=' . $id : '' ?>">
        <div class="row g-3">
            <!-- Primera fila: Campos básicos -->
            <div class="col-md-4">
                <label for="product_name" class="form-label">Producto</label>
                <input type="text" class="form-control" id="product_name" name="product_name" list="productList"
                    value="<?= $saleToEdit['product_name'] ?? ' ' ?>" required>
                <small id="monedaSugerida" class="text-muted mt-1 d-block"></small>

                <datalist id="productList">
                    <?php foreach ($products as $product): ?>
                        <option value="<?= htmlspecialchars($product['name'] . '|' . $product['channel']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <small id="ultimasVentas" class="text-muted d-block mt-1 ms-1"></small>

            </div>

            <div class="col-md-2">
                <label for="price" class="form-label">Precio</label>
                <input type="number" step="0.01" class="form-control" id="price" name="price" list="priceList" required
                    value="<?= $saleToEdit['price'] ?? '' ?>">
                <ul id="priceSuggestions" class="list-group position-absolute mt-1"
                    style="z-index: 9999; display: none; width: auto;"></ul>


                <datalist id="priceList"></datalist>

            </div>
            <!-- WhatsApp Fields (debajo de los switches) -->
            <div class="col-md-3">
                <label for="client_phone" class="form-label" id="client_phone_label">
                    <?= ($saleToEdit['sale_type'] ?? 'messenger') === 'whatsapp' ? 'Número de WhatsApp' : 'Nombre del Cliente' ?>
                </label>
                <input type="text" class="form-control" id="client_phone" name="client_phone"
                    placeholder="<?= ($saleToEdit['sale_type'] ?? 'messenger') === 'whatsapp' ? 'Ej: +51987654321' : 'Ej: Juan Pérez' ?>"
                    value="<?= $saleToEdit['client_phone'] ?? '' ?>" required>
            </div>

            <!-- Columna para los switches -->
            <div class="col-md-2">
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

            <div class="col-md-1">
                <label for="quantity" class="form-label">Cant.</label>
                <input type="number" class="form-control form-control-sm" id="quantity" name="quantity"
                    value="<?= $saleToEdit['quantity'] ?? '1' ?>" min="1">
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
        <input type="hidden" name="currency_hidden" id="currency_hidden">

    </form>

    <div id="alertaValidacionMoneda" class="alert alert-warning d-none mt-3" role="alert"></div>

    <!-- Tabla de ventas -->
    <h2>Ventas Registradas</h2>
    <!-- En la sección de la tabla de ventas -->
    <table id="salesTable" class="table table-striped display compact table-bordered ">
        <thead>
            <tr>
                <th>ID</th>
                <th>Producto</th>
                <th>Teléfono / Nombre</th>
                <th>Precio</th>
                <th>Moneda</th>
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
                    <td>
                        <?php if ($sale['sale_type'] === 'whatsapp'): ?>
                            <i class="bi bi-whatsapp" style="color: #25D366;"></i>
                        <?php else: ?>
                            <i class="bi bi-messenger" style="color: #0084FF;"></i>
                        <?php endif; ?>
                        <?= $sale['client_phone'] ? htmlspecialchars($sale['client_phone']) : '-' ?>
                    </td>

                    <td><?= htmlspecialchars($sale['price']) ?></td>
                    <td><?= strtoupper($sale['currency']) ?></td>

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
        const clientPhone = document.getElementById('client_phone');
        const clientPhoneLabel = document.getElementById('client_phone_label');

        // Limpiar clases anteriores
        clientPhone.classList.remove('border-whatsapp', 'border-messenger');

        if (saleTypeToggle.checked) {
            // WhatsApp
            clientPhoneLabel.textContent = 'Número de WhatsApp';
            clientPhone.placeholder = 'Ej: +51987654321';
            clientPhone.classList.add('border-whatsapp');
        } else {
            // Messenger
            clientPhoneLabel.textContent = 'Nombre del Cliente';
            clientPhone.placeholder = 'Ej: Juan Pérez';
            clientPhone.classList.add('border-messenger');
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

<script>
    const priceInput = document.getElementById('price');
    const productInput = document.getElementById('product_name');
    const priceSuggestions = document.getElementById('priceSuggestions');
    const productData = <?= json_encode($products) ?>;

    let currentPriceVariants = [];

    function getPriceVariants(basePrice) {
        let variants = [basePrice];
        if (basePrice === 100) variants.push(99.00, 99.90, 80.00);
        else if (basePrice === 150) variants.push(149.00, 149.90, 130.00);
        else if (basePrice === 19.90) variants.push(19.00, 20.00);
        else if (basePrice === 9.90) variants.push(8.00, 9.00);
        return [...new Set(variants.map(v => parseFloat(v.toFixed(2))))];
    }

    function mostrarSugerencias(precios) {
        priceSuggestions.innerHTML = '';
        if (!precios.length) {
            priceSuggestions.style.display = 'none';
            return;
        }

        precios.forEach(precio => {
            const li = document.createElement('li');
            li.className = 'list-group-item list-group-item-action py-1';
            li.textContent = precio.toFixed(2);
            li.style.cursor = 'pointer';
            li.onclick = () => {
                priceInput.value = li.textContent;
                priceSuggestions.style.display = 'none';
            };
            priceSuggestions.appendChild(li);
        });

        const rect = priceInput.getBoundingClientRect();
        priceSuggestions.style.position = 'absolute';
        priceSuggestions.style.top = `${priceInput.offsetTop + priceInput.offsetHeight}px`;
        priceSuggestions.style.left = `${priceInput.offsetLeft}px`;
        priceSuggestions.style.width = `${priceInput.offsetWidth}px`;
        priceSuggestions.style.display = 'block';
    }

    // Al seleccionar un producto
    productInput.addEventListener('input', function () {
        const [name, channel] = this.value.split('|').map(s => s.trim());
        const producto = productData.find(p => p.name === name && p.channel === channel);

        if (producto) {
            priceInput.value = parseFloat(producto.price).toFixed(2);

            if (producto.estado && producto.estado.toLowerCase() === 'promocion') {
                currentPriceVariants = getPriceVariants(parseFloat(producto.price));
                mostrarSugerencias(currentPriceVariants);
            } else {
                currentPriceVariants = [];
                priceSuggestions.style.display = 'none';
            }
        }
    });

    // Mostrar sugerencias incluso si el valor actual es parcial o no coincide
    priceInput.addEventListener('focus', function () {
        if (currentPriceVariants.length > 0) {
            mostrarSugerencias(currentPriceVariants);
        }
    });

    priceInput.addEventListener('click', function () {
        if (currentPriceVariants.length > 0) {
            mostrarSugerencias(currentPriceVariants);
        }
    });

    // Ocultar sugerencias al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (!priceInput.contains(e.target) && !priceSuggestions.contains(e.target)) {
            priceSuggestions.style.display = 'none';
        }
    });
</script>



<script>
    const ventasHoy = <?= json_encode($ventas_agrupadas_usuario) ?>;
</script>

<script>
    let ventasRecientes = <?= json_encode(array_map(function ($s) {
        return [
            'product' => trim(strtolower(preg_replace('/\|+$/', '', $s['product_name']))),
            'phone' => strtolower(trim(preg_replace('/[\s\-\+]/', '', $s['client_phone'])))
        ];
    }, $sales)); ?>;


    function normalizarProducto(producto) {
        return producto.trim().toLowerCase().replace(/\|+$/, '');
    }

    function normalizarTelefono(telefono) {
        return telefono.trim().toLowerCase().replace(/[\s\-\+]/g, '');
    }


    function validarDuplicado() {
        const productoInput = document.getElementById('product_name');
        const telefonoInput = document.getElementById('client_phone');
        const producto = normalizarProducto(productoInput.value);
        const telefono = normalizarTelefono(telefonoInput.value);
        const alerta = document.getElementById('alertaDuplicado');

        // Solo validar si ambos campos tienen contenido
        if (producto && telefono) {
            const duplicado = ventasRecientes.some(v => v.product === producto && v.phone === telefono);

            if (duplicado) {
                alerta.classList.remove('d-none');
                alerta.textContent = "⚠️ Ya existe una venta hoy con el mismo producto y número/nombre. Verifica antes de continuar.";
            } else {
                alerta.classList.add('d-none');
            }
        } else {
            alerta.classList.add('d-none');
        }
    }



    document.getElementById('product_name').addEventListener('input', validarDuplicado);
    document.getElementById('client_phone').addEventListener('input', validarDuplicado);
</script>

<script>
    function normalizarProducto(prod) {
        return prod.trim().toLowerCase().replace(/\|+$/, '');
    }

    function normalizarTelefono(phone) {
        return phone.replace(/[\s\-\+]/g, '');
    }

    function actualizarCoincidencias() {
        const productoInput = document.getElementById('product_name').value;
        const telefonoInput = document.getElementById('client_phone').value;
        const alerta = document.getElementById('alertaCoincidencias');

        const producto = normalizarProducto(productoInput);
        const telefono = normalizarTelefono(telefonoInput);

        if (!producto || !telefono) {
            alerta.classList.add('d-none');
            alerta.innerHTML = '';
            return;
        }

        const coincidencias = ventasHoy.filter(v => {
            const vProd = normalizarProducto(v.product_name);
            const vTel = normalizarTelefono(v.client_phone || '');
            return vProd === producto && vTel === telefono;
        });

        if (coincidencias.length > 0) {
            const ultimos = coincidencias.slice(0, 5).map(v =>
                `<li><strong>${new Date(v.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</strong> - ${v.product_name}</li>`
            ).join('');

            alerta.classList.remove('d-none');
            alerta.innerHTML = `
            <strong>⚠️ Hoy ingresaste ${coincidencias.length} ${coincidencias.length === 1 ? 'vez' : 'veces'} el producto</strong>
            <br><strong>"${productoInput}" con el número "${telefonoInput}"</strong>.<br>
            <ul class="mb-0">${ultimos}</ul>
        `;
        } else {
            alerta.classList.add('d-none');
            alerta.innerHTML = '';
        }
    }

    document.getElementById('product_name').addEventListener('input', actualizarCoincidencias);
    document.getElementById('client_phone').addEventListener('input', actualizarCoincidencias);
</script>
<script>
    const ultimasVentasElement = document.getElementById('ultimasVentas');

    function normalizarProducto(p) {
        return (p || '').toLowerCase().trim().replace(/\|+$/, '');
    }

    function formatearTelefono(tel) {
        return tel ? tel.replace(/\s+/g, '') : ' ';
    }

    function mostrarUltimasVentas() {
        const input = document.getElementById('product_name').value;
        const valorNormalizado = normalizarProducto(input);

        if (!valorNormalizado) {
            ultimasVentasElement.innerHTML = '';
            return;
        }

        const coincidencias = ventasHoy.filter(v => normalizarProducto(v.product_name).includes(valorNormalizado));
        if (coincidencias.length === 0) {
            ultimasVentasElement.innerHTML = 'No hay registros previos con este producto.';
            return;
        }

        const lista = coincidencias.slice(0, 5).map(v => {
            const hora = new Date(v.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const tel = formatearTelefono(v.client_phone);
            const precio = `${v.currency} ${parseFloat(v.price).toFixed(2)}`;
            return `<li><strong>${hora}</strong> | ${v.product_name} | <span class="text-secondary">${tel}</span>  | <span class="text-success">${precio}</span></li>`;
        }).join('');

        ultimasVentasElement.innerHTML = `
            <small>Últimas 5 ventas con este producto:</small>
            <ul class="mb-0 small ps-3">${lista}</ul>
        `;
    }

    document.getElementById('product_name').addEventListener('input', mostrarUltimasVentas);
    window.addEventListener('DOMContentLoaded', mostrarUltimasVentas);
</script>

<script>
    function sugerirMoneda() {
        const productoInput = document.getElementById('product_name');
        const valor = productoInput.value;
        const sugerencia = document.getElementById('monedaSugerida');

        if (!valor.includes('|')) {
            sugerencia.textContent = '';
            return;
        }

        const [nombre, canal] = valor.split('|').map(s => s.trim().toLowerCase());
        const producto = productData.find(p => p.name.toLowerCase() === nombre && p.channel.toLowerCase() === canal);

        if (producto) {
            let monedaSugerida = '';
            if (producto.price < 100) {
                monedaSugerida = 'PEN';
            } else {
                monedaSugerida = 'MXN';
            }

            sugerencia.innerHTML = `<small class="text-primary">💡 Este producto parece ser en <strong>${monedaSugerida}</strong>.</small>`;
        } else {
            sugerencia.textContent = '';
        }
    }

    document.getElementById('product_name').addEventListener('input', sugerirMoneda);

</script>
<script>
    let lastClickedCurrency = null;

    document.querySelectorAll("button[type='submit'][name='currency']").forEach(btn => {
        btn.addEventListener("click", () => {
            lastClickedCurrency = btn.value;
        });
    });

    document.querySelector("form").addEventListener("submit", function (e) {
        e.preventDefault();

        const precio = parseFloat(document.getElementById('price').value);
        const moneda = document.activeElement?.value;
        const productoInput = document.getElementById('product_name').value;

        const partes = productoInput.split('|');
        if (partes.length !== 2 || isNaN(precio) || !moneda) {
            document.getElementById('currency_hidden').value = lastClickedCurrency || moneda || 'MXN';
            this.submit();
            return;
        }


        const nombre = partes[0].trim().toLowerCase();
        const canal = partes[1].trim().toLowerCase();

        const productoCoincidente = productData.find(p =>
            p.name.toLowerCase() === nombre &&
            p.channel.toLowerCase() === canal
        );

        if (productoCoincidente) {
            const precioBase = parseFloat(productoCoincidente.price);
            let monedaSugerida = 'MXN';
            if (precioBase < 100 && precio >= (precioBase - 5) && precio <= (precioBase + 5)) {
                monedaSugerida = 'PEN';
            }

            // Si la moneda es incorrecta primero
            if (moneda !== monedaSugerida) {
                Swal.fire({
                    icon: 'warning',
                    title: '¿Moneda incorrecta?',
                    html: `Estás ingresando <strong>${moneda}</strong> pero se sugiere <strong>${monedaSugerida}</strong> según el precio <strong>${precio}</strong>.`,
                    showCancelButton: true,
                    confirmButtonText: 'Insertar de todas formas',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (lastClickedCurrency) {
                            document.getElementById('currency_hidden').value = lastClickedCurrency;
                        }

                        e.target.submit();
                    }
                });
                return;
            }

            // Luego validamos el rango de precios
            let tolerancia = { min: precioBase, max: precioBase };
            if (moneda === 'MXN') {
                tolerancia.min = precioBase - 50;
                tolerancia.max = precioBase + 80;
            } else if (moneda === 'PEN') {
                tolerancia.min = precioBase - 10;
                tolerancia.max = precioBase + 10;
            }

            const dentroDelRango = precio >= tolerancia.min && precio <= tolerancia.max;

            if (!dentroDelRango) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Precio fuera de rango esperado',
                    html: `El producto "<strong>${productoCoincidente.name}</strong>" con canal "<strong>${productoCoincidente.channel}</strong>" tiene un precio base de <strong>${precioBase.toFixed(2)}</strong>.<br>
Estás ingresando <strong>${precio.toFixed(2)} ${moneda}</strong>, lo cual está fuera del rango permitido:<br>
<strong>${tolerancia.min.toFixed(2)} a ${tolerancia.max.toFixed(2)}</strong>.`,
                    showCancelButton: true,
                    confirmButtonText: 'Insertar de todas formas',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (lastClickedCurrency) {
                            document.getElementById('currency_hidden').value = lastClickedCurrency || moneda || 'MXN';
                        }

                        e.target.submit();
                    }
                });
            } else {
                if (lastClickedCurrency) {
                    document.getElementById('currency_hidden').value = lastClickedCurrency || moneda || 'MXN';
                }



                e.target.submit(); // Todo correcto
            }

        } else {
            // Producto no encontrado
            if (lastClickedCurrency) {
                document.getElementById('currency_hidden').value = lastClickedCurrency || moneda || 'MXN';
            }

            e.target.submit();

        }
    });

</script>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
<?php $pdo = null; ?>
<?php
unset($stmt);
?>

</html>