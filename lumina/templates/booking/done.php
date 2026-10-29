<section class="section auth">
  <article class="card">
    <p class="kicker">Confermata</p>
    <h1><?= e($appointment['service_name']) ?></h1>
    <p><?= e(dt((string) $appointment['starts_at'], (string) $appointment['timezone'])) ?> – <?= e(dt((string) $appointment['ends_at'], (string) $appointment['timezone'], 'HH:mm')) ?></p>
    <p><?= e($appointment['business_name']) ?> · <?= e($appointment['staff_name']) ?><?php if ($appointment['resource_name']): ?> · <?= e($appointment['resource_name']) ?><?php endif; ?></p>
    <p><?= e(trim($appointment['address'] . ' ' . $appointment['city'])) ?></p>
    <p class="price">
      <?php if ((int) $appointment['price_cents'] !== (int) $appointment['list_price_cents']): ?><s><?= e(euro((int) $appointment['list_price_cents'])) ?></s> <?php endif; ?>
      <strong><?= e(euro((int) $appointment['price_cents'])) ?></strong>
      <?php if ($appointment['price_label'] !== ''): ?><span class="badge"><?= e($appointment['price_label']) ?></span><?php endif; ?>
    </p>
    <?php if ((int) $appointment['deposit_advised'] === 1): ?>
      <p class="callout">Per questa prenotazione l'attività può chiedere una caparra. Non è un pagamento online: concordatela in sede.</p>
    <?php endif; ?>
    <div class="actions">
      <a class="btn" href="<?= e(url('/p/' . ($appointment['public_token']))) ?>">Apri la prenotazione</a>
      <a class="btn ghost" href="<?= e(url('/p/' . ($appointment['public_token']) . '.ics')) ?>">Aggiungi al calendario</a>
    </div>
    <p class="muted">Conserva il link: non serve un account per annullare entro <?= (int) $appointment['cancel_hours'] ?> ore.</p>
  </article>
</section>
