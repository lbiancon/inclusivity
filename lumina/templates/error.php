<section class="section">
  <p class="kicker"><?= (int) ($status ?? 400) ?></p>
  <h1><?= e($title ?? 'Errore') ?></h1>
  <p class="lede error-detail"><?= e($message ?? '') ?></p>
  <p><a class="btn" href="<?= e(url('/')) ?>">Torna alla vetrina</a></p>
</section>
