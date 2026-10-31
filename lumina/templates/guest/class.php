<?php
$labels = ['booked' => 'Iscritto', 'cancelled' => 'Annullata', 'attended' => 'Presente', 'no_show' => 'Assente'];
$status = (string) $booking['status'];
?>
<section class="section auth">
  <article class="card">
    <p class="kicker"><?= e($booking['business_name']) ?></p>
    <h1><?= e($booking['service_name']) ?></h1>
    <p><span class="badge <?= e($status) ?>"><?= e($labels[$status] ?? $status) ?></span></p>
    <p><?= e(dt((string) $booking['starts_at'], (string) $booking['timezone'])) ?> · <?= e($booking['staff_name']) ?></p>
    <p><?= e($booking['client_name']) ?> · <?= e(euro((int) $booking['price_cents'])) ?> <?php if ($booking['price_label'] !== ''): ?><span class="badge"><?= e($booking['price_label']) ?></span><?php endif; ?></p>
    <a class="btn ghost" href="<?= e(url('/b/' . ($booking['slug']))) ?>">Torna all'attività</a>
  </article>
  <?php if ($status === 'booked' && (string) $booking['session_status'] === 'scheduled'): ?>
    <form class="card" method="post" action="<?= e(url('/c/' . ($booking['public_token']) . '/annulla')) ?>" data-confirm="Annullare l'iscrizione?">
      <?= csrf_field() ?>
      <h2>Annulla l'iscrizione</h2>
      <p class="muted">Fino a <?= (int) $booking['cancel_hours'] ?> ore prima. Il credito, se usato, torna sulla tessera.</p>
      <button class="btn danger" type="submit">Annulla</button>
    </form>
  <?php endif; ?>
</section>
