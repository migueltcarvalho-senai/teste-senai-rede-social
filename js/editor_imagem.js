/**
 * ============================================================
 * EDITOR DE IMAGENS COM FABRIC.JS (v5.3.1)
 * ============================================================
 *
 * ARQUITETURA (inspirada em princípios de estado imutável):
 *
 *  stackFiltros (Map)  ← fonte de verdade
 *       │
 *       ├── Alimenta: _renderizarStack() → atualiza canvas
 *       └── Alimenta: _renderizarPainelSliders() → atualiza UI
 *
 * O usuário interage com os botões/sliders → atualiza stackFiltros
 * → dispara _renderizarStack() + _renderizarPainelSliders()
 *
 * Filtros suportados:
 *  - Preset (sem params): Polaroid, Sepia, Kodachrome, Greyscale,
 *    Brownie, Vintage, Technicolor
 *  - Paramétricos (com slider): Contrast, Brightness, Pixelate,
 *    Blur, Bloom
 *
 * Blur: nativo Fabric.js (fabric.Image.filters.Blur)
 * Bloom: composto por Brightness + Blur + Contrast (efeito de brilho)
 */

// ─── Estado central ──────────────────────────────────────────
/**
 * stackFiltros é um Map que armazena os filtros ativos e seus parâmetros.
 * Chave: filtroId (string) | Valor: objeto de parâmetros { paramId: valor }
 * Exemplo: Map { 'contrast' → { contrast: 0.5 }, 'vintage' → {} }
 */
const stackFiltros = new Map();

// ─── Estado interno do módulo ────────────────────────────────
let canvasFabric = null; // Instância do canvas Fabric.js
let imagemAtual  = null; // Referência ao objeto fabric.Image no canvas

// ─── Referências ao DOM ──────────────────────────────────────
const estagioEdicao     = document.getElementById('estagio-edicao');
const wrapEditor        = document.querySelector('.editor-wrap');
const btnAvancarLegenda = document.getElementById('btn-avancar-legenda');
const botoesFiltro      = document.querySelectorAll('.btn-filtro');

// ─── Configuração dos filtros ─────────────────────────────────
/**
 * FILTROS_CONFIG: define cada filtro disponível.
 *
 * Campos:
 *  - label:     Nome amigável exibido no painel de sliders
 *  - filtros(params): função que recebe { paramId: valor } e retorna
 *    array de instâncias fabric.IBaseFilter
 *  - controles: array de definições de slider, ou null para presets
 *    Cada item: { id, label, min, max, step, default }
 */
const FILTROS_CONFIG = {

    // ── Presets (sem parâmetros ajustáveis) ─────────────────

    polaroid: {
        label: 'Polaroid',
        filtros: () => [new fabric.Image.filters.Polaroid()],
        controles: null
    },
    sepia: {
        label: 'Sepia',
        filtros: () => [new fabric.Image.filters.Sepia()],
        controles: null
    },
    kodachrome: {
        label: 'Kodachrome',
        filtros: () => [new fabric.Image.filters.Kodachrome()],
        controles: null
    },
    greyscale: {
        label: 'Greyscale',
        filtros: () => [new fabric.Image.filters.Grayscale()],
        controles: null
    },
    brownie: {
        label: 'Brownie',
        filtros: () => [new fabric.Image.filters.Brownie()],
        controles: null
    },
    vintage: {
        label: 'Vintage',
        filtros: () => [new fabric.Image.filters.Vintage()],
        controles: null
    },
    technicolor: {
        label: 'Technicolor',
        filtros: () => [new fabric.Image.filters.Technicolor()],
        controles: null
    },

    // ── Paramétricos (com sliders de controle) ───────────────

    contrast: {
        label: 'Contraste',
        // Contraste: -1 = mínimo, 0 = neutro, +1 = máximo
        filtros: (p) => [
            new fabric.Image.filters.Contrast({ contrast: p.contrast })
        ],
        controles: [
            { id: 'contrast', label: 'Intensidade', min: -1, max: 1, step: 0.01, default: 0.3 }
        ]
    },

    brightness: {
        label: 'Brilho',
        // Brilho: -1 = escuro, 0 = neutro, +1 = claro
        filtros: (p) => [
            new fabric.Image.filters.Brightness({ brightness: p.brightness })
        ],
        controles: [
            { id: 'brightness', label: 'Intensidade', min: -1, max: 1, step: 0.01, default: 0.2 }
        ]
    },

    pixelate: {
        label: 'Pixelate',
        // blocksize: 2 = sutil, 50 = extremo (deve ser inteiro)
        filtros: (p) => [
            new fabric.Image.filters.Pixelate({ blocksize: Math.round(p.blocksize) })
        ],
        controles: [
            { id: 'blocksize', label: 'Tamanho do Pixel', min: 2, max: 50, step: 1, default: 6 }
        ]
    },

    blur: {
        label: 'Blur',
        // blur: 0 = nítido, 1 = máximo desfoque
        filtros: (p) => [
            new fabric.Image.filters.Blur({ blur: p.blur })
        ],
        controles: [
            { id: 'blur', label: 'Intensidade', min: 0, max: 1, step: 0.01, default: 0.2 }
        ]
    },

    bloom: {
        label: 'Bloom',
        /**
         * Bloom (efeito de brilho/glow) composto por 3 filtros nativos:
         *  1. Brightness: empurra as áreas claras para cima
         *  2. Blur: suaviza as bordas criando o halo de luz
         *  3. Contrast: reforça a separação entre luzes e sombras
         * O parâmetro `intensity` escala proporcionalmente os três.
         */
        filtros: (p) => [
            new fabric.Image.filters.Brightness({ brightness: p.intensity * 0.4  }),
            new fabric.Image.filters.Blur({       blur:       p.intensity * 0.12  }),
            new fabric.Image.filters.Contrast({   contrast:   p.intensity * 0.2   })
        ],
        controles: [
            { id: 'intensity', label: 'Intensidade', min: 0, max: 1, step: 0.01, default: 0.4 }
        ]
    }
};

// ─── Inicialização do editor ─────────────────────────────────

/**
 * Inicializa o editor com a foto capturada.
 * Chamada pelo nova_postagem.php quando o usuário confirma a foto.
 * @param {string} base64Image - String base64 da foto capturada
 */
function iniciarEditor(base64Image) {
    const largura = wrapEditor.clientWidth || 320;

    // Cria o canvas Fabric apenas uma vez; reutiliza nas visitas subsequentes
    if (!canvasFabric) {
        canvasFabric = new fabric.Canvas('canvas-editor', {
            width:               largura,
            height:              largura,
            selection:           false,
            allowTouchScrolling: true
        });

        // Cria o painel de sliders no DOM (apenas uma vez)
        _criarPainelSliders();
    } else {
        canvasFabric.setWidth(largura);
        canvasFabric.setHeight(largura);
    }

    // Carrega a imagem base64 no canvas Fabric
    fabric.Image.fromURL(base64Image, function (img) {
        const escala = largura / Math.max(img.width, 1);
        img.set({
            scaleX:     escala,
            scaleY:     escala,
            left:       0,
            top:        0,
            selectable: false, // Usuário não pode mover a imagem
            evented:    false  // Sem eventos de clique na imagem
        });

        canvasFabric.clear();
        canvasFabric.add(img);
        canvasFabric.renderAll();

        imagemAtual = img;

        // Limpa o stack e reseta a UI ao carregar nova foto
        stackFiltros.clear();
        _sincronizarTudo();
    });
}

// ─── Engine de aplicação de filtros ─────────────────────────

/**
 * Lê o stackFiltros e aplica TODOS os filtros acumulados na imagem.
 * É a única função que escreve nos filtros do Fabric.js.
 * Sempre deve ser chamada após qualquer mutação no stackFiltros.
 */
function _renderizarStack() {
    if (!imagemAtual) return;

    // Coleta todas as instâncias de filtro de todos os filtros ativos
    const todosFiltros = [];
    stackFiltros.forEach((params, filtroId) => {
        const config = FILTROS_CONFIG[filtroId];
        if (!config) return;
        const instancias = config.filtros(params);
        todosFiltros.push(...instancias);
    });

    // Aplica o array completo no canvas Fabric
    imagemAtual.filters = todosFiltros;
    imagemAtual.applyFilters();
    canvasFabric.renderAll();
}

// ─── Painel de sliders ────────────────────────────────────────

/**
 * Cria o elemento container do painel no DOM.
 * Chamado apenas uma vez na inicialização do canvas.
 */
function _criarPainelSliders() {
    // Remove painel anterior se existir (segurança para reinicialização)
    const anterior = document.getElementById('painel-sliders');
    if (anterior) anterior.remove();

    const painel = document.createElement('div');
    painel.id = 'painel-sliders';
    painel.className = 'painel-sliders oculto';

    // Insere ANTES de .filtros-container dentro de #estagio-edicao
    const filtrosContainer = estagioEdicao.querySelector('.filtros-container');
    estagioEdicao.insertBefore(painel, filtrosContainer);
}

/**
 * Re-renderiza todo o painel de sliders a partir do stackFiltros.
 * Chamado após qualquer mutação no stack (adicionar, remover, ou atualizar filtro).
 */
function _renderizarPainelSliders() {
    const painel = document.getElementById('painel-sliders');
    if (!painel) return;

    // Filtra apenas os filtros ativos que têm controles (paramétricos)
    const filtrosComControles = [];
    stackFiltros.forEach((params, filtroId) => {
        const config = FILTROS_CONFIG[filtroId];
        if (config && config.controles) {
            filtrosComControles.push({ filtroId, config, params });
        }
    });

    // Se nenhum filtro paramétrico estiver ativo, oculta o painel
    if (filtrosComControles.length === 0) {
        painel.classList.add('oculto');
        painel.innerHTML = '';
        return;
    }

    // Gera um grupo de sliders para cada filtro paramétrico ativo
    painel.innerHTML = filtrosComControles.map(({ filtroId, config, params }) => `
        <div class="slider-grupo-filtro" data-filtro="${filtroId}">
            <div class="slider-grupo-cabecalho">
                <span class="slider-grupo-titulo">${config.label}</span>
                <button
                    class="btn-remover-filtro"
                    data-filtro="${filtroId}"
                    title="Remover ${config.label}"
                    aria-label="Remover filtro ${config.label}"
                >×</button>
            </div>
            ${config.controles.map(ctrl => `
                <div class="slider-grupo">
                    <div class="slider-cabecalho">
                        <label class="slider-label" for="slider-${filtroId}-${ctrl.id}">
                            ${ctrl.label}
                        </label>
                        <span class="slider-valor" id="valor-${filtroId}-${ctrl.id}">
                            ${_formatarValor(params[ctrl.id] ?? ctrl.default, ctrl.step)}
                        </span>
                    </div>
                    <input
                        class="slider-range"
                        type="range"
                        id="slider-${filtroId}-${ctrl.id}"
                        data-filtro="${filtroId}"
                        data-param="${ctrl.id}"
                        min="${ctrl.min}"
                        max="${ctrl.max}"
                        step="${ctrl.step}"
                        value="${params[ctrl.id] ?? ctrl.default}"
                    >
                </div>
            `).join('')}
        </div>
    `).join('');

    // Adiciona os event listeners nos sliders recém-criados
    painel.querySelectorAll('.slider-range').forEach(slider => {
        slider.addEventListener('input', _onSliderChange);
    });

    // Adiciona os event listeners nos botões de remover filtro
    painel.querySelectorAll('.btn-remover-filtro').forEach(btn => {
        btn.addEventListener('click', function () {
            _removerFiltro(this.dataset.filtro);
        });
    });

    // Exibe o painel com animação
    painel.classList.remove('oculto');
}

/**
 * Handler do evento 'input' dos sliders.
 * Atualiza o parâmetro no stackFiltros e re-renderiza o canvas.
 */
function _onSliderChange() {
    const filtroId = this.dataset.filtro;
    const paramId  = this.dataset.param;
    const valor    = parseFloat(this.value);

    // Garante que o filtro ainda está no stack antes de atualizar
    if (!stackFiltros.has(filtroId)) return;

    // Atualiza SOMENTE o parâmetro alterado, mantendo os demais intactos
    const paramsAtuais = stackFiltros.get(filtroId);
    paramsAtuais[paramId] = valor;

    // Atualiza o label de valor ao lado do slider
    const ctrl = FILTROS_CONFIG[filtroId].controles.find(c => c.id === paramId);
    const valorEl = document.getElementById(`valor-${filtroId}-${paramId}`);
    if (valorEl && ctrl) {
        valorEl.textContent = _formatarValor(valor, ctrl.step);
    }

    // Re-renderiza apenas o canvas (o painel de sliders já está atualizado)
    _renderizarStack();
}

// ─── Gerenciamento do stack ───────────────────────────────────

/**
 * Adiciona um filtro ao stack com seus valores padrão.
 * Se o filtro já estiver no stack, não faz nada (evita reset de params).
 * @param {string} filtroId - Chave do filtro em FILTROS_CONFIG
 */
function _adicionarFiltro(filtroId) {
    if (stackFiltros.has(filtroId)) return;

    const config = FILTROS_CONFIG[filtroId];
    if (!config) return;

    // Inicializa os parâmetros com os valores padrão definidos em FILTROS_CONFIG
    const paramsIniciais = {};
    if (config.controles) {
        config.controles.forEach(ctrl => {
            paramsIniciais[ctrl.id] = ctrl.default;
        });
    }

    stackFiltros.set(filtroId, paramsIniciais);
    _sincronizarTudo();
}

/**
 * Remove um filtro do stack e atualiza canvas e UI.
 * @param {string} filtroId - Chave do filtro a remover
 */
function _removerFiltro(filtroId) {
    if (!stackFiltros.has(filtroId)) return;

    stackFiltros.delete(filtroId);
    _sincronizarTudo();
}

/**
 * Alterna um filtro entre ativo e inativo no stack (toggle).
 * @param {string} filtroId - Chave do filtro a alternar
 */
function _toggleFiltro(filtroId) {
    if (stackFiltros.has(filtroId)) {
        _removerFiltro(filtroId);
    } else {
        _adicionarFiltro(filtroId);
    }
}

/**
 * Sincroniza canvas e painel de sliders com o estado atual do stackFiltros.
 * Deve ser chamada após QUALQUER mutação no stack.
 */
function _sincronizarTudo() {
    _atualizarBotoesUI();
    _renderizarPainelSliders();
    _renderizarStack();
}

// ─── Utilitários de UI ────────────────────────────────────────

/**
 * Atualiza a classe 'ativo' em todos os botões de filtro com base no stack.
 */
function _atualizarBotoesUI() {
    botoesFiltro.forEach(btn => {
        const id = btn.getAttribute('data-filtro');
        // Ignora o botão "Normal" (ele limpa o stack)
        if (id === 'normal') return;
        btn.classList.toggle('ativo', stackFiltros.has(id));
    });
}

/**
 * Formata um valor numérico para exibição no label do slider.
 * Inteiros para step >= 1, duas casas decimais para os demais.
 * @param {number} valor - Valor a formatar
 * @param {number} step  - Step do slider
 * @returns {string}
 */
function _formatarValor(valor, step) {
    return step >= 1 ? Math.round(valor).toString() : parseFloat(valor).toFixed(2);
}

// ─── Event Listeners ─────────────────────────────────────────

/**
 * Clique nos botões de filtro:
 * - "Normal": limpa todo o stack e reseta a UI
 * - Demais: alterna o filtro no stack (toggle)
 */
botoesFiltro.forEach(btn => {
    btn.addEventListener('click', function () {
        const filtroId = this.getAttribute('data-filtro');

        if (filtroId === 'normal') {
            // Limpa todos os filtros do stack e reseta a UI
            stackFiltros.clear();
            _sincronizarTudo();

            // Marca apenas o botão "Normal" como ativo
            botoesFiltro.forEach(b => b.classList.remove('ativo'));
            this.classList.add('ativo');
        } else {
            // Remove o estado ativo do botão "Normal" se outro filtro for selecionado
            const btnNormal = document.querySelector('.btn-filtro[data-filtro="normal"]');
            if (btnNormal) btnNormal.classList.remove('ativo');

            _toggleFiltro(filtroId);
        }
    });
});

/**
 * Clique em "Avançar →":
 * Exporta a imagem com todos os filtros do stack aplicados e avança para a legenda.
 */
btnAvancarLegenda.addEventListener('click', function () {
    if (!canvasFabric) return;

    // Exporta o canvas com os filtros ativos como JPEG de alta qualidade
    const fotoEditadaBase64 = canvasFabric.toDataURL({
        format:  'jpeg',
        quality: 0.92
    });

    // Atualiza a variável global usada por publicarPost() no PHP
    window.fotoBase64 = fotoEditadaBase64;

    // Atualiza o preview no estágio de legenda
    document.getElementById('preview-foto').src = fotoEditadaBase64;

    // Avança para o estágio de legenda
    estagioEdicao.classList.remove('ativo');
    document.getElementById('estagio-legenda').classList.add('ativo');

    document.getElementById('campo-legenda').focus();
});
