<?php
// Inicia a sessão para poder destruir os dados
session_start();

// Apaga todos os dados da sessão atual
session_destroy();

// Redireciona para a página de login
header('Location: ../login.php');
exit;
?>
