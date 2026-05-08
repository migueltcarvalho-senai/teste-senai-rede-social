<?php
// Inicia configurações (sessão + banco + constantes)
require_once __DIR__ . '/../config/app.php';

// Só usuários logados podem excluir
exigirLogin();

header('Content-Type: application/json');

// Valida o ID do post recebido
$postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

if ($postId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
    exit;
}

$idUsuario = $_SESSION['usuario_id'];

try {
    // Busca o post garantindo que pertence ao usuário logado (segurança)
    $stmt = $pdo->prepare(
        "SELECT id, caminho_foto FROM post WHERE id = ? AND id_user = ? LIMIT 1"
    );
    $stmt->execute([$postId, $idUsuario]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode(['sucesso' => false, 'erro' => 'Post não encontrado ou sem permissão.']);
        exit;
    }

    // Remove o arquivo de imagem do servidor
    $caminhoFisico = __DIR__ . '/../' . $post['caminho_foto'];
    if (file_exists($caminhoFisico)) {
        unlink($caminhoFisico);
    }

    // Deleta o registro do banco de dados
    $del = $pdo->prepare("DELETE FROM post WHERE id = ? AND id_user = ?");
    $del->execute([$postId, $idUsuario]);

    echo json_encode(['sucesso' => true]);

} catch (PDOException $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao excluir: ' . $e->getMessage()]);
}
?>
