# Security

## Threat model

ePublicLibrary is designed for self-hosting on shared hosts where:

- Anyone with the URL can browse the library and read books (this is the point).
- Account registration is open — anyone can create a reader account.
- A small number of administrators upload and manage books.
- The host's file system is shared with other tenants; defense in depth matters.

Assumed adversaries:

- Random internet visitors trying common credentials, scraping the catalog,
  or probing for misconfigured endpoints.
- Bots crawling the open internet for outdated PHP apps.
- Mistakes by the admin (committing secrets, weak passwords).

Out of scope (for now):

- Determined attackers with physical or hypervisor access to the server.
- DoS resistant beyond per-IP rate limits.
- Cross-site cooperative attacks involving the user's other browser tabs.

## Defenses

| Concern | Mitigation |
|---|---|
| **SQL injection** | All queries use prepared statements via PDO. Repositories are the only place that touch the DB. |
| **XSS** | Output is escaped with `e()` (htmlspecialchars w/ ENT_QUOTES\|ENT_SUBSTITUTE). EPUB content is sandboxed in `epub.js` iframes; comic pages are served as images with `nosniff`, never as HTML. |
| **CSRF** | Per-session token rotated on login, embedded in all forms and sent in the `X-CSRF-Token` header for XHR. `hash_equals` verification. |
| **Session theft** | Cookies are `HttpOnly; Secure (HTTPS); SameSite=Lax`. Session ID is regenerated on login/logout. Optional remember-me uses split-token rotation. |
| **Password attacks** | Argon2id hashing. NIST 800-63B-aligned rules (12+ chars, plus a common-password check on Phase 4). Failed logins take constant time. |
| **Brute force / credential stuffing** | Per-IP and per-username sliding-window throttle in the `rate_limits` table. Account lockout after 5 failed attempts. |
| **Path traversal** | `BookFileStorage::resolveForBook()` uses `realpath()` and asserts the path stays inside the configured books root. |
| **Upload abuse** | `BookFileValidator` decides the format from **magic bytes, not the filename**, then runs a format-specific structural check: EPUB (mimetype entry + `META-INF/container.xml` parses), PDF (header, `%%EOF` trailer, encryption probe), comic (at least one real page image). Plus extension allow-list, finfo MIME, file-size cap, and SHA-256 dedup. A PDF renamed `.epub` is rejected, and an encrypted PDF is refused rather than stored unreadable. Files are quarantined in `storage/uploads/tmp/` before being moved into place. |
| **Direct file access** | `.htaccess` denies `includes/`, `views/`, `storage/`, `database/`, and `config.php`. All book downloads go through `api/download.php`, and comic pages through `api/comic.php` (rate limited + audit logged). |
| **Clickjacking** | `X-Frame-Options: DENY` and `frame-ancestors 'none'` in CSP. |
| **MIME sniffing** | `X-Content-Type-Options: nosniff` everywhere. |
| **Information disclosure** | `display_errors=0` in production. Errors logged to `storage/logs/error.log`; generic 500 page on failure. |
| **Audit trail** | `audit_log` captures logins, role changes, uploads, deletions, suspicious remember-me mismatches. |

## Content-Security-Policy

The default policy is:

```
default-src 'self';
script-src 'self' 'nonce-{N}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;
style-src 'self' 'unsafe-inline';
img-src 'self' data: blob:;
font-src 'self' data:;
connect-src 'self';
worker-src 'self' blob:;
child-src 'self' blob:;
frame-src 'self' blob:;
object-src 'none';
base-uri 'self';
form-action 'self';
frame-ancestors 'none';
```

The two CDN hosts in `script-src` are only used as a fallback when
`assets/vendor/` is empty. After vendoring the libraries locally (see
[`assets/vendor/README.md`](assets/vendor/README.md)), you can remove the
CDN hosts from the `$cdnHosts` constant in `includes/security.php` to
fully eliminate third-party origins.

### Why `style-src` has no nonce

`style-src` carries `'unsafe-inline'` and deliberately **no** nonce. Under
CSP Level 3 a nonce or hash in `style-src` makes the browser *ignore*
`'unsafe-inline'` — so a policy listing both is strictly the nonce-only
policy, and every inline style is blocked. That took out cover art, progress
bars and rating histograms (all server-rendered `style=""` built from escaped
values), plus the `<style>` blocks `epub.js` injects into each chapter iframe
to apply the reader theme and font size.

The exposure is small: no inline style in this app is built from unescaped
input, and CSS injection without script execution is a limited primitive.
`script-src` keeps its nonce and remains strict, which is where the real
protection is. If `epub.js` ever grows a nonce hook, `style-src` can be
tightened to match.

### Workers

`worker-src` is `'self' blob:` only. A `Worker` script must be same-origin —
that is a browser rule, not a CSP one — so the pdf.js worker is either the
vendored copy or `assets/js/pdf-worker-cdn.js`, a same-origin shim that
`importScripts()` the CDN build (permitted by `script-src`). Vendoring pdf.js
removes the hop; see [`assets/vendor/README.md`](assets/vendor/README.md).

### Fonts

The UI fonts are served from `assets/fonts/`, so no `style-src` or `font-src`
exception for a font CDN is needed and the interface makes no third-party
requests.

## What's NOT yet protected (roadmap)

- **Email-based password reset** — admins must reset reader passwords
  manually from `/admin/users` in Phase 1.
- **Two-factor authentication** — planned for a future release.
- **Per-IP request quotas beyond login/register/download** — coarse-grained
  rate limiting only.

## Reporting a vulnerability

If you discover a security issue, please contact the maintainer privately
rather than opening a public issue. Provide:

1. A description of the vulnerability.
2. Steps to reproduce.
3. The affected version (see `app_version` in `includes/config.php`).

We aim to acknowledge reports within 7 days.
