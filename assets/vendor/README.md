# Vendored JavaScript libraries

The reader page loads `epub.js` and `jszip` from this directory if the files
exist; otherwise it falls back to the public CDN. Hosting them locally
tightens your Content-Security-Policy (no third-party origins) and removes
a dependency on external uptime.

## What to vendor

| File | Version | Source |
|---|---|---|
| `epub.min.js`  | 0.3.93 | https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js |
| `jszip.min.js` | 3.10.1 | https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js |

## How to vendor (one-time, on your computer or server with shell access)

```bash
cd assets/vendor/
curl -L -o epub.min.js  https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js
curl -L -o jszip.min.js https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js
```

If you don't have shell access, download the two files in a browser and upload
them via FTP / cPanel File Manager into this directory.

## Verifying integrity

Add Subresource Integrity hashes to `views/layouts/reader.php` after vendoring:

```bash
openssl dgst -sha384 -binary epub.min.js  | openssl base64 -A
openssl dgst -sha384 -binary jszip.min.js | openssl base64 -A
```

Then update the `<script>` tags in `views/layouts/reader.php` with
`integrity="sha384-..." crossorigin="anonymous"` attributes.

## Upgrade path

The EPUB reader is pinned to epub.js 0.3.93. If you bump it, re-check
`assets/js/reader/viewer.js`: it passes `openAs: 'epub'` because epub.js
otherwise guesses the input type from the URL's file extension, and the
library streams books from `api/download.php?b=…`, which has none.

## pdf.js (PDF reader)

PDF books are rendered with [pdf.js](https://mozilla.github.io/pdf.js/).
Vendor it here to drop the CDN dependency:

| File | Version | Source |
|---|---|---|
| `pdf.min.js`        | 3.11.174 | https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js |
| `pdf.worker.min.js` | 3.11.174 | https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js |

```bash
cd assets/vendor/
curl -L -o pdf.min.js        https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js
curl -L -o pdf.worker.min.js https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js
```

Keep both files at the **same version** — pdf.js refuses to run against a
mismatched worker.

### Why the worker matters

A `Worker` script must be same-origin; no CSP directive can relax that. When
`pdf.worker.min.js` is present here, the reader points straight at it. When it
is not, the reader falls back to `assets/js/pdf-worker-cdn.js`, a one-line
same-origin shim that `importScripts()` the CDN copy — which *is* allowed.
Vendoring removes that hop entirely and lets PDFs work offline.

## Comics need nothing

CBZ/CBR reading is served page-by-page by `api/comic.php` and rendered with
plain `<img>` elements, so the comic reader has no third-party dependency at
all.
