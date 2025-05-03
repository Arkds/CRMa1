<?php

// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (!ob_get_level()) {
    ob_start();
}


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

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación COHERENTE con reports_crud.php
if (isset($_COOKIE['user_session'])) {
    $user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
    if ($user_data) {
        $_SESSION['user_id'] = $user_data['user_id'];
        $_SESSION['username'] = $user_data['username'];
        $_SESSION['role'] = $user_data['role'];

    } else {

        header("Location: login.php");
        exit;
    }
} elseif (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Obtener el ID del usuario logueado
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'vendedor';

// Solo el endpoint API

require 'db.php'; // Asegúrate que este archivo tiene la conexión correcta



// Acción para verificar código (AJAX)
if (isset($_GET['verificar_codigo'])) {
    header('Content-Type: application/json');

    $codigo = $_GET['codigo'] ?? '';
    $current_id = $_GET['current_id'] ?? null;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificados_emitidos 
                          WHERE codigo_unico = ? AND id != ?");
    $stmt->execute([$codigo, $current_id]);
    $existe = $stmt->fetchColumn() > 0;

    echo json_encode(['existe' => $existe]);
    exit;
}

// Acciones CRUD principales
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id = $_POST['id'] ?? $_GET['id'] ?? null;
// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_certificado'])) {
    if (empty($_POST['cliente_nombre']) || empty($_POST['codigo_unico'])) {
        $_SESSION['error_message'] = "Nombre del cliente y código son requeridos!";
        header("Location: certificaciones.php");
        exit();
    }
    $es_especial = isset($_POST['es_especial']) && $_POST['es_especial'] == 1;
    $curso_id = $es_especial ? NULL : ($_POST['curso_id'] ?? NULL);
    // Asegurar valor por defecto
    $curso_id = null;
    if (!$es_especial && isset($_POST['curso_id']) && !empty($_POST['curso_id'])) {
        $curso_id = (int) $_POST['curso_id'];
    }

    $nombre_curso_manual = $es_especial ? ($_POST['nombre_curso_manual'] ?? '') : null;
    $nombre_curso_manual = $es_especial ? ($_POST['nombre_curso_manual'] ?? '') : null;
    $cliente_nombre = $_POST['cliente_nombre'] ?? '';
    $cliente_email = $_POST['cliente_email'] ?? '';
    $codigo_unico = $_POST['codigo_unico'] ?? '';

    // Debug: Verificar datos recibidos
    error_log("Datos recibidos:");
    error_log("Es especial: " . ($es_especial ? 'Sí' : 'No'));
    error_log("Curso ID: " . $curso_id);
    error_log("Nombre curso manual: " . $nombre_curso_manual);
    error_log("Código único: " . $codigo_unico);

    // Validación de código único
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificados_emitidos WHERE codigo_unico = ? AND id != ?");
    $stmt->execute([$codigo_unico, $id ?? 0]);

    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error_message'] = "El código ya está en uso!";
        header("Location: certificaciones.php");
        exit();
    }

    if ($action === 'create') {
        try {
            $stmt = $pdo->prepare("INSERT INTO certificados_emitidos 
                    (codigo_unico, curso_id, cliente_nombre, cliente_email, 
                     es_especial, nombre_curso_manual, user_id,
                     fecha_inicio, fecha_fin, duracion_horas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $result = $stmt->execute([
                $codigo_unico,
                $curso_id,
                $cliente_nombre,
                $cliente_email,
                $es_especial ? 1 : 0,
                $nombre_curso_manual,
                $user_id,
                $_POST['fecha_inicio'],
                $_POST['fecha_fin'],
                $_POST['duracion_horas']
            ]);
            if (!$result) {
                throw new Exception("Error al guardar: " . implode(" ", $stmt->errorInfo()));
            }
            if (!empty($_POST['temas_json'])) {
                $temas = json_decode($_POST['temas_json'], true);
                if (is_array($temas)) {
                    $cert_id = $pdo->lastInsertId() ?: $id;
                    $stmtTema = $pdo->prepare("INSERT INTO certificado_temas (certificado_id, titulo_tema, nota) VALUES (?, ?, ?)");
                    foreach ($temas as $tema) {
                        $stmtTema->execute([$cert_id, $tema['tema'], $tema['nota']]);
                    }
                }
            }


            $_SESSION['success_message'] = "Certificado generado correctamente!";
            header("Location: certificaciones.php");
            exit();

        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error de base de datos: " . $e->getMessage();
            header("Location: certificaciones.php");
            exit();
        }
    } elseif ($action === 'edit' && $id) {
        try {
            // Primero obtenemos el certificado para verificar el usuario
            $stmt = $pdo->prepare("SELECT user_id FROM certificados_emitidos WHERE id = ?");
            $stmt->execute([$id]);
            $cert = $stmt->fetch();

            if (!$cert) {
                throw new Exception("Certificado no encontrado");
            }

            // Verificar que el usuario sea el creador o admin
            if ($cert['user_id'] != $user_id && $_SESSION['role'] !== 'admin') {
                $_SESSION['error_message'] = "No tienes permiso para editar este certificado";
                header("Location: certificaciones.php");
                exit();
            }

            $stmt = $pdo->prepare("UPDATE certificados_emitidos SET 
                codigo_unico = ?, curso_id = ?, cliente_nombre = ?, cliente_email = ?, 
                es_especial = ?, nombre_curso_manual = ?, fecha_inicio = ?, 
                fecha_fin = ?, duracion_horas = ?
                WHERE id = ?");

            $result = $stmt->execute([
                $codigo_unico,
                $curso_id,
                $cliente_nombre,
                $cliente_email,
                $es_especial ? 1 : 0,
                $nombre_curso_manual,
                $_POST['fecha_inicio'],
                $_POST['fecha_fin'],
                $_POST['duracion_horas'],
                $id
            ]);

            if (!$result) {
                throw new Exception("Error al actualizar: " . implode(" ", $stmt->errorInfo()));
            }
            // Si hay temas enviados, eliminar los antiguos y guardar los nuevos
            if (!empty($_POST['temas_json'])) {
                // Eliminar anteriores
                $pdo->prepare("DELETE FROM certificado_temas WHERE certificado_id = ?")->execute([$id]);
            
                // Insertar nuevos
                $temas = json_decode($_POST['temas_json'], true);
                if (is_array($temas)) {
                    $stmtTema = $pdo->prepare("INSERT INTO certificado_temas (certificado_id, titulo_tema, nota) VALUES (?, ?, ?)");
                    foreach ($temas as $tema) {
                        $stmtTema->execute([$id, $tema['tema'], $tema['nota']]);
                    }
                }
            }


            $_SESSION['success_message'] = "Certificado actualizado correctamente!";
            header("Location: certificaciones.php");
            exit();

        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error de base de datos: " . $e->getMessage();
            header("Location: certificaciones.php");
            exit();
        }
    }


}
// En el endpoint get_certificado (PHP):
if (isset($_GET['action']) && $_GET['action'] === 'get_certificado' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT * FROM certificados_emitidos WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $certificado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($certificado) {
        // Obtener datos adicionales para el formulario
        $certificado['es_especial_checked'] = $certificado['es_especial'] ? 1 : 0;
        $certificado['nombre_curso_manual_value'] = $certificado['nombre_curso_manual'] ?? '';
        
        // Añadir fechas y duración
        $certificado['fecha_inicio_value'] = $certificado['fecha_inicio'] ?? '';
        $certificado['fecha_fin_value'] = $certificado['fecha_fin'] ?? '';
        $certificado['duracion_horas_value'] = $certificado['duracion_horas'] ?? '';
        // Obtener temas del reverso
        $stmt = $pdo->prepare("SELECT titulo_tema, nota FROM certificado_temas WHERE certificado_id = ?");
        $stmt->execute([$certificado['id']]);
        $temas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $certificado['temas'] = $temas;


        // Solo buscar prefijo si no es especial
        if (!$certificado['es_especial']) {
            $stmt = $pdo->prepare("SELECT prefijo_codigo FROM cursos_certificados WHERE id = ?");
            $stmt->execute([$certificado['curso_id']]);
            $curso = $stmt->fetch(PDO::FETCH_ASSOC);
            $certificado['curso_prefijo'] = $curso['prefijo_codigo'] ?? '';
        } else {
            $certificado['curso_prefijo'] = 'ESP';
        }
    }

    echo json_encode($certificado ?: []);
    exit;
}
// Obtener datos para mostrar
$cursos = $pdo->query("SELECT * FROM cursos_certificados")->fetchAll();
$certificados = $pdo->query("
    SELECT 
        ce.*, 
        COALESCE(cc.nombre_curso, ce.nombre_curso_manual) as nombre_curso,
        COALESCE(cc.prefijo_codigo, 'ESP') as prefijo_codigo,
        ce.es_especial,
        u.username as creador
    FROM certificados_emitidos ce
    LEFT JOIN cursos_certificados cc ON ce.curso_id = cc.id AND ce.es_especial = 0
    LEFT JOIN users u ON ce.user_id = u.id
    ORDER BY ce.fecha_generacion DESC
")->fetchAll();

// Datos para edición
$certificado_editar = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM certificados_emitidos WHERE id = ?");
    $stmt->execute([$id]);
    $certificado_editar = $stmt->fetch();
}

include('header.php');

$pdo = null;

?>

<div class="container mt-5">
    <!-- Mostrar mensajes -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <h1 class="text-center">Gestión de Certificados</h1>

    <!-- Botón para abrir modal -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#certificadoModal">
        Generar Nuevo Certificado
    </button>
    <button class="btn btn-info mb-3"
        onclick="window.location.href='https://certificados-edu.a1cursosmaster.com';">Consultar certificados</button>



    <!-- Tabla de certificados -->
    <table id="certificadosTable" class="table table-striped">
        <thead>
            <tr>
                <th>Código</th>
                <th>Curso</th>
                <th>Cliente</th>
                <th>Email</th>
                <th>Fecha</th>
                <th>Creado por</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($certificados as $cert): ?>
                <tr>
                    <td><?= $cert['codigo_unico'] ?></td>
                    <td><?= $cert['nombre_curso'] ?></td>
                    <td><?= $cert['cliente_nombre'] ?></td>
                    <td><?= $cert['cliente_email'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($cert['fecha_generacion'])) ?></td>
                    <td><?= $cert['creador'] ?? 'Sistema' ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm editar-btn" data-id="<?= $cert['id'] ?>"
                            data-bs-toggle="modal" data-bs-target="#certificadoModal">
                            Editar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal para crear/editar certificados -->
<div class="modal fade" id="certificadoModal" tabindex="-1" aria-labelledby="certificadoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="certificadoModalLabel">Nuevo Certificado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="certificadoForm" method="POST" action="certificaciones.php">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="certificadoId" value="">
                    <input type="hidden" name="submit_certificado" value="1">

                    <!-- Selección de curso -->
                    <!-- Selección de curso con autocompletado -->
                    <!-- Selección de curso con autocompletado obligatorio -->
                    <div class="mb-3">
                        <label for="curso_input" class="form-label">Curso</label>
                        <input type="text" class="form-control" id="curso_input" list="cursos_list"
                            placeholder="Escribe para buscar cursos..." required autocomplete="off">
                        <input type="hidden" id="curso_id" name="curso_id" required>
                        <datalist id="cursos_list">
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?= htmlspecialchars($curso['nombre_curso']) ?>"
                                    data-id="<?= $curso['id'] ?>" data-prefijo="<?= $curso['prefijo_codigo'] ?>">
                                <?php endforeach; ?>
                        </datalist>
                        <div class="invalid-feedback">Por favor seleccione un curso válido de la lista</div>
                    </div>
                    <!-- Asegurar que los campos estén correctamente configurados -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="es_especial" name="es_especial" value="1">
                        <label class="form-check-label" for="es_especial">Certificado Especial</label>
                    </div>

                    <div id="curso-especial-container" style="display: none;" class="mb-3">
                        <label for="nombre_curso_manual" class="form-label">Nombre del Curso Especial</label>
                        <input type="text" class="form-control" id="nombre_curso_manual" name="nombre_curso_manual">
                    </div>

                    <!-- Código único -->
                    <div class="mb-3">
                        <label for="codigo_unico" class="form-label">Código del Certificado</label>
                        <div class="input-group">
                            <span class="input-group-text" id="prefijo-display">PREFIJO</span>
                            <input type="text" class="form-control" id="codigo_unico" name="codigo_unico" required>
                            <button class="btn btn-outline-secondary" type="button" id="generar-codigo">
                                Generar
                            </button>
                        </div>
                        <div id="codigo-feedback" class="invalid-feedback">El código ya está en uso</div>
                    </div>

                    <!-- Datos del cliente -->
                    <div class="mb-3">
                        <label for="cliente_nombre" class="form-label">Nombre del Cliente</label>
                        <input type="text" class="form-control" id="cliente_nombre" name="cliente_nombre" required>
                    </div>

                    <div class="mb-3">
                        <label for="cliente_email" class="form-label">Email del Cliente</label>
                        <input type="email" class="form-control" id="cliente_email" name="cliente_email">
                    </div>
                    
                    <!-- Fechas del curso -->
                    <div class="mb-3">
                        <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_fin" class="form-label">Fecha de Finalización</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                    </div>
                    
                    <!-- Duración en horas -->
                    <div class="mb-3">
                        <label for="duracion_horas" class="form-label">Duración (horas académicas)</label>
                        <input type="number" class="form-control" id="duracion_horas" name="duracion_horas" min="1" required>
                    </div>
                    
                    <!-- Checkbox para activar reverso -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="habilitar_reverso">
                        <label class="form-check-label" for="habilitar_reverso">Agregar información del reverso</label>
                    </div>
                    
                    <!-- Contenedor del reverso -->
                    <div id="reverso_container" style="display: none;">
                        <h6>Temas del curso y notas</h6>
                        <table class="table table-bordered" id="tablaTemas">
                            <thead>
                                <tr>
                                    <th>Tema</th>
                                    <th>Nota</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <button type="button" class="btn btn-secondary mb-2" id="agregarTema">Agregar Tema</button>
                        <div class="mb-3">
                            <label for="promedio_final">Promedio Final</label>
                            <input type="text" class="form-control" id="promedio_final" readonly>
                        </div>
                    </div>



                    <button type="submit" class="btn btn-primary">Guardar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Scripts para el funcionamiento -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function () {
        // Inicializar DataTable
        $('#certificadosTable').DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            },
            order: [[4, 'desc']]
        });

        // Variables para el estado del modal
        let currentAction = 'create';
        let currentCertificadoId = null;

        // Configurar modal para edición
        // Configurar modal para edición
        $(document).on('click', '.editar-btn', function() {
            const id = $(this).data('id');
            currentAction = 'edit';
            currentCertificadoId = id;
            
            $.get('certificaciones.php?action=get_certificado&id=' + id, function(data) {
                if (data) {
                    $('#certificadoModalLabel').text('Editar Certificado');
                    $('#formAction').val('edit');
                    $('#certificadoId').val(id);
                    
                    // Resetear estado del formulario
                    $('#es_especial').prop('checked', false).trigger('change');
                    $('#curso_input').prop('disabled', false).val('');
                    $('#curso_id').val('');
                    
                    // Manejar certificados especiales
                    if (data.es_especial_checked) {
                        $('#es_especial').prop('checked', true).trigger('change');
                        $('#nombre_curso_manual').val(data.nombre_curso_manual_value);
                        $('#prefijo-display').text('ESP-');
                    } else {
                        // Manejar certificados normales
                        const cursoOption = $(`#cursos_list option[data-id="${data.curso_id}"]`);
                        if (cursoOption.length) {
                            $('#curso_input').val(cursoOption.val())
                                            .removeClass('is-invalid')
                                            .addClass('is-valid');
                            $('#curso_id').val(data.curso_id);
                            $('#prefijo-display').text(cursoOption.data('prefijo') || 'PREFIJO');
                            cursoValidoSeleccionado = true;
                        }
                    }
                    
                    // Rellenar campos de fechas y duración
                    $('#fecha_inicio').val(data.fecha_inicio_value);
                    $('#fecha_fin').val(data.fecha_fin_value);
                    $('#duracion_horas').val(data.duracion_horas_value);
                    
                    $('#codigo_unico').val(data.codigo_unico);
                    $('#cliente_nombre').val(data.cliente_nombre);
                    $('#cliente_email').val(data.cliente_email);
                }
                // Mostrar temas si existen
                if (data.temas && data.temas.length > 0) {
                    $('#habilitar_reverso').prop('checked', true).trigger('change');
                    $('#tablaTemas tbody').empty();
                
                    data.temas.forEach(function (tema) {
                        const fila = `
                            <tr>
                                <td><input type="text" class="form-control tema" value="${tema.titulo_tema}" required></td>
                                <td><input type="number" class="form-control nota" value="${tema.nota}" step="0.01" min="0" max="20" required></td>
                                <td><button type="button" class="btn btn-danger btn-sm eliminarFila">Eliminar</button></td>
                            </tr>
                        `;
                        $('#tablaTemas tbody').append(fila);
                    });
                
                    calcularPromedio();
                } else {
                    $('#habilitar_reverso').prop('checked', false).trigger('change');
                }

            }, 'json');
        });

        // Asegurar que el formulario se envíe correctamente
        $('#certificadoForm').on('submit', function (e) {
            const esEspecial = $('#es_especial').is(':checked');

            // Resetear validaciones
            $('#curso_input').removeClass('is-invalid');
            $('#nombre_curso_manual').removeClass('is-invalid');

            // Validar según el tipo
            if (!esEspecial && !cursoValidoSeleccionado) {
                e.preventDefault();
                $('#curso_input').addClass('is-invalid');
                $('#curso_input').focus();
                return false;
            }

            if (esEspecial && !$('#nombre_curso_manual').val().trim()) {
                e.preventDefault();
                $('#nombre_curso_manual').addClass('is-invalid');
                $('#nombre_curso_manual').focus();
                return false;
            }
            
            if (reversoAct) {
                const temas = [];
                $('#tablaTemas tbody tr').each(function () {
                    const tema = $(this).find('.tema').val().trim();
                    const nota = parseFloat($(this).find('.nota').val());
                    if (tema && !isNaN(nota)) {
                        temas.push({ tema, nota });
                    }
                });
            
                if (temas.length === 0) {
                    alert('Debe agregar al menos un tema con nota válida');
                    return false;
                }
            
                // Guardar en hidden para enviarlo por POST
                if ($('#temas_input').length === 0) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'temas_json',
                        id: 'temas_input',
                        value: JSON.stringify(temas)
                    }).appendTo('#certificadoForm');
                } else {
                    $('#temas_input').val(JSON.stringify(temas));
                }
            }


            return true;
        });
        // Configurar modal para creación
        $('#certificadoModal').on('show.bs.modal', function (e) {
            if (currentAction === 'create') {
                $('#certificadoModalLabel').text('Nuevo Certificado');
                $('#formAction').val('create');
                $('#certificadoId').val('');
                $('#certificadoForm')[0].reset();
                $('#prefijo-display').text('PREFIJO');
            }
        });

        // Actualizar prefijo cuando se selecciona un curso - ESTO YA NO ES NECESARIO
        /*
        $('#curso_id').change(function() {
            const prefijo = $(this).find(':selected').data('prefijo');
            $('#prefijo-display').text(prefijo || 'PREFIJO');
        });
        */
        // Manejar cambio entre certificado normal y especial

        $('#es_especial').change(function () {
            const isChecked = $(this).is(':checked');

            if (isChecked) {
                // Modo especial - deshabilitar curso normal
                $('#curso_input')
                    .val('')
                    .prop('required', false)
                    .prop('disabled', true)
                    .removeClass('is-valid is-invalid');

                $('#curso_id').val('');
                $('#curso-especial-container').show();
                $('#nombre_curso_manual')
                    .prop('required', true)
                    .prop('disabled', false);

                $('#prefijo-display').text('ESP-');
                cursoValidoSeleccionado = true;
            } else {
                // Modo normal - habilitar curso normal
                $('#curso_input')
                    .prop('required', true)
                    .prop('disabled', false);

                $('#curso-especial-container').hide();
                $('#nombre_curso_manual')
                    .val('')
                    .prop('required', false)
                    .prop('disabled', true);

                $('#prefijo-display').text('PREFIJO');
                cursoValidoSeleccionado = false;
            }
        });

        // Asegurar que el campo especial esté deshabilitado inicialmente
        $('#nombre_curso_manual').prop('disabled', true);

        // Generar código - MODIFICADO
        $('#generar-codigo').click(function () {
            const esEspecial = $('#es_especial').is(':checked');

            if (!esEspecial && !cursoValidoSeleccionado) {
                $('#curso_input').addClass('is-invalid');
                alert('Por favor selecciona un curso válido de la lista');
                $('#curso_input').focus();
                return;
            }

            const prefijo = esEspecial ? 'ESP-' : $('#prefijo-display').text();
            const randomPart = Math.random().toString(36).substring(2, 8).toUpperCase();
            $('#codigo_unico').val(`${prefijo}${randomPart}`);
            verificarCodigoUnico();
        });

        $('#codigo_unico').on('input', verificarCodigoUnico);

        function verificarCodigoUnico() {
            const codigo = $('#codigo_unico').val();
            if (codigo.length < 3) return;

            $.ajax({
                url: 'certificaciones.php?verificar_codigo=1&current_id=' + currentCertificadoId + '&codigo=' + encodeURIComponent(codigo),
                method: 'GET',
                success: function (res) {
                    if (res.existe) {
                        $('#codigo_unico').addClass('is-invalid');
                        $('#certificadoForm button[type="submit"]').prop('disabled', true);
                    } else {
                        $('#codigo_unico').removeClass('is-invalid');
                        $('#certificadoForm button[type="submit"]').prop('disabled', false);
                    }
                },
                error: function () {
                    console.error('Error al verificar código');
                }
            });
        }

        let cursoValidoSeleccionado = false;

        // Manejar selección de curso desde el datalist
        $('#curso_input').on('input change', function () {
            const cursoInput = $(this).val();
            const cursoOption = $(`#cursos_list option[value="${cursoInput}"]`);

            if (cursoOption.length) {
                // Curso válido seleccionado
                const cursoId = cursoOption.data('id');
                const prefijo = cursoOption.data('prefijo');

                $('#curso_id').val(cursoId);
                $('#prefijo-display').text(prefijo || 'PREFIJO');
                $(this).removeClass('is-invalid').addClass('is-valid');
                cursoValidoSeleccionado = true;
            } else {
                // No es un curso válido
                $('#curso_id').val('');
                $('#prefijo-display').text('PREFIJO');
                $(this).removeClass('is-valid');
                cursoValidoSeleccionado = false;
            }
        });
        
        let reversoAct = false;

        $('#habilitar_reverso').change(function () {
            reversoAct = this.checked;
            $('#reverso_container').toggle(reversoAct);
        });
        
        // Agregar fila de tema
        $('#agregarTema').click(function () {
            const nuevaFila = `
                <tr>
                    <td><input type="text" class="form-control tema" required></td>
                    <td><input type="number" class="form-control nota" step="0.01" min="0" max="20" required></td>
                    <td><button type="button" class="btn btn-danger btn-sm eliminarFila">Eliminar</button></td>
                </tr>`;
            $('#tablaTemas tbody').append(nuevaFila);
            calcularPromedio();
        });
        
        // Eliminar fila
        $(document).on('click', '.eliminarFila', function () {
            $(this).closest('tr').remove();
            calcularPromedio();
        });
        
        // Calcular promedio
        $(document).on('input', '.nota', function () {
            calcularPromedio();
        });
        
        function calcularPromedio() {
            let total = 0;
            let count = 0;
            $('.nota').each(function () {
                const val = parseFloat($(this).val());
                if (!isNaN(val)) {
                    total += val;
                    count++;
                }
            });
            const promedio = count > 0 ? (total / count).toFixed(2) : '';
            $('#promedio_final').val(promedio);
        }


        // Validar antes de enviar - MODIFICADO
        $('#certificadoForm').on('submit', function (e) {
            const esEspecial = $('#es_especial').is(':checked');

            // Resetear validaciones
            $('#curso_input').removeClass('is-invalid');
            $('#nombre_curso_manual').removeClass('is-invalid');

            // Validar según el tipo
            if (!esEspecial && !cursoValidoSeleccionado) {
                e.preventDefault();
                $('#curso_input').addClass('is-invalid');
                $('#curso_input').focus();
                return false;
            }

            if (esEspecial && !$('#nombre_curso_manual').val().trim()) {
                e.preventDefault();
                $('#nombre_curso_manual').addClass('is-invalid');
                $('#nombre_curso_manual').focus();
                return false;
            }

            if (esEspecial) {
                $('#curso_id').val('0');
            }

            // Debug: Mostrar datos que se enviarán
            console.log("Datos a enviar:", {
                es_especial: esEspecial ? 1 : 0,
                curso_id: $('#curso_id').val(),
                nombre_curso_manual: $('#nombre_curso_manual').val(),
                codigo_unico: $('#codigo_unico').val(),
                cliente_nombre: $('#cliente_nombre').val(),
                cliente_email: $('#cliente_email').val()
            });

            return true;
        });
    });
</script>