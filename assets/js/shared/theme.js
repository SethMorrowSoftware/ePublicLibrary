/**
 * Theme switcher.
 *
 * Theme state lives on <html data-theme="light|dark|sepia">. Persisted in
 * localStorage so it survives reloads.
 */

const STORAGE_KEY = 'elib-theme';

export function getTheme() {
    return localStorage.getItem(STORAGE_KEY) || 'auto';
}

export function applyTheme(theme) {
    const root = document.documentElement;
    if (theme === 'auto') {
        root.removeAttribute('data-theme');
    } else {
        root.setAttribute('data-theme', theme);
    }
    localStorage.setItem(STORAGE_KEY, theme);
}

/** What `auto` currently resolves to. */
function systemTheme() {
    return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/**
 * Step through auto → opposite-of-system → same-as-system → auto.
 *
 * The order matters: cycling `auto → light` first meant that on a
 * light-preferring machine the first click changed nothing visible and the
 * button felt broken. Starting with the opposite of whatever is on screen
 * guarantees the first press does something, while keeping `auto` reachable.
 */
export function cycleTheme() {
    const current = getTheme();
    const system = systemTheme();
    const next = current === 'auto'
        ? (system === 'dark' ? 'light' : 'dark')
        : (current === system ? 'auto' : system);
    applyTheme(next);
    return next;
}

const LABELS = {
    auto:  'Theme: follow system. Activate for light or dark.',
    light: 'Theme: light. Activate to change.',
    dark:  'Theme: dark. Activate to change.',
};

export function initThemeToggle() {
    // Apply the persisted theme as early as possible. (head-meta.php already
    // does this pre-paint; repeating it here keeps the module self-contained.)
    const stored = getTheme();
    if (stored !== 'auto') {
        document.documentElement.setAttribute('data-theme', stored);
    }
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;

    const describe = (theme) => {
        btn.setAttribute('aria-label', LABELS[theme] || LABELS.auto);
        btn.setAttribute('title', LABELS[theme] || LABELS.auto);
        btn.dataset.theme = theme;
    };
    describe(stored);

    btn.addEventListener('click', () => describe(cycleTheme()));
}
