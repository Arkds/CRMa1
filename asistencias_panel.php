<?php
require 'datos_index.php'; // para tener $pdo, $user_id, $role

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sancion_id'])) {
    $id = (int) $_POST['sancion_id'];
    $min_castigo = (int) $_POST['minutos_castigo'];
    $min_recuperados = isset($_POST['minutos_recuperados']) ? (int) $_POST['minutos_recuperados'] : 0;
    $descuento = isset($_POST['descuento_soles']) ? (float) $_POST['descuento_soles'] : 0;

    $stmt = $pdo->prepare("
        UPDATE sanciones 
        SET minutos_castigo = :castigo, 
            minutos_recuperados = :recuperados, 
            descuento_soles = :descuento 
        WHERE id = :id
    ");
    $stmt->execute([
        ':castigo' => $min_castigo,
        ':recuperados' => $min_recuperados,
        ':descuento' => $descuento,
        ':id' => $id
    ]);

    // Redireccionar para evitar doble envío al recargar
    echo "<script>window.location.href = window.location.pathname;</script>";
    exit;
}



if (isset($_GET['get_sancion']) && is_numeric($_GET['get_sancion'])) {
    $stmt = $pdo->prepare("SELECT minutos_castigo, minutos_recuperados, descuento_soles FROM sanciones WHERE id = :id");
    $stmt->execute([':id' => $_GET['get_sancion']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($data ?: []);
    exit;
}

if ($role === 'admin') {
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            u.username,
            (
                SELECT SUM(r.minutos_extra)
                FROM recuperaciones r
                WHERE r.asistencia_id = a.id
            ) AS minutos_extra
        FROM asistencia a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.fecha DESC, a.hora_entrada ASC
    ");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            u.username,
            (
                SELECT SUM(r.minutos_extra)
                FROM recuperaciones r
                WHERE r.asistencia_id = a.id
            ) AS minutos_extra
        FROM asistencia a
        JOIN users u ON a.user_id = u.id
        WHERE a.user_id = :user_id
        ORDER BY a.fecha DESC, a.hora_entrada ASC
    ");
    $stmt->execute([':user_id' => $user_id]);
}


$asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
include('header.php');
?>



<div class="container mt-4">
    <?php if ($role === 'admin'): ?>
        <?php
        // Obtener minutos pendientes agrupados por usuario
        $stmt = $pdo->query("
        SELECT 
            u.username, 
            SUM(s.minutos_castigo) - SUM(COALESCE(s.minutos_recuperados, 0)) AS pendientes
        FROM sanciones s
        JOIN users u ON s.user_id = u.id
        GROUP BY s.user_id
        HAVING pendientes > 0
        ORDER BY pendientes DESC
    ");
        $pendientesPorUsuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($pendientesPorUsuario as $p): ?>
                <span class="badge rounded-pill bg-warning text-dark">
                    <?= htmlspecialchars($p['username']) ?>: <?= (int) $p['pendientes'] ?> min
                </span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?php
        $stmt = $pdo->prepare("
        SELECT SUM(minutos_castigo) - SUM(COALESCE(minutos_recuperados, 0)) AS pendientes
        FROM sanciones
        WHERE user_id = :user_id
    ");
        $stmt->execute([':user_id' => $user_id]);
        $pendientes = (int) $stmt->fetchColumn();
        ?>
        <div class="alert alert-warning text-center fw-bold">
            ⏱️ Minutos pendientes por recuperar: <span class="text-danger"><?= $pendientes ?> min</span>
        </div>
    <?php endif; ?>



    <h2> Historial de Asistencia</h2>
    <table class="table table-striped table-bordered mt-3 display compact" id="tablaAsistencia">
        <thead class="table-dark">
            <tr>
                <?php if ($role === 'admin'): ?>
                    <th>Usuario</th><?php endif; ?>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Tipo Entrada</th>
                <th>Salida</th>
                <th>Tipo Salida</th>
                <th>Min. Recuperados</th>
                <th>Min. Castigo</th>
                <?php if ($role === 'admin'): ?>
                    <th>Descuento</th>
                <?php endif; ?>
                <?php if ($role === 'admin'): ?>
                    <th>Editar</th>
                <?php endif; ?>



            </tr>
        </thead>
        <tbody>

            <?php foreach ($asistencias as $a): ?>
                <tr>
                    <?php
                    $hora_entrada = $a['hora_entrada'];
                    $hora_esperada = null;
                    $horarios_entrada = ['08:00:00', '14:00:00', '17:00:00', '20:00:00'];

                    foreach (array_reverse($horarios_entrada) as $h) {
                        if ($hora_entrada >= $h) {
                            $hora_esperada = $h;
                            break;
                        }
                    }

                    $minutos_tarde = 0;
                    if ($hora_esperada) {
                        $entrada = new DateTime($a['fecha'] . ' ' . $hora_entrada);
                        $esperada = new DateTime($a['fecha'] . ' ' . $hora_esperada);
                        $minutos_tarde = $entrada > $esperada ? round(($entrada->getTimestamp() - $esperada->getTimestamp()) / 60) : 0;
                    }
                    ?>

                    <?php if ($role === 'admin'): ?>
                        <td><?= htmlspecialchars($a['username']) ?></td><?php endif; ?>
                    <td>
    <?php
    if (!isset($fechaAnterior) || $fechaAnterior !== $a['fecha']) {
        echo "<span class='badge bg-secondary'>{$a['fecha']}</span>";
        $fechaAnterior = $a['fecha'];
    } else {
        echo "<span class='text-muted'>{$a['fecha']}</span>";
    }
    ?>
</td>

                    <td><?= $a['hora_entrada'] ?? '<span class="text-muted">-</span>' ?></td>
                    <td>
                        <span class="badge bg-<?= $a['tipo_entrada'] === 'tardanza' ? 'danger' : 'success' ?>">
                            <?= ucfirst($a['tipo_entrada']) ?>
                            <?php if ($a['tipo_entrada'] === 'tardanza' && $minutos_tarde > 0): ?>
                                +<?= $minutos_tarde ?> min
                            <?php endif; ?>
                        </span>
                    </td>

                    <td><?= $a['hora_salida'] ?? '<span class="text-muted">-</span>' ?></td>
                    <td>
                        <span
                            class="badge bg-<?= $a['tipo_salida'] === 'salida_adelantada' ? 'warning' : ($a['tipo_salida'] === 'pendiente' ? 'secondary' : 'success') ?>">
                            <?= ucfirst($a['tipo_salida']) ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $stmtRec = $pdo->prepare("SELECT SUM(minutos_extra) FROM recuperaciones WHERE asistencia_id = :id");
                        $stmtRec->execute([':id' => $a['id']]);
                        $minRec = $stmtRec->fetchColumn();
                        echo $minRec ? $minRec . ' min' : '<span class="text-muted">-</span>';
                        ?>
                    </td>
                    <td>
                        <?php
                        $stmtSan = $pdo->prepare("SELECT minutos_castigo FROM sanciones WHERE asistencia_id = :id");
                        $stmtSan->execute([':id' => $a['id']]);
                        $minSan = $stmtSan->fetchColumn();
                        echo $minSan ? $minSan . ' min' : '<span class="text-muted">-</span>';
                        ?>
                    </td>
                    <?php if ($role === 'admin'): ?>
                        <td>
                            <?php
                            $stmtDesc = $pdo->prepare("SELECT descuento_soles FROM sanciones WHERE asistencia_id = :id");
                            $stmtDesc->execute([':id' => $a['id']]);
                            $descuento = $stmtDesc->fetchColumn();
                            echo $descuento !== false ? 'S/ ' . number_format($descuento, 2) : '<span class="text-muted">-</span>';
                            ?>
                        </td>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <td>
                            <?php
                            $stmtSan = $pdo->prepare("SELECT id FROM sanciones WHERE asistencia_id = :id");
                            $stmtSan->execute([':id' => $a['id']]);
                            $sancion_id = $stmtSan->fetchColumn();
                            if ($sancion_id) {
                                echo "<button class='btn btn-sm btn-outline-secondary' onclick='editarSancion($sancion_id)'>Editar</button>";
                            } else {
                                echo "<span class='text-muted'>-</span>";
                            }
                            ?>
                        </td>
                    <?php endif; ?>




                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <hr>
    <h3 class="mt-5"> Recuperaciones de Horas</h3>

    <?php
    if ($role === 'admin') {
        $stmt = $pdo->prepare("
        SELECT r.*, u.username
        FROM recuperaciones r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.fecha DESC

    ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
        SELECT r.*, u.username
        FROM recuperaciones r
        JOIN users u ON r.user_id = u.id
        WHERE r.user_id = :user_id
        ORDER BY r.fecha DESC
    ");
        $stmt->execute([':user_id' => $user_id]);
    }
    $recuperaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ?>

    <table class="table table-bordered table-striped mt-3 display compact" id="tablaRecuperaciones">
        <thead class="table-dark">
            <tr>
                <?php if ($role === 'admin'): ?>
                    <th>Usuario</th>
                <?php endif; ?>
                <th>Fecha</th>
                <th>Minutos Recuperados</th>
                <th>Aplicado a Castigo</th>
                <th>-</th>

            </tr>
        </thead>
        <tbody>
            <?php foreach ($recuperaciones as $r): ?>
                <tr>
                    <?php if ($role === 'admin'): ?>
                        <td><?= htmlspecialchars($r['username']) ?></td>
                    <?php endif; ?>
                    <td><?= $r['fecha'] ?></td>
                    <td><?= $r['minutos_extra'] ?> min</td>
                    <td>
                        <span class="badge bg-<?= $r['aplicado_a_sancion'] ? 'primary' : 'warning' ?>">
                            <?= $r['aplicado_a_sancion'] ? 'Sí' : 'No' ?>
                        </span>
                    </td>
                    <td class="text-muted text-center">-</td>

                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        $(document).ready(function () {
            $('#tablaRecuperaciones').DataTable({
                order: [[1, 'desc']],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });
        });
    </script>

</div>


<!-- Modal edición sanción -->
<div class="modal fade" id="modalEditarSancion" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Editar Sanción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="sancion_id" id="sancion_id">
                <div class="mb-3">
                    <label class="form-label">Minutos de Castigo</label>
                    <input type="number" class="form-control" name="minutos_castigo" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Minutos Recuperados</label>
                    <input type="number" class="form-control" name="minutos_recuperados">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descuento (S/)</label>
                    <input type="number" step="0.01" class="form-control" name="descuento_soles">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>

<script>
    function editarSancion(id) {
        fetch('?get_sancion=' + id)

            .then(response => response.json())
            .then(data => {
                const modal = new bootstrap.Modal(document.getElementById('modalEditarSancion'));

                document.getElementById('sancion_id').value = id;
                document.querySelector('#modalEditarSancion input[name="minutos_castigo"]').value = data.minutos_castigo || 0;
                document.querySelector('#modalEditarSancion input[name="minutos_recuperados"]').value = data.minutos_recuperados || 0;
                document.querySelector('#modalEditarSancion input[name="descuento_soles"]').value = data.descuento_soles || 0;

                modal.show();
            })
            .catch(err => {
                console.error('Error al obtener sanción:', err);
                alert('No se pudo cargar la sanción. Revisa la consola.');
            });
    }

</script>



<!-- DataTables -->

<script>
    $(document).ready(function () {
        $('#tablaAsistencia').DataTable({
            order: [[1, 'desc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            }
        });
    });
</script>

<?php
// Liberar recursos y cerrar conexión (buenas prácticas)
$stmt = null;
$verificar = null;
$insert = null;
$pdo = null;
?>