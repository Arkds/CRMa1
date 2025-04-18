<?php
require 'db.php';
require 'puntos_personales.php'; 
if (isset($_COOKIE['user_session'])) {
    $user_data = json_decode(base64_decode($_COOKIE['user_session']), true);

    if ($user_data) {
        $user_id = $user_data['user_id'];
        $username = $user_data['username'];
        $role = $user_data['role'];
        $isAdmin = ($role === 'admin');
    } else {
        header("Location: login.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$products = $pdo->query("SELECT relevance, name, price, description FROM products ORDER BY relevance DESC, name ASC")->fetchAll();

$user_constants = [
    'Sheyla' => 0.83,
    'Magaly' => 1.11,
    'Sonaly' => 1.11,
    'Frank' => 0.77,
    'Esther' => 1.33,
];


$fecha_inicio_formateada = date('d/m/Y', strtotime('-7 days'));
$fecha_fin_formateada = date('d/m/Y');
$rango_fechas = "Del $fecha_inicio_formateada al $fecha_fin_formateada";

$niveles = [
    'Oro' => ['min' => 8000, 'class' => 'bg-warning'],
    'Plata' => ['min' => 4000, 'class' => 'bg-secondary'],
    'Bronce' => ['min' => 0, 'class' => 'bg-danger']
];

$datos_para_mostrar = [];
if ($role !== 'admin') {
    foreach ($puntos_comisiones as $pc) {
        if ($pc['vendedor'] === $username) {
            $datos_para_mostrar['comisiones'] = $pc;
            break;
        }
    }

    foreach ($puntos_ventas_normales as $pvn) {
        if ($pvn['vendedor'] === $username) {
            $datos_para_mostrar['ventas_normales'] = $pvn;
            break;
        }
    }

    $datos_para_mostrar['total'] = $puntos_totales[$username] ?? 0;
}
// Definir recompensas grupales (antes de usar la función calcularPuntosPorEquipo)
$recompensas_grupales = [
    [
        'nombre' => 'Caja de pizza para el turno completo',
        'puntos_requeridos' => 9000, // 3 días de buen rendimiento (3000*3)
        'icono' => '🍕'
    ],
    [
        'nombre' => 'Bono colectivo dividido (ej. S/60 para 3 personas: S/20 cada uno)',
        'puntos_requeridos' => 15000, // 5 días de buen rendimiento
        'icono' => '💰'
    ],
    [
        'nombre' => 'Tarde libre por rotación',
        'puntos_requeridos' => 21000, // 7 días de buen rendimiento
        'icono' => '🏖️'
    ],
    [
        'nombre' => 'Reconocimiento público ("Equipo campeón de la semana")',
        'puntos_requeridos' => 30000, // 10 días de excelente rendimiento
        'icono' => '🏆'
    ]
];


$current_week = date('2025-W12'); // Ejemplo: 2025-W16

// Verificar si el usuario ya reclamó recompensa esta semana
$stmt = $pdo->prepare("SELECT * FROM recompensas_reclamadas WHERE user_id = ? AND semana_year = ?");
$stmt->execute([$user_id, $current_week]);
$reclamo_actual = $stmt->fetch();

$recompensa_reclamada = false;
if ($reclamo_actual) {
    $recompensa_reclamada = true;
    $puntos_usuario = 0; // Mostrar visualmente como 0
}

// Función para calcular puntos por equipo
function calcularPuntosPorEquipo($pdo, $puntos_individuales, $user_constants, $recompensas_grupales)
{
    // Definición de equipos y horarios (solo días laborales)
    $equipos = [
        'mañana' => [
            'miembros' => [
                'Sheyla' => ['08:00', '14:00'],
                'Frank' => ['08:00', '14:00']
            ],
            'dias' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
        ],
        'tarde' => [
            'miembros' => [
                'Sheyla' => ['14:00', '17:00'],
                'Sonaly' => ['14:00', '20:00']
            ],
            'dias' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
        ],
        'noche' => [
            'miembros' => [
                'Magaly' => ['17:00', '23:00'],
                'Esther' => ['20:00', '23:00']
            ],
            'dias' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
        ]
    ];

    $puntos_equipos = [];
    // Modificar el rango de fechas para 10 días
    $periodo_inicio = date('Y-m-d 00:00:00', strtotime('-10 days'));  // 10 días atrás
    $periodo_fin = date('Y-m-d 23:59:59');  // Hasta el final del día actual


    foreach ($equipos as $nombre => $equipo) {
        $puntos_equipo = 0;
        $ventas_equipo = [];
        $datos_grafico = [];

        foreach ($equipo['miembros'] as $miembro => $horario) {
            // Obtener ID del usuario
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$miembro]);
            $user_id = $stmt->fetchColumn();

            if (!$user_id)
                continue;

            // Consulta para ventas válidas (≥150 MXN o ≥29.90 PEN) en horario específico
            $sql_ventas = "
                SELECT 
                    SUM(
                        CASE 
                            WHEN (s.currency = 'MXN' AND s.price >= 150) OR 
                                 (s.currency = 'PEN' AND s.price >= 29.9)
                            THEN s.quantity 
                            ELSE 0 
                        END
                    ) as ventas_validas,
                    DATE(s.created_at) as fecha
                FROM sales s
                WHERE s.user_id = :user_id
                AND s.created_at BETWEEN :inicio AND :fin
                AND DAYNAME(s.created_at) IN ('" . implode("','", $equipo['dias']) . "')
                AND TIME(s.created_at) BETWEEN :hora_inicio AND :hora_fin
                GROUP BY DATE(s.created_at)
                ORDER BY fecha
            ";

            $stmt = $pdo->prepare($sql_ventas);
            $stmt->execute([
                'user_id' => $user_id,
                'inicio' => $periodo_inicio,
                'fin' => $periodo_fin,
                'hora_inicio' => $horario[0] . ':00',
                'hora_fin' => $horario[1] . ':00'
            ]);

            $ventas_miembro = $stmt->fetchAll();

            // Procesar datos para el gráfico
            foreach ($ventas_miembro as $venta) {
                if ($venta['ventas_validas'] > 0) {
                    $datos_grafico[$venta['fecha']] = ($datos_grafico[$venta['fecha']] ?? 0) + $venta['ventas_validas'];
                }
            }

            // Calcular puntos (180 puntos por venta válida)
            $total_ventas_validas = array_sum(array_column($ventas_miembro, 'ventas_validas'));
            $puntos = $total_ventas_validas * 180;

            // Aplicar constante del usuario

            $ventas_equipo[$miembro] = [
                'ventas' => $total_ventas_validas,
                'puntos' => $puntos,
                'horario' => $horario[0] . ' a ' . $horario[1],
                'detalle_grafico' => $datos_grafico
            ];

            $puntos_equipo += $puntos;
        }

        // REMOVED: Se eliminó el bonus del 15% por equipo


        $puntos_equipos[$nombre] = [
            'puntos' => $puntos_equipo,
            'miembros' => array_keys($equipo['miembros']),
            'horario' => 'Lunes a Viernes - ' . implode(' / ', array_map(
                function ($h) {
                    return $h[0] . ' a ' . $h[1];
                },
                $equipo['miembros']
            )),
            'detalle' => $ventas_equipo,
            'datos_grafico' => $datos_grafico
        ];
    }
    foreach ($puntos_equipos as &$equipo) {
        $equipo['recompensas_ganadas'] = [];
        $equipo['proxima_recompensa'] = null;
        $equipo['puntos_para_proxima'] = 0;

        foreach ($recompensas_grupales as $recompensa) {
            if ($equipo['puntos'] >= $recompensa['puntos_requeridos']) {
                $equipo['recompensas_ganadas'][] = $recompensa;
            } else {
                if ($equipo['proxima_recompensa'] === null) {
                    $equipo['proxima_recompensa'] = $recompensa;
                    $equipo['puntos_para_proxima'] = $recompensa['puntos_requeridos'] - $equipo['puntos'];
                }
            }
        }
    }


    return $puntos_equipos;
}



// Llamar a la función después de calcular los puntos individuales
$puntos_equipos = calcularPuntosPorEquipo($pdo, $puntos_totales, $user_constants, $recompensas_grupales);
// Creamos los datos para el gráfico de resumen de puntos
$labels = [];
$data = [];

foreach ($puntos_totales as $vendedor => $total) {
    $labels[] = $vendedor;
    $data[] = $total;
}

$labels_json = json_encode($labels);
$data_json = json_encode($data);

?>


