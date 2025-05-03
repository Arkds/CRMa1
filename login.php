<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Crear cookie con datos del usuario por 6 horas
        $cookie_data = json_encode([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);
        setcookie("user_session", base64_encode($cookie_data), time() + (6 * 3600), "/", "", false, true);

        header('Location: index.php');
        exit;
    } else {
        $error = "Credenciales inv��lidas.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="src/bootstrapcss.css" rel="stylesheet">
    <link href="src/datatablescss.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <script src="src/jquery.js"></script>
    <script src="src/datatablesjs.js"></script>
    <script src="src/chartjs.js"></script>
    <script src="src/chartplugin.js"></script>

    <title>CRM</title>
</head>

<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <h3 class="text-center">Iniciar Sesión</h3>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Ingresar</button>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
<php if (isset($pdo)) {
    $pdo = null;
}
?> 
</html>
