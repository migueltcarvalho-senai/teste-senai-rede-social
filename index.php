<?php
// Inicia configurações (sessão + banco + constantes)
require_once __DIR__ . '/config/app.php';

// Exige que o usuário esteja logado para ver o feed
exigirLogin();

// Pega os 5 primeiros posts direto no PHP para o primeiro render (SSR)
// O lazy load via AJAX vai buscar os próximos conforme o usuário rolar
$paginaInicial = 1;
$porPagina     = 5;

try {
    /**
     * Busca os posts mais recentes com os dados do usuário que postou.
     * O foreach abaixo vai renderizar cada post na página.
     */
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.caminho_foto,
            p.descricao,
            p.data_criacao,
            u.nome AS nome_usuario,
            u.nick AS nick_usuario
        FROM post p
        INNER JOIN usuario u ON u.id = p.id_user
        ORDER BY p.id DESC
        LIMIT :limite OFFSET 0
    ");
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->execute();

    $posts = $stmt->fetchAll();

    // Conta o total de posts para saber se tem mais para carregar
    $total   = (int)$pdo->query("SELECT COUNT(*) FROM post")->fetchColumn();
    $temMais = $total > $porPagina;

} catch (PDOException $e) {
    $posts   = [];
    $temMais = false;
    $total   = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InstaSenai – Feed</title>
    <meta name="description" content="Veja as fotos compartilhadas pela sua turma no InstaSenai.">

    <!-- Fonte moderna -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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

        /* ===== NAVBAR FIXA NO TOPO ===== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
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

        /* Info do usuário logado na navbar */
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            color: #262626;
        }

        .navbar-user a {
            color: #8e8e8e;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .navbar-user a:hover {
            color: #262626;
        }

        /* ===== LAYOUT PRINCIPAL ===== */
        .main-layout {
            /* Empurra o conteúdo abaixo da navbar fixa */
            padding-top: 70px;
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }

        /* Coluna central onde ficam os posts */
        .feed-column {
            width: 100%;
            max-width: 470px;
            padding: 0 0 80px;
        }

        /* ===== CARD DE POST ===== */
        .post-card {
            background: #ffffff;
            border: 1px solid #dbdbdb;
            border-radius: 4px;
            margin-bottom: 24px;
            overflow: hidden;
        }

        /* Cabeçalho do post: avatar + nome + data */
        .post-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
        }

        /* Avatar circular com inicial do nick */
        .post-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #262626;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .post-user-info {
            display: flex;
            flex-direction: column;
        }

        .post-username {
            font-size: 0.875rem;
            font-weight: 600;
            color: #262626;
        }

        .post-date {
            font-size: 0.75rem;
            color: #8e8e8e;
        }

        /* Imagem do post (ocupa 100% da largura do card) */
        .post-image-wrap {
            width: 100%;
            aspect-ratio: 1 / 1; /* Quadrado como o Instagram */
            overflow: hidden;
            background: #f0f0f0;
        }

        .post-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Corpo do post: legenda */
        .post-body {
            padding: 12px 16px 16px;
        }

        .post-caption {
            font-size: 0.875rem;
            line-height: 1.5;
            color: #262626;
        }

        .post-caption strong {
            margin-right: 4px;
        }

        /* ===== ESTADO VAZIO (nenhum post ainda) ===== */
        .feed-empty {
            text-align: center;
            padding: 60px 24px;
            color: #8e8e8e;
        }

        .feed-empty p {
            font-size: 0.95rem;
            margin-top: 8px;
        }

        /* ===== INDICADOR DE CARREGAMENTO ===== */
        .loading-indicator {
            text-align: center;
            padding: 24px;
            font-size: 0.85rem;
            color: #8e8e8e;
            display: none; /* Aparece só quando está carregando mais posts */
        }

        /* Animação de pontinhos de carregamento */
        .loading-dots span {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #8e8e8e;
            margin: 0 2px;
            animation: pulsar 1.2s infinite ease-in-out;
        }

        .loading-dots span:nth-child(2) { animation-delay: 0.2s; }
        .loading-dots span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes pulsar {
            0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
            40%            { transform: scale(1);   opacity: 1; }
        }

        /* ===== BOTÃO FLUTUANTE "+" PARA NOVA POSTAGEM ===== */
        .btn-nova-postagem {
            position: fixed;
            /* Fica no lado direito da tela, centralizado verticalmente */
            right: 24px;
            bottom: 40px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #262626;
            color: #ffffff;
            border: none;
            font-size: 1.6rem;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.2);
            transition: background 0.15s ease, transform 0.15s ease;
            z-index: 90;
        }

        .btn-nova-postagem:hover {
            background: #444;
            transform: scale(1.05);
        }

        /* ===== ENTRADA SUAVE DOS POSTS (ANIMAÇÃO) ===== */
        @keyframes entrar {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .post-card {
            animation: entrar 0.3s ease forwards;
        }
    </style>
</head>
<body>

    <!-- Barra de navegação fixa no topo -->
    <nav class="navbar">
        <a href="index.php" class="navbar-logo">InstaSenai</a>
        <div class="navbar-user">
            <span>@<?= htmlspecialchars($_SESSION['usuario_nick']) ?></span>
            <a href="auth/logout.php">Sair</a>
        </div>
    </nav>

    <!-- Layout principal centralizado -->
    <div class="main-layout">
        <main class="feed-column">

            <!-- Container onde os posts são renderizados -->
            <div id="feed-container">

                <?php if (empty($posts)): ?>
                    <!-- Mensagem quando não há nenhum post ainda -->
                    <div class="feed-empty">
                        <strong>Nenhuma postagem ainda</strong>
                        <p>Seja o primeiro a compartilhar uma foto!</p>
                    </div>

                <?php else: ?>
                    <?php
                    // ======================================================
                    // FOREACH: renderiza cada post buscado do banco de dados
                    // ======================================================
                    foreach ($posts as $post):
                        $dataFormatada = date('d/m/Y', strtotime($post['data_criacao']));
                        $inicial       = strtoupper(substr($post['nick_usuario'], 0, 1));
                    ?>
                        <article class="post-card">

                            <!-- Cabeçalho: avatar + nome + data -->
                            <div class="post-header">
                                <div class="post-avatar"><?= $inicial ?></div>
                                <div class="post-user-info">
                                    <span class="post-username">@<?= htmlspecialchars($post['nick_usuario']) ?></span>
                                    <span class="post-date"><?= $dataFormatada ?></span>
                                </div>
                            </div>

                            <!-- Foto do post -->
                            <div class="post-image-wrap">
                                <img
                                    src="<?= htmlspecialchars($post['caminho_foto']) ?>"
                                    alt="Foto de <?= htmlspecialchars($post['nome_usuario']) ?>"
                                    class="post-image"
                                    loading="lazy"
                                >
                            </div>

                            <!-- Legenda -->
                            <div class="post-body">
                                <p class="post-caption">
                                    <strong>@<?= htmlspecialchars($post['nick_usuario']) ?></strong>
                                    <?= htmlspecialchars($post['descricao']) ?>
                                </p>
                            </div>

                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div><!-- fim #feed-container -->

            <!-- Indicador de carregamento (aparece durante o lazy load) -->
            <div class="loading-indicator" id="loading-indicator">
                <div class="loading-dots">
                    <span></span><span></span><span></span>
                </div>
            </div>

        </main>
    </div><!-- fim .main-layout -->

    <!-- Botão flutuante para criar nova postagem -->
    <a href="nova_postagem.php" class="btn-nova-postagem" title="Nova postagem" id="btn-nova-postagem">+</a>

    <script>
        /**
         * ============================================================
         * LAZY LOAD (Carregamento Infinito)
         * ============================================================
         * Controla o carregamento de posts conforme o usuário rola a tela.
         * Quando o usuário chega perto do final da página, busca mais posts
         * via AJAX e adiciona ao feed.
         */

        // Página atual (já carregamos a página 1 via PHP)
        let paginaAtual = 1;

        // Controle para não fazer requisições duplicadas ao mesmo tempo
        let carregando = false;

        // Indica se ainda tem mais posts para buscar
        let temMaisPosts = <?= $temMais ? 'true' : 'false' ?>;

        // Elementos do DOM que vamos precisar
        const feedContainer   = document.getElementById('feed-container');
        const loadingIndicator = document.getElementById('loading-indicator');

        /**
         * Busca mais posts do servidor via AJAX e adiciona no feed.
         */
        function carregarMaisPosts() {
            // Evita chamadas duplicadas ou quando não há mais posts
            if (carregando || !temMaisPosts) return;

            carregando = true;
            loadingIndicator.style.display = 'block'; // Mostra os pontinhos

            // Próxima página
            paginaAtual++;

            // Busca os posts da próxima página
            fetch('api/get_posts.php?pagina=' + paginaAtual)
                .then(res => res.json())
                .then(dados => {
                    if (dados.erro) {
                        console.error('Erro ao carregar posts:', dados.erro);
                        return;
                    }

                    // Adiciona o HTML dos novos posts no feed
                    if (dados.html) {
                        feedContainer.insertAdjacentHTML('beforeend', dados.html);
                    }

                    // Atualiza o controle de paginação
                    temMaisPosts = dados.temMais;
                })
                .catch(err => console.error('Erro na requisição:', err))
                .finally(() => {
                    carregando = false;
                    loadingIndicator.style.display = 'none'; // Esconde os pontinhos
                });
        }

        /**
         * Observador de interseção: detecta quando o usuário está chegando
         * perto do fim da página para disparar o carregamento de mais posts.
         *
         * É mais eficiente que o evento "scroll" pois não executa a cada pixel.
         */
        const observador = new IntersectionObserver(
            function(entries) {
                // Se o indicador de loading ficou visível, carrega mais posts
                if (entries[0].isIntersecting) {
                    carregarMaisPosts();
                }
            },
            {
                // Começa a carregar quando o indicador estiver a 200px da tela
                rootMargin: '200px'
            }
        );

        // Começa a observar o indicador de carregamento
        observador.observe(loadingIndicator);
    </script>

</body>
</html>
