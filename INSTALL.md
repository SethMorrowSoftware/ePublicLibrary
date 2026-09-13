# Installation

## Shared cPanel (the most common case)

1. **Download** the latest release archive (or `git clone` if you have shell access).

2. **Upload** the contents to your web root using cPanel *File Manager* or FTP:
   - Standard install: extract everything into `public_html/`.
   - Subdirectory install: extract into `public_html/library/` (or wherever).
     The app auto-detects its base URL during setup.

3. **Create a MySQL database**:
   - cPanel → *MySQL® Databases*
   - Create a new database (note the name, often prefixed with your account name).
   - Create a new MySQL user with a strong password.
   - Grant *all privileges* to the user on the new database.

4. **Set folder permissions** if your host doesn't auto-create them:
   - `includes/` — 755 (PHP must be able to write `config.php` here once)
   - `storage/books/`, `storage/uploads/tmp/`, `storage/logs/`,
     `storage/cache/`, `assets/covers/` — 755 (PHP-writable)

5. **Open the setup wizard** at `https://your-site.com/setup.php` (or
   `https://your-site.com/library/setup.php` if installed in a subdirectory).
   Walk through:
   - Requirements check — fix any failing extension before continuing.
   - Database — enter the credentials from step 3.
   - Site — confirm the auto-detected base URL.
   - Admin account — your first sign-in. Use a strong password.
   - Install — writes `includes/config.php`, creates tables, seeds you in.

6. **Lock down setup**: after success, `setup.php` records a completion
   timestamp and refuses to run again. For maximum safety, delete the file
   from your server entirely.

## Upgrading

1. Back up `includes/config.php`, `storage/`, and your MySQL database.
2. Upload the new release archive over the existing files (it won't touch
   `config.php` or `storage/` — those are gitignored).
3. Sign in as admin and visit *Admin → Migrations*. Apply any pending
   schema changes.
4. (Optional) *Admin → Thumbnails → Build missing* to refresh covers.

## Configuring email

Out of the box, password-reset and other transactional emails are written
to `storage/logs/mail.log` (the `log` driver). To send real email, edit
`includes/config.php`:

```php
'mail' => [
    'driver'    => 'mail',                       // or 'smtp'
    'from_addr' => 'no-reply@your-domain.com',
    'from_name' => 'Your Library',
    'smtp' => [
        'host'       => 'smtp.example.com',
        'port'       => 587,
        'username'   => 'you@example.com',
        'password'   => '...',
        'encryption' => 'tls',
    ],
],
```

- `driver = 'mail'` uses PHP's built-in `mail()` — usually works on
  cPanel hosts with a sendmail-compatible MTA. No SMTP config needed.
- `driver = 'smtp'` requires PHPMailer vendored under
  `includes/vendor/PHPMailer/`:

```bash
cd includes/vendor/
git clone --depth 1 https://github.com/PHPMailer/PHPMailer.git
# or download the release zip and extract here so includes/vendor/PHPMailer/src/PHPMailer.php exists
```

The mailer auto-detects PHPMailer's presence; if missing, it falls back
to the `log` driver and logs a warning so you'll see the misconfiguration.

## Tightening security (optional but recommended)

- **Vendor JS libraries locally**: download `epub.js` and `jszip` into
  `assets/vendor/` so the reader doesn't fetch from a CDN. See
  [`assets/vendor/README.md`](assets/vendor/README.md) for one-liners.
- **Move storage above the web root**: the default install keeps
  `storage/` inside `public_html/` (denied to web via `.htaccess`). Hosts
  with full shell access can move it to a sibling directory and update
  `storage.books_path` in `includes/config.php`.
- **Force HTTPS**: enable AutoSSL in cPanel; the app already sends
  `Strict-Transport-Security` when served over HTTPS.

## Troubleshooting

**Setup wizard says an extension is missing.** Most hosts let you pick the
PHP version in cPanel → *Select PHP Version*. Enable the missing extension
checkbox and re-run setup.

**"Database connection failed".** Double-check the host (often `localhost`
or `127.0.0.1`), the full database/user names (cPanel adds your account
prefix), and that the user has been granted privileges to the database.

**"includes/ not writable".** Set the permissions to 755 (the setup wizard
needs to write `config.php` exactly once; afterwards you can revert to 555).

**"Upload exceeds the 100 MB limit".** Adjust `storage.max_upload_mb` in
`includes/config.php` AND raise `upload_max_filesize` and `post_max_size`
in your PHP configuration (cPanel → *MultiPHP INI Editor*). Comics and
scanned PDFs are frequently larger than the 100 MB default; also raise
`max_execution_time` if big uploads time out mid-import.

**Bulk-importing books already on the server**: put them in a folder inside
the installation and use *Admin → Import folder*. It walks subdirectories,
handles every supported format, copies rather than moves (your originals stay
put), and is safe to re-run — anything already in the library is skipped by
checksum. The default folder is the legacy v7.x `books/` directory.

## Format support

The library reads `.epub`, `.pdf`, `.cbz`, and `.cbr`. Two of those depend on
optional server software; *Admin → Health* reports what your host has.

**PDF covers** need `imagick` with PDF (Ghostscript) support. Without it,
PDFs import fine — title, author, and page count are still read — but arrive
without cover art. Open *Admin → Thumbnails* and use **Render PDF covers
here**: your browser rasterises page 1 with pdf.js and uploads the result.

**CBR** files are RAR archives, which no browser can unpack, so they are
converted to CBZ during import. That needs one of `bsdtar`, `unar`, `unrar`,
or `7z` on the server:

```bash
# Debian / Ubuntu
apt-get install libarchive-tools     # provides bsdtar
```

On shared hosting you usually cannot install these. Two options: convert the
files to `.cbz` before uploading (a CBZ is just a ZIP of images), or check
whether the file is already a ZIP with a `.cbr` name — very common, and those
import with no unpacker at all.
