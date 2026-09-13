/**
 * Library page entry — search autocomplete, theme toggle, sort, book-card a11y.
 */

import { initThemeToggle } from './shared/theme.js';
import { combobox } from './shared/combobox.js';
import { get } from './shared/api.js';
import { registerServiceWorker } from './shared/sw-register.js';
import { initBehaviors } from './shared/behaviors.js';

registerServiceWorker();
initBehaviors();

/* ---- Theme ---- */
initThemeToggle();

/* ---- Sort dropdowns auto-submit ---- */
['sortBy', 'sortOrder'].forEach((id) => {
    const el = document.getElementById(id);
    if (el && el.form) {
        el.addEventListener('change', () => el.form.submit());
    }
});

/* ---- Search autocomplete ---- */
const searchInput = document.getElementById('searchInput');
const listbox = document.getElementById('autocomplete-listbox');
if (searchInput && listbox) {
    combobox({
        input: searchInput,
        listbox,
        async fetch(term) {
            try {
                return await get(`api/search.php?q=${encodeURIComponent(term)}`);
            } catch {
                return [];
            }
        },
        onSelect(value) {
            searchInput.value = value;
            searchInput.form.submit();
        },
    });

    // Position listbox under input
    const wrap = searchInput.closest('.search-input-wrapper');
    if (wrap) {
        const r = wrap.getBoundingClientRect();
        listbox.style.top = `${r.bottom + window.scrollY + 4}px`;
        listbox.style.left = `${r.left + window.scrollX}px`;
        listbox.style.width = `${r.width}px`;
    }
    window.addEventListener('resize', () => {
        if (!wrap) return;
        const r = wrap.getBoundingClientRect();
        listbox.style.top = `${r.bottom + window.scrollY + 4}px`;
        listbox.style.left = `${r.left + window.scrollX}px`;
        listbox.style.width = `${r.width}px`;
    });
}

/* ---- Book card keyboard activation ---- */
document.querySelectorAll('.book-card').forEach((card) => {
    const readUrl = card.dataset.readUrl;
    if (!readUrl) return;
    card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            // Don't intercept when focus is inside an interactive child
            if (e.target.closest('a, button')) return;
            e.preventDefault();
            window.location.href = readUrl;
        }
    });
    card.addEventListener('click', (e) => {
        // Allow native link clicks (download, details) to bubble through
        if (e.target.closest('a, button')) return;
        window.location.href = readUrl;
    });
});

/* ---- Lazy cover loading ---- *
 * Covers are already <div style="background-image"> in the template, so the
 * browser fetches them as needed. If we add a lazy hint, IntersectionObserver
 * can start them only when scrolled near. The current implementation relies on
 * browser native lazy behavior; explicit observer can be added later.
 */
