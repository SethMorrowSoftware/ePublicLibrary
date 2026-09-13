/**
 * Bookmarks panel. Lists bookmarks, adds a bookmark at the current position,
 * deletes bookmarks. Syncs to server when authed; falls back to localStorage.
 */

import { post, get, request } from '../shared/api.js';
import { showToast } from './toast.js';

const STORAGE_KEY_PREFIX = 'elib-bookmarks-';

export function initBookmarks(ctx) {
    const { rendition, bookUuid, isGuest, bookmarksUrl } = ctx;
    const listEl   = document.getElementById('bookmarks-list');
    const addBtn   = document.getElementById('btn-bookmark');
    let currentCfi = ctx.initialCfi;

    rendition.on('relocated', (loc) => { currentCfi = loc.start.cfi; });

    addBtn?.addEventListener('click', async () => {
        if (!currentCfi) return;
        const label = currentChapterLabel(ctx.book, currentCfi) || 'Bookmark';
        try {
            if (isGuest) {
                addLocal({ cfi: currentCfi, label, created_at: new Date().toISOString() });
            } else {
                const res = await post(bookmarksUrl, {
                    uuid: bookUuid, cfi: currentCfi, label,
                });
                renderRow({ id: res.id, cfi: currentCfi, label, created_at: new Date().toISOString() });
            }
            showToast('Bookmark added');
        } catch (e) {
            showToast('Could not save bookmark', 'error');
        }
    });

    function addLocal(bookmark) {
        const all = readLocal();
        bookmark.id = 'local-' + Date.now();
        all.unshift(bookmark);
        localStorage.setItem(STORAGE_KEY_PREFIX + bookUuid, JSON.stringify(all));
        renderRow(bookmark);
    }

    function readLocal() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY_PREFIX + bookUuid) || '[]'); }
        catch { return []; }
    }

    function renderRow(b) {
        if (!listEl) return;
        // Remove empty-state placeholder if present
        listEl.querySelectorAll('.reader-panel-empty').forEach(n => n.remove());
        const li = document.createElement('li');
        li.dataset.cfi = b.cfi;
        li.dataset.id  = b.id;
        const jump = document.createElement('button');
        jump.type = 'button';
        jump.className = 'bookmark-jump';
        jump.textContent = b.label || 'Bookmark';
        jump.addEventListener('click', () => rendition.display(b.cfi));
        const date = document.createElement('small');
        date.textContent = new Date(b.created_at).toLocaleString();
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'bookmark-delete';
        del.setAttribute('aria-label', 'Delete bookmark');
        del.textContent = '×';
        del.addEventListener('click', async () => {
            if (String(b.id).startsWith('local-') || isGuest) {
                const all = readLocal().filter(x => x.id !== b.id);
                localStorage.setItem(STORAGE_KEY_PREFIX + bookUuid, JSON.stringify(all));
            } else {
                try {
                    await request(`${bookmarksUrl}?id=${encodeURIComponent(b.id)}`, { method: 'DELETE' });
                } catch (e) { showToast('Could not delete bookmark', 'error'); return; }
            }
            li.remove();
            if (!listEl.children.length) {
                const empty = document.createElement('li');
                empty.className = 'reader-panel-empty';
                empty.textContent = 'No bookmarks yet.';
                listEl.appendChild(empty);
            }
        });
        li.appendChild(jump);
        li.appendChild(date);
        li.appendChild(del);
        listEl.insertBefore(li, listEl.firstChild);
    }

    // Wire existing server-rendered bookmarks
    listEl?.querySelectorAll('[data-cfi]').forEach((li) => {
        const cfi = li.dataset.cfi;
        const id  = li.dataset.id;
        li.querySelector('.bookmark-jump')?.addEventListener('click', () => rendition.display(cfi));
        li.querySelector('.bookmark-delete')?.addEventListener('click', async () => {
            try {
                await request(`${bookmarksUrl}?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
                li.remove();
            } catch { showToast('Could not delete bookmark', 'error'); }
        });
    });

    // For guests, hydrate from localStorage
    if (isGuest) {
        const local = readLocal();
        local.forEach(renderRow);
    }
}

function currentChapterLabel(book, cfi) {
    try {
        const spineItem = book.spine.get(cfi);
        if (!spineItem) return null;
        const toc = book.navigation?.toc || [];
        for (const item of toc) {
            if (item.href && item.href.includes(spineItem.href.split('#')[0])) {
                return item.label.trim();
            }
        }
    } catch {}
    return null;
}
