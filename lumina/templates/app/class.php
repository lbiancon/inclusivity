<?php
$labels = ['booked' => 'Iscritto', 'cancelled' => 'Annullata', 'attended' => 'Presente', 'no_show' => 'Assente'];
$taken = 0;
foreach ($bookings as $booking) {
    if ((string) $booking['status'] === 'booked') {
        $taken++;
    }
}
?>
<article class="panel">
  <p><?= e(dt((string) $session['starts_at'], (string) $business['timezone'])) ?> – <?= e(dt((string) $session['ends_at'], (string) $business['timezone'], 'HH:mm')) ?></p>
  <p><?= e($session['staff_name']) ?><?php if ($session['resource_name']): ?> · <?= e($session['resource_name']) ?><?php endif; ?> · <?= $taken ?>/<?= (int) $session['capacity'] ?> · <?= e(euro((int) $session['price_cents'])) ?></p>
  <?php if ($session['notes'] !== ''): ?><p><?= e($session['notes']) ?></p><?php endif; ?>
  <?php if ((string) $session['status'] === 'scheduled'): ?>
    <form class="actions" method="post" action="<?= e(url('/app/classi/' . ((int) $session['id']) . '/stato')) ?>">
      <?= csrf_field() ?>
      <button class="btn small ghost" name="status" value="completed" type="submit">Segna chiuso</button>
      <button class="btn small danger" name="status" value="cancelled" type="submit">Annulla il corso</button>
    </form>
  <?php else: ?>
    <p class="badge <?= e($session['status']) ?>"><?= e((string) $session['status']) ?></p>
  <?php endif; ?>
</article>
<div class="grid two">
  <div class="list">
    <?php foreach ($bookings as $booking): ?>
      <article class="card">
        <strong><?= e($booking['client_name']) ?></strong>
        <span class="badge <?= e($booking['status']) ?>"><?= e($labels[(string) $booking['status']] ?? '') ?></span>
        <span><?= e(euro((int) $booking['price_cents'])) ?> <?= e($booking['price_label']) ?></span>
        <?php if ((string) $booking['status'] === 'booked'): ?>
          <form method="post" action="<?= e(url('/app/classi/iscrizioni/' . ((int) $booking['id']))) ?>">
            <?= csrf_field() ?>
            <button class="btn small ghost" name="status" value="attended">Presente</button>
            <button class="btn small ghost" name="status" value="no_show">Assente</button>
            <button class="btn small danger" name="status" value="cancelled">Annulla</button>
          </form>
        <?php endif; ?>
        <?php if ($booking['client_package_id'] !== null): ?>
          <form method="post" action="<?= e(url('/app/classi/iscrizioni/' . ((int) $booking['id']) . '/rimborsa')) ?>"><?= csrf_field() ?><button class="btn small ghost">Restituisci credito</button></form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if ((string) $session['status'] === 'scheduled'): ?>
    <form class="card" method="post" action="<?= e(url('/app/classi/' . ((int) $session['id']) . '/prenota')) ?>">
      <?= csrf_field() ?>
      <?= redirect_field('/app/classi/' . $session['id']) ?>
      <h2>Iscrivi</h2>
      <label class="field">Cliente
        <select name="client_id">
          <option value="0">Nuovo</option>
          <?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>"><?= e($client['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field">Nome <input name="client_name"></label>
      <label class="field">Email <input type="email" name="client_email"></label>
      <label class="field">Telefono <input name="client_phone"></label>
      <label class="checks"><input type="checkbox" name="use_package" value="1"> Scala un credito</label>
      <button class="btn" type="submit">Iscrivi</button>
    </form>
  <?php endif; ?>
</div>
