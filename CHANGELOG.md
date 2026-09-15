# Changelog

## 1.4.1 — Library home and folder-import fixes

### Fixed

- **Folder import left cover art behind.** The importer only looked for a
  cover *inside* each file, so the legacy `books/covers/` thumbnails — and any
  `Title.jpg` or Calibre-style `cover.jpg` beside a book — were ignored. New
  `CoverSidecar` finds them (same-name image, `cover.jpg`/`folder.jpg` in a
  one-book folder, then the v7.x `covers/<md5(relative path)>.jpg` and the
  older underscore naming), they take precedence over the embedded image, and
  the import report now has a *Cover* column saying where each one came from.
- **Re-running the import now repairs covers.** Books already in the library
  are still skipped by checksum, but a skipped book with no cover gets one
  attached — from the sidecar, or re-extracted from the file — instead of
  just being reported as a duplicate. Re-import `books/` once to fix a
  library imported before this release.
- EPUB covers whose manifest href was percent-encoded (`Cover%20Image.jpg`)
  or differed in case from the archive entry were never found.
- Without GD, a JPEG cover is stored as-is rather than silently dropped.
- **"See all" and "Browse all" reloaded the home page.** `index.php` only
  switched to the list for search and filter parameters; `sort`, `order` and
  `page` now open the full sorted, paginated grid, so the header's sort
  selects work from the home page too. The list heading says how it is
  ordered ("newest first", "by author").
- The folder scan skips dotfiles, so macOS `._Title.epub` resource forks no
  longer show up as failed imports.

### Changed

- **Rails no longer scroll sideways.** "Recently added", "Top rated" and
  "You might also like" wrap into the same grid the library uses, and the
  home page now ends with the library itself — the first page of the A–Z
  grid with pagination — instead of a link to it. Both views page by 24.
- The empty-library state is shared by the home page and the list, and
  offers admins the folder importer alongside the uploader.

## 1.4.0 — Multi-format library

Phase 5. The library now holds **PDFs and comics** alongside EPUBs, with a
shared page-based reader for the two paginated formats. This release also
fixes a set of defects that made parts of the previous release unusable in
production — most importantly an `.htaccess` rule that returned 403 for every
PHP file in the install.

### Added

- **PDF support**
  - `PdfParser` reads title, author, subject, keywords, creation date and
    page count from the `/Info` dictionary, falling back to XMP. It inflates
    FlateDecode object streams first, so metadata still surfaces on modern
    PDFs where the catalogue is compressed.
  - `views/reader/pdf.php` renders pages with pdf.js, range-requesting the
    file so first paint does not wait on the whole document.
  - Covers rasterise from page 1 with Imagick when the host has it. When it
    does not, *Admin → Thumbnails* renders them in the browser instead and
    POSTs the result to `api/covers.php`.
- **Comic support (CBZ / CBR)**
  - `ComicArchive` lists pages in natural order (`page2` before `page10`) and
    skips `__MACOSX`, dotfiles and `Thumbs.db`.
  - `api/comic.php` serves a manifest and one page at a time, with ETags and
    a long immutable cache, so a 400 MB volume opens as fast as a small one.
  - `RarTranscoder` converts CBR to CBZ on import using whichever of
    `bsdtar` / `unar` / `unrar` / `7z` the host has, and renumbers pages into
    a flat sequence. CBRs that are really ZIPs are detected by magic bytes
    and need no unpacker.
- **Shared page reader** for PDF and comics: fit width / height / whole page,
  zoom, two-page spread, right-to-left (manga) order, go-to-page, page list,
  bookmarks, immersive mode, swipe and keyboard navigation. Progress is
  stored as a `page:N` locator in the same `reading_progress` row EPUBs use,
  so "Continue reading" spans every format.
- **Format everywhere**: badges and page counts on book cards, a format facet
  on advanced search, a Format column in the admin book list, per-format
  counts and server-capability reporting on *Admin → Health*.
- **Self-hosted webfonts.** DM Sans and Instrument Serif now ship with the
  app (`assets/fonts/`, SIL OFL 1.1). The UI makes no third-party requests.
- **Installable PWA**: `manifest.php` (base-URL aware), generated app icons,
  and a favicon — the app previously linked a `favicon.ico` that did not exist.
- **`BookImporter`** unifies upload and folder import, which had drifted apart.
  The folder importer is no longer legacy-only: it takes any directory inside
  the install and handles every format.
- `assets/js/shared/behaviors.js` — delegated `data-confirm` / `data-autosubmit`
  handlers, replacing inline event attributes.

### Fixed

- **`.htaccess` denied every request to the application.** The rule meant to
  block `.phtml` / `.php5` used `php[3457]?`, and the optional digit made it
  match plain `.php` as well, so a correctly installed site returned 403
  everywhere. *(This is the one to backport if you are running 1.3.0.)*
- **Admin → Migrations always returned 500.** It ran a `FETCH_KEY_PAIR` query
  over three columns, which throws, before the assoc re-fetch that followed.
- **Full-text search always returned 500.** The list query bound `:ft_term`
  twice, which PDO rejects with native prepares (`HY093`). Any "all fields"
  search of three characters or more hit it.
- **The EPUB reader never opened a book.** epub.js infers the container type
  from the URL's file extension; books stream from `api/download.php?b=…`,
  which has none, so it looked for an unpacked EPUB and 404'd on
  `api/META-INF/container.xml`. Fixed with `openAs: 'epub'`.
- **The CSP blocked the interface it was protecting.** A nonce in `style-src`
  makes browsers ignore `'unsafe-inline'`, which killed every server-rendered
  `style=""` — cover art, progress bars, rating histograms — and the `<style>`
  blocks epub.js injects to theme each chapter. `style-src` no longer carries
  a nonce; `script-src` still does.
- **Inline `onclick` / `onsubmit` / `onchange` handlers were dead** under that
  same CSP. Ten of them: destructive admin forms submitted with no
  confirmation at all, and the Users page role/status selects did nothing.
- **A missing `sessions` table took down the whole site**, including the
  Migrations page that creates it. Session start now falls back to file
  sessions instead of locking the administrator out.
- Web fonts never loaded: `design-system.css` `@import`ed Google Fonts, which
  `style-src 'self'` blocked.
- Editing a highlight's note or colour never persisted — the `_method=PATCH`
  branch in `api/highlights.php` sat after the POST branch that returns first.
  Colour-only edits also wiped the note.
- Search autocomplete only worked on the library index; its listbox is now in
  the layout, like the search box that drives it.
- The mobile header overflowed the viewport by ~125 px: the responsive rules
  targeted `.sort-controls` as a child of `.header-row`, but the markup nested
  it one level deeper.
- An open reader panel covered the toolbar button that opened it.
- The theme toggle's first press from `auto` changed nothing visible on a
  light-preferring machine; it now flips away from the current appearance.
- The guest banner sat on top of the reading-progress readout.
- Comic and PDF pages ignored "fit width" because the stage shrank to fit its
  contents.
- `db.prefix` was configurable but never worked — migrations hard-code table
  names and most queries ignored it. The knob is gone.
- Path containment in `BookFileStorage` and the orphan cleaner used a bare
  prefix match, so a sibling directory sharing the prefix passed.
- The health check only counted `.epub` files on disk, so PDFs and comics
  could never be reported as orphans.
- A failed catalogue INSERT left the uploaded file stranded in storage.
- `ThumbnailService` destroyed an already-freed GD handle.
- Assets are cache-busted per deploy. JavaScript revalidates rather than
  hard-caching, because ES modules import each other by relative path and a
  relative import cannot carry the version query.
- The service worker precached a list that missed every reader module, so the
  offline reader loaded its shell and then failed on the first import.

### Changed

- `read.php` dispatches to `views/reader/{epub,pdf,comic}.php` by format;
  `views/reader/show.php` is now `views/reader/epub.php`.
- Downloads carry the correct extension and MIME type for their format, and
  stream in 128 KB chunks that stop when the client disconnects.
- Sort controls appear only on the library index, where they actually apply.
- `BookFileStorage` stores `{shard}/{uuid}.{ext}` rather than always `.epub`.

### Migration

Run **Admin → Migrations** after upgrading to apply
`0015_book_formats.sql`, which adds `format`, `original_format` and
`page_count` to `books`. Existing rows are marked as EPUB.

## 1.3.0 — Polish

Phase 4 — closes out the original four-phase plan. Adds reading-session
tracking and the stats dashboard it feeds, an immersive reader mode plus a
continuous-scroll layout, offline support via a service worker, admin
quality-of-life improvements (health checks, bulk actions), and email-based
password recovery with a flexible mailer.

### Added

- **Reading session tracking**
  - `ReadingSessionRepository` class with start / heartbeat / aggregations
    (`totals`, `dailyTotals`, `dayStreak`, `topBooks`, `topGenres`,
    `recentSessions`).
  - `api/sessions.php` exposes `start` and `heartbeat` verbs (auth required).
  - `assets/js/reader/sessions.js` opens a session on reader boot, beats
    every 30 s, and closes via `navigator.sendBeacon` on pagehide /
    beforeunload so the tail of each session is captured even on tab close.
    Idle-aware — drops big gaps from the duration estimate.
  - CSRF helper accepts `?_token=` query string as a fallback so beacons
    (which can't set custom headers) can authenticate.

- **Reading stats dashboard** (`stats.php`)
  - 8 top-line tiles: time read, books opened / finished, day streak,
    sessions, words read (estimate), highlights, reviews posted.
  - 30-day bar chart of daily reading time.
  - Most-read books rank list (with covers).
  - Top genres rank list.
  - Recent sessions table.
  - Linked from the user menu (Your shelves · Reading stats · Advanced
    search · Account).

- **Immersive mode** in the reader
  - New `Immersive mode` button in the reader header (`btn-immersive`).
  - `.is-immersive` class on the reader shell hides the header, the
    progress info, and the TTS toolbar; leaves a 3 px progress strip.
  - Center-tap reveals controls briefly; Escape (or Ctrl+Shift+I, F11) exits.
  - Preference persisted in `localStorage`.

- **Continuous-scroll layout** option in reader Settings → Layout.
  - Toggle changes the epub.js rendition `flow` from `paginated` to
    `scrolled-doc`. Reloads the reader to re-create the rendition since
    flow swaps can't happen mid-stream.

- **Service worker** for offline reading
  - `sw.js` at the install root with multi-strategy caching:
    cache-first for the app shell (CSS / JS / fonts / vendored libs),
    network-first for HTML with `offline.html` fallback,
    stale-while-revalidate for cover images,
    cache-first with a 3-book quota for EPUB streams
    (`api/download.php?stream=1`).
  - `assets/js/shared/sw-register.js` registers from both library and
    reader pages. Subdirectory-aware (scope = install base).
  - `.htaccess` adds a `no-cache` rule for `sw.js` plus a
    `Service-Worker-Allowed: /` header.
  - `offline.html` is a tiny self-contained fallback page (no dependencies).

- **Admin health check** (`admin/health.php`)
  - Missing EPUB files: DB rows with no file on disk.
  - Missing covers: books without a cover image on disk.
  - **Orphan EPUB files**: on-disk files with no DB row, with a
    checkbox-selectable bulk delete action (CSRF-confirmed).
  - Orphan covers (informational; listed but not auto-deleted).
  - New `Health` entry in the admin sidebar.

- **Bulk actions** on `admin/books.php`
  - Checkboxes in every row + a "Select all" header checkbox.
  - Sticky bulk toolbar shows selection count + action dropdown
    (Publish / Hide / Mark removed / Delete) + Apply button.
  - Confirmation prompt before the action runs; delete prompt is
    stricter.
  - Admin list now shows **every** book status (Phase 2 added the
    filter to hide non-published books in public views).

- **Email-based password reset**
  - `forgot-password.php` — request a reset link by email. Always shows
    "if that email is registered, a link is on its way" regardless of
    whether the address exists (no account enumeration). Per-IP rate
    limited.
  - `reset-password.php` — land here from the email, set a new password
    (NIST 800-63B-validated). On success: revokes all remember-me tokens
    for the user and signs them out everywhere.
  - `Mailer` class with three drivers:
    - `log` (default): writes to `storage/logs/mail.log` — safe for dev.
    - `mail`: PHP's built-in `mail()` — works on most cPanel hosts.
    - `smtp`: uses PHPMailer if vendored at
      `includes/vendor/PHPMailer/`. Reads config from
      `config('mail.smtp.*')`. Falls back to `log` with a warning if
      PHPMailer is not present.
  - Login page gains a "Forgot your password?" link.

### Changed

- `BookRepository::paginate()` accepts an `include_all_status` flag so
  the admin books page can list hidden/removed entries too.
- `setup.php` writes the new mail config keys (`smtp.*`); existing
  installs can hand-edit `includes/config.php` or run setup again with
  `setup_completed_at = 0`.
- The reader entry imports two new modules: `sessions.js` and
  `immersive.js`. Both bail cleanly when their prerequisites are absent
  (guest user, no immersive button, etc.).
- `config.example.php` documents the three mail drivers and SMTP fields.

### Notes

- The service worker is HTTPS-only (skips registration on plain HTTP),
  except on `localhost` / `127.0.0.1` for development.
- Session-tracking heartbeats are best-effort; if a tab closes before the
  first beat, the session's `ended_at` stays NULL and `duration_seconds`
  is 0. Stats aggregations only use rows with non-zero duration.
- `epub.js` is **still pinned at 0.3.93**. The upgrade is now the
  primary item left on the roadmap — see below.

### Migration notes

No new SQL migrations. The `reading_sessions`, `auth_tokens`, and
`highlights` tables have been in place since Phase 1.

---

## 1.2.0 — Reader features

Phase 3. The reader catches up to commercial parity: highlights and
annotations with notes and color, full-text search across the book,
text-to-speech with adjustable rate and voice picker, double-tap word
lookup against a dictionary API (proxied + cached), and an
OpenDyslexic font option.

### Added

- **Highlights & annotations**
  - Select text in the rendition → a 5-color picker pops up
    (yellow / green / blue / pink / orange) to save the highlight
    with a click.
  - Tap an existing highlight → edit popover lets you change the
    color, add or edit a note, or delete the highlight.
  - Side panel lists all highlights with chapter context and tap-to-jump.
  - **Export to Markdown** — one-click download of all highlights as
    a Markdown file grouped by chapter, each with its note.
  - Server-synced for authed users via `api/highlights.php` (GET/POST/
    PATCH/DELETE); falls back to localStorage for guests.
  - `HighlightRepository` class with `listForBook`, `create`,
    `update`, `delete`, `findById`, plus a 5-color whitelist.
- **In-book search** — search panel iterates the EPUB spine lazily,
  surfacing matches with `<mark>`-highlighted snippets grouped by
  chapter. Tap a snippet to jump.
- **Text-to-speech** — toolbar appears under the header when the TTS
  button is toggled. Uses the browser's `SpeechSynthesis` API (no
  third-party):
  - Play / Pause / Stop / Previous-sentence / Next-sentence buttons
  - Rate slider (0.5×–2×) with live readout
  - Voice picker (lists the user's installed system voices)
  - Auto-advances to the next page at end-of-chunk
  - Visual highlight of the currently-spoken sentence
- **Dictionary popup**
  - Optional setting (off by default to keep selection-for-highlight
    snappy). When enabled, double-tap a word → popover with
    pronunciation and top 3 definitions per part of speech.
  - `api/dictionary.php` proxies `dictionaryapi.dev` and caches each
    word in `storage/cache/dictionary/` for 30 days. Per-IP rate
    limited.
  - Graceful degradation if the upstream is unreachable.
- **OpenDyslexic font option** in the font-family setting — wires an
  `@font-face` block into every rendered chapter iframe pointing at
  `assets/fonts/OpenDyslexic-{Regular,Bold}.woff2`. The font files
  are gitignored; see `assets/fonts/README.md` for download
  instructions.
- New reader-header buttons: search, highlights, listen (TTS).
- New reader panels: `panel-search` and `panel-highlights`, wired
  through the existing focus-trap and click-outside logic.
- Reader shell carries the highlights API URL as a new `data-*`
  attribute so the JS module is path-agnostic.

### Changed

- `views/reader/show.php` adds the new buttons, panels, and TTS
  toolbar; settings panel gains a "Tools" section with the
  dictionary toggle.
- `assets/js/reader.js` orchestrates the four new modules:
  `highlights.js`, `in-book-search.js`, `tts.js`, `dictionary.js`.
- `assets/js/reader/settings.js` injects `@font-face` declarations
  for OpenDyslexic into every rendered iframe so the family resolves
  to local files; falls back gracefully when the files are absent.
- `assets/js/reader/panels.js` registers the two new panels.
- `assets/css/reader.css` grows ~330 lines of Phase-3 styling: color
  swatches, highlight items with per-color accent stripes, in-book
  search results with `<mark>` styling, TTS toolbar, dictionary
  popover, settings toggle group.

### Notes

- `epub.js` is **still pinned at 0.3.93**. A version upgrade was on
  the Phase 3 roadmap but is deferred to a follow-up release so the
  feature surface can be exercised against the known-good version
  first. Highlights are rendered via `rendition.annotations.add()`,
  which 0.3.93 supports.
- Dictionary lookup requires the host to allow outbound HTTPS from
  PHP (`file_get_contents` on remote URLs). On hosts with
  `allow_url_fopen=Off`, the proxy returns 503 and the client shows
  "Lookup unavailable right now."
- The dictionary cache lives under `storage/cache/dictionary/`
  (sharded by first two letters). Safe to delete at any time.

### Migration notes

No new SQL migrations — the `highlights` table (migration 0007) has
been in place since Phase 1; this release just adds the class +
API on top.

---

## 1.1.0 — Library UX

Phase 2 of the multi-phase refactor. Builds discovery and curation on top
of the foundation: rails on the home page, a richer book detail experience,
user shelves, public reviews, an advanced search with structured filters,
and genre browse pages.

### Added

- **Continue Reading rail** on the home page for signed-in users. Each
  card shows cover, title, author, percent complete, and time since last
  read; click resumes at the saved CFI.
- **Recently added** and **Top rated** rails on the home page (both
  guest- and user-visible). Top rated only surfaces books with at least
  one review.
- **Home shortcuts** for logged-in users — quick links to *Your shelves*
  and *Advanced search*.
- **Book detail page** (`book.php?b={uuid}`) substantially upgraded:
  - Star ratings + review distribution histogram
  - Inline write-review form (one review per user per book, edit + delete)
  - Reviews list with author name, avatar, date, optional title + body
  - "Add to shelf" dropdown listing all of the user's shelves with
    membership toggle
  - "You might also like" rail of related books (shared tags)
  - Tag chips link out to the genre browse page
- **Shelves / collections**:
  - System shelves (Favorites, Want to Read, Finished) auto-seeded per user
  - Create / rename / delete / public-visibility custom shelves
  - Shelf mosaic preview (first four covers)
  - Per-shelf detail view with remove-from-shelf controls
  - Public shelf URLs (`collection.php?slug=...&user={uuid}`)
- **Advanced search** (`search.php`) with structured filters:
  - Free-text query (FULLTEXT `MATCH ... AGAINST` in BOOLEAN MODE)
  - Genre, language, year range, minimum rating
  - Sort by relevance / title / author / date / rating / popularity
- **Genre browse pages** (`genre.php?slug={tag-slug}`) — paginated list of
  every book tagged with that genre.
- **Book aggregates on the `books` table**: `review_count` and `avg_rating`
  columns kept fresh by `AFTER INSERT/UPDATE/DELETE` triggers on reviews
  (migration 0014). Sub-50ms responses for rating-sorted queries.
- **`ReviewRepository`** class with upsert/delete/distribution helpers.
- **`BookRepository`** gains FULLTEXT-aware `paginate()` with filters
  (`tag_slug`, `language`, `year_min`/`year_max`, `min_rating`,
  `sort_by` including `relevance`/`rating`/`popular`) plus
  `recentlyAdded()`, `topRated()`, `mostRead()`, `relatedTo()`,
  `distinctLanguages()`, `yearRange()`.
- **`CollectionRepository`** gains full CRUD (`create`, `update`, `delete`),
  `addBook`, `removeBook`, `reorder`, `forUserAndBook` (powering the
  Add-to-shelf UI), book listing with position-based ordering.
- **JSON APIs** for inline UX:
  - `api/shelves.php` — list user's shelves with membership for a book;
    POST `{uuid, collection_id, action: add|remove|toggle}` toggles.
  - `api/reviews.php` — list / upsert / delete reviews. Public GET; POST
    and DELETE require auth.
- **Header user menu** gains *Your shelves* and *Advanced search*
  entries.

### Changed

- **Library home view** now branches: with no search/filter active, shows
  the rail-driven home page; once any filter is applied, shows the standard
  paginated grid (which now honors all the new filter parameters).
- **Pagination partial** accepts a `baseUrl` so it can be reused on
  `genre.php` and elsewhere without hardcoding `index.php`.
- **Mobile sort controls** restored: previously hidden via `display:none`
  on small viewports. Now wrap onto their own row with 44px-min tap
  targets. A global `@media (pointer: coarse)` rule enforces the 44×44
  minimum on all icon buttons (theme toggle, reader chrome, etc.).
- **Book detail wrapper** is now the source of truth for book-page styling
  (lives in `assets/css/discovery.css`); the inline `<style>` block in
  the old Phase 1 view is removed.

### Migration notes

- Apply migration `0014_book_aggregates.sql` via `/admin/migrate.php`.
  The migration backfills `review_count` and `avg_rating` from any
  existing reviews (no-op on a fresh install) and installs three
  triggers (`reviews_after_insert`, `_update`, `_delete`) that keep the
  columns fresh.
- No data migrations needed for shelves — existing users already have
  system shelves seeded at registration (Phase 1 behaviour).
- Triggers are dropped (`DROP TRIGGER IF EXISTS`) and recreated, so
  re-running the migration is safe.

---

## 1.0.0 — Foundation release

This release rebuilds the project from the v7.x BookShelf proof-of-concept
into a deployable platform. The legacy script files are removed; the
application now follows a structured layout with proper authentication and
a MySQL-backed data layer.

### Added

- **MySQL data layer.** 13 tables for users, books, tags, reading progress,
  bookmarks, highlights, collections, reviews, audit log, sessions, auth
  tokens, and rate-limit buckets. Full-text search on `books`.
- **Setup wizard** (`setup.php`) — multi-step browser install: requirements
  check, DB connection test, site config (auto-detects subdirectory installs),
  admin account creation, schema migrations, sentinel lock.
- **Real authentication.** Argon2id password hashing, DB-backed sessions,
  CSRF protection, rate limiting (per-IP + per-username), remember-me with
  split-token rotation, audit logging.
- **Admin panel** under `/admin/`:
  - Dashboard with library stats
  - EPUB upload with full validation pipeline (extension + MIME + magic
    bytes + ZIP + mimetype entry + container.xml + size + dedup)
  - Book listing with edit / delete
  - User management (role + status + admin-triggered password reset)
  - Audit log viewer
  - Migration runner with drift detection
  - Thumbnail batch rebuild
  - Legacy `books/` directory importer
- **Modern reader** at `/read.php`:
  - epub.js viewer with sepia / light / dark / auto themes
  - Font family, size, line height, margin controls
  - Bookmarks, table of contents, reading progress
  - Click hotspots + keyboard + swipe navigation
  - Server-synced state for logged-in users; localStorage fallback for guests
- **Accessibility baseline**:
  - ARIA roles on book cards, autocomplete combobox, reader panels
  - Keyboard navigation throughout (Tab cycling, Escape closes, focus return)
  - Skip-to-content link, `prefers-reduced-motion` respect
  - Visible focus rings
- **Subdirectory-friendly URLs**: no hardcoded leading slashes anywhere.
  Every link/asset goes through `url()` / `asset()` helpers that prepend
  the auto-detected base URL.
- **Security headers**: per-request CSP nonce, HSTS, `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`.
- **Path-traversal hardening**: `realpath()`-based checks replace the
  legacy `strpos('..')` guards.
- **EPUB metadata extraction** via `DOMDocument` + XPath instead of regex,
  with multi-strategy cover extraction.
- **Vendor README** at `assets/vendor/README.md` explaining how to host
  `epub.js` and `jszip` locally for tighter CSP.

### Changed

- **`index.php`**: completely rewritten. No longer scans the filesystem on
  every request — queries the `books` table via `BookRepository`.
- **CSS**: 1660-line `styles.css` split into `design-system.css`, `base.css`,
  `components.css`, `library.css`, `reader.css`, `admin.css`, `auth.css`.
  Design tokens (terracotta + teal palette, Instrument Serif + DM Sans
  typography) preserved.
- **JavaScript**: 1414-line `script.js` split into focused ES modules under
  `assets/js/{shared,reader}/`.
- **Storage layout**: books move from `books/` (web-served) to
  `storage/books/{shard}/{uuid}.epub` (deny-listed); covers move to
  `assets/covers/{uuid}.jpg`.

### Removed (closed vulnerabilities)

- **`upload.php`** — had NO authentication whatsoever. Anyone with the URL
  could upload arbitrary files. Replaced by `admin/upload.php` with full
  admin role check + multi-layer validation.
- **`upload_epub.php`** — used a hardcoded SHA-256 password placeholder
  (`PUT_YOUR_SHA256_HASH_HERE`). Replaced by proper login + admin role gate.
- **`update_metadata.php`** — used regex over EPUB OPF XML to rewrite ZIP
  contents in place. Brittle and could corrupt files. Metadata edits now
  write to the database; on-disk EPUBs stay canonical.
- **Tailwind CDN** (`cdn.tailwindcss.com`) — explicitly marked dev-only by
  upstream. Replaced by hand-rolled CSS in `assets/css/`.
- **Legacy thumbnail scripts** — `save_thumbnail.php`, `generate_thumbnails.php`,
  `thumbnail_admin.php` merged into `admin/thumbnails.php` and
  `ThumbnailService` class.

### Notes

- This release intentionally keeps `epub.js` pinned at 0.3.93 to avoid
  reader regressions during the refactor. The library will upgrade to
  current `epub.js` in the Phase 3 release alongside highlights / TTS work.
- Reading progress for guest sessions still lives in `localStorage` only;
  sign in (or create an account) to sync across devices.

## Roadmap

The original four-phase plan is complete. Possible follow-ups:

- **epub.js upgrade** to the current release. Highlights are stored as
  stable CFI ranges so the upgrade should not invalidate saved data, but
  needs a corpus smoke-test.
- **PHPMailer vendoring** as a first-class install step (the Mailer class
  detects it; instructions are in `INSTALL.md`).
- **Light test harness** runnable from `/admin/run-tests.php` for
  smoke-testing on shared hosts that don't run CI.
- **Two-factor authentication** for admins.
