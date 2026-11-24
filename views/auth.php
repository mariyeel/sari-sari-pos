<?php require_once __DIR__ . '/components.php'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= !empty($setup) ? 'Set up your store' : 'Sign in' ?> · SariSari</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/icons.css">
    <link rel="stylesheet" href="assets/store.css">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-intro">
            <?php brand(); ?>
            <div class="auth-drawing" aria-hidden="true"><?= icon('shop-window') ?></div>
            <div class="auth-copy">
                <p class="eyebrow">SARI-SARI STORE POS</p>
                <h1>Run your tindahan.</h1>
                <p>Ring up tingi sales, track your shelves, and keep customer utang in one place.</p>
            </div>
            <div class="auth-footer"><span></span> Built for neighborhood stores</div>
        </section>
        <section class="auth-form">
            <div class="auth-mark"><?= icon(!empty($setup) ? 'person-plus' : 'box-arrow-in-right') ?></div>
            <?php if (!empty($unavailable)) { ?>
                <h2>Store unavailable</h2>
                <p>Check the database connection and import the store schema before opening the app.</p>
            <?php } else { ?>
                <h2><?= $setup ? 'Set up your store' : 'Welcome back' ?></h2>
                <p><?= $setup ? 'Create the first store owner account to get started.' : 'Sign in to your store account.' ?></p>
                <?php alertMessage($message ?? null); ?>
                <form method="post" action="index.php" class="form-stack">
                    <?= formAction($setup ? 'setup' : 'login') ?>
                    <?php if ($setup) { ?><label class="form-label">Full name<input class="form-control" name="name" autocomplete="name" maxlength="100" required autofocus></label><?php } ?>
                    <label class="form-label">Username<input class="form-control" name="username" autocomplete="username" maxlength="40" required <?= $setup ? '' : 'autofocus' ?>></label>
                    <label class="form-label">Password<input class="form-control" type="password" name="password" autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" maxlength="72" <?= $setup ? 'minlength="12"' : '' ?> required></label>
                    <?php if ($setup) { ?><small class="text-secondary">Use at least 12 characters.</small><?php } ?>
                    <button class="btn btn-primary" type="submit"><?= $setup ? 'Create owner account' : 'Sign in' ?></button>
                </form>
            <?php } ?>
        </section>
    </main>
</body>
</html>
