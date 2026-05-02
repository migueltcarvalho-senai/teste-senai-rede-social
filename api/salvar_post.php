<?php
// Inicia configurações (sessão + banco + constantes)
require_once __DIR__ . '/../config/app.php';

// Garante que só usuários logados chegam até aqui
exigirLogin();

// Verifica se os dados foram enviados corretamente
if (empty($_POST['foto_base64']) || empty($_POST['descricao'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos.']);
    exit;
}

// Pega os dados do formulário
$fotoBase64 = $_POST['foto_base64'];
$descricao  = trim($_POST['descricao']);
$idUsuario  = $_SESSION['usuario_id'];

// Limita a descrição a 255 caracteres (limite da coluna no banco)
$descricao = mb_substr($descricao, 0, 255);

// Define a pasta onde as fotos serão salvas
$pastaUploads = __DIR__ . '/../uploads/';

// Cria a pasta se ela não existir
if (!is_dir($pastaUploads)) {
    mkdir($pastaUploads, 0755, true);
}

/**
 * Converte a imagem base64 (vinda do canvas do navegador) em arquivo JPG
 * e salva no servidor.
 */

// Remove o prefixo "data:image/png;base64," ou "data:image/jpeg;base64,"
$imagemBase64Limpa = preg_replace('/^data:image\/\w+;base64,/', '', $fotoBase64);

// Decodifica de base64 para binário
$imagemBinaria = base64_decode($imagemBase64Limpa);

// Gera um nome único para o arquivo usando timestamp + ID do usuário
$nomeArquivo = 'post_' . $idUsuario . '_' . time() . '.jpg';
$caminhoCompleto = $pastaUploads . $nomeArquivo;

// Tenta salvar o arquivo no servidor
if (!file_put_contents($caminhoCompleto, $imagemBinaria)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Falha ao salvar a imagem.']);
    exit;
}

// Caminho relativo para salvar no banco (relativo à raiz do projeto)
$caminhoRelativo = 'uploads/' . $nomeArquivo;

try {
    // Salva o post no banco de dados com a foto e a legenda
    $stmt = $pdo->prepare(
        "INSERT INTO post (id_user, caminho_foto, descricao, data_criacao) VALUES (?, ?, ?, CURDATE())"
    );
    $stmt->execute([$idUsuario, $caminhoRelativo, $descricao]);

    // Retorna sucesso para o JavaScript redirecionar
    echo json_encode(['sucesso' => true]);

} catch (PDOException $e) {
    // Se der erro no banco, remove a foto que foi salva (evita arquivos órfãos)
    unlink($caminhoCompleto);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar no banco: ' . $e->getMessage()]);
}
?>
