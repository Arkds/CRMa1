<?php
require 'db.php';
require 'config.php'; // trae el token

// Validación del token
if (!isset($_GET['token']) || $_GET['token'] !== LANDING_API_TOKEN) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT 
        l.id,
        p.name AS product_name,
        l.campaña,
        l.vendedor_id,
        l.slug,
        l.titulo,
        l.descripcion,
        l.imagen_destacada,
        l.precio,
        l.url_pago,
        l.url_whatsapp,
        l.clicks_total,
        l.fecha_creacion
    FROM landing_pages l
    LEFT JOIN products p ON l.product_id = p.id
    ORDER BY l.id ASC");

    $landings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($landings);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
