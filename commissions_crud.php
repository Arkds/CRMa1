<?php
require 'db.php';

// Verificar sesión
if (!isset($_COOKIE['user_session'])) {
    header("Location: login.php");
    exit;
}

// Decodificar cookie
$user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
$user_id = $user_data['user_id'];
$username = $user_data['username'];
$role = $user_data['role'];
$isAdmin = ($role === 'admin');

$stmt = $pdo->prepare("SELECT drive_folder FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_drive = $stmt->fetch();
$drive_folder = $user_drive['drive_folder'] ?? null;

$stmt = $pdo->prepare("INSERT INTO commissions (product_name, price, channel, operation_number, user_id) VALUES (?, ?, ?, ?, ?)");
$commission_id = $pdo->lastInsertId(); 

if (!empty($links)) {
    foreach ($links as $link) {
        if (!empty($link)) {
            $stmt = $pdo->prepare("INSERT INTO commission_files (commission_id, file_link) VALUES (?, ?)");
            $stmt->execute([$commission_id, $link]);
        }
    }
}

if ($commission_id) {
    $stmt = $pdo->prepare("DELETE FROM commission_files WHERE commission_id = ?");
    $stmt->execute([$commission_id]);

    foreach ($links as $link) {
        if (!empty($link)) {
            $stmt = $pdo->prepare("INSERT INTO commission_files (commission_id, file_link) VALUES (?, ?)");
            $stmt->execute([$commission_id, $link]);
        }
    }
}


// Registrar o Editar Comisión

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['product_name'] ?? null;
    $price = $_POST['price'] ?? null;
    $channel = $_POST['channel'] ?? null;
    $operation_number = $_POST['operation_number'] ?? null;
    $description = $_POST['description'] ?? null;

    $links = $_POST['links'] ?? []; 
    $commission_id = $_POST['commission_id'] ?? null;

    if (empty($product_name) || empty($price) || empty($channel) || empty($operation_number)) {
        die("Error: Todos los campos son obligatorios.");
    }

    if ($commission_id) {
        $description = $_POST['description'] ?? null;

        $query = $isAdmin
            ? "UPDATE commissions SET product_name=?, price=?, channel=?, operation_number=?, description=? WHERE id=?"
            : "UPDATE commissions SET product_name=?, price=?, channel=?, operation_number=?, description=? WHERE id=? AND user_id=?";

        $params = $isAdmin
            ? [$product_name, $price, $channel, $operation_number, $description, $commission_id]
            : [$product_name, $price, $channel, $operation_number, $description, $commission_id, $user_id];

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        setcookie('success_message', "¡Comisión actualizada correctamente!", time() + 5, "/");
    } else {
        $stmt = $pdo->prepare("INSERT INTO commissions (product_name, price, channel, operation_number, description, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$product_name, $price, $channel, $operation_number, $description, $user_id]);

        $commission_id = $pdo->lastInsertId(); 

        setcookie('success_message', "¡Comisión registrada correctamente!", time() + 5, "/");
    }

    // Si hay un ID de comisión, proceder con los comprobantes
    if ($commission_id) {
        $stmt = $pdo->prepare("DELETE FROM commission_files WHERE commission_id = ?");
        $stmt->execute([$commission_id]);

        foreach ($links as $link) {
            if (!empty($link)) {
                $stmt = $pdo->prepare("INSERT INTO commission_files (commission_id, file_link) VALUES (?, ?)");
                $stmt->execute([$commission_id, $link]);
            }
        }
    }

    header('Location: commissions_crud.php');
    exit;
}

if (isset($_GET['delete']) && $isAdmin) {
    $stmt = $pdo->prepare("DELETE FROM commissions WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    setcookie('success_message', "¡Comisión eliminada!", time() + 5, "/");
    header('Location: commissions_crud.php');
    exit;
}

// Consultar comisiones
$commissionsQuery = $isAdmin
    ? "SELECT c.*, u.username FROM commissions c JOIN users u ON c.user_id = u.id ORDER BY created_at DESC"
    : "SELECT * FROM commissions WHERE user_id = ? ORDER BY created_at DESC";

$stmt = $pdo->prepare($commissionsQuery);
$isAdmin ? $stmt->execute() : $stmt->execute([$user_id]);
$commissions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Comisiones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>

<body>
    <div class="container mt-5">
        <h1 class="text-center">Gestión de Comisiones</h1>
        <button class="btn btn-secondary mb-3" onclick="window.location.replace('sales_crud.php');">Volver</button>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#commissionModal">Nueva
            Comisión</button>

        <?php if (!empty($drive_folder)): ?>
            <button class="btn btn-info mb-3" onclick="window.open('<?= htmlspecialchars($drive_folder) ?>', '_blank');">
                📁 Carpeta de Comprobantes
            </button>

        <?php else: ?>
            <button class="btn btn-secondary mb-3" disabled>📁 Carpeta no asignada</button>
        <?php endif; ?>

        <?php if (isset($_COOKIE['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_COOKIE['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php setcookie('success_message', '', time() - 3600, "/"); ?>
        <?php endif; ?>

        <table class="table table-striped" id="table1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Canal</th>
                    <th>Número de Operación</th>
                    <th>Comprobante</th>
                    <th>Descripción</th>

                    <th>Usuario</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commissions as $commission): ?>
                    <tr>
                        <td><?= $commission['id'] ?></td>
                        <td><?= htmlspecialchars($commission['product_name']) ?></td>
                        <td><?= htmlspecialchars($commission['price']) ?></td>
                        <td><?= htmlspecialchars($commission['channel']) ?></td>
                        <td><?= htmlspecialchars($commission['operation_number']) ?></td>
                        

                        <td>
                            <?php
                            $stmt = $pdo->prepare("SELECT file_link FROM commission_files WHERE commission_id = ?");
                            $stmt->execute([$commission['id']]);
                            $comprobantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (count($comprobantes) > 0):
                                $index = 1; // Se inicializa el contador
                                foreach ($comprobantes as $comprobante): ?>
                                    <a href="<?= htmlspecialchars($comprobante['file_link']) ?>" target="_blank"
                                        class="btn btn-dark btn-sm mb-1">
                                        📄 Captura <?= $index ?> <!-- Ahora el número se genera correctamente -->
                                    </a>
                                    <?php
                                    $index++; // Incrementa el contador
                                endforeach;
                            else: ?>
                                <span class="text-muted">Sin comprobantes</span>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($commission['description']) ? htmlspecialchars($commission['description']) : '<span class="text-muted">Sin descripción</span>'; ?>
                        </td>


                        <td><?= $isAdmin ? htmlspecialchars($commission['username']) : htmlspecialchars($username) ?></td>
                        <td><?= htmlspecialchars($commission['created_at']) ?></td>
                        <td>
                            <?php
                            $stmt = $pdo->prepare("SELECT file_link FROM commission_files WHERE commission_id = ?");
                            $stmt->execute([$commission['id']]);
                            $comprobantes = $stmt->fetchAll(PDO::FETCH_COLUMN); // Obtener solo los valores (enlaces)
                        
                            $comprobantes_json = htmlspecialchars(json_encode($comprobantes), ENT_QUOTES);
                            ?>

                            <button class="btn btn-warning btn-sm " data-bs-toggle="modal" data-bs-target="#commissionModal"
                                onclick='editCommission(
        "<?= $commission['id'] ?>",
        "<?= htmlspecialchars($commission['product_name'], ENT_QUOTES) ?>",
        "<?= $commission['price'] ?>",
        "<?= htmlspecialchars($commission['channel'], ENT_QUOTES) ?>",
        "<?= htmlspecialchars($commission['operation_number'], ENT_QUOTES) ?>",
        "<?= isset($commission['description']) ? htmlspecialchars($commission['description'], ENT_QUOTES) : '' ?>",
        <?= json_encode($comprobantes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    )'>
                                Editar
                            </button>


                            <?php if ($isAdmin): ?>
                                <a href="commissions_crud.php?delete=<?= $commission['id'] ?>"
                                    class="btn btn-danger btn-sm">Eliminar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal para agregar/editar comisión -->
    <div class="modal fade" id="commissionModal" tabindex="-1" aria-labelledby="commissionModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="commissionModalLabel">Registrar Comisión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" id="commission_id" name="commission_id">
                        <div class="mb-3">
                            <label for="product_name" class="form-label">Producto</label>
                            <input type="text" id="product_name" name="product_name" class="form-control" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label for="price" class="form-label">Precio</label>
                            <input type="number" step="0.01" id="price" name="price" class="form-control" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label for="channel" class="form-label">Canal</label>
                            <input type="text" id="channel" name="channel" class="form-control" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label for="operation_number" class="form-label">Número de Operación</label>
                            <input type="text" id="operation_number" name="operation_number" class="form-control"
                                required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label for="link" class="form-label">Comprobantes (Enlaces de Google Drive)</label>
                            <div id="comprobantesContainer">
                                <input type="url" name="links[]" class="form-control mb-2"
                                    placeholder="Pega el enlace del comprobante">
                            </div>
                            <hr>
                            <button type="button" class="btn btn-secondary btn-sm"
                                onclick="agregarComprobante()">Agregar más enlaces</button>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción (opcional)</label>
                            <textarea id="description" name="description" class="form-control" rows="2"
                                placeholder="Escribe una descripción..."></textarea>
                        </div>


                        <button type="submit" class="btn btn-success w-100">Guardar Comisión</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <script>
        function editCommission(id, productName, price, channel, operationNumber, description, links) {
            document.getElementById("commission_id").value = id;
            document.getElementById("product_name").value = productName;
            document.getElementById("price").value = price;
            document.getElementById("channel").value = channel;
            document.getElementById("operation_number").value = operationNumber;

            document.getElementById("description").value = description && description !== "null" ? description : "";

            document.querySelector("#commissionModal .modal-title").textContent = "Editar Comisión";

            let container = document.getElementById("comprobantesContainer");
            container.innerHTML = '';

            if (typeof links === "string") {
                try {
                    links = JSON.parse(links);
                } catch (error) {
                    links = [];
                }
            }

            if (!Array.isArray(links)) {
                links = [];
            }

            if (links.length > 0) {
                links.forEach(link => {
                    let input = document.createElement("input");
                    input.type = "url";
                    input.name = "links[]";
                    input.value = link;
                    input.classList.add("form-control", "mb-2");
                    container.appendChild(input);
                });
            } else {
                let input = document.createElement("input");
                input.type = "url";
                input.name = "links[]";
                input.classList.add("form-control", "mb-2");
                input.placeholder = "Pega el enlace del comprobante";
                container.appendChild(input);
            }
        }

    </script>
    <script>
        function agregarComprobante() {
            let container = document.getElementById("comprobantesContainer");
            let input = document.createElement("input");
            input.type = "url";
            input.name = "links[]";
            input.classList.add("form-control", "mb-2");
            input.placeholder = "Pega otro enlace de comprobante";
            container.appendChild(input);
        }
    </script>
    <script>
        $(document).ready(function () {
            $('#table1').DataTable({
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