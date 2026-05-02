<?php
// Inicia configurações (sessão + banco + constantes)
require_once __DIR__ . '/config/app.php';

// Garante que só usuários logados chegam até aqui
exigirLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InstaSenai – Nova Postagem</title>
    <meta name="description" content="Tire uma foto com sua câmera e compartilhe com sua turma no InstaSenai.">

    <!-- Fonte moderna -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            color: #262626;
        }

        /* ===== NAVBAR FIXA ===== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: #ffffff;
            border-bottom: 1px solid #dbdbdb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 54px;
        }

        .navbar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #262626;
        }

        .navbar a {
            font-size: 0.85rem;
            color: #8e8e8e;
            text-decoration: none;
        }

        .navbar a:hover {
            color: #262626;
        }

        /* ===== CONTAINER PRINCIPAL ===== */
        .container {
            padding-top: 70px;
            max-width: 470px;
            margin: 0 auto;
            padding-left: 16px;
            padding-right: 16px;
            padding-bottom: 40px;
        }

        /* ===== ESTÁGIOS DA POSTAGEM ===== */
        /* Estágio 1: câmera | Estágio 2: legenda */
        .estagio {
            display: none;
        }

        .estagio.ativo {
            display: block;
        }

        /* ===== ESTÁGIO 1: CÂMERA ===== */
        .camera-wrap {
            margin: 24px 0 16px;
            position: relative;
            background: #000;
            border-radius: 4px;
            overflow: hidden;
            /* Proporção quadrada como o Instagram */
            aspect-ratio: 1 / 1;
        }

        /* Preview da câmera ao vivo */
        #video-camera {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            /* Espelha a câmera frontal para ficar mais natural */
            transform: scaleX(-1);
        }

        /* Foto capturada (fica por cima do vídeo) */
        #canvas-foto {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none; /* Aparece só depois de capturar */
        }

        /* Botão de captura: círculo branco no centro inferior */
        .btn-capturar {
            display: block;
            margin: 0 auto 16px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: 4px solid #262626;
            background: #ffffff;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.1s ease;
            position: relative;
        }

        .btn-capturar::after {
            content: '';
            position: absolute;
            inset: 4px;
            border-radius: 50%;
            background: #262626;
            transition: background 0.15s ease;
        }

        .btn-capturar:hover::after {
            background: #555;
        }

        .btn-capturar:active {
            transform: scale(0.95);
        }

        /* Instruções abaixo da câmera */
        .camera-instrucao {
            text-align: center;
            font-size: 0.8rem;
            color: #8e8e8e;
            margin-bottom: 16px;
        }

        /* Botão para usar a foto capturada */
        .btn-usar-foto {
            display: none; /* Aparece só depois de capturar */
            width: 100%;
            padding: 12px;
            background: #262626;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
            margin-bottom: 12px;
        }

        .btn-usar-foto:hover {
            background: #444;
        }

        /* Botão para tirar outra foto */
        .btn-tirar-outra {
            display: none; /* Aparece só depois de capturar */
            width: 100%;
            padding: 12px;
            background: transparent;
            color: #262626;
            border: 1px solid #dbdbdb;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .btn-tirar-outra:hover {
            background: #f5f5f5;
        }

        /* Mensagem de erro se a câmera não abrir */
        .camera-erro {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 16px;
            font-size: 0.875rem;
            color: #856404;
            margin: 24px 0;
            text-align: center;
            display: none;
        }

        /* ===== ESTÁGIO 2: LEGENDA ===== */
        /* Preview da foto capturada no estágio 2 */
        .preview-legenda-foto {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 4px;
            display: block;
            margin: 24px 0 16px;
            background: #f0f0f0;
        }

        /* Campo de texto da legenda */
        .campo-legenda {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #dbdbdb;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            color: #262626;
            resize: vertical;
            margin-bottom: 16px;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .campo-legenda:focus {
            border-color: #a0a0a0;
        }

        .campo-legenda::placeholder {
            color: #c7c7c7;
        }

        /* Botão de publicar */
        .btn-publicar {
            width: 100%;
            padding: 12px;
            background: #0095f6; /* Azul do Instagram */
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
            margin-bottom: 12px;
        }

        .btn-publicar:hover {
            background: #1aa1f8;
        }

        .btn-publicar:disabled {
            background: #b3d9fc;
            cursor: not-allowed;
        }

        /* Botão de voltar para câmera */
        .btn-voltar-camera {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: #262626;
            border: 1px solid #dbdbdb;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .btn-voltar-camera:hover {
            background: #f5f5f5;
        }

        /* Mensagem de status ao publicar */
        .status-msg {
            text-align: center;
            font-size: 0.85rem;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 12px;
            display: none;
        }

        .status-msg.erro {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .status-msg.sucesso {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <span class="navbar-title">Nova postagem</span>
        <a href="index.php">Cancelar</a>
    </nav>

    <div class="container">

        <!-- ===================== -->
        <!-- ESTÁGIO 1: CÂMERA     -->
        <!-- ===================== -->
        <div class="estagio ativo" id="estagio-camera">

            <!-- Aviso de erro se a câmera não abrir -->
            <div class="camera-erro" id="camera-erro">
                Não foi possível acessar a câmera.<br>
                Verifique as permissões do navegador e tente novamente.
            </div>

            <!-- Área da câmera: vídeo ao vivo + canvas da foto capturada -->
            <div class="camera-wrap">
                <video id="video-camera" autoplay playsinline></video>
                <canvas id="canvas-foto"></canvas>
            </div>

            <p class="camera-instrucao" id="camera-instrucao">
                Clique no botão abaixo para capturar a foto
            </p>

            <!-- Botão de captura (círculo) -->
            <button class="btn-capturar" id="btn-capturar" title="Capturar foto"></button>

            <!-- Botões que aparecem após a captura -->
            <button class="btn-usar-foto" id="btn-usar-foto">Usar esta foto →</button>
            <button class="btn-tirar-outra" id="btn-tirar-outra">Tirar outra foto</button>

        </div>

        <!-- ===================== -->
        <!-- ESTÁGIO 2: LEGENDA    -->
        <!-- ===================== -->
        <div class="estagio" id="estagio-legenda">

            <!-- Preview da foto que o usuário tirou -->
            <img id="preview-foto" class="preview-legenda-foto" src="" alt="Foto capturada">

            <!-- Campo para escrever a legenda -->
            <textarea
                id="campo-legenda"
                class="campo-legenda"
                placeholder="Escreva uma legenda..."
                maxlength="255"
            ></textarea>

            <!-- Mensagem de status (erro ou sucesso) -->
            <div class="status-msg" id="status-msg"></div>

            <!-- Botão para publicar -->
            <button class="btn-publicar" id="btn-publicar" onclick="publicarPost()">
                Compartilhar
            </button>

            <!-- Botão para voltar e tirar outra foto -->
            <button class="btn-voltar-camera" onclick="voltarParaCamera()">
                ← Voltar para câmera
            </button>

        </div>

    </div><!-- fim .container -->

    <script>
        /**
         * ============================================================
         * CONTROLE DA CÂMERA E PUBLICAÇÃO DE POSTS
         * ============================================================
         */

        // Referências aos elementos do DOM que vamos usar
        const videoCamera    = document.getElementById('video-camera');
        const canvasFoto     = document.getElementById('canvas-foto');
        const btnCapturar    = document.getElementById('btn-capturar');
        const btnUsarFoto    = document.getElementById('btn-usar-foto');
        const btnTirarOutra  = document.getElementById('btn-tirar-outra');
        const cameraErro     = document.getElementById('camera-erro');
        const cameraInstrucao = document.getElementById('camera-instrucao');
        const estagioCamera  = document.getElementById('estagio-camera');
        const estagioLegenda = document.getElementById('estagio-legenda');
        const previewFoto    = document.getElementById('preview-foto');
        const campoLegenda   = document.getElementById('campo-legenda');
        const statusMsg      = document.getElementById('status-msg');
        const btnPublicar    = document.getElementById('btn-publicar');

        // Guarda o stream da câmera para poder parar depois
        let streamCamera = null;

        // Guarda a foto em base64 para enviar ao servidor
        let fotoBase64 = null;

        /**
         * Inicia a câmera assim que a página carrega.
         * Usa mediaDevices.getUserMedia para acessar a câmera do navegador.
         */
        async function iniciarCamera() {
            try {
                // Solicita acesso à câmera (prefere câmera frontal, mas aceita qualquer uma)
                streamCamera = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user', // Câmera frontal
                        width:  { ideal: 720 },
                        height: { ideal: 720 }
                    },
                    audio: false // Não precisamos de áudio
                });

                // Conecta o stream ao elemento de vídeo
                videoCamera.srcObject = streamCamera;

            } catch (erro) {
                // Câmera negada ou não disponível: mostra a mensagem de erro
                console.error('Erro ao acessar câmera:', erro);
                cameraErro.style.display = 'block';
                document.querySelector('.camera-wrap').style.display = 'none';
                cameraInstrucao.style.display = 'none';
                btnCapturar.style.display = 'none';
            }
        }

        /**
         * Captura o frame atual do vídeo e transforma em imagem estática.
         * Usa o elemento <canvas> para "tirar uma foto" do vídeo.
         */
        function capturarFoto() {
            // Define o tamanho do canvas igual ao vídeo
            canvasFoto.width  = videoCamera.videoWidth;
            canvasFoto.height = videoCamera.videoHeight;

            const ctx = canvasFoto.getContext('2d');

            // Como o vídeo está espelhado (scaleX(-1) no CSS),
            // aplicamos o mesmo espelhamento no canvas
            ctx.save();
            ctx.scale(-1, 1);
            ctx.drawImage(videoCamera, -canvasFoto.width, 0, canvasFoto.width, canvasFoto.height);
            ctx.restore();

            // Converte o canvas para base64 (JPG, qualidade 90%)
            fotoBase64 = canvasFoto.toDataURL('image/jpeg', 0.9);

            // Mostra o canvas por cima do vídeo (exibe a foto capturada)
            canvasFoto.style.display = 'block';
            canvasFoto.style.position = 'absolute';
            canvasFoto.style.top = '0';
            canvasFoto.style.left = '0';
            canvasFoto.style.width = '100%';
            canvasFoto.style.height = '100%';
            canvasFoto.style.objectFit = 'cover';

            // Mostra os botões de ação e atualiza instrução
            cameraInstrucao.textContent = 'Gostou? Use esta foto ou tire outra.';
            btnCapturar.style.display   = 'none';
            btnUsarFoto.style.display   = 'block';
            btnTirarOutra.style.display = 'block';
        }

        /**
         * Vai para o estágio 2 (legenda) com a foto capturada.
         */
        function usarFoto() {
            // Coloca a foto capturada no preview do estágio 2
            previewFoto.src = fotoBase64;

            // Troca de estágio
            estagioCamera.classList.remove('ativo');
            estagioLegenda.classList.add('ativo');

            // Foca no campo de legenda para facilitar a digitação
            campoLegenda.focus();
        }

        /**
         * Volta para a câmera para tirar outra foto.
         * Limpa o canvas e restaura o estado inicial.
         */
        function tirarOutraFoto() {
            // Limpa o canvas
            const ctx = canvasFoto.getContext('2d');
            ctx.clearRect(0, 0, canvasFoto.width, canvasFoto.height);
            canvasFoto.style.display = 'none';

            // Limpa a foto salva
            fotoBase64 = null;

            // Restaura os botões e instrução
            cameraInstrucao.textContent = 'Clique no botão abaixo para capturar a foto';
            btnCapturar.style.display   = 'block';
            btnUsarFoto.style.display   = 'none';
            btnTirarOutra.style.display = 'none';
        }

        /**
         * Volta do estágio 2 (legenda) para o estágio 1 (câmera).
         */
        function voltarParaCamera() {
            estagioLegenda.classList.remove('ativo');
            estagioCamera.classList.add('ativo');
        }

        /**
         * Envia a foto e a legenda para o servidor e redireciona para o feed.
         */
        function publicarPost() {
            const legenda = campoLegenda.value.trim();

            // Validações básicas
            if (!fotoBase64) {
                mostrarStatus('Nenhuma foto capturada.', 'erro');
                return;
            }

            if (!legenda) {
                mostrarStatus('Escreva uma legenda antes de compartilhar.', 'erro');
                return;
            }

            // Desativa o botão durante o envio para evitar duplo clique
            btnPublicar.disabled     = true;
            btnPublicar.textContent  = 'Publicando...';

            // Monta os dados para enviar ao servidor
            const formData = new FormData();
            formData.append('foto_base64', fotoBase64);
            formData.append('descricao', legenda);

            // Envia para o endpoint de salvar post
            fetch('api/salvar_post.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(dados => {
                if (dados.sucesso) {
                    // Sucesso: redireciona para o feed
                    mostrarStatus('Post publicado! Redirecionando...', 'sucesso');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1200);
                } else {
                    // Erro: mostra a mensagem e reativa o botão
                    mostrarStatus(dados.erro || 'Erro ao publicar. Tente novamente.', 'erro');
                    btnPublicar.disabled    = false;
                    btnPublicar.textContent = 'Compartilhar';
                }
            })
            .catch(() => {
                mostrarStatus('Erro de conexão. Tente novamente.', 'erro');
                btnPublicar.disabled    = false;
                btnPublicar.textContent = 'Compartilhar';
            });
        }

        /**
         * Exibe uma mensagem de status (erro ou sucesso) na tela.
         * @param {string} mensagem - Texto a exibir
         * @param {string} tipo - 'erro' ou 'sucesso'
         */
        function mostrarStatus(mensagem, tipo) {
            statusMsg.textContent    = mensagem;
            statusMsg.className      = 'status-msg ' + tipo;
            statusMsg.style.display  = 'block';
        }

        // ===== EVENTOS DOS BOTÕES =====

        // Captura a foto quando o usuário clica no botão circular
        btnCapturar.addEventListener('click', capturarFoto);

        // Usa a foto capturada para ir para o estágio de legenda
        btnUsarFoto.addEventListener('click', usarFoto);

        // Permite tirar outra foto
        btnTirarOutra.addEventListener('click', tirarOutraFoto);

        // Para a câmera quando o usuário sai da página (evita vazamento de memória)
        window.addEventListener('beforeunload', function() {
            if (streamCamera) {
                streamCamera.getTracks().forEach(track => track.stop());
            }
        });

        // Inicia a câmera assim que a página carrega
        iniciarCamera();
    </script>

</body>
</html>
