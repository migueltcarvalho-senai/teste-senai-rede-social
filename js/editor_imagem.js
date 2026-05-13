/**
 * ============================================================
 * EDITOR DE IMAGENS — PixiJS v7.3.3 + pixi-filters v5
 * PIPELINE DE FILTROS ESTILO INSTAGRAM
 * ============================================================
 *
 * ARQUITETURA DE PIPELINE:
 *
 *   Imagem
 *     ↓
 *   ColorMatrixFilter  (base look: contraste, saturação, matiz)
 *     ↓
 *   AdvancedBloomFilter (luz e brilho realistas)
 *     ↓
 *   NoiseFilter         (film grain analógico)
 *     ↓
 *   BlurFilter          (soft depth, apenas quando necessário)
 *     ↓
 *   EXPORT (renderer.plugins.extract)
 *
 * CONVENÇÃO DE VALORES em applyMatrix():
 *   contrast / saturate: offset relativo a 1.0 como neutro
 *     ex: 1.1  → leve aumento   →  passa 0.10 ao PixiJS
 *     ex: 0.95 → leve redução   →  passa -0.05 ao PixiJS
 *   brightness: offset relativo a 1.0 como neutro
 *     ex: -0.05 → escurece levemente  → passa 0.95 ao PixiJS
 *   hue: graus de rotação de matiz (passa direto)
 *
 * Interface pública (chamada por nova_postagem.php):
 *   iniciarEditor(base64Image)
 * ============================================================
 */

'use strict';

// ─── Estado do módulo ─────────────────────────────────────────

/** Instância única do PIXI.Application (renderer WebGL). */
let app = null;

/** Sprite que contém a foto capturada como textura PixiJS. */
let sprite = null;

/**
 * Array de filtros do pipeline ativo.
 * Armazenado para ser trocado atomicamente ao mudar de preset.
 */
let filtroPipelineAtivo = [];

/** ID do preset ativo. null = 'normal'. */
let presetAtivo = null;

// ─── Referências ao DOM ──────────────────────────────────────

const estagioEdicao = document.getElementById('estagio-edicao');
const pixiContainer  = document.getElementById('pixi-container');
const btnAvancar     = document.getElementById('btn-avancar-legenda');

// ─── Helper: applyMatrix ─────────────────────────────────────

/**
 * Configura um ColorMatrixFilter com os parâmetros do preset.
 *
 * Convenção de valores (relativa a 1.0 = neutro):
 *  - contrast / saturate: 1.1 = +10%, 0.9 = -10%
 *  - brightness         : -0.05 = 5% mais escuro
 *  - hue                : graus de rotação (ex: 3, -8)
 *
 * O multiply=true encadeia sem sobrescrever o estado anterior,
 * permitindo combinar contrast + saturate + hue em sequência.
 *
 * @param {PIXI.filters.ColorMatrixFilter} filter
 * @param {{ contrast?, saturate?, brightness?, hue? }} options
 */
function applyMatrix(filter, options = {}) {
    // Converte de "relativo a 1.0" para o range esperado pelo PixiJS (-1 a 1)
    if (options.contrast   !== undefined) filter.contrast(options.contrast - 1.0,   true);
    if (options.saturate   !== undefined) filter.saturate(options.saturate - 1.0,   true);
    // brightness no PixiJS é multiplier (1 = neutro): converte offset para multiplier
    if (options.brightness !== undefined) filter.brightness(1.0 + options.brightness, true);
    // hue em graus: passa direto
    if (options.hue        !== undefined) filter.hue(options.hue, true);
}

// ─── Definição dos presets ────────────────────────────────────

/**
 * FILTER_PRESETS: mapeamento id → factory que retorna um pipeline de filtros.
 *
 * Cada factory recebe uma intensidade (i = 0.0 a 1.0) e retorna um
 * array de instâncias de filtros PixiJS prontas para serem aplicadas
 * em sprite.filters = [...].
 *
 * Pipeline padrão (inspirado no spec presetsFiltros.txt):
 *   [ColorMatrixFilter, AdvancedBloomFilter?, NoiseFilter?, BlurFilter?]
 */
const FILTER_PRESETS = {

    // ─── Normal ─────────────────────────────────────────────
    /**
     * Normal — sem nenhum filtro. Imagem ao natural.
     */
    normal: () => [],

    // ─── 1. Warm Vintage ────────────────────────────────────
    /**
     * Warm Vintage
     * Look: quente, pele bonita, leve fade analógico.
     * Pipeline: ColorMatrix + Bloom + Grain
     */
    warmVintage: (i = 1.0) => {
        const cm = new PIXI.filters.ColorMatrixFilter();
        applyMatrix(cm, {
            contrast: 1.1  + 0.05 * i,  // Leve aumento de contraste
            saturate: 1.05 + 0.10 * i,  // Saturação quente
            hue:      3    *        i   // Rotação para tons âmbar
        });
        return [
            cm,
            new PIXI.filters.AdvancedBloomFilter({
                threshold:  0.70,
                bloomScale: 0.20 * i,  // Glow suave em highlights
                brightness: 1.0
            }),
            new PIXI.filters.NoiseFilter(0.04 * i)  // Film grain leve
        ];
    },

    // ─── 2. Cool Cinematic ──────────────────────────────────
    /**
     * Cool Cinematic
     * Look: azul frio, moderno, tech, night vibe urbano.
     * Pipeline: ColorMatrix + Bloom + Grain
     */
    coolCinematic: (i = 1.0) => {
        const cm = new PIXI.filters.ColorMatrixFilter();
        applyMatrix(cm, {
            contrast:   1.15 + 0.08 * i,  // Contraste forte
            saturate:   0.95,              // Levemente dessaturado
            hue:        -8   *        i,  // Vira para tons frios/azulados
            brightness: -0.02 *       i   // Ligeiramente mais escuro
        });
        return [
            cm,
            new PIXI.filters.AdvancedBloomFilter({
                threshold:  0.75,
                bloomScale: 0.18 * i       // Bloom controlado (não queima)
            }),
            new PIXI.filters.NoiseFilter(0.03 * i)
        ];
    },

    // ─── 3. Golden Hour ─────────────────────────────────────
    /**
     * Golden Hour
     * Look: viral Instagram, dourado forte, hora mágica.
     * Pipeline: ColorMatrix + Bloom forte + Grain
     */
    goldenHour: (i = 1.0) => {
        const cm = new PIXI.filters.ColorMatrixFilter();
        applyMatrix(cm, {
            contrast: 1.20 + 0.05 * i,  // Contraste elevado
            saturate: 1.15 + 0.15 * i,  // Saturação vibrante e quente
            hue:      2    *        i   // Toque dourado
        });
        return [
            cm,
            new PIXI.filters.AdvancedBloomFilter({
                threshold:  0.65,
                bloomScale: 0.28 * i   // Bloom generoso (efeito golden hour)
            }),
            new PIXI.filters.NoiseFilter(0.05 * i)
        ];
    },

    // ─── 4. Soft Beauty ─────────────────────────────────────
    /**
     * Soft Beauty
     * Look: suavização leve, estética selfie, pele suave.
     * Pipeline: Blur (depth) + ColorMatrix + Grain mínimo
     * Nota: Blur vem ANTES da colormatrix para simular profundidade
     */
    softBeauty: (i = 1.0) => {
        const cm = new PIXI.filters.ColorMatrixFilter();
        applyMatrix(cm, {
            contrast: 1.05,  // Contraste mínimo
            saturate: 1.05   // Saturação mínima
        });
        return [
            new PIXI.filters.BlurFilter(0.8 * i),  // Suavização leve
            cm,
            new PIXI.filters.NoiseFilter(0.02 * i) // Grain quase imperceptível
        ];
    },

    // ─── 5. Film Look ───────────────────────────────────────
    /**
     * Film Look
     * Look: cinema clássico, grain analógico, contraste controlado.
     * Pipeline: ColorMatrix + Grain forte + Bloom sutil
     */
    filmLook: (i = 1.0) => {
        const cm = new PIXI.filters.ColorMatrixFilter();
        applyMatrix(cm, {
            contrast: 1.18,             // Contraste cinematográfico
            saturate: 0.95,             // Levemente dessaturado (tom fílmico)
            hue:      -2   *       i   // Leve virada para azul-frio
        });
        return [
            cm,
            new PIXI.filters.NoiseFilter(0.08 * i),  // Grain analógico forte
            new PIXI.filters.AdvancedBloomFilter({
                threshold:  0.80,
                bloomScale: 0.12 * i                 // Bloom mínimo (realista)
            })
        ];
    },

    // ─── 6. Vibrant Pop ─────────────────────────────────────
    /**
     * Vibrant Pop
     * Look: cores fortes, impacto alto, estilo TikTok/Reels.
     * Pipeline: ColorMatrix agressivo + Bloom forte
     */
    vibrantPop: (i = 1.0) => {
        const cm = new PIXI.filters.ColorMatrixFilter();
        applyMatrix(cm, {
            contrast: 1.30 + 0.10 * i,  // Contraste muito alto
            saturate: 1.35 + 0.20 * i   // Saturação muito alta
        });
        return [
            cm,
            new PIXI.filters.AdvancedBloomFilter({
                threshold:  0.60,
                bloomScale: 0.32 * i   // Bloom agressivo
            })
        ];
    },

    // ─── 7. Dark Moody ──────────────────────────────────────
    /**
     * Dark Moody
     * Look: escuro, dramático, cinematográfico noir.
     * Pipeline: ColorMatrix escuro + Grain
     */
    darkMoody: (i = 1.0) => {
        const cm = new PIXI.filters.ColorMatrixFilter();
        applyMatrix(cm, {
            contrast:   1.35 + 0.10 * i, // Contraste forte
            brightness: -0.05 *       i, // Escurece levemente
            saturate:   0.85,            // Dessaturado (tons sombrios)
            hue:        -4   *       i  // Vira levemente para tons frios
        });
        return [
            cm,
            new PIXI.filters.NoiseFilter(0.06 * i)  // Grain dramático
        ];
    }
};

// ─── Metadados dos presets para a UI ────────────────────────

/**
 * PRESETS_META: define a ordem e aparência dos botões de preset.
 * Cada entrada corresponde a uma chave em FILTER_PRESETS.
 */
const PRESETS_META = [
    { id: 'normal',       label: 'Normal',    emoji: '⊘'  },
    { id: 'softBeauty',   label: 'Soft',      emoji: '🌸' },
    { id: 'warmVintage',  label: 'Vintage',   emoji: '🌅' },
    { id: 'goldenHour',   label: 'Golden',    emoji: '✨' },
    { id: 'coolCinematic',label: 'Cinema',    emoji: '🎬' },
    { id: 'filmLook',     label: 'Film',      emoji: '🎞️' },
    { id: 'vibrantPop',   label: 'Pop',       emoji: '💥' },
    { id: 'darkMoody',    label: 'Dark',      emoji: '🌑' }
];

// ─── Inicialização do editor ─────────────────────────────────

/**
 * Inicializa o editor PixiJS com a foto capturada.
 * Chamada por usarFoto() em nova_postagem.php.
 *
 * @param {string} base64Image - Base64 da foto (data:image/jpeg;base64,...)
 */
function iniciarEditor(base64Image) {
    const tamanho = pixiContainer.clientWidth || 320;

    // Cria o PIXI.Application apenas uma vez — reutiliza em sessões subsequentes
    if (!app) {
        app = new PIXI.Application({
            width:                tamanho,
            height:               tamanho,
            backgroundColor:      0x1a1a1a,
            antialias:            true,
            // preserveDrawingBuffer: obrigatório para renderer.plugins.extract funcionar
            preserveDrawingBuffer: true
        });

        // Monta o canvas WebGL do PixiJS no container HTML
        pixiContainer.appendChild(app.view);

        // Constrói os botões de preset na barra horizontal
        _construirBarraPresets();
    } else {
        // Redimensiona se o container mudou
        app.renderer.resize(tamanho, tamanho);
    }

    // Carrega a foto e configura a cena
    _carregarImagem(base64Image).then((novoSprite) => {
        // Remove o sprite anterior se existir
        if (sprite) {
            app.stage.removeChild(sprite);
            sprite.destroy({ texture: true, baseTexture: true });
        }

        sprite = novoSprite;

        // Escala o sprite para preencher o container quadrado (aspect-fill)
        const escala = tamanho / Math.max(sprite.texture.width, sprite.texture.height);
        sprite.scale.set(escala);
        sprite.anchor.set(0.5);
        sprite.x = tamanho / 2;
        sprite.y = tamanho / 2;

        app.stage.addChild(sprite);

        // Reseta para "Normal" ao carregar nova foto
        presetAtivo = null;
        _aplicarPreset();
        _atualizarUI();
    });
}

// ─── Carregamento de imagem ──────────────────────────────────

/**
 * Carrega uma imagem base64 como PIXI.Sprite com Promise.
 * Aguarda o evento 'loaded' para garantir que a textura está pronta.
 *
 * @param {string} base64
 * @returns {Promise<PIXI.Sprite>}
 */
function _carregarImagem(base64) {
    return new Promise((resolve, reject) => {
        // Limpa o cache para permitir reload limpo (ex: "tirar outra foto")
        if (PIXI.utils.TextureCache[base64]) {
            PIXI.utils.TextureCache[base64].destroy(true);
            delete PIXI.utils.TextureCache[base64];
        }

        const novoSprite = PIXI.Sprite.from(base64);

        // Textura já carregada (base64 em memória é imediato na maioria dos casos)
        if (novoSprite.texture.valid && novoSprite.texture.width > 0) {
            resolve(novoSprite);
            return;
        }

        // Fallback: aguarda o evento loaded da BaseTexture
        novoSprite.texture.baseTexture.on('loaded', () => resolve(novoSprite));
        novoSprite.texture.baseTexture.on('error', (err) => {
            console.error('[Editor] Falha ao carregar textura:', err);
            reject(err);
        });
    });
}

// ─── Construção da barra de presets ─────────────────────────

/**
 * Gera os botões de preset dinamicamente dentro de #filtros-lista.
 * Chamada uma única vez na inicialização do PIXI.Application.
 */
function _construirBarraPresets() {
    const lista = document.getElementById('filtros-lista');
    if (!lista) return;

    lista.innerHTML = '';

    PRESETS_META.forEach(({ id, label, emoji }) => {
        const btn = document.createElement('button');
        btn.className      = 'btn-filtro' + (id === 'normal' ? ' ativo' : '');
        btn.dataset.preset = id;
        btn.setAttribute('aria-pressed', id === 'normal' ? 'true' : 'false');
        btn.setAttribute('aria-label', `Aplicar filtro ${label}`);
        btn.innerHTML = `
            <span class="icone-filtro" aria-hidden="true">${emoji}</span>
            <span class="preset-label">${label}</span>
        `;

        btn.addEventListener('click', () => _selecionarPreset(id));
        lista.appendChild(btn);
    });
}

// ─── Aplicação de presets ────────────────────────────────────

/**
 * Seleciona um preset e aplica seu pipeline de filtros.
 * Garante seleção exclusiva — clicar no preset ativo não faz nada.
 *
 * @param {string} id - ID do preset (chave em FILTER_PRESETS)
 */
function _selecionarPreset(id) {
    if (presetAtivo === id) return;

    presetAtivo = id;
    _aplicarPreset();
    _atualizarUI();
}

/**
 * Aplica o pipeline de filtros do preset ativo no sprite.
 *
 * Substitui o array de filtros atomicamente — os filtros antigos são
 * simplesmente desreferenciados (WebGL gerencia os shaders internamente).
 */
function _aplicarPreset() {
    if (!sprite) return;

    const factory = FILTER_PRESETS[presetAtivo ?? 'normal'];

    if (factory) {
        // Gera instâncias frescas do pipeline (intensidade 1.0)
        filtroPipelineAtivo = factory(1.0);
    } else {
        filtroPipelineAtivo = [];
    }

    // Aplica o pipeline no sprite (substitui o array anterior)
    sprite.filters = filtroPipelineAtivo.length > 0 ? filtroPipelineAtivo : null;

    // Força re-renderização da cena na GPU
    if (app) app.renderer.render(app.stage);
}

// ─── Sincronização da UI ─────────────────────────────────────

/**
 * Atualiza o estado visual dos botões de preset.
 * Apenas o botão do preset ativo recebe a classe 'ativo'.
 */
function _atualizarUI() {
    document.querySelectorAll('.btn-filtro[data-preset]').forEach(btn => {
        const isAtivo = btn.dataset.preset === (presetAtivo ?? 'normal');
        btn.classList.toggle('ativo', isAtivo);
        btn.setAttribute('aria-pressed', isAtivo ? 'true' : 'false');
    });
}

// ─── Exportação e avanço ─────────────────────────────────────

/**
 * Clique em "Avançar →":
 * Extrai o canvas WebGL renderizado, converte para JPEG base64
 * e avança para o estágio de legenda.
 *
 * renderer.plugins.extract.canvas(stage):
 *   Lê os pixels do framebuffer WebGL e os copia para um Canvas2D.
 *   preserveDrawingBuffer:true é obrigatório para que readPixels funcione.
 */
btnAvancar.addEventListener('click', function () {
    if (!app || !sprite) return;

    try {
        const canvasExportado = app.renderer.plugins.extract.canvas(app.stage);
        const base64          = canvasExportado.toDataURL('image/jpeg', 0.92);

        // Disponibiliza para publicarPost() em nova_postagem.php
        window.fotoBase64 = base64;

        // Atualiza preview no estágio de legenda
        document.getElementById('preview-foto').src = base64;

        // Avança o estágio
        estagioEdicao.classList.remove('ativo');
        document.getElementById('estagio-legenda').classList.add('ativo');
        document.getElementById('campo-legenda').focus();

    } catch (err) {
        console.error('[Editor] Erro ao exportar imagem:', err);
    }
});
