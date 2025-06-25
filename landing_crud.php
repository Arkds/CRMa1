<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// Validación de sesión
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

// Guardar landing
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $product_id = $_POST['product_id'];
    $campania = $_POST['campaña'];
    $vendedor_id = $_POST['vendedor_id'] ?? null;
    $vendedor_id = $_POST['vendedor_id'] ?? null;
    if (empty($vendedor_id)) {
        $vendedor_id = null;
    }

    $slug = $_POST['slug'];
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $url_pago = $_POST['url_pago'];
    $url_whatsapp = $_POST['url_whatsapp'];
    $imagen_destacada = $_POST['imagen_destacada'];

    try {
        // Verificar si slug ya existe (para nuevo o update)
        $stmt_check = $pdo->prepare("SELECT id FROM landing_pages WHERE slug = ? AND id != ?");
        $stmt_check->execute([$slug, $id ?? 0]);

        if ($stmt_check->fetch()) {
            echo json_encode(['error' => 'El slug ya está en uso, elige otro.']);
            exit;
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE landing_pages SET product_id = ?, `campaña` = ?, vendedor_id = ?, slug = ?, titulo = ?, descripcion = ?, imagen_destacada = ?, precio = ?, url_pago = ?, url_whatsapp = ? WHERE id = ?");
            $stmt->execute([$product_id, $campania, $vendedor_id, $slug, $titulo, $descripcion, $imagen_destacada, $precio, $url_pago, $url_whatsapp, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO landing_pages (product_id, `campaña`, vendedor_id, slug, titulo, descripcion, imagen_destacada, precio, url_pago, url_whatsapp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $campania, $vendedor_id, $slug, $titulo, $descripcion, $imagen_destacada, $precio, $url_pago, $url_whatsapp]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }

    exit;
}



// Eliminar landing
if ($action === 'delete' && $id) {
    $stmt = $pdo->prepare("DELETE FROM landing_pages WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: landing_crud.php');
    exit;
}

// Obtener landing
if ($action === 'get' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM landing_pages WHERE id = ?");
    $stmt->execute([$id]);
    $landing = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($landing ?: ['error' => 'Landing no encontrada']);
    unset($stmt);
    unset($pdo);
    exit;
}

// Obtener datos para dropdowns
$products = $pdo->query("SELECT * FROM products")->fetchAll();
$users = $pdo->query("SELECT * FROM users WHERE role = 'vendedor'")->fetchAll();
$landings = $pdo->query("SELECT l.*, p.name AS product_name, u.username AS vendedor_name 
                         FROM landing_pages l 
                         LEFT JOIN products p ON l.product_id = p.id 
                         LEFT JOIN users u ON l.vendedor_id = u.id 
                         ORDER BY l.id DESC")->fetchAll();

include('header.php');
?>

<div class="container mt-5">
    <h1 class="text-center">Gestión de Landing Pages</h1>
    <button class="btn btn-primary mb-3" onclick="openLandingModal()">Crear Landing Page</button>

    <table id="landingTable" class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Producto</th>
                <th>Campaña</th>
                <th>Vendedor</th>
                <th>Slug</th>
                <th>URL</th>
                <th>Clicks</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($landings as $landing): ?>
                <tr>
                    <td><?= $landing['id'] ?></td>
                    <td><?= htmlspecialchars($landing['product_name']) ?></td>
                    <td><?= htmlspecialchars($landing['campaña']) ?></td>
                    <td><?= htmlspecialchars($landing['vendedor_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($landing['slug']) ?></td>
                    <td>
                        <input type="text" readonly class="form-control form-control-sm"
                            value="https://landing.tudominio.com/<?= htmlspecialchars($landing['slug']) ?>"
                            onclick="this.select();document.execCommand('copy');">
                    </td>
                    <td><?= $landing['clicks_total'] ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm"
                            onclick="openLandingModal(<?= $landing['id'] ?>)">Editar</button>
                        <button class="btn btn-danger btn-sm"
                            onclick="window.location.replace('landing_crud.php?action=delete&id=<?= $landing['id'] ?>');">Eliminar</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="landingModal" tabindex="-1" aria-labelledby="landingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Landing Page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="landingForm" method="POST">

                    <input type="hidden" name="id" id="landing_id">

                    <div class="mb-3">
                        <label>Producto</label>
                        <select name="product_id" id="product_id" class="form-select" required>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Campaña</label>
                        <input type="text" name="campaña" id="campaña" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>Vendedor</label>
                        <select name="vendedor_id" id="vendedor_id" class="form-select">
                            <option value="">(Opcional)</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Slug (URL)</label>
                        <input type="text" name="slug" id="slug" class="form-control" required>
                        <small class="form-text text-muted">Ejemplo: curso-rcp-fb-junio</small>
                    </div>

                    <div class="mb-3">
                        <label>Título</label>
                        <input type="text" name="titulo" id="titulo" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>Descripción</label>
                        <textarea name="descripcion" id="descripcion" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label>Imagen destacada (URL completa)</label>
                        <input type="text" name="imagen_destacada" id="imagen_destacada" class="form-control">
                        <small class="form-text text-muted">Ej: https://tuimagen.com/banner.jpg</small>
                    </div>

                    <div class="mb-3">
                        <label>Precio</label>
                        <input type="number" step="0.01" name="precio" id="precio" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>URL de Pago</label>
                        <input type="text" name="url_pago" id="url_pago" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>URL de WhatsApp</label>
                        <input type="text" name="url_whatsapp" id="url_whatsapp" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-success">Guardar Landing</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openLandingModal(id = null) {
        if (id) {
            $.get('landing_crud.php?action=get&id=' + id, function (landing) {
                $('#landingModal .modal-title').text('Editar Landing');
                $('#landing_id').val(landing.id);
                $('#product_id').val(landing.product_id);
                $('#campaña').val(landing.campaña);
                $('#vendedor_id').val(landing.vendedor_id);
                $('#slug').val(landing.slug);
                $('#titulo').val(landing.titulo);
                $('#descripcion').val(landing.descripcion);
                $('#precio').val(landing.precio);
                $('#url_pago').val(landing.url_pago);
                $('#url_whatsapp').val(landing.url_whatsapp);
                // 🚨 FALTA ESTA LINEA:
                $('#imagen_destacada').val(landing.imagen_destacada);
                $('#landingModal').modal('show');
            });

        } else {
            $('#landingModal .modal-title').text('Crear Landing Page');
            $('#landingForm')[0].reset();
            $('#landing_id').val('');
            $('#landingModal').modal('show');
        }
    }

    $('#landingForm').submit(function (e) {
        e.preventDefault();
        $.post('landing_crud.php?action=save', $(this).serialize(), function (response) {
            console.log(response); // 🚀 para ver si falla
            try {
                const r = JSON.parse(response);
                if (r.success) {
                    location.reload();
                } else {
                    alert('Error al guardar: ' + r.error);
                }
            } catch (e) {
                alert('Respuesta inesperada: ' + response);
            }
        });
    });


    $(document).ready(function () {
        $('#landingTable').DataTable({
            order: [[0, 'desc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            }
        });
    });
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php
if (isset($stmt))
    unset($stmt);
unset($pdo);
?>