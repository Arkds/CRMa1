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


// Obtener la lista de usuarios para el filtro
$stmt = $pdo->query("SELECT id, username FROM users ORDER BY username ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consulta base para obtener los reportes
$query = "SELECT r.id, r.type, r.date, u.username AS usuario,
                 (SELECT COUNT(*) FROM report_clients WHERE report_id = r.id) AS total_clientes,
                 (SELECT GROUP_CONCAT(content SEPARATOR ' | ') FROM report_entries WHERE report_id = r.id AND category = 'problemas') AS problemas,
                 (SELECT GROUP_CONCAT(content SEPARATOR ' | ') FROM report_entries WHERE report_id = r.id AND category = 'cursos_mas_vendidos') AS cursos,
                 (SELECT GROUP_CONCAT(content SEPARATOR ' | ') FROM report_entries WHERE report_id = r.id AND category = 'dudas_frecuentes') AS dudas,
                 (SELECT content FROM report_entries WHERE report_id = r.id AND category = 'recomendaciones' LIMIT 1) AS recomendaciones
          FROM reports r 
          JOIN users u ON r.user_id = u.id
          WHERE 1=1";

// Filtros dinámicos
$conditions = [];
$params = [];

if (!empty($_GET['type'])) {
    $conditions[] = "r.type = ?";
    $params[] = $_GET['type'];
}

if (!empty($_GET['user_id'])) {
    $conditions[] = "r.user_id = ?";
    $params[] = $_GET['user_id'];
}

if (!empty($_GET['fecha_inicio']) && !empty($_GET['fecha_fin'])) {
    $conditions[] = "r.date BETWEEN ? AND ?";
    $params[] = $_GET['fecha_inicio'];
    $params[] = $_GET['fecha_fin'];
}

if ($conditions) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Personalizados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body class="container py-4">
    <h1 class="text-center">Reportes Personalizados</h1>
    <button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>


    <form method="GET" action="report_custom.php" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <label>Tipo de Reporte</label>
                <select name="type" class="form-control">
                    <option value="">Todos</option>
                    <option value="diario">Diario</option>
                    <option value="semanal">Semanal</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>Usuario</label>
                <select name="user_id" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label>Fecha Inicio</label>
                <input type="date" name="fecha_inicio" class="form-control">
            </div>
            <div class="col-md-3">
                <label>Fecha Fin</label>
                <input type="date" name="fecha_fin" class="form-control">
            </div>
            <div class="col-md-3 mt-4">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </div>
    </form>

    <table class="table table-striped" id="customreporttable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Tipo</th>
                <th>Fecha</th>
                <th>Clientes</th>
                <th>Problemas</th>
                <th>Cursos Más Vendidos</th>
                <th>Dudas Frecuentes</th>
                <th>Recomendaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?= htmlspecialchars($report['id']) ?></td>
                    <td><?= htmlspecialchars($report['usuario']) ?></td>
                    <td><?= htmlspecialchars($report['type']) ?></td>
                    <td><?= htmlspecialchars($report['date']) ?></td>
                    <td><?= htmlspecialchars($report['total_clientes']) ?></td>
                    <td>
                        <div class="text-truncate" style="max-width: 150px; cursor:pointer;" onclick="toggleExpand(this)">
                            <?= htmlspecialchars($report['problemas']) ?: 'No registrado' ?>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 150px; cursor:pointer;" onclick="toggleExpand(this)">
                            <?= htmlspecialchars($report['cursos']) ?: 'No registrado' ?>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 150px; cursor:pointer;" onclick="toggleExpand(this)">
                            <?= htmlspecialchars($report['dudas']) ?: 'No registrado' ?>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 150px; cursor:pointer;" onclick="toggleExpand(this)">
                            <?= htmlspecialchars($report['recomendaciones']) ?: 'No registrado' ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

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

        $(document).ready(function () {
            $('#customreporttable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                order: [[3, 'desc']], 
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });
        });

    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
