<?php
session_start();
$_SESSION['LAST_ACTIVITY'] = time(); // Actualizar actividad
http_response_code(204); // No devuelve contenido visible
exit;
?>
