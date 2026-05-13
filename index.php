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
</head>

<body>


    <!-- Barra de navegação fixa no topo -->
    <nav class="navbar">
        <!-- Logo à esquerda -->
        <a href="index.php" class="navbar-logo-img">
            <img src="icon/logo.webp" alt="SenaiDex Logo">
        </a>

        <!-- Título central com fonte Capuche Trial -->
        <span class="navbar-brand">SenaiDex</span>

        <!-- Avatar do usuário logado à direita (leva ao perfil) -->
        <a href="perfil.php?nick=<?= urlencode($_SESSION['usuario_nick']) ?>" class="navbar-avatar"
            aria-label="Meu perfil">
            <?= strtoupper(substr($_SESSION['usuario_nick'], 0, 1)) ?>
        </a>
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

                            <?php if ($post['nick_usuario'] === $_SESSION['usuario_nick']): ?>
                                <!-- Botão de compartilhar: visível APENAS para o autor do post -->
                                <div class="post-actions">
                                    <button class="btn-share" onclick="compartilhar(this)"
                                        data-texto="<?= htmlspecialchars($post['descricao'], ENT_QUOTES) ?>"
                                        data-foto="<?= htmlspecialchars($post['caminho_foto'], ENT_QUOTES) ?>"
                                        data-nick="<?= htmlspecialchars($post['nick_usuario'], ENT_QUOTES) ?>"
                                        data-url="<?= htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . '/teste-senai-rede-social/perfil.php?nick=' . urlencode($post['nick_usuario']), ENT_QUOTES) ?>"
                                        title="Compartilhar este post">
                                        <span class="share-icon">&#8679;</span>
                                        <span class="share-label">Compartilhar</span>
                                    </button>
                                </div>
                            <?php endif; ?>

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

        // Nick do usuário logado (injetado pelo PHP para comparação no JS dos posts AJAX)
        const nickLogado = '<?= addslashes($_SESSION['usuario_nick']) ?>';

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

        /**
         * Abre o diálogo nativo de compartilhamento do sistema (Web Share API).
         *
         * POR QUE USAR files:[] ?
         * Passar apenas {title, text, url} faz o share sheet mostrar apenas apps
         * de mensagens (WhatsApp, SMS, e-mail). Para que o Instagram (e outros
         * apps de foto) apareçam no share sheet do sistema, é obrigatório
         * incluir um arquivo de imagem no campo files:[].
         *
         * COMO FUNCIONA:
         * 1. Busca a imagem do post como Blob via fetch()
         * 2. Cria um File a partir do Blob (nome + tipo MIME)
         * 3. Verifica se o dispositivo suporta compartilhar aquele arquivo
         * 4. Chama navigator.share({ files, text, url })
         * 5. O usuário escolhe o Instagram no share sheet nativo
         * 6. Dentro do Instagram, ele escolhe: Stories, Feed ou DM
         *
         * NOTA: não é possível forçar "Stories" ou "Feed" via web —
         * isso é decidido dentro do app pelo usuário.
            /**
         * Gera uma imagem com overlay de hashtags/menções via Canvas API
         * e a compartilha via Web Share API.
         *
         * FLUXO:
         *  1. Carrega a foto original num elemento <img> temporário
         *  2. Cria um <canvas> offscreen com as mesmas dimensões
         *  3. Desenha a foto original no canvas
         *  4. Desenha um gradiente escuro no rodapé (para o texto ficar legível)
         *  5. Escreve o nick (@autor), a descrição e as hashtags sobre o gradiente
         *  6. Exporta o canvas como Blob JPEG
         *  7. Compartilha via navigator.share({ files: [File] })
         *
         * RESULTADO:
         *  - Instagram Stories/Feed: a imagem já chega com o texto visível ✅
         *  - WhatsApp / Telegram:    imagem + texto no campo de mensagem ✅
         *  - Desktop (fallback):     compartilha texto + URL sem arquivo ✅
         *
         * @param {HTMLElement} btn - O botão .btn-share clicado
         */
        async function compartilhar(btn) {
            const texto       = btn.dataset.texto || '';
            const url         = btn.dataset.url   || window.location.href;
            const caminhoFoto = btn.dataset.foto   || '';
            const nick        = btn.dataset.nick   || '';

            if (!navigator.share) {
                alert('Seu navegador não suporta compartilhamento nativo.\nCopie o link: ' + url);
                return;
            }

            // Feedback visual
            const label         = btn.querySelector('.share-label');
            const textoOriginal = label ? label.textContent : 'Compartilhar';
            btn.disabled = true;
            if (label) label.textContent = 'Gerando imagem...';

            try {
                // ── 1. Carrega a imagem original ──────────────────────────────
                const imagemOriginal = await new Promise((resolve, reject) => {
                    const img = new Image();
                    // crossOrigin necessário para poder desenhar no canvas sem "tainted"
                    img.crossOrigin = 'anonymous';
                    img.onload  = () => resolve(img);
                    img.onerror = () => reject(new Error('Não foi possível carregar a imagem.'));
                    img.src = caminhoFoto;
                });

                // ── 2. Cria canvas com as dimensões da imagem ─────────────────
                const canvas  = document.createElement('canvas');
                const largura = imagemOriginal.naturalWidth  || imagemOriginal.width;
                const altura  = imagemOriginal.naturalHeight || imagemOriginal.height;
                canvas.width  = largura;
                canvas.height = altura;
                const ctx = canvas.getContext('2d');

                // ── 3. Desenha a foto original ────────────────────────────────
                ctx.drawImage(imagemOriginal, 0, 0, largura, altura);

                // ── 4. Gradiente escuro no rodapé (legibilidade do texto) ─────
                // Altura da faixa: 28% da imagem ou 180px, o que for menor
                const alturaFaixa = Math.min(Math.round(altura * 0.28), 180);
                const gradiente   = ctx.createLinearGradient(0, altura - alturaFaixa, 0, altura);
                gradiente.addColorStop(0,   'rgba(0,0,0,0)');
                gradiente.addColorStop(0.4, 'rgba(0,0,0,0.55)');
                gradiente.addColorStop(1,   'rgba(0,0,0,0.82)');
                ctx.fillStyle = gradiente;
                ctx.fillRect(0, altura - alturaFaixa, largura, alturaFaixa);

                // ── 5. Configurações de texto ─────────────────────────────────
                const padding     = Math.round(largura * 0.04); // 4% das bordas
                const tamBase     = Math.max(18, Math.round(largura * 0.042)); // tamanho dinâmico

                // Sombra para garantir leitura em qualquer fundo
                ctx.shadowColor   = 'rgba(0,0,0,0.7)';
                ctx.shadowBlur    = 6;
                ctx.shadowOffsetX = 1;
                ctx.shadowOffsetY = 1;

                // ── 5a. Nick do autor (@instaSenai) ──────────────────────────
                ctx.fillStyle = '#ffffff';
                ctx.font      = `bold ${tamBase}px Inter, Arial, sans-serif`;
                ctx.textAlign = 'left';
                const nickTexto = nick ? '@' + nick : '@InstaSenai';
                ctx.fillText(nickTexto, padding, altura - alturaFaixa + Math.round(tamBase * 1.6));

                // ── 5b. Descrição do post (truncada se longa) ─────────────────
                if (texto) {
                    const tamDesc  = Math.round(tamBase * 0.82);
                    ctx.font       = `${tamDesc}px Inter, Arial, sans-serif`;
                    ctx.fillStyle  = 'rgba(255,255,255,0.92)';

                    // Trunca a descrição para caber em uma linha
                    const maxLargTexto = largura - padding * 2;
                    let descTruncada   = texto;
                    while (ctx.measureText(descTruncada).width > maxLargTexto && descTruncada.length > 10) {
                        descTruncada = descTruncada.slice(0, -4) + '...';
                    }
                    ctx.fillText(descTruncada, padding, altura - alturaFaixa + Math.round(tamBase * 3.0));
                }

                // ── 5c. Hashtags fixas no rodapé ──────────────────────────────
                const tamHash  = Math.round(tamBase * 0.75);
                ctx.font       = `bold ${tamHash}px Inter, Arial, sans-serif`;
                ctx.fillStyle  = 'rgba(180,220,255,0.95)'; // azul claro para destacar
                ctx.fillText('#InstaSenai #SENAI', padding, altura - padding);

                // Remove sombra para não afetar outros desenhos
                ctx.shadowColor = 'transparent';

                // ── 6. Exporta canvas como JPEG (qualidade 90%) ───────────────
                if (label) label.textContent = 'Compartilhando...';
                const blobGerado = await new Promise((resolve, reject) => {
                    canvas.toBlob(
                        (b) => b ? resolve(b) : reject(new Error('Falha ao gerar imagem.')),
                        'image/jpeg',
                        0.9
                    );
                });

                const arquivoGerado = new File(
                    [blobGerado],
                    'instaSenai_post.jpg',
                    { type: 'image/jpeg' }
                );

                // ── 7. Compartilha via Web Share API ──────────────────────────
                const hashtags    = '#InstaSenai #SENAI';
                const textoShare  = texto ? hashtags + ' ' + texto : hashtags;

                const dadosShare = {
                    files: [arquivoGerado], // imagem com overlay embutido
                    text:  textoShare,      // pre-preenche WhatsApp / Telegram / SMS
                    url:   url
                };

                if (navigator.canShare && navigator.canShare(dadosShare)) {
                    await navigator.share(dadosShare);
                } else {
                    // Fallback: sem arquivo (desktop ou navegador sem suporte a files)
                    await navigator.share({ title: 'InstaSenai', text: textoShare, url: url });
                }

            } catch (erro) {
                if (erro.name !== 'AbortError') {
                    console.warn('Erro ao compartilhar:', erro);
                    // Fallback seguro: texto + URL sem imagem
                    try {
                        await navigator.share({ title: 'InstaSenai', text: texto, url: url });
                    } catch (_) { /* usuário cancelou */ }
                }
            } finally {
                btn.disabled = false;
                if (label) label.textContent = textoOriginal;
            }
        }
    </script>

</body>

</html>