<?php
require 'datos_index.php';

$user_constants = [
    'Sheyla' => 0.83,
    'Magaly' => 1.11,
    'Sonaly' => 1.11,
    'Frank' => 0.77,
    'Esther' => 1.33,
];
include('header.php');
?>

<div class="container mt-5">
    <div id="liveAlertPlaceholder"></div>

    <script>
        const alertPlaceholder = document.getElementById('liveAlertPlaceholder')
        const appendAlert = (message, type) => {
            const wrapper = document.createElement('div')
            wrapper.innerHTML = [
                `<div class="alert alert-${type} alert-dismissible" role="alert">`,
                `   <div>${message}</div>`,
                '   <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>',
                '</div>'
            ].join('')

            alertPlaceholder.append(wrapper)
        }

        // Evento para mostrar la alerta al hacer clic en el botón
        const alertTrigger = document.getElementById('liveAlertBtn')
        if (alertTrigger) {
            alertTrigger.addEventListener('click', () => {
                // Lista numerada de instrucciones
                const message = `
                <ol>
                    <li>IMPORTANTE: Toma precauciones de tus credenciales, son privados y solo un administrador puede cambiarlos.</li>
                    <li>Los datos de tu cuenta solo deben ser manejados por ti, no compartas tu contraseña.</li>
                    <li>Si algo no funciona, encuentras un error o abuso reportalo de inmediato</li>
                    <li>Reportes de ventas: Ver todas las ventas de todos por filtros.</li>
                    <li>Registrar ventas: Registro de ventas a México</li>
                    <li>Gestionar socios: Gestion de socios a1.</li>
                    <li>Reportes: Gestion de reportes diarios y semanales</li>
                    <li>Seguimientos: Gestión de clientes potenciales y seguimientos</li>
                    <li>Utiliza la barra de búsqueda para encontrar regístros específicos rápidamente.</li> 
                </ol>
            `;
                appendAlert(message, 'success');
            })
        }
    </script>

    <div class="container">
        <h1 class="text-center">Bienvenido(a), <?= htmlspecialchars($username) ?> 👋</h1>
        <hr>
    </div>

    <!-- Tabla de productos relevantes -->
    <div class="container mt-4">
        <div class="col">
            <section class="card p-3">
                <h2 class="text-center">Precios de productos</h2>
                <table id="relevantProductsTable" class="table table-striped display compact">
                    <thead>
                        <tr>
                            <th>Relevante</th>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= $product['relevance'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?>
                                </td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= $product['price'] !== null ? $product['price'] : 'No especificado' ?></td>
                                <td><?= htmlspecialchars($product['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <?php if ($role === 'admin'): ?>

            <div class="card mt-3">
                <div class="card-header bg-primary text-white">
                    <small class="d-block">Puntos Finales = (Ventas × Puntos Base) × Constante (ajustada por
                        dedicación)</small>
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
                        <tbody>
                            <?php foreach ($puntos_comisiones as $pc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pc['vendedor']) ?></td>
                                    <td><?= $pc['ventas'] ?></td>
                                    <td><?= $pc['puntos_base'] ?></td>
                                    <td><?= $pc['constante'] ?>
                                        <span class="badge bg-light text-dark" data-bs-toggle="tooltip"
                                            title="Basado en horas trabajadas y antigüedad">
                                            ⓘ
                                        </span>
                                    </td>
                                    <td class="font-weight-bold"><?= $pc['puntos_finales'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>





            <!-- TABLA DE PUNTOS POR VENTAS NORMALES -->
            <div class="card mt-4">
                <div class="card-header bg-success text-white">
                    <small class="d-block">Puntos Finales = (Ventas × Puntos Base) × Constante (ajustada por
                        dedicación)</small>
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
                        <tbody>
                            <?php foreach ($puntos_ventas_normales as $pvn): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pvn['vendedor']) ?></td>
                                    <td><?= $pvn['ventas_mxn'] ?></td>
                                    <td><?= $pvn['ventas_pen'] ?></td>
                                    <td><?= $pvn['total_ventas'] ?></td>
                                    <td>
                                        <?= $pvn['puntos_base'] ?>
                                        <?php if ($pvn['puntos_finales'] >= 4500): ?>
                                            <span class="badge bg-info">Rango 4500+ puntos</span>
                                        <?php elseif ($pvn['puntos_finales'] >= 2000): ?>
                                            <span class="badge bg-warning">Rango 2000+ puntos</span>
                                        <?php endif; ?>
                                    </td>


                                    <td><?= $pvn['constante'] ?></td>
                                    <td class="font-weight-bold"><?= $pvn['puntos_finales'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="progress mt-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                            style="width: <?= ($puntos_totales[$username] / 7500) * 100 ?>%">
                            <?= $puntos_totales[$username] ?? 0 ?> / 7500 pts
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
            </div>
            <!-- TABLA RESUMEN DE PUNTOS TOTALES -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h3 class="mb-0">Resumen General de Puntos</h3>
                    <p class="mb-0"><small>Período evaluado: <?= $rango_fechas ?></small></p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Posición</th>
                                    <th>Vendedor</th>
                                    <th>Puntos Comisiones</th>
                                    <th>Puntos Ventas Normales</th>
                                    <th>Puntos Totales</th>
                                    <th>Recompensa Alcanzada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $posicion = 1;
                                foreach ($puntos_totales as $vendedor => $total):
                                    // Obtener puntos por comisiones para este vendedor
                                    $puntos_com = 0;
                                    foreach ($puntos_comisiones as $pc) {
                                        if ($pc['vendedor'] === $vendedor) {
                                            $puntos_com = $pc['puntos_finales'];
                                            break;
                                        }
                                    }

                                    // Obtener puntos por ventas normales para este vendedor
                                    $puntos_vn = 0;
                                    foreach ($puntos_ventas_normales as $pvn) {
                                        if ($pvn['vendedor'] === $vendedor) {
                                            $puntos_vn = $pvn['puntos_finales'];
                                            break;
                                        }
                                    }

                                    // Determinar recompensa alcanzada
                                    $recompensa = '';
                                    if ($total >= 12000) {
                                        $recompensa = '<span class="badge bg-success">Vale S/20</span>';
                                    } elseif ($total >= 7500) {
                                        $recompensa = '<span class="badge bg-primary">Suscripción app</span>';
                                    } elseif ($total >= 5000) {
                                        $recompensa = '<span class="badge bg-info">Recarga S/10</span>';
                                    } elseif ($total >= 3000) {
                                        $recompensa = '<span class="badge bg-warning">Recarga S/5</span>';
                                    } else {
                                        $recompensa = '<span class="badge bg-secondary">En progreso</span>';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= $posicion++ ?></td>
                                        <td><?= htmlspecialchars($vendedor) ?></td>
                                        <td><?= number_format($puntos_com, 0) ?></td>
                                        <td><?= number_format($puntos_vn, 0) ?></td>
                                        <td class="font-weight-bold"><?= number_format($total, 0) ?></td>
                                        <td><?= $recompensa ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <th colspan="2">Totales</th>
                                    <th><?= number_format(array_sum(array_column($puntos_comisiones, 'puntos_finales')), 0) ?>
                                    </th>
                                    <th><?= number_format(array_sum(array_column($puntos_ventas_normales, 'puntos_finales')), 0) ?>
                                    </th>
                                    <th><?= number_format(array_sum($puntos_totales), 0) ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-3 p-3 bg-light rounded">
                        <h5>Detalles del Período:</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Ventas con Comisión</h6>
                                <ul>
                                    <li>100 puntos por cada venta registrada</li>
                                    <li>Constantes aplicadas según carga horaria</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Ventas Normales</h6>
                                <ul>
                                    <li>180 puntos por cada venta fuera de promoción</li>
                                    <li>Promoción: MXN &lt;150, PEN &lt;29.9</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
        <!-- TABLA DE RECOMPENSAS ALCANZABLES -->
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
                            <td>3,000</td>
                            <td>Recarga de S/5 o snack sorpresa</td>
                        </tr>
                        <tr>
                            <td>5,000</td>
                            <td>Recarga de S/10 o canjeo de menú</td>
                        </tr>
                        <tr>
                            <td>7,500</td>
                            <td>Suscripción de un mes (app)</td>
                        </tr>
                        <tr>
                            <td>12,000</td>
                            <td>Vale digital de S/20</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="container mt-5">
            <?php if ($role === 'admin'): ?>
                <!-- Dashboard completo para admin -->
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="chartComisiones"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="chartVentasNormales"></canvas>
                    </div>
                </div>
                <div class="mt-4">
                    <canvas id="chartResumen"></canvas>
                </div>
            <?php else: ?>
                <?php
                // Datos del usuario actual
                $puntos_usuario = $datos_para_mostrar['total'] ?? 0;
                $recompensas = [
                    3000 => "Recarga de S/5 o snack sorpresa",
                    5000 => "Recarga de S/10 o canjeo de menú",
                    7500 => "Suscripción de un mes (app)",
                    12000 => "Vale digital de S/20"
                ];

                // Determinar recompensas alcanzadas y siguientes
                $recompensas_alcanzadas = [];
                $recompensas_pendientes = [];
                $siguiente_recompensa = null;
                $puntos_para_siguiente = 0;

                foreach ($recompensas as $puntos => $descripcion) {
                    if ($puntos_usuario >= $puntos) {
                        $recompensas_alcanzadas[$puntos] = $descripcion;
                    } else {
                        if ($siguiente_recompensa === null) {
                            $siguiente_recompensa = $puntos;
                            $puntos_para_siguiente = $puntos - $puntos_usuario;
                        }
                        $recompensas_pendientes[$puntos] = $descripcion;
                    }
                }

                // Si ha alcanzado todas, mostrar la última como próxima
                if ($siguiente_recompensa === null && !empty($recompensas)) {
                    end($recompensas);
                    $siguiente_recompensa = key($recompensas);
                    $puntos_para_siguiente = 0;
                }
                ?>
                <!-- Vista personalizada para vendedores -->
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="chartMiProgreso"></canvas>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Tus recompensas</h5>

                                <?php if (!empty($recompensas_alcanzadas)): ?>
                                    <div class="mb-3">
                                        <h6 class="text-success">✅ Recompensas obtenidas</h6>
                                        <ul class="list-group">
                                            <?php foreach ($recompensas_alcanzadas as $puntos => $desc): ?>
                                                <li class="list-group-item list-group-item-success">
                                                    <strong><?= number_format($puntos) ?> pts:</strong> <?= $desc ?>
                                                    <?php if (!$recompensa_reclamada): ?>
                                                        <form method="POST" action="reclamar_recompensa.php"
                                                            onsubmit="return confirm('¿Seguro que deseas reclamar esta recompensa?');">
                                                            <input type="hidden" name="puntos" value="<?= $puntos_usuario ?>">
                                                            <input type="hidden" name="descripcion"
                                                                value="<?= $recompensas_alcanzadas[$siguiente_recompensa] ?? 'Recompensa alcanzada' ?>">
                                                            <button class="btn btn-success mt-3" type="submit">🎁 Reclamar
                                                                recompensa</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <div class="alert alert-success mt-3">✅ Ya reclamaste tu recompensa esta semana.
                                                        </div>
                                                    <?php endif; ?>

                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($recompensas_pendientes)): ?>
                                    <div>
                                        <h6 class="text-primary">🎯 Próximas recompensas</h6>
                                        <ul class="list-group">
                                            <?php foreach ($recompensas_pendientes as $puntos => $desc): ?>
                                                <li class="list-group-item">
                                                    <strong><?= number_format($puntos) ?> pts:</strong> <?= $desc ?>
                                                    <small class="text-muted d-block">
                                                        <?php if ($puntos == $siguiente_recompensa): ?>
                                                            <span class="text-primary">¡Faltan solo
                                                                <?= number_format($puntos_para_siguiente) ?> puntos!</span>
                                                        <?php else: ?>
                                                            Falta: <?= number_format($puntos - $puntos_usuario) ?> pts
                                                        <?php endif; ?>
                                                    </small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($role === 'admin'): ?>
            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Competencia por Equipos</h3>
                    <p class="mb-0"><small>Puntos calculados solo para ventas en horarios específicos + 15% bonus
                            equipo</small></p>
                </div>
                <div class="card-body">
                    <?php if (!empty($puntos_equipos)): ?>
                        <div class="row">
                            <?php
                            $equipo_colors = [
                                'mañana' => 'warning',
                                'tarde' => 'info',
                                'noche' => 'secondary',
                                'fin_semana' => 'success'
                            ];

                            foreach ($puntos_equipos as $nombre => $equipo): ?>
                                <div class="col-md-3">
                                    <div class="card text-white bg-<?= $equipo_colors[$nombre] ?? 'primary' ?> mb-3">
                                        <div class="card-header">
                                            <h4 class="card-title">Equipo <?= ucfirst($nombre) ?></h4>
                                            <small><?= $equipo['horario'] ?></small>
                                        </div>
                                        <div class="card-body">
                                            <h2 class="display-4 text-center"><?= number_format($equipo['puntos']) ?></h2>
                                            <button class="btn btn-sm btn-light w-100 mt-2" data-bs-toggle="collapse"
                                                data-bs-target="#detalle-<?= $nombre ?>">
                                                Ver Detalles
                                            </button>
                                        </div>
                                        <div class="card-footer">
                                            <?php if ($equipo['puntos'] >= 20000): ?>
                                                <span class="badge bg-success">Premio: S/50 + Cena</span>
                                            <?php elseif ($equipo['puntos'] >= 15000): ?>
                                                <span class="badge bg-primary">Premio: S/30 + Merienda</span>
                                            <?php elseif ($equipo['puntos'] >= 10000): ?>
                                                <span class="badge bg-info">Premio: S/20</span>
                                            <?php elseif ($equipo['puntos'] >= 5000): ?>
                                                <span class="badge bg-light text-dark">Premio: Snacks</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">En progreso</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Detalle colapsable -->
                                <div class="collapse" id="detalle-<?= $nombre ?>">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5>Detalle del Equipo <?= ucfirst($nombre) ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Vendedor</th>
                                                        <th>Horario</th>
                                                        <th>Ventas Normales</th>
                                                        <th>Puntos Ventas</th>
                                                        <th>Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($equipo['detalle'] as $vendedor => $datos): ?>
                                                        <tr>
                                                            <td><?= $vendedor ?></td>
                                                            <td><?= $datos['horario'] ?></td>
                                                            <td><?= $datos['ventas'] ?? 0 ?></td>
                                                            <td><?= number_format($datos['puntos'] ?? 0) ?></td>
                                                            <td><?= number_format(($datos['puntos'] ?? 0) + ($datos['puntos_comisiones'] ?? 0)) ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <tr class="table-active">
                                                        <td colspan="6" class="text-end"><strong>Total + 15% Bonus:</strong></td>
                                                        <td><strong><?= number_format($equipo['puntos']) ?></strong></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            No hay datos de ventas para mostrar en los horarios de equipos.
                        </div>
                    <?php endif; ?>
                    <div class="mt-4">
                        <canvas id="chartEquipos"></canvas>
                    </div>
                </div>


            </div>

            <script>
                // Gráfico de equipos optimizado
                const ctxEquipos = document.getElementById('chartEquipos').getContext('2d');
                const equiposData = <?= json_encode($puntos_equipos) ?>;

                // Preparar datos para el gráfico
                const labels = Object.keys(equiposData).map(nombre => `Equipo ${nombre.charAt(0).toUpperCase() + nombre.slice(1)}`);
                const puntosData = Object.values(equiposData).map(equipo => equipo.puntos);

                new Chart(ctxEquipos, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Puntos por Equipo',
                            data: puntosData,
                            backgroundColor: [
                                'rgba(255, 193, 7, 0.7)', // mañana - amarillo
                                'rgba(23, 162, 184, 0.7)', // tarde - azul
                                'rgba(108, 117, 125, 0.7)'  // noche - gris
                            ],
                            borderColor: [
                                'rgba(255, 193, 7, 1)',
                                'rgba(23, 162, 184, 1)',
                                'rgba(108, 117, 125, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Puntos (180 por venta válida)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Equipos'
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const equipo = equiposData[context.label.replace('Equipo ', '').toLowerCase()];
                                        return [
                                            `Puntos: ${context.raw.toLocaleString()}`,
                                            `Miembros: ${equipo.miembros.join(', ')}`,
                                            `Horario: ${equipo.horario}`
                                        ];
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: 'Puntos por Equipo (Ventas válidas ≥150 MXN o ≥29.9 PEN)',
                                font: {
                                    size: 16
                                }
                            }
                        }
                    }
                });
            </script>
        <?php endif; ?>


        <?php if ($role === 'admin'): ?>
            <div class="card mt-4">
                <div class="card-header bg-success text-white">
                    <h3>Recompensas Grupales</h3>
                    <small>Basado en el rendimiento colectivo del equipo</small>
                </div>
                <div class="card-body">
                    <?php foreach ($puntos_equipos as $nombre => $equipo): ?>
                        <div class="mb-4">
                            <h4>Equipo <?= ucfirst($nombre) ?></h4>

                            <?php if (!empty($equipo['recompensas_ganadas'])): ?>
                                <div class="alert alert-success">
                                    <h5>Recompensas Ganadas:</h5>
                                    <ul>
                                        <?php foreach ($equipo['recompensas_ganadas'] as $recompensa): ?>
                                            <li>
                                                <?= $recompensa['icono'] ?>                 <?= $recompensa['nombre'] ?>
                                                <small>(<?= number_format($recompensa['puntos_requeridos']) ?> pts)</small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if ($equipo['proxima_recompensa']): ?>
                                <div class="alert alert-info">
                                    <h5>Próxima Recompensa:</h5>
                                    <p>
                                        <strong><?= $equipo['proxima_recompensa']['icono'] ?>
                                            <?= $equipo['proxima_recompensa']['nombre'] ?></strong><br>
                                        Faltan <?= number_format($equipo['puntos_para_proxima']) ?> puntos
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    ¡El equipo ha ganado todas las recompensas disponibles!
                                </div>
                            <?php endif; ?>

                            <div class="progress">
                                <div class="progress-bar progress-bar-striped" role="progressbar"
                                    style="width: <?= min(100, ($equipo['puntos'] / 30000) * 100) ?>%"
                                    aria-valuenow="<?= $equipo['puntos'] ?>" aria-valuemin="0" aria-valuemax="30000">
                                    <?= number_format($equipo['puntos']) ?> / 30,000 pts
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-3 p-3 bg-light rounded">
                        <h5>Recompensas Disponibles:</h5>
                        <ul>
                            <?php foreach ($recompensas_grupales as $recompensa): ?>
                                <li>
                                    <strong><?= $recompensa['icono'] ?>         <?= $recompensa['nombre'] ?></strong> -
                                    <?= number_format($recompensa['puntos_requeridos']) ?> puntos
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <small class="text-muted">* Basado en un promedio de 3,000 puntos diarios por equipo</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>


        <?php if ($role !== 'admin'): ?>
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h3>Recompensas de tu Equipo</h3>
                </div>
                <div class="card-body">
                    <?php
                    // Encontrar el equipo del usuario actual
                    $equipo_usuario = null;
                    foreach ($puntos_equipos as $nombre => $equipo) {
                        if (in_array($username, $equipo['miembros'])) {
                            $equipo_usuario = $equipo;
                            break;
                        }
                    }
                    ?>

                    <?php if ($equipo_usuario): ?>
                        <h4>Equipo <?= ucfirst($nombre) ?></h4>
                        <p>Puntos totales: <strong><?= number_format($equipo_usuario['puntos']) ?></strong></p>

                        <?php if (!empty($equipo_usuario['recompensas_ganadas'])): ?>
                            <div class="alert alert-success">
                                <h5>Recompensas Ganadas:</h5>
                                <ul class="list-group">
                                    <?php foreach ($equipo_usuario['recompensas_ganadas'] as $recompensa): ?>
                                        <li class="list-group-item list-group-item-success">
                                            <?= $recompensa['icono'] ?>                 <?= $recompensa['nombre'] ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($equipo_usuario['proxima_recompensa']): ?>
                            <div class="alert alert-warning">
                                <h5>Próxima Recompensa:</h5>
                                <p>
                                    <?= $equipo_usuario['proxima_recompensa']['icono'] ?>
                                    <strong><?= $equipo_usuario['proxima_recompensa']['nombre'] ?></strong><br>
                                    Faltan <?= number_format($equipo_usuario['puntos_para_proxima']) ?> puntos
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-primary">
                                ¡Tu equipo ha ganado todas las recompensas disponibles!
                            </div>
                        <?php endif; ?>

                        <div class="progress mt-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                                style="width: <?= min(100, ($equipo_usuario['puntos'] / 30000) * 100) ?>%">
                                <?= number_format($equipo_usuario['puntos']) ?> / 30,000 pts
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            No estás asignado a ningún equipo actualmente.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Botón para mostrar/ocultar gráficos -->
        <div class="text-center mb-3">
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#graphsContainer"
                aria-expanded="false" aria-controls="graphsContainer" id="toggleGraphsBtn">
                Mostrar Gráficos
            </button>
        </div>

        <!-- Contenedor colapsable para los gráficos -->
        <div class="collapse" id="graphsContainer">
            <?php include('graphs.php'); ?>
        </div>
    </div>
</div>

<!-- Script para DataTables de la tabla de productos -->
<script>
    $(document).ready(function () {
        $('#relevantProductsTable').DataTable({
            paging: true,
            searching: true,
            order: [[0, 'desc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            }
        });

        // Cambiar el texto del botón cuando se muestra/ocultan los gráficos
        $('#graphsContainer').on('show.bs.collapse', function () {
            $('#toggleGraphsBtn').text('Ocultar Gráficos');
        }).on('hide.bs.collapse', function () {
            $('#toggleGraphsBtn').text('Mostrar Gráficos');
        });
    });
</script>

<!-- Script para mantener la sesión activa -->
<script>
    setInterval(() => {
        fetch('keep-alive.php')
            .then(response => console.log('Sesión actualizada'));
    }, 300000); // 5 minutos
</script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('chartMiProgreso').getContext('2d');
    const puntosUsuario = <?= $puntos_usuario ?>;
    const siguienteRecompensa = <?= $siguiente_recompensa ?? 0 ?>;
    const porcentaje = siguienteRecompensa > 0 ? Math.min(100, (puntosUsuario / siguienteRecompensa) * 100) : 100;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Tus puntos', 'Faltan para siguiente'],
            datasets: [{
                data: [puntosUsuario, Math.max(0, siguienteRecompensa - puntosUsuario)],
                backgroundColor: [
                    '#4bc0c0',
                    '#e8e8e8'
                ],
                borderWidth: 0
            }]
        },
        options: {
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return `${context.label}: ${context.raw.toLocaleString()} pts`;
                        }
                    }
                },
                title: {
                    display: true,
                    text: `Total: ${puntosUsuario.toLocaleString()} pts`,
                    font: {
                        size: 18,
                        weight: 'bold'
                    },
                    padding: {
                        top: 10,
                        bottom: 20
                    }
                },
                subtitle: {
                    display: true,
                    text: siguienteRecompensa > 0 ?
                        `${Math.round(porcentaje)}% de ${siguienteRecompensa.toLocaleString()} pts` :
                        '¡Has alcanzado todas las recompensas!',
                    color: '#666',
                    font: {
                        size: 14
                    },
                    padding: {
                        bottom: 20
                    }
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true
            }
        }
    });
</script>


<?php include('footer.php'); ?>