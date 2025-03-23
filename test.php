<?php
require 'db.php';

// Verificar si la cookie de sesión existe
if (isset($_COOKIE['user_session'])) {
    $user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
    if ($user_data) {
        $user_id = $user_data['user_id'];
        $username = $user_data['username'];
        $role = $user_data['role'];
    } else {
        header("Location: login.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}

// Consultar ventas con usuarios
$query = "
    SELECT u.username, s.product_name, s.total
    FROM sales s
    JOIN users u ON s.user_id = u.id
";
$salesData = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Construcción de datos para el gráfico Sunburst
$labels = ["Sistema"];
$parents = [""];
$values = [0]; // Total de todas las ventas

$seller_sales = [];
$product_sales = [];

// Procesar datos asegurando que los valores sean correctos
foreach ($salesData as $row) {
    $seller = $row['username'];
    $product = $row['product_name'];
    $total_sale = floatval($row['total']); // Asegurar formato numérico

    // Agregar vendedor si no existe
    if (!isset($seller_sales[$seller])) {
        $labels[] = $seller;
        $parents[] = "Sistema";
        $seller_sales[$seller] = 0;
    }

    // Agregar producto bajo el vendedor si no existe
    if (!isset($product_sales[$product])) {
        $labels[] = $product;
        $parents[] = $seller;
        $product_sales[$product] = 0;
    }

    // Sumar correctamente el total de ventas del vendedor y del producto
    $seller_sales[$seller] += $total_sale;
    $product_sales[$product] += $total_sale;
}

// Asegurar que el total del nodo raíz sea la suma de todos los vendedores
$values[0] = array_sum($seller_sales);

// Asignar valores de ventas por vendedor (exactamente la suma de sus productos)
foreach ($seller_sales as $seller => $total) {
    $values[] = $total;
}

// Asignar valores de ventas por producto (ahora dentro del total del vendedor)
foreach ($product_sales as $product => $total) {
    $values[] = $total;
}

// Convertir a JSON para JavaScript
$jsonData = json_encode(["labels" => $labels, "parents" => $parents, "values" => $values]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribución de Ventas - Sunburst Chart</title>
    <script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Distribución de Ventas por Vendedor y Producto</h1>
        <div id="chart" style="width: 100%; height: 600px;"></div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        var data = <?= $jsonData; ?>;

        var trace = {
            type: "sunburst",
            labels: data.labels,
            parents: data.parents,
            values: data.values,
            branchvalues: "total", // Asegura que el padre siempre sea la suma de sus hijos
            marker: {
                colors: ["#444", "#007bff", "#28a745", "#ff9800", "#17a2b8"]
            }
        };

        var layout = {
            margin: { t: 10, l: 10, r: 10, b: 10 },
            sunburstcolorway: ["#007bff", "#28a745", "#ff9800"],
            hoverinfo: "label+value+percent parent"
        };

        Plotly.newPlot('chart', [trace], layout);
    });
    </script>
</body>
</html>

