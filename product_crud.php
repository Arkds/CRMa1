<?php

require 'db.php';

if (isset($_COOKIE['user_session'])) {
    // Decodificar la cookie
    $user_data = json_decode(base64_decode($_COOKIE['user_session']), true);

    if ($user_data) {
        $user_id = $user_data['user_id'];
        $username = $user_data['username'];
        $role = $user_data['role'];

        // Restringir acceso si el usuario no es admin
        if ($role !== 'admin') {
            header("Location: index.php"); // Redirigir a otra página
            exit;
        }
    } else {
        // Si la cookie está corrupta, redirigir al login
        header("Location: login.php");
        exit;
    }
} else {
    // Si no hay sesión, redirigir al login
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
    $syllabus = $_POST['syllabus'];
    $relevance = isset($_POST['relevance']) ? 1 : 0;
    $related_products = isset($_POST['related_products']) ? explode(',', $_POST['related_products']) : [];

    if ($id) {
        $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, description = ?, syllabus = ?, relevance = ? WHERE id = ?");
        $stmt->execute([$name, $price, $description, $syllabus, $relevance, $id]);

        // Actualizar relaciones de productos
        $pdo->prepare("DELETE FROM product_relations WHERE product_id = ?")->execute([$id]);

        foreach ($related_products as $related_id) {
            $pdo->prepare("INSERT INTO product_relations (product_id, related_product_id) VALUES (?, ?)")
                ->execute([$id, $related_id]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, price, description, syllabus, relevance) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $description, $syllabus, $relevance]);
        $product_id = $pdo->lastInsertId();

        foreach ($related_products as $related_id) {
            $pdo->prepare("INSERT INTO product_relations (product_id, related_product_id) VALUES (?, ?)")
                ->execute([$product_id, $related_id]);
        }
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
        // Obtener los productos relacionados
        $stmt = $pdo->prepare("SELECT related_product_id FROM product_relations WHERE product_id = ?");
        $stmt->execute([$id]);
        $related_products = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $product['related_products'] = $related_products;
        echo json_encode($product);
    } else {
        echo json_encode(['error' => 'Producto no encontrado']);
    }
    exit;
}


$products = $pdo->query("SELECT * FROM products")->fetchAll();
include('header.php')

    ?>

<!-- Estilos de Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Script de Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>

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
                <th>Temario</th>
                <th>Productos Relacionados</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= $product['id'] ?></td>
                    <td><?= $product['name'] ?></td>
                    <td><?= $product['price'] !== null ? $product['price'] : 'No especificado' ?></td>
                    <td>
                        <div class="text-truncate" style="max-width: 150px; cursor:pointer;" onclick="toggleExpand(this)">
                            <?= htmlspecialchars($product['description']) ?>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate temario-content" style="max-width: 150px; cursor:pointer;"
                            onclick="toggleExpand(this)">
                            <?= htmlspecialchars($product['syllabus']) ?>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 150px; cursor:pointer;" onclick="toggleExpand(this)">
                            <?php
                            $stmt = $pdo->prepare("SELECT p.name FROM products p JOIN product_relations r ON p.id = r.related_product_id WHERE r.product_id = ?");
                            $stmt->execute([$product['id']]);
                            $related_products = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            echo htmlspecialchars(implode(", ", $related_products)) ?: 'No relacionados';
                            ?>
                        </div>
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

                        <div class="mb-3">
                            <label for="syllabus" class="form-label">Temario</label>
                            <textarea class="form-control temario-content" id="syllabus" name="syllabus"
                                rows="5"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="related_products_input" class="form-label">Productos Relacionados</label>
                            <input type="text" class="form-control" id="related_products_input" list="productList"
                                placeholder="Escribe y selecciona productos...">
                            <datalist id="productList">
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= htmlspecialchars($p['name']) ?>" data-id="<?= $p['id'] ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div id="selectedProducts" class="mt-2"></div>
                            <input type="hidden" id="related_products" name="related_products">
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
        document.addEventListener('DOMContentLoaded', function () {
            let selectedProducts = [];

            // Agregar producto relacionado al escribir en el input
            document.getElementById('related_products_input').addEventListener('change', function () {
                let input = this;
                let datalist = document.getElementById('productList').options;
                let selectedProduct = null;

                // Buscar el ID del producto seleccionado
                for (let option of datalist) {
                    if (option.value === input.value) {
                        selectedProduct = { id: option.getAttribute('data-id'), name: option.value };
                        break;
                    }
                }

                if (selectedProduct && !selectedProducts.find(p => p.id === selectedProduct.id)) {
                    selectedProducts.push(selectedProduct);
                    updateSelectedProducts();
                    input.value = ''; // Limpiar el input
                }
            });

            // Mostrar productos seleccionados
            function updateSelectedProducts() {
                let container = document.getElementById('selectedProducts');
                container.innerHTML = '';

                selectedProducts.forEach(product => {
                    let badge = document.createElement('span');
                    badge.classList.add('badge', 'bg-primary', 'me-1');
                    badge.textContent = product.name + ' ×';
                    badge.style.cursor = 'pointer';

                    badge.addEventListener('click', function () {
                        selectedProducts = selectedProducts.filter(p => p.id !== product.id);
                        updateSelectedProducts();
                    });

                    container.appendChild(badge);
                });

                document.getElementById('related_products').value = selectedProducts.map(p => p.id).join(',');
            }

            // Al abrir el modal para editar, recuperar productos relacionados
            window.openProductModal = function (id = null) {
                if (id) {
                    $.ajax({
                        url: 'product_crud.php?action=get&id=' + id,
                        method: 'GET',
                        dataType: 'json',
                        success: function (product) {
                            $('#productModalLabel').text('Editar Producto');
                            $('#product_id').val(product.id);
                            $('#name').val(product.name);
                            $('#price').val(product.price);
                            $('#description').val(product.description);
                            $('#syllabus').val(product.syllabus);
                            $('#relevance').prop('checked', product.relevance == 1);

                            selectedProducts = product.related_products.map(id => {
                                let name = $('#productList option[data-id="' + id + '"]').val();
                                return { id, name };
                            });

                            updateSelectedProducts();
                            $('#productModal').modal('show');
                        }
                    });
                } else {
                    $('#productModalLabel').text('Agregar Producto');
                    $('#product_id').val('');
                    $('#name').val('');
                    $('#price').val('');
                    $('#description').val('');
                    $('#syllabus').val('');
                    $('#relevance').prop('checked', false);
                    selectedProducts = [];
                    updateSelectedProducts();
                    $('#productModal').modal('show');
                }
            };
        });

    </script>

    <script>
        $(document).ready(function () {
            if ($.fn.DataTable.isDataTable("#productsTable")) {
                $("#productsTable").DataTable().destroy(); // Destruye la instancia previa
            }

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
    <script>
        function toggleExpand(element) {
            if (element.style.whiteSpace === "normal") {
                element.style.whiteSpace = "nowrap";
                element.style.overflow = "hidden";
                element.style.textOverflow = "ellipsis";
                element.style.maxWidth = "150px";
            } else {
                element.style.whiteSpace = "normal";
                element.style.maxWidth = "none";
            }
        }



    </script>
    </body>

    </html>