<?php

use ItPortal\View;

/** @var string $contentTemplate */
/** @var array $menu */
/** @var array $user */
/** @var bool $loggedIn */
/** @var string $appName */
/** @var string $version */
/** @var string $title */
/** @var string $active */
/** @var mixed $flash */
/** @var string $flashType */
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="app-body" data-page="<?= View::e($active) ?>">
<div class="ambient" aria-hidden="true"></div>
<header class="topbar">
    <div class="topbar__brand">
        <a href="/index.php" class="brand">
            <span class="brand__mark">JSS</span>
            <span class="brand__text">
                <strong>IT Portal</strong>
                <small>Colleferro · v<?= View::e($version) ?></small>
            </span>
        </a>
    </div>
    <nav class="topbar__nav" id="mainNav">
        <?php foreach ($menu as $section): ?>
            <div class="nav-item<?= !empty($section['active']) ? ' is-active' : '' ?>">
                <button type="button" class="nav-item__btn" aria-expanded="false">
                    <?= View::e($section['label']) ?>
                    <span class="caret"></span>
                </button>
                <div class="nav-item__menu">
                    <?php foreach ($section['children'] as $child): ?>
                        <?php
                        $href = $child['href'] ?? '#';
                        $soon = !empty($child['coming_soon']);
                        $external = !empty($child['external']);
                        ?>
                        <a
                            href="<?= $soon ? '#' : View::e($href) ?>"
                            class="nav-link<?= $soon ? ' is-soon' : '' ?>"
                            <?= $external ? 'target="_blank" rel="noopener"' : '' ?>
                            <?= $soon ? 'aria-disabled="true" title="In migrazione"' : '' ?>
                        >
                            <?= View::e($child['label']) ?>
                            <?= $soon ? '<em>soon</em>' : '' ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </nav>
    <div class="topbar__auth">
        <?php if ($loggedIn): ?>
            <div class="user-chip">
                <span class="user-chip__name"><?= View::e(trim($user['cognome'] . ' ' . $user['nome'])) ?></span>
                <span class="user-chip__role"><?= View::e($user['roles']) ?></span>
            </div>
            <a class="btn btn--ghost" href="/logout.php">Logout</a>
        <?php else: ?>
            <a class="btn btn--primary" href="/login.php">Login</a>
        <?php endif; ?>
        <button class="nav-toggle" type="button" aria-label="Menu" id="navToggle">☰</button>
    </div>
</header>

<?php if ($flash): ?>
    <div class="toast toast--<?= View::e((string) $flashType) ?>" role="status"><?= View::e((string) $flash) ?></div>
<?php endif; ?>

<main class="main">
    <?php
    $contentPath = dirname(__DIR__) . '/templates/' . $contentTemplate . '.php';
    if (is_file($contentPath)) {
        require $contentPath;
    }
    ?>
</main>

<footer class="site-footer">
    <p>© <?= date('Y') ?> I.T. J.S.S. Colleferro · Modern rewrite PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></p>
</footer>

<input type="hidden" id="swiped" name="swiped" value="">
<script src="/assets/js/cardswipe.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
