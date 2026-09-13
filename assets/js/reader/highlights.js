/**
 * Highlights — text-selection capture, color picker popover, server sync.
 *
 * UX:
 *   1. User selects text inside the rendition iframe.
 *   2. A small color-picker popover appears near the selection.
 *   3. Clicking a color creates a highlight (server-synced for authed users;
 *      localStorage fallback for guests).
 *   4. Existing highlights re-render whenever a chapter is displayed.
 *   5. Tapping an existing highlight opens an edit popover (note + color
 *      change + delete).
 *
 * Notes:
 *   - epub.js 0.3.93's `rendition.annotations.add(type, cfiRange, ...)` is
 *     the rendering primitive. It overlays a colored span in the iframe.
 *   - On guest accounts, we use IDs prefixed with `local-` and persist to
 *     localStorage.
 */

import { post, get, request } from '../shared/api.js';
import { showToast } from './toast.js';

const COLORS = ['yellow', 'green', 'blue', 'pink', 'orange'];
const COLOR_RGBA = {
    yellow: 'rgba(255, 234, 100, 0.45)',
    green:  'rgba(125, 220, 150, 0.45)',
    blue:   'rgba(120, 190, 255, 0.45)',
    pink:   'rgba(255, 150, 200, 0.45)',
    orange: 'rgba(255, 180, 100, 0.50)',
};

export function initHighlights(ctx) {
    const { rendition, book, bookUuid, isGuest, highlightsUrl } = ctx;
    if (!rendition) return;

    /** id → highlight row */
    const store = new Map();

    const listEl = document.getElementById('highlights-list');
    const countEl = document.getElementById('highlights-count');
    const popover = createPopover();
    const editPopover = createEditPopover();

    let currentSelection = null;  // { cfiRange, text, chapter }
    let currentEditing = null;    // highlight id

    /* ---- Load existing ---- */
    async function loadAll() {
        try {
            const items = isGuest ? readLocal() : await get(highlightsUrl + '?b=' + encodeURIComponent(bookUuid));
            (items || []).forEach((h) => {
                store.set(String(h.id), h);
                applyToRendition(h);
            });
            renderList();
        } catch {
            // 401 etc. — fall back to local
            readLocal().forEach((h) => {
                store.set(String(h.id), h);
                applyToRendition(h);
            });
            renderList();
        }
    }

    function applyToRendition(h) {
        try {
            rendition.annotations.add(
                'highlight',
                h.cfi_range,
                { id: h.id },
                () => onHighlightClick(String(h.id)),
                `elib-hl elib-hl-${h.color || 'yellow'}`,
                {
                    'background-color': COLOR_RGBA[h.color] || COLOR_RGBA.yellow,
                    'cursor': 'pointer',
                    'mix-blend-mode': 'multiply',
                }
            );
        } catch (e) {
            // Older epub.js may throw if annotation already exists. Ignore.
        }
    }

    function removeFromRendition(h) {
        try { rendition.annotations.remove(h.cfi_range, 'highlight'); }
        catch { /* ignore */ }
    }

    function reapplyAll() {
        store.forEach(applyToRendition);
    }

    /* ---- Selection → color picker popover ---- */
    rendition.on('rendered', () => {
        // After a relocation, the iframe is fresh — highlights need re-application
        // (epub.js 0.3.x retains them across pages but be defensive).
        reapplyAll();
    });

    rendition.on('selected', (cfiRange, contents) => {
        const text = (contents.window.getSelection().toString() || '').trim();
        if (!text) return;
        currentSelection = {
            cfiRange,
            text,
            chapter: ctx.currentChapter || null,
        };
        showColorPicker(contents);
    });

    /* ---- Color picker ---- */
    function createPopover() {
        const el = document.createElement('div');
        el.className = 'highlight-popover';
        el.setAttribute('role', 'menu');
        el.hidden = true;
        el.innerHTML = COLORS.map((c) =>
            `<button type="button" class="highlight-swatch highlight-swatch-${c}" data-color="${c}"
                     aria-label="Highlight ${c}" title="${c}"></button>`
        ).join('');
        document.body.appendChild(el);
        el.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-color]');
            if (!btn) return;
            createHighlight(btn.dataset.color);
        });
        return el;
    }

    function showColorPicker(contents) {
        const sel = contents.window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const rect = sel.getRangeAt(0).getBoundingClientRect();
        // Translate iframe coords to viewport coords
        const iframeRect = ctx.viewer.querySelector('iframe')?.getBoundingClientRect();
        const top  = (iframeRect?.top || 0) + rect.bottom + 8;
        const left = (iframeRect?.left || 0) + rect.left + (rect.width / 2);
        popover.hidden = false;
        popover.style.top  = `${Math.max(8, top + window.scrollY)}px`;
        popover.style.left = `${Math.max(8, left + window.scrollX - popover.offsetWidth / 2)}px`;
    }

    function hideColorPicker() {
        popover.hidden = true;
        currentSelection = null;
    }

    document.addEventListener('mousedown', (e) => {
        if (popover.contains(e.target)) return;
        hideColorPicker();
    });

    /* ---- Edit popover ---- */
    function createEditPopover() {
        const el = document.createElement('div');
        el.className = 'highlight-edit-popover';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', 'Edit highlight');
        el.hidden = true;
        el.innerHTML = `
            <div class="highlight-edit-header">
                <div class="highlight-edit-swatches">
                    ${COLORS.map((c) =>
                        `<button type="button" class="highlight-swatch highlight-swatch-${c}" data-color="${c}" aria-label="Set ${c}"></button>`
                    ).join('')}
                </div>
                <button type="button" class="highlight-edit-delete" aria-label="Delete highlight">×</button>
            </div>
            <textarea class="highlight-edit-note" rows="3" placeholder="Add a note (optional)"></textarea>
            <div class="highlight-edit-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-action="cancel">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" data-action="save">Save note</button>
            </div>`;
        document.body.appendChild(el);
        el.addEventListener('click', async (e) => {
            const colorBtn = e.target.closest('[data-color]');
            if (colorBtn) {
                await updateHighlight(currentEditing, { color: colorBtn.dataset.color });
                hideEditPopover();
                return;
            }
            if (e.target.closest('.highlight-edit-delete')) {
                await removeHighlight(currentEditing);
                hideEditPopover();
                return;
            }
            const action = e.target.closest('[data-action]')?.dataset.action;
            if (action === 'cancel') { hideEditPopover(); return; }
            if (action === 'save') {
                const note = el.querySelector('.highlight-edit-note').value.trim();
                await updateHighlight(currentEditing, { note: note || null });
                hideEditPopover();
            }
        });
        return el;
    }

    function showEditPopover(highlightId, anchorEl) {
        const h = store.get(String(highlightId));
        if (!h) return;
        currentEditing = String(highlightId);
        editPopover.querySelector('.highlight-edit-note').value = h.note || '';
        editPopover.hidden = false;
        // Position near the click location
        const rect = anchorEl?.getBoundingClientRect();
        if (rect) {
            editPopover.style.top  = `${Math.max(8, rect.bottom + window.scrollY + 8)}px`;
            editPopover.style.left = `${Math.max(8, rect.left + window.scrollX)}px`;
        }
    }

    function hideEditPopover() {
        editPopover.hidden = true;
        currentEditing = null;
    }

    document.addEventListener('mousedown', (e) => {
        if (editPopover.contains(e.target)) return;
        if (e.target.closest && e.target.closest('.elib-hl')) return;
        hideEditPopover();
    });

    /* ---- CRUD ---- */
    async function createHighlight(color) {
        if (!currentSelection) return;
        const payload = {
            uuid:      bookUuid,
            cfi_range: currentSelection.cfiRange,
            text:      currentSelection.text,
            chapter:   currentSelection.chapter,
            color,
        };
        const optimistic = {
            id: 'local-' + Date.now(),
            book_id: null,
            user_id: null,
            cfi_range: currentSelection.cfiRange,
            text:    currentSelection.text,
            note:    null,
            color,
            chapter: currentSelection.chapter,
            created_at: new Date().toISOString(),
        };
        store.set(optimistic.id, optimistic);
        applyToRendition(optimistic);
        renderList();
        hideColorPicker();

        if (isGuest) {
            writeLocal();
            showToast('Highlight saved (this browser)');
            return;
        }
        try {
            const res = await post(highlightsUrl, payload);
            // Swap optimistic id for the real one
            store.delete(optimistic.id);
            removeFromRendition(optimistic);
            const real = { ...optimistic, id: res.id };
            store.set(String(real.id), real);
            applyToRendition(real);
            renderList();
        } catch (e) {
            showToast('Could not save highlight — falling back to this browser.', 'error');
            writeLocal();
        }
    }

    async function updateHighlight(id, fields) {
        const h = store.get(String(id));
        if (!h) return;
        const wasColor = h.color;
        Object.assign(h, fields);
        // Re-apply with new color
        if (fields.color && fields.color !== wasColor) {
            removeFromRendition({ ...h, color: wasColor });
            applyToRendition(h);
        }
        renderList();
        if (isGuest || String(id).startsWith('local-')) {
            writeLocal();
            return;
        }
        try {
            await request(`${highlightsUrl}?id=${encodeURIComponent(id)}&_method=PATCH`,
                          { method: 'POST', body: fields });
        } catch {
            showToast('Could not save changes.', 'error');
        }
    }

    async function removeHighlight(id) {
        const h = store.get(String(id));
        if (!h) return;
        store.delete(String(id));
        removeFromRendition(h);
        renderList();
        if (isGuest || String(id).startsWith('local-')) {
            writeLocal();
            return;
        }
        try {
            await request(`${highlightsUrl}?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
        } catch {
            showToast('Could not delete highlight.', 'error');
        }
    }

    /** Triggered when user clicks an existing highlight in the rendition. */
    function onHighlightClick(id) {
        const range = store.get(String(id))?.cfi_range;
        if (!range) return;
        // Use the iframe's range bounding rect for popover positioning
        try {
            const node = ctx.viewer.querySelector('iframe')
                ?.contentDocument
                ?.querySelector(`.elib-hl[data-epubcfi="${CSS.escape(range)}"]`);
            showEditPopover(id, node);
        } catch {
            showEditPopover(id, null);
        }
    }

    /* ---- Panel list rendering ---- */
    function renderList() {
        if (!listEl) return;
        const arr = Array.from(store.values()).sort((a, b) =>
            new Date(a.created_at) - new Date(b.created_at)
        );
        if (countEl) countEl.textContent = String(arr.length);
        if (arr.length === 0) {
            listEl.innerHTML = '<li class="reader-panel-empty">Select text in the book to add a highlight.</li>';
            return;
        }
        listEl.innerHTML = '';
        arr.forEach((h) => {
            const li = document.createElement('li');
            li.className = `highlight-item highlight-color-${h.color}`;
            li.innerHTML = `
                <button type="button" class="highlight-jump" data-cfi="${escapeAttr(h.cfi_range)}">
                    <span class="highlight-text">${escapeHtml(h.text)}</span>
                    ${h.note ? `<span class="highlight-note">${escapeHtml(h.note)}</span>` : ''}
                    ${h.chapter ? `<span class="highlight-chapter">${escapeHtml(h.chapter)}</span>` : ''}
                </button>
                <button type="button" class="highlight-remove" aria-label="Delete highlight" data-id="${escapeAttr(String(h.id))}">×</button>
            `;
            listEl.appendChild(li);
        });
    }

    listEl?.addEventListener('click', (e) => {
        const jump = e.target.closest('.highlight-jump');
        if (jump) {
            rendition.display(jump.dataset.cfi);
            // Close the panel for focus
            document.getElementById('panel-highlights')?.setAttribute('hidden', '');
            return;
        }
        const del = e.target.closest('.highlight-remove');
        if (del) {
            removeHighlight(del.dataset.id);
        }
    });

    /* ---- Export to Markdown ---- */
    document.getElementById('btn-highlights-export')?.addEventListener('click', () => {
        const arr = Array.from(store.values()).sort((a, b) =>
            new Date(a.created_at) - new Date(b.created_at)
        );
        if (arr.length === 0) {
            showToast('No highlights to export.', 'error');
            return;
        }
        const bookTitle = document.querySelector('meta[name=book-title]')?.content || 'Book';
        const bookAuthor = document.querySelector('meta[name=book-author]')?.content || '';
        const lines = [
            `# Highlights from “${bookTitle}”`,
            bookAuthor ? `by ${bookAuthor}` : '',
            '',
            `Exported ${new Date().toISOString().slice(0, 10)} · ${arr.length} highlight${arr.length === 1 ? '' : 's'}.`,
            '',
            '---',
            '',
        ];
        arr.forEach((h) => {
            if (h.chapter) lines.push(`### ${h.chapter}`);
            lines.push(`> ${h.text.replace(/\n/g, '\n> ')}`);
            if (h.note) lines.push('', `**Note:** ${h.note}`);
            lines.push('', '---', '');
        });
        const blob = new Blob([lines.join('\n')], { type: 'text/markdown' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = bookTitle.replace(/[^A-Za-z0-9._-]+/g, '_') + '-highlights.md';
        a.click();
        URL.revokeObjectURL(a.href);
    });

    /* ---- Local storage fallback ---- */
    function localKey() { return 'elib-highlights-' + bookUuid; }
    function readLocal() {
        try { return JSON.parse(localStorage.getItem(localKey()) || '[]'); }
        catch { return []; }
    }
    function writeLocal() {
        localStorage.setItem(localKey(), JSON.stringify(Array.from(store.values())));
    }

    /* ---- Helpers ---- */
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function escapeAttr(s) { return escapeHtml(s); }

    /* ---- Boot ---- */
    loadAll();
}
