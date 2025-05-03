<?php
// puntos_personales.php

// Función para obtener el último lunes a las 00:00:00
function getLastMonday() {
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $dayOfWeek = (int)$today->format('N'); // 1 (lunes) - 7 (domingo)
    
    // Si hoy no es lunes, retroceder al lunes anterior
    if ($dayOfWeek > 1) {
        $today->modify('-'.($dayOfWeek - 1).' days');
    }
    
    return $today->format('Y-m-d H:i:s');
}


$user_constants = [
    'Sheyla' => 0.83,
    'Magaly' => 1.11,
    'Sonaly' => 1.11,
    'Frank' => 0.77,
    'Esther' => 1.33,
];
$canal_constants = [
    'hazla' => 1.3
];



// Obtener el último lunes y la fecha/hora actual
$inicio_semana = getLastMonday();
$fin_semana = date('Y-m-d H:i:s');

// Consulta para comisiones (solo desde el lunes)
$stmt_comisiones = $pdo->prepare("
    SELECT u.username, COUNT(c.id) AS total_ventas
    FROM commissions c
    JOIN users u ON c.user_id = u.id
    WHERE c.created_at BETWEEN :inicio AND :fin
    GROUP BY u.username
");
$stmt_comisiones->execute([
    'inicio' => $inicio_semana,
    'fin' => $fin_semana
]);
$ventas_comision = $stmt_comisiones->fetchAll();

// Resto del código sigue igual...
foreach ($ventas_comision as $vc) {
    $puntos_base = $vc['total_ventas'] * 100;
    $constante = $user_constants[$vc['username']] ?? 1;

    $puntos_comisiones[] = [
        'vendedor' => $vc['username'],
        'ventas' => $vc['total_ventas'],
        'puntos_base' => $puntos_base,
        'constante' => $constante,
        'puntos_finales' => round($puntos_base * $constante)
    ];
}

// Consulta para ventas normales (solo desde el lunes)
$stmt_ventas_normales = $pdo->prepare("
    SELECT 
        u.username,
        SUM(CASE WHEN s.currency = 'MXN' AND s.price >= 150 THEN s.quantity ELSE 0 END) AS ventas_mxn,
        SUM(CASE WHEN s.currency = 'PEN' AND s.price >= 29.9 THEN s.quantity ELSE 0 END) AS ventas_pen
    FROM sales s
    JOIN users u ON s.user_id = u.id
    WHERE s.created_at BETWEEN :inicio AND :fin
    GROUP BY u.username
");
$stmt_ventas_normales->execute([
    'inicio' => $inicio_semana,
    'fin' => $fin_semana
]);
$ventas_normales = $stmt_ventas_normales->fetchAll();

// Resto del código sigue igual...
foreach ($ventas_normales as $vn) {
    $total_ventas = $vn['ventas_mxn'] + $vn['ventas_pen'];
    $constante = $user_constants[$vn['username']] ?? 1;

    // Nueva lógica: obtener las ventas individuales para calcular por producto
    $stmt = $pdo->prepare("
        SELECT s.product_name, s.price, s.currency
        FROM sales s
        JOIN users u ON s.user_id = u.id
        WHERE u.username = :username
        AND s.created_at BETWEEN :inicio AND :fin
        AND (
            (s.currency = 'MXN' AND s.price >= 150)
            OR
            (s.currency = 'PEN' AND s.price >= 29.9)
        )
    ");
    $stmt->execute([
        'username' => $vn['username'],
        'inicio' => $inicio_semana,
        'fin' => $fin_semana
    ]);
    $ventas_validas = $stmt->fetchAll();

    $puntos_base = 0;
    foreach ($ventas_validas as $venta) {
        $canal = explode('|', $venta['product_name'])[0];
        $puntos_por_venta = (strtolower(trim($canal)) === 'hazla') ? 210 : 180;
        $puntos_base += $puntos_por_venta;
    }

    $puntos_finales = round($puntos_base * $constante);

    $puntos_ventas_normales[] = [
        'vendedor' => $vn['username'],
        'ventas_mxn' => $vn['ventas_mxn'],
        'ventas_pen' => $vn['ventas_pen'],
        'total_ventas' => $total_ventas,
        'puntos_base' => $puntos_base,
        'constante' => $constante,
        'puntos_finales' => $puntos_finales
    ];
}


// Sumar puntos totales (solo para esta semana)
$puntos_totales = [];
foreach ($puntos_comisiones as $pc) {
    $vendedor = $pc['vendedor'];
    if (!isset($puntos_totales[$vendedor])) {
        $puntos_totales[$vendedor] = 0;
    }
    $puntos_totales[$vendedor] += $pc['puntos_finales'];
}

foreach ($puntos_ventas_normales as $pvn) {
    $vendedor = $pvn['vendedor'];
    if (!isset($puntos_totales[$vendedor])) {
        $puntos_totales[$vendedor] = 0;
    }
    $puntos_totales[$vendedor] += $pvn['puntos_finales'];
}

arsort($puntos_totales);