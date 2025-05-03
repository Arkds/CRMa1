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
// Lista de estados y canales
$statuses = ["Nuevo", "Interesado", "Negociación", "Comprometido", "Vendido", "Perdido"];
$channels = ["WhatsApp", "Email", "Teléfono", "Referido", "Redes Sociales"];
// Editar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id']; 
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $description = $_POST['description'];
    $status = $_POST['status'];
    $channel = $_POST['channel'];
    $fecha_recuerdo = !empty($_POST['fecha_recuerdo']) ? $_POST['fecha_recuerdo'] : null;

    if ($id) {
        $stmt = $pdo->prepare("UPDATE report_clients SET name = ?, phone = ?, email = ?, description = ?, status = ?, channel = ?, fecha_recuerdo = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $email, $description, $status, $channel, $fecha_recuerdo, $id]);
    }

    header('Location: tracin_crud.php');
    exit;
}
// Obtener clientes con la última edición
$query = "
    SELECT rc.*, 
           DATE_FORMAT(rc.updated_at, '%d/%m/%Y %H:%i') AS last_edit, 
           DATE_FORMAT(rc.created_at, '%Y-%m-%d %H:%i:%s') AS created_date,
           u.username AS registrado_por
    FROM report_clients rc
    JOIN reports r ON rc.report_id = r.id
    JOIN users u ON r.user_id = u.id
    ORDER BY FIELD(rc.status, 'Nuevo', 'Interesado', 'Negociación', 'Comprometido', 'Vendido', 'Perdido'), rc.created_at DESC
";



$stmt = $pdo->query($query);
$clients = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $clients[$row['status']][] = $row;
    
}

include('header.php')


?>
<style>
    .ocultar-fila {
        display: none !important;
    }
</style>


    <script>
    const currentUser = "<?= htmlspecialchars($username); ?>";
    const isAdmin = "<?= $role; ?>" === "admin";
</script>



    <div class="container mt-5">
        <div id="liveAlertPlaceholder"></div>
        <!--<button type="button" class="btn btn-outline-dark float-end" id="liveAlertBtn">Ayuda</button>-->

        <script>
            const alertPlaceholder = document.getElementById('liveAlertPlaceholder')
            const appendAlert = (message, type) => {
                const wrapper = document.createElement('div')
                wrapper.innerHTML = [
                    <div class="alert alert-${type} alert-dismissible" role="alert">,
                       <div>${message}</div>,
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
                    const message = 
                <ol>
                    <li>Aqui se pueden ver los clientes potenciales y hacerle seguimiento.</li>
                    <li>En las dos tablas los clientes mas recietnes estan en la parte superior.</li>
                    <li>Después de hacer algún contacto recuerda cambiar el estado.</li>
                    <li>En la tabla clientes en Proceso se encuentran los clientes con estado: Nuevo, Interesado, Negociación, Comprometido.</li>
                    <li>En la tabla clientes Finalizados se encuentran los clientes con estado: Vendido, Perdido.</li>
                    <li>Todos tienen acceso a los clientes potenciales.</li>
                    <li>Utiliza la barra de búsqueda para encontrar regístros específicos rápidamente.</li> 
                </ol>
            ;
                    appendAlert(message, 'success');
                })
            }
        </script>
        <h1 class="text-center">Seguimiento de Clientes</h1>
        <!--<button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>-->
        
        <div class="mb-3">
        <button class="btn btn-outline-primary" onclick="filtrarRecuerdo('futuro')">☀️ Mostrar Recordatorios Actuales/Futuros</button>
        <button class="btn btn-outline-danger" onclick="filtrarRecuerdo('pasado')">⏰ Mostrar Recordatorios Pasados</button>
        <button class="btn btn-outline-secondary" onclick="resetFiltro()">🔄 Ver Todos</button>
        </div>
        <!-- Tabla 1: Clientes en proceso -->
        <h2 class="mt-5 text-primary">Clientes en Proceso</h2>
        <table class="table table-bordered table-striped" id="tracintable1">
            <thead>
                <tr>
                    <th>Fecha de Creación</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Fecha recuerdo</th>
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
                                <td>
    <?= date("Y-m-d H:i:s", strtotime($client['created_date'])) ?><br>
    <small class="text-muted">por <?= htmlspecialchars($client['registrado_por']) ?></small>
</td>




                                <td><?= htmlspecialchars($client['name']); ?></td>
                                <td><?= htmlspecialchars($client['phone']); ?></td>
                                <td><?= htmlspecialchars($client['email']); ?></td>
                                <td><?= htmlspecialchars($client['fecha_recuerdo']); ?></td>
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
    onclick="openModal(
        <?= $client['id']; ?>, 
        '<?= addslashes(htmlspecialchars($client['name'])); ?>', 
        '<?= addslashes(htmlspecialchars($client['phone'])); ?>', 
        '<?= addslashes(htmlspecialchars($client['email'])); ?>', 
        `<?= addslashes(str_replace(["\r", "\n"], '\n', htmlspecialchars($client['description'] ?: ""))); ?>`, 
        '<?= $client['status']; ?>', 
        '<?= addslashes(htmlspecialchars($client['channel'] ?: "")); ?>',
        '<?= $client['fecha_recuerdo'] ? date('Y-m-d', strtotime($client['fecha_recuerdo'])) : ''; ?>'
    )">
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
    onclick="openModal(
        <?= $client['id']; ?>, 
        '<?= addslashes(htmlspecialchars($client['name'])); ?>', 
        '<?= addslashes(htmlspecialchars($client['phone'])); ?>', 
        '<?= addslashes(htmlspecialchars($client['email'])); ?>', 
        `<?= addslashes(str_replace(["\r", "\n"], '\n', htmlspecialchars($client['description'] ?: ""))); ?>`, 
        '<?= $client['status']; ?>', 
        '<?= addslashes(htmlspecialchars($client['channel'] ?: "")); ?>',
        '<?= $client['fecha_recuerdo'] ? date('Y-m-d', strtotime($client['fecha_recuerdo'])) : ''; ?>'
    )">
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
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="recuerdoCheck">
                            <label class="form-check-label" for="recuerdoCheck">¿Recordar seguimiento?</label>
                        </div>
                        <div class="mb-3" id="recuerdoFechaContainer" style="display:none;">
                            <label for="fecha_recuerdo" class="form-label">Fecha de Recuerdo</label>
                            <input type="date" class="form-control" id="fecha_recuerdo" name="fecha_recuerdo">
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
        function openModal(id, name, phone, email, description, status, channel, fecha_recuerdo = '') {
    // Asignar valores básicos
    document.getElementById('clientId').value = id;
    document.getElementById('name').value = name;
    document.getElementById('phone').value = phone;
    document.getElementById('email').value = email;
    document.getElementById('description').value = description.replace(/\\n/g, "\n");
    document.getElementById('status').value = status || 'Nuevo';
    document.getElementById('channel').value = channel || '';
    
    // Manejo de la fecha de recuerdo
    const recuerdoInput = document.getElementById('fecha_recuerdo');
    const recuerdoCheck = document.getElementById('recuerdoCheck');
    const recuerdoContainer = document.getElementById('recuerdoFechaContainer');
    
    if (fecha_recuerdo && fecha_recuerdo !== '0000-00-00' && fecha_recuerdo !== '') {
        recuerdoCheck.checked = true;
        recuerdoInput.value = fecha_recuerdo;
        recuerdoContainer.style.display = 'block';
    } else {
        recuerdoCheck.checked = false;
        recuerdoInput.value = '';
        recuerdoContainer.style.display = 'none';
    }
    
    // Event listener para el checkbox
    recuerdoCheck.addEventListener('change', function() {
        recuerdoContainer.style.display = this.checked ? 'block' : 'none';
        if (!this.checked) {
            recuerdoInput.value = '';
        }
    });
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
        $(document).ready(function () {
    // Si viene el parámetro ?filtro=recordatorios_hoy en la URL
     const urlParams = new URLSearchParams(window.location.search);
    const filtro = urlParams.get('filtro');

    if (filtro === 'recordatorios_hoy') {
        setTimeout(() => {
            filtrarRecuerdo('futuro');
            $('html, body').animate({
                scrollTop: $("#tracintable1").offset().top
            }, 600);
        }, 500);
    }

    if (filtro === 'recordatorios_pasados') {
        setTimeout(() => {
            filtrarRecuerdo('pasado');
            $('html, body').animate({
                scrollTop: $("#tracintable1").offset().top
            }, 600);
        }, 500);
    }
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
    
<script>
function filtrarRecuerdo(tipo) {
    const tabla = $('#tracintable1').DataTable();
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0); // GMT-5 cero hora

    tabla.page.len(100); // mostrar 100 filas al filtrar

    tabla.rows().every(function () {
        const fila = $(this.node());
        const fechaTexto = fila.find('td:eq(4)').text().trim();
        const registradoPor = fila.find('td:eq(0)').find('small').text().replace('por ', '').trim();
        const esClienteDelUsuario = isAdmin || (registradoPor === currentUser);

        if (!esClienteDelUsuario || !fechaTexto) {
            fila.addClass('ocultar-fila');
            return;
        }

        const fecha = new Date(fechaTexto + 'T00:00:00-05:00');
        const esFuturo = fecha >= hoy;
        const esPasado = fecha < hoy;

        if ((tipo === 'futuro' && esFuturo) || (tipo === 'pasado' && esPasado)) {
            fila.removeClass('ocultar-fila');
        } else {
            fila.addClass('ocultar-fila');
        }
    });

    tabla.draw();
}

function resetFiltro() {
    const tabla = $('#tracintable1').DataTable();
    tabla.page.len(10); // volver a 10 filas por página

    tabla.rows().every(function () {
        $(this.node()).removeClass('ocultar-fila');
    });

    tabla.draw();
}

</script>

</body>

</html>