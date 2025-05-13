<?php
require 'datos_index.php'; // para tener $pdo, $user_id, $role

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
                <th>Min. Extra</th>

            </tr>
        </thead>
        <tbody>
            <?php foreach ($asistencias as $a): ?>
                <tr>
                    <?php if ($role === 'admin'): ?>
                        <td><?= htmlspecialchars($a['username']) ?></td><?php endif; ?>
                    <td><?= $a['fecha'] ?></td>
                    <td><?= $a['hora_entrada'] ?? '<span class="text-muted">-</span>' ?></td>
                    <td>
                        <span class="badge bg-<?= $a['tipo_entrada'] === 'tardanza' ? 'danger' : 'success' ?>">
                            <?= ucfirst($a['tipo_entrada']) ?>
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
                        <?= $a['minutos_extra'] !== null ? $a['minutos_extra'] . ' min' : '<span class="text-muted">-</span>' ?>
                    </td>

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
                <th>Minutos Extra</th>
                <th>¿Aplicado a Sanción?</th>
                <th>Puntos Generados</th>
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
                    <td><?= $r['puntos_generados'] ?> pts</td>
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

<?php include('footer.php'); ?>