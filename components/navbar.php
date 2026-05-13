<?php
/**
 * components/navbar.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Componente reutilizável da barra de navegação do SenaiDex.
 *
 * COMO USAR EM QUALQUER PÁGINA:
 *   require_once __DIR__ . '/components/navbar.php';
 *   (ajuste o caminho relativo conforme a profundidade do arquivo)
 *
 * PRÉ-REQUISITOS:
 *   - A sessão deve estar iniciada antes de incluir este arquivo.
 *   - $_SESSION['usuario_nick'] deve estar definido (usuário logado).
 *
 * O link do CSS da navbar é injetado aqui automaticamente; não é necessário
 * adicionar <link rel="stylesheet" href="css/navbar.css"> manualmente nas páginas.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// Garante que a sessão esteja ativa antes de usar $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pega o nick do usuário logado de forma segura
$_navNick = isset($_SESSION['usuario_nick']) ? $_SESSION['usuario_nick'] : '';

// Inicial do nick para o avatar (primeira letra, maiúscula)
$_navInicial = $_navNick !== '' ? strtoupper(substr($_navNick, 0, 1)) : '?';

// Nick encodado para uso seguro em URLs
$_navNickUrl = urlencode($_navNick);
?>

<?php /* ── Injeção do CSS da navbar ─────────────────────────────────────────── */ ?>
<link rel="stylesheet" href="css/navbar.css">

<?php /* ── HTML da barra de navegação ──────────────────────────────────────── */ ?>
<nav class="navbar" role="navigation" aria-label="Barra de navegação principal">

    <?php /* Logo à esquerda: clicável, leva ao feed */ ?>
    <a href="index.php" class="navbar-logo-img" aria-label="Ir para o feed">
        <img src="icon/logo.webp" alt="Logo SenaiDex" width="60" height="60">
    </a>

    <?php /* Título "SenaiDex" centralizado absolutamente na navbar */ ?>
    <span class="navbar-brand" aria-hidden="true">SenaiDex</span>

    <?php /* Avatar do usuário logado à direita: exibe inicial do nick */ ?>
    <a href="perfil.php?nick=<?= htmlspecialchars($_navNickUrl, ENT_QUOTES) ?>"
       class="navbar-avatar"
       aria-label="Meu perfil (<?= htmlspecialchars($_navNick, ENT_QUOTES) ?>)">
        <?= htmlspecialchars($_navInicial, ENT_QUOTES) ?>
    </a>

</nav>
