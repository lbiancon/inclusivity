<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($title ?? 'Portale') ?> · Lumina</title>
  <link rel="icon" href="<?= e(url('/favicon.svg')) ?>">
  <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
</head>
<body>
  <header class="portal-bar">
    <a class="wordmark" href="<?= e(url('/portale')) ?>"><span class="mark" aria-hidden="true"></span> Lumina</a>
    <nav>
      <a href="<?= e(url('/#vetrina')) ?>">Vetrina</a>
      <span><?= e($user['name'] ?? '') ?></span>
      <form method="post" action="<?= e(url('/esci')) ?>"><?= csrf_field() ?><button class="btn small ghost" type="submit">Esci</button></form>
    </nav>
  </header>
  <main class="portal" id="contenuto">
    <?php if ($flash = pull_flash()): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['text']) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
  <script src="<?= e(url('/assets/app.js')) ?>"></script>
</body>
</html>
