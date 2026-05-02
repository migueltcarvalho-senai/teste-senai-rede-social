<?php
// Inicia configurações da aplicação (sessão + banco + constantes)
require_once __DIR__ . '/config/app.php';

// Se o usuário já está logado, manda direto pro feed
if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InstaSenai – Entrar</title>
    <meta name="description" content="Entre no InstaSenai com sua conta Google para compartilhar fotos com sua turma.">

    <!-- Fonte moderna do Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- SDK do Google Identity Services (login com Google) -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #262626;
        }

        /* ===== CARD DE LOGIN ===== */
        .login-card {
            background: #ffffff;
            border: 1px solid #dbdbdb;
            border-radius: 4px;
            padding: 40px 40px 32px;
            width: 100%;
            max-width: 380px;
            text-align: center;
        }

        /* Logo / Nome do app */
        .login-logo {
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: -1px;
            margin-bottom: 32px;
            color: #262626;
        }

        /* Linha divisória "ou" */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: #8e8e8e;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #dbdbdb;
        }

        /* Texto de boas-vindas */
        .login-subtitle {
            color: #8e8e8e;
            font-size: 0.875rem;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        /* Botão do Google */
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 12px 16px;
            background: #ffffff;
            border: 1px solid #dbdbdb;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            color: #262626;
            cursor: pointer;
            transition: background 0.15s ease, box-shadow 0.15s ease;
        }

        .btn-google:hover {
            background: #f5f5f5;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }

        /* Ícone SVG do Google */
        .btn-google svg {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
        }

        /* Mensagem de erro caso algo dê errado */
        .erro-msg {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 10px;
            font-size: 0.85rem;
            color: #856404;
            margin-top: 16px;
            display: none;
        }

        /* Rodapé do card */
        .login-footer {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #dbdbdb;
            font-size: 0.8rem;
            color: #8e8e8e;
        }
    </style>
</head>
<body>

    <div class="login-card">

        <!-- Nome do aplicativo -->
        <h1 class="login-logo">InstaSenai</h1>

        <!-- Subtítulo -->
        <p class="login-subtitle">
            Entre com sua conta Google para compartilhar fotos com sua turma.
        </p>

        <div class="divider">entrar com</div>

        <!-- Botão de login com Google -->
        <button class="btn-google" id="btn-google-login" onclick="iniciarLoginGoogle()">
            <!-- Ícone oficial do Google em SVG -->
            <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                <path fill="none" d="M0 0h48v48H0z"/>
            </svg>
            Entrar com Google
        </button>

        <!-- Área de erro (aparece se o login falhar) -->
        <div class="erro-msg" id="erro-msg">
            Não foi possível realizar o login. Tente novamente.
        </div>

        <div class="login-footer">
            Protótipo Senai &bull; Somente para uso interno
        </div>

    </div>

    <script>
        /**
         * Inicializa o Google Identity Services assim que o SDK carregar.
         * O callback "receberTokenGoogle" é chamado automaticamente quando o usuário autentica.
         */
        window.onload = function() {
            google.accounts.id.initialize({
                client_id: '<?= GOOGLE_CLIENT_ID ?>',
                callback: receberTokenGoogle
            });
        };

        /**
         * Dispara o popup de login do Google quando o usuário clica no botão.
         */
        function iniciarLoginGoogle() {
            google.accounts.id.prompt(); // Abre o popup do Google
        }

        /**
         * Recebe a resposta do Google após autenticação bem-sucedida.
         * Envia o token JWT para o backend PHP validar e criar a sessão.
         * @param {Object} resposta - Objeto com o credential (JWT) do Google
         */
        function receberTokenGoogle(resposta) {
            // Envia o token para o backend via POST
            fetch('auth/google_callback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: resposta.credential })
            })
            .then(res => res.json())
            .then(dados => {
                if (dados.sucesso) {
                    // Login bem-sucedido: redireciona para o feed
                    window.location.href = 'index.php';
                } else {
                    // Mostra mensagem de erro
                    document.getElementById('erro-msg').style.display = 'block';
                    document.getElementById('erro-msg').textContent = dados.erro || 'Erro ao autenticar.';
                }
            })
            .catch(() => {
                document.getElementById('erro-msg').style.display = 'block';
            });
        }
    </script>

</body>
</html>
