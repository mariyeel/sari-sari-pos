<?php $staff = $db->query("SELECT id, name, username, role, active FROM users ORDER BY role, name")->fetchAll(); ?>
<section class="page-heading"><div><p class="eyebrow">SETTINGS</p><h1>Staff accounts</h1><p>Give each cashier their own username and password.</p></div></section>
<div class="management-grid">
    <section class="panel table-panel"><div class="panel-head"><div><h2>Store users</h2><p>Manage cashier access.</p></div></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Name</th><th>Role</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
            <?php foreach ($staff as $person) { ?><tr><td class="fw-semibold"><?= e($person['name']) ?><small class="d-block text-secondary"><?= e($person['username']) ?></small></td><td><?= $person['role'] === 'owner' ? 'Store owner' : 'Cashier' ?></td><td><span class="method <?= $person['active'] ? 'method-cash' : 'method-utang' ?>"><?= $person['active'] ? 'Active' : 'Disabled' ?></span></td><td class="text-end">
                <?php if ($person['role'] === 'cashier') { ?><form method="post"><?= formAction('staff_status') ?><input type="hidden" name="id" value="<?= e($person['id']) ?>"><button class="btn btn-light btn-sm"><?= $person['active'] ? 'Disable' : 'Enable' ?></button></form><?php } ?>
            </td></tr><?php } ?>
            </tbody>
        </table></div>
    </section>
    <section class="panel"><div class="panel-head"><div><h2>Add cashier</h2><p>Cashiers can ring up sales, check stock, and record utang payments.</p></div></div>
        <form method="post" class="panel-body form-stack">
            <?= formAction('staff') ?>
            <label class="form-label">Full name<input class="form-control" name="name" maxlength="100" required></label>
            <label class="form-label">Username<input class="form-control" name="username" maxlength="40" pattern="[a-zA-Z0-9._-]{3,40}" required></label>
            <label class="form-label">Password<input type="password" class="form-control" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <button class="btn btn-primary"><?= icon('person-plus') ?> Create cashier</button>
        </form>
    </section>
</div>
