/**
 * Same-origin shim for the pdf.js worker.
 *
 * A Worker script has to be same-origin — that is a browser rule, not a CSP
 * one, so no policy tweak makes `new Worker('https://cdn…')` legal. But a
 * worker may importScripts() across origins, and the page CSP already trusts
 * jsDelivr for scripts. So this file is loaded as the worker and immediately
 * pulls in the real implementation.
 *
 * Vendor pdf.js locally (see assets/vendor/README.md) and this file stops
 * being used at all — views/layouts/reader.php points the worker straight at
 * assets/vendor/pdf.worker.min.js.
 *
 * Keep the version in step with the <script> tag in views/layouts/reader.php.
 */
importScripts('https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js');
