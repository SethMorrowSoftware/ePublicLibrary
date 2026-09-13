/**
 * PDF reader entry.
 *
 * pdf.js is loaded as a classic script by views/layouts/reader.php (locally
 * vendored when available, CDN otherwise) and exposes `pdfjsLib`. The
 * document itself is range-requested from api/download.php, which speaks
 * HTTP Range, so pdf.js pulls only the bytes it needs for the page on screen.
 */

import { initPageReader } from './page-reader/core.js';
import { registerServiceWorker } from './shared/sw-register.js';

registerServiceWorker();

const shell = document.getElementById('reader');

if (shell) {
    start().catch((e) => console.error('PDF reader failed to start:', e));
}

async function start() {
    const lib = await waitForPdfJs();
    if (!lib) {
        showFatal('The PDF engine could not be loaded. Check your connection, or ask an '
                + 'administrator to vendor pdf.js locally.');
        return;
    }

    const workerSrc = document.querySelector('meta[name=pdf-worker-src]')?.content;
    if (workerSrc) {
        lib.GlobalWorkerOptions.workerSrc = workerSrc;
    }

    const sourceUrl = shell.dataset.sourceUrl;
    let doc = null;
    /** Rendered canvases keyed by `${page}@${width}`. */
    const cache = new Map();
    const MAX_CACHED = 6;

    initPageReader({
        async load() {
            const task = lib.getDocument({
                url: sourceUrl,
                withCredentials: true,
                // Range requests keep first paint fast on large documents.
                rangeChunkSize: 262144,
                disableAutoFetch: true,
                disableStream: false,
            });
            doc = await task.promise;
            return { pageCount: doc.numPages };
        },

        async renderPage(n, box) {
            const page = await doc.getPage(n);
            const scale = scaleFor(page, box);
            const key = `${n}@${Math.round(scale * 1000)}`;
            if (cache.has(key)) {
                const cached = cache.get(key);
                return cached.isConnected ? cached.cloneNode(true) : cached;
            }

            const viewport = page.getViewport({ scale: scale * box.dpr });
            const canvas = document.createElement('canvas');
            canvas.className = 'pdf-page';
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            // Lay out at CSS pixels while rendering at device pixels, so the
            // page is sharp on high-DPI screens without doubling its size.
            canvas.style.width = Math.floor(viewport.width / box.dpr) + 'px';
            canvas.style.height = Math.floor(viewport.height / box.dpr) + 'px';
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', `Page ${n}`);

            await page.render({
                canvasContext: canvas.getContext('2d', { alpha: false }),
                viewport,
            }).promise;

            cache.set(key, canvas);
            if (cache.size > MAX_CACHED) {
                cache.delete(cache.keys().next().value);
            }
            return canvas;
        },

        prefetch(n) {
            if (doc && n >= 1 && n <= doc.numPages) {
                doc.getPage(n).catch(() => { /* best effort */ });
            }
        },
    });

    /** Translate the shell's fit mode into a pdf.js scale factor. */
    function scaleFor(page, box) {
        const base = page.getViewport({ scale: 1 });
        switch (box.fit) {
            case 'height': return box.height / base.height;
            case 'page':   return Math.min(box.width / base.width, box.height / base.height);
            case 'custom': return box.zoom;
            case 'width':
            default:       return box.width / base.width;
        }
    }
}

/**
 * The pdf.js <script> is deferred, so the module may run first. Poll briefly
 * rather than racing it.
 */
function waitForPdfJs(timeoutMs = 15000) {
    return new Promise((resolve) => {
        const started = Date.now();
        (function check() {
            if (typeof window.pdfjsLib !== 'undefined') {
                resolve(window.pdfjsLib);
            } else if (Date.now() - started > timeoutMs) {
                resolve(null);
            } else {
                setTimeout(check, 50);
            }
        })();
    });
}

function showFatal(message) {
    const el = document.getElementById('page-error');
    const loading = document.getElementById('page-loading');
    if (loading) loading.hidden = true;
    if (el) {
        el.textContent = message;
        el.hidden = false;
    }
}
