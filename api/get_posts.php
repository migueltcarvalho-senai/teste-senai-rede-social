<?php
// Inicia configurações (sessão + banco + constantes)
require_once __DIR__ . '/../config/app.php';

// Parâmetros de paginação recebidos via GET
// Página atual (começa em 1)
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;

// Quantos posts mostrar por carregamento
$porPagina = 5;

// Calcula quantos posts pular (OFFSET do SQL)
$offset = ($pagina - 1) * $porPagina;

try {
    /**
     * Busca os posts mais recentes com o nome do usuário que postou.
     * Usa JOIN para pegar os dados do usuário junto com o post.
     * LIMIT e OFFSET controlam a paginação.
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
        LIMIT :limite OFFSET :offset
    ");

    // Bindeia os valores como inteiro para evitar injeção SQL
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $posts = $stmt->fetchAll();

    // Verifica se tem mais posts após esta página
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM post");
    $total      = (int)$stmtTotal->fetchColumn();
    $temMais    = ($pagina * $porPagina) < $total;

    // Monta o HTML dos posts para retornar ao JavaScript
    $html = '';

    foreach ($posts as $post) {
        // Formata a data no padrão brasileiro
        $dataFormatada = date('d/m/Y', strtotime($post['data_criacao']));

        // Cada post é um bloco de HTML com foto, nome do usuário e legenda
        $html .= '
        <article class="post-card">
            <div class="post-header">
                <!-- Avatar clicável leva ao perfil do autor -->
                <a href="perfil.php?nick=' . urlencode($post['nick_usuario']) . '" style="text-decoration:none;">
                    <div class="post-avatar">' . strtoupper(substr($post['nick_usuario'], 0, 1)) . '</div>
                </a>
                <div class="post-user-info">
                    <!-- Nick clicável leva ao perfil do autor -->
                    <a href="perfil.php?nick=' . urlencode($post['nick_usuario']) . '" class="post-username" style="text-decoration:none;color:#262626;">@' . htmlspecialchars($post['nick_usuario']) . '</a>
                    <span class="post-date">' . $dataFormatada . '</span>
                </div>
            </div>
            <div class="post-image-wrap">
                <img
                    src="' . htmlspecialchars($post['caminho_foto']) . '"
                    alt="Foto de ' . htmlspecialchars($post['nome_usuario']) . '"
                    class="post-image"
                    loading="lazy"
                >
            </div>
            <div class="post-body">
                <p class="post-caption">
                    <!-- Nick na legenda também é clicável -->
                    <a href="perfil.php?nick=' . urlencode($post['nick_usuario']) . '" style="font-weight:600;text-decoration:none;color:#262626;">@' . htmlspecialchars($post['nick_usuario']) . '</a>
                    ' . htmlspecialchars($post['descricao']) . '
                </p>
            </div>
        </article>';
    }

    // Retorna o HTML dos posts + indicador se tem mais
    header('Content-Type: application/json');
    echo json_encode([
        'html'    => $html,
        'temMais' => $temMais,
        'total'   => $total
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Erro ao buscar posts: ' . $e->getMessage()]);
}
?>
