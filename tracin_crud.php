<?php
require 'db.php'; // Asegurar que este archivo configura $pdo correctamente
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
// Lista de estados y canales
$statuses = ["Nuevo", "Interesado", "Negociación", "Comprometido", "Vendido", "Perdido"];
$channels = ["WhatsApp", "Email", "Teléfono", "Referido", "Redes Sociales"];
// Editar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];  // Obtener el ID del cliente a editar
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $description = $_POST['description'];
    $status = $_POST['status'];
    $channel = $_POST['channel'];
    if ($id) {
        $stmt = $pdo->prepare("UPDATE report_clients SET name = ?, phone = ?, email = ?, description = ?, status = ?, channel = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $email, $description, $status, $channel, $id]);
    }
    header('Location: tracin_crud.php');
    exit;
}
// Obtener clientes con la última edición
$query = "SELECT *, 
                 DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i') AS last_edit, 
                 DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_date 
          FROM report_clients 
          ORDER BY FIELD(status, 'Nuevo', 'Interesado', 'Negociación', 'Comprometido', 'Vendido', 'Perdido'), created_at DESC";


$stmt = $pdo->query($query);
$clients = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $clients[$row['status']][] = $row;
}
include('header.php')

?>




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
                    <li>Aqui se pueden ver los clientes potenciales y hacerle seguimiento.</li>
                    <li>En las dos tablas los clientes mas recietnes estan en la parte superior.</li>
                    <li>Después de hacer algún contacto recuerda cambiar el estado.</li>
                    <li>En la tabla clientes en Proceso se encuentran los clientes con estado: Nuevo, Interesado, Negociación, Comprometido.</li>
                    <li>En la tabla clientes Finalizados se encuentran los clientes con estado: Vendido, Perdido.</li>
                    <li>Todos tienen acceso a los clientes potenciales.</li>
                    <li>Utiliza la barra de búsqueda para encontrar regístros específicos rápidamente.</li> 
                </ol>
            `;
                    appendAlert(message, 'success');
                })
            }
        </script>
        <h1 class="text-center">Seguimiento de Clientes</h1>
        <button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>
        <!-- Tabla 1: Clientes en proceso -->
        <h2 class="mt-5 text-primary">Clientes en Proceso</h2>
        <table class="table table-bordered table-striped" id="tracintable1">
            <thead>
                <tr>
                    <th>Fecha de Creación</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Descripción</th>
                    <th>Canal</th>
                    <th>Estado</th> <!-- Nueva columna -->
                    <th>Última Edición</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $processingStatuses = ["Nuevo", "Interesado", "Negociación", "Comprometido"];
                foreach ($processingStatuses as $status):
                    if (!empty($clients[$status])):
                        foreach ($clients[$status] as $client): ?>
                            <tr>
                                <td><?= date("Y-m-d H:i:s", strtotime($client['created_date'])) ?: 'No disponible'; ?></td>



                                <td><?= htmlspecialchars($client['name']); ?></td>
                                <td><?= htmlspecialchars($client['phone']); ?></td>
                                <td><?= htmlspecialchars($client['email']); ?></td>
                                <td>
                                    <div class="text-truncate" style="max-width: 150px; cursor:pointer;"
                                        onclick="toggleExpand(this)">
                                        <?= htmlspecialchars($client['description']) ?: 'Sin descripción' ?>
                                    </div>
                                </td>

                                <td><?= htmlspecialchars($client['channel'] ?: 'No especificado'); ?></td>
                                <td><strong><?= $client['status']; ?></strong></td> <!-- Estado agregado -->
                                <td><?= $client['last_edit'] ?: 'Nunca editado'; ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#clientModal"
                                        onclick='openModal(<?= $client['id']; ?>, 
    <?= json_encode($client['name']); ?>, 
    <?= json_encode($client['phone']); ?>, 
    <?= json_encode($client['email']); ?>, 
    <?= json_encode($client['description'] ?: ""); ?>, 
    <?= json_encode($client['status']); ?>, 
    <?= json_encode($client['channel'] ?: ""); ?>)'>
                                        Editar
                                    </button>

                                </td>
                            </tr>
                        <?php endforeach;
                    endif;
                endforeach;
                ?>
            </tbody>
        </table>
        <!-- Tabla 2: Clientes finalizados -->
        <h2 class="mt-5 text-danger">Clientes Finalizados</h2>
        <table class="table table-bordered" id="tracintable2">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Descripción</th>
                    <th>Canal</th>
                    <th>Estado</th>
                    <th>Última Edición</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $finalizedStatuses = ["Vendido", "Perdido"];
                foreach ($finalizedStatuses as $status):
                    if (!empty($clients[$status])):
                        foreach ($clients[$status] as $client): ?>
                            <tr>
                                <td><?= htmlspecialchars($client['name']); ?></td>
                                <td><?= htmlspecialchars($client['phone']); ?></td>
                                <td><?= htmlspecialchars($client['email']); ?></td>
                                <td>
                                    <div class="text-truncate" style="max-width: 150px; cursor:pointer;"
                                        onclick="toggleExpand(this)">
                                        <?= htmlspecialchars($client['description']) ?: 'Sin descripción' ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($client['channel'] ?: 'No especificado'); ?></td>
                                <td><strong><?= $client['status']; ?></strong></td> <!-- Estado agregado -->
                                <td><?= $client['last_edit'] ?: 'Nunca editado'; ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#clientModal"
                                        onclick="openModal(<?= $client['id']; ?>, '<?= htmlspecialchars($client['name']); ?>', 
            '<?= htmlspecialchars($client['phone']); ?>', '<?= htmlspecialchars($client['email']); ?>', 
            '<?= htmlspecialchars($client['description']); ?>', '<?= $client['status']; ?>', '<?= htmlspecialchars($client['channel']); ?>')">
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach;
                    endif;
                endforeach;
                ?>
            </tbody>
        </table>
    </div>
    <!-- Modal para Editar Cliente -->
    <div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientModalLabel">Editar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="tracin_crud.php">
                    <input type="hidden" id="clientId" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status">
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s; ?>"><?= $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="channel" class="form-label">Canal</label>
                            <input type="text" class="form-control" id="channel" name="channel">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>but
    </div>
    <script>
        function openModal(id, name, phone, email, description, status, channel) {
            document.getElementById('clientModalLabel').innerText = 'Editar Cliente';
            document.getElementById('clientId').value = id;
            document.getElementById('name').value = name;
            document.getElementById('phone').value = phone;
            document.getElementById('email').value = email;
            document.getElementById('description').value = description.replace(/\\n/g, "\n"); // Manejar saltos de línea
            document.getElementById('status').value = status || 'Nuevo';
            document.getElementById('channel').value = channel || '';
        }
        $(document).ready(function () {
            $('#tracintable1').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[0, 'desc']], // Primera columna (Fecha de Creación)
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });
        });
        $(document).ready(function () {
            $('#tracintable2').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[0, 'desc']], // Primera columna (Fecha de Creación)
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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