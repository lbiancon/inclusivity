<div class="list">
  <?php if ($rows === []): ?><p class="empty">Nessuno in attesa. Quando annulli un appuntamento, il posto va al primo compatibile.</p><?php endif; ?>
  <?php foreach ($rows as $row): ?>
    <article class="card">
      <p>
        <span class="badge <?= e($row['status']) ?>"><?= $row['status'] === 'offered' ? 'Offerta' : 'In attesa' ?></span>
        <strong><?= e($row['client_name']) ?></strong> · <?= e($row['service_name']) ?>
      </p>
      <p><?= e($row['preferred_date']) ?> <?= e(substr((string) $row['time_from'], 0, 5)) ?>–<?= e(substr((string) $row['time_to'], 0, 5)) ?><?php if ($row['staff_name']): ?> · <?= e($row['staff_name']) ?><?php endif; ?> · <?= e($row['phone']) ?></p>
      <?php if ($row['status'] === 'offered'): ?>
        <p class="notice">Posto del <?= e(dt((string) $row['offer_starts_at'], (string) $business['timezone'])) ?>, scade <?= e(dt((string) $row['offer_expires_at'], (string) $business['timezone'], 'HH:mm')) ?>.</p>
        <form method="post" action="<?= e(url('/app/attesa/' . ((int) $row['id']) . '/accetta')) ?>">
          <?= csrf_field() ?>
          <label class="checks"><input type="checkbox" name="use_package" value="1"> Scala un credito</label>
          <button class="btn small" type="submit">Conferma al banco</button>
        </form>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/app/attesa/' . ((int) $row['id']) . '/annulla')) ?>" data-confirm="Chiudere la richiesta?">
        <?= csrf_field() ?>
        <button class="btn small danger" type="submit">Chiudi</button>
      </form>
    </article>
  <?php endforeach; ?>
</div>
