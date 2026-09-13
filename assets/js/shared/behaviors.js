/**
 * Small delegated behaviours shared by every page.
 *
 * These replace inline `onclick` / `onsubmit` / `onchange` attributes, which
 * the Content-Security-Policy blocks (script-src carries a nonce and no
 * 'unsafe-inline'). Inline handlers failed silently, which was worse than
 * not having them: destructive forms submitted with no confirmation at all,
 * and auto-submitting selects simply did nothing.
 *
 * Markup contract:
 *   <form data-confirm="Delete this?">          confirm before submitting
 *   <button data-confirm="Sure?">               confirm before activating
 *   <select data-autosubmit>                    submit its form on change
 *   <details data-close-outside>                close when clicked away from
 */

export function initBehaviors(root = document) {
    root.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        // A submit button's own message wins over the form's.
        const trigger = e.submitter;
        const message = trigger?.dataset?.confirm || form.dataset.confirm;
        if (message && !window.confirm(message)) {
            e.preventDefault();
        }
    });

    root.addEventListener('click', (e) => {
        const el = e.target.closest('[data-confirm]');
        // Submit buttons are handled by the submit listener above, so that the
        // question is asked once and the submitter is known.
        if (!el || el.tagName === 'FORM' || el.type === 'submit') return;
        if (!window.confirm(el.dataset.confirm)) {
            e.preventDefault();
            e.stopPropagation();
        }
    });

    root.addEventListener('change', (e) => {
        const el = e.target;
        if (el instanceof HTMLSelectElement && el.hasAttribute('data-autosubmit') && el.form) {
            el.form.requestSubmit ? el.form.requestSubmit() : el.form.submit();
        }
    });

    // <details> menus should close when you click elsewhere or press Escape —
    // the element does neither on its own.
    document.addEventListener('click', (e) => {
        document.querySelectorAll('details[data-close-outside][open]').forEach((d) => {
            if (!d.contains(e.target)) d.open = false;
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('details[data-close-outside][open]').forEach((d) => {
            d.open = false;
            d.querySelector('summary')?.focus();
        });
    });
}
