<?php
require 'db.php';

// Verificar si es admin (solo admin puede ejecutar este script directamente)
if (php_sapi_name() !== 'cli') {
    if (!isset($_COOKIE['user_session']) || json_decode(base64_decode($_COOKIE['user_session']), true)['role'] !== 'admin') {
        die("Acceso denegado");
    }
}

// Obtener fecha del último reset
$stmt = $pdo->query("SELECT ultima_fecha_grupal FROM metadatos_reseteo LIMIT 1");
$metadatos = $stmt->fetch();
$fecha_inicio_programa = $metadatos ? $metadatos['ultima_fecha_grupal'] : date('Y-m-d');

// Obtener todas las ventas sin puntos asignados desde la fecha de inicio
$stmt = $pdo->prepare("SELECT id, user_id, price, currency, created_at 
                      FROM sales 
                      WHERE puntos_asignados = FALSE 
                      AND DATE(created_at) >= ?
                      ORDER BY created_at ASC");
$stmt->execute([$fecha_inicio_programa]);
$ventas_sin_puntos = $stmt->fetchAll();

$total_procesadas = 0;
$total_puntos = 0;

foreach ($ventas_sin_puntos as $venta) {
    $pdo->beginTransaction();

    try {
        // Obtener la cantidad de la venta
        $stmt = $pdo->prepare("SELECT quantity FROM sales WHERE id = ?");
        $stmt->execute([$venta['id']]);
        $quantity = (int)$stmt->fetchColumn();

        if ($quantity > 0) {
            $puntos = $quantity * 50;

            // 1. Asignar puntos
            $stmt = $pdo->prepare("UPDATE users SET puntos_historicos = puntos_historicos + ? WHERE id = ?");
            $stmt->execute([$puntos, $venta['user_id']]);

            // 2. Historial
            $comentario = "Venta histórica #{$venta['id']} - " . 
                         ($venta['currency'] == 'MXN' ? "$" . number_format($venta['price'], 2) . " MXN" : 
                         "S/" . number_format($venta['price'], 2));
            $stmt = $pdo->prepare("INSERT INTO historial_puntos_historicos 
                                 (user_id, puntos, tipo, comentario, fecha_registro) 
                                 VALUES (?, ?, 'venta_normal', ?, ?)");
            $stmt->execute([$venta['user_id'], $puntos, $comentario, $venta['created_at']]);

            // 3. Marcar como procesada
            $stmt = $pdo->prepare("UPDATE sales SET puntos_asignados = TRUE, puntos_venta = ? WHERE id = ?");
            $stmt->execute([$puntos, $venta['id']]);

            $total_procesadas++;
            $total_puntos += $puntos;
        } else {
            // Si cantidad inválida o cero, marcar igualmente para evitar reintentos
            $stmt = $pdo->prepare("UPDATE sales SET puntos_asignados = TRUE WHERE id = ?");
            $stmt->execute([$venta['id']]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error procesando venta ID {$venta['id']}: " . $e->getMessage());
    }
}
  

// Registrar en el log de administración si se ejecuta manualmente
if (php_sapi_name() !== 'cli') {
    $admin_id = json_decode(base64_decode($_COOKIE['user_session']), true)['user_id'];
    $stmt = $pdo->prepare("INSERT INTO historial_puntos_historicos 
                          (user_id, puntos, tipo, comentario, admin_id) 
                          VALUES (?, ?, 'admin_proceso', ?, ?)");
    $stmt->execute([
        $admin_id, 
        0, 
        "Proceso automático ejecutado - Ventas procesadas: $total_procesadas, Puntos asignados: $total_puntos",
        $admin_id
    ]);

    echo json_encode([
        'status' => 'success',
        'ventas_procesadas' => $total_procesadas,
        'puntos_asignados' => $total_puntos,
        'fecha_inicio_programa' => $fecha_inicio_programa
    ]);
    $pdo = null; 
} else {
    // Log para ejecución en segundo plano
    error_log("Proceso automático de puntos ejecutado. Ventas: $total_procesadas, Puntos: $total_puntos");
    $pdo = null;
}


