<?php $money = static fn (int $cents): string => number_format($cents / 100, 2, ',', ''); ?>
<div class="grid two">
  <form class="card" method="post" action="<?= e(url('/app/pacchetti')) ?>">
    <?= csrf_field() ?>
    <?= redirect_field('/app/pacchetti') ?>
    <h2>Nuova tessera</h2>
    <label class="field">Nome <input name="name" required value="<?= e(field('name')) ?>"></label>
    <label class="field">Vale per
      <select name="service_id">
        <option value="0">Qualsiasi servizio</option>
        <?php foreach ($services as $service): ?><option value="<?= (int) $service['id'] ?>"><?= e($service['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <div class="split">
      <label class="field">Crediti <input name="credits" required value="<?= e(field('credits', '10')) ?>"></label>
      <label class="field">Prezzo <input name="price" required value="<?= e(field('price', '150')) ?>"></label>
      <label class="field">Giorni di validità <input name="valid_days" required value="<?= e(field('valid_days', '90')) ?>"></label>
    </div>
    <label class="checks"><input type="checkbox" name="active" value="1" checked> In vendita</label>
    <button class="btn" type="submit">Salva</button>
  </form>
  <div class="list">
    <?php foreach ($packages as $package): ?>
      <details class="card">
        <summary><?= e($package['name']) ?> · <?= (int) $package['credits'] ?> crediti</summary>
        <form method="post" action="<?= e(url('/app/pacchetti/' . ((int) $package['id']))) ?>">
          <?= csrf_field() ?>
          <?= redirect_field('/app/pacchetti') ?>
          <label class="field">Nome <input name="name" required value="<?= e($package['name']) ?>"></label>
          <label class="field">Servizio
            <select name="service_id">
              <option value="0">Qualsiasi</option>
              <?php foreach ($services as $service): ?>
                <option value="<?= (int) $service['id'] ?>"<?= (int) $package['service_id'] === (int) $service['id'] ? ' selected' : '' ?>><?= e($service['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field">Crediti <input name="credits" value="<?= (int) $package['credits'] ?>"></label>
          <label class="field">Prezzo <input name="price" value="<?= e($money((int) $package['price_cents'])) ?>"></label>
          <label class="field">Giorni <input name="valid_days" value="<?= (int) $package['valid_days'] ?>"></label>
          <label class="checks"><input type="checkbox" name="active" value="1"<?= (int) $package['active'] === 1 ? ' checked' : '' ?>> In vendita</label>
          <button class="btn small" type="submit">Aggiorna</button>
        </form>
      </details>
    <?php endforeach; ?>
  </div>
</div>
