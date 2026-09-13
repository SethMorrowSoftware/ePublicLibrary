/**
 * Reader entry — orchestrates the reader modules.
 *
 * Depends on epub.js + jszip being loaded as classic scripts (see
 * views/layouts/reader.php). They register globals `ePub` and `JSZip`.
 */

import { initViewer }       from './reader/viewer.js';
import { initNavigation }   from './reader/navigation.js';
import { initSettings }     from './reader/settings.js';
import { initBookmarks }    from './reader/bookmarks.js';
import { initProgress }     from './reader/progress.js';
import { initToc }          from './reader/toc.js';
import { initPanels }       from './reader/panels.js';
import { initHighlights }   from './reader/highlights.js';
import { initInBookSearch } from './reader/in-book-search.js';
import { initTts }          from './reader/tts.js';
import { initDictionary }   from './reader/dictionary.js';
import { initSessions }     from './reader/sessions.js';
import { initImmersive }    from './reader/immersive.js';
import { registerServiceWorker } from './shared/sw-register.js';
import { showToast } from './reader/toast.js';

registerServiceWorker();

const shell = document.getElementById('reader');
if (!shell) {
    console.error('Reader shell #reader is missing.');
} else {
    const ctx = {
        shell,
        bookUuid:       shell.dataset.bookUuid,
        bookUrl:        shell.dataset.bookUrl,
        initialCfi:     shell.dataset.initialCfi || null,
        isGuest:        shell.dataset.isGuest === '1',
        progressUrl:    shell.dataset.progressUrl,
        bookmarksUrl:   shell.dataset.bookmarksUrl,
        highlightsUrl:  shell.dataset.highlightsUrl,
        viewer: document.getElementById('epub-viewer'),
    };

    function ready() {
        if (typeof ePub === 'undefined') {
            setTimeout(ready, 50);
            return;
        }
        boot(ctx);
    }
    ready();
}

async function boot(ctx) {
    try {
        const view = await initViewer(ctx);
        ctx.book = view.book;
        ctx.rendition = view.rendition;

        initSettings(ctx);
        initPanels(ctx);
        initNavigation(ctx);
        initToc(ctx);
        initBookmarks(ctx);
        initProgress(ctx);
        initHighlights(ctx);
        initInBookSearch(ctx);
        initTts(ctx);
        initDictionary(ctx);
        initSessions(ctx);
        initImmersive(ctx);

        document.querySelectorAll('[data-dismiss]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.dismiss);
                if (target) target.hidden = true;
            });
        });

    } catch (e) {
        console.error('Reader boot failed:', e);
        showToast('Sorry — this book could not be opened.', 'error');
    }
}
