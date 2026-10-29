<?php $money = static fn (int $cents): string => number_format($cents / 100, 2, ',', ''); ?>
<div class="grid two">
  <form class="card" method="post" action="<?= e(url('/app/servizi')) ?>">
    <?= csrf_field() ?>
    <?= redirect_field('/app/servizi') ?>
    <h2>Nuovo servizio</h2>
    <label class="field">Nome <input name="name" required value="<?= e(field('name')) ?>"></label>
    <label class="field">Descrizione <textarea name="description"><?= e(field('description')) ?></textarea></label>
    <div class="split">
      <label class="field">Durata minuti <input name="duration_min" inputmode="numeric" required value="<?= e(field('duration_min', '45')) ?>"></label>
      <label class="field">Prezzo <input name="price" required value="<?= e(field('price', '30')) ?>"></label>
      <label class="field">Margine prima <input name="buffer_before" inputmode="numeric" value="<?= e(field('buffer_before', '0')) ?>"></label>
      <label class="field">Margine dopo <input name="buffer_after" inputmode="numeric" value="<?= e(field('buffer_after', '0')) ?>"></label>
      <label class="field">Tipo
        <select name="kind">
          <option value="appointment"<?= selected('kind', 'appointment', 'appointment') ?>>Appuntamento</option>
          <option value="class"<?= selected('kind', 'class') ?>>Corso</option>
        </select>
      </label>
      <label class="field">Posti, se è un corso <input name="capacity" inputmode="numeric" value="<?= e(field('capacity', '10')) ?>"></label>
    </div>
    <p>Chi lo esegue</p>
    <div class="checks">
      <?php foreach ($staff as $person): ?>
        <label><input type="checkbox" name="staff_ids[]" value="<?= (int) $person['id'] ?>"> <?= e($person['name']) ?></label>
      <?php endforeach; ?>
    </div>
    <p>Postazioni necessarie</p>
    <div class="checks">
      <?php foreach ($resources as $resource): ?>
        <label><input type="checkbox" name="resource_ids[]" value="<?= (int) $resource['id'] ?>"> <?= e($resource['name']) ?></label>
      <?php endforeach; ?>
    </div>
    <label class="checks"><input type="checkbox" name="active" value="1" checked> Attivo</label>
    <button class="btn" type="submit">Salva</button>
  </form>
  <div class="list">
    <?php foreach ($services as $service): ?>
      <?php
        $sid = (int) $service['id'];
        $linkedStaff = $links['staff'][$sid] ?? [];
        $linkedResources = $links['resources'][$sid] ?? [];
      ?>
      <details class="card">
        <summary><?= e($service['name']) ?> · <?= e(euro((int) $service['price_cents'])) ?> <?= (int) $service['active'] === 1 ? '' : '· spento' ?></summary>
        <form method="post" action="<?= e(url('/app/servizi/' . ($sid))) ?>">
          <?= csrf_field() ?>
          <?= redirect_field('/app/servizi') ?>
          <label class="field">Nome <input name="name" required value="<?= e($service['name']) ?>"></label>
          <label class="field">Descrizione <textarea name="description"><?= e($service['description']) ?></textarea></label>
          <div class="split">
            <label class="field">Durata <input name="duration_min" required value="<?= (int) $service['duration_min'] ?>"></label>
            <label class="field">Prezzo <input name="price" required value="<?= e($money((int) $service['price_cents'])) ?>"></label>
            <label class="field">Margine prima <input name="buffer_before" value="<?= (int) $service['buffer_before'] ?>"></label>
            <label class="field">Margine dopo <input name="buffer_after" value="<?= (int) $service['buffer_after'] ?>"></label>
            <label class="field">Tipo
              <select name="kind">
                <option value="appointment"<?= $service['kind'] === 'appointment' ? ' selected' : '' ?>>Appuntamento</option>
                <option value="class"<?= $service['kind'] === 'class' ? ' selected' : '' ?>>Corso</option>
              </select>
            </label>
            <label class="field">Posti <input name="capacity" value="<?= (int) $service['capacity'] ?>"></label>
          </div>
          <div class="checks">
            <?php foreach ($staff as $person): ?>
              <label><input type="checkbox" name="staff_ids[]" value="<?= (int) $person['id'] ?>"<?= in_array((int) $person['id'], $linkedStaff, true) ? ' checked' : '' ?>> <?= e($person['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <div class="checks">
            <?php foreach ($resources as $resource): ?>
              <label><input type="checkbox" name="resource_ids[]" value="<?= (int) $resource['id'] ?>"<?= in_array((int) $resource['id'], $linkedResources, true) ? ' checked' : '' ?>> <?= e($resource['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="checks"><input type="checkbox" name="active" value="1"<?= (int) $service['active'] === 1 ? ' checked' : '' ?>> Attivo</label>
          <button class="btn small" type="submit">Aggiorna</button>
        </form>
      </details>
    <?php endforeach; ?>
  </div>
</div>
