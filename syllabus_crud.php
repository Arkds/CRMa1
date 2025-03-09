<?php
require 'db.php';

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

$query = "SELECT * FROM products ORDER BY relevance DESC, name ASC";
$products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

include('header.php');
?>

<div class="container mt-5">
    <h1 class="text-center">Gestión de Temarios</h1>
    <button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>

    <table id="syllabusTable" class="table table-striped display compact">
        <thead>
            <tr>
                <th>ID</th>
                <th>Relevancia</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Temario</th>
                <th>Precio</th>
                <th>Productos Relacionados</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= $product['id'] ?></td>
                    <td><?= $product['relevance'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?>
                    </td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td>
                        <div class="text-truncate" style="max-width: 200px; cursor:pointer;" onclick="toggleExpand(this)">
                            <?= htmlspecialchars($product['description']) ?>
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary"
                            onclick="copyToClipboard(`<?= htmlspecialchars_decode($product['syllabus'], ENT_QUOTES) ?>`, '<?= htmlspecialchars($product['name']) ?>')">
                            Copiar
                        </button>
                    </td>
                    <td>
                        <?= $product['price'] !== null ? $product['price'] : 'No especificado' ?>
                    </td>
                    <td>
                        <?php
                        $stmt = $pdo->prepare("SELECT p.id, p.name, p.syllabus FROM products p 
                           JOIN product_relations r ON p.id = r.related_product_id 
                           WHERE r.product_id = ?");
                        $stmt->execute([$product['id']]);
                        $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if ($related_products) {
                            $relatedId = "related-" . $product['id'];
                            ?>
                            <button class="btn btn-sm btn-light" onclick="toggleRelatedProducts('<?= $relatedId ?>', this)">
                                ▼
                            </button>
                            <div id="<?= $relatedId ?>" style="display: none; margin-top: 5px;">
                                <?php foreach ($related_products as $related): ?>
                                    <button class="btn btn-sm btn-outline-primary"
                                        onclick="copyToClipboard(`<?= htmlspecialchars_decode($related['syllabus'], ENT_QUOTES) ?>`, '<?= htmlspecialchars($related['name']) ?>')">
                                        <?= htmlspecialchars($related['name']) ?>
                                    </button><br>
                                <?php endforeach; ?>
                            </div>
                        <?php } else {
                            echo 'No relacionados';
                        } ?>
                    </td>


                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Script para copiar texto al portapapeles y toggle -->
<script>
    function copyToClipboard(text, productName) {
        if (!text || text.trim() === "Sin temario") {
            alert("Este producto no tiene temario disponible.");
            return;
        }

        // Crear un elemento de texto temporal para copiar el contenido con formato correcto
        let tempTextarea = document.createElement("textarea");
        tempTextarea.style.position = "fixed";
        tempTextarea.style.opacity = "0";
        tempTextarea.value = text;

        document.body.appendChild(tempTextarea);
        tempTextarea.select();
        document.execCommand("copy");
        document.body.removeChild(tempTextarea);

        alert("Temario de " + productName + " copiado al portapapeles.");
    }


    function toggleRelatedProducts(relatedId, button) {
        let relatedDiv = document.getElementById(relatedId);

        if (relatedDiv.style.display === "none") {
            relatedDiv.style.display = "block";
            button.innerHTML = "▲"; // Cambia a flecha hacia arriba
        } else {
            relatedDiv.style.display = "none";
            button.innerHTML = "▼"; // Cambia a flecha hacia abajo
        }
    }



    $(document).ready(function () {
        $('#syllabusTable').DataTable({
            paging: true,
            searching: true,  // ✔ Habilita la barra de búsqueda
            ordering: true,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            },
            initComplete: function () {
                // Mueve el buscador a la parte superior izquierda
                $('#syllabusTable_filter').css({
                    'text-align': 'left',
                    'float': 'none',
                    'margin': '0'
                });

                // Mueve "Mostrar n registros" a la parte superior derecha
                $('#syllabusTable_length').css({
                    'text-align': 'right',
                    'float': 'right',
                    'margin': '0'
                });
            }
        });
    });


</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>