<section class="page-heading"><div><p class="eyebrow">SETTINGS</p><h1>My account</h1><p><?= e($user['name']) ?> · <?= e($user['username']) ?></p></div></section>
<section class="panel edit-panel">
    <div class="panel-head"><div><h2>Change password</h2><p>Use at least 12 characters for your new password.</p></div></div>
    <form method="post" class="panel-body form-stack">
        <?= formAction('password') ?>
        <label class="form-label">Current password<input type="password" class="form-control" name="current_password" maxlength="72" autocomplete="current-password" required></label>
        <label class="form-label">New password<input type="password" class="form-control" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
        <button class="btn btn-primary">Update password</button>
    </form>
</section>
