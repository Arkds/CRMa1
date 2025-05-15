<?php
require 'db.php';
session_start();

// Verificar autenticación y permisos
if (!isset($_COOKIE['user_session'])) {
    header("Location: login.php");
    exit;
}

$user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
if ($user_data['role'] !== 'admin') {
    die("Acceso denegado. Solo administradores pueden configurar el programa.");
}

// Función para ejecutar comandos en segundo plano
function runInBackground($scriptPath)
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        pclose(popen("start /B php $scriptPath", "r"));
    } else {
        exec("php $scriptPath > /dev/null 2>&1 &");
    }
}

// Procesar el formulario de inicio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iniciar_programa'])) {
    $fecha_inicio = $_POST['fecha_inicio'];

    // Validación básica de fecha
    if (!strtotime($fecha_inicio)) {
        $_SESSION['error'] = "Fecha no válida";
        header("Location: iniciar_programa_puntos.php");
        exit;
    }

    try {
        // Desactivar autocommit
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);

        // Iniciar transacción explícitamente
        if (!$pdo->beginTransaction()) {
            throw new Exception("No se pudo iniciar la transacción");
        }

        // 1. Insertar/Actualizar metadatos de reseteo
        $stmt = $pdo->prepare("INSERT INTO metadatos_reseteo (ultima_fecha_grupal) VALUES (?) 
                              ON DUPLICATE KEY UPDATE ultima_fecha_grupal = ?");
        if (!$stmt->execute([$fecha_inicio, $fecha_inicio])) {
            throw new Exception("Error al actualizar metadatos");
        }

        // 2. Limpiar puntos históricos
        if ($pdo->exec("UPDATE users SET puntos_historicos = 0") === false) {
            throw new Exception("Error al limpiar puntos");
        }

        // 3. Limpiar historial
        if ($pdo->exec("TRUNCATE TABLE historial_puntos_historicos") === false) {
            throw new Exception("Error al limpiar historial");
        }

        // 4. Limpiar solicitudes pendientes
        if ($pdo->exec("DELETE FROM solicitudes_puntos WHERE estado = 'pendiente'") === false) {
            throw new Exception("Error al limpiar solicitudes");
        }

        // 5. Resetear ventas
        $updateSales = $pdo->prepare("UPDATE sales SET puntos_asignados = FALSE, puntos_venta = 0 
                                    WHERE DATE(created_at) >= ?");
        if (!$updateSales->execute([$fecha_inicio])) {
            throw new Exception("Error al resetear ventas");
        }

        // Confirmar transacción
        if (!$pdo->commit()) {
            throw new Exception("Error al confirmar cambios");
        }

        // Reactivar autocommit
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);

        // Procesar ventas históricas si es fecha pasada
        if (strtotime($fecha_inicio) <= time()) {
            $script_path = realpath('asignar_puntos_historicos.php');
            runInBackground($script_path);
        }

        $_SESSION['success_message'] = "Programa iniciado desde $fecha_inicio";
        header("Location: super_recompensas.php");
        exit;

    } catch (Exception $e) {
        // Solo hacer rollback si hay transacción activa
        if ($pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (PDOException $rollbackEx) {
                error_log("Error en rollback: " . $rollbackEx->getMessage());
            }
        }

        // Reactivar autocommit
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);

        $_SESSION['error'] = "Error al iniciar programa: " . $e->getMessage();
        header("Location: iniciar_programa_puntos.php");
        exit;
    }
}

// Verificar autenticación
if (!isset($_COOKIE['user_session'])) {
    header("Location: login.php");
    exit;
}

$user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
$isAdmin = ($user_data['role'] === 'admin');

if (!$isAdmin) {
    die("Acceso denegado. Solo administradores pueden ver esta página.");
}

// Verificar si el programa está activo
$stmt = $pdo->query("SELECT ultima_fecha_grupal FROM metadatos_reseteo LIMIT 1");
$metadatos = $stmt->fetch();

//if (!$metadatos) {
//    $_SESSION['error'] = "El programa de puntos históricos no ha sido iniciado. <a href='iniciar_programa_puntos.php'>Configurar ahora</a>";
//    header("Location: index.php");
//    exit;
//}

// Definir recompensas históricas
$recompensas_historicas = [
    40000 => "Snack o bebida",
    100000 => "Entrada al cine",
    170000 => "Bono Yape S/30",
    280000 => "Audífonos",
    500000 => "Teclado",
    850000 => "Día libre",
    1600000 => "Tablet",
    2800000 => "TV - laptop básica ",
    4000000 => "¡Viaje doble pagado! "

];

// Procesar acciones del admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['actualizar_estado_reclamo']) && isset($_POST['reclamo_id'])) {
        $reclamo_id = (int) $_POST['reclamo_id'];
        $nuevo_estado = isset($_POST['estado_pagado']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE recompensas_historicas_reclamadas_usuarios SET pagada = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $reclamo_id]);

        $_SESSION['mensaje'] = "Estado de recompensa actualizado.";
        header("Location: iniciar_programa_puntos.php");
        exit;
    }

    if (isset($_POST['aprobar_solicitud']) || isset($_POST['rechazar_solicitud'])) {
        $solicitud_id = $_POST['solicitud_id'];
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_puntos WHERE id = ?");
        $stmt->execute([$solicitud_id]);
        $solicitud = $stmt->fetch();

        if ($solicitud) {
            if (isset($_POST['aprobar_solicitud'])) {
                $puntos = match ($solicitud['tipo']) {
                    'venta_dificil' => 100,
                    'seguimiento_3ventas' => 300,
                    'ventas_cruzadas' => 150,
                    default => 0
                };

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE users SET puntos_historicos = puntos_historicos + ? WHERE id = ?");
                $stmt->execute([$puntos, $solicitud['user_id']]);

                $stmt = $pdo->prepare("INSERT INTO historial_puntos_historicos 
                                      (user_id, puntos, tipo, comentario, evidencia, admin_id) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $solicitud['user_id'],
                    $puntos,
                    $solicitud['tipo'],
                    $solicitud['comentario'] ?? 'Solicitud aprobada',
                    $solicitud['evidencia'],
                    $user_data['user_id']
                ]);

                $stmt = $pdo->prepare("UPDATE solicitudes_puntos 
                                      SET estado = 'aprobado', puntos_asignados = ?,
                                          admin_id = ?, fecha_revision = NOW()
                                      WHERE id = ?");
                $stmt->execute([$puntos, $user_data['user_id'], $solicitud_id]);
                $pdo->commit();

                $_SESSION['mensaje'] = "Solicitud aprobada y puntos asignados";
            } else {
                $comentario_rechazo = $_POST['comentario_rechazo'] ?? '';
                $stmt = $pdo->prepare("UPDATE solicitudes_puntos 
                                      SET estado = 'rechazado', admin_id = ?,
                                          comentario_rechazo = ?, fecha_revision = NOW()
                                      WHERE id = ?");
                $stmt->execute([$user_data['user_id'], $comentario_rechazo, $solicitud_id]);
                $_SESSION['mensaje'] = "Solicitud rechazada correctamente";
            }
        }
        header("Location: super_recompensas.php");
        exit;
    }

    // Procesar puntos manuales del admin
    if (isset($_POST['agregar_puntos'])) {
        $tipo = $_POST['tipo'];
        $vendedor_id = $_POST['vendedor_id'];
        $comentario = $_POST['comentario'] ?? '';

        $puntos = match ($tipo) {
            'sin_errores_semana' => 100,
            'error_registro' => -150,
            'falta_sin_aviso' => -300,
            'engano_inventar' => -1000,
            default => 0
        };

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE users SET puntos_historicos = puntos_historicos + ? WHERE id = ?");
        $stmt->execute([$puntos, $vendedor_id]);

        $stmt = $pdo->prepare("INSERT INTO historial_puntos_historicos 
                              (user_id, puntos, tipo, comentario, admin_id) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$vendedor_id, $puntos, $tipo, $comentario, $user_data['user_id']]);
        $pdo->commit();

        $_SESSION['mensaje'] = "Puntos asignados correctamente";
        header("Location: super_recompensas.php");
        exit;
    }
}

// Obtener todos los vendedores con sus puntos
$stmt = $pdo->query("
    SELECT 
        u.id, 
        u.username,
        u.puntos_historicos,
        COUNT(s.id) as total_ventas,
        SUM(s.puntos_venta) as puntos_ventas,
        (SELECT COUNT(*) FROM solicitudes_puntos sp WHERE sp.user_id = u.id AND sp.estado = 'aprobado') as solicitudes_aprobadas
    FROM users u
    LEFT JOIN sales s ON u.id = s.user_id AND s.puntos_asignados = TRUE
    WHERE u.role = 'vendedor'
    GROUP BY u.id
    ORDER BY u.puntos_historicos DESC
");
$vendedores = $stmt->fetchAll();

// Obtener solicitudes pendientes
$stmt = $pdo->prepare("SELECT s.*, u.username as vendedor 
                      FROM solicitudes_puntos s
                      JOIN users u ON s.user_id = u.id
                      WHERE s.estado = 'pendiente'
                      ORDER BY s.fecha_solicitud DESC");
$stmt->execute();
$solicitudes_pendientes = $stmt->fetchAll();
$username = $user_data['username'];

include('header.php');
?>

<div class="container mt-5">
    <h1 class="text-center">Configuración del Programa de Puntos Históricos</h1>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <h3>Iniciar Nuevo Programa</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <strong>¡Advertencia!</strong> Al iniciar un nuevo programa:
                <ul>
                    <li>Todos los puntos históricos se reiniciarán a cero</li>
                    <li>Se borrará todo el historial de puntos</li>
                    <li>Se cancelarán todas las solicitudes pendientes</li>
                    <li>Si la fecha es pasada, se procesarán automáticamente todas las ventas desde esa fecha</li>
                </ul>
            </div>

            <form method="POST">
                <div class="mb-3">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio"
                        value="<?= date('Y-m-d') ?>" required>
                    <small class="text-muted">Puedes seleccionar una fecha pasada o futura</small>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" name="iniciar_programa" class="btn btn-danger"
                        onclick="return confirm('¿Estás seguro de iniciar un nuevo programa? Esta acción NO se puede deshacer.')">
                        Iniciar Nuevo Programa de Puntos
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Estado actual del programa -->
    <div class="card mt-4">
        <div class="card-header">
            <h3>Estado Actual del Programa</h3>
        </div>
        <div class="card-body">
            <?php
            $stmt = $pdo->query("SELECT * FROM metadatos_reseteo LIMIT 1");
            $metadatos = $stmt->fetch();

            $stmt = $pdo->query("SELECT SUM(puntos_historicos) as total_puntos FROM users");
            $total_puntos = $stmt->fetch()['total_puntos'];
            ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Fecha de Inicio</h5>
                            <p class="card-text display-6">
                                <?= $metadatos ? date('d/m/Y', strtotime($metadatos['ultima_fecha_grupal'])) : 'No iniciado' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Puntos Asignados</h5>
                            <p class="card-text display-6">
                                <?= number_format($total_puntos) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-4">


        <!-- Barras de progreso de todos los vendedores -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Progreso de Todos los Vendedores</h3>
            </div>
            <div class="card-body">
                <?php foreach ($vendedores as $vendedor): ?>
                    <?php
                    if ($vendedor['puntos_historicos'] == 0)
                        continue; // 👈 Saltar vendedores sin puntos
                
                    $max_puntos = 4000000; // Puntos de la máxima recompensa
                    $porcentaje = min(100, ($vendedor['puntos_historicos'] / $max_puntos) * 100);
                    ?>

                    <div class="mb-4 p-3 border rounded">
                        <div class="d-flex justify-content-between mb-2">
                            <h5><?= htmlspecialchars($vendedor['username']) ?></h5>
                            <span class="badge bg-dark"><?= number_format($vendedor['puntos_historicos']) ?> pts</span>
                        </div>

                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped" role="progressbar"
                                style="width: <?= $porcentaje ?>%" aria-valuenow="<?= $vendedor['puntos_historicos'] ?>"
                                aria-valuemin="0" aria-valuemax="<?= $max_puntos ?>">
                            </div>
                        </div>

                        <!-- Marcas de recompensas -->
                        <div class="progress-marks mt-2" style="position: relative; height: 30px;">
                            <?php foreach ($recompensas_historicas as $puntos => $desc): ?>
                                <?php $posicion = ($puntos / $max_puntos) * 100; ?>
                                <div style="position: absolute; left: <?= $posicion ?>%; transform: translateX(-50%);">
                                    <div
                                        style="width: 2px; height: 15px; 
                                     background: <?= $vendedor['puntos_historicos'] >= $puntos ? '#28a745' : '#dc3545' ?>;">
                                    </div>
                                    <small style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%); 
                                       white-space: nowrap; font-size: 10px;">
                                        <?= number_format($puntos) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Recompensas alcanzadas -->

                        <div class="mt-2">
                            <?php
                            $recompensas_alcanzadas = array_filter(
                                $recompensas_historicas,
                                fn($p) => $vendedor['puntos_historicos'] >= $p,
                                ARRAY_FILTER_USE_KEY
                            );
                            ?>

                            <?php if (!empty($recompensas_alcanzadas)): ?>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <?php foreach ($recompensas_alcanzadas as $puntos => $desc): ?>
                                        <span class="badge bg-success"><?= $desc ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Aún no alcanza recompensas</span>
                            <?php endif; ?>
                        </div>
                        <?php
                        if ($vendedor['puntos_historicos'] > 0):
                            // Obtener recompensas reclamadas por el vendedor
                            $stmt = $pdo->prepare("SELECT recompensa, puntos_usados, pagada, fecha_reclamo 
                           FROM recompensas_historicas_reclamadas_usuarios 
                           WHERE user_id = ? ORDER BY fecha_reclamo DESC");
                            $stmt->execute([$vendedor['id']]);
                            $reclamos = $stmt->fetchAll();

                            if (!empty($reclamos)):
                                ?>
                                <div class="mt-3">
                                    <h6>🎁 Recompensas Reclamadas</h6>
                                    <ul class="list-group">
                                        <?php foreach ($reclamos as $r): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <?= htmlspecialchars($r['recompensa']) ?><br>
                                                    <small
                                                        class="text-muted"><?= date('d/m/Y H:i', strtotime($r['fecha_reclamo'])) ?></small>
                                                </div>
                                                <?php if ($r['pagada']): ?>
                                                    <span class="badge bg-success">Pagada</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php
                            endif;
                        endif;
                        ?>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        $stmt = $pdo->prepare("SELECT r.id, r.recompensa, r.puntos_usados, r.pagada, r.fecha_reclamo, u.username 
                       FROM recompensas_historicas_reclamadas_usuarios r
                       JOIN users u ON r.user_id = u.id
                       ORDER BY r.fecha_reclamo DESC");
        $stmt->execute();
        $recompensas_reclamadas = $stmt->fetchAll();

        if (!empty($recompensas_reclamadas)):
            ?>
            <div class="card mt-5">
                <div class="card-header bg-secondary text-white">
                    <h4>Recompensas Reclamadas (Gestión de Pago)</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla_recompensas" class="table table-striped table-bordered table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>Vendedor</th>
                                    <th>Recompensa</th>
                                    <th>Puntos usados</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recompensas_reclamadas as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['username']) ?></td>
                                        <td><?= htmlspecialchars($r['recompensa']) ?></td>
                                        <td><?= number_format($r['puntos_usados']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($r['fecha_reclamo'])) ?></td>
                                        <td>
                                            <?php if ($r['pagada']): ?>
                                                <span class="badge bg-success">Pagada</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="actualizar_estado_reclamo" value="1">
                                                <input type="hidden" name="reclamo_id" value="<?= $r['id'] ?>">
                                                <button type="submit" name="estado_pagado" value="1"
                                                    class="btn btn-sm <?= $r['pagada'] ? 'btn-secondary' : 'btn-success' ?>"
                                                    <?= $r['pagada'] ? 'disabled' : '' ?>>
                                                    Marcar como Pagada
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>



    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Manejar modal de rechazo
            const rechazoModal = new bootstrap.Modal(document.getElementById('rechazoModal'));
            document.querySelectorAll('[data-bs-target="#rechazoModal"]').forEach(button => {
                button.addEventListener('click', function () {
                    document.getElementById('rechazo_solicitud_id').value =
                        this.getAttribute('data-solicitud-id');
                });
            });

            // Opcional: Hacer las barras de progreso animadas
            setTimeout(() => {
                document.querySelectorAll('.progress-bar').forEach(bar => {
                    bar.classList.add('progress-bar-animated');
                });
            }, 500);
        });
    </script>

    <style>
        .progress-marks {
            position: relative;
            height: 40px;
        }

        .progress-marks small {
            font-size: 10px;
            font-weight: bold;
        }

        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .border {
            border: 1px solid #dee2e6 !important;
            border-radius: 5px;
        }
    </style>
    <?php
    if (isset($pdo)) {
        $pdo = null;
    }
    ?>


    <?php include('footer.php'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#tabla_recompensas').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                order: [[3, 'desc']],
                pageLength: 10
            });
        });
    </script>