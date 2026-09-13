<?php
defined('APP_BOOTED') or exit;
/** @var array $books */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var string $searchTerm */
?>
<div class="admin-page-header">
    <h1>Books</h1>
    <p class="muted"><?= number_format($total) ?> total · sorted by recently added</p>
</div>

<form method="get" action="<?= e(url('admin/books.php')) ?>" class="admin-toolbar">
    <input type="search" name="q" value="<?= e($searchTerm) ?>" placeholder="Search books..."
           aria-label="Search books">
    <button type="submit" class="btn btn-ghost">Search</button>
    <a href="<?= e(url('admin/upload.php')) ?>" class="btn btn-primary">+ Upload</a>
</form>

<?php if (!$books): ?>
    <div class="empty-state">
        <h2>No books to manage</h2>
        <p><?= $searchTerm ? 'Nothing matches that search.' : 'Upload an EPUB, PDF, or comic to get started.' ?></p>
    </div>
<?php else: ?>
    <form method="post" action="<?= e(url('admin/books.php')) ?>" id="bulk-form">
        <?= csrf_field() ?>
        <input type="hidden" name="verb" value="bulk">

        <div class="bulk-toolbar" hidden id="bulk-toolbar">
            <span class="bulk-count"><span id="bulk-count">0</span> selected</span>
            <select name="bulk_action" aria-label="Bulk action" required>
                <option value="">Choose action…</option>
                <option value="publish">Publish</option>
                <option value="hide">Hide</option>
                <option value="remove">Mark removed</option>
                <option value="delete">Delete (permanent)</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <button type="button" class="btn btn-ghost btn-sm" id="bulk-clear">Cancel</button>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th class="bulk-cell"><input type="checkbox" id="bulk-all" aria-label="Select all"></th>
                    <th class="cover-cell">Cover</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Format</th>
                    <th>Status</th>
                    <th>Added</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $b): ?>
                    <tr>
                        <td class="bulk-cell">
                            <input type="checkbox" name="ids[]" value="<?= e((string) $b['id']) ?>"
                                   class="bulk-check" aria-label="Select <?= e($b['title']) ?>">
                        </td>
                        <td class="cover-cell">
                            <?php if (!empty($b['cover_path'])): ?>
                                <img src="<?= e(asset($b['cover_path'])) ?>" alt="">
                            <?php else: ?>
                                <div class="cover-placeholder" aria-hidden="true"></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= e(url('admin/books.php?action=edit&id=' . $b['id'])) ?>">
                                <?= e($b['title']) ?>
                            </a>
                        </td>
                        <td><?= e($b['author']) ?></td>
                        <td>
                            <span class="badge badge-format format-<?= e(BookFormat::normalize($b['format'] ?? null)) ?>">
                                <?= e(BookFormat::labelForBook($b)) ?>
                            </span>
                            <?php if (!empty($b['page_count'])): ?>
                                <small class="muted"><?= e(number_format((int) $b['page_count'])) ?> pp</small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
                        <td><?= e(date('M j, Y', strtotime($b['created_at']))) ?></td>
                        <td class="actions-cell">
                            <a href="<?= e(url('book.php?b=' . $b['uuid'])) ?>" title="View">View</a>
                            <a href="<?= e(url('read.php?b=' . $b['uuid'])) ?>" title="Read">Read</a>
                            <a href="<?= e(url('admin/books.php?action=edit&id=' . $b['id'])) ?>" title="Edit">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <script nonce="<?= e(csp_nonce()) ?>">
        (function () {
            const form     = document.getElementById('bulk-form');
            const all      = document.getElementById('bulk-all');
            const toolbar  = document.getElementById('bulk-toolbar');
            const counter  = document.getElementById('bulk-count');
            const clearBtn = document.getElementById('bulk-clear');
            const checks   = form.querySelectorAll('.bulk-check');

            function refresh() {
                const sel = Array.from(checks).filter(c => c.checked).length;
                counter.textContent = sel;
                toolbar.hidden = sel === 0;
                if (sel === 0) all.checked = false;
                else if (sel === checks.length) all.checked = true;
                else { all.indeterminate = true; all.checked = false; return; }
                all.indeterminate = false;
            }
            checks.forEach(c => c.addEventListener('change', refresh));
            all.addEventListener('change', () => {
                checks.forEach(c => { c.checked = all.checked; });
                refresh();
            });
            clearBtn.addEventListener('click', () => {
                checks.forEach(c => { c.checked = false; });
                refresh();
            });
            form.addEventListener('submit', (e) => {
                const action = form.elements['bulk_action'].value;
                const sel = Array.from(checks).filter(c => c.checked).length;
                if (!action) {
                    e.preventDefault();
                    alert('Choose a bulk action.');
                    return;
                }
                if (sel === 0) {
                    e.preventDefault();
                    alert('Select at least one book.');
                    return;
                }
                const msg = action === 'delete'
                    ? `Permanently delete ${sel} book(s) and their files? This cannot be undone.`
                    : `Apply "${action}" to ${sel} book(s)?`;
                if (!confirm(msg)) e.preventDefault();
            });
        })();
    </script>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Books pagination">
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <?php $params = http_build_query(array_filter(['q' => $searchTerm, 'page' => $i])); ?>
                <?php if ($i === $page): ?>
                    <span aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e(url('admin/books.php?' . $params)) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
