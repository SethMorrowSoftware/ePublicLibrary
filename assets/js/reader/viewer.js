/**
 * Initialize the epub.js Book + Rendition.
 *
 * Returns { book, rendition }.
 */

export async function initViewer(ctx) {
    // `openAs: 'epub'` is required, not cosmetic. epub.js sniffs the input
    // type from the URL's file extension, and our download endpoint is
    // `api/download.php?b=…` — no `.epub` on the end — so it concluded the URL
    // pointed at an *unpacked* EPUB directory and went looking for
    // `api/META-INF/container.xml`, which 404s. Telling it the input is a
    // packed archive makes it fetch the bytes and unzip them.
    const book = ePub(ctx.bookUrl, {
        openAs: 'epub',
        requestCredentials: 'same-origin',
    });
    // Flow preference is set in the Settings panel ("Continuous scroll").
    // Read it once at init — changing the flow requires re-creating the
    // rendition, which the settings module handles via a page reload.
    let flow = 'paginated';
    try {
        const s = JSON.parse(localStorage.getItem('elib-reader-settings') || '{}');
        if (s.continuousScroll) flow = 'scrolled-doc';
    } catch { /* ignore */ }

    const rendition = book.renderTo(ctx.viewer, {
        width: '100%',
        height: '100%',
        flow,
        spread: flow === 'paginated' ? 'auto' : 'none',
        manager: flow === 'paginated' ? 'default' : 'continuous',
    });

    // Apply persisted theme to the rendition (sepia / dark / light)
    applyRenditionTheme(rendition, getStoredTheme());

    // Display the initial location (resumed CFI if any).
    try {
        await rendition.display(ctx.initialCfi || undefined);
    } catch (e) {
        // Some EPUBs throw on initial CFI; fall back to start.
        await rendition.display();
    }

    // Generate location index for percentage calculation in the background.
    book.ready.then(() => {
        book.locations.generate(1024).catch(() => { /* not critical */ });
    });

    return { book, rendition };
}

export function applyRenditionTheme(rendition, theme) {
    const themes = {
        light: { body: { background: '#fdfcfb', color: '#1c1917' } },
        dark:  { body: { background: '#0f0e0d', color: '#fafaf9' } },
        sepia: { body: { background: '#f4ecd8', color: '#5b4636' } },
    };
    rendition.themes.register('light', themes.light);
    rendition.themes.register('dark',  themes.dark);
    rendition.themes.register('sepia', themes.sepia);
    const resolved = theme === 'auto'
        ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : theme;
    rendition.themes.select(resolved);
    document.getElementById('reader').setAttribute('data-theme', resolved);
}

function getStoredTheme() {
    return localStorage.getItem('elib-reader-theme') || 'auto';
}
