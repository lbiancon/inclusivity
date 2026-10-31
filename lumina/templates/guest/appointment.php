<?php $open = !in_array((string) $appointment['status'], ['cancelled', 'completed', 'no_show'], true); ?>
<section class="section auth">
  <article class="card">
    <p class="kicker"><?= e($appointment['business_name']) ?></p>
    <h1><?= e($appointment['service_name']) ?></h1>
    <p><span class="badge <?= e($appointment['status']) ?>"><?= e(appointment_status_label((string) $appointment['status'])) ?></span></p>
    <p><?= e(dt((string) $appointment['starts_at'], (string) $appointment['timezone'])) ?> – <?= e(dt((string) $appointment['ends_at'], (string) $appointment['timezone'], 'HH:mm')) ?></p>
    <p><?= e($appointment['staff_name']) ?> · <?= e($appointment['client_name']) ?></p>
    <p><?= e(trim($appointment['address'] . ', ' . $appointment['city'])) ?></p>
    <p><strong><?= e(euro((int) $appointment['price_cents'])) ?></strong> <?php if ($appointment['price_label'] !== ''): ?><span class="badge"><?= e($appointment['price_label']) ?></span><?php endif; ?></p>
    <?php if ($appointment['notes'] !== ''): ?><p><?= e($appointment['notes']) ?></p><?php endif; ?>
    <?php if ($appointment['cancel_reason'] !== ''): ?><p class="muted">Motivo: <?= e($appointment['cancel_reason']) ?></p><?php endif; ?>
    <div class="actions">
      <a class="btn ghost" href="<?= e(url('/p/' . ($appointment['public_token']) . '.ics')) ?>">Calendario</a>
      <a class="btn ghost" href="<?= e(url('/b/' . ($appointment['slug']))) ?>">Altri orari</a>
    </div>
  </article>
  <?php if ($open && (string) $appointment['status'] === 'confirmed'): ?>
    <form class="card" method="post" action="<?= e(url('/p/' . ($appointment['public_token']) . '/annulla')) ?>" data-confirm="Annullare questa prenotazione?">
      <?= csrf_field() ?>
      <h2>Annulla</h2>
      <p class="muted">Puoi farlo fino a <?= (int) $appointment['cancel_hours'] ?> ore prima. Un credito di tessera torna sulla tessera.</p>
      <label class="field">Motivo <input name="reason" maxlength="180"></label>
      <button class="btn danger" type="submit">Annulla la prenotazione</button>
    </form>
  <?php endif; ?>
</section>
