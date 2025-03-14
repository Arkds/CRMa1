<?php
require 'db.php';

if (!isset($_COOKIE['user_session'])) {
    die("ERROR: Sesión no válida.");
}

$user_data = json_decode(base64_decode($_COOKIE['user_session']), true);
$user_id = $user_data['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['initial_feedback'])) {
        $has_problem = $_POST['has_problem'] === "yes" ? 1 : 0;
        $problem_description = $_POST['problem_description'] ?? null;
        $used_similar = $_POST['used_similar'] === "yes" ? 1 : 0;
        $similar_system = $_POST['similar_system'] ?? null;
        $used_help = $_POST['used_help'] === "yes" ? 1 : 0;
        $help_feedback = $_POST['help_feedback'] ?? null;
        $general_feedback = $_POST['general_feedback'] ?? null;

        $stmt = $pdo->prepare("INSERT INTO user_feedback (user_id, has_problem, problem_description, used_similar, similar_system, used_help, help_feedback, general_feedback) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $has_problem, $problem_description, $used_similar, $similar_system, $used_help, $help_feedback, $general_feedback])) {
            echo "OK";
        } else {
            echo "ERROR: No se pudo guardar.";
        }
        exit;
    }

    if (isset($_POST['quick_feedback'])) {
        $stmt = $pdo->prepare("UPDATE user_feedback SET general_feedback = CONCAT(IFNULL(general_feedback, ''), '\n', ?) WHERE user_id = ?");
        if ($stmt->execute([$_POST['quick_feedback_text'], $user_id])) {
            echo "OK";
        } else {
            echo "ERROR: No se pudo actualizar.";
        }
        exit;
    }

    echo "ERROR: Solicitud no válida.";
    exit;
}
?>
