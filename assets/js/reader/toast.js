/**
 * Reader toast — the small transient message strip at the bottom of the
 * reader shell.
 *
 * This lives in its own module rather than in reader.js on purpose. An entry
 * module must never be imported by its own dependencies: the browser keys the
 * module registry by resolved URL, and the entry is loaded as
 * `reader.js?v=<asset-version>` while a relative `../reader.js` import
 * resolves without the query. That is two different URLs, so the browser
 * evaluates the module twice and every listener gets bound twice — which
 * showed up as reader panels opening and instantly closing again.
 */

const AUTO_HIDE_MS = 2600;

export function showToast(message, kind = 'info') {
    const el = document.getElementById('reader-toast');
    if (!el) return;
    el.textContent = message;
    el.dataset.kind = kind;
    el.hidden = false;
    clearTimeout(showToast._timer);
    showToast._timer = setTimeout(() => { el.hidden = true; }, AUTO_HIDE_MS);
}
