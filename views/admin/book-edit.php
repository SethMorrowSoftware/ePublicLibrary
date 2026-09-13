<?php
defined('APP_BOOTED') or exit;
/** @var array $book */
/** @var array $tags */
$genreList = implode(', ', array_map(static fn($t) => $t['name'], $tags));
?>
<div class="admin-page-header">
    <h1>Edit book</h1>
    <p class="muted">Changes update metadata in the database. The book file
       itself is left untouched — downloaders get the original file as uploaded.</p>
</div>

<form method="post" action="<?= e(url('admin/books.php')) ?>" class="form admin-form">
    <?= csrf_field() ?>
    <input type="hidden" name="verb" value="update">
    <input type="hidden" name="id" value="<?= e((string) $book['id']) ?>">

    <div class="admin-form-grid">
        <div class="form-col-left">
            <?php if (!empty($book['cover_path'])): ?>
                <img src="<?= e(asset($book['cover_path'])) ?>" alt="" class="edit-cover">
            <?php else: ?>
                <div class="edit-cover-placeholder">no cover</div>
            <?php endif; ?>
            <dl class="edit-meta">
                <dt>UUID</dt><dd><code><?= e($book['uuid']) ?></code></dd>
                <dt>Slug</dt><dd><code><?= e($book['slug']) ?></code></dd>
                <dt>Size</dt><dd><?= e(format_bytes((int) $book['file_size'])) ?></dd>
                <dt>Hash</dt><dd><code><?= e(substr($book['file_hash'], 0, 16)) ?>…</code></dd>
                <dt>Added</dt><dd><?= e(date('M j, Y', strtotime($book['created_at']))) ?></dd>
            </dl>
            <p>
                <a href="<?= e(url('read.php?b=' . $book['uuid'])) ?>" class="btn btn-ghost">Read</a>
                <a href="<?= e(url('api/download.php?b=' . $book['uuid'])) ?>" class="btn btn-ghost" download>Download <?= e(strtoupper(BookFormat::extension($book['format'] ?? null))) ?></a>
            </p>
        </div>

        <div class="form-col-right">
            <label>Title
                <input name="title" value="<?= e($book['title']) ?>" required>
            </label>
            <label>Subtitle
                <input name="subtitle" value="<?= e($book['subtitle'] ?? '') ?>">
            </label>
            <label>Author
                <input name="author" value="<?= e($book['author']) ?>" required>
            </label>
            <div class="form-row-2">
                <label>Language
                    <input name="language" value="<?= e($book['language'] ?? '') ?>" maxlength="10" placeholder="en">
                </label>
                <label>ISBN
                    <input name="isbn" value="<?= e($book['isbn'] ?? '') ?>" maxlength="20">
                </label>
            </div>
            <div class="form-row-2">
                <label>Publisher
                    <input name="publisher" value="<?= e($book['publisher'] ?? '') ?>">
                </label>
                <label>Published date
                    <input name="published_date" value="<?= e($book['published_date'] ?? '') ?>" placeholder="2024">
                </label>
            </div>
            <label>Description
                <textarea name="description" rows="6"><?= e($book['description'] ?? '') ?></textarea>
            </label>
            <label>Genres / tags
                <input name="genres" value="<?= e($genreList) ?>" placeholder="Fiction, Mystery, Thriller">
                <small>Comma-separated list. Existing tags will be replaced.</small>
            </label>
            <label>Status
                <select name="status">
                    <?php foreach (['published','hidden','removed'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $book['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Hidden books don't appear in the library but are still readable via direct URL.</small>
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="<?= e(url('admin/books.php')) ?>" class="btn btn-ghost">Cancel</a>
            </div>
        </div>
    </div>
</form>

<details class="danger-zone">
    <summary>Danger zone</summary>
    <form method="post" action="<?= e(url('admin/books.php')) ?>"
          data-confirm="Permanently delete &quot;<?= e($book['title']) ?>&quot;? This removes the database row, the book file, and the cover.">
        <?= csrf_field() ?>
        <input type="hidden" name="verb" value="delete">
        <input type="hidden" name="id" value="<?= e((string) $book['id']) ?>">
        <p class="muted">Delete this book permanently. Reading progress and bookmarks
           by all users will be removed along with the book file and cover.</p>
        <button type="submit" class="btn btn-danger">Delete book</button>
    </form>
</details>
