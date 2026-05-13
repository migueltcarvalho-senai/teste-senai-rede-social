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

    <!-- Link com o CSS -->
    <link rel="stylesheet" href="css/nova_postagem.css">
    <link rel="stylesheet" href="css/editor_imagem.css">

    <!-- Fabric.js via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.js"></script>

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
        <!-- ESTÁGIO 2: EDIÇÃO     -->
        <!-- ===================== -->
        <div class="estagio" id="estagio-edicao">
            <div class="editor-wrap">
                <canvas id="canvas-editor"></canvas>
            </div>
            
            <div class="filtros-container">
                <p class="filtros-titulo">Filtros</p>
                <div class="filtros-lista">
                    <!-- Filtro Normal: sem efeitos -->
                    <button class="btn-filtro ativo" data-filtro="normal">
                        <span class="icone-filtro">⊘</span>
                        Normal
                    </button>
                    <!-- Filtro Polaroid: tom frio, preset nativo -->
                    <button class="btn-filtro" data-filtro="polaroid">
                        <span class="icone-filtro">📷</span>
                        Polaroid
                    </button>
                    <!-- Filtro Sepia: tons castanhos clássicos -->
                    <button class="btn-filtro" data-filtro="sepia">
                        <span class="icone-filtro">🍂</span>
                        Sepia
                    </button>
                    <!-- Filtro Kodachrome: paleta vibrante e quente -->
                    <button class="btn-filtro" data-filtro="kodachrome">
                        <span class="icone-filtro">🎨</span>
                        Kodachrome
                    </button>
                    <!-- Filtro Contrast: realça contraste -->
                    <button class="btn-filtro" data-filtro="contrast">
                        <span class="icone-filtro">◑</span>
                        Contrast
                    </button>
                    <!-- Filtro Brightness: aumenta brilho -->
                    <button class="btn-filtro" data-filtro="brightness">
                        <span class="icone-filtro">☀️</span>
                        Brightness
                    </button>
                    <!-- Filtro Greyscale: preto e branco puro -->
                    <button class="btn-filtro" data-filtro="greyscale">
                        <span class="icone-filtro">⚫</span>
                        Greyscale
                    </button>
                    <!-- Filtro Brownie: tons quentes analógicos -->
                    <button class="btn-filtro" data-filtro="brownie">
                        <span class="icone-filtro">🤎</span>
                        Brownie
                    </button>
                    <!-- Filtro Vintage: desbotado e amarelado -->
                    <button class="btn-filtro" data-filtro="vintage">
                        <span class="icone-filtro">🎞️</span>
                        Vintage
                    </button>
                    <!-- Filtro Technicolor: cores vibrantes e saturadas -->
                    <button class="btn-filtro" data-filtro="technicolor">
                        <span class="icone-filtro">🌈</span>
                        Technicolor
                    </button>
                    <!-- Filtro Pixelate: efeito de pixel/mosaico -->
                    <button class="btn-filtro" data-filtro="pixelate">
                        <span class="icone-filtro">🟦</span>
                        Pixelate
                    </button>
                    <!-- Filtro Blur: desfoque suave e ajustável -->
                    <button class="btn-filtro" data-filtro="blur">
                        <span class="icone-filtro">💧</span>
                        Blur
                    </button>
                    <!-- Filtro Bloom: efeito de brilho/glow em áreas claras -->
                    <button class="btn-filtro" data-filtro="bloom">
                        <span class="icone-filtro">✨</span>
                        Bloom
                    </button>
                </div>
            </div>

            <button class="btn-avancar" id="btn-avancar-legenda">Avançar →</button>
            <!-- Volta para a câmera e descarta a imagem atual -->
            <button class="btn-voltar-camera" onclick="voltarParaCamera()">
                ← Voltar para câmera
            </button>
        </div>

        <!-- ===================== -->
        <!-- ESTÁGIO 3: LEGENDA    -->
        <!-- ===================== -->
        <div class="estagio" id="estagio-legenda">

            <!-- Preview da foto que o usuário tirou -->
            <img id="preview-foto" class="preview-legenda-foto" src="" alt="Foto capturada">

            <!-- Campo para escrever a legenda -->
            <textarea id="campo-legenda" class="campo-legenda" placeholder="Escreva uma legenda..."
                maxlength="255"></textarea>

            <!-- Mensagem de status (erro ou sucesso) -->
            <div class="status-msg" id="status-msg"></div>

            <!-- Botão para publicar -->
            <button class="btn-publicar" id="btn-publicar" onclick="publicarPost()">
                Compartilhar
            </button>

            <!-- Botão para voltar para a edição -->
            <button class="btn-voltar-camera" onclick="voltarParaEdicao()">
                ← Voltar para edição
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
        const videoCamera = document.getElementById('video-camera');
        const canvasFoto = document.getElementById('canvas-foto');
        const btnCapturar = document.getElementById('btn-capturar');
        const btnUsarFoto = document.getElementById('btn-usar-foto');
        const btnTirarOutra = document.getElementById('btn-tirar-outra');
        const cameraErro = document.getElementById('camera-erro');
        const cameraInstrucao = document.getElementById('camera-instrucao');
        const estagioCamera = document.getElementById('estagio-camera');
        const estagioLegenda = document.getElementById('estagio-legenda');
        const previewFoto = document.getElementById('preview-foto');
        const campoLegenda = document.getElementById('campo-legenda');
        const statusMsg = document.getElementById('status-msg');
        const btnPublicar = document.getElementById('btn-publicar');

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
                        width: { ideal: 720 },
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
            canvasFoto.width = videoCamera.videoWidth;
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
            btnCapturar.style.display = 'none';
            btnUsarFoto.style.display = 'block';
            btnTirarOutra.style.display = 'block';
        }

        /**
         * Vai para o estágio 2 (edição) com a foto capturada.
         */
        function usarFoto() {
            // Troca de estágio
            estagioCamera.classList.remove('ativo');
            document.getElementById('estagio-edicao').classList.add('ativo');

            // Inicia o editor com a foto capturada (em base64)
            if (typeof iniciarEditor === 'function') {
                iniciarEditor(fotoBase64);
            }
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
            btnCapturar.style.display = 'block';
            btnUsarFoto.style.display = 'none';
            btnTirarOutra.style.display = 'none';
        }

        /**
         * Volta do estágio 2 (edição) para o estágio 1 (câmera).
         */
        function voltarParaCamera() {
            document.getElementById('estagio-edicao').classList.remove('ativo');
            estagioCamera.classList.add('ativo');
        }

        /**
         * Volta do estágio 3 (legenda) para o estágio 2 (edição).
         */
        function voltarParaEdicao() {
            estagioLegenda.classList.remove('ativo');
            document.getElementById('estagio-edicao').classList.add('ativo');
        }

        /**
         * Envia a foto e a legenda para o servidor e redireciona para o feed.
         */
        function publicarPost() {
            const legenda = campoLegenda.value.trim();

            // Usa a foto com filtro (window.fotoBase64) definida pelo editor,
            // ou a foto bruta (fotoBase64) como fallback de segurança
            const fotoParaEnviar = window.fotoBase64 || fotoBase64;

            // Validações básicas
            if (!fotoParaEnviar) {
                mostrarStatus('Nenhuma foto capturada.', 'erro');
                return;
            }

            if (!legenda) {
                mostrarStatus('Escreva uma legenda antes de compartilhar.', 'erro');
                return;
            }

            // Desativa o botão durante o envio para evitar duplo clique
            btnPublicar.disabled = true;
            btnPublicar.textContent = 'Publicando...';

            // Monta os dados para enviar ao servidor
            const formData = new FormData();
            formData.append('foto_base64', fotoParaEnviar); // Usa a foto com filtro
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
                        btnPublicar.disabled = false;
                        btnPublicar.textContent = 'Compartilhar';
                    }
                })
                .catch(() => {
                    mostrarStatus('Erro de conexão. Tente novamente.', 'erro');
                    btnPublicar.disabled = false;
                    btnPublicar.textContent = 'Compartilhar';
                });
        }

        /**
         * Exibe uma mensagem de status (erro ou sucesso) na tela.
         * @param {string} mensagem - Texto a exibir
         * @param {string} tipo - 'erro' ou 'sucesso'
         */
        function mostrarStatus(mensagem, tipo) {
            statusMsg.textContent = mensagem;
            statusMsg.className = 'status-msg ' + tipo;
            statusMsg.style.display = 'block';
        }

        // ===== EVENTOS DOS BOTÕES =====

        // Captura a foto quando o usuário clica no botão circular
        btnCapturar.addEventListener('click', capturarFoto);

        // Usa a foto capturada para ir para o estágio de legenda
        btnUsarFoto.addEventListener('click', usarFoto);

        // Permite tirar outra foto
        btnTirarOutra.addEventListener('click', tirarOutraFoto);

        // Para a câmera quando o usuário sai da página (evita vazamento de memória)
        window.addEventListener('beforeunload', function () {
            if (streamCamera) {
                streamCamera.getTracks().forEach(track => track.stop());
            }
        });

        // Inicia a câmera assim que a página carrega
        iniciarCamera();
    </script>
    <script src="js/editor_imagem.js"></script>

</body>

</html>