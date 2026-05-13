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
    <title>@<?= htmlspecialchars($perfil['nick']) ?> · SenaiDex</title>
    <meta name="description" content="Veja as fotos de <?= htmlspecialchars($perfil['nome']) ?> no SenaiDex.">

    <!-- Fonte moderna -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Link com o CSS -->
    <link rel="stylesheet" href="css/perfil.css">
</head>

<body>

    <!-- Escalonamento da animação por item -->
    <?php
    // Gera um atraso de animação para cada post (máx. 24 itens animados)
    $maxAnimados = min($totalPosts, 24);
    for ($i = 0; $i < $maxAnimados; $i++):
        ?>
        .grid-item:nth-child(<?= $i + 1 ?>) { animation-delay: <?= $i * 0.04 ?>s; }
    <?php endfor; ?>

    <?php
    /**
     * Inclui o componente reutilizável da navbar.
     * O próprio componente injeta o link para css/navbar.css automaticamente.
     */
    require_once __DIR__ . '/components/navbar.php';
    ?>

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
                    <span class="perfil-nick"><?= htmlspecialchars($perfil['nick']) ?></span>
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

            <!-- Botão Sair (visível apenas no próprio perfil) -->
            <?php if ($ehProprioUsuario): ?>
                <a href="auth/logout.php" class="perfil-sair-btn">Sair</a>
            <?php endif; ?>

            <!-- Botão Editar (visível apenas no próprio perfil) -->
            <?php if ($ehProprioUsuario): ?>
                <a href="auth/logout.php" class="perfil-editar-btn">Editar Perfil</a>
            <?php endif; ?>

        </header>

        <!-- ===== GRID DE POSTS ===== -->
        <section class="posts-grid" id="posts-grid"
            aria-label="Publicações de <?= htmlspecialchars($perfil['nick']) ?>">

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
                    $fotoEsc = htmlspecialchars($post['caminho_foto'], ENT_QUOTES);
                    $descEsc = htmlspecialchars($post['descricao'], ENT_QUOTES);
                    $dataEsc = htmlspecialchars($dataFormatada, ENT_QUOTES);
                    ?>
                    <article class="grid-item" role="button" tabindex="0"
                        aria-label="Ver publicação de <?= htmlspecialchars($perfil['nick']) ?>" data-id="<?= $post['id'] ?>"
                        data-foto="<?= $fotoEsc ?>" data-descricao="<?= $descEsc ?>" data-data="<?= $dataEsc ?>"
                        onclick="abrirModal(this)" onkeydown="if(event.key==='Enter') abrirModal(this)">
                        <!-- Foto em miniatura -->
                        <img src="<?= htmlspecialchars($post['caminho_foto']) ?>"
                            alt="Publicação de <?= htmlspecialchars($perfil['nick']) ?> em <?= $dataFormatada ?>"
                            loading="lazy">
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

                <!-- Cabeçalho: avatar + nick + lixeira (só para o dono) -->
                <div class="modal-header">
                    <div class="modal-avatar" aria-hidden="true">
                        <?= strtoupper(substr($perfil['nick'], 0, 1)) ?>
                    </div>
                    <span class="modal-nick">@<?= htmlspecialchars($perfil['nick']) ?></span>

                    <?php if ($ehProprioUsuario): ?>
                        <button class="modal-trash-btn" id="modal-trash-btn" onclick="deletarPost()"
                            aria-label="Excluir publicação" title="Excluir">
                            <img src="icon/trash.webp" alt="Excluir">
                        </button>
                    <?php endif; ?>
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
        const modalBackdrop = document.getElementById('modal-backdrop');
        const modalFoto = document.getElementById('modal-foto');
        const modalDescricao = document.getElementById('modal-descricao-texto');
        const modalData = document.getElementById('modal-data');

        // ID do post atualmente aberto no modal
        let postIdAtual = null;

        /**
         * Abre o modal com os dados do post clicado.
         * Os dados vêm dos atributos data-* do elemento do grid.
         *
         * @param {HTMLElement} el - O article.grid-item clicado
         */
        function abrirModal(el) {
            // Guarda o ID do post para uso no botão de exclusão
            postIdAtual = el.dataset.id;

            // Preenche o modal com os dados do post clicado
            modalFoto.src = el.dataset.foto;
            modalDescricao.textContent = el.dataset.descricao;
            modalData.textContent = el.dataset.data;

            // Guarda referência ao grid-item para removê-lo após exclusão
            modalBackdrop.dataset.gridItem = el;
            modalBackdrop._gridItem = el;

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
            postIdAtual = null;
        }

        /**
         * Envia requisição para excluir o post atual.
         * Confirma com o usuário antes de executar.
         */
        function deletarPost() {
            if (!postIdAtual) return;
            if (!confirm('Tem certeza que deseja excluir esta publicação? Esta ação não pode ser desfeita.')) return;

            const formData = new FormData();
            formData.append('post_id', postIdAtual);

            fetch('api/deletar_post.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(dados => {
                    if (dados.sucesso) {
                        // Remove o item do grid visualmente
                        if (modalBackdrop._gridItem) {
                            modalBackdrop._gridItem.remove();
                        }
                        fecharModal();
                    } else {
                        alert('Erro ao excluir: ' + (dados.erro || 'Tente novamente.'));
                    }
                })
                .catch(() => alert('Erro de conexão. Tente novamente.'));
        }

        /**
         * Fecha o modal ao clicar no backdrop (fora da caixa)
         */
        modalBackdrop.addEventListener('click', function (e) {
            if (e.target === modalBackdrop) fecharModal();
        });

        /**
         * Fecha o modal ao pressionar a tecla Escape
         */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') fecharModal();
        });
    </script>

</body>

</html>