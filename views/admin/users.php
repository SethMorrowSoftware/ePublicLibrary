<?php
defined('APP_BOOTED') or exit;
/** @var array $users */
$me = current_user();
?>
<div class="admin-page-header">
    <h1>Users</h1>
    <p class="muted"><?= count($users) ?> users · <?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) ?> admin<?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) !== 1 ? 's' : '' ?></p>
</div>

<table class="admin-table">
    <thead>
        <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Last sign-in</th>
            <th>Joined</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <strong><?= e($u['username']) ?></strong>
                    <?php if ($u['display_name']): ?>
                        <br><small class="muted"><?= e($u['display_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= e($u['email']) ?></td>
                <td>
                    <?php if ((int) $u['id'] === (int) $me['id']): ?>
                        <span class="badge badge-published"><?= e($u['role']) ?></span>
                        <small class="muted">(you)</small>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('admin/users.php')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="verb" value="set_role">
                            <input type="hidden" name="id" value="<?= e((string) $u['id']) ?>">
                            <select name="role" data-autosubmit>
                                <option value="reader" <?= $u['role'] === 'reader' ? 'selected' : '' ?>>reader</option>
                                <option value="admin"  <?= $u['role'] === 'admin'  ? 'selected' : '' ?>>admin</option>
                            </select>
                        </form>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $u['id'] === (int) $me['id']): ?>
                        <span class="badge badge-<?= e($u['status']) ?>"><?= e($u['status']) ?></span>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('admin/users.php')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="verb" value="set_status">
                            <input type="hidden" name="id" value="<?= e((string) $u['id']) ?>">
                            <select name="status" data-autosubmit>
                                <?php foreach (['active','suspended','pending'] as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $u['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                </td>
                <td><?= $u['last_login_at'] ? e(date('M j, Y', strtotime($u['last_login_at']))) : '—' ?></td>
                <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                <td>
                    <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                        <form method="post" action="<?= e(url('admin/users.php')) ?>" class="inline-form"
                              data-confirm="Reset the password for <?= e($u['username']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="verb" value="reset_password">
                            <input type="hidden" name="id" value="<?= e((string) $u['id']) ?>">
                            <button type="submit" class="btn-link">Reset password</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
