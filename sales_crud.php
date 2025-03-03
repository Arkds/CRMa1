<?php
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



$isAdmin = isset($role) && $role === 'admin';

// Consultar productos disponibles
$productsQuery = "SELECT name FROM products";
$stmt = $pdo->query($productsQuery);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Acción actual
$action = $_GET['action'] ?? 'create'; // Por defecto, la acción será 'create'
$id = $_GET['id'] ?? null;

// Registrar o editar venta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = $_POST['product_name'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO sales (product_name, price, quantity, user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$productName, $price, $quantity, $user_id]);
        setcookie('success_message', "¡La venta de '$productName' se registró correctamente!", time() + 5, "/");
        header('Location: sales_crud.php');
        exit;
    }

    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare("UPDATE sales SET product_name = ?, price = ?, quantity = ? WHERE id = ?");
        $stmt->execute([$productName, $price, $quantity, $id]);
        setcookie('success_message', "¡La venta de '$productName' se registró correctamente!", time() + 5, "/");

        header('Location: sales_crud.php');
        exit;
    }
}

// Consultar ventas
$salesQuery = $isAdmin
    ? "SELECT s.*, u.username FROM sales s JOIN users u ON s.user_id = u.id ORDER BY created_at DESC"
    : "SELECT * FROM sales WHERE user_id = ? ORDER BY created_at DESC";

$stmt = $pdo->prepare($salesQuery);
if (!$isAdmin) {
    $stmt->execute([$user_id]);
} else {
    $stmt->execute();
}

$sales = $stmt->fetchAll();

// Si está en modo edición, obtener datos de la venta
$saleToEdit = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    $saleToEdit = $stmt->fetch();
}
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
    <title>Gestión de Ventas</title>
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
                    <li>Registra ventas ingresando producto (por defecto ProductoA, precio en pesos mexicanos y cantidad (por defecto 1).</li>
                    <li>Si eres vendedor solo tienes acceso de editar tus registros.</li>
                    <li>Puedes editar tus productos, el boton verde cambiara a "Actualizar", cuidado con eso.</li>
                    <li>Utiliza la barra de búsqueda para encontrar ventas específicas rápidamente.</li> 
                </ol>
            `;
                    appendAlert(message, 'success');
                })
            }
        </script>
        <h1 class="text-center">Gestión de Ventas</h1>
        <button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>
        <button class="btn btn-outline-primary mb-3" onclick="window.location.replace('commissions_crud.php');">Comisiones</button>

        


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
        <form method="POST" class="mb-4" action="sales_crud.php?action=<?= $action ?><?= $id ? '&id=' . $id : '' ?>">
            <div class="row">
                <div class="col-md-4">
                    <label for="product_name" class="form-label">Producto</label>
                    <input type="text" class="form-control" id="product_name" name="product_name" list="productList"
                        value="<?= $saleToEdit['product_name'] ?? 'ProductoA ' ?>" required>
                    <datalist id="productList">
                        <?php foreach ($products as $product): ?>
                            <option value="<?= htmlspecialchars($product['name']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label for="price" class="form-label">Precio</label>
                    <input type="number" step="0.01" class="form-control" id="price" name="price"
                        value="<?= $saleToEdit['price'] ?? '' ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="quantity" class="form-label">Cantidad</label>
                    <input type="number" class="form-control" id="quantity" name="quantity"
                        value="<?= $saleToEdit['quantity'] ?? '1' ?>" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">
                        <?= $action === 'edit' ? 'Actualizar' : 'Guardar' ?>
                    </button>
                </div>
            </div>
        </form>

        <!-- Tabla de ventas -->
        <h2>Ventas Registradas</h2>
        <table id="salesTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Cantidad</th>
                    <th>Total</th>
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
                        <td><?= htmlspecialchars($sale['price']) ?></td>
                        <td><?= htmlspecialchars($sale['quantity']) ?></td>
                        <td><?= number_format($sale['price'] * $sale['quantity'], 2) ?></td>
                        <td><?= $isAdmin ? htmlspecialchars($sale['username']) : htmlspecialchars($username) ?></td>
                        <td><?= htmlspecialchars($sale['created_at']) ?></td>
                        <td>
                            <a href="sales_crud.php?action=edit&id=<?= $sale['id'] ?>"
                                class="btn btn-warning btn-sm">Editar</a>
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
                order: [[6, 'desc']],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>

</html>