<div class="actions">
  <form method="post" action="<?= e(url('/app/notifiche/lette')) ?>"><?= csrf_field() ?><button class="btn small ghost" type="submit">Segna tutte come lette</button></form>
</div>
<div class="list">
  <?php if ($rows === []): ?><p class="empty">Nessuna notifica. Restano dentro Lumina: non partono email.</p><?php endif; ?>
  <?php foreach ($rows as $row): ?>
    <article class="card">
      <strong><?= e($row['title']) ?></strong>
      <?php if ($row['read_at'] === null): ?><span class="badge ideal">Nuova</span><?php endif; ?>
      <p><?= e($row['body']) ?></p>
      <p class="muted"><?= e(dt((string) $row['created_at'], (string) $business['timezone'])) ?></p>
      <?php if ($row['href'] !== ''): ?><a href="<?= e(url((string) $row['href'])) ?>">Apri</a><?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
