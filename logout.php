<?php
// Eliminar la cookie estableciendo un tiempo de expiraci贸n pasado
setcookie("user_session", "", time() - 3600, "/");
header("Location: login.php");
exit;
?>
