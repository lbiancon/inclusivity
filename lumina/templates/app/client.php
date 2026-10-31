<div class="grid two">
  <form class="card" method="post" action="<?= e(url('/app/clienti/' . ((int) $client['id']))) ?>">
    <?= csrf_field() ?>
    <?= redirect_field('/app/clienti/' . $client['id']) ?>
    <label class="field">Nome <input name="name" required value="<?= e($client['name']) ?>"></label>
    <label class="field">Email <input type="email" name="email" value="<?= e($client['email'] ?? '') ?>"></label>
    <label class="field">Telefono <input name="phone" value="<?= e($client['phone']) ?>"></label>
    <label class="field">Operatore preferito
      <select name="preferred_staff_id">
        <option value="0">Nessuno</option>
        <?php foreach ($staff as $person): ?>
          <option value="<?= (int) $person['id'] ?>"<?= (int) $client['preferred_staff_id'] === (int) $person['id'] ? ' selected' : '' ?>><?= e($person['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">Preferenze <input name="preferences" value="<?= e($client['preferences']) ?>"></label>
    <label class="field">Allergie <input name="allergies" value="<?= e($client['allergies']) ?>"></label>
    <label class="field">Note <textarea name="notes"><?= e($client['notes']) ?></textarea></label>
    <button class="btn" type="submit">Salva scheda</button>
  </form>
  <div>
    <article class="panel">
      <h2>Affidabilità</h2>
      <p><?= (int) $score['kept'] ?> presenze, <?= (int) $score['missed'] ?> assenze.</p>
      <?php if ($score['percent'] === null): ?>
        <p class="muted">Il giudizio parte da due esiti. Prima di allora non consigliamo la caparra.</p>
      <?php else: ?>
        <div class="meter"><span style="width: <?= (int) $score['percent'] ?>%"></span></div>
        <p><?= (int) $score['percent'] ?>% di presenze.</p>
        <?php if ($score['advise_deposit']): ?><p class="callout">Sotto la soglia dell'attività: sulle prossime prenotazioni compare il consiglio di caparra.</p><?php endif; ?>
      <?php endif; ?>
    </article>
    <form class="card" method="post" action="<?= e(url('/app/clienti/' . ((int) $client['id']) . '/tessera')) ?>">
      <?= csrf_field() ?>
      <h2>Attiva una tessera</h2>
      <label class="field">Tessera
        <select name="package_id">
          <?php foreach ($packages as $package): ?>
            <option value="<?= (int) $package['id'] ?>"><?= e($package['name']) ?> · <?= (int) $package['credits'] ?> crediti · <?= e(euro((int) $package['price_cents'])) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if ($packages === []): ?><p class="muted">Prima crea una tessera nel catalogo.</p><?php endif; ?>
      <button class="btn small" type="submit"<?= $packages === [] ? ' disabled' : '' ?>>Attiva</button>
    </form>
    <h3>Crediti</h3>
    <div class="list">
      <?php foreach ($cards as $card): ?>
        <p><?= e($card['name']) ?> · <?= (int) $card['credits_left'] ?>/<?= (int) $card['credits_total'] ?> fino al <?= e(dt((string) $card['expires_at'], (string) $business['timezone'], 'd MMM y')) ?></p>
      <?php endforeach; ?>
      <?php if ($cards === []): ?><p class="muted">Nessuna tessera.</p><?php endif; ?>
    </div>
  </div>
</div>
<h2>Storico</h2>
<div class="list">
  <?php foreach ($history as $row): ?>
    <a href="<?= e(url('/app/appuntamenti/' . ((int) $row['id']))) ?>">
      <time><?= e(dt((string) $row['starts_at'], (string) $business['timezone'], 'd MMM HH:mm')) ?></time>
      <span><?= e($row['service_name']) ?> · <?= e($row['staff_name']) ?></span>
      <span class="badge <?= e($row['status']) ?>"><?= e(appointment_status_label((string) $row['status'])) ?></span>
    </a>
  <?php endforeach; ?>
</div>
