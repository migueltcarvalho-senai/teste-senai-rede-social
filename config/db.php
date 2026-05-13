<?php
// Configurações de conexão com o banco de dados MySQL (XAMPP padrão)
$host = '127.0.0.1';
$dbname = 'instasenaidb';
$username = 'root';
$password = ''; // XAMPP não tem senha por padrão
$port = "3307";
try {
    // Cria a conexão usando PDO para mais segurança e flexibilidade
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);

    // Define que erros do PDO vão lançar exceções (facilita o debug)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Retorna objetos como array associativo por padrão
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Se der erro de conexão, para tudo e mostra a mensagem
    die(json_encode(['erro' => 'Falha ao conectar no banco: ' . $e->getMessage()]));
}
?>