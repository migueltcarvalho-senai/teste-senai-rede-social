<?php
// Inicia a sessão PHP para guardar os dados do usuário logado
session_start();

// Inclui o arquivo de conexão com o banco
require_once __DIR__ . '/db.php';

// Google OAuth 2.0 - Client ID fornecido pelo usuário
define('GOOGLE_CLIENT_ID', '683810976168-ht7po6fdrdr4c1kdihlr5qq3b0ogq4t7.apps.googleusercontent.com');

/**
 * Verifica se o usuário está logado.
 * Se não estiver, redireciona para a página de login.
 */
function exigirLogin()
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}
?>