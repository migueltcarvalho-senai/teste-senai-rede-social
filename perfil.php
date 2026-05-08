<?php
// Inicia configurações da aplicação (sessão + banco + constantes)
require_once __DIR__ . '/config/app.php';

// Exige que o visitante esteja logado
exigirLogin();

// Pega o nick da URL (?nick=fulano). Remove caracteres perigosos.
$nickUrl = isset($_GET['nick']) ? trim(strip_tags($_GET['nick'])) : '';

// Se não recebeu nenhum nick, volta para o feed
if ($nickUrl === '') {
    header('Location: index.php');
    exit;
}

try {
    // Busca os dados do usuário pelo nick recebido na URL
    $stmtUser = $pdo->prepare("SELECT id, nome, nick, data_criacao FROM usuario WHERE nick = ? LIMIT 1");
    $stmtUser->execute([$nickUrl]);
    $perfil = $stmtUser->fetch();

    // Se o usuário não existir, redireciona para o feed
    if (!$perfil) {
        header('Location: index.php');
        exit;
    }

    // Busca TODAS as postagens do usuário, da mais recente para a mais antiga
    $stmtPosts = $pdo->prepare("
        SELECT id, caminho_foto, descricao, data_criacao
        FROM post
        WHERE id_user = ?
        ORDER BY id DESC
    ");
    $stmtPosts->execute([$perfil['id']]);
    $postsDoPerfil = $stmtPosts->fetchAll();

    // Conta o total de posts para exibir no cabeçalho do perfil
    $totalPosts = count($postsDoPerfil);

} catch (PDOException $e) {
    // Erro de banco: redireciona com segurança
    header('Location: index.php');
    exit;
}

// Verifica se a página sendo visitada é o próprio perfil do usuário logado
$ehProprioUsuario = ($_SESSION['usuario_nick'] === $perfil['nick']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@<?= htmlspecialchars($perfil['nick']) ?> · InstaSenai</title>
    <meta name="description" content="Veja as fotos de <?= htmlspecialchars($perfil['nome']) ?> no InstaSenai.">

    <!-- Fonte moderna -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ===== RESET E BASE ===== */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fafafa;
            color: #262626;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 200;
            background: #ffffff;
            border-bottom: 1px solid #dbdbdb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 54px;
        }

        .navbar-logo {
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #262626;
            text-decoration: none;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
        }

        /* Nick clicável na navbar leva ao próprio perfil */
        .navbar-user a.nick-link {
            color: #262626;
            font-weight: 600;
            text-decoration: none;
        }

        .navbar-user a.nick-link:hover {
            text-decoration: underline;
        }

        .navbar-user a.sair-link {
            color: #8e8e8e;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .navbar-user a.sair-link:hover { color: #262626; }

        /* ===== LAYOUT ===== */
        .page-wrap {
            padding-top: 70px;
            max-width: 935px;
            margin: 0 auto;
            padding-left: 20px;
            padding-right: 20px;
            padding-bottom: 60px;
        }

        /* ===== CABEÇALHO DO PERFIL ===== */
        .perfil-header {
            display: flex;
            align-items: center;
            gap: 48px;
            padding: 40px 0 36px;
            border-bottom: 1px solid #dbdbdb;
            margin-bottom: 44px;
        }

        /* Avatar grande com inicial do nick — estilo texturizado */
        .perfil-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: linear-gradient(135deg, #262626 0%, #555 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            font-weight: 800;
            flex-shrink: 0;
            letter-spacing: -2px;
            /* Texturização sutil via box-shadow em camadas */
            box-shadow:
                inset 0 2px 4px rgba(255,255,255,0.15),
                inset 0 -2px 4px rgba(0,0,0,0.3),
                0 8px 24px rgba(0,0,0,0.18);
            user-select: none;
        }

        .perfil-info {
            flex: 1;
        }

        /* Nome do usuário em destaque com efeito texturizado */
        .perfil-nome-wrap {
            margin-bottom: 16px;
        }

        .perfil-nick {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -1px;
            /* Efeito texturizado: sombra dupla que simula relevo */
            color: #1a1a1a;
            text-shadow:
                1px 1px 0px rgba(255,255,255,0.8),
                -1px -1px 0px rgba(0,0,0,0.15),
                2px 2px 6px rgba(0,0,0,0.08);
            display: block;
            line-height: 1;
            margin-bottom: 4px;
        }

        .perfil-nome {
            font-size: 0.95rem;
            font-weight: 400;
            color: #8e8e8e;
        }

        /* Contador de posts */
        .perfil-stats {
            display: flex;
            gap: 32px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .stat-num {
            font-size: 1.2rem;
            font-weight: 700;
            color: #262626;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #8e8e8e;
        }

        /* Data de entrada na plataforma */
        .perfil-membro {
            margin-top: 12px;
            font-size: 0.8rem;
            color: #b0b0b0;
        }

        /* ===== GRID DE POSTS ===== */
        /* Três colunas como o Instagram */
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3px;
        }

        /* Cada célula do grid é um quadrado clicável */
        .grid-item {
            position: relative;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            cursor: pointer;
            background: #efefef;
        }

        .grid-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease, filter 0.3s ease;
        }

        /* Overlay escuro que aparece no hover com ícone e descrição preview */
        .grid-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            opacity: 0;
            transition: background 0.25s ease, opacity 0.25s ease;
        }

        .grid-item:hover img {
            transform: scale(1.04);
            filter: brightness(0.75);
        }

        .grid-item:hover .grid-overlay {
            background: rgba(0, 0, 0, 0.35);
            opacity: 1;
        }

        /* Ícone de "ver" no hover */
        .grid-overlay-icon {
            font-size: 1.6rem;
            color: #fff;
            line-height: 1;
        }

        /* Estado vazio: nenhuma postagem ainda */
        .grid-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 24px;
            color: #8e8e8e;
        }

        .grid-empty strong {
            display: block;
            font-size: 1.1rem;
            color: #262626;
            margin-bottom: 8px;
        }

        /* ===== MODAL DE FOTO ===== */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            z-index: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        /* Classe que abre o modal */
        .modal-backdrop.aberto {
            opacity: 1;
            visibility: visible;
        }

        /* Caixa do modal: foto à esquerda, info à direita */
        .modal-box {
            background: #ffffff;
            border-radius: 4px;
            overflow: hidden;
            display: flex;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            transform: scale(0.95);
            transition: transform 0.2s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }

        .modal-backdrop.aberto .modal-box {
            transform: scale(1);
        }

        /* Lado esquerdo: foto */
        .modal-foto-wrap {
            flex: 1;
            min-width: 0;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-foto-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            max-height: 90vh;
            display: block;
        }

        /* Lado direito: cabeçalho + descrição */
        .modal-info {
            width: 280px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #dbdbdb;
        }

        /* Cabeçalho do modal com nick e avatar */
        .modal-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-bottom: 1px solid #dbdbdb;
        }

        .modal-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #262626;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .modal-nick {
            font-size: 0.875rem;
            font-weight: 600;
            color: #262626;
        }

        /* Corpo do modal: descrição */
        .modal-body {
            padding: 16px;
            flex: 1;
            overflow-y: auto;
        }

        .modal-descricao {
            font-size: 0.875rem;
            line-height: 1.6;
            color: #262626;
        }

        .modal-descricao strong {
            margin-right: 4px;
        }

        /* Data no rodapé do modal */
        .modal-footer {
            padding: 12px 16px;
            border-top: 1px solid #dbdbdb;
            font-size: 0.75rem;
            color: #8e8e8e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Botão de fechar o modal (X) */
        .modal-fechar {
            position: fixed;
            top: 20px;
            right: 24px;
            z-index: 600;
            background: none;
            border: none;
            color: #ffffff;
            font-size: 2rem;
            cursor: pointer;
            line-height: 1;
            opacity: 0.8;
            transition: opacity 0.15s ease;
        }

        .modal-fechar:hover { opacity: 1; }

        /* ===== RESPONSIVO: tela pequena quebra o modal para coluna única ===== */
        @media (max-width: 680px) {
            .perfil-header {
                gap: 24px;
            }

            .perfil-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }

            .perfil-nick {
                font-size: 1.3rem;
            }

            .modal-box {
                flex-direction: column;
            }

            .modal-info {
                width: 100%;
                border-left: none;
                border-top: 1px solid #dbdbdb;
            }
        }

        /* ===== ANIMAÇÃO DE ENTRADA DOS ITENS DO GRID ===== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .grid-item {
            animation: fadeInUp 0.3s ease forwards;
        }

        /* Escalonamento da animação por item */
        <?php
        // Gera um atraso de animação para cada post (máx. 24 itens animados)
        $maxAnimados = min($totalPosts, 24);
        for ($i = 0; $i < $maxAnimados; $i++):
        ?>
        .grid-item:nth-child(<?= $i + 1 ?>) { animation-delay: <?= $i * 0.04 ?>s; }
        <?php endfor; ?>
    </style>
</head>
<body>

    <!-- Navbar fixa -->
    <nav class="navbar">
        <a href="index.php" class="navbar-logo">InstaSenai</a>
        <div class="navbar-user">
            <!-- Nick do usuário logado leva ao próprio perfil -->
            <a href="perfil.php?nick=<?= urlencode($_SESSION['usuario_nick']) ?>" class="nick-link">
                @<?= htmlspecialchars($_SESSION['usuario_nick']) ?>
            </a>
            <a href="auth/logout.php" class="sair-link">Sair</a>
        </div>
    </nav>

    <div class="page-wrap">

        <!-- ===== CABEÇALHO DO PERFIL ===== -->
        <header class="perfil-header">

            <!-- Avatar com a inicial do nick -->
            <div class="perfil-avatar" aria-label="Avatar de <?= htmlspecialchars($perfil['nick']) ?>">
                <?= strtoupper(substr($perfil['nick'], 0, 1)) ?>
            </div>

            <div class="perfil-info">

                <!-- Nome texturizado em destaque -->
                <div class="perfil-nome-wrap">
                    <span class="perfil-nick">@<?= htmlspecialchars($perfil['nick']) ?></span>
                    <span class="perfil-nome"><?= htmlspecialchars($perfil['nome']) ?></span>
                </div>

                <!-- Contador de postagens -->
                <div class="perfil-stats">
                    <div class="stat-item">
                        <span class="stat-num"><?= $totalPosts ?></span>
                        <span class="stat-label"><?= $totalPosts === 1 ? 'publicação' : 'publicações' ?></span>
                    </div>
                </div>

                <!-- Data de entrada -->
                <p class="perfil-membro">
                    Membro desde <?= date('d/m/Y', strtotime($perfil['data_criacao'])) ?>
                </p>

            </div>
        </header>

        <!-- ===== GRID DE POSTS ===== -->
        <section class="posts-grid" id="posts-grid" aria-label="Publicações de <?= htmlspecialchars($perfil['nick']) ?>">

            <?php if (empty($postsDoPerfil)): ?>
                <!-- Estado vazio -->
                <div class="grid-empty">
                    <strong>Nenhuma publicação ainda</strong>
                    <?php if ($ehProprioUsuario): ?>
                        <p>Compartilhe sua primeira foto com a turma!</p>
                    <?php else: ?>
                        <p>Este usuário ainda não publicou nada.</p>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <?php
                // ====================================================
                // FOREACH: itera cada post do usuário e cria a célula
                // do grid. Ao clicar, os dados são passados ao modal
                // via atributos data-* para evitar requisições extras.
                // ====================================================
                foreach ($postsDoPerfil as $post):
                    $dataFormatada = date('d/m/Y', strtotime($post['data_criacao']));
                    // Escapa os valores para uso seguro em atributos HTML
                    $fotoEsc      = htmlspecialchars($post['caminho_foto'], ENT_QUOTES);
                    $descEsc      = htmlspecialchars($post['descricao'],    ENT_QUOTES);
                    $dataEsc      = htmlspecialchars($dataFormatada,        ENT_QUOTES);
                ?>
                <article
                    class="grid-item"
                    role="button"
                    tabindex="0"
                    aria-label="Ver publicação de <?= htmlspecialchars($perfil['nick']) ?>"
                    data-foto="<?= $fotoEsc ?>"
                    data-descricao="<?= $descEsc ?>"
                    data-data="<?= $dataEsc ?>"
                    onclick="abrirModal(this)"
                    onkeydown="if(event.key==='Enter') abrirModal(this)"
                >
                    <!-- Foto em miniatura -->
                    <img
                        src="<?= htmlspecialchars($post['caminho_foto']) ?>"
                        alt="Publicação de <?= htmlspecialchars($perfil['nick']) ?> em <?= $dataFormatada ?>"
                        loading="lazy"
                    >
                    <!-- Overlay de hover -->
                    <div class="grid-overlay">
                        <span class="grid-overlay-icon">&#128065;</span>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>

        </section>

    </div><!-- fim .page-wrap -->


    <!-- ===== MODAL DE FOTO ===== -->
    <div class="modal-backdrop" id="modal-backdrop" role="dialog" aria-modal="true" aria-label="Visualizar publicação">

        <!-- Botão de fechar (X) -->
        <button class="modal-fechar" id="modal-fechar" onclick="fecharModal()" aria-label="Fechar">&times;</button>

        <div class="modal-box">

            <!-- Foto em tamanho maior -->
            <div class="modal-foto-wrap">
                <img id="modal-foto" src="" alt="Foto da publicação">
            </div>

            <!-- Informações da publicação -->
            <div class="modal-info">

                <!-- Cabeçalho: avatar + nick -->
                <div class="modal-header">
                    <div class="modal-avatar" aria-hidden="true">
                        <?= strtoupper(substr($perfil['nick'], 0, 1)) ?>
                    </div>
                    <span class="modal-nick">@<?= htmlspecialchars($perfil['nick']) ?></span>
                </div>

                <!-- Descrição da publicação -->
                <div class="modal-body">
                    <p class="modal-descricao">
                        <strong>@<?= htmlspecialchars($perfil['nick']) ?></strong>
                        <span id="modal-descricao-texto"></span>
                    </p>
                </div>

                <!-- Data da publicação -->
                <div class="modal-footer" id="modal-data"></div>

            </div>
        </div>
    </div><!-- fim .modal-backdrop -->


    <script>
        /**
         * Referências aos elementos do modal
         */
        const modalBackdrop    = document.getElementById('modal-backdrop');
        const modalFoto        = document.getElementById('modal-foto');
        const modalDescricao   = document.getElementById('modal-descricao-texto');
        const modalData        = document.getElementById('modal-data');

        /**
         * Abre o modal com os dados do post clicado.
         * Os dados vêm dos atributos data-* do elemento do grid.
         *
         * @param {HTMLElement} el - O article.grid-item clicado
         */
        function abrirModal(el) {
            // Preenche o modal com os dados do post clicado
            modalFoto.src          = el.dataset.foto;
            modalDescricao.textContent = el.dataset.descricao;
            modalData.textContent  = el.dataset.data;

            // Abre o modal com a classe CSS
            modalBackdrop.classList.add('aberto');

            // Trava o scroll da página enquanto o modal está aberto
            document.body.style.overflow = 'hidden';
        }

        /**
         * Fecha o modal e restaura o scroll da página.
         */
        function fecharModal() {
            modalBackdrop.classList.remove('aberto');
            document.body.style.overflow = '';

            // Limpa a src da foto para não mostrar a imagem anterior ao reabrir
            setTimeout(() => { modalFoto.src = ''; }, 200);
        }

        /**
         * Fecha o modal ao clicar no backdrop (fora da caixa)
         */
        modalBackdrop.addEventListener('click', function(e) {
            if (e.target === modalBackdrop) fecharModal();
        });

        /**
         * Fecha o modal ao pressionar a tecla Escape
         */
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fecharModal();
        });
    </script>

</body>
</html>
