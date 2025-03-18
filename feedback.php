<?php
require 'db.php';

// Verificar sesión
if (!isset($_COOKIE['user_session'])) {
    header("Location: login.php");
    exit;
}

// Decodificar la cookie del usuario
$user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
$user_id = $user_data['user_id'];
$username = $user_data['username'];

// Verificar si el usuario ya completó el formulario inicial
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_feedback WHERE user_id = ?");
$stmt->execute([$user_id]);
$hasSubmitted = $stmt->fetchColumn() > 0;
?>

<!-- Botón flotante de soporte -->
<button id="feedbackButton" class="support-btn" data-bs-toggle="modal" data-bs-target="#feedbackModal">
    <i class="bi bi-chat-dots"></i>
    <span class="tooltip-text">¡Hazme clic!</span>
</button>

<style>
    /* Estilos del botón flotante */
    .support-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        background: linear-gradient(45deg, #ff6b6b, #ff8e53);
        color: white;
        border: none;
        border-radius: 50%;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s, box-shadow 0.3s;
        cursor: pointer;
        animation: pulse 1.5s infinite;
    }

    /* Efecto de vibración sutil */
    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }

    /* Al pasar el mouse */
    .support-btn:hover {
        background: linear-gradient(45deg, #ff8e53, #ff6b6b);
        transform: scale(1.2);
        box-shadow: 0 6px 15px rgba(255, 107, 107, 0.5);
    }

    /* Tooltip con animación */
    .tooltip-text {
        position: absolute;
        top: -30px;
        background: #000;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 12px;
        opacity: 0;
        transition: opacity 0.5s;
        pointer-events: none;
    }

    .support-btn:hover .tooltip-text {
        opacity: 1;
    }
</style>




<!-- Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $hasSubmitted ? "Sugerencias" : "Formulario de Feedback" ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (!$hasSubmitted): ?>

                    <form id="initialFeedbackForm">
                        <p><strong>Bienvenido(a) Tu opinión nos ayudará a mejorar el CRM y facilitar tu trabajo.</strong>
                        </p>
                        <p class="fw-lighter text-sm">Las respuestas son parcialemnte anónimas</p>

                        <input type="hidden" name="initial_feedback" value="1"> <!-- Campo oculto para enviar siempre -->
                        <label>¿Has tenido algún problema al usar el CRM?</label>
                        <div>
                            <input type="radio" name="has_problem" value="yes" required> Sí
                            <input type="radio" name="has_problem" value="no" required> No
                        </div>
                        <div id="problemDescriptionContainer" class="mt-2" style="display: none;">
                            <label>Describe el problema:</label>
                            <textarea name="problem_description" class="form-control"></textarea>
                        </div>
                        <hr>
                        <label>¿Has usado antes un sistema similar?</label>
                        <div>
                            <input type="radio" name="used_similar" value="yes" required> Sí
                            <input type="radio" name="used_similar" value="no" required> No
                        </div>
                        <div id="similarSystemContainer" class="mt-2" style="display: none;">
                            <label>¿Cuál sistema usaste y qué te pareció?</label>
                            <textarea name="similar_system" class="form-control"></textarea>
                        </div>
                        <hr>
                        <label>¿Has usado el botón de ayuda?</label>
                        <div>
                            <input type="radio" name="used_help" value="yes" required> Sí
                            <input type="radio" name="used_help" value="no" required> No
                        </div>
                        <div id="helpFeedbackContainer" class="mt-2" style="display: none;">
                            <label>¿Te fue útil? ¿Qué mejorarías?</label>
                            <textarea name="help_feedback" class="form-control"></textarea>
                        </div>
                        <hr>
                        <label>¿Qué te gustaría mejorar del CRM?</label>
                        <textarea name="general_feedback" class="form-control"
                            placeholder="Ejemplo: Menú más rápido, accesos directos..."></textarea>
                        <p><strong>Nota:</strong> Después de enviar este cuestionario pordrás enviar sugerencias rápidas
                            desde el mismo bónton</p>
                        <button type="submit" class="btn btn-primary w-100 mt-3">Enviar</button>

                    </form>
                <?php else: ?>
                    <form id="quickFeedbackForm">
                        <input type="hidden" name="quick_feedback" value="1">
                        <label>¿Tienes alguna sugerencia rápida?</label>
                        <textarea name="quick_feedback_text" class="form-control"
                            placeholder="Ejemplo: Me gustaría ver más atajos de teclado."></textarea>
                        <button type="submit" class="btn btn-primary w-100 mt-3">Enviar</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Función para mostrar/ocultar los campos dinámicos
        function toggleField(radioName, targetId) {
            document.querySelectorAll(`input[name='${radioName}']`).forEach(radio => {
                radio.addEventListener("change", function () {
                    document.getElementById(targetId).style.display = this.value === "yes" ? "block" : "none";
                });
            });
        }

        toggleField("has_problem", "problemDescriptionContainer");
        toggleField("used_similar", "similarSystemContainer");
        toggleField("used_help", "helpFeedbackContainer");

        // Enviar el formulario inicial
        document.getElementById("initialFeedbackForm")?.addEventListener("submit", function (e) {
            e.preventDefault();
            fetch("feedback_process.php", {
                method: "POST",
                body: new FormData(this)
            })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === "OK") {

                        location.reload();
                    } else {
                        alert("Error: " + data);
                    }
                });
        });

        document.getElementById("quickFeedbackForm")?.addEventListener("submit", function (e) {
            e.preventDefault();
            fetch("feedback_process.php", {
                method: "POST",
                body: new FormData(this)
            })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === "OK") {
                        location.reload();
                    } else {
                        alert("Error: " + data);
                    }
                });
        });
    });

</script>