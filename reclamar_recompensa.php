<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

if (isset($_COOKIE['user_session'])) {
    $user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
    $user_id = $user_data['user_id'];
    $username = $user_data['username'];
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Sesión no válida']);
    exit;
}

$current_week = date('o-\WW');
$puntos = $_POST['puntos'] ?? 0;
$descripcion = $_POST['descripcion'] ?? '';

// Verificar si ya reclamó esta semana
$stmt = $pdo->prepare("SELECT id FROM recompensas_reclamadas WHERE user_id = ? AND semana_year = ?");
$stmt->execute([$user_id, $current_week]);

if ($stmt->fetch()) {
    echo json_encode([
        'error' => 'Ya has reclamado tu recompensa esta semana. No puedes reclamar otra hasta la próxima semana.',
        'reclamado' => true
    ]);
    exit;
}

// Verificar que los puntos coincidan con una recompensa válida
$recompensas_validas = [
    5000 => 'Recarga de S/5 o snack sorpresa',
    10000 => 'Recarga de S/10 o canjeo de menú',
    1500 => 'Suscripción de un mes (app)',
    20000 => 'Vale digital de S/20'
];

if (!array_key_exists($puntos, $recompensas_validas)) {
    echo json_encode(['error' => 'La recompensa seleccionada no es válida']);
    exit;
}

// Insertar nuevo reclamo
try {
    $stmt = $pdo->prepare("INSERT INTO recompensas_reclamadas (user_id, puntos_reclamados, descripcion, semana_year) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $puntos, $descripcion, $current_week]);
    
    echo json_encode([
        'success' => '¡Recompensa reclamada con éxito!',
        'recompensa' => $recompensas_validas[$puntos],
        'semana' => $current_week
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error al guardar en la base de datos: ' . $e->getMessage()]);
}
// Al final de reclamar_recompensa.php, después del INSERT exitoso:
$_SESSION['recompensa_reclamada'] = true;
header('Location: index.php?recompensa=success');
exit;