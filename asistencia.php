<?php

if ((new DateTime('now', new DateTimeZone('America/Lima')))->format('N') == 1) {
    $semana_anterior = (new DateTime('last week'))->format("o-W");

    try {
        $pdo->beginTransaction();

        $verificar = $pdo->prepare("
            SELECT COUNT(*) FROM historial_puntos_historicos
            WHERE user_id = :user_id AND tipo = 'sin_errores_semana' AND semana_year = :semana
        ");
        $verificar->execute([
            ':user_id' => $user_id,
            ':semana' => $semana_anterior
        ]);

        if ($verificar->fetchColumn() == 0) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total_dias,
                       SUM(tipo_entrada = 'normal') AS dias_puntuales
                FROM asistencia
                WHERE user_id = :user_id
                  AND WEEK(fecha, 1) = WEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)
                  AND YEAR(fecha) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 WEEK))
                  AND DAYOFWEEK(fecha) BETWEEN 2 AND 7
            ");
            $stmt->execute([':user_id' => $user_id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($res['total_dias'] > 0 && $res['total_dias'] == $res['dias_puntuales']) {
                $insert = $pdo->prepare("
                    INSERT INTO historial_puntos_historicos (user_id, puntos, tipo, comentario, semana_year, origen)
                    VALUES (:user_id, 100, 'sin_errores_semana', 'Puntualidad completa en la semana', :semana, 'asistencia')
                ");
                $insert->execute([
                    ':user_id' => $user_id,
                    ':semana' => $semana_anterior
                ]);
                $puntos_asignados_esta_visita = true;
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al asignar puntos semanales: " . $e->getMessage());
    }
}

$fechaHoy = (new DateTime('now', new DateTimeZone('America/Lima')))->format('Y-m-d');

$stmt = $pdo->prepare("SELECT * FROM asistencia WHERE user_id = :user_id AND fecha = :fecha ORDER BY id DESC LIMIT 1");

$stmt->execute([':user_id' => $user_id, ':fecha' => $fechaHoy]);
$asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

$horarios_entrada = ['08:00:00', '14:00:00', '17:00:00', '20:00:00'];
$horarios_salida = ['14:00:00', '17:00:00', '20:00:00', '23:00:00'];
define('MARGEN_TARDANZA_MINUTOS', 2);
define('UMBRAL_EXTRA_MINUTOS', 1);

$ahora = new DateTime('now', new DateTimeZone('America/Lima'));
$horaActual = $ahora->format('H:i:s');



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['entrada']) && (!$asistencia || $asistencia['hora_salida'] !== null)) {



        // Encontrar el horario más cercano hacia atrás
            // Buscar el horario más próximo con tolerancia de 15 min antes
// Buscar el horario más próximo con tolerancia de 15 min antes
$horaEsperada = null;

foreach ($horarios_entrada as $i => $hora) {
    $esperada = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $hora, new DateTimeZone('America/Lima'));

    if ($hora === '08:00:00') {
        $rangoInicio = (clone $esperada)->modify('-60 minutes'); // 07:00
        $rangoFin = (clone $esperada)->modify('+300 minutes');   // 13:00

        if ($ahora >= $rangoInicio && $ahora <= $rangoFin) {
            $horaEsperada = $esperada;
            break;
        }
    } if ($hora === '20:00:00') {
    $rangoInicio = (clone $esperada)->modify('-15 minutes'); // 19:45
    $rangoFin = (clone $esperada)->modify('+180 minutes');   // 23:59
}

    else {
        $rangoInicio = (clone $esperada)->modify('-15 minutes');
        $rangoFin = (clone $esperada)->modify('+165 minutes');

        if ($ahora >= $rangoInicio && $ahora <= $rangoFin) {
            $horaEsperada = $esperada;
            break;
        }
    }
}




        $tipo = 'normal';
        $minutos = 0;

        if ($horaEsperada) {
            $minutos = round(($ahora->getTimestamp() - $horaEsperada->getTimestamp()) / 60);


            if ($minutos > MARGEN_TARDANZA_MINUTOS) {
                $tipo = 'tardanza';
            }
        } else {
            // No hay horario anterior, asumimos muy tarde
            $tipo = 'tardanza';
            $minutos = 999;
        }


        $stmt = $pdo->prepare("INSERT INTO asistencia (user_id, fecha, hora_entrada, tipo_entrada, tipo_salida) VALUES (:user_id, :fecha, :hora, :tipo_entrada, :tipo_salida)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':fecha' => $fechaHoy,
            ':hora' => $horaActual,
            ':tipo_entrada' => $tipo,
            ':tipo_salida' => 'pendiente'
        ]);

        $asistencia_id = $pdo->lastInsertId();

        // Calcular y registrar sanción si hubo tardanza
        if ($tipo === 'tardanza') {
            $minutosTarde = $minutos;

            $dobles = min($minutosTarde, 20) * 2;
            $triples = max($minutosTarde - 20, 0) * 3;
            $minCastigo = $dobles + $triples;
            $descuento = floor($minCastigo / 60) * 50;

            $stmt = $pdo->prepare("INSERT INTO sanciones (user_id, asistencia_id, fecha, minutos_tardanza, minutos_castigo, descuento_soles) 
            VALUES (:user_id, :asistencia_id, :fecha, :min_tardanza, :min_castigo, :descuento)");
            $stmt->execute([
                ':user_id' => $user_id,
                ':asistencia_id' => $asistencia_id,
                ':fecha' => $fechaHoy,
                ':min_tardanza' => $minutosTarde,
                ':min_castigo' => $minCastigo,
                ':descuento' => $descuento
            ]);
        }
    }  elseif (isset($_POST['salida']) && $asistencia && $asistencia['hora_salida'] === null) {


        $horaEntrada = new DateTime($fechaHoy . ' ' . $asistencia['hora_entrada'], new DateTimeZone('America/Lima'));

        $horaSalida = new DateTime($fechaHoy . ' ' . $horaActual, new DateTimeZone('America/Lima'));

        // Buscar hora esperada de salida según la hora de entrada
        $horaEsperadaSalida = null;

$horaEsperadaSalida = null;
$minDiferencia = PHP_INT_MAX;

foreach ($horarios_salida as $horaSalidaDefinida) {
    $definida = new DateTime($fechaHoy . ' ' . $horaSalidaDefinida, new DateTimeZone('America/Lima'));
    $diferencia = abs($horaSalida->getTimestamp() - $definida->getTimestamp());

    if ($diferencia < $minDiferencia) {
        $minDiferencia = $diferencia;
        $horaEsperadaSalida = $definida;
    }
}
    


        if (!$horaEsperadaSalida) {
            $horaEsperadaSalida = new DateTime($fechaHoy . ' 23:00:00', new DateTimeZone('America/Lima'));
        }


        // Definir tipo de salida
        $tipoFinal = ($horaSalida < $horaEsperadaSalida) ? 'salida_adelantada' : 'normal';

        // Actualizar salida y tipo
        $stmt = $pdo->prepare("UPDATE asistencia SET hora_salida = :hora_salida, tipo_salida = :tipo_salida WHERE id = :id");
        $stmt->execute([
            ':hora_salida' => $horaActual,
            ':tipo_salida' => $tipoFinal,
            ':id' => $asistencia['id']
        ]);

        // Calcular minutos extra para recuperación si aplica
        $minutosExtra = ($horaSalida > $horaEsperadaSalida)
            ? round(($horaSalida->getTimestamp() - $horaEsperadaSalida->getTimestamp()) / 60)
            : 0;

        
if ($minutosExtra < 1) {
    $pendientes = 0;
}
        if ($minutosExtra >= UMBRAL_EXTRA_MINUTOS) {
            // Buscar minutos de castigo pendientes
            $stmt = $pdo->prepare("SELECT SUM(minutos_castigo) - SUM(COALESCE(minutos_recuperados, 0)) FROM sanciones WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $pendientes = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM recuperaciones WHERE asistencia_id = :id");
$stmt->execute([':id' => $asistencia['id']]);
$yaRecuperado = $stmt->fetchColumn();

            if ($pendientes > 0) {
                $minutosAplicables = min($pendientes, $minutosExtra);

                // Aplicar recuperación a sanción
                $stmt = $pdo->prepare("
            UPDATE sanciones 
            SET minutos_recuperados = LEAST(minutos_castigo, COALESCE(minutos_recuperados, 0) + :rec) 
            WHERE user_id = :user_id AND (minutos_recuperados IS NULL OR minutos_recuperados < minutos_castigo)
            ORDER BY id ASC LIMIT 1
        ");
                $stmt->execute([
                    ':rec' => $minutosAplicables,
                    ':user_id' => $user_id
                ]);

                // Registrar recuperación en la tabla correspondiente
                $stmt = $pdo->prepare("INSERT INTO recuperaciones (user_id, asistencia_id, fecha, minutos_extra, aplicado_a_sancion, puntos_generados)
            VALUES (:user_id, :asistencia_id, :fecha, :minutos, 1, 0)");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':asistencia_id' => $asistencia['id'],
                    ':fecha' => $fechaHoy,
                    ':minutos' => $minutosAplicables

                ]);
            }
            

            // Si no hay pendientes, no se registra nada
        }



    }





    echo "<script>window.location.href = 'index.php';</script>";
    exit;

}

// Obtener valores actualizados si ya existía
if (!$asistencia) {
    $horaEntrada = null;
    $horaSalida = null;
} else {
    $horaEntrada = $asistencia['hora_entrada'];
    $horaSalida = $asistencia['hora_salida'];
}
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">🕒 Asistencia del día</h5>
        <button class="btn btn-sm btn-primary" onclick="window.location.href='asistencias_panel.php';">
            Ver historial de registros
        </button>
    </div>

    <div class="card-body">
        <div class="mb-3 text-center">
            <h4 class="text-dark mb-0">
                📅 <span id="fechaActual">Cargando fecha...</span>
            </h4>
            <h1 class="text-primary fw-bold" id="horaActual" style="font-size: 3rem;">--:--:--</h1>
        </div>

        <form method="POST">
            <div class="d-flex gap-3 align-items-center">
                <div>
                    <strong>Entrada:</strong>
                    <?= $horaEntrada ? $horaEntrada : '<span class="text-muted">No registrada</span>' ?>
                </div>
                <div>
                    <strong>Salida:</strong>
                    <?= $horaSalida ? $horaSalida : '<span class="text-muted">No registrada</span>' ?>
                </div>

                <?php if (!$horaEntrada): ?>
                    <button type="submit" name="entrada" class="btn btn-success">Marcar Entrada</button>
                <?php elseif (!$horaSalida): ?>
                    <button type="submit" name="salida" class="btn btn-danger">Marcar Salida</button>
                <?php else: ?>
                    <button type="submit" name="entrada" class="btn btn-info">Marcar nueva asistencia</button>

                <?php endif; ?>
            </div>
        </form>
    </div>
</div>


<script>
    function actualizarHora() {
        const ahora = new Date();
        const horas = ahora.getHours().toString().padStart(2, '0');
        const minutos = ahora.getMinutes().toString().padStart(2, '0');
        const segundos = ahora.getSeconds().toString().padStart(2, '0');
        document.getElementById('horaActual').textContent = `${horas}:${minutos}:${segundos}`;
    }

    setInterval(actualizarHora, 1000);
    actualizarHora();
</script>
<script>
    function actualizarReloj() {
        const ahora = new Date();

        // Formato de hora
        const horas = ahora.getHours().toString().padStart(2, '0');
        const minutos = ahora.getMinutes().toString().padStart(2, '0');
        const segundos = ahora.getSeconds().toString().padStart(2, '0');
        document.getElementById('horaActual').textContent = `${horas}:${minutos}:${segundos}`;

        // Formato de fecha y día
        const dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        const diaSemana = dias[ahora.getDay()];
        const dia = ahora.getDate();
        const mes = meses[ahora.getMonth()];
        const anio = ahora.getFullYear();

        const fechaFormateada = `${diaSemana.charAt(0).toUpperCase() + diaSemana.slice(1)}, ${dia} de ${mes} de ${anio}`;
        document.getElementById('fechaActual').textContent = fechaFormateada;
    }

    setInterval(actualizarReloj, 1000);
    actualizarReloj();
</script>

<?php
// Liberar recursos y cerrar conexión (buenas prácticas)
$stmt = null;
$verificar = null;
$insert = null;
$pdo = null;
?>