<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Lumina') ?></title>
  <meta name="description" content="Agenda operativa per parrucchieri, palestre e studi. Chiude i buchi, adegua il prezzo e riempie le disdette.">
  <link rel="icon" href="<?= e(url('/favicon.svg')) ?>">
  <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
</head>
<body>
  <a class="skip" href="#contenuto">Vai al contenuto</a>
  <header class="nav">
    <a class="wordmark" href="<?= e(url('/')) ?>"><span class="mark" aria-hidden="true"></span> Lumina</a>
    <nav>
      <a href="<?= e(url('/#vetrina')) ?>">Vetrina</a>
      <a href="<?= e(url('/prezzi')) ?>">Piani</a>
      <a href="<?= e(url('/accedi')) ?>">Accedi</a>
      <a class="btn small" href="<?= e(url('/registrati')) ?>">Apri l'agenda</a>
    </nav>
  </header>
  <?php if ($flash = pull_flash()): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['text']) ?></div><?php endif; ?>
  <main id="contenuto"><?= $content ?></main>
  <footer class="site-footer">
    <span>Lumina · gli orari restano in Europa/Roma</span>
    <span>Caparra consigliata, non un pagamento online.</span>
  </footer>
  <script src="<?= e(url('/assets/app.js')) ?>"></script>
</body>
</html>
