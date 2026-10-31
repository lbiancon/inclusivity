<?php
$labels = ['waiting' => 'In attesa', 'offered' => 'Posto proposto', 'booked' => 'Prenotato', 'expired' => 'Scaduta', 'cancelled' => 'Annullata'];
$status = (string) $entry['status'];
?>
<section class="section auth">
  <article class="card">
    <p class="kicker"><?= e($entry['business_name']) ?></p>
    <h1><?= e($entry['service_name']) ?></h1>
    <p><span class="badge <?= e($status) ?>"><?= e($labels[$status] ?? $status) ?></span></p>
    <p><?= e($entry['client_name']) ?> · <?= e($entry['preferred_date']) ?> tra <?= e(substr((string) $entry['time_from'], 0, 5)) ?> e <?= e(substr((string) $entry['time_to'], 0, 5)) ?></p>
    <?php if ($status === 'offered' && $entry['offer_starts_at']): ?>
      <p class="notice">Si è liberato il <?= e(dt((string) $entry['offer_starts_at'], (string) $entry['timezone'])) ?>. L'offerta scade <?= e(dt((string) $entry['offer_expires_at'], (string) $entry['timezone'], 'HH:mm')) ?>.</p>
      <form method="post" action="<?= e(url('/w/' . ($entry['public_token']) . '/accetta')) ?>">
        <?= csrf_field() ?>
        <label class="checks"><input type="checkbox" name="use_package" value="1"> Usa un credito della tessera, se ne hai uno</label>
        <button class="btn" type="submit">Accetto il posto</button>
      </form>
    <?php elseif ($status === 'waiting'): ?>
      <p class="muted">Appena un orario compatibile si libera, questa pagina mostra l'offerta. Non arriva un'email: tieni il link.</p>
    <?php endif; ?>
  </article>
  <?php if (in_array($status, ['waiting', 'offered'], true)): ?>
    <form class="card" method="post" action="<?= e(url('/w/' . ($entry['public_token']) . '/annulla')) ?>" data-confirm="Uscire dalla lista?">
      <?= csrf_field() ?>
      <button class="btn danger" type="submit">Esci dalla lista</button>
    </form>
  <?php endif; ?>
</section>
