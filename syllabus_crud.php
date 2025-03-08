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

// Obtener todos los productos con sus temarios
$query = "SELECT * FROM products ORDER BY relevance DESC, name ASC";
$products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

include('header.php');
?>

<div class="container mt-5">
    <h1 class="text-center">Gestión de Temarios</h1>
    <button class="btn btn-secondary mb-3" onclick="window.location.replace('index.php');">Volver</button>

    <!-- Filtros -->
    <form method="GET" action="syllabus_crud.php" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <label for="searchName" class="form-label">Buscar por Nombre</label>
                <input type="text" class="form-control" id="searchName" onkeyup="filterTable()">
            </div>
            <div class="col-md-4">
                <label for="searchRelevance" class="form-label">Relevancia</label>
                <select class="form-control" id="searchRelevance" onchange="filterTable()">
                    <option value="">Todos</option>
                    <option value="1">Relevantes</option>
                    <option value="0">No Relevantes</option>
                </select>
            </div>
        </div>
    </form>

    <!-- Tabla de Temarios -->
    <table id="syllabusTable" class="table table-striped">
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
                    <td><?= $product['relevance'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td>
                        <div class="text-truncate" style="max-width: 200px; cursor:pointer;" onclick="toggleExpand(this)">
                            <?= htmlspecialchars($product['description']) ?>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 200px; cursor:pointer;" onclick="toggleExpand(this)">
                            <?= htmlspecialchars($product['syllabus']) ?>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="copyToClipboard('<?= htmlspecialchars($product['syllabus']) ?>', '<?= htmlspecialchars($product['name']) ?>')">
                            Copiar
                        </button>
                    </td>
                    <td><?= $product['price'] !== null ? $product['price'] : 'No especificado' ?></td>
                    <td>
                        <?php
                        $stmt = $pdo->prepare("SELECT p.id, p.name, p.syllabus FROM products p 
                                               JOIN product_relations r ON p.id = r.related_product_id 
                                               WHERE r.product_id = ?");
                        $stmt->execute([$product['id']]);
                        $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if ($related_products) {
                            foreach ($related_products as $related) {
                                echo '<button class="btn btn-link p-0" onclick="copyToClipboard(\'' . htmlspecialchars($related['syllabus']) . '\', \'' . htmlspecialchars($related['name']) . '\')">' . htmlspecialchars($related['name']) . '</button><br>';
                            }
                        } else {
                            echo 'No relacionados';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Script para copiar texto al portapapeles y toggle -->
<script>
    function copyToClipboard(text, productName) {
        navigator.clipboard.writeText(text).then(() => {
            alert("Temario de " + productName + " copiado al portapapeles.");
        }).catch(err => {
            console.error("Error al copiar: ", err);
        });
    }

    function toggleExpand(element) {
        if (element.style.whiteSpace === "normal") {
            element.style.whiteSpace = "nowrap";
            element.style.overflow = "hidden";
            element.style.textOverflow = "ellipsis";
            element.style.maxWidth = "200px";
        } else {
            element.style.whiteSpace = "normal";
            element.style.maxWidth = "none";
        }
    }

    function filterTable() {
        let inputName = document.getElementById("searchName").value.toLowerCase();
        let inputRelevance = document.getElementById("searchRelevance").value;
        let table = document.getElementById("syllabusTable");
        let tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            let tdName = tr[i].getElementsByTagName("td")[2];
            let tdRelevance = tr[i].getElementsByTagName("td")[1];

            if (tdName && tdRelevance) {
                let name = tdName.textContent.toLowerCase();
                let relevance = tdRelevance.textContent.includes("Sí") ? "1" : "0";

                if (
                    (name.includes(inputName) || inputName === "") &&
                    (relevance === inputRelevance || inputRelevance === "")
                ) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    $(document).ready(function () {
        $('#syllabusTable').DataTable({
            paging: true,
            searching: false,
            ordering: true,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            }
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
