<?php
$fechaHoy = (new DateTime('now', new DateTimeZone('America/Lima')))->format('Y-m-d');

$stmt = $pdo->prepare("SELECT * FROM asistencia WHERE user_id = :user_id AND fecha = :fecha");
$stmt->execute([':user_id' => $user_id, ':fecha' => $fechaHoy]);
$asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

$horarios_entrada = ['08:00:00', '14:00:00', '17:00:00', '20:00:00'];
$horarios_salida = ['14:00:00', '17:00:00', '20:00:00', '23:00:00'];
define('MARGEN_TARDANZA_MINUTOS', 1);
define('UMBRAL_EXTRA_MINUTOS', 1);

$ahora = new DateTime('now', new DateTimeZone('America/Lima'));
$horaActual = $ahora->format('H:i:s');

function detectarHorarioEntrada($horaActual, $horarios)
{
    $actual = new DateTime($horaActual);
    $mejorOpcion = null;
    $menorDiferencia = PHP_INT_MAX;

    foreach ($horarios as $hora) {
        $esperada = new DateTime($hora);
        $diff = $esperada->diff($actual);
        $minutos = ($diff->h * 60) + $diff->i;

        if ($actual >= $esperada && $minutos <= MARGEN_TARDANZA_MINUTOS) {
            if ($minutos < $menorDiferencia) {
                $mejorOpcion = $esperada;
                $menorDiferencia = $minutos;
            }
        }
    }

    return $mejorOpcion;
}
function detectarHorarioSalida($horaActual, $horarios)
{
    $actual = new DateTime($horaActual);
    foreach ($horarios as $hora) {
        $esperada = new DateTime($hora);
        if ($actual >= $esperada) {
            return $esperada;
        }
    }
    return null;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['entrada']) && !$asistencia) {
        // Encontrar el horario más cercano hacia atrás
        $horaEsperada = null;
        foreach (array_reverse($horarios_entrada) as $hora) {
            $esperada = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $hora, new DateTimeZone('America/Lima'));
            if ($ahora >= $esperada) {
                $horaEsperada = $esperada;
                break;
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
    } elseif (isset($_POST['salida']) && $asistencia && $asistencia['hora_salida'] === null) {
        $horaEntrada = new DateTime($asistencia['hora_entrada']);
        $horaSalida = new DateTime($fechaHoy . ' ' . $horaActual, new DateTimeZone('America/Lima'));

        // Buscar hora esperada de salida según la hora de entrada
        $horaEsperadaSalida = null;
        for ($i = count($horarios_entrada) - 1; $i >= 0; $i--) {
            $entradaHora = new DateTime($fechaHoy . ' ' . $horarios_entrada[$i], new DateTimeZone('America/Lima'));
            if ($horaEntrada >= $entradaHora) {
                $horaEsperadaSalida = new DateTime($fechaHoy . ' ' . $horarios_salida[$i], new DateTimeZone('America/Lima'));
                break;
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
        // Calcular minutos extra para recuperación si aplica
        $minutosExtra = ($horaSalida > $horaEsperadaSalida)
            ? round(($horaSalida->getTimestamp() - $horaEsperadaSalida->getTimestamp()) / 60)
            : 0;

        if ($minutosExtra >= UMBRAL_EXTRA_MINUTOS) {
            // Buscar minutos de castigo pendientes
            $stmt = $pdo->prepare("SELECT SUM(minutos_castigo) - SUM(COALESCE(minutos_recuperados, 0)) FROM sanciones WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $pendientes = (int) $stmt->fetchColumn();

            $aplicado_a_sancion = 0;
            $puntos = 0;
            $minutosAplicables = $minutosExtra;

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
                $aplicado_a_sancion = 1;
            } else {
                $puntos = floor($minutosExtra / 10);
                if ($puntos > 0) {
                    $stmt = $pdo->prepare("INSERT INTO historial_puntos_historicos (user_id, puntos, tipo, origen, created_at)
                VALUES (:user_id, :puntos, 'extra', 'recuperacion', NOW())");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':puntos' => $puntos
                    ]);
                }
            }

            // Registrar recuperación en la tabla correspondiente
            $stmt = $pdo->prepare("INSERT INTO recuperaciones (user_id, asistencia_id, fecha, minutos_extra, aplicado_a_sancion, puntos_generados)
        VALUES (:user_id, :asistencia_id, :fecha, :minutos, :aplicado, :puntos)");
            $stmt->execute([
                ':user_id' => $user_id,
                ':asistencia_id' => $asistencia['id'],
                ':fecha' => $fechaHoy,
                ':minutos' => $minutosAplicables,
                ':aplicado' => $aplicado_a_sancion,
                ':puntos' => $puntos
            ]);
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
                    <span class="badge bg-secondary">Asistencia completada</span>
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