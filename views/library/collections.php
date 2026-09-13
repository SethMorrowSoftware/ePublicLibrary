<?php
defined('APP_BOOTED') or exit;
/** @var array $shelves */
?>
<div class="content-wrapper collections-wrapper">
    <header class="page-header">
        <h1>Your shelves</h1>
        <p class="muted">Group your library however you like. System shelves
           (Favorites, Want to Read, Finished) are seeded for you.</p>
    </header>

    <section class="shelf-create-card">
        <details>
            <summary class="btn btn-primary">+ New shelf</summary>
            <form method="post" action="<?= e(url('collections.php')) ?>" class="form shelf-create-form">
                <?= csrf_field() ?>
                <input type="hidden" name="verb" value="create">
                <label>
                    <span>Name</span>
                    <input type="text" name="name" maxlength="120" required autofocus
                           placeholder="e.g. Summer reading">
                </label>
                <label>
                    <span>Description <small>(optional)</small></span>
                    <textarea name="description" rows="2" maxlength="500"></textarea>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="is_public" value="1">
                    <span>Make this shelf public so others can browse it</span>
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create shelf</button>
                </div>
            </form>
        </details>
    </section>

    <div class="shelf-grid">
        <?php foreach ($shelves as $s):
            $url = url('collection.php?slug=' . eurl($s['slug']));
            $covers = array_filter($s['preview_covers'] ?? []);
        ?>
            <article class="shelf-card">
                <a href="<?= e($url) ?>" class="shelf-card-mosaic" aria-label="Open <?= e($s['name']) ?>">
                    <?php if ($covers): ?>
                        <div class="shelf-mosaic">
                            <?php for ($i = 0; $i < 4; $i++): ?>
                                <div class="shelf-mosaic-cell"
                                     <?= !empty($covers[$i]) ? 'style="background-image:url(\'' . e(asset($covers[$i])) . '\')"' : '' ?>></div>
                            <?php endfor; ?>
                        </div>
                    <?php else: ?>
                        <div class="shelf-mosaic shelf-mosaic-empty" aria-hidden="true">
                            <span>Empty</span>
                        </div>
                    <?php endif; ?>
                </a>
                <div class="shelf-card-body">
                    <a href="<?= e($url) ?>" class="shelf-card-name"><?= e($s['name']) ?></a>
                    <span class="shelf-card-count">
                        <?= number_format((int) $s['book_count']) ?> book<?= (int) $s['book_count'] !== 1 ? 's' : '' ?>
                    </span>
                    <?php if ($s['description']): ?>
                        <p class="shelf-card-desc"><?= e($s['description']) ?></p>
                    <?php endif; ?>
                    <details class="shelf-card-edit">
                        <summary class="btn-link">Edit</summary>
                        <form method="post" action="<?= e(url('collections.php')) ?>" class="form shelf-edit-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="verb" value="update">
                            <input type="hidden" name="id" value="<?= e((string) $s['id']) ?>">
                            <label>
                                <span>Name</span>
                                <input type="text" name="name" value="<?= e($s['name']) ?>" maxlength="120" required
                                       <?= $s['is_system'] ? 'readonly' : '' ?>>
                            </label>
                            <label>
                                <span>Description</span>
                                <textarea name="description" rows="2" maxlength="500"><?= e($s['description'] ?? '') ?></textarea>
                            </label>
                            <label class="checkbox">
                                <input type="checkbox" name="is_public" value="1" <?= $s['is_public'] ? 'checked' : '' ?>>
                                <span>Public shelf</span>
                            </label>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                <?php if (!$s['is_system']): ?>
                                    <button type="submit" form="delete-shelf-<?= e((string) $s['id']) ?>"
                                            class="btn-link review-delete">Delete shelf</button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if (!$s['is_system']): ?>
                            <form id="delete-shelf-<?= e((string) $s['id']) ?>" method="post" action="<?= e(url('collections.php')) ?>"
                                  data-confirm="Delete &quot;<?= e($s['name']) ?>&quot;? Books on it stay in the library.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="verb" value="delete">
                                <input type="hidden" name="id" value="<?= e((string) $s['id']) ?>">
                            </form>
                        <?php endif; ?>
                    </details>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>
