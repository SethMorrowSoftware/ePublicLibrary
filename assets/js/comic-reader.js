/**
 * Comic reader entry (CBZ).
 *
 * Pages are plain images fetched one at a time from api/comic.php, so a
 * 400 MB volume opens as fast as a 4 MB one and the service worker can cache
 * individual pages. The shared page-reader core owns everything else.
 */

import { initPageReader } from './page-reader/core.js';
import { registerServiceWorker } from './shared/sw-register.js';

registerServiceWorker();

const shell = document.getElementById('reader');

if (shell) {
    const sourceUrl = shell.dataset.sourceUrl;
    /** Decoded <img> elements, keyed by page number. */
    const cache = new Map();
    const MAX_CACHED = 8;
    let total = 0;

    const pageUrl = (n) => `${sourceUrl}&page=${n}`;

    function loadImage(n) {
        if (cache.has(n)) return cache.get(n);
        const promise = new Promise((resolve, reject) => {
            const img = new Image();
            img.decoding = 'async';
            img.alt = `Page ${n}`;
            img.className = 'comic-page';
            img.dataset.page = String(n);
            img.addEventListener('load', () => resolve(img), { once: true });
            img.addEventListener('error', () => {
                cache.delete(n);
                reject(new Error(`Page ${n} could not be loaded.`));
            }, { once: true });
            img.src = pageUrl(n);
        });
        cache.set(n, promise);
        // Keep the working set small: comics are big and mobile memory is not.
        if (cache.size > MAX_CACHED) {
            cache.delete(cache.keys().next().value);
        }
        return promise;
    }

    initPageReader({
        async load() {
            const res = await fetch(sourceUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                throw new Error(`Comic manifest request failed (HTTP ${res.status})`);
            }
            const info = await res.json();
            total = Number(info.pages) || 0;
            return { pageCount: total };
        },

        async renderPage(n) {
            const img = await loadImage(n);
            // The same element cannot live in two places, so hand back a clone
            // when a spread shows a page that is already mounted.
            return img.isConnected ? img.cloneNode(true) : img;
        },

        prefetch(n) {
            if (n >= 1 && n <= total) {
                loadImage(n).catch(() => { /* prefetch failures are silent */ });
            }
        },
    }).catch((e) => {
        console.error('Comic reader failed to start:', e);
    });
}
