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

if (!$metadatos) {
    $_SESSION['error'] = "El programa de puntos históricos no ha sido iniciado. <a href='iniciar_programa_puntos.php'>Configurar ahora</a>";
    header("Location: index.php");
    exit;
}

// Definir recompensas históricas
$recompensas_historicas = [
    8000 => "Snack o bebida",
    15000 => "Entrada al cine",
    25000 => "Bono Yape S/30",
    50000 => "Día libre",
    120000 => "Audífonos Bluetooth",
    200000 => "Teclado mecánico o silla gamer simple",
    550000 => "¡TV, consola o laptop básica! (Desafío a largo plazo)"
];

// Procesar acciones del admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                    $max_puntos = 550000; // Puntos de la máxima recompensa
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
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Panel de administración -->
        <div class="card mt-4">
            <div class="card-header bg-warning">
                <h3>Panel de Administración</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Vendedor</label>
                                <select name="vendedor_id" class="form-select" required>
                                    <?php foreach ($vendedores as $v): ?>
                                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tipo de Puntos</label>
                                <select name="tipo" class="form-select" required>
                                    <option value="sin_errores_semana">Semana sin errores (+100 pts)</option>
                                    <option value="error_registro">Error en registro (-150 pts)</option>
                                    <option value="falta_sin_aviso">Falta sin aviso (-300 pts)</option>
                                    <option value="engano_inventar">Engaño/Inventar venta (-1000 pts)</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Evidencia (opcional)</label>
                                <input type="file" name="evidencia" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comentario</label>
                        <textarea name="comentario" class="form-control" rows="2" required></textarea>
                    </div>

                    <button type="submit" name="agregar_puntos" class="btn btn-primary">
                        Registrar Puntos
                    </button>
                </form>
            </div>
        </div>

        <!-- Solicitudes pendientes -->
        <div class="card mt-4">
            <div class="card-header bg-warning">
                <h3>Solicitudes Pendientes de Revisión</h3>
                <small>Total: <?= count($solicitudes_pendientes) ?></small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Vendedor</th>
                                <th>Tipo</th>
                                <th>Evidencia</th>
                                <th>Comentario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes_pendientes as $solicitud): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])) ?></td>
                                    <td><?= htmlspecialchars($solicitud['vendedor']) ?></td>
                                    <td>
                                        <?= match ($solicitud['tipo']) {
                                            'venta_dificil' => '<span class="badge bg-primary">Venta difícil</span><br>+100 pts',
                                            'seguimiento_3ventas' => '<span class="badge bg-success">3 Ventas</span><br>+300 pts',
                                            'ventas_cruzadas' => '<span class="badge bg-info">Ventas cruzadas</span><br>+150 pts',
                                            default => $solicitud['tipo']
                                        } ?>
                                    </td>
                                    <td>
                                        <a href="<?= htmlspecialchars($solicitud['evidencia']) ?>" target="_blank"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i> Ver
                                        </a>
                                    </td>
                                    <td><?= !empty($solicitud['comentario']) ? htmlspecialchars($solicitud['comentario']) : '--' ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <form method="POST" class="mb-0">
                                                <input type="hidden" name="solicitud_id" value="<?= $solicitud['id'] ?>">
                                                <button type="submit" name="aprobar_solicitud"
                                                    class="btn btn-sm btn-success"
                                                    onclick="return confirm('¿Aprobar esta solicitud?')">
                                                    <i class="fas fa-check"></i> Aprobar
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                data-bs-target="#rechazoModal" data-solicitud-id="<?= $solicitud['id'] ?>">
                                                <i class="fas fa-times"></i> Rechazar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal para el rechazo -->
        <div class="modal fade" id="rechazoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Rechazar Solicitud</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="formRechazar">
                        <div class="modal-body">
                            <input type="hidden" name="solicitud_id" id="rechazo_solicitud_id">
                            <div class="mb-3">
                                <label class="form-label">Motivo del rechazo</label>
                                <textarea name="comentario_rechazo" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="rechazar_solicitud" class="btn btn-danger">
                                <i class="fas fa-times"></i> Confirmar Rechazo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

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


    <?php include('footer.php'); ?>