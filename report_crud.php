<?php


if (isset($_COOKIE['user_session'])) {
    // Decodificar la cookie
    $user_data = json_decode(base64_decode($_COOKIE['user_session']), true);

    if ($user_data) {
        // Variables disponibles para usar en la página
        $user_id = $user_data['user_id'];
        $username = $user_data['username'];
        $role = $user_data['role'];
    } else {
        // Si hay un problema con la cookie, redirigir al login
        header("Location: login.php");
        exit;
    }
} else {
    // Si no hay cookie, redirigir al login
    header("Location: login.php");
    exit;
}
require 'db.php';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;
// Depuración: Muestra lo que llega desde el formulario

if ($action === 'get_report' && isset($_GET['id'])) {
    $report_id = $_GET['id'];

    // Obtener los datos principales del reporte
    $stmt = $pdo->prepare("SELECT id, type FROM reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();

    // Obtener problemas
    $stmt = $pdo->prepare("SELECT content FROM report_entries WHERE report_id = ? AND category = 'problemas'");
    $stmt->execute([$report_id]);
    $problemas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener cursos más vendidos
    $stmt = $pdo->prepare("SELECT content FROM report_entries WHERE report_id = ? AND category = 'cursos_mas_vendidos'");
    $stmt->execute([$report_id]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener dudas frecuentes
    $stmt = $pdo->prepare("SELECT content FROM report_entries WHERE report_id = ? AND category = 'dudas_frecuentes'");
    $stmt->execute([$report_id]);
    $dudas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener clientes potenciales
    $stmt = $pdo->prepare("SELECT id, name, phone, email, description, status, channel FROM report_clients WHERE report_id = ?");
    $stmt->execute([$report_id]);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // Obtener recomendaciones
    $stmt = $pdo->prepare("SELECT content FROM report_entries WHERE report_id = ? AND category = 'recomendaciones' LIMIT 1");
    $stmt->execute([$report_id]);
    $recomendacion = $stmt->fetch(PDO::FETCH_ASSOC);
    $recomendacion_texto = $recomendacion ? $recomendacion['content'] : '';

    echo json_encode([
        "id" => $report['id'],
        "type" => $report['type'],
        "problemas" => $problemas,
        "cursos" => $cursos,
        "dudas" => $dudas,
        "clientes" => $clientes,
        "recomendaciones" => $recomendacion_texto
    ]);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $user_id = $user_data['user_id'];


    if ($action === 'create') {
        // Insertar el reporte
        $stmt = $pdo->prepare("INSERT INTO reports (user_id, type) VALUES (?, ?)");
        $stmt->execute([$user_id, $type]);
        $report_id = $pdo->lastInsertId();

        // Insertar problemas
        if (!empty($_POST['problemas'])) {
            foreach ($_POST['problemas'] as $problema) {
                $stmt = $pdo->prepare("INSERT INTO report_entries (report_id, category, content) VALUES (?, 'problemas', ?)");
                $stmt->execute([$report_id, $problema]);
            }
        }


        // Insertar cursos más vendidos
        if (!empty($_POST['cursos'])) {
            foreach ($_POST['cursos'] as $curso) {
                $stmt = $pdo->prepare("INSERT INTO report_entries (report_id, category, content) VALUES (?, 'cursos_mas_vendidos', ?)");
                $stmt->execute([$report_id, $curso]);
            }
        }

        // Insertar dudas frecuentes
        if (!empty($_POST['dudas'])) {
            foreach ($_POST['dudas'] as $duda) {
                $stmt = $pdo->prepare("INSERT INTO report_entries (report_id, category, content) VALUES (?, 'dudas_frecuentes', ?)");
                $stmt->execute([$report_id, $duda]);
            }
        }





        if (!empty($_POST['clientes']) && is_array($_POST['clientes'])) {
            foreach ($_POST['clientes'] as $cliente) {
                $name = isset($cliente['name']) ? trim($cliente['name']) : '';
                $phone = isset($cliente['phone']) ? trim($cliente['phone']) : null;
                $email = isset($cliente['email']) ? trim($cliente['email']) : null;
                $description = isset($cliente['description']) ? trim($cliente['description']) : '';
                $status = isset($cliente['status']) ? trim($cliente['status']) : 'Nuevo';
                $channel = isset($cliente['channel']) ? trim($cliente['channel']) : null;

                if (!empty($name)) {
                    $stmt = $pdo->prepare("INSERT INTO report_clients (report_id, name, phone, email, description, status, channel) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$report_id, $name, $phone, $email, $description, $status, $channel]);
                }
            }
        }




        // Insertar recomendaciones
        if (!empty($_POST['recomendaciones'])) {
            $stmt = $pdo->prepare("INSERT INTO report_entries (report_id, category, content) VALUES (?, 'recomendaciones', ?)");
            $stmt->execute([$report_id, $_POST['recomendaciones']]);
        }

        header('Location: report_crud.php');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
        $report_id = $_POST['id'];
        $type = $_POST['type'];

        try {
            // Actualizar el tipo de reporte
            $stmt = $pdo->prepare("UPDATE reports SET type = ? WHERE id = ?");
            $stmt->execute([$type, $report_id]);

            // Eliminar las entradas anteriores del reporte (problemas, cursos, dudas, recomendaciones)
            $categories = ['problemas', 'cursos_mas_vendidos', 'dudas_frecuentes', 'recomendaciones'];
            foreach ($categories as $category) {
                $stmt = $pdo->prepare("DELETE FROM report_entries WHERE report_id = ? AND category = ?");
                $stmt->execute([$report_id, $category]);
            }

            // Insertar los nuevos problemas
            if (!empty($_POST['editProblemas'])) {
                foreach ($_POST['editProblemas'] as $problema) {
                    $stmt = $pdo->prepare("INSERT INTO report_entries (report_id, category, content) VALUES (?, 'problemas', ?)");
                    $stmt->execute([$report_id, $problema]);
                }
            }

            // Insertar los nuevos cursos más vendidos
            if (!empty($_POST['editCursos'])) {
                foreach ($_POST['editCursos'] as $curso) {
                    $stmt = $pdo->prepare("INSERT INTO report_entries (report_id, category, content) VALUES (?, 'cursos_mas_vendidos', ?)");
                    $stmt->execute([$report_id, $curso]);
                }
            }

            // Insertar las nuevas dudas frecuentes
            if (!empty($_POST['editDudas'])) {
                foreach ($_POST['editDudas'] as $duda) {
                    $stmt = $pdo->prepare("INSERT INTO report_entries (report_id, category, content) VALUES (?, 'dudas_frecuentes', ?)");
                    $stmt->execute([$report_id, $duda]);
                }
            }

            // Eliminar los clientes anteriores
            // Obtener los clientes actuales de la base de datos
            $stmt = $pdo->prepare("SELECT id FROM report_clients WHERE report_id = ?");
            $stmt->execute([$report_id]);
            $clientes_actuales = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Si hay clientes en el formulario
            if (!empty($_POST['editClientes'])) {
                $clientes_ids_enviados = [];

                foreach ($_POST['editClientes'] as $index => $cliente) {
                    $name = isset($cliente['name']) ? trim($cliente['name']) : '';
                    $phone = isset($cliente['phone']) ? trim($cliente['phone']) : null;
                    $email = isset($cliente['email']) ? trim($cliente['email']) : null;
                    $description = isset($cliente['description']) ? trim($cliente['description']) : '';
                    $status = isset($cliente['status']) ? trim($cliente['status']) : 'Nuevo';
                    $channel = isset($cliente['channel']) ? trim($cliente['channel']) : null;

                    if (!empty($name)) {
                        // Si el cliente ya existe en la base de datos, se actualiza
                        if (!empty($cliente['id']) && in_array($cliente['id'], $clientes_actuales)) {
                            $stmt = $pdo->prepare("UPDATE report_clients SET name = ?, phone = ?, email = ?, description = ?, status = ?, channel = ? WHERE id = ?");
                            $stmt->execute([$name, $phone, $email, $description, $status, $channel, $cliente['id']]);
                            $clientes_ids_enviados[] = $cliente['id'];
                        } else {
                            // Si el cliente es nuevo, se inserta
                            $stmt = $pdo->prepare("INSERT INTO report_clients (report_id, name, phone, email, description, status, channel) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$report_id, $name, $phone, $email, $description, $status, $channel]);
                            $clientes_ids_enviados[] = $pdo->lastInsertId();
                        }
                    }
                }

                // Eliminar los clientes que ya no están en el formulario
                $clientes_a_eliminar = array_diff($clientes_actuales, $clientes_ids_enviados);
                if (!empty($clientes_a_eliminar)) {
                    $stmt = $pdo->prepare("DELETE FROM report_clients WHERE id IN (" . implode(",", array_map("intval", $clientes_a_eliminar)) . ")");
                    $stmt->execute();
                }
            }



            // Insertar la nueva recomendación
            if (!empty($_POST['editRecomendaciones'])) {
                $stmt = $pdo->prepare("INSERT INTO report_entries (report_id, category, content) VALUES (?, 'recomendaciones', ?)");
                $stmt->execute([$report_id, $_POST['editRecomendaciones']]);
            }

            header('Location: report_crud.php');
            exit;

        } catch (Exception $e) {
            header('Location: report_crud.php');
        }
        exit;
    }



}

$isAdmin = ($role === 'admin'); // ✅ Corrección
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_role = $stmt->fetchColumn();

if ($user_role === 'admin') {
    $stmt = $pdo->query("
        SELECT reports.id, users.username AS user_name, reports.type, reports.date
        FROM reports
        JOIN users ON reports.user_id = users.id
        ORDER BY reports.date DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT reports.id, users.username AS user_name, reports.type, reports.date
        FROM reports
        JOIN users ON reports.user_id = users.id
        WHERE reports.user_id = ?
        ORDER BY reports.date DESC
    ");
    $stmt->execute([$user_id]);
}

$reports = $stmt->fetchAll();
include('header.php')
?>


    <div class="container py-4">
        <div id="liveAlertPlaceholder"></div>
        <!--<button type="button" class="btn btn-outline-dark float-end" id="liveAlertBtn">Ayuda</button>-->

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
                        <li>Tus reportes pueden ser diarios o semanales.</li>
                        <li>Puedes agregar "n" problemas, cursos, dudas y clientes potenciales.</li>
                        <li>En clientes no se puede dejar en blanco el email, si el cliente no tiene email coloca "no@no.no".</li>
                        <li>En clientes, arriba de canal se refiere al estado del cliente.</li>
                        <li>En canal coloca el canal de venta (a1cursosmaster, whatsapp, messenger de página), el donde encontrar al cliente para hacerle seguimiento".</li>
                        <li>Si eres venededor solo puedes ver tus reportes.</li> 
                        <li>Utiliza la barra de búsqueda para encontrar registros específicos.</li> 
                    </ol>
                `;
                    appendAlert(message, 'success');
                })
            }
        </script>
        <h1 class="text-center">Gestión de Reportes</h1>
        <!--<button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>-->
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#reportModal">Agregar Reporte</button>
        <?php if ($isAdmin): ?>
            <button class="btn btn-info mb-3" onclick="window.location.href='report_custom.php';">Ver Reportes Personalizados</button>

        <?php endif; ?>


        <table class="table table-striped  display compact" id="reportstable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Tipo</th>
                    <th>Fecha</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?= $report['id'] ?></td>
                        <td><?= $report['user_name'] ?></td>
                        <td><?= $report['type'] ?></td>
                        <td><?= $report['date'] ?></td>
                        <td>
                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editReportModal"
                                onclick="cargarReporte(<?= $report['id'] ?>)">Ver/Editar</button>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Modal para crear reporte -->
        <div class="modal fade" id="reportModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Nuevo Reporte</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="report_crud.php?action=create">
                            <div class="mb-3">
                                <label class="form-label">Tipo de Reporte</label>
                                <div>
                                    <button type="button" class="btn btn-outline-primary active"
                                        id="btnDiario">Diario</button>
                                    <button type="button" class="btn btn-outline-secondary" id="btnSemanal">Semanal</button>
                                </div>
                                <input type="hidden" name="type" id="reportType" value="diario">
                            </div>

                            <div id="problemas">
                                <h6>Problemas</h6>
                                <div id="problemasContainer"></div>
                                <button type="button" class="btn btn-sm btn-secondary"
                                    onclick="agregarCampo('problemasContainer', 'problemas[]')">Agregar Problema</button>
                            </div>
                            <hr>
                            <div id="cursos">
                                <h6>Cursos Más Vendidos</h6>
                                <div id="cursosContainer"></div>
                                <button type="button" class="btn btn-sm btn-secondary"
                                    onclick="agregarCampo('cursosContainer', 'cursos[]')">Agregar Curso</button>
                            </div>
                            <hr>
                            <div id="dudas">
                                <h6>Dudas Frecuentes</h6>
                                <div id="dudasContainer"></div>
                                <button type="button" class="btn btn-sm btn-secondary"
                                    onclick="agregarCampo('dudasContainer', 'dudas[]')">Agregar Duda</button>
                            </div>
                            <hr>
                            <div id="clientes">
                                <h6>Clientes Potenciales</h6>
                                <div id="clientesContainer"></div>
                                <button type="button" class="btn btn-sm btn-secondary"
                                    onclick="agregarClienteNuevo('clientesContainer')">
                                    Agregar Cliente
                                </button>
                            </div>
                            <hr>
                            <div>
                                <h6>Recomendaciones</h6>
                                <textarea class="form-control" name="recomendaciones"></textarea>
                            </div>
                            <hr>
                            <button type="submit" class="btn btn-success mt-3">Guardar Reporte</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal para Editar Reporte -->
        <!-- Modal para Editar Reporte -->
        <div class="modal fade" id="editReportModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Reporte</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editReportForm" method="POST" action="report_crud.php?action=edit">
                            <input type="hidden" id="editReportId" name="id">
                            <div class="mb-3">
                                <label class="form-label">Tipo de Reporte</label>
                                <select class="form-select" id="editReportType" name="type">
                                    <option value="diario">Diario</option>
                                    <option value="semanal">Semanal</option>
                                </select>
                            </div>
                            <div id="editProblemas">
                                <h6>Problemas</h6>
                                <div id="editProblemasContainer"></div>
                                <button type="button" class="btn btn-sm btn-secondary"
                                    onclick="agregarCampo('editProblemasContainer', 'editProblemas[]')">Agregar
                                    Problema</button>
                            </div>
                            <hr>
                            <div id="editCursos">
                                <h6>Cursos Más Vendidos</h6>
                                <div id="editCursosContainer"></div>
                                <button type="button" class="btn btn-sm btn-secondary"
                                    onclick="agregarCampo('editCursosContainer', 'editCursos[]')">Agregar Curso</button>
                            </div>
                            <hr>
                            <div id="editDudas">
                                <h6>Dudas Frecuentes</h6>
                                <div id="editDudasContainer"></div>
                                <button type="button" class="btn btn-sm btn-secondary"
                                    onclick="agregarCampo('editDudasContainer', 'editDudas[]')">Agregar Duda</button>
                            </div>
                            <hr>
                            <div id="editClientes">
                                <h6>Clientes Potenciales</h6>
                                <div id="editClientesContainer"></div>
                                <button type="button" class="btn btn-sm btn-secondary"
                                    onclick="agregarClienteEditar('editClientesContainer')">Agregar Cliente</button>


                            </div>
                            <hr>
                            <div>
                                <h6>Recomendaciones</h6>
                                <textarea class="form-control" id="editRecomendaciones"
                                    name="editRecomendaciones"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success mt-3">Guardar Cambios</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>    
    </div>
    



    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let reportTypeInput = document.getElementById("reportType");
            let btnDiario = document.getElementById("btnDiario");
            let btnSemanal = document.getElementById("btnSemanal");

            // Configurar el evento para cambiar el valor al hacer clic en los botones
            btnDiario.addEventListener("click", function () {
                reportTypeInput.value = "diario";
                btnDiario.classList.add("active");
                btnSemanal.classList.remove("active");
            });

            btnSemanal.addEventListener("click", function () {
                reportTypeInput.value = "semanal";
                btnSemanal.classList.add("active");
                btnDiario.classList.remove("active");
            });
        });


        // Función para agregar campos dinámicamente
        function agregarCampo(containerId, inputName, value = '') {
            let container = document.getElementById(containerId);
            let input = `<input type="text" name="${inputName}" class="form-control my-2" value="${value}" >`;
            container.insertAdjacentHTML("beforeend", input);
        }

        function agregarClienteNuevo(containerId) {
            let container = document.getElementById(containerId);
            let index = container.children.length; // Índice único para cada cliente

            let input = `<div class="border p-2 my-2">
        <input type="text" name="clientes[${index}][name]" placeholder="Nombre" class="form-control my-1" required>
        <input type="text" name="clientes[${index}][phone]" placeholder="Teléfono" class="form-control my-1">
        <input type="email" name="clientes[${index}][email]" placeholder="Email" class="form-control my-1">
        <textarea name="clientes[${index}][description]" placeholder="Descripción" class="form-control my-1"></textarea>
        
        <!-- Estado -->
        <select name="clientes[${index}][status]" class="form-select my-1" required>
            <option value="Nuevo">Nuevo</option>
            <option value="Interesado">Interesado</option>
            <option value="Negociación">Negociación</option>
            <option value="Comprometido">Comprometido</option>
            <option value="Vendido">Vendido</option>
            <option value="Perdido">Perdido</option>
        </select>

        <!-- Canal -->
        <input type="text" name="clientes[${index}][channel]" placeholder="Canal" class="form-control my-1" list="canales${index}">

        <!-- Datalist para sugerencias -->
        <datalist id="canales${index}">
            <option value="WhatsApp Premium">
            <option value="WhatsApp Hazla">
            <option value="Messenger Tx">
            <option value="Messenger Hazla">
            <option value="Messenger Premium">
        </datalist>

    </div>`;

            container.insertAdjacentHTML("beforeend", input);
        }


        function agregarClienteEditar(containerId, id = '', name = '', phone = '', email = '', description = '', status = 'Nuevo', channel = '') {
            let container = document.getElementById(containerId);
            let index = container.children.length; // Índice único para cada cliente

            let input = `<div class="border p-2 my-2">
        <input type="hidden" name="editClientes[${index}][id]" value="${id}">
        <input type="text" name="editClientes[${index}][name]" placeholder="Nombre" class="form-control my-1" value="${name}" required>
        <input type="text" name="editClientes[${index}][phone]" placeholder="Teléfono" class="form-control my-1" value="${phone}">
        <input type="email" name="editClientes[${index}][email]" placeholder="Email" class="form-control my-1" value="${email}">
        <textarea name="editClientes[${index}][description]" placeholder="Descripción" class="form-control my-1">${description}</textarea>

        <!-- Estado -->
        <select name="editClientes[${index}][status]" class="form-select my-1" required>
            <option value="Nuevo" ${status === 'Nuevo' ? 'selected' : ''}>Nuevo</option>
            <option value="Interesado" ${status === 'Interesado' ? 'selected' : ''}>Interesado</option>
            <option value="Negociación" ${status === 'Negociación' ? 'selected' : ''}>Negociación</option>
            <option value="Comprometido" ${status === 'Comprometido' ? 'selected' : ''}>Comprometido</option>
            <option value="Vendido" ${status === 'Vendido' ? 'selected' : ''}>Vendido</option>
            <option value="Perdido" ${status === 'Perdido' ? 'selected' : ''}>Perdido</option>
        </select>

        <!-- Canal -->
        <input type="text" name="clientes[${index}][channel]" placeholder="Canal" class="form-control my-1" list="canales${index}">
        
        <!-- Datalist para sugerencias -->
        <datalist id="canales${index}">
            <option value="WhatsApp Premium">
            <option value="WhatsApp Hazla">
            <option value="Messenger Tx">
            <option value="Messenger Hazla">
            <option value="Messenger Premium">
        </datalist>
    </div>`;

            container.insertAdjacentHTML("beforeend", input);
        }




        // Función para cargar los datos del reporte al modal de edición
        function cargarReporte(id) {
            fetch(`report_crud.php?action=get_report&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById("editReportId").value = data.id;
                    document.getElementById("editReportType").value = data.type;

                    let problemasContainer = document.getElementById("editProblemasContainer");
                    problemasContainer.innerHTML = "";
                    data.problemas.forEach(problema => {
                        agregarCampo('editProblemasContainer', 'editProblemas[]', problema.content);
                    });

                    let cursosContainer = document.getElementById("editCursosContainer");
                    cursosContainer.innerHTML = "";
                    data.cursos.forEach(curso => {
                        agregarCampo('editCursosContainer', 'editCursos[]', curso.content);
                    });

                    let dudasContainer = document.getElementById("editDudasContainer");
                    dudasContainer.innerHTML = "";
                    data.dudas.forEach(duda => {
                        agregarCampo('editDudasContainer', 'editDudas[]', duda.content);
                    });

                    // Cargar clientes potenciales
                    let clientesContainer = document.getElementById("editClientesContainer");
                    clientesContainer.innerHTML = "";
                    data.clientes.forEach(cliente => {
                        agregarClienteEditar('editClientesContainer', cliente.id, cliente.name, cliente.phone, cliente.email, cliente.description, cliente.status, cliente.channel);
                    });


                    document.getElementById("editRecomendaciones").value = data.recomendaciones;
                });

        }
        $(document).ready(function () {
            $('#reportstable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[4, 'desc']],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });
        });

    </script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const form = document.querySelector('#reportModal form');
        const submitButton = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.innerText = 'Guardando...';
        });
    });
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>