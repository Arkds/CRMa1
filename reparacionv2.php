<?php
require 'datos_index.php';

$horarios_entrada = ['08:00:00', '14:00:00', '17:00:00', '20:00:00'];
$horarios_salida = ['14:00:00', '17:00:00', '20:00:00', '23:00:00'];
define('MARGEN_TARDANZA_MINUTOS', 2);
define('UMBRAL_EXTRA_MINUTOS', 1);

try {
    $pdo->beginTransaction();

    $fechasStmt = $pdo->query("SELECT DISTINCT fecha FROM asistencia WHERE hora_entrada IS NOT NULL ORDER BY fecha ASC");
    $fechas = $fechasStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($fechas as $fecha) {
        $pdo->prepare("DELETE FROM sanciones WHERE fecha = ?")->execute([$fecha]);
        $pdo->prepare("DELETE FROM recuperaciones WHERE fecha = ?")->execute([$fecha]);

        $stmt = $pdo->prepare("SELECT * FROM asistencia WHERE hora_entrada IS NOT NULL AND fecha = ?");
        $stmt->execute([$fecha]);
        $asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($asistencias as $a) {
            $user_id = $a['user_id'];
            $id = $a['id'];

            $horaEntrada = new DateTime($fecha . ' ' . $a['hora_entrada'], new DateTimeZone('America/Lima'));
            $horaSalida = $a['hora_salida'] ? new DateTime($fecha . ' ' . $a['hora_salida'], new DateTimeZone('America/Lima')) : null;

            // Procesar tipo_entrada
            $horaEsperada = null;
            $index = null;

            foreach ($horarios_entrada as $i => $h) {
                $esperada = new DateTime($fecha . ' ' . $h, new DateTimeZone('America/Lima'));
                $inicioTolerancia = (clone $esperada)->modify('-15 minutes');
                $finTolerancia = (clone $esperada)->modify('+2 hours');

                if ($horaEntrada >= $inicioTolerancia && $horaEntrada <= $finTolerancia) {
                    $horaEsperada = $esperada;
                    $index = $i;
                    break;
                }
            }

            if ($horaEsperada !== null && isset($horarios_salida[$index])) {
                $minutosTarde = $horaEntrada > $horaEsperada
                    ? round(($horaEntrada->getTimestamp() - $horaEsperada->getTimestamp()) / 60)
                    : 0;

                $tipoEntrada = $minutosTarde > MARGEN_TARDANZA_MINUTOS ? 'tardanza' : 'normal';
            } else {
                $tipoEntrada = 'normal';
                $minutosTarde = 0;
            }

            $pdo->prepare("UPDATE asistencia SET tipo_entrada = :tipo WHERE id = :id")
                ->execute([':tipo' => $tipoEntrada, ':id' => $id]);

            if ($tipoEntrada === 'tardanza') {
                $dobles = min($minutosTarde, 20) * 2;
                $triples = max($minutosTarde - 20, 0) * 3;
                $minCastigo = $dobles + $triples;
                $descuento = floor($minCastigo / 60) * 50;

                $pdo->prepare("INSERT INTO sanciones (user_id, asistencia_id, fecha, minutos_tardanza, minutos_castigo, descuento_soles)
                    VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$user_id, $id, $fecha, $minutosTarde, $minCastigo, $descuento]);
            }

            // Procesar salida
            if ($horaSalida) {
                // Buscar hora de salida más cercana (pasado o futuro)
                $horaEsperadaSalida = null;
                $minDiferencia = PHP_INT_MAX;

                foreach ($horarios_salida as $h) {
                    $salidaDef = new DateTime($fecha . ' ' . $h, new DateTimeZone('America/Lima'));
                    $diferencia = abs($horaSalida->getTimestamp() - $salidaDef->getTimestamp());

                    if ($diferencia < $minDiferencia) {
                        $minDiferencia = $diferencia;
                        $horaEsperadaSalida = $salidaDef;
                    }
                }

                $tipoSalida = $horaSalida < $horaEsperadaSalida ? 'salida_adelantada' : 'normal';
                $pdo->prepare("UPDATE asistencia SET tipo_salida = :tipo WHERE id = :id")
                    ->execute([':tipo' => $tipoSalida, ':id' => $id]);

                $minutosExtra = $horaSalida > $horaEsperadaSalida
                    ? round(($horaSalida->getTimestamp() - $horaEsperadaSalida->getTimestamp()) / 60)
                    : 0;

                echo "🕒 Entrada: {$a['hora_entrada']} | Esperada salida: {$horaEsperadaSalida->format('H:i:s')} | Salida real: {$a['hora_salida']} | Min. extra calculados: {$minutosExtra}<br>";
                echo "🧾 Asistencia ID {$id} | Usuario ID {$user_id} | Fecha: {$fecha}<br>";

                if ($minutosExtra >= UMBRAL_EXTRA_MINUTOS) {
                    $stmtPendientes = $pdo->prepare("SELECT SUM(minutos_castigo) - SUM(COALESCE(minutos_recuperados, 0)) FROM sanciones WHERE user_id = ? AND fecha <= ?");
                    $stmtPendientes->execute([$user_id, $fecha]);
                    $pendientes = (int) $stmtPendientes->fetchColumn();

                    if ($pendientes > 0) {
                        $minAplicables = min($pendientes, $minutosExtra);

                        $pdo->prepare("UPDATE sanciones 
                            SET minutos_recuperados = LEAST(minutos_castigo, COALESCE(minutos_recuperados, 0) + :rec)
                            WHERE user_id = :user_id AND (minutos_recuperados IS NULL OR minutos_recuperados < minutos_castigo)
                            AND fecha <= :fecha
                            ORDER BY id ASC
                            LIMIT 1")
                            ->execute([':rec' => $minAplicables, ':user_id' => $user_id, ':fecha' => $fecha]);

                        $pdo->prepare("INSERT INTO recuperaciones (user_id, asistencia_id, fecha, minutos_extra, aplicado_a_sancion, puntos_generados)
                            VALUES (?, ?, ?, ?, 1, 0)")
                            ->execute([$user_id, $id, $fecha, $minAplicables]);
                    }
                }
            }
        }

        echo "🟢 Día procesado: $fecha<br>";
    }

    $pdo->commit();
    echo "<br>✅ Reparación completa por día.";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage();
}
