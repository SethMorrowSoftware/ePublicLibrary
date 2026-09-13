/**
 * Shared engine for the page-based readers (PDF and comic).
 *
 * Both formats answer the same questions — which page am I on, how many are
 * there, how big should it be drawn — so the chrome, navigation, zoom,
 * bookmarks, progress sync and keyboard handling all live here. A format
 * plugs in by supplying:
 *
 *   {
 *     load()                  → { pageCount }   open the document
 *     renderPage(n, opts)     → HTMLElement     draw one page
 *     pageAspect(n)           → number|null     w/h, for placeholder sizing
 *     destroy?()                                release resources
 *   }
 *
 * Progress is stored as the locator "page:N" in the same reading_progress row
 * the EPUB reader uses, so "Continue reading" works across every format.
 */

import { post, request } from '../shared/api.js';

const PROGRESS_PREFIX = 'elib-progress-';
const PROGRESS_DEBOUNCE_MS = 2500;

/**
 * Defaults differ by format because the expectations do: a PDF is a document
 * you read column-by-column, so fit-to-width is right; a comic page is a
 * composed image you want to see whole.
 */
const DEFAULTS_BY_KIND = {
    pdf:   { fit: 'width' },
    comic: { fit: 'page' },
};

const BASE_SETTINGS = {
    fit: 'width',
    zoom: 100,
    theme: 'auto',
    spread: false,
    rtl: false,
    continuous: false,
};

/** Settings are per-format: comic zoom should not follow you into a PDF. */
function settingsKey(kind) {
    return `elib-page-reader-settings:${kind}`;
}

export function readSettings(kind = 'pdf') {
    const defaults = { ...BASE_SETTINGS, ...(DEFAULTS_BY_KIND[kind] || {}) };
    try {
        return { ...defaults, ...(JSON.parse(localStorage.getItem(settingsKey(kind))) || {}) };
    } catch {
        return { ...defaults };
    }
}

function writeSettings(kind, s) {
    try { localStorage.setItem(settingsKey(kind), JSON.stringify(s)); } catch { /* private mode */ }
}

export function toast(message, kind = 'info') {
    const el = document.getElementById('reader-toast');
    if (!el) return;
    el.textContent = message;
    el.dataset.kind = kind;
    el.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { el.hidden = true; }, 2600);
}

/**
 * @param {object} source  the format adapter described above
 */
export async function initPageReader(source) {
    const shell = document.getElementById('reader');
    if (!shell) return;

    const els = {
        shell,
        stage:      document.getElementById('page-stage'),
        scroll:     document.getElementById('page-scroll'),
        slider:     document.getElementById('page-slider'),
        indicator:  document.getElementById('page-indicator'),
        percent:    document.getElementById('progress-percent'),
        loading:    document.getElementById('page-loading'),
        error:      document.getElementById('page-error'),
        thumbList:  document.getElementById('page-thumb-list'),
        jumpForm:   document.getElementById('page-jump-form'),
        jumpInput:  document.getElementById('page-jump-input'),
        bookmarks:  document.getElementById('bookmarks-list'),
    };

    const kind = shell.dataset.kind === 'comic' ? 'comic' : 'pdf';

    const ctx = {
        kind,
        bookUuid:     shell.dataset.bookUuid,
        sourceUrl:    shell.dataset.sourceUrl,
        progressUrl:  shell.dataset.progressUrl,
        bookmarksUrl: shell.dataset.bookmarksUrl,
        isGuest:      shell.dataset.isGuest === '1',
        pageCount:    parseInt(shell.dataset.pageCount, 10) || 0,
        page:         parseInt(shell.dataset.startPage, 10) || 1,
        settings:     readSettings(kind),
        els,
        source,
    };

    applyTheme(ctx.settings.theme);
    wirePanels();
    wireSettings(ctx);
    wireNavigation(ctx);
    wireBookmarks(ctx);
    wireImmersive();

    setLoading(ctx, true, 'Opening…');
    try {
        const info = await source.load();
        if (info && info.pageCount > 0) {
            ctx.pageCount = info.pageCount;
        }
    } catch (e) {
        console.error('Reader failed to open the document:', e);
        setLoading(ctx, false);
        showError(ctx, 'This book could not be opened. It may be missing or damaged.');
        return;
    }
    setLoading(ctx, false);

    if (ctx.pageCount < 1) {
        showError(ctx, 'This book has no readable pages.');
        return;
    }

    // A guest's place lives in this browser only.
    if (ctx.isGuest) {
        const saved = readLocalProgress(ctx.bookUuid);
        if (saved && saved > 0 && shell.dataset.startPage === '1') {
            ctx.page = Math.min(saved, ctx.pageCount);
        }
    }
    ctx.page = clamp(ctx.page, 1, ctx.pageCount);

    els.slider.max = String(ctx.pageCount);
    if (els.jumpInput) els.jumpInput.max = String(ctx.pageCount);

    buildThumbList(ctx);
    await goTo(ctx, ctx.page, { replace: true });
}

/* ------------------------------------------------------------------ */
/*  Rendering                                                          */
/* ------------------------------------------------------------------ */

/** Pages currently visible: one, or two in spread mode on a wide screen. */
function visiblePages(ctx) {
    if (!ctx.settings.spread || window.innerWidth < 900 || ctx.settings.continuous) {
        return [ctx.page];
    }
    // Page 1 stands alone like a cover, then pairs run 2-3, 4-5, …
    if (ctx.page === 1) return [1];
    const left = ctx.page % 2 === 0 ? ctx.page : ctx.page - 1;
    const pair = [left, left + 1].filter((n) => n <= ctx.pageCount);
    return ctx.settings.rtl ? pair.reverse() : pair;
}

export async function renderCurrent(ctx) {
    const { stage } = ctx.els;
    const pages = visiblePages(ctx);
    const token = ++renderCurrent._token;

    stage.dataset.spread = pages.length > 1 ? '1' : '0';
    setLoading(ctx, true);

    let rendered;
    try {
        rendered = await Promise.all(pages.map((n) => ctx.source.renderPage(n, viewportBox(ctx))));
    } catch (e) {
        console.error('Page render failed:', e);
        if (token === renderCurrent._token) {
            setLoading(ctx, false);
            showError(ctx, 'That page could not be displayed.');
        }
        return;
    }
    // A newer navigation started while we were decoding — drop this result.
    if (token !== renderCurrent._token) return;

    clearError(ctx);
    stage.replaceChildren(...rendered.filter(Boolean));
    applyZoom(ctx);
    setLoading(ctx, false);

    ctx.els.scroll.scrollTop = 0;
    ctx.els.scroll.scrollLeft = 0;

    // Warm the next page so a forward turn feels instant.
    const next = ctx.page + pages.length;
    if (next <= ctx.pageCount && typeof ctx.source.prefetch === 'function') {
        ctx.source.prefetch(next);
    }
}
renderCurrent._token = 0;

/** Space the renderer may use, in CSS pixels. */
function viewportBox(ctx) {
    const vp = ctx.els.scroll;
    const pages = visiblePages(ctx).length;
    return {
        width: Math.max(200, (vp.clientWidth - 32) / pages),
        height: Math.max(200, vp.clientHeight - 32),
        fit: ctx.settings.fit,
        zoom: ctx.settings.zoom / 100,
        dpr: Math.min(window.devicePixelRatio || 1, 2),
    };
}

function applyZoom(ctx) {
    const { stage } = ctx.els;
    stage.dataset.fit = ctx.settings.fit;
    stage.style.setProperty('--page-zoom', String(ctx.settings.zoom / 100));
}

/* ------------------------------------------------------------------ */
/*  Navigation                                                         */
/* ------------------------------------------------------------------ */

export async function goTo(ctx, page, { replace = false } = {}) {
    const target = clamp(Math.round(page), 1, ctx.pageCount);
    if (!replace && target === ctx.page) return;
    ctx.page = target;
    syncIndicators(ctx);
    await renderCurrent(ctx);
    scheduleProgressSync(ctx);
}

function step(ctx, delta) {
    const size = visiblePages(ctx).length;
    return goTo(ctx, ctx.page + delta * size);
}

function wireNavigation(ctx) {
    const { els } = ctx;

    document.querySelectorAll('.reader-hotspot').forEach((spot) => {
        spot.addEventListener('click', () => {
            const forward = spot.dataset.direction === 'next';
            // In manga mode the visual left edge advances the story.
            step(ctx, (forward !== !!ctx.settings.rtl) ? 1 : -1);
        });
    });

    els.slider.addEventListener('input', () => {
        // Update the label live, but only render when the drag settles.
        const n = clamp(parseInt(els.slider.value, 10) || 1, 1, ctx.pageCount);
        els.indicator.textContent = `Page ${n} of ${ctx.pageCount}`;
    });
    els.slider.addEventListener('change', () => {
        goTo(ctx, parseInt(els.slider.value, 10) || 1);
    });

    if (els.jumpForm) {
        els.jumpForm.addEventListener('submit', (e) => {
            e.preventDefault();
            goTo(ctx, parseInt(els.jumpInput.value, 10) || 1);
            closePanel('panel-pages');
        });
    }

    document.getElementById('btn-zoom-in')?.addEventListener('click', () => nudgeZoom(ctx, 10));
    document.getElementById('btn-zoom-out')?.addEventListener('click', () => nudgeZoom(ctx, -10));

    document.addEventListener('keydown', (e) => {
        if (e.metaKey || e.ctrlKey || e.altKey) return;
        const tag = document.activeElement?.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

        switch (e.key) {
            case 'ArrowRight': case 'PageDown': case ' ':
                e.preventDefault(); step(ctx, ctx.settings.rtl ? -1 : 1); break;
            case 'ArrowLeft': case 'PageUp':
                e.preventDefault(); step(ctx, ctx.settings.rtl ? 1 : -1); break;
            case 'Home':
                e.preventDefault(); goTo(ctx, 1); break;
            case 'End':
                e.preventDefault(); goTo(ctx, ctx.pageCount); break;
            case '+': case '=':
                e.preventDefault(); nudgeZoom(ctx, 10); break;
            case '-':
                e.preventDefault(); nudgeZoom(ctx, -10); break;
            case 'f': case 'F':
                e.preventDefault(); document.getElementById('btn-immersive')?.click(); break;
        }
    });

    // Swipe.
    let touch = null;
    const SWIPE = 60;
    ctx.els.scroll.addEventListener('touchstart', (e) => { touch = e.changedTouches[0]; }, { passive: true });
    ctx.els.scroll.addEventListener('touchend', (e) => {
        if (!touch) return;
        const t = e.changedTouches[0];
        const dx = t.clientX - touch.clientX;
        const dy = t.clientY - touch.clientY;
        touch = null;
        if (Math.abs(dx) < SWIPE || Math.abs(dx) < Math.abs(dy)) return;
        const forward = dx < 0;
        step(ctx, (forward !== !!ctx.settings.rtl) ? 1 : -1);
    }, { passive: true });

    // Re-render on resize so fit-to-width stays true (and canvases stay sharp).
    let resizeHandle = null;
    window.addEventListener('resize', () => {
        clearTimeout(resizeHandle);
        resizeHandle = setTimeout(() => renderCurrent(ctx), 200);
    });
}

function nudgeZoom(ctx, delta) {
    ctx.settings.fit = 'custom';
    ctx.settings.zoom = clamp(ctx.settings.zoom + delta, 50, 400);
    writeSettings(ctx.kind, ctx.settings);
    const fitSel = document.getElementById('setting-fit');
    const zoomInput = document.getElementById('setting-zoom');
    const zoomOut = document.getElementById('zoom-out-value');
    if (fitSel) fitSel.value = 'custom';
    if (zoomInput) zoomInput.value = String(ctx.settings.zoom);
    if (zoomOut) zoomOut.textContent = ctx.settings.zoom + '%';
    renderCurrent(ctx);
}

function syncIndicators(ctx) {
    const { els } = ctx;
    els.slider.value = String(ctx.page);
    els.slider.setAttribute('aria-valuetext', `Page ${ctx.page} of ${ctx.pageCount}`);
    els.indicator.textContent = `Page ${ctx.page} of ${ctx.pageCount}`;
    const pct = ctx.pageCount > 0 ? (ctx.page / ctx.pageCount) * 100 : 0;
    els.percent.textContent = pct.toFixed(1) + '%';
    if (els.jumpInput) els.jumpInput.value = String(ctx.page);

    els.thumbList?.querySelectorAll('[data-page]').forEach((li) => {
        li.classList.toggle('is-current', Number(li.dataset.page) === ctx.page);
    });
}

/* ------------------------------------------------------------------ */
/*  Progress                                                           */
/* ------------------------------------------------------------------ */

function scheduleProgressSync(ctx) {
    clearTimeout(scheduleProgressSync._t);
    scheduleProgressSync._t = setTimeout(() => saveProgress(ctx), PROGRESS_DEBOUNCE_MS);
}

async function saveProgress(ctx) {
    const percentage = ctx.pageCount > 0 ? (ctx.page / ctx.pageCount) * 100 : 0;
    const locator = `page:${ctx.page}`;

    try {
        localStorage.setItem(PROGRESS_PREFIX + ctx.bookUuid, JSON.stringify({
            cfi: locator, percentage, ts: Date.now(),
        }));
    } catch { /* private mode */ }

    if (ctx.isGuest) return;
    try {
        await post(ctx.progressUrl, {
            uuid: ctx.bookUuid,
            cfi: locator,
            percentage,
            current_chapter: `Page ${ctx.page}`,
            finished: ctx.page >= ctx.pageCount,
        });
    } catch { /* localStorage already holds the fallback */ }
}

function readLocalProgress(uuid) {
    try {
        const saved = JSON.parse(localStorage.getItem(PROGRESS_PREFIX + uuid) || 'null');
        const m = /^page:(\d+)$/.exec(saved?.cfi || '');
        return m ? parseInt(m[1], 10) : null;
    } catch {
        return null;
    }
}

/* ------------------------------------------------------------------ */
/*  Bookmarks                                                          */
/* ------------------------------------------------------------------ */

function wireBookmarks(ctx) {
    const list = ctx.els.bookmarks;

    document.getElementById('btn-bookmark')?.addEventListener('click', async () => {
        if (ctx.isGuest) {
            toast('Sign in to save bookmarks.', 'error');
            return;
        }
        const locator = `page:${ctx.page}`;
        const label = `Page ${ctx.page}`;
        try {
            const res = await post(ctx.bookmarksUrl, {
                uuid: ctx.bookUuid, cfi: locator, label, chapter: label,
            });
            addBookmarkRow(ctx, res.id, locator, label);
            toast('Bookmarked ' + label.toLowerCase() + '.');
        } catch {
            toast('Could not save the bookmark.', 'error');
        }
    });

    list?.addEventListener('click', async (e) => {
        const li = e.target.closest('li[data-cfi]');
        if (!li) return;
        if (e.target.closest('.bookmark-jump')) {
            const m = /^page:(\d+)$/.exec(li.dataset.cfi);
            if (m) {
                goTo(ctx, parseInt(m[1], 10));
                closePanel('panel-bookmarks');
            }
            return;
        }
        if (e.target.closest('.bookmark-delete')) {
            const id = li.dataset.id;
            li.remove();
            if (!list.querySelector('li[data-cfi]')) {
                list.innerHTML = '<li class="reader-panel-empty">No bookmarks yet. Use the + button to mark this page.</li>';
            }
            try {
                await request(`${ctx.bookmarksUrl}?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
            } catch {
                toast('Could not remove the bookmark.', 'error');
            }
        }
    });
}

function addBookmarkRow(ctx, id, locator, label) {
    const list = ctx.els.bookmarks;
    if (!list) return;
    list.querySelector('.reader-panel-empty')?.remove();
    const li = document.createElement('li');
    li.dataset.cfi = locator;
    li.dataset.id = String(id);
    const jump = document.createElement('button');
    jump.type = 'button';
    jump.className = 'bookmark-jump';
    jump.textContent = label;
    const when = document.createElement('small');
    when.textContent = new Date().toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'bookmark-delete';
    del.setAttribute('aria-label', 'Delete bookmark');
    del.textContent = '×';
    li.append(jump, when, del);
    list.appendChild(li);
}

/* ------------------------------------------------------------------ */
/*  Page list                                                          */
/* ------------------------------------------------------------------ */

function buildThumbList(ctx) {
    const list = ctx.els.thumbList;
    if (!list) return;
    const frag = document.createDocumentFragment();
    for (let n = 1; n <= ctx.pageCount; n++) {
        const li = document.createElement('li');
        li.dataset.page = String(n);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'toc-item';
        btn.textContent = `Page ${n}`;
        btn.addEventListener('click', () => {
            goTo(ctx, n);
            closePanel('panel-pages');
        });
        li.appendChild(btn);
        frag.appendChild(li);
    }
    list.replaceChildren(frag);
}

/* ------------------------------------------------------------------ */
/*  Panels, settings, chrome                                           */
/* ------------------------------------------------------------------ */

const PANELS = {
    'panel-pages':     'btn-pages',
    'panel-bookmarks': 'btn-bookmarks',
    'panel-settings':  'btn-settings',
};

let activePanel = null;

function openPanel(id) {
    if (activePanel && activePanel !== id) closePanel(activePanel);
    const el = document.getElementById(id);
    if (!el) return;
    el.hidden = false;
    const btn = document.getElementById(PANELS[id]);
    btn?.setAttribute('aria-expanded', 'true');
    btn?.classList.add('is-active');
    activePanel = id;
    el.querySelector('button, input, select, a')?.focus();
}

function closePanel(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.hidden = true;
    const btn = document.getElementById(PANELS[id]);
    btn?.setAttribute('aria-expanded', 'false');
    btn?.classList.remove('is-active');
    if (activePanel === id) activePanel = null;
}

function wirePanels() {
    Object.entries(PANELS).forEach(([panelId, btnId]) => {
        const btn = document.getElementById(btnId);
        if (!btn) return;
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', panelId);
        btn.addEventListener('click', () => {
            const el = document.getElementById(panelId);
            el && el.hidden ? openPanel(panelId) : closePanel(panelId);
        });
    });

    document.querySelectorAll('[data-close]').forEach((btn) => {
        btn.addEventListener('click', () => closePanel(btn.dataset.close));
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && activePanel) {
            closePanel(activePanel);
            e.stopPropagation();
        }
    });

    document.addEventListener('mousedown', (e) => {
        if (!activePanel) return;
        const el = document.getElementById(activePanel);
        const btn = document.getElementById(PANELS[activePanel]);
        if (el?.contains(e.target) || btn?.contains(e.target)) return;
        closePanel(activePanel);
    });

    document.querySelectorAll('[data-dismiss]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.dismiss);
            if (target) target.hidden = true;
        });
    });
}

function wireSettings(ctx) {
    const s = ctx.settings;

    const fit = document.getElementById('setting-fit');
    if (fit) {
        fit.value = s.fit;
        fit.addEventListener('change', () => {
            s.fit = fit.value;
            writeSettings(ctx.kind, s);
            renderCurrent(ctx);
        });
    }

    const zoom = document.getElementById('setting-zoom');
    const zoomOut = document.getElementById('zoom-out-value');
    if (zoom && zoomOut) {
        zoom.value = String(s.zoom);
        zoomOut.textContent = s.zoom + '%';
        zoom.addEventListener('input', () => {
            s.zoom = parseInt(zoom.value, 10) || 100;
            s.fit = 'custom';
            if (fit) fit.value = 'custom';
            zoomOut.textContent = s.zoom + '%';
            writeSettings(ctx.kind, s);
            renderCurrent(ctx);
        });
    }

    document.querySelectorAll('input[name="reader-theme"]').forEach((radio) => {
        radio.checked = radio.value === s.theme;
        radio.addEventListener('change', () => {
            if (!radio.checked) return;
            s.theme = radio.value;
            writeSettings(ctx.kind, s);
            applyTheme(s.theme);
        });
    });

    bindToggle('setting-spread', 'spread');
    bindToggle('setting-rtl', 'rtl');
    bindToggle('setting-continuous', 'continuous');

    function bindToggle(id, key) {
        const input = document.getElementById(id);
        if (!input) return;
        input.checked = !!s[key];
        input.addEventListener('change', () => {
            s[key] = input.checked;
            writeSettings(ctx.kind, s);
            ctx.els.shell.dataset[key] = input.checked ? '1' : '0';
            renderCurrent(ctx);
        });
        ctx.els.shell.dataset[key] = s[key] ? '1' : '0';
    }
}

function applyTheme(theme) {
    const resolved = theme === 'auto'
        ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : theme;
    document.documentElement.setAttribute('data-theme', resolved);
    document.getElementById('reader')?.setAttribute('data-theme', resolved);
}

function wireImmersive() {
    const btn = document.getElementById('btn-immersive');
    if (!btn) return;
    const shell = document.getElementById('reader');
    const toggle = (on) => {
        shell.classList.toggle('is-immersive', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    };
    btn.addEventListener('click', () => toggle(!shell.classList.contains('is-immersive')));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && shell.classList.contains('is-immersive')) toggle(false);
    });
}

/* ------------------------------------------------------------------ */

function setLoading(ctx, on, text) {
    const el = ctx.els.loading;
    if (!el) return;
    if (text) {
        const label = el.querySelector('.page-loading-text');
        if (label) label.textContent = text;
    }
    el.hidden = !on;
}

function showError(ctx, message) {
    const el = ctx.els.error;
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
}

function clearError(ctx) {
    if (ctx.els.error) ctx.els.error.hidden = true;
}

export function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}
