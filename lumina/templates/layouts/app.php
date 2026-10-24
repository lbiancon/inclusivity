<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($title) ?> · <?= e($business['name']) ?></title>
  <link rel="icon" href="<?= e(url('/favicon.svg')) ?>">
  <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
</head>
<body>
  <a class="skip" href="#contenuto">Vai al contenuto</a>
  <div class="shell">
    <aside class="sidebar">
      <a class="wordmark" href="<?= e(url('/app')) ?>"><span class="mark" aria-hidden="true"></span> Lumina</a>
      <p class="biz"><?= e($business['name']) ?></p>
      <nav class="side-nav">
        <a class="<?= path_is('/app') ? 'active' : '' ?>" href="<?= e(url('/app')) ?>">Oggi</a>
        <a class="<?= path_is('/app/giornata') ? 'active' : '' ?>" href="<?= e(url('/app/giornata')) ?>">Bacheca</a>
        <a class="<?= path_is('/app/agenda') ? 'active' : '' ?>" href="<?= e(url('/app/agenda')) ?>">Settimana</a>
        <a class="<?= path_is('/app/clienti') ? 'active' : '' ?>" href="<?= e(url('/app/clienti')) ?>">Clienti</a>
        <a class="<?= path_is('/app/classi') ? 'active' : '' ?>" href="<?= e(url('/app/classi')) ?>">Corsi</a>
        <a class="<?= path_is('/app/attesa') ? 'active' : '' ?>" href="<?= e(url('/app/attesa')) ?>">Attesa</a>
        <a class="<?= path_is('/app/analisi') ? 'active' : '' ?>" href="<?= e(url('/app/analisi')) ?>">Analisi</a>
        <?php if (!empty($owner)): ?>
          <a class="<?= path_is('/app/servizi') ? 'active' : '' ?>" href="<?= e(url('/app/servizi')) ?>">Servizi</a>
          <a class="<?= path_is('/app/staff') ? 'active' : '' ?>" href="<?= e(url('/app/staff')) ?>">Persone</a>
          <a class="<?= path_is('/app/prezzi') ? 'active' : '' ?>" href="<?= e(url('/app/prezzi')) ?>">Prezzo vivo</a>
          <a class="<?= path_is('/app/pacchetti') ? 'active' : '' ?>" href="<?= e(url('/app/pacchetti')) ?>">Tessere</a>
          <a class="<?= path_is('/app/impostazioni') ? 'active' : '' ?>" href="<?= e(url('/app/impostazioni')) ?>">Impostazioni</a>
          <a class="<?= path_is('/app/piano') ? 'active' : '' ?>" href="<?= e(url('/app/piano')) ?>">Piano</a>
        <?php endif; ?>
      </nav>
      <a href="<?= e(url('/b/' . ($business['slug']))) ?>">Pagina pubblica</a>
    </aside>
    <div class="workspace" id="contenuto">
      <header class="topbar">
        <div>
          <p class="kicker"><?= e(category_label((string) $business['category'])) ?></p>
          <h1><?= e($title) ?></h1>
        </div>
        <div class="actions">
          <a class="bell" href="<?= e(url('/app/notifiche')) ?>">Notifiche <?php if (($unread ?? 0) > 0): ?><strong><?= (int) $unread ?></strong><?php endif; ?></a>
          <span><?= e($user['name']) ?></span>
          <form method="post" action="<?= e(url('/esci')) ?>"><?= csrf_field() ?><button class="btn small ghost" type="submit">Esci</button></form>
        </div>
      </header>
      <?php if ($flash = pull_flash()): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['text']) ?></div><?php endif; ?>
      <?= $content ?>
    </div>
  </div>
  <script src="<?= e(url('/assets/app.js')) ?>"></script>
</body>
</html>
