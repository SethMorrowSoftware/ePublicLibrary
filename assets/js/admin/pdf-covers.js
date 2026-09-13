/**
 * Render PDF covers in the admin's browser and upload them.
 *
 * Rasterising a PDF needs Ghostscript-backed Imagick, which plenty of shared
 * hosts do not have. Rather than leave those books cover-less, the browser
 * already has a PDF renderer (pdf.js, loaded by the page) — so page 1 is
 * drawn to a canvas here and POSTed to api/covers.php, which is the same
 * admin-only endpoint the client-side cover cache uses.
 *
 * One book at a time: this is a background chore, not a race, and serialising
 * keeps memory flat when there are hundreds of PDFs.
 */

import { post } from '../shared/api.js';

const COVER_WIDTH = 400;   // matches ThumbnailService::TARGET_WIDTH

const root = document.getElementById('pdf-cover-tool');
if (root) init(root);

function init(root) {
    const startBtn = document.getElementById('pdf-cover-start');
    const status   = document.getElementById('pdf-cover-status');
    const log      = document.getElementById('pdf-cover-log');

    let pending = [];
    try {
        pending = JSON.parse(root.dataset.pending || '[]');
    } catch {
        pending = [];
    }

    startBtn?.addEventListener('click', async () => {
        if (typeof window.pdfjsLib === 'undefined') {
            status.textContent = 'The PDF engine has not loaded yet — wait a moment and try again.';
            return;
        }
        // Same-origin worker (vendored copy, or the importScripts shim) — a
        // Worker script cannot be cross-origin.
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = root.dataset.workerSrc;

        startBtn.disabled = true;
        let done = 0;
        let failed = 0;

        for (const book of pending) {
            status.textContent = `Rendering ${done + failed + 1} of ${pending.length}…`;
            try {
                const dataUrl = await renderCover(root.dataset.downloadUrl, book.uuid);
                await post(root.dataset.coversUrl, { uuid: book.uuid, data_url: dataUrl });
                done++;
                addRow(log, book.title, true, 'cover uploaded');
            } catch (e) {
                failed++;
                addRow(log, book.title, false, e.message || 'could not render');
            }
        }

        status.textContent = `Finished — ${done} cover${done === 1 ? '' : 's'} saved`
                           + (failed ? `, ${failed} failed` : '') + '.';
        startBtn.textContent = 'Reload to see the new covers';
        startBtn.disabled = false;
        startBtn.addEventListener('click', () => window.location.reload(), { once: true });
    });
}

async function renderCover(downloadUrl, uuid) {
    const url = `${downloadUrl}?b=${encodeURIComponent(uuid)}&stream=1`;
    const doc = await window.pdfjsLib.getDocument({ url, withCredentials: true }).promise;
    try {
        const page = await doc.getPage(1);
        const base = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: COVER_WIDTH / base.width });

        const canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);
        const ctx = canvas.getContext('2d', { alpha: false });
        // PDFs assume paper: without this, transparent regions render black.
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        await page.render({ canvasContext: ctx, viewport }).promise;
        return canvas.toDataURL('image/jpeg', 0.85);
    } finally {
        doc.destroy();
    }
}

function addRow(log, title, ok, note) {
    if (!log) return;
    const li = document.createElement('li');
    li.className = ok ? 'is-ok' : 'is-bad';
    const strong = document.createElement('strong');
    strong.textContent = title;
    const span = document.createElement('span');
    span.textContent = ' — ' + note;
    li.append(strong, span);
    log.appendChild(li);
}
