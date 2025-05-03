<?php
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

// Verificar si el usuario es administrador
if ($role !== 'admin') {
    header('Location: index.php');
    exit;
}


// En la parte superior del archivo, después de la verificación de admin
$turnos_disponibles = [
    'mañana_completo' => 'Mañana Completo (8am-5pm)',
    'mañana_parcial' => 'Mañana Parcial (8am-2pm)',
    'tarde' => 'Tarde (2pm-8pm)',
    'noche' => 'Noche (5pm-11pm)',
    'mixto' => 'Mixto (Horarios variables)'
];
// Acción actual
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['user_id'], $_POST['shift'], $_POST['action_shift'])) {
    $user_id = $_POST['user_id'];
    $shift = $_POST['shift'];

    if ($_POST['action_shift'] === 'add') {
        // Verificar si el turno ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_shifts WHERE user_id = ? AND shift = ?");
        $stmt->execute([$user_id, $shift]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $stmt = $pdo->prepare("INSERT INTO user_shifts (user_id, shift) VALUES (?, ?)");
            $stmt->execute([$user_id, $shift]);
        }
    } elseif ($_POST['action_shift'] === 'remove') {
        $stmt = $pdo->prepare("DELETE FROM user_shifts WHERE user_id = ? AND shift = ?");
        $stmt->execute([$user_id, $shift]);
    }

    echo json_encode(['status' => 'success']);
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
// Obtener todos los turnos de todos los usuarios en una sola consulta
$stmt = $pdo->query("SELECT user_id, shift FROM user_shifts");
$user_turnos = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $user_turnos[$row['user_id']][] = $row['shift'];
}


foreach ($users as $user) {
    if (isset($asignaciones_automaticas[$user['username']])) {
        foreach ($asignaciones_automaticas[$user['username']] as $shift) {
            // Verificar si ya está asignado
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_shifts WHERE user_id = ? AND shift = ?");
            $stmt->execute([$user['id'], $shift]);
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                $stmt = $pdo->prepare("INSERT INTO user_shifts (user_id, shift) VALUES (?, ?)");
                $stmt->execute([$user['id'], $shift]);
            }
        }
    }
}
include('header.php')

    ?>

<div class="container mt-5">
    <h1 class="text-center">Gestión de Usuarios</h1>
    <!--<button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>-->
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
                <th>Turnos Asignados</th>
                <th>Asignar Turnos</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <?php
                // Obtener los turnos asignados al usuario
                $user_shifts = $user_turnos[$user['id']] ?? [];

                ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= $user['username'] ?></td>
                    <td><?= $user['role'] ?></td>
                    <td><?= $user['created_at'] ?></td>
                    <td>
                        <?php if (!empty($user_shifts)): ?>
                            <ul>
                                <?php foreach ($user_shifts as $shift): ?>
                                    <li><?= $turnos_disponibles[$shift] ?? $shift ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="text-muted">Sin turnos asignados</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <select class="form-select shift-select" data-user="<?= $user['id'] ?>">
                            <option value="">Seleccionar turno...</option>
                            <?php foreach ($turnos_disponibles as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-primary mt-2 assign-shift"
                            data-user="<?= $user['id'] ?>">Asignar</button>
                        <button class="btn btn-sm btn-danger mt-2 remove-shift"
                            data-user="<?= $user['id'] ?>">Quitar</button>
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
        // Manejar asignación de turnos
        document.querySelectorAll('.assign-shift').forEach(btn => {
            btn.addEventListener('click', function () {
                const userId = this.getAttribute('data-user');
                const select = this.closest('td').querySelector('.shift-select');
                const shift = select.value;

                if (!shift) {
                    alert('Por favor selecciona un turno');
                    return;
                }

                fetch("user_crud.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `user_id=${userId}&shift=${shift}&action_shift=add`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            location.reload(); // Recargar para ver los cambios
                        }
                    })
                    .catch(error => console.error("Error:", error));
            });
        });

        // Manejar eliminación de turnos
        document.querySelectorAll('.remove-shift').forEach(btn => {
            btn.addEventListener('click', function () {
                const userId = this.getAttribute('data-user');
                const select = this.closest('td').querySelector('.shift-select');
                const shift = select.value;

                if (!shift) {
                    alert('Por favor selecciona un turno');
                    return;
                }

                if (!confirm(`¿Estás seguro de quitar el turno ${select.options[select.selectedIndex].text}?`)) {
                    return;
                }

                fetch("user_crud.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `user_id=${userId}&shift=${shift}&action_shift=remove`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            location.reload(); // Recargar para ver los cambios
                        }
                    })
                    .catch(error => console.error("Error:", error));
            });
        });

        // Inicializar DataTables
        $('#usersTable').DataTable({
            paging: true,
            searching: true,
            ordering: true,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            },
            columnDefs: [
                { orderable: false, targets: [4, 5, 6] } // Hacer que las columnas de acciones no sean ordenables
            ]
        });
    });
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"></script>
</body>

</html>