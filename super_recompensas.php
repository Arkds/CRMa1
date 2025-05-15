<?php


require 'db.php';


// Verificar autenticación
if (!isset($_COOKIE['user_session'])) {
    header("Location: login.php");
    exit;
}
// Verificar si el programa está activo
$stmt = $pdo->query("SELECT ultima_fecha_grupal FROM metadatos_reseteo LIMIT 1");
$metadatos = $stmt->fetch();

//if (!$metadatos) {
//    if ($isAdmin) {
//        $_SESSION['error'] = "El programa de puntos históricos no ha sido iniciado. <a href='iniciar_programa_puntos.php'>Configurar ahora</a>";
//    } else {
//        $_SESSION['error'] = "El programa de puntos históricos no está activo actualmente.";
//    }

//    header("Location: super_recompensas.php");
//    exit;
//}
$user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
$user_id = $user_data['user_id'];
$username = $user_data['username'];
$isAdmin = ($user_data['role'] === 'admin');

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT puntos_historicos, drive_folder FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$puntos_historicos = $user['puntos_historicos'] ?? 0;
$drive_folder = $user['drive_folder'];

// Definir recompensas históricas
/*$recompensas_historicas = [
    8000 => "Snack o bebida",
    15000 => "Entrada al cine",
    25000 => "Bono Yape S/30",
    50000 => "Día libre",
    120000 => "Audífonos Bluetooth",
    200000 => "Teclado mecánico o silla gamer simple",
    350000 => "Viaje doble pagado",
    550000 => "¡TV, consola o laptop básica! "
    
];*/
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
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
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

                // Actualizar puntos y marcar como aprobado
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
                    $user_id
                ]);

                $stmt = $pdo->prepare("UPDATE solicitudes_puntos 
                                      SET estado = 'aprobado', puntos_asignados = ?,
                                          admin_id = ?, fecha_revision = NOW()
                                      WHERE id = ?");
                $stmt->execute([$puntos, $user_id, $solicitud_id]);
                $pdo->commit();

                $_SESSION['mensaje'] = "Solicitud aprobada y puntos asignados";
            } else {
                $comentario_rechazo = $_POST['comentario_rechazo'] ?? '';
                $stmt = $pdo->prepare("UPDATE solicitudes_puntos 
                                      SET estado = 'rechazado', admin_id = ?,
                                          comentario_rechazo = ?, fecha_revision = NOW()
                                      WHERE id = ?");
                $stmt->execute([$user_id, $comentario_rechazo, $solicitud_id]);
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
        $stmt->execute([$vendedor_id, $puntos, $tipo, $comentario, $user_id]);
        $pdo->commit();

        $_SESSION['mensaje'] = "Puntos asignados correctamente";
        header("Location: super_recompensas.php");
        exit;
    }
}
// Procesar reclamo de recompensa
if (!$isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reclamar_recompensa'])) {
    $puntos_requeridos = (int) $_POST['puntos_recompensa'];
    $descripcion = $_POST['descripcion_recompensa'];

    if ($puntos_historicos >= $puntos_requeridos) {
        $pdo->beginTransaction();
        try {
            // 1. Descontar puntos al usuario
            $stmt = $pdo->prepare("UPDATE users SET puntos_historicos = puntos_historicos - ? WHERE id = ?");
            $stmt->execute([$puntos_requeridos, $user_id]);

            // 2. Registrar el reclamo
            $stmt = $pdo->prepare("INSERT INTO recompensas_historicas_reclamadas_usuarios 
                (user_id, puntos_usados, recompensa) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $puntos_requeridos, $descripcion]);

            // 3. Registrar en historial
            $stmt = $pdo->prepare("INSERT INTO historial_puntos_historicos 
                (user_id, puntos, tipo, comentario) 
                VALUES (?, ?, 'reclamo_recompensa', ?)");
            $stmt->execute([$user_id, -$puntos_requeridos, "Reclamo de recompensa: $descripcion"]);

            $pdo->commit();
            $_SESSION['mensaje'] = "Recompensa reclamada correctamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Ocurrió un error al reclamar la recompensa.";
        }
    } else {
        $_SESSION['error'] = "No tienes suficientes puntos para reclamar esta recompensa.";
    }

    header("Location: super_recompensas.php");
    exit;
}


// Procesar solicitud de puntos (vendedores)

if (!$isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_puntos'])) {
    $tipo = $_POST['tipo'];
    $evidencia = filter_var($_POST['evidencia'], FILTER_SANITIZE_URL);
    $comentario = $_POST['comentario'] ?? '';

    if (!empty($evidencia)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO solicitudes_puntos 
                                  (user_id, tipo, evidencia, comentario) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $tipo, $evidencia, $comentario]);
            $_SESSION['mensaje'] = "Solicitud enviada para revisión";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error al registrar la solicitud";
        }
    } else {
        $_SESSION['error'] = "Debes ingresar un enlace a la evidencia";
    }
    header("Location: super_recompensas.php");
    exit;
}

// Obtener datos para mostrar
$stmt = $pdo->prepare("SELECT h.*, u.username as admin_username 
                      FROM historial_puntos_historicos h
                      LEFT JOIN users u ON h.admin_id = u.id
                      WHERE h.user_id = ?
                      AND h.fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                      ORDER BY h.fecha_registro DESC");

$stmt->execute([$user_id]);
$historial = $stmt->fetchAll();


if (!$isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM solicitudes_puntos 
                          WHERE user_id = ? ORDER BY fecha_solicitud DESC");
    $stmt->execute([$user_id]);
    $solicitudes_puntos = $stmt->fetchAll();
}

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT s.*, u.username as vendedor 
                          FROM solicitudes_puntos s
                          JOIN users u ON s.user_id = u.id
                          WHERE s.estado = 'pendiente'
                          ORDER BY s.fecha_solicitud DESC");
    $stmt->execute();
    $solicitudes_pendientes = $stmt->fetchAll();
}

// Calcular recompensas alcanzadas/pendientes
$recompensas_alcanzadas = array_filter($recompensas_historicas, fn($p) => $puntos_historicos >= $p, ARRAY_FILTER_USE_KEY);
$recompensas_pendientes = array_filter($recompensas_historicas, fn($p) => $puntos_historicos < $p, ARRAY_FILTER_USE_KEY);
$siguiente_recompensa = array_key_first($recompensas_pendientes);
$puntos_para_siguiente = $siguiente_recompensa - $puntos_historicos;

include('header.php');
?>
<style>
    .list-group-item .badge {
        font-size: 0.85rem;
        padding: 5px 10px;
    }
</style>


<div class="container mt-4">
    <h1 class="text-center">Super Recompensas Históricas</h1>
    <p class="text-center">Tus puntos históricos nunca se reinician</p>
    <?php if ($isAdmin): ?>
        <button class="btn btn-info mb-3"
            onclick="window.location.href='iniciar_programa_puntos.php';">Iniciar/Reiniciarz</button>

    <?php endif; ?>
    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <h3>Tu Progreso Histórico</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="text-center mb-4">
                        <h2>Puntos Acumulados</h2>
                        <div class="display-4"><?= number_format($puntos_historicos) ?></div>
                        <small class="text-muted">Puntos históricos acumulados</small>
                    </div>

                    <div class="progress" style="height: 30px;">
                        <?php
                        $max_puntos = max(550000, $puntos_historicos + 10000); // Ajuste dinámico hasta la máxima recompensa
                        $porcentaje = min(100, ($puntos_historicos / $max_puntos) * 100);
                        ?>
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                            style="width: <?= $porcentaje ?>%" aria-valuenow="<?= $puntos_historicos ?>"
                            aria-valuemin="0" aria-valuemax="<?= $max_puntos ?>">
                        </div>
                    </div>

                    <!-- Marcas de recompensas -->
                    <div class="progress-marks" style="position: relative; height: 40px; margin-top: 20px;">
                        <?php foreach ($recompensas_historicas as $puntos => $desc): ?>
                            <?php $posicion = ($puntos / $max_puntos) * 100; ?>
                            <div style="position: absolute; left: <?= $posicion ?>%; transform: translateX(-50%);">
                                <div
                                    style="width: 2px; height: 20px; background: <?= $puntos_historicos >= $puntos ? '#28a745' : '#dc3545' ?>;">
                                </div>
                                <small
                                    style="position: absolute; top: 25px; left: 50%; transform: translateX(-50%); white-space: nowrap;">
                                    <?= number_format($puntos) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <h4>Recompensas Disponibles</h4>

                    <?php if (!empty($recompensas_alcanzadas)): ?>
                        <div class="alert alert-success">
                            <h5>✅ Recompensas Alcanzadas</h5>
                            <p>¡Felicidades! Has alcanzado estas recompensas que serán entregadas próximamente.</p>
                            <ul class="list-group">
                                <?php foreach ($recompensas_alcanzadas as $puntos => $desc): ?>
                                    <?php
                                    // Verificar si ya fue reclamada
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM recompensas_historicas_reclamadas_usuarios 
    WHERE user_id = ? AND recompensa = ?");
                                    $stmt->execute([$user_id, $desc]);
                                    $ya_reclamada = $stmt->fetchColumn() > 0;
                                    ?>

                                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                        <div class="fw-bold"><?= $desc ?></div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary rounded-pill"><?= number_format($puntos) ?> pts</span>

                                            <?php
                                            $stmt = $pdo->prepare("SELECT fecha_reclamo FROM recompensas_historicas_reclamadas_usuarios 
            WHERE user_id = ? AND recompensa = ? ORDER BY fecha_reclamo DESC LIMIT 1");
                                            $stmt->execute([$user_id, $desc]);
                                            $reclamo = $stmt->fetch();
                                            $ya_reclamada = $reclamo ? true : false;
                                            ?>

                                            <?php if (!$ya_reclamada): ?>
                                                <form method="POST" class="m-0">
                                                    <input type="hidden" name="puntos_recompensa" value="<?= $puntos ?>">
                                                    <input type="hidden" name="descripcion_recompensa"
                                                        value="<?= htmlspecialchars($desc) ?>">
                                                    <button type="submit" name="reclamar_recompensa" class="btn btn-sm btn-success">
                                                        Reclamar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <?php
                                                $stmt = $pdo->prepare("SELECT fecha_reclamo, pagada FROM recompensas_historicas_reclamadas_usuarios 
    WHERE user_id = ? AND recompensa = ? ORDER BY fecha_reclamo DESC LIMIT 1");
                                                $stmt->execute([$user_id, $desc]);
                                                $reclamo = $stmt->fetch();
                                                ?>

                                                <?php if ($reclamo): ?>
                                                    <span class="badge <?= $reclamo['pagada'] ? 'bg-success' : 'bg-secondary' ?>">
                                                        ✅ Reclamada<br>
                                                        <small><?= date('d/m/Y H:i', strtotime($reclamo['fecha_reclamo'])) ?></small><br>
                                                        <?= $reclamo['pagada'] ? '<i class="fas fa-check-circle"></i> Pagada' : '⏳ Pendiente' ?>
                                                    </span>
                                                <?php endif; ?>

                                            <?php endif; ?>
                                        </div>
                                    </li>

                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($recompensas_pendientes)): ?>
                        <div class="alert alert-info">
                            <h5>🎯 Próximas Recompensas</h5>
                            <ul class="list-group">
                                <?php foreach ($recompensas_pendientes as $puntos => $desc): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= $desc ?>
                                        <span class="badge bg-secondary rounded-pill"><?= number_format($puntos) ?> pts</span>
                                        <?php if ($puntos == $siguiente_recompensa): ?>
                                            <span class="small text-muted ms-2">(Faltan <?= number_format($puntos_para_siguiente) ?>
                                                pts)</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>



    <!-- Historial de puntos -->
    <div class="card mt-4">
        <div class="card-header">
            <h3>Historial de Puntos</h3>
        </div>
        <div class="card-body">
            <table class="table table-striped display compact" id="mitabla">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Puntos</th>
                        <th>Tipo</th>
                        <th>Comentario</th>
                        <th>Evidencia</th>
                        <th>Registrado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $registro): ?>
                        <tr class="<?= $registro['puntos'] >= 0 ? 'table-success' : 'table-danger' ?>">
                            <td><?= date('d/m/Y H:i', strtotime($registro['fecha_registro'])) ?></td>
                            <td><?= $registro['puntos'] >= 0 ? '+' : '' ?><?= $registro['puntos'] ?></td>
                            <td>
                                <?= match ($registro['tipo']) {
                                    'venta_normal' => 'Venta normal (+50 pts)',
                                    'venta_dificil' => 'Venta difícil (+100 pts)',
                                    'seguimiento_3ventas' => 'Seguimiento con 3 ventas (+300 pts)',
                                    'sin_errores_semana' => 'Semana sin errores (+100 pts)',
                                    'ventas_cruzadas' => 'Ventas cruzadas (+150 pts)',
                                    'error_registro' => 'Error en registro (-150 pts)',
                                    'falta_sin_aviso' => 'Falta sin aviso (-300 pts)',
                                    'engano_inventar' => 'Engaño/Inventar venta (-1000 pts)',
                                    default => $registro['tipo']
                                } ?>
                            </td>
                            <td><?= htmlspecialchars($registro['comentario']) ?></td>
                            <td>
                                <?php if (!empty($registro['evidencia'])): ?>
                                    <a href="<?= $registro['evidencia'] ?>" target="_blank" class="btn btn-sm btn-info">Ver</a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $registro['admin_username'] ?? 'Sistema' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>


    <?php if ($isAdmin): ?>
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
                                    <?php
                                    $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'vendedor'");
                                    while ($user = $stmt->fetch()): ?>
                                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                    <?php endwhile; ?>
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
    <?php endif; ?>

    <?php if (!$isAdmin): ?>
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h3>Solicitar Puntos por Actividad</h3>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['debug'])): ?>
                    <div class="alert alert-warning">DEBUG: <?= $_SESSION['debug'] ?></div>
                    <?php unset($_SESSION['debug']); ?>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Tipo de Actividad</label>
                        <select name="tipo" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <option value="venta_dificil">Venta difícil (+100 pts)</option>
                            <option value="seguimiento_3ventas">Seguimiento con 3 ventas (+300 pts)</option>
                            <option value="ventas_cruzadas">Ventas cruzadas (+150 pts)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Enlace a la evidencia en Drive</label>
                        <input type="url" name="evidencia" class="form-control" required
                            placeholder="Pega aquí el enlace completo de Google Drive">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comentarios adicionales</label>
                        <textarea name="comentario" class="form-control" rows="2"></textarea>
                    </div>

                    <button type="submit" name="solicitar_puntos" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Enviar Solicitud
                    </button>
                </form>

                <!-- Estado de solicitudes anteriores -->
                <?php if (!empty($solicitudes_puntos)): ?>
                    <div class="mt-4">
                        <h5>Tus solicitudes recientes</h5>
                        <table class="table table-sm display compact" id="mitabla">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Puntos</th>
                                    <th>Comentario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solicitudes_puntos as $solicitud): ?>
                                    <tr class="<?= match ($solicitud['estado']) {
                                        'aprobado' => 'table-success',
                                        'rechazado' => 'table-danger',
                                        default => 'table-warning'
                                    } ?>">
                                        <td><?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?></td>
                                        <td>
                                            <?= match ($solicitud['tipo']) {
                                                'venta_dificil' => 'Venta difícil',
                                                'seguimiento_3ventas' => 'Seguimiento 3 ventas',
                                                'ventas_cruzadas' => 'Ventas cruzadas',
                                                default => $solicitud['tipo']
                                            } ?>
                                        </td>
                                        <td>
                                            <?= match ($solicitud['estado']) {
                                                'pendiente' => '<span class="badge bg-warning">Pendiente</span>',
                                                'aprobado' => '<span class="badge bg-success">Aprobado</span>',
                                                'rechazado' => '<span class="badge bg-danger">Rechazado</span>',
                                                default => $solicitud['estado']
                                            } ?>
                                        </td>
                                        <td>
                                            <?= $solicitud['puntos_asignados'] > 0 ? '+' : '' ?>
                                            <?= $solicitud['puntos_asignados'] ?>
                                        </td>
                                        <td>
                                            <?php if ($solicitud['estado'] === 'rechazado' && !empty($solicitud['comentario_rechazo'])): ?>
                                                <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="tooltip"
                                                    title="<?= htmlspecialchars($solicitud['comentario_rechazo']) ?>">
                                                    <i class="fas fa-comment"></i> Ver motivo
                                                </button>
                                            <?php elseif (!empty($solicitud['comentario'])): ?>
                                                <?= htmlspecialchars($solicitud['comentario']) ?>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
        <div class="card mt-4">
            <div class="card-header bg-warning">
                <h3>Solicitudes Pendientes de Revisión</h3>
                <small>Total: <?= count($solicitudes_pendientes) ?></small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover display compact" id="mitabla">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Vendedor</th>
                                <th>Tipo</th>
                                <th>Enlace Evidencia</th>
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
                                            class="btn btn-sm btn-outline-primary" title="Abrir en nueva pestaña">
                                            <i class="fas fa-external-link-alt"></i> Ver Evidencia
                                        </a>
                                    </td>
                                    <td><?= !empty($solicitud['comentario']) ? htmlspecialchars($solicitud['comentario']) : '--' ?>
                                    </td>

                                    <td>
                                        <div class="d-flex gap-2">
                                            <form method="POST" class="mb-0">
                                                <input type="hidden" name="solicitud_id" value="<?= $solicitud['id'] ?>">
                                                <button type="submit" name="aprobar_solicitud" class="btn btn-sm btn-success"
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
                                <small class="text-muted">Este comentario será visible para el vendedor</small>
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

        <!-- Script para manejar el modal -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const rechazoModal = new bootstrap.Modal(document.getElementById('rechazoModal'));

                // Manejar clic en botones de rechazo
                document.querySelectorAll('[data-bs-target="#rechazoModal"]').forEach(button => {
                    button.addEventListener('click', function () {
                        const solicitudId = this.getAttribute('data-solicitud-id');
                        document.getElementById('rechazo_solicitud_id').value = solicitudId;
                    });
                });
            });
        </script>
    <?php endif; ?>
</div>

<style>
    .progress-marks {
        position: relative;
        height: 50px;
    }

    .progress-marks small {
        font-size: 12px;
        font-weight: bold;
    }

    .list-group-item {
        transition: all 0.3s;
    }

    .list-group-item:hover {
        transform: translateX(5px);
    }
</style>
<script>
    // Activar tooltips de Bootstrap
    document.addEventListener('DOMContentLoaded', function () {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>


<script>
    $(document).ready(function () {
        $('#mitabla').DataTable({
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

<?php include('footer.php'); ?>