<?php

require 'db.php';

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

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'];
    $price = $_POST['price'] ?? null;
    $description = $_POST['description'];
    $relevance = isset($_POST['relevance']) ? 1 : 0;

    if ($id) {
        // Editar producto
        $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, description = ?, relevance = ? WHERE id = ?");
        $stmt->execute([$name, $price, $description, $relevance, $id]);
    } else {
        // Crear producto
        $stmt = $pdo->prepare("INSERT INTO products (name, price, description, relevance) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $price, $description, $relevance]);
    }

    echo json_encode(['success' => true]);
    exit;
}


if ($action === 'delete' && $id) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: product_crud.php');
    exit;
}
if ($action === 'get' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        echo json_encode($product);
    } else {
        echo json_encode(['error' => 'Producto no encontrado']);
    }
    exit;
}


$products = $pdo->query("SELECT * FROM products")->fetchAll();
include('header.php')

?>

    <div class="container mt-5">
        <h1 class="text-center">Gestión de Productos</h1>
        <button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>
        <!-- Botón para agregar un nuevo producto -->
        <button class="btn btn-primary mb-3" onclick="openProductModal()">Agregar Producto</button>

        <!-- En la tabla, modificar el botón de editar -->


        <!-- Tabla de productos -->
        <table id="productsTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Precio</th>
                    <th>Descripción</th>
                    <th>Relevante</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= $product['id'] ?></td>
                        <td><?= $product['name'] ?></td>
                        <td><?= $product['price'] !== null ? $product['price'] : 'No especificado' ?></td>
                        <td><?= $product['description'] ?></td>
                        <td>
                            <?= $product['relevance'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?>
                        </td>

                        <td>
                            <button class="btn btn-warning btn-sm"
                                onclick="openProductModal(<?= $product['id'] ?>)">Editar</button>

                            <button class="btn btn-danger btn-sm"
                                onclick="window.location.replace('product_crud.php?action=delete&id=<?= $product['id'] ?>');">Eliminar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>


        <!-- Modal para Agregar/Editar Productos -->
        <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="productModalLabel">Agregar Producto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="productForm">
                            <input type="hidden" id="product_id" name="id">

                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label for="price" class="form-label">Precio</label>
                                <input type="number" step="0.01" class="form-control" id="price" name="price">
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="relevance" name="relevance">
                                <label class="form-check-label" for="relevance">Relevante</label>
                            </div>

                            <button type="submit" class="btn btn-success">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Inicialización de DataTables -->
    <script>
        function openProductModal(id = null) {
            if (id) {
                $.ajax({
                    url: 'product_crud.php?action=get&id=' + id,
                    method: 'GET',
                    dataType: 'json',
                    success: function (product) {
                        if (product.error) {
                            alert('Error: ' + product.error);
                        } else {
                            $('#productModalLabel').text('Editar Producto');
                            $('#product_id').val(product.id);
                            $('#name').val(product.name);
                            $('#price').val(product.price);
                            $('#description').val(product.description);
                            $('#relevance').prop('checked', product.relevance == 1);
                            $('#productModal').modal('show');
                        }
                    },
                    error: function () {
                        alert('Error al recuperar los datos del producto.');
                    }
                });
            } else {
                // Limpiar el formulario para agregar un nuevo producto
                $('#productModalLabel').text('Agregar Producto');
                $('#product_id').val('');
                $('#name').val('');
                $('#price').val('');
                $('#description').val('');
                $('#relevance').prop('checked', false);
                $('#productModal').modal('show');
            }
        }


        $('#productForm').submit(function (event) {
            event.preventDefault();
            let formData = $(this).serialize();

            $.post('product_crud.php?action=save', formData, function (response) {
                location.reload();
            });
        });
    </script>

    <script>
        $(document).ready(function () {
            $('#productsTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
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