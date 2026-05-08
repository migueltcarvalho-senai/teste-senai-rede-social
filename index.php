<?php
// Inicia configurações (sessão + banco + constantes)
require_once __DIR__ . '/config/app.php';

// Exige que o usuário esteja logado para ver o feed
exigirLogin();

// Pega os 5 primeiros posts direto no PHP para o primeiro render (SSR)
// O lazy load via AJAX vai buscar os próximos conforme o usuário rolar
$paginaInicial = 1;
$porPagina = 5;

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
    $total = (int) $pdo->query("SELECT COUNT(*) FROM post")->fetchColumn();
    $temMais = $total > $porPagina;

} catch (PDOException $e) {
    $posts = [];
    $temMais = false;
    $total = 0;
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

    <!-- Link com o CSS -->
    <link rel="stylesheet" href="css/index.css">

    <!-- Barra de navegação fixa no topo -->
    <nav class="navbar">
        <a href="index.php" class="navbar-logo">InstaSenai</a>
        <div class="navbar-user">
            <!-- Nick do usuário logado leva ao próprio perfil -->
            <a href="perfil.php?nick=<?= urlencode($_SESSION['usuario_nick']) ?>"
                style="color:#262626;font-weight:600;text-decoration:none;">@<?= htmlspecialchars($_SESSION['usuario_nick']) ?></a>
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
                        $inicial = strtoupper(substr($post['nick_usuario'], 0, 1));
                        ?>
                        <article class="post-card">

                            <!-- Cabeçalho: avatar + nome + data -->
                            <div class="post-header">
                                <!-- Avatar clicável leva ao perfil do autor -->
                                <a href="perfil.php?nick=<?= urlencode($post['nick_usuario']) ?>" style="text-decoration:none;">
                                    <div class="post-avatar"><?= $inicial ?></div>
                                </a>
                                <div class="post-user-info">
                                    <!-- Nick clicável leva ao perfil do autor -->
                                    <a href="perfil.php?nick=<?= urlencode($post['nick_usuario']) ?>" class="post-username"
                                        style="text-decoration:none;color:#262626;">@<?= htmlspecialchars($post['nick_usuario']) ?></a>
                                    <span class="post-date"><?= $dataFormatada ?></span>
                                </div>
                            </div>

                            <!-- Foto do post -->
                            <div class="post-image-wrap">
                                <img src="<?= htmlspecialchars($post['caminho_foto']) ?>"
                                    alt="Foto de <?= htmlspecialchars($post['nome_usuario']) ?>" class="post-image"
                                    loading="lazy">
                            </div>

                            <!-- Legenda -->
                            <div class="post-body">
                                <p class="post-caption">
                                    <!-- Nick na legenda também é clicável -->
                                    <a href="perfil.php?nick=<?= urlencode($post['nick_usuario']) ?>"
                                        style="font-weight:600;text-decoration:none;color:#262626;">@<?= htmlspecialchars($post['nick_usuario']) ?></a>
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
        const feedContainer = document.getElementById('feed-container');
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
            function (entries) {
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