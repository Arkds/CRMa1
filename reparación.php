<?php
require 'datos_index.php'; 

$horarios_entrada = ['08:00:00', '14:00:00', '17:00:00', '20:00:00'];
$horarios_salida = ['14:00:00', '17:00:00', '20:00:00', '23:00:00'];
define('MARGEN_TARDANZA_MINUTOS', 2);
define('UMBRAL_EXTRA_MINUTOS', 1);

try {
    $pdo->beginTransaction();

    $pdo->exec("DELETE FROM sanciones");
    $pdo->exec("DELETE FROM recuperaciones");

    $stmt = $pdo->query("SELECT * FROM asistencia WHERE hora_entrada IS NOT NULL");
    $asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($asistencias as $a) {
        $user_id = $a['user_id'];
        $fecha = $a['fecha'];
        $id = $a['id'];

        $horaEntrada = new DateTime($fecha . ' ' . $a['hora_entrada'], new DateTimeZone('America/Lima'));
        $horaSalida = $a['hora_salida'] ? new DateTime($fecha . ' ' . $a['hora_salida'], new DateTimeZone('America/Lima')) : null;

        $horaEsperada = null;
        foreach ($horarios_entrada as $i => $hora) {
            $esperada = new DateTime($fecha . ' ' . $hora, new DateTimeZone('America/Lima'));
            $inicioTolerancia = (clone $esperada)->modify('-15 minutes');
            $finTolerancia = (clone $esperada)->modify('+165 minutes');

            if ($horaEntrada >= $inicioTolerancia && $horaEntrada <= $finTolerancia) {
                $horaEsperada = $esperada;
                break;
            }
        }

        if (!$horaEsperada) {
            $tipoEntrada = 'tardanza';
            $minutosTarde = 999;
        } else {
            if ($horaEntrada <= (clone $horaEsperada)->modify('+' . MARGEN_TARDANZA_MINUTOS . ' minutes')) {

                $tipoEntrada = 'normal';
                $minutosTarde = 0;
            } else {
                $tipoEntrada = 'tardanza';
                $diff = $horaEntrada->getTimestamp() - $horaEsperada->getTimestamp();
                $minutosTarde = $diff > 0 ? round($diff / 60) : 0;

            }
        }

        $stmtUpdateEntrada = $pdo->prepare("UPDATE asistencia SET tipo_entrada = :tipo WHERE id = :id");
        $stmtUpdateEntrada->execute([
            ':tipo' => $tipoEntrada,
            ':id' => $id
        ]);

        if ($tipoEntrada === 'tardanza') {
            $dobles = min($minutosTarde, 20) * 2;
            $triples = max($minutosTarde - 20, 0) * 3;
            $minCastigo = $dobles + $triples;
            $descuento = floor($minCastigo / 60) * 50;

            $stmtInsertSan = $pdo->prepare("INSERT INTO sanciones (user_id, asistencia_id, fecha, minutos_tardanza, minutos_castigo, descuento_soles) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsertSan->execute([$user_id, $id, $fecha, $minutosTarde, $minCastigo, $descuento]);
        }

        // Validar salida
        if ($horaSalida) {
            // Determinar salida esperada según hora de entrada
            $horaEsperadaSalida = null;

            foreach ($horarios_entrada as $i => $inicioHora) {
                $inicio = new DateTime($fecha . ' ' . $inicioHora, new DateTimeZone('America/Lima'));
                $fin = isset($horarios_entrada[$i + 1])
                    ? new DateTime($fecha . ' ' . $horarios_entrada[$i + 1], new DateTimeZone('America/Lima'))
                    : new DateTime($fecha . ' 23:59:59', new DateTimeZone('America/Lima'));

                if ($horaEntrada >= $inicio && $horaEntrada < $fin) {
                    $horaEsperadaSalida = new DateTime($fecha . ' ' . $horarios_salida[$i], new DateTimeZone('America/Lima'));
                    break;
                }
            }

            if (!$horaEsperadaSalida) {
                $horaEsperadaSalida = new DateTime($fecha . ' 23:00:00', new DateTimeZone('America/Lima'));
            }

            // Tipo de salida
            $tipoSalida = $horaSalida < $horaEsperadaSalida ? 'salida_adelantada' : 'normal';

            $stmtUpdateSalida = $pdo->prepare("UPDATE asistencia SET tipo_salida = :tipo WHERE id = :id");
            $stmtUpdateSalida->execute([
                ':tipo' => $tipoSalida,
                ':id' => $id
            ]);

            // Calcular minutos extra
            $minutosExtra = $horaSalida > $horaEsperadaSalida
                ? round(($horaSalida->getTimestamp() - $horaEsperadaSalida->getTimestamp()) / 60)
                : 0;

            if ($minutosExtra >= UMBRAL_EXTRA_MINUTOS && $horaSalida > $horaEsperadaSalida) {
                // Buscar minutos de castigo pendientes
                $stmtPendientes = $pdo->prepare("SELECT SUM(minutos_castigo) - SUM(COALESCE(minutos_recuperados, 0)) FROM sanciones WHERE user_id = ?");
                $stmtPendientes->execute([$user_id]);
                $pendientes = (int) $stmtPendientes->fetchColumn();

                if ($pendientes > 0) {
                    $minAplicables = min($pendientes, $minutosExtra);

                    // Aplicar minutos a la primera sanción disponible
                    $stmtUpdateSancion = $pdo->prepare("
                        UPDATE sanciones 
                        SET minutos_recuperados = LEAST(minutos_castigo, COALESCE(minutos_recuperados, 0) + :rec)
                        WHERE user_id = :user_id AND (minutos_recuperados IS NULL OR minutos_recuperados < minutos_castigo)
                        ORDER BY id ASC
                        LIMIT 1
                    ");
                    $stmtUpdateSancion->execute([
                        ':rec' => $minAplicables,
                        ':user_id' => $user_id
                    ]);

                    // Registrar recuperación
                    $stmtInsertRec = $pdo->prepare("INSERT INTO recuperaciones (user_id, asistencia_id, fecha, minutos_extra, aplicado_a_sancion, puntos_generados) VALUES (?, ?, ?, ?, 1, 0)");
                    $stmtInsertRec->execute([$user_id, $id, $fecha, $minAplicables]);
                }
            }
        }
    }

    $pdo->commit();
    echo "✅ Reparación completa de asistencias, sanciones y recuperaciones.";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage();
}
