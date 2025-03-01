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


// Verificar la acción solicitada
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'add') {
    // Agregar un nuevo socio
    if (isset($_POST['submit'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $whatsapp = $_POST['whatsapp'];
        $start_date = date('Y-m-d'); // Fecha actual
        $end_date = date('Y-m-d', strtotime('+6 months')); // Caducidad 6 meses después
        $description = $_POST['description'];

        $sql = "INSERT INTO members (name, email, whatsapp, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $email, $whatsapp, $start_date, $end_date, $description]);

        header('Location: members_crud.php');
    }
} elseif ($action == 'edit') {
    // Editar un socio existente
    $id = $_GET['id'] ?? null;
if (!$id) {
    die("Error: ID no proporcionado.");
}

    if (isset($_POST['submit'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $whatsapp = $_POST['whatsapp'];
        $description = $_POST['description'];

        $sql = "UPDATE members SET name = ?, email = ?, whatsapp = ?, description = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $email, $whatsapp, $description, $id]);

        header('Location: members_crud.php');
    } else {
        $sql = "SELECT * FROM members WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} elseif ($action == 'delete') {
    // Eliminar un socio
    $id = $_GET['id'];
    $sql = "DELETE FROM members WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    header('Location: members_crud.php');
} elseif ($action == 'extend') {
    // Ampliar membresía
    $id = $_GET['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Actualizar fecha de caducidad y descripción
        $description = $_POST['description'];
        $sql = "UPDATE members SET end_date = DATE_ADD(end_date, INTERVAL 1 MONTH), description = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$description, $id]);

        header('Location: members_crud.php');
    } else {
        // Obtener los datos actuales del miembro
        $sql = "SELECT * FROM members WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} elseif ($action == 'edit_benefits') {
    // Editar beneficios reclamados
    $id = $_GET['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Actualizar beneficios reclamados
        $benefits_claimed = isset($_POST['benefits_claimed']) ? implode(',', $_POST['benefits_claimed']) : '';
        $sql = "UPDATE members SET benefits_claimed = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$benefits_claimed, $id]);

        header('Location: members_crud.php');
    } else {
        // Obtener los datos actuales del miembro
        $sql = "SELECT * FROM members WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Actualizar automáticamente el estado según la fecha de caducidad
$sql = "UPDATE members SET is_active = 0 WHERE end_date < CURDATE()";
$pdo->query($sql);

// Listar todos los socios
$sql = "SELECT * FROM members";
$result = $pdo->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Socios</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-dt/css/jquery.dataTables.min.css" rel="stylesheet">
</head>


<body>

    <div class="container my-5">
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
                    <li>Los datos de socios se recogen de los correos.</li>
                    <li>Al ingresar un socio automáticamente se le aplica una membresia de 6 meses.</li>
                    <li>El boton membresia permite editar la descripción y ampliar un mes (automático).</li>
                    <li>Puedes editar o eliminar socios con los botones correspondientes.</li>
                    <li>Si un socio reclama un beneficio se cambia en "editar beneficios".</li>
                    <li>Utiliza la barra de búsqueda para encontrar registros específicas .</li> 
                </ol>
            `;
                    appendAlert(message, 'success');
                })
            }
        </script>
        <h1 class="mb-4">Gestión de Socios</h1>
        <button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>


        <button class="btn btn-success mb-3" onclick="window.location.replace('members_crud.php?action=add');">Agregar Nuevo Socio</button>


        <?php if ($action == 'add' || $action == 'edit') { ?>
            <form method="POST" action="" class="mb-4">
                <div class="mb-3">
                    <label class="form-label">Nombre:</label>
                    <input type="text" class="form-control" name="name"
                        value="<?php echo isset($member['name']) ? $member['name'] : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo Electrónico:</label>
                    <input type="email" class="form-control" name="email"
                        value="<?php echo isset($member['email']) ? $member['email'] : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">WhatsApp:</label>
                    <input type="text" class="form-control" name="whatsapp"
                        value="<?php echo isset($member['whatsapp']) ? $member['whatsapp'] : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción:</label>
                    <textarea class="form-control" name="description"
                        required><?php echo isset($member['description']) ? $member['description'] : ''; ?></textarea>
                </div>


                <button type="submit" name="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-danger"
                    onclick="document.querySelector('form').style.display='none'">Cancelar</button>
            </form>
        <?php } ?>

        <?php if ($action == 'extend') { ?>
            <form method="POST" class="mb-4">
                <h2>Ampliar Membresía</h2>
                <p>Membresía actual hasta: <strong><?php echo $member['end_date']; ?></strong></p>
                <div class="mb-3">
                    <label class="form-label">Descripción:</label>
                    <textarea class="form-control" name="description"
                        required><?php echo $member['description']; ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Ampliar un Mes</button>
                <button type="button" class="btn btn-danger"
                    onclick="document.querySelector('form').style.display='none'">Cancelar</button>
            </form>
        <?php } ?>
        <div class="table-wraper ">
            <table id="membersTable" class="table table-striped table-bordered ">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Correo Electrónico</th>
                        <th>WhatsApp</th>
                        <th>Fecha de Inicio</th>
                        <th>Fecha de Caducidad</th>
                        <th>Descripción</th>
                        <th>Beneficios Reclamados</th>

                        <th>Acciones</th>
                        <th>Estado</th>

                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)) { ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['name']; ?></td>
                            <td><?php echo $row['email']; ?></td>
                            <td><?php echo $row['whatsapp']; ?></td>
                            <td><?php echo $row['start_date']; ?></td>
                            <td><?php echo $row['end_date']; ?></td>
                            <td class="col-description"><?php echo $row['description']; ?></td>
                            <td>
                                <?php
                                if (!empty($row['benefits_claimed'])) {
                                    $benefits = explode(',', $row['benefits_claimed']); // Convertir a array
                                    echo '<ul>';
                                    foreach ($benefits as $benefit) {
                                        echo '<li>' . htmlspecialchars($benefit) . '</li>'; // Mostrar cada beneficio
                                    }
                                    echo '</ul>';
                                } else {
                                    echo 'Ninguno';
                                }
                                ?>
                                <button class="btn btn-info btn-sm" onclick="window.location.replace('members_crud.php?action=edit_benefits&id=<?php echo $row['id']; ?>');">Editar Beneficios</button>

                            </td>
                            <td>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-warning btn-sm" style="padding: 2px;" onclick="window.location.href='members_crud.php?action=edit&id=<?php echo $row['id']; ?>';">Editar</button>

                                    <button class="btn btn-danger btn-sm" style="padding: 2px;" onclick="if (confirm('¿Estás seguro de eliminar este socio?')) { window.location.href='members_crud.php?action=delete&id=<?php echo $row['id']; ?>'; }">Eliminar</button>

                                    <button class="btn btn-primary btn-sm" style="padding: 2px;" onclick="window.location.href='members_crud.php?action=extend&id=<?php echo $row['id']; ?>';">Ampliar Membresía</button>

                                </div>
                            </td>

                            <td>
                                <?php echo $row['is_active'] ? 'Activo' : 'No Activo'; ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if ($action == 'edit_benefits') { ?>
                        <div class="container my-5">
                            <h2>Editar Beneficios Reclamados</h2>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Beneficios Reclamados:</label>
                                    <?php
                                    // Convertir los beneficios reclamados actuales en un array
                                    $claimed_benefits = explode(',', $member['benefits_claimed']);
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="benefits_claimed[]"
                                            value="Netflix" <?php echo in_array('Netflix', $claimed_benefits) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Netflix</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="benefits_claimed[]"
                                            value="KFC" <?php echo in_array('KFC', $claimed_benefits) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">KFC 2</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="benefits_claimed[]"
                                            value="CINEPLANET" <?php echo in_array('CINEPLANET', $claimed_benefits) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">CINEPLANET</label>
                                    </div>

                                </div>
                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                <button class="btn btn-secondary" onclick="window.location.href='members_crud.php';">Cancelar</button>

                            </form>
                        </div>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (requerido para DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>

    <script>
        // Inicializar DataTables
        $(document).ready(function () {
            $('#membersTable').DataTable();
        });
    </script>

</body>

</html>