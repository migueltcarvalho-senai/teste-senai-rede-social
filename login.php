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

        /* Container do botão renderizado pelo SDK do Google */
        .google-btn-wrap {
            display: flex;
            justify-content: center;
            width: 100%;
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

        <!-- Container onde o SDK do Google vai renderizar o botão oficial -->
        <!-- Usando renderButton() em vez de prompt() para evitar o erro de FedCM -->
        <div class="google-btn-wrap">
            <div id="google-btn-container"></div>
        </div>

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
         * Aguarda o SDK do Google carregar e então renderiza o botão oficial.
         *
         * Por que renderButton() e não prompt()?
         * O prompt() depende do FedCM (Federated Credential Management), que o
         * Chrome pode bloquear em localhost ou após o usuário dispensar o popup.
         * O renderButton() usa o fluxo OAuth padrão via popup e NÃO depende do FedCM.
         */
        window.onload = function () {
            google.accounts.id.initialize({
                client_id: '<?= GOOGLE_CLIENT_ID ?>',
                callback: receberTokenGoogle,
                // Garante que o fluxo seja via popup, sem depender do FedCM
                ux_mode: 'popup'
            });

            // Renderiza o botão oficial dentro do container definido no HTML
            google.accounts.id.renderButton(
                document.getElementById('google-btn-container'),
                {
                    type:  'standard',   // botão com texto
                    theme: 'outline',    // borda cinza (combina com o design)
                    size:  'large',      // altura confortável
                    width: 280,          // largura fixa em pixels
                    text:  'signin_with' // texto: "Fazer login com o Google"
                }
            );
        };

        /**
         * Recebe o token JWT do Google após autenticação bem-sucedida.
         * Envia para o backend PHP criar/atualizar a sessão do usuário.
         *
         * @param {Object} resposta - { credential: "<JWT>" }
         */
        function receberTokenGoogle(resposta) {
            fetch('auth/google_callback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: resposta.credential })
            })
            .then(res => res.json())
            .then(dados => {
                if (dados.sucesso) {
                    window.location.href = 'index.php';
                } else {
                    const erroEl = document.getElementById('erro-msg');
                    erroEl.textContent  = dados.erro || 'Erro ao autenticar.';
                    erroEl.style.display = 'block';
                }
            })
            .catch(() => {
                document.getElementById('erro-msg').style.display = 'block';
            });
        }
    </script>

</body>
</html>
