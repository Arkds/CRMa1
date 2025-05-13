<?php
require 'datos_index.php';
if ($role === 'vendedor') {
    $fechaHoy = (new DateTime('now', new DateTimeZone('America/Lima')))->format('Y-m-d');

    // Recordatorios de hoy
    $stmtHoy = $pdo->prepare("
        SELECT COUNT(*) FROM report_clients rc
        JOIN reports r ON rc.report_id = r.id
        WHERE rc.fecha_recuerdo = :hoy AND r.user_id = :user_id
    ");
    $stmtHoy->execute([':hoy' => $fechaHoy, ':user_id' => $user_id]);
    $recordatoriosHoy = $stmtHoy->fetchColumn();

    // Recordatorios pasados
    $stmtPasado = $pdo->prepare("
        SELECT COUNT(*) FROM report_clients rc
        JOIN reports r ON rc.report_id = r.id
        WHERE rc.fecha_recuerdo < :hoy AND r.user_id = :user_id
    ");
    $stmtPasado->execute([':hoy' => $fechaHoy, ':user_id' => $user_id]);
    $recordatoriosPasados = $stmtPasado->fetchColumn();
}


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
    <?php if ($role === 'vendedor'): ?>
        <div class="d-flex flex-column gap-2 my-3">
            <?php if (!empty($recordatoriosHoy)): ?>
                <div class="alert alert-info py-2 px-3 d-flex justify-content-between align-items-center shadow-sm"
                    style="font-size: 0.9rem;">
                    <span>📅 Tienes <strong><?= $recordatoriosHoy ?></strong> recordatorio(s) para <strong>hoy</strong>.</span>
                    <a href="tracin_crud.php?filtro=recordatorios_hoy" class="btn btn-sm btn-outline-dark">Ver</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($recordatoriosPasados)): ?>
                <div class="alert alert-warning py-2 px-3 d-flex justify-content-between align-items-center shadow-sm"
                    style="font-size: 0.9rem;">
                    <span>⏰ Tienes <strong><?= $recordatoriosPasados ?></strong> recordatorio(s)
                        <strong>vencidos</strong>.</span>
                    <a href="tracin_crud.php?filtro=recordatorios_pasados" class="btn btn-sm btn-outline-dark">Ver</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>


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
    <div class="asistencia" id="asistencia">
        <?php include('asistencia.php'); ?>
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
                            <th>Canal</th>
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
                                <td><?= htmlspecialchars($product['channel']) ?></td> <!-- 🔹 Mostrar canal -->
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </section>
        </div>

        <div class="text-center mb-3">
            <br>
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#graphsContainer"
                aria-expanded="false" aria-controls="graphsContainer" id="toggleGraphsBtn">
                Mostrar Gráficos
            </button>
        </div>

        <div class="collapse" id="graphsContainer">
            <?php include('graphs.php'); ?>
        </div>

        <?php if ($role === 'admin'): ?>
            <div class="text-center mb-3">
                <br>
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#panelsigContainer"
                    aria-expanded="false" aria-controls="panelsig" id="toggleGraphsBtn">
                    Mostrar Panel
                </button>
            </div>

            <div class="collapse" id="panelsigContainer">
                <?php include('panel_sig.php'); ?>
            </div>
        <?php endif; ?>


        <!-- TABLA DE RECOMPENSAS ALCANZABLES -->


        <div class="container mt-5">
            <?php if ($role === 'admin'): ?>
                <!-- Dashboard completo para admin -->
                <?= $tabla_comisiones ?>
                <?= $tabla_ventas_normales ?>
                <?= $resumen_puntos ?>
                <?= $recompensas_disponibles ?>
                <?= $recompensas_grupales_html ?>
            <?php else: ?>

                <?= $recompensas_disponibles ?>
                <?php
                // Datos del usuario actual
                $puntos_usuario = $datos_para_mostrar['total'] ?? 0;
                $recompensas = [
                    5000 => "Recarga de S/5 o snack sorpresa",
                    10000 => "Recarga de S/10 o canjeo de menú",
                    15000 => "Suscripción de un mes (app)",
                    20000 => "Vale digital de S/20"
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
                <div class="card mt-4">
                    <div class="card-header ">
                        <h3 class="mb-0 ">Mi PROGRESO</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="chartMiProgreso"></canvas>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Tus recompensas</h5>

                                        <?php if ($recompensa_reclamada && $recompensa_data): ?>
                                            <div class="alert alert-success mt-3">
                                                <h5>✅ Recompensa Reclamada</h5>
                                                <p><strong>Tipo:</strong>
                                                    <?= htmlspecialchars($recompensa_data['descripcion']) ?></p>
                                                <p><strong>Puntos:</strong> <?= number_format($recompensa_data['puntos']) ?></p>
                                                <p><strong>Fecha:</strong>
                                                    <?= date('d/m/Y H:i', strtotime($recompensa_data['fecha'])) ?></p>
                                                <p class="small text-muted">Semana: <?= $current_week ?></p>
                                            </div>
                                        <?php elseif (!empty($recompensas_alcanzadas)): ?>
                                            <!-- Mostrar opciones para reclamar recompensas -->
                                            <div class="mb-3">
                                                <h6 class="text-success">🎁 Recompensas disponibles</h6>
                                                <ul class="list-group">
                                                    <?php foreach ($recompensas_alcanzadas as $puntos => $desc): ?>
                                                        <li class="list-group-item list-group-item-success">
                                                            <strong><?= number_format($puntos) ?> pts:</strong> <?= $desc ?>
                                                            <form method="POST" action="reclamar_recompensa.php" class="mt-2">
                                                                <input type="hidden" name="puntos" value="<?= $puntos ?>">
                                                                <input type="hidden" name="descripcion"
                                                                    value="<?= htmlspecialchars($desc) ?>">
                                                                <button type="submit" class="btn btn-sm btn-success">
                                                                    Reclamar esta recompensa
                                                                </button>
                                                            </form>
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
                    </div>
                </div>


                <?= $recompensas_equipo ?>


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
                                            <?php if ($equipo['puntos'] >= 31000): ?>
                                                <span class="badge bg-success">Premio: Reconocimiento</span>
                                            <?php elseif ($equipo['puntos'] >= 27000): ?>
                                                <span class="badge bg-primary">Premio: Tarde libre</span>
                                            <?php elseif ($equipo['puntos'] >= 21000): ?>
                                                <span class="badge bg-info">Premio: Bono</span>
                                            <?php elseif ($equipo['puntos'] >= 15000): ?>
                                                <span class="badge bg-light text-dark">Premio: Pizza</span>
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

        <!-- Botón para mostrar/ocultar gráficos -->

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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Manejar clic en botones de reclamar
        document.querySelectorAll('.btn-reclamar').forEach(button => {
            button.addEventListener('click', function () {
                const puntos = this.getAttribute('data-puntos');
                const descripcion = this.getAttribute('data-descripcion');
                const loader = document.getElementById('recompensaLoader');

                // Mostrar loader
                loader.classList.remove('d-none');
                this.disabled = true;

                // Enviar solicitud AJAX
                fetch('reclamar_recompensa.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `puntos=${encodeURIComponent(puntos)}&descripcion=${encodeURIComponent(descripcion)}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            if (data.reclamado) {
                                // Recargar la página para mostrar el estado actualizado
                                location.reload();
                            }
                        } else if (data.success) {
                            // Recargar la página para mostrar el mensaje de éxito
                            location.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al conectar con el servidor');
                    })
                    .finally(() => {
                        loader.classList.add('d-none');
                        this.disabled = false;
                    });
            });
        });
    });
</script>
<?php
if (isset($pdo)) {
    $pdo = null;
}
?>


<?php include('footer.php'); ?>