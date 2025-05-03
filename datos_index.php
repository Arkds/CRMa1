<?php
require 'db.php';
require 'puntos_personales.php';

$puntos_comisiones = $puntos_comisiones ?? [];
$puntos_ventas_normales = $puntos_ventas_normales ?? [];
$puntos_totales = $puntos_totales ?? [];


// Autenticación y obtención de datos del usuario
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

// Obtener productos relevantes
$products = $pdo->query("SELECT relevance, name, price, description FROM products ORDER BY relevance DESC, name ASC")->fetchAll();


// Configuración de rangos de fechas
$fecha_inicio_formateada = date('d/m/Y', strtotime('-7 days'));
$fecha_fin_formateada = date('d/m/Y');
$rango_fechas = "Del $fecha_inicio_formateada al $fecha_fin_formateada";

$niveles = [
    'Oro' => ['min' => 8000, 'class' => 'bg-warning'],
    'Plata' => ['min' => 4000, 'class' => 'bg-secondary'],
    'Bronce' => ['min' => 0, 'class' => 'bg-danger']
];

// Datos para mostrar al usuario no admin
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

// Recompensas grupales
$recompensas_grupales = [
    [
        'nombre' => 'Caja de pizza para el turno completo',
        'puntos_requeridos' => 20000,
        'icono' => '🍕'
    ],
    [
        'nombre' => 'Bono colectivo dividido (ej. S/60 para 3 personas: S/20 cada uno)',
        'puntos_requeridos' => 25000,
        'icono' => '💰'
    ],
    [
        'nombre' => 'Tarde libre por rotación',
        'puntos_requeridos' => 30000,
        'icono' => '🏖️'
    ],
    [
        'nombre' => 'Reconocimiento público ("Equipo campeón de la semana")',
        'puntos_requeridos' => 35000,
        'icono' => '🏆'
    ]
];

// Verificación de recompensas reclamadas
$current_week = date('o-\WW'); // Formato ISO correcto para semana (ej: "2025-W12")
$stmt = $pdo->prepare("SELECT * FROM recompensas_reclamadas WHERE user_id = ? AND semana_year = ?");
$stmt->execute([$user_id, $current_week]);
$reclamo_actual = $stmt->fetch();

$recompensa_reclamada = false;
$recompensa_data = null;

if ($reclamo_actual) {
    $recompensa_reclamada = true;
    $recompensa_data = [
        'puntos' => $reclamo_actual['puntos_reclamados'],
        'descripcion' => $reclamo_actual['descripcion'],
        'fecha' => $reclamo_actual['fecha_reclamo']
    ];

    // No es necesario establecer $puntos_usuario = 0 aquí a menos que sea parte de tu lógica
    // $puntos_usuario = 0; // (Solo si es necesario para tu aplicación)
}
// Obtener todas las recompensas reclamadas (para el resumen admin)
// Al inicio del archivo, después de la conexión a la base de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!$isAdmin) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    if ($_POST['accion'] === 'actualizar_pago') {

        try {
            $id = intval($_POST['id'] ?? 0);
            $pagada = intval($_POST['pagada'] ?? 0);

            error_log("Actualizando recompensa ID: $id, Estado: $pagada"); // Log para depuración

            $stmt = $pdo->prepare("UPDATE recompensas_reclamadas SET pagada = ? WHERE id = ?");
            $stmt->execute([$pagada, $id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Estado actualizado correctamente',
                    'id' => $id,
                    'pagada' => $pagada
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró la recompensa con ID ' . $id,
                    'id' => $id,
                    'pagada' => $pagada
                ]);
            }
        } catch (PDOException $e) {
            error_log("Error en la base de datos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Error en la base de datos: ' . $e->getMessage()
            ]);
        }
        exit; // Importante: terminar la ejecución
    }
}
// Función para calcular puntos por equipo (mensual)
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

    // Obtener el primer día del mes actual y el último día del mes actual
    $primer_dia_mes = date('Y-m-01 00:00:00');
    $ultimo_dia_mes = date('Y-m-t 23:59:59');

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

            // Consulta para ventas válidas (≥150 MXN o ≥29.90 PEN) en horario específico DURANTE EL MES ACTUAL
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
                'inicio' => $primer_dia_mes,
                'fin' => $ultimo_dia_mes,
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
            // Recalcular puntos por venta válida (hazla = 210, otros = 180)
            $puntos = 0;
            
            $sql_detalles = "
                SELECT s.product_name, s.quantity
                FROM sales s
                WHERE s.user_id = :user_id
                AND s.created_at BETWEEN :inicio AND :fin
                AND (
                    (s.currency = 'MXN' AND s.price >= 150) OR 
                    (s.currency = 'PEN' AND s.price >= 29.9)
                )
                AND DAYNAME(s.created_at) IN ('" . implode("','", $equipo['dias']) . "')
                AND TIME(s.created_at) BETWEEN :hora_inicio AND :hora_fin
            ";
            
            $stmt_detalles = $pdo->prepare($sql_detalles);
            $stmt_detalles->execute([
                'user_id' => $user_id,
                'inicio' => $primer_dia_mes,
                'fin' => $ultimo_dia_mes,
                'hora_inicio' => $horario[0] . ':00',
                'hora_fin' => $horario[1] . ':00'
            ]);
            
            $ventas_detalle = $stmt_detalles->fetchAll();
            
            $total_ventas_validas = 0;
            foreach ($ventas_detalle as $venta) {
                $canal = explode('|', $venta['product_name'])[0];
                $puntos_por_venta = (strtolower(trim($canal)) === 'hazla') ? 210 : 180;
                $puntos += $puntos_por_venta * $venta['quantity'];
                $total_ventas_validas += $venta['quantity'];
            }


           
            $ventas_equipo[$miembro] = [
                'ventas' => $total_ventas_validas,
                'puntos' => $puntos,
                'horario' => $horario[0] . ' a ' . $horario[1],
                'detalle_grafico' => $datos_grafico
            ];

            $puntos_equipo += $puntos;
        }

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
            'datos_grafico' => $datos_grafico,
            'mes_actual' => date('F Y') // Agregamos el mes actual para referencia
        ];
    }

    // Calcular recompensas grupales (ajustar puntos requeridos para mensual)
    foreach ($puntos_equipos as &$equipo) {
        $equipo['recompensas_ganadas'] = [];
        $equipo['proxima_recompensa'] = null;
        $equipo['puntos_para_proxima'] = 0;

        foreach ($recompensas_grupales as $recompensa) {
            // Multiplicar por 4 para ajustar de semanal a mensual (aproximadamente)
            $puntos_requeridos_mensual = $recompensa['puntos_requeridos'] ;

            if ($equipo['puntos'] >= $puntos_requeridos_mensual) {
                $equipo['recompensas_ganadas'][] = array_merge($recompensa, ['puntos_requeridos' => $puntos_requeridos_mensual]);
            } else {
                if ($equipo['proxima_recompensa'] === null) {
                    $equipo['proxima_recompensa'] = array_merge($recompensa, ['puntos_requeridos' => $puntos_requeridos_mensual]);
                    $equipo['puntos_para_proxima'] = $puntos_requeridos_mensual - $equipo['puntos'];
                }
            }
        }
    }

    return $puntos_equipos;
}

// Calcular puntos por equipo
$puntos_equipos = calcularPuntosPorEquipo($pdo, $puntos_totales, $user_constants, $recompensas_grupales);

// Datos para gráficos (si los necesitaras en otros archivos)
$labels = [];
$data = [];

foreach ($puntos_totales as $vendedor => $total) {
    $labels[] = $vendedor;
    $data[] = $total;
}

$labels_json = json_encode($labels);
$data_json = json_encode($data);


// En datos_index.php - después de la lógica principal

// 1. Tabla de Puntos por Ventas con Comisión
$tabla_comisiones = '
<div class="card mt-3">
    <div class="card-header bg-primary text-white">
        <small class="d-block">Puntos Finales = (Ventas × Puntos Base) × Constante (ajustada por dedicación)</small>
        <h3 class="mb-0">Puntos por Ventas con Comisión</h3>
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Vendedor</th>
                    <th>Ventas Registradas</th>
                    <th>Puntos Base (100/venta)</th>
                    <th>Constante</th>
                    <th>Puntos Finales</th>
                </tr>
            </thead>
            <tbody>';

foreach ($puntos_comisiones as $pc) {
    $tabla_comisiones .= '
                <tr>
                    <td>' . htmlspecialchars($pc['vendedor']) . '</td>
                    <td>' . $pc['ventas'] . '</td>
                    <td>' . $pc['puntos_base'] . '</td>
                    <td>' . $pc['constante'] . '
                        <span class="badge bg-light text-dark" data-bs-toggle="tooltip" title="Basado en horas trabajadas y antigüedad">
                            ⓘ
                        </span>
                    </td>
                    <td class="font-weight-bold">' . $pc['puntos_finales'] . '</td>
                </tr>';
}

$tabla_comisiones .= '
            </tbody>
        </table>
    </div>
</div>';

// 2. Tabla de Puntos por Ventas Normales
$tabla_ventas_normales = '
<div class="card mt-4">
    <div class="card-header bg-success text-white">
        <small class="d-block">Puntos Finales = (Ventas × Puntos Base) × Constante (ajustada por dedicación)</small>
        <h3 class="mb-0">Puntos por Ventas Fuera de Promoción</h3>
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Vendedor</th>
                    <th>Ventas MXN (≥149)</th>
                    <th>Ventas PEN (≥29.8)</th>
                    <th>Total Ventas</th>
                    <th>Puntos Base</th>
                    <th>Constante</th>
                    <th>Puntos Finales</th>
                </tr>
            </thead>
            <tbody>';

foreach ($puntos_ventas_normales as $pvn) {
    $badge = '';
    if ($pvn['puntos_finales'] >= 4500) {
        $badge = '<span class="badge bg-info">Rango 4500+ puntos</span>';
    } elseif ($pvn['puntos_finales'] >= 2000) {
        $badge = '<span class="badge bg-warning">Rango 2000+ puntos</span>';
    }

    $tabla_ventas_normales .= '
                <tr>
                    <td>' . htmlspecialchars($pvn['vendedor']) . '</td>
                    <td>' . $pvn['ventas_mxn'] . '</td>
                    <td>' . $pvn['ventas_pen'] . '</td>
                    <td>' . $pvn['total_ventas'] . '</td>
                    <td>' . $pvn['puntos_base'] . $badge . '</td>
                    <td>' . $pvn['constante'] . '</td>
                    <td class="font-weight-bold">' . $pvn['puntos_finales'] . '</td>
                </tr>';
}

$tabla_ventas_normales .= '
            </tbody>
        </table>
        <div class="progress mt-3">
           <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
    style="width: ' . ((isset($puntos_totales[$username]) ? $puntos_totales[$username] : 0) / 7500 * 100) . '%">
    ' . ($puntos_totales[$username] ?? 0) . ' / 7500 pts
</div>

        </div>
        <div class="alert alert-info mt-3">
            <strong>Rangos de puntos:</strong>
            <ul>
                <li>10-19 ventas: 2,000 puntos</li>
                <li>20+ ventas: 4,500 puntos</li>
            </ul>
        </div>
    </div>
</div>';

$resumen_puntos = '
<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h3 class="mb-0">Resumen General de Puntos</h3>
        <p class="mb-0"><small>Período evaluado: ' . $rango_fechas . '</small></p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="tablaRecompensas">
                <thead class="thead-dark">
                    <tr>
                        <th>Posición</th>
                        <th>Vendedor</th>
                        <th>Puntos Comisiones</th>
                        <th>Puntos Ventas Normales</th>
                        <th>Puntos Totales</th>
                        <th>Recompensa Alcanzada</th>
                        ' . ($isAdmin ? '<th>Estado</th><th>Acciones</th>' : '') . '
                    </tr>
                </thead>
                <tbody>';

$posicion = 1;
foreach ($puntos_totales as $vendedor => $total) {
    $puntos_com = 0;
    foreach ($puntos_comisiones as $pc) {
        if ($pc['vendedor'] === $vendedor) {
            $puntos_com = $pc['puntos_finales'];
            break;
        }
    }

    $puntos_vn = 0;
    foreach ($puntos_ventas_normales as $pvn) {
        if ($pvn['vendedor'] === $vendedor) {
            $puntos_vn = $pvn['puntos_finales'];
            break;
        }
    }

    $recompensa = '';
    if ($total >= 20000) {
        $recompensa = '<span class="badge bg-success">Vale S/20</span>';
    } elseif ($total >= 15000) {
        $recompensa = '<span class="badge bg-primary">Suscripción app</span>';
    } elseif ($total >= 10000) {
        $recompensa = '<span class="badge bg-info">Recarga S/10</span>';
    } elseif ($total >= 5000) {
        $recompensa = '<span class="badge bg-warning">Recarga S/5</span>';
    } else {
        $recompensa = '<span class="badge bg-secondary">En progreso</span>';
    }

    // Buscar si tiene recompensas reclamadas
    $stmt = $pdo->prepare("SELECT recompensas_reclamadas.id, recompensas_reclamadas.pagada, 
                      recompensas_reclamadas.puntos_reclamados, recompensas_reclamadas.descripcion, 
                      recompensas_reclamadas.fecha_reclamo 
                      FROM recompensas_reclamadas 
                      JOIN users ON recompensas_reclamadas.user_id = users.id
                      WHERE users.username = ?
                      ORDER BY recompensas_reclamadas.fecha_reclamo DESC");
    $stmt->execute([$vendedor]);
    $recompensas_usuario = $stmt->fetchAll();

    $resumen_puntos .= '
                    <tr>
                        <td>' . $posicion++ . '</td>
                        <td>' . htmlspecialchars($vendedor) . '</td>
                        <td>' . number_format($puntos_com, 0) . '</td>
                        <td>' . number_format($puntos_vn, 0) . '</td>
                        <td class="font-weight-bold">' . number_format($total, 0) . '</td>
                        <td>' . $recompensa . '</td>';

    if ($isAdmin) {
        $resumen_puntos .= '
                        <td>';

        foreach ($recompensas_usuario as $r) {
            $estado = $r['pagada'] ?
                '<span class="badge bg-success">Pagada</span>' :
                '<span class="badge bg-warning">Pendiente</span>';

            $resumen_puntos .= '
                            <div class="mb-1">
                                ' . htmlspecialchars($r['descripcion']) . '
                                <small class="d-block">' . $estado . ' - ' . date('d/m/Y', strtotime($r['fecha_reclamo'])) . '</small>
                            </div>';
        }

        $resumen_puntos .= '
                        </td>
                        <td>';

        foreach ($recompensas_usuario as $r) {
            $checked = $r['pagada'] ? 'checked' : '';
            $resumen_puntos .= '
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input toggle-pago" 
                                       type="checkbox" 
                                       data-id="' . $r['id'] . '" 
                                       ' . $checked . '>
                                <label class="form-check-label">Marcar como pagada</label>
                            </div>';
        }

        $resumen_puntos .= '
                        </td>';
    }

    $resumen_puntos .= '
                    </tr>';
}

$resumen_puntos .= '
                </tbody>
            </table>
        </div>
    </div>
</div>';

// JavaScript para manejar los switches
// Reemplaza el código JavaScript actual con este:
$resumen_puntos .= '
<script>
document.querySelectorAll(".toggle-pago").forEach(switchEl => {
    switchEl.addEventListener("change", function() {
        const recompensaId = this.getAttribute("data-id");
        const pagada = this.checked ? 1 : 0;
        const switchElement = this;
        
        switchElement.disabled = true;
        const originalState = this.checked;
        
        fetch(window.location.href, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `accion=actualizar_pago&id=${recompensaId}&pagada=${pagada}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error HTTP! estado: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message);
            }
            // Actualizar badge visualmente
            const badge = switchElement.closest("tr").querySelector(".badge");
            if (badge) {
                badge.className = pagada ? "badge bg-success" : "badge bg-warning";
                badge.textContent = pagada ? "Pagada" : "Pendiente";
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Error: " + error.message);
            switchElement.checked = !originalState;
        })
        .finally(() => {
            switchElement.disabled = false;
        });
    });
});
</script>';

// 4. Recompensas Disponibles
$recompensas_disponibles = '
<div class="card mt-4">
    <div class="card-header bg-warning">
        <h3 class="mb-0">Recompensas Disponibles</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Puntos Requeridos</th>
                    <th>Recompensa</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>5,000</td>
                    <td>Recarga de S/5 o snack sorpresa</td>
                </tr>
                <tr>
                    <td>10,000</td>
                    <td>Recarga de S/10 o canjeo de menú</td>
                </tr>
                <tr>
                    <td>15,000</td>
                    <td>Suscripción de un mes (app)</td>
                </tr>
                <tr>
                    <td>20,000</td>
                    <td>Vale digital de S/20</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>';

// 6. Recompensas Grupales
$recompensas_grupales_html = '
<div class="card mt-4">
    <div class="card-header bg-success text-white">
        <h3>Recompensas Grupales Mensuales</h3>
        <small>Puntos acumulados durante el mes actual</small>
    </div>
    <div class="card-body">';

foreach ($puntos_equipos as $nombre => $equipo) {
    $max_puntos = 35000; // Puntos máximos para la barra de progreso
    
    $recompensas_grupales_html .= '
        <div class="mb-4">
            <h4>Equipo ' . ucfirst($nombre) . '</h4>';

    if (!empty($equipo['recompensas_ganadas'])) {
        $recompensas_grupales_html .= '
            <div class="alert alert-success">
                <h5>Recompensas Ganadas:</h5>
                <ul>';

        foreach ($equipo['recompensas_ganadas'] as $recompensa) {
            $recompensas_grupales_html .= '
                    <li>
                        ' . $recompensa['icono'] . ' ' . $recompensa['nombre'] . '
                        <small>(' . number_format($recompensa['puntos_requeridos']) . ' pts)</small>
                    </li>';
        }

        $recompensas_grupales_html .= '
                </ul>
            </div>';
    }

    if ($equipo['proxima_recompensa']) {
        $recompensas_grupales_html .= '
            <div class="alert alert-info">
                <h5>Próxima Recompensa:</h5>
                <p>
                    <strong>' . $equipo['proxima_recompensa']['icono'] . ' ' . $equipo['proxima_recompensa']['nombre'] . '</strong><br>
                    Faltan ' . number_format($equipo['puntos_para_proxima']) . ' puntos
                </p>
            </div>';
    } else {
        $recompensas_grupales_html .= '
            <div class="alert alert-warning">
                ¡El equipo ha ganado todas las recompensas disponibles!
            </div>';
    }

    // Barra de progreso con marcas
    $recompensas_grupales_html .= '
            <div class="progress-container mb-4" style="position: relative; height: 40px;">
                <div class="progress" style="height: 30px;">
                    <div class="progress-bar progress-bar-striped" role="progressbar"
                        style="width: ' . min(100, ($equipo['puntos'] / $max_puntos) * 100) . '%"
                        aria-valuenow="' . $equipo['puntos'] . '" 
                        aria-valuemin="0" 
                        aria-valuemax="' . $max_puntos . '">
                        ' . number_format($equipo['puntos']) . ' / ' . number_format($max_puntos) . ' pts
                    </div>
                </div>';
    
    // Añadir marcas para cada recompensa
    foreach ($recompensas_grupales as $recompensa) {
        $posicion = ($recompensa['puntos_requeridos'] / $max_puntos) * 100;
        $recompensas_grupales_html .= '
                <div style="position: absolute; left: ' . $posicion . '%; top: 30px; transform: translateX(-50%);">
                    <div style="position: relative; text-align: center;">
                        <div style="width: 2px; height: 10px; background: #dc3545; margin: 0 auto;"></div>
                        <div style="font-size: 12px; color: #495057; white-space: nowrap;">
                            ' . number_format($recompensa['puntos_requeridos']) . ' pts
                        </div>
                    </div>
                </div>';
    }
    
    $recompensas_grupales_html .= '
            </div>
        </div>';
}

$recompensas_grupales_html .= '
        <div class="mt-3 p-3 bg-light rounded">
            <h5>Recompensas Disponibles:</h5>
            <ul>';

foreach ($recompensas_grupales as $recompensa) {
    $recompensas_grupales_html .= '
                <li>
                    <strong>' . $recompensa['icono'] . ' ' . $recompensa['nombre'] . '</strong> -
                    ' . number_format($recompensa['puntos_requeridos']) . ' puntos
                </li>';
}

$recompensas_grupales_html .= '
            </ul>
            <small class="text-muted">* Basado en un promedio de 3,000 puntos diarios por equipo</small>
        </div>
    </div>
</div>';

// Añadir estilos CSS para mejorar la visualización
$recompensas_grupales_html .= '
<style>
    .progress-container {
        position: relative;
        margin-bottom: 50px;
    }
    @media (max-width: 768px) {
        .progress-container {
            margin-bottom: 70px;
        }
        .progress-container div[style*="position: absolute"] {
            font-size: 10px;
        }
    }
</style>';

// 7. Recompensas de Equipo (para no admin)
$recompensas_equipo = '';
if ($role !== 'admin') {
    $equipo_usuario = null;
    $nombre_equipo = '';
    foreach ($puntos_equipos as $nombre => $equipo) {
        if (in_array($username, $equipo['miembros'])) {
            $equipo_usuario = $equipo;
            $nombre_equipo = $nombre;
            break;
        }
    }

    $recompensas_equipo = '
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h3>Recompensas Mensuales de tu Equipo</h3>
        </div>
        <div class="card-body">';

    if ($equipo_usuario) {
        $max_puntos = 35000; // Puntos máximos para la barra de progreso
        
        $recompensas_equipo .= '
            <h4>Equipo ' . ucfirst($nombre_equipo) . '</h4>
            <p>Puntos totales: <strong>' . number_format($equipo_usuario['puntos']) . '</strong> (Desde el primero de este mes)</p>';

        if (!empty($equipo_usuario['recompensas_ganadas'])) {
            $recompensas_equipo .= '
            <div class="alert alert-success">
                <h5>Recompensas Ganadas:</h5>
                <ul class="list-group">';

            foreach ($equipo_usuario['recompensas_ganadas'] as $recompensa) {
                $recompensas_equipo .= '
                    <li class="list-group-item list-group-item-success">
                        ' . $recompensa['icono'] . ' ' . $recompensa['nombre'] . '
                        <span class="badge bg-primary float-end">' . number_format($recompensa['puntos_requeridos']) . ' pts</span>
                    </li>';
            }

            $recompensas_equipo .= '
                </ul>
            </div>';
        }

        if ($equipo_usuario['proxima_recompensa']) {
            $recompensas_equipo .= '
            <div class="alert alert-warning">
                <h5>Próxima Recompensa:</h5>
                <p>
                    ' . $equipo_usuario['proxima_recompensa']['icono'] . '
                    <strong>' . $equipo_usuario['proxima_recompensa']['nombre'] . '</strong><br>
                    Faltan ' . number_format($equipo_usuario['puntos_para_proxima']) . ' puntos
                </p>
            </div>';
        } else {
            $recompensas_equipo .= '
            <div class="alert alert-primary">
                ¡Tu equipo ha ganado todas las recompensas disponibles!
            </div>';
        }

        // Barra de progreso con marcas
        $recompensas_equipo .= '
            <div class="progress-container mb-4" style="position: relative; height: 60px;">
                <div class="progress" style="height: 30px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                        style="width: ' . min(100, ($equipo_usuario['puntos'] / $max_puntos) * 100) . '%"
                        aria-valuenow="' . $equipo_usuario['puntos'] . '" 
                        aria-valuemin="0" 
                        aria-valuemax="' . $max_puntos . '">
                        ' . number_format($equipo_usuario['puntos']) . ' / ' . number_format($max_puntos) . ' pts
                    </div>
                </div>';
        
        // Añadir marcas para cada recompensa
        foreach ($recompensas_grupales as $recompensa) {
            $posicion = ($recompensa['puntos_requeridos'] / $max_puntos) * 100;
            $recompensas_equipo .= '
                <div style="position: absolute; left: ' . $posicion . '%; top: 35px; transform: translateX(-50%);">
                    <div style="position: relative; text-align: center;">
                        <div style="width: 2px; height: 10px; background: #dc3545; margin: 0 auto;"></div>
                        <div style="font-size: 12px; color: #495057; white-space: nowrap;">
                            ' . number_format($recompensa['puntos_requeridos']) . ' pts
                        </div>
                    </div>
                </div>';
        }
        
        $recompensas_equipo .= '
            </div>';
    } else {
        $recompensas_equipo .= '
            <div class="alert alert-warning">
                No estás asignado a ningún equipo actualmente.
            </div>';
    }

    $recompensas_equipo .= '
        </div>
    </div>';

    // Añadir estilos CSS para mejorar la visualización
    $recompensas_equipo .= '
    <style>
        .progress-container {
            position: relative;
            margin-bottom: 50px;
        }
        @media (max-width: 768px) {
            .progress-container {
                margin-bottom: 70px;
            }
            .progress-container div[style*="position: absolute"] {
                font-size: 10px;
            }
        }
        .list-group-item.list-group-item-success .badge {
            background-color: #0d6efd !important;
            color: white !important;
        }
    </style>';
}

?>