<?php
require 'db.php';
date_default_timezone_set('America/Lima');

$now = new DateTime();
$hoy = $now->format('Y-m-d');
$hora = $now->format('H:i:s');
$ayer = (clone $now)->modify('-1 day')->format('Y-m-d');

function obtenerVentas($pdo, $inicio, $fin)
{
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_ventas,
            SUM(quantity) AS total_cantidad,
            SUM(CASE WHEN currency = 'MXN' THEN quantity ELSE 0 END) AS cantidad_mxn,
            SUM(CASE WHEN currency = 'PEN' THEN quantity ELSE 0 END) AS cantidad_pen,
            SUM(CASE WHEN currency = 'MXN' THEN price * quantity ELSE 0 END) AS total_mxn,
            SUM(CASE WHEN currency = 'PEN' THEN price * quantity ELSE 0 END) AS total_pen
        FROM sales
        WHERE created_at BETWEEN :inicio AND :fin
    ");
    $stmt->execute([':inicio' => $inicio, ':fin' => $fin]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}


function getVal($arr, $key)
{
    return isset($arr[$key]) ? floatval($arr[$key]) : 0;
}

function calcPorcentaje($hoy, $ayer)
{
    if ($ayer <= 0)
        return $hoy > 0 ? 100 : 0;
    return round((($hoy - $ayer) / $ayer) * 100, 1);
}

function formatCambio($valor)
{
    $icono = $valor > 0 ? '▲' : ($valor < 0 ? '▼' : '■');
    $color = $valor > 0 ? 'text-success' : ($valor < 0 ? 'text-danger' : 'text-secondary');
    return "<span class=\"$color\">$icono " . abs($valor) . "%</span>";
}

$ventasHoy = obtenerVentas($pdo, "$hoy 00:00:00", "$hoy $hora");
$ventasAyerHora = obtenerVentas($pdo, "$ayer 00:00:00", "$ayer $hora");
$ventasAyerTotal = obtenerVentas($pdo, "$ayer 00:00:00", "$ayer 23:59:59");


$hoyMXN = getVal($ventasHoy, 'total_mxn');
$hoyPEN = getVal($ventasHoy, 'total_pen');
$ayerMXN = getVal($ventasAyerHora, 'total_mxn');
$ayerPEN = getVal($ventasAyerHora, 'total_pen');
$totalHoy = $hoyMXN + $hoyPEN;
$totalAyer = $ayerMXN + $ayerPEN;
$totalAyerFull = getVal($ventasAyerTotal, 'total_mxn') + getVal($ventasAyerTotal, 'total_pen');
$hoyCantMXN = getVal($ventasHoy, 'cantidad_mxn');
$hoyCantPEN = getVal($ventasHoy, 'cantidad_pen');
$ayerCantMXN = getVal($ventasAyerHora, 'cantidad_mxn');
$ayerCantPEN = getVal($ventasAyerHora, 'cantidad_pen');
$ayerFullCantMXN = getVal($ventasAyerTotal, 'cantidad_mxn');
$ayerFullCantPEN = getVal($ventasAyerTotal, 'cantidad_pen');
$pdo = null;
?>

<style>
    .text-success {
        background-color: #e6f4ea;
    }

    .text-danger {
        background-color: #fce8e6;
    }

    .text-secondary {
        background-color: #f2f2f2;
    }

    .table th,
    .table td {
        vertical-align: middle;
    }

    .table thead .table-secondary td {
        background-color: #f8f9fa !important;
        text-align: center;
    }
</style>

<div class="container mt-4">
    <h2 class="text-center mb-4">KPI: Ventas Comparativas por Hora</h2>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-primary shadow p-3">
                <h5 class="card-title text-center">
                    Comparativa de Ventas – <span class="text-primary">Hasta <?= substr($hora, 0, 5) ?>
                        (Hoy)</span><span title="Monto total registrado hasta el cierre del día anterior.">🛈</span>
                </h5>
                <table class="table table-bordered table-sm text-center">
                    <thead class="table-light">
                        <tr class="table-secondary fw-bold">
                            <td colspan="5">Cantidad de Productos Vendidos</td>
                        </tr>

                        <tr>
                            <th></th>
                            <th>Hoy</th>
                            <th>Ayer (misma hora)</th>
                            <th>Cambio</th>
                            <th>Ayer TOTAL (23:59)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Ventas</td>
                            <td><?= intval($ventasHoy['total_ventas']) ?></td>
                            <td><?= intval($ventasAyerHora['total_ventas']) ?></td>
                            <td><?= formatCambio(valor: calcPorcentaje(getVal($ventasHoy, 'total_ventas'), getVal($ventasAyerHora, 'total_ventas'))) ?>
                            </td>
                            <td><?= intval($ventasAyerTotal['total_ventas']) ?></td>

                        </tr>
                        <tr>
                            <td>Productos</td>
                            <td><?= intval($ventasHoy['total_cantidad']) ?></td>
                            <td><?= intval($ventasAyerHora['total_cantidad']) ?></td>
                            <td><?= formatCambio(calcPorcentaje(getVal($ventasHoy, 'total_cantidad'), getVal($ventasAyerHora, 'total_cantidad'))) ?>
                            </td>
                            <td><?= intval($ventasAyerTotal['total_cantidad']) ?></td>

                        </tr>
                        <tr>
                            <td>Cantidad MXN</td>
                            <td><?= intval($hoyCantMXN) ?></td>
                            <td><?= intval($ayerCantMXN) ?></td>
                            <td><?= formatCambio(calcPorcentaje($hoyCantMXN, $ayerCantMXN)) ?></td>
                            <td><?= intval($ayerFullCantMXN) ?></td>
                        </tr>
                        <tr>
                            <td>Cantidad PEN</td>
                            <td><?= intval($hoyCantPEN) ?></td>
                            <td><?= intval($ayerCantPEN) ?></td>
                            <td><?= formatCambio(calcPorcentaje($hoyCantPEN, $ayerCantPEN)) ?></td>
                            <td><?= intval($ayerFullCantPEN) ?></td>
                        </tr>

                        <tr>
                            <td>Total MXN</td>
                            <td><?= number_format($hoyMXN, 2) ?></td>
                            <td><?= number_format($ayerMXN, 2) ?></td>
                            <td><?= formatCambio(calcPorcentaje($hoyMXN, $ayerMXN)) ?></td>
                            <td><?= number_format(getVal($ventasAyerTotal, 'total_mxn'), 2) ?></td>
                        </tr>
                        <tr>
                            <td>Total PEN</td>
                            <td><?= number_format($hoyPEN, 2) ?></td>
                            <td><?= number_format($ayerPEN, 2) ?></td>
                            <td><?= formatCambio(calcPorcentaje($hoyPEN, $ayerPEN)) ?></td>
                            <td><?= number_format(getVal($ventasAyerTotal, 'total_pen'), 2) ?></td>
                        </tr>
                        <tr class="fw-bold bg-light">
                            <td>Total Suma</td>
                            <td class="<?= $totalHoy < $totalAyer ? 'text-danger' : 'text-success' ?>">
                                <?= number_format($totalHoy, 2) ?>
                            </td>
                            <td><?= number_format($totalAyer, 2) ?></td>
                            <td><?= formatCambio(calcPorcentaje($totalHoy, $totalAyer)) ?></td>
                            <td><?= number_format($totalAyerFull, 2) ?></td>
                        </tr>

                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>