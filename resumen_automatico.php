<?php
echo '<style>
    .resumen-card {
    height: 500px; /* altura uniforme */
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
}


    .resumen-card .card {
        height: 800px; /* Altura fija */
        overflow-y: auto;
        border: 1px solid #e3e6f0;
        border-radius: 0.75rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        transition: box-shadow 0.3s ease;
        padding-right: 0.5rem; /* Espacio para scroll */
    }

    .resumen-card .card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .resumen-card h4,
    .resumen-card .resumen-header {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: #0d6efd;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .resumen-card h5 {
        font-size: 1.05rem;
        font-weight: 500;
        margin-top: 1rem;
        color: #0d6efd;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.3rem;
    }

    .resumen-card ul {
    max-height: 120px;
    overflow-y: auto;
    padding-left: 1.2rem;
    margin-bottom: 1rem;
}


    .resumen-card li {
        font-size: 0.95rem;
        margin-bottom: 0.3rem;
        color: #212529;
    }

    .resumen-card p {
        font-size: 0.9rem;
        font-style: italic;
        color: #6c757d;
        margin-bottom: 0.7rem;
    }

    .resumen-card .form-control {
        font-size: 0.95rem;
    }

    .resumen-card .btn {
        font-size: 0.95rem;
    }

    iframe {
        background: #f8f9fa;
        border-radius: 0.5rem;
        padding: 0.5rem;
        height: 360px;
    }
    .resumen-iframe {
    height: 200px;
    overflow-y: auto;
    background: #f8f9fa;
    border-radius: 0.5rem;
    padding: 0.5rem;
    border: none;
}

</style>';



require 'db.php';
date_default_timezone_set('America/Lima');

function obtenerResumenPorFecha($fecha, $pdo) {
    $stmt = $pdo->prepare("
        SELECT re.category, re.content 
        FROM report_entries re
        JOIN reports r ON re.report_id = r.id
        WHERE DATE(r.date) = :fecha
    ");
    $stmt->execute([':fecha' => $fecha]);

    $entradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resumen = [
        'problemas' => [],
        'cursos_mas_vendidos' => [],
        'dudas_frecuentes' => [],
        'recomendaciones' => [],
    ];

    foreach ($entradas as $entrada) {
        $cat = strtolower(trim($entrada['category']));
        if (isset($resumen[$cat])) {
            $resumen[$cat][] = $entrada['content'];
        }
    }

    return $resumen;
}

function resumen_simple($array, $titulo) {
    if (empty($array)) return "<p><em>No se registraron $titulo.</em></p>";
    $texto = "<h5>$titulo</h5><ul>";
    foreach (array_slice($array, 0, 3) as $item) {
        $texto .= "<li>" . htmlspecialchars($item) . "</li>";
    }
    $texto .= "</ul>";
    return $texto;
}

// ðŸš¨ Solo muestra resumen si se accede con GET desde iframe
if (isset($_GET['fecha'])) {
    $fecha = $_GET['fecha'];
    $resumen = obtenerResumenPorFecha($fecha, $pdo);

   echo "<div class='resumen-card'>";
echo "<div class='resumen-header'><i class='bi bi-journal-text'></i> Resumen del " . htmlspecialchars($fecha) . "</div>";
echo resumen_simple($resumen['problemas'], 'Problemas');
echo resumen_simple($resumen['cursos_mas_vendidos'], 'Cursos mÃ¡s vendidos');
echo resumen_simple($resumen['dudas_frecuentes'], 'Dudas frecuentes');
echo resumen_simple($resumen['recomendaciones'], 'Recomendaciones');
echo "</div>";

    exit;
}

// ðŸš« Si no se estÃ¡ llamando con fecha, render normal (resumen de hoy y ayer)
$fechaHoy = date('Y-m-d');
$fechaAyer = date('Y-m-d', strtotime('-1 day'));
$resumenHoy = obtenerResumenPorFecha($fechaHoy, $pdo);
$resumenAyer = obtenerResumenPorFecha($fechaAyer, $pdo);
?>

<div class="container mt-4">
  <div class="row">
    <!-- Ayer -->
    <div class="col-md-4">
      <div class="card p-3 mb-3 shadow-sm resumen-card">

        <h4>ðŸ•˜ Resumen Ayer (<?= $fechaAyer ?>)</h4>
        <?= resumen_simple($resumenAyer['problemas'], 'Problemas') ?>
        <?= resumen_simple($resumenAyer['cursos_mas_vendidos'], 'Cursos mÃ¡s vendidos') ?>
        <?= resumen_simple($resumenAyer['dudas_frecuentes'], 'Dudas frecuentes') ?>
        <?= resumen_simple($resumenAyer['recomendaciones'], 'Recomendaciones') ?>
      </div>
    </div>

    <!-- Hoy -->
    <div class="col-md-4">
      <div class="card p-3 mb-3 shadow-sm resumen-card">

        <h4>ðŸ•˜ Resumen Hoy (<?= $fechaHoy ?>)</h4>
        <?= resumen_simple($resumenHoy['problemas'], 'Problemas') ?>
        <?= resumen_simple($resumenHoy['cursos_mas_vendidos'], 'Cursos mÃ¡s vendidos') ?>
        <?= resumen_simple($resumenHoy['dudas_frecuentes'], 'Dudas frecuentes') ?>
        <?= resumen_simple($resumenHoy['recomendaciones'], 'Recomendaciones') ?>
      </div>
    </div>

    <!-- Formulario + iframe -->
    <div class="col-md-4">
      <div class="card p-3 mb-3 shadow-sm resumen-card">

        <h4>ðŸ“… Ver otra fecha</h4>
        <form onsubmit="cargarIframe(event)">
          <label for="fecha">Selecciona la fecha:</label>
          <input type="date" id="fecha" name="fecha" class="form-control mb-2" required>
          <button class="btn btn-primary w-100" type="submit">Ver resumen</button>
        </form>

        <iframe id="iframeResumen" src="" style="width: 100%; height: 400px; border: none;" class="mt-3"></iframe>
      </div>
    </div>
  </div>
</div>

<script>
function cargarIframe(e) {
  e.preventDefault();
  const fecha = document.getElementById('fecha').value;
  if (fecha) {
    const iframe = document.getElementById('iframeResumen');
    iframe.src = `resumen_automatico.php?fecha=${encodeURIComponent(fecha)}`;
  }
}
</script>
