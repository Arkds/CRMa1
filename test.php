<?php
require 'db.php';


// Verificar sesión
if (!isset($_COOKIE['user_session'])) {
    header("Location: login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_check'])) {
    header("Content-Type: application/json");

    $operation_number = $_POST['operation_number'] ?? '';
    $client_email = $_POST['client_email'] ?? '';
    $voucher_datetime = $_POST['voucher_datetime'] ?? '';
    $commission_id = $_POST['commission_id'] ?? null;

    $combinations = [
        ['operation_number', 'client_email'],
        ['operation_number', 'voucher_datetime'],
        ['client_email', 'voucher_datetime']
    ];

    $results = [];
    $matchedFields = [];

    foreach ($combinations as $combo) {
        $field1 = $combo[0];
        $field2 = $combo[1];

        $value1 = $$field1;
        $value2 = $$field2;

        if (!empty($value1) && !empty($value2)) {
            $sql = "SELECT COUNT(*) FROM commissions WHERE $field1 = ? AND $field2 = ?";
            $params = [$value1, $value2];

            if ($commission_id) {
                $sql .= " AND id != ?";
                $params[] = $commission_id;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $results[] = $combo;
                $matchedFields = array_merge($matchedFields, $combo);
            }
        }
    }

    $exists = !empty($results);
    $matchedFields = array_unique($matchedFields);

    echo json_encode([
        'exists' => $exists,
        'matchedFields' => $matchedFields,
        'conflicts' => $results
    ]);
    exit;
}
// Decodificar cookie
$user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
$user_id = $user_data['user_id'];
$username = $user_data['username'];
$role = $user_data['role'];
$isAdmin = ($role === 'admin');
// Actualizar el estado de 'is_checked' si es un admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_checked']) && $isAdmin) {
    header("Content-Type: application/json"); // Asegura que la respuesta sea JSON

    $commission_id = $_POST['id'] ?? null;
    $is_checked = $_POST['is_checked'] ?? null;

    if ($commission_id !== null && $is_checked !== null) {
        $stmt = $pdo->prepare("UPDATE commissions SET is_checked = ? WHERE id = ?");
        $stmt->execute([$is_checked, $commission_id]);

        echo json_encode(["success" => true]);
        exit;
    } else {
        echo json_encode(["success" => false, "message" => "Error en los datos"]);
        exit;
    }
}



// Obtener la carpeta de Google Drive del usuario
$stmt = $pdo->prepare("SELECT drive_folder FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_drive = $stmt->fetch();
$drive_folder = $user_drive['drive_folder'] ?? null;

// Insertar nueva comisión
$stmt = $pdo->prepare("INSERT INTO commissions (product_name, price, channel, operation_number, user_id) VALUES (?, ?, ?, ?, ?)");
$commission_id = $pdo->lastInsertId(); // Obtener el ID de la nueva comisión

// Guardar múltiples comprobantes
if (!empty($links)) {
    foreach ($links as $link) {
        if (!empty($link)) {
            $stmt = $pdo->prepare("INSERT INTO commission_files (commission_id, file_link) VALUES (?, ?)");
            $stmt->execute([$commission_id, $link]);
        }
    }
}

if ($commission_id) {
    // Borrar los comprobantes anteriores
    $stmt = $pdo->prepare("DELETE FROM commission_files WHERE commission_id = ?");
    $stmt->execute([$commission_id]);

    // Insertar los nuevos comprobantes
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
    $client_email = $_POST['client_email'] ?? null;
    $voucher_datetime = $_POST['voucher_datetime'] ?? null;
    if ($voucher_datetime) {
        $voucher_datetime = date('Y-m-d', strtotime($voucher_datetime));
    }


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
        $stmt = $pdo->prepare("INSERT INTO commissions (product_name, price, channel, operation_number, description, user_id, client_email, voucher_datetime) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$product_name, $price, $channel, $operation_number, $description, $user_id, $client_email, $voucher_datetime]);


        $commission_id = $pdo->lastInsertId(); 

        setcookie('success_message', "¡Comisión registrada correctamente!", time() + 5, "/");
    }
    // Si hay un ID de comisión, proceder con los comprobantes
    if ($commission_id) {
        // Eliminar comprobantes previos para esta comisión antes de insertar nuevos
        $stmt = $pdo->prepare("DELETE FROM commission_files WHERE commission_id = ?");
        $stmt->execute([$commission_id]);

        // Insertar nuevos comprobantes
        foreach ($links as $link) {
            if (!empty($link)) {
                $stmt = $pdo->prepare("INSERT INTO commission_files (commission_id, file_link) VALUES (?, ?)");
                $stmt->execute([$commission_id, $link]);
            }
        }
    }

    header('Location: test.php');
    exit;
}
// Eliminar Comisión (Solo Admin)
if (isset($_GET['delete']) && $isAdmin) {
    $stmt = $pdo->prepare("DELETE FROM commissions WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    setcookie('success_message', "¡Comisión eliminada!", time() + 5, "/");
    header('Location: test.php');
    exit;
}
// Consultar comisiones
$commissionsQuery = $isAdmin
    ? "SELECT c.*, u.username FROM commissions c JOIN users u ON c.user_id = u.id ORDER BY created_at DESC"
    : "SELECT * FROM commissions WHERE user_id = ? ORDER BY created_at DESC";

$stmt = $pdo->prepare($commissionsQuery);
$isAdmin ? $stmt->execute() : $stmt->execute([$user_id]);
$commissions = $stmt->fetchAll();
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
                        <li>Subir tu archivo a la carpeta de drive disponible en "Carpeta de comprobantes".</li>
                        <li>Luego de subir tu imagen copear el link haciendo click derecho "COMPARTI>LINK".</li>
                        <li>Llenar detalles de comsión y pegar el link.</li>
                        <li>Si son más de una imagen agregar campo de otro link con "Agregar más enlaces"</li>
                        <li>"Guardar Comisión" para guardar comisión.</li>
                        <li>Se pueden editar todos los campos.</li>
                        <li>Para verl el comprobante hacer click en el boton negro de "Captura".</li>
                        <li>Si tienes dudas preguntar al administrador, si encuentras fallas reportar.</li>
                    </ol>
                    ;
                appendAlert(message, 'success');
            })
        }
    </script>
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
    <?php endif; ?>

    <table class="table table-sm table-striped display compact" id="table1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Producto</th>
                <th>Precio</th>
                <th>Canal</th>
                <th>Número de Operación</th>
                <th>Correo Cliente</th>
                <th>Fecha/Hora Comprobante</th>
                <th>Comprobante</th>
                <th>Descripción</th>
                <th>Usuario</th>
                <th>Procesado</th>
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
                    <td><?= htmlspecialchars($commission['client_email']) ?></td>
                    <td><?= htmlspecialchars($commission['voucher_datetime']) ?></td>


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
                    <td class="text-center">
                        <?php if ($isAdmin): ?>
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input check-status" type="checkbox" role="switch"
                                    data-id="<?= $commission['id'] ?>" <?= $commission['is_checked'] ? 'checked' : '' ?>>
                            </div>
                        <?php else: ?>
                            <span class="badge rounded-pill <?= $commission['is_checked'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $commission['is_checked'] ? 'Sí' : 'No' ?>
                            </span>
                        <?php endif; ?>
                    </td>





                    <td><?= htmlspecialchars($commission['created_at']) ?></td>
                    <td>
                        <?php
                        $stmt = $pdo->prepare("SELECT file_link FROM commission_files WHERE commission_id = ?");
                        $stmt->execute([$commission['id']]);
                        $comprobantes = $stmt->fetchAll(PDO::FETCH_COLUMN); // Obtener solo los valores (enlaces)
                    
                        // Convertir a JSON para pasarlo a la función de JavaScript
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
        <?= json_encode($comprobantes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        "<?= isset($commission['client_email']) ? htmlspecialchars($commission['client_email'], ENT_QUOTES) : '' ?>",
        "<?= isset($commission['voucher_datetime']) ? htmlspecialchars($commission['voucher_datetime'], ENT_QUOTES) : '' ?>"
    )'>
                            Editar
                        </button>


                        <?php if ($isAdmin): ?>
                            <a href="test.php?delete=<?= $commission['id'] ?>"
                                class="btn btn-danger btn-sm">Eliminar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<!-- Modal para agregar/editar comisión -->
<div class="modal fade" id="commissionModal" tabindex="-1" aria-labelledby="commissionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commissionModalLabel">Registrar Comisión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <!-- En tu modal, asegúrate de tener estos campos -->
                    <div class="mb-3">
                        <label for="operation_number" class="form-label"><strong>Número de Operación</strong></label>
                        <input type="text" id="operation_number" name="operation_number" class="form-control" required>
                        <div class="invalid-feedback" id="op-feedback"></div>
                    </div>

                    <div class="mb-3">
                        <label for="client_email" class="form-label">Correo del Cliente</label>
                        <input type="email" id="client_email" name="client_email" class="form-control" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="mb-3">
                        <label for="voucher_datetime" class="form-label">Fecha del Comprobante</label>
                        <input type="date" id="voucher_datetime" name="voucher_datetime" class="form-control" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <hr>
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
                        <input id="channel" name="channel" class="form-control" list="canales"
                            placeholder="Selecciona o escribe un canal" required>
                        <datalist id="canales">
                            <option value="WhatsApp Premium">
                            <option value="WhatsApp Hazla">
                            <option value="Messenger Tx">
                            <option value="Messenger Hazla">
                            <option value="Messenger Premium">
                        </datalist>
                    </div>
                    <hr>


                    <div class="mb-3">
                        <label for="link" class="form-label">Comprobantes (Enlaces de Google Drive)</label>
                        <div id="comprobantesContainer">
                            <input type="url" name="links[]" class="form-control mb-2"
                                placeholder="Pega el enlace del comprobante">
                        </div>
                        <hr>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="agregarComprobante()">Agregar
                            más enlaces</button>
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
    function editCommission(id, productName, price, channel, operationNumber, description, links, clientEmail, voucherDatetime) {
        document.getElementById("commission_id").value = id;
        document.getElementById("product_name").value = productName;
        document.getElementById("price").value = price;
        document.getElementById("channel").value = channel;
        document.getElementById("operation_number").value = operationNumber;
        document.getElementById("description").value = description && description !== "null" ? description : "";
        document.getElementById("client_email").value = clientEmail || "";

        // Formatear la fecha/hora para el input datetime-local
        // Formatear la fecha para el input date (solo fecha)
        if (voucherDatetime) {
            let date = new Date(voucherDatetime);
            let formattedDate = date.toISOString().split('T')[0];
            document.getElementById("voucher_datetime").value = formattedDate;
        } else {
            document.getElementById("voucher_datetime").value = "";
        }

        document.querySelector("#commissionModal .modal-title").textContent = "Editar Comisión";

        let container = document.getElementById("comprobantesContainer");
        container.innerHTML = '';

        // Si links es un string JSON, lo convertimos a array
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
            order: [[0, 'desc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            }
        });
    });
</script>
<script>
    document.querySelectorAll('.check-status').forEach(switchButton => {
        switchButton.addEventListener('change', function () {
            let commissionId = this.dataset.id;
            let isChecked = this.checked ? 1 : 0;

            fetch('test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `update_checked=1&id=${commissionId}&is_checked=${isChecked}`
            })
                .then(response => response.json()) // Intenta convertir la respuesta en JSON
                .then(data => {
                    if (!data.success) {
                        console.error("Error en la respuesta:", data.message);
                        alert("Error al actualizar el estado: " + data.message);
                    }
                })
                .catch(error => console.error("Error en el fetch:", error));
        });
    });


</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
</body>

</html>
<script>
    const opInput = document.getElementById('operation_number');
    const emailInput = document.getElementById('client_email');
    const dateInput = document.getElementById('voucher_datetime');
    const opFeedback = document.getElementById('op-feedback');
    const emailFeedback = document.querySelector('#client_email + .invalid-feedback');
    const dateFeedback = document.querySelector('#voucher_datetime + .invalid-feedback');
    const commissionIdInput = document.getElementById('commission_id');
    const form = document.querySelector('#commissionModal form');

    function checkCommissionExists() {
        const operation_number = opInput.value.trim();
        const client_email = emailInput.value.trim();
        const voucher_datetime = dateInput.value;
        const commission_id = commissionIdInput.value;

        // Resetear estados
        opInput.classList.remove('is-invalid', 'is-valid');
        emailInput.classList.remove('is-invalid', 'is-valid');
        dateInput.classList.remove('is-invalid', 'is-valid');
        if (opFeedback) opFeedback.style.display = 'none';
        if (emailFeedback) emailFeedback.style.display = 'none';
        if (dateFeedback) dateFeedback.style.display = 'none';

        // Verificar si hay al menos 2 campos con datos
        const filledFields = [
            operation_number.length >= 3,
            client_email.length >= 3,
            voucher_datetime.length >= 3
        ].filter(Boolean).length;

        if (filledFields < 2) return;

        fetch('test.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax_check=1&operation_number=${encodeURIComponent(operation_number)}&client_email=${encodeURIComponent(client_email)}&voucher_datetime=${encodeURIComponent(voucher_datetime)}&commission_id=${encodeURIComponent(commission_id)}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.exists) {
                    const errorMessages = {

                    };

                    // Marcar todos los campos involucrados en los conflictos
                    data.matchedFields.forEach(field => {
                        if (field === 'operation_number') {
                            opInput.classList.add('is-invalid');
                            if (opFeedback) {
                                opFeedback.textContent = 'Este número de operación coincide con una fecha o correo ya registrado';
                                opFeedback.style.display = 'block';
                            }
                        }
                        if (field === 'client_email') {
                            emailInput.classList.add('is-invalid');
                            if (emailFeedback) {
                                emailFeedback.textContent = 'Este correo coincide con un numero de opración o fecha ya registrado';
                                emailFeedback.style.display = 'block';
                            }
                        }
                        if (field === 'voucher_datetime') {
                            dateInput.classList.add('is-invalid');
                            if (dateFeedback) {
                                dateFeedback.textContent = 'Esta fecha coincide con un numero de opreción o correo ya registrado';
                                dateFeedback.style.display = 'block';
                            }
                        }
                    });

                    // Mostrar mensaje general con los conflictos específicos
                    if (data.conflicts && data.conflicts.length > 0) {
                        const conflictMessages = data.conflicts.map(conflict => {
                            const key = conflict.join('_');
                            return errorMessages[key] || `Conflicto en ${conflict.join(' y ')}`;
                        });

                        const alertPlaceholder = document.getElementById('liveAlertPlaceholder');
                        if (alertPlaceholder) {
                            const wrapper = document.createElement('div');
                            wrapper.innerHTML = [
                                '<div class="alert alert-warning alert-dismissible fade show" role="alert">',
                                '   <strong>Ten cuidado al ingresar comisiones ya ingresadas por otro usuario</strong><br>',

                                '   <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>',
                                '</div>'
                            ].join('');
                            alertPlaceholder.innerHTML = '';
                            alertPlaceholder.appendChild(wrapper);
                        }
                    }
                } else {
                    // Marcar como válidos los campos con datos
                    if (operation_number.length >= 3) opInput.classList.add('is-valid');
                    if (client_email.length >= 3) emailInput.classList.add('is-valid');
                    if (voucher_datetime.length >= 3) dateInput.classList.add('is-valid');
                }
            })
            .catch(error => console.error("Error:", error));
    }

    // Event listeners para los tres campos
    [opInput, emailInput, dateInput].forEach(input => {
        if (input) {
            input.addEventListener('input', checkCommissionExists);
            input.addEventListener('change', checkCommissionExists);
        }
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            const invalidFields = form.querySelectorAll('.is-invalid');
            if (invalidFields.length > 0) {
                e.preventDefault();
                invalidFields[0].focus();
            }
        });
    }
</script>