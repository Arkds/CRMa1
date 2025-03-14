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
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['user_id'], $_POST['shift'], $_POST['checked'])) {
    $user_id = $_POST['user_id'];
    $shift = $_POST['shift'];
    $checked = $_POST['checked'];

    if ($checked == 1) {
        // Agregar turno si no existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_shifts WHERE user_id = ? AND shift = ?");
        $stmt->execute([$user_id, $shift]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $stmt = $pdo->prepare("INSERT INTO user_shifts (user_id, shift) VALUES (?, ?)");
            $stmt->execute([$user_id, $shift]);
        }
    } else {
        // Eliminar turno si el checkbox se desmarca
        $stmt = $pdo->prepare("DELETE FROM user_shifts WHERE user_id = ? AND shift = ?");
        $stmt->execute([$user_id, $shift]);
    }

    echo "Horario actualizado";
    exit;
}


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
include('header.php')

    ?>

<div class="container mt-5">
    <h1 class="text-center">Gestión de Usuarios</h1>
    <button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>
    <button class="btn btn-primary mb-3" onclick="window.location.replace('user_crud.php?action=create');">Agregar
        Usuariob</button>
    <!-- Tabla de Usuarios -->
    <table id="usersTable" class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Fecha de Creación</th>
                <th>Turno Mañana</th>
                <th>Turno Tarde</th>
                <th>Turno Noche 1</th>
                <th>Turno Noche 2</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <?php
                // Obtener los turnos asignados al usuario
                $stmt = $pdo->prepare("SELECT shift FROM user_shifts WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $user_shifts = $stmt->fetchAll(PDO::FETCH_COLUMN);
                ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= $user['username'] ?></td>
                    <td><?= $user['role'] ?></td>
                    <td><?= $user['created_at'] ?></td>
                    <td>
                        <input type="checkbox" class="shift-checkbox" data-user="<?= $user['id'] ?>" value="mañana"
                            <?= in_array('mañana', $user_shifts) ? 'checked' : '' ?>>
                    </td>
                    <td>
                        <input type="checkbox" class="shift-checkbox" data-user="<?= $user['id'] ?>" value="tarde"
                            <?= in_array('tarde', $user_shifts) ? 'checked' : '' ?>>
                    </td>
                    <td>
                        <input type="checkbox" class="shift-checkbox" data-user="<?= $user['id'] ?>" value="noche_1"
                            <?= in_array('noche_1', $user_shifts) ? 'checked' : '' ?>>
                    </td>
                    <td>
                        <input type="checkbox" class="shift-checkbox" data-user="<?= $user['id'] ?>" value="noche_2"
                            <?= in_array('noche_2', $user_shifts) ? 'checked' : '' ?>>
                    </td>
                    <td>
                        <a href="user_crud.php?action=edit&id=<?= $user['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                        <a href="user_crud.php?action=delete&id=<?= $user['id'] ?>"
                            class="btn btn-danger btn-sm">Eliminar</a>
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
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".shift-checkbox").forEach(checkbox => {
            checkbox.addEventListener("change", function () {
                let userId = this.getAttribute("data-user");
                let shift = this.value;
                let checked = this.checked ? 1 : 0;

                fetch("user_crud.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `user_id=${userId}&shift=${shift}&checked=${checked}`
                }).then(response => response.text())
                    .then(data => console.log(data))
                    .catch(error => console.error("Error:", error));
            });
        });
    });
</script>

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
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"></script>
</body>

</html>