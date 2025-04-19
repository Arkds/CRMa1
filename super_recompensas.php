<?php
require 'db.php';

// Verificar autenticación
if (!isset($_COOKIE['user_session'])) {
    header("Location: login.php");
    exit;
}

$user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
$user_id = $user_data['user_id'];
$username = $user_data['username'];
$isAdmin = ($user_data['role'] === 'admin');

// Obtener datos del usuario incluyendo su carpeta de Drive
$stmt = $pdo->prepare("SELECT puntos_historicos, drive_folder FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$puntos_historicos = $user['puntos_historicos'] ?? 0;
$drive_folder = $user['drive_folder'];

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

// Procesar solicitudes de agregar puntos (solo admin)
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_puntos'])) {
    $tipo = $_POST['tipo'];
    $vendedor_id = $_POST['vendedor_id'];
    $comentario = $_POST['comentario'] ?? '';
    
    // Obtener folder de Drive del vendedor
    $stmt = $pdo->prepare("SELECT drive_folder FROM users WHERE id = ?");
    $stmt->execute([$vendedor_id]);
    $vendedor_folder = $stmt->fetchColumn();
    
    $evidencia_link = '';
    
    // Procesar evidencia si se subió
    if (!empty($_FILES['evidencia']['name']) && !empty($vendedor_folder)) {
        $nombre_archivo = uniqid() . '_' . basename($_FILES['evidencia']['name']);
        $ruta_destino = "ruta/a/drive/" . $vendedor_folder . "/" . $nombre_archivo;
        
        // Aquí iría el código para subir a Drive (API de Google Drive)
        // Esto es solo un ejemplo conceptual
        if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $ruta_destino)) {
            $evidencia_link = $ruta_destino;
        }
    }

    // Definir puntos por tipo
    $puntos = match ($tipo) {
        'venta_normal' => 180,
        'venta_dificil' => 100,
        'seguimiento_3ventas' => 300,
        'sin_errores_semana' => 100,
        'ventas_cruzadas' => 150,
        'error_registro' => -150,
        'falta_sin_aviso' => -300,
        'engano_inventar' => -1000,
        default => 0
    };

    // Actualizar puntos históricos
    $stmt = $pdo->prepare("UPDATE users SET puntos_historicos = puntos_historicos + ? WHERE id = ?");
    $stmt->execute([$puntos, $vendedor_id]);

    // Registrar en el historial
    $stmt = $pdo->prepare("INSERT INTO historial_puntos_historicos 
                          (user_id, puntos, tipo, comentario, evidencia, admin_id) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$vendedor_id, $puntos, $tipo, $comentario, $evidencia_link, $user_id]);

    // Redirigir para evitar reenvío del formulario
    header("Location: super_recompensas.php");
    exit;
}

// Procesar solicitudes de reclamar recompensa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reclamar_recompensa'])) {
    $puntos_requeridos = $_POST['puntos_requeridos'];
    $recompensa = $_POST['recompensa'];
    
    // Verificar que tenga suficientes puntos
    if ($puntos_historicos >= $puntos_requeridos) {
        // Registrar la reclamación
        $stmt = $pdo->prepare("INSERT INTO recompensas_reclamadas 
                              (user_id, puntos_reclamados, descripcion, semana_year) 
                              VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $user_id, 
            $puntos_requeridos, 
            $recompensa, 
            date('Y-W') // Año-Semana
        ]);
        
        // Actualizar puntos (opcional, podrían mantenerse los puntos históricos)
        // $stmt = $pdo->prepare("UPDATE users SET puntos_historicos = puntos_historicos - ? WHERE id = ?");
        // $stmt->execute([$puntos_requeridos, $user_id]);
        
        $_SESSION['mensaje'] = "¡Recompensa reclamada con éxito!";
    } else {
        $_SESSION['error'] = "No tienes suficientes puntos para esta recompensa";
    }
    
    header("Location: super_recompensas.php");
    exit;
}

// Obtener historial de puntos
$stmt = $pdo->prepare("SELECT h.*, u.username as admin_username 
                      FROM historial_puntos_historicos h
                      LEFT JOIN users u ON h.admin_id = u.id
                      WHERE h.user_id = ?
                      ORDER BY h.fecha_registro DESC");
$stmt->execute([$user_id]);
$historial = $stmt->fetchAll();

// Obtener recompensas reclamadas
$stmt = $pdo->prepare("SELECT * FROM recompensas_reclamadas 
                      WHERE user_id = ? 
                      ORDER BY fecha_reclamo DESC");
$stmt->execute([$user_id]);
$recompensas_reclamadas = $stmt->fetchAll();

// Determinar recompensas alcanzadas y siguientes
$recompensas_alcanzadas = [];
$recompensas_pendientes = [];
$siguiente_recompensa = null;
$puntos_para_siguiente = 0;

foreach ($recompensas_historicas as $puntos => $descripcion) {
    if ($puntos_historicos >= $puntos) {
        $recompensas_alcanzadas[$puntos] = $descripcion;
    } else {
        if ($siguiente_recompensa === null) {
            $siguiente_recompensa = $puntos;
            $puntos_para_siguiente = $puntos - $puntos_historicos;
        }
        $recompensas_pendientes[$puntos] = $descripcion;
    }
}

include('header.php');
?>

<div class="container mt-4">
    <h1 class="text-center">Super Recompensas Históricas</h1>
    <p class="text-center">Tus puntos históricos nunca se reinician</p>

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
                            <h5>✅ Recompensas Obtenidas</h5>
                            <ul class="list-group">
                                <?php foreach ($recompensas_alcanzadas as $puntos => $desc): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= $desc ?>
                                        <span class="badge bg-primary rounded-pill"><?= number_format($puntos) ?> pts</span>
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
                                            <span class="small text-muted ms-2">(Faltan <?= number_format($puntos_para_siguiente) ?> pts)</span>
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

    <!-- Reclamar recompensas -->
    <?php if (!empty($recompensas_alcanzadas)): ?>
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h3>Reclamar Recompensa</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Selecciona tu recompensa</label>
                        <select name="recompensa" class="form-select" required>
                            <?php foreach ($recompensas_alcanzadas as $puntos => $desc): ?>
                                <option value="<?= $desc ?>"><?= $desc ?> (<?= number_format($puntos) ?> pts)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="puntos_requeridos" value="<?= array_key_last($recompensas_alcanzadas) ?>">
                    </div>
                    <button type="submit" name="reclamar_recompensa" class="btn btn-success">
                        Reclamar Recompensa
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Historial de puntos -->
    <div class="card mt-4">
        <div class="card-header">
            <h3>Historial de Puntos</h3>
        </div>
        <div class="card-body">
            <table class="table table-striped">
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
                                    'venta_normal' => 'Venta normal (+180 pts)',
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

    <!-- Historial de recompensas reclamadas -->
    <?php if (!empty($recompensas_reclamadas)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h3>Recompensas Reclamadas</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Puntos</th>
                            <th>Recompensa</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recompensas_reclamadas as $recompensa): ?>
                            <tr class="<?= $recompensa['pagada'] ? 'table-success' : 'table-warning' ?>">
                                <td><?= date('d/m/Y', strtotime($recompensa['fecha_reclamo'])) ?></td>
                                <td><?= number_format($recompensa['puntos_reclamados']) ?></td>
                                <td><?= htmlspecialchars($recompensa['descripcion']) ?></td>
                                <td>
                                    <?php if ($recompensa['pagada']): ?>
                                        <span class="badge bg-success">Entregada</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">En proceso</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

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
                                    <option value="venta_normal">Venta normal (+180 pts)</option>
                                    <option value="venta_dificil">Venta difícil (+100 pts)</option>
                                    <option value="seguimiento_3ventas">Seguimiento con 3 ventas (+300 pts)</option>
                                    <option value="sin_errores_semana">Semana sin errores (+100 pts)</option>
                                    <option value="ventas_cruzadas">Ventas cruzadas (+150 pts)</option>
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
                                <small class="text-muted">Se guardará en la carpeta de Drive del vendedor</small>
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

<?php include('footer.php'); ?>