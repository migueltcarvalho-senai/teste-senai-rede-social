<?php
// Inicia configurações da aplicação (sessão + banco + constantes)
require_once __DIR__ . '/../config/app.php';

// Pega os dados enviados via POST em formato JSON
$dados = json_decode(file_get_contents('php://input'), true);

// Se não recebeu o token, retorna erro
if (empty($dados['token'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Token não recebido.']);
    exit;
}

$token = $dados['token'];

/**
 * Decodifica o JWT do Google sem verificar a assinatura (modo protótipo).
 * Em produção, use a biblioteca google/apiclient para verificação completa.
 */
function decodificarJwtGoogle(string $token): ?array {
    // O JWT tem 3 partes separadas por ponto: header.payload.signature
    $partes = explode('.', $token);
    if (count($partes) !== 3) return null;

    // O payload é a segunda parte, codificado em base64url
    $payload = base64_decode(str_pad(
        strtr($partes[1], '-_', '+/'),
        strlen($partes[1]) % 4 === 0 ? strlen($partes[1]) : strlen($partes[1]) + (4 - strlen($partes[1]) % 4),
        '='
    ));

    return json_decode($payload, true);
}

// Decodifica o token para pegar os dados do usuário
$dadosGoogle = decodificarJwtGoogle($token);

// Verifica se conseguiu decodificar e se tem os campos necessários
if (!$dadosGoogle || empty($dadosGoogle['email']) || empty($dadosGoogle['name'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Token inválido.']);
    exit;
}

// Pega os dados do usuário do Google
$email = $dadosGoogle['email'];
$nome  = $dadosGoogle['name'];

// Gera um nick a partir do email (tudo antes do @)
$nick = explode('@', $email)[0];

try {
    // Verifica se o usuário já existe no banco pelo email
    $stmt = $pdo->prepare("SELECT id, nome, nick FROM usuario WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        // Usuário já existe: só atualiza a sessão
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_nick'] = $usuario['nick'];
    } else {
        // Usuário novo: cadastra no banco
        $stmt = $pdo->prepare(
            "INSERT INTO usuario (email, nome, nick, data_criacao) VALUES (?, ?, ?, CURDATE())"
        );
        $stmt->execute([$email, $nome, $nick]);

        // Pega o ID do usuário recém-criado
        $novoId = $pdo->lastInsertId();

        // Salva na sessão
        $_SESSION['usuario_id']   = $novoId;
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_nick'] = $nick;
    }

    // Retorna sucesso para o JavaScript da página de login
    echo json_encode(['sucesso' => true]);

} catch (PDOException $e) {
    // Erro de banco de dados
    echo json_encode(['sucesso' => false, 'erro' => 'Erro no banco: ' . $e->getMessage()]);
}
?>
