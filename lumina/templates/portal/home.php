<h1>Ciao <?= e(first_name((string) $user['name'])) ?></h1>
<p class="muted">Qui trovi le prenotazioni collegate alla tua email. Le notifiche restano nell'agenda dell'attività: non partono SMS né email.</p>

<?php if ($offers !== []): ?>
  <h2>Posti appena liberati</h2>
  <div class="list">
    <?php foreach ($offers as $offer): ?>
      <article class="card">
        <strong><?= e($offer['service_name']) ?></strong> · <?= e($offer['business_name']) ?>
        <p>Proposto per <?= e(dt((string) $offer['offer_starts_at'], (string) $offer['timezone'])) ?>, scade <?= e(dt((string) $offer['offer_expires_at'], (string) $offer['timezone'], 'd MMM HH:mm')) ?>.</p>
        <form method="post" action="<?= e(url('/portale/offerte/' . ((int) $offer['id']) . '/accetta')) ?>">
          <?= csrf_field() ?>
          <label class="checks"><input type="checkbox" name="use_package" value="1"> Scala un credito</label>
          <button class="btn small" type="submit">Accetto</button>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2>In arrivo</h2>
<div class="list">
  <?php if ($upcoming === []): ?><p class="empty">Nessuna prenotazione futura.</p><?php endif; ?>
  <?php foreach ($upcoming as $row): ?>
    <article class="appt">
      <time><?= e(dt((string) $row['starts_at'], (string) $row['timezone'], 'd MMM HH:mm')) ?></time>
      <div>
        <strong><?= e($row['service_name']) ?></strong>
        <span class="muted"><?= e($row['business_name']) ?> · <?= e($row['staff_name']) ?></span>
      </div>
      <div class="actions">
        <a class="btn small ghost" href="<?= e(url('/portale/appuntamenti/' . ((int) $row['id']) . '.ics')) ?>">Calendario</a>
        <?php if ((string) $row['status'] === 'confirmed'): ?>
          <form method="post" action="<?= e(url('/portale/appuntamenti/' . ((int) $row['id']) . '/annulla')) ?>" data-confirm="Annullare?">
            <?= csrf_field() ?>
            <button class="btn small danger" type="submit">Annulla</button>
          </form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if ($cards !== []): ?>
  <h2>Tessere</h2>
  <div class="grid">
    <?php foreach ($cards as $card): ?>
      <article class="card">
        <h3><?= e($card['name']) ?></h3>
        <p><?= e($card['business_name']) ?></p>
        <p><strong><?= (int) $card['credits_left'] ?></strong> crediti su <?= (int) $card['credits_total'] ?></p>
        <p class="muted">Valida fino al <?= e(dt((string) $card['expires_at'], 'Europe/Rome', 'd MMM y')) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2>Passate</h2>
<div class="list">
  <?php foreach ($past as $row): ?>
    <article class="appt">
      <time><?= e(dt((string) $row['starts_at'], (string) $row['timezone'], 'd MMM')) ?></time>
      <div><?= e($row['service_name']) ?> · <?= e($row['business_name']) ?></div>
      <span class="badge <?= e($row['status']) ?>"><?= e(appointment_status_label((string) $row['status'])) ?></span>
    </article>
  <?php endforeach; ?>
  <?php if ($past === []): ?><p class="empty">Lo storico è ancora vuoto.</p><?php endif; ?>
</div>
