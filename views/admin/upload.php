<?php
defined('APP_BOOTED') or exit;
/** @var array  $messages */
/** @var int    $maxMb */
/** @var string $accept */
/** @var bool   $rarSupport */
/** @var string $rarTools */
/** @var bool   $pdfCovers */
?>
<div class="admin-page-header">
    <h1>Upload books</h1>
    <p class="muted">Drop EPUB, PDF, or comic archives (CBZ / CBR) here. Each file is
       validated by its real contents, deduplicated, parsed for metadata, and given
       a cover where one can be produced. Maximum <?= e((string) $maxMb) ?> MB per file.</p>
</div>

<?php if ($messages): ?>
    <div class="upload-messages" role="status">
        <?php foreach ($messages as $m): ?>
            <div class="upload-message upload-<?= e($m['type']) ?>"><?= e($m['text']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/upload.php')) ?>" enctype="multipart/form-data"
      class="upload-form" id="uploadForm">
    <?= csrf_field() ?>
    <label for="book-input" class="upload-dropzone" id="dropzone">
        <input type="file" name="book[]" id="book-input" multiple accept="<?= e($accept) ?>" hidden>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
        </svg>
        <span class="upload-prompt">
            <strong>Drop books here</strong>
            or click to browse
        </span>
        <span class="upload-hint">EPUB · PDF · CBZ · CBR — up to <?= e((string) $maxMb) ?> MB each</span>
    </label>

    <ul class="upload-queue" id="uploadQueue" hidden></ul>

    <div class="upload-actions">
        <button type="submit" class="btn btn-primary" id="uploadSubmit">Upload selected</button>
        <a href="<?= e(url('admin/books.php')) ?>" class="btn btn-ghost">View all books</a>
    </div>
</form>

<aside class="upload-tips">
    <h2>What this server can do</h2>
    <ul class="capability-list">
        <li class="ok"><span class="mark" aria-hidden="true">✓</span>
            <span><strong>EPUB</strong> — metadata and cover read from the OPF package.</span></li>
        <li class="<?= $pdfCovers ? 'ok' : 'warn' ?>"><span class="mark" aria-hidden="true"><?= $pdfCovers ? '✓' : '!' ?></span>
            <span><strong>PDF</strong> — title, author, and page count are always read.
            <?php if ($pdfCovers): ?>
                Covers are rendered from page 1 with Imagick.
            <?php else: ?>
                Imagick with PDF support is not installed, so covers are generated
                in your browser from the
                <a href="<?= e(url('admin/thumbnails.php')) ?>">Thumbnails</a> page instead.
            <?php endif; ?>
            </span></li>
        <li class="ok"><span class="mark" aria-hidden="true">✓</span>
            <span><strong>CBZ</strong> — pages listed in natural order, first page becomes the cover.</span></li>
        <li class="<?= $rarSupport ? 'ok' : 'warn' ?>"><span class="mark" aria-hidden="true"><?= $rarSupport ? '✓' : '!' ?></span>
            <span><strong>CBR</strong> —
            <?php if ($rarSupport): ?>
                converted to CBZ on upload so it streams page by page.
            <?php else: ?>
                needs a RAR unpacker on the server (<?= e($rarTools) ?>). CBR files that
                are secretly ZIPs still import fine; true RAR archives are rejected with
                a message. Convert them to <code>.cbz</code> first.
            <?php endif; ?>
            </span></li>
    </ul>

    <h2>Tips</h2>
    <ul>
        <li>Select many files at once — each is processed independently, and one
            bad file will not stop the rest.</li>
        <li>Re-uploading the same file is safe; duplicates are detected by SHA-256 checksum.</li>
        <li>To bulk-import an existing folder of books from disk, use
            <a href="<?= e(url('admin/scan-books.php')) ?>">Import folder</a>.</li>
        <li>Large comics and PDFs may need higher <code>upload_max_filesize</code>,
            <code>post_max_size</code>, and <code>max_execution_time</code> in your PHP settings.</li>
    </ul>
</aside>
