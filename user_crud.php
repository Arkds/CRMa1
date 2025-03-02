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

// Verificar si el usuario es administrador
if ($role !== 'admin') {
    header('Location: index.php');
    exit;
}


// Acción actual
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Hash de la contraseña
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $passwordHash, $role]);
        header('Location: user_crud.php');
        exit;
    }

    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
        $stmt->execute([$username, $passwordHash, $role, $id]);
        header('Location: user_crud.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: user_crud.php');
    exit;
}

// Obtener lista de usuarios
$users = $pdo->query("SELECT id, username, role, created_at FROM users")->fetchAll();
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
    <title>Gestión de Usuarios</title>
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">Gestión de Usuarios</h1>
    <a href="index.php" class="btn btn-secondary mb-3">Volver</a>
    <a href="user_crud.php?action=create" class="btn btn-primary mb-3">Agregar Usuario</a>

    <!-- Tabla de Usuarios -->
    <table id="usersTable" class="table table-striped">
        <thead>
        <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Fecha de Creación</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= $user['username'] ?></td>
                <td><?= $user['role'] ?></td>
                <td><?= $user['created_at'] ?></td>
                <td>
                    <a href="user_crud.php?action=edit&id=<?= $user['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                    <a href="user_crud.php?action=delete&id=<?= $user['id'] ?>" class="btn btn-danger btn-sm">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Formulario de Crear/Editar Usuario -->
    <?php if ($action === 'create' || ($action === 'edit' && $id)): ?>
        <?php
        $user = ['username' => '', 'role' => ''];
        if ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
        }
        ?>
        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label for="username" class="form-label">Usuario</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Rol</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="vendedor" <?= $user['role'] === 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Guardar</button>
        </form>
    <?php endif; ?>
</div>

<!-- Inicializar DataTables -->
<script>
    $(document).ready(function () {
        $('#usersTable').DataTable({
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
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></script>
</body>
</html>
