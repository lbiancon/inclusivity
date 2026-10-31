<?php
$kinds = ['chair' => 'Poltrona', 'room' => 'Sala', 'equipment' => 'Attrezzo', 'other' => 'Altro'];
$byStaff = [];
foreach ($hours as $row) {
    $byStaff[(int) $row['staff_id']][] = $row;
}
?>
<div class="grid two">
  <div>
    <form class="card" method="post" action="<?= e(url('/app/staff')) ?>">
      <?= csrf_field() ?>
      <?= redirect_field('/app/staff') ?>
      <h2>Nuova persona</h2>
      <label class="field">Nome <input name="name" required value="<?= e(field('name')) ?>"></label>
      <label class="field">Ruolo <input name="title" value="<?= e(field('title')) ?>"></label>
      <label class="field">Note <textarea name="bio"><?= e(field('bio')) ?></textarea></label>
      <label class="field">Colore <input type="color" name="color" value="<?= e(field('color', '#1f6b45')) ?>"></label>
      <label class="field">Email di accesso, facoltativa <input type="email" name="email" value="<?= e(field('email')) ?>"></label>
      <label class="field">Password, almeno 10 caratteri <input type="password" name="password" autocomplete="new-password"></label>
      <label class="checks"><input type="checkbox" name="active" value="1" checked> Attiva</label>
      <button class="btn" type="submit">Salva</button>
    </form>
    <form class="card" method="post" action="<?= e(url('/app/risorse')) ?>">
      <?= csrf_field() ?>
      <?= redirect_field('/app/staff') ?>
      <h2>Nuova postazione</h2>
      <p class="muted">Poltrone, sale, attrezzi. Un servizio le richiede e Lumina ne tiene una libera per tutto il margine dell'appuntamento.</p>
      <label class="field">Nome <input name="name" required></label>
      <label class="field">Tipo
        <select name="kind">
          <?php foreach ($kinds as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field">Quante in parallelo <input name="capacity" value="1"></label>
      <label class="checks"><input type="checkbox" name="active" value="1" checked> Attiva</label>
      <button class="btn" type="submit">Salva postazione</button>
    </form>
  </div>
  <div class="list">
    <?php foreach ($staff as $person): ?>
      <?php $pid = (int) $person['id']; ?>
      <details class="card">
        <summary><i class="dot" style="background: <?= e(hex_color($person['color'])) ?>"></i> <?= e($person['name']) ?><?= $person['email'] ? ' · ' . e($person['email']) : '' ?></summary>
        <form method="post" action="<?= e(url('/app/staff/' . ($pid))) ?>">
          <?= csrf_field() ?>
          <?= redirect_field('/app/staff') ?>
          <label class="field">Nome <input name="name" required value="<?= e($person['name']) ?>"></label>
          <label class="field">Ruolo <input name="title" value="<?= e($person['title']) ?>"></label>
          <label class="field">Note <textarea name="bio"><?= e($person['bio']) ?></textarea></label>
          <label class="field">Colore <input type="color" name="color" value="<?= e(hex_color($person['color'])) ?>"></label>
          <?php if ($person['email']): ?>
            <p class="muted">Accesso: <?= e($person['email']) ?>. Una password nuova sostituisce la precedente.</p>
            <label class="field">Nuova password <input type="password" name="password" autocomplete="new-password"></label>
          <?php else: ?>
            <label class="field">Email di accesso <input type="email" name="email"></label>
            <label class="field">Password <input type="password" name="password" autocomplete="new-password"></label>
          <?php endif; ?>
          <label class="checks"><input type="checkbox" name="active" value="1"<?= (int) $person['active'] === 1 ? ' checked' : '' ?>> Attiva</label>
          <button class="btn small" type="submit">Aggiorna</button>
        </form>
        <form method="post" action="<?= e(url('/app/staff/' . ($pid) . '/orari')) ?>">
          <?= csrf_field() ?>
          <?= redirect_field('/app/staff') ?>
          <h3>Turno personale</h3>
          <label class="checks"><input type="checkbox" name="custom" value="1"<?= isset($byStaff[$pid]) ? ' checked' : '' ?>> Orari diversi da quelli dell'attività</label>
          <?php \Lumina\View::partial('partials/hours', ['hours' => $byStaff[$pid] ?? []]); ?>
          <button class="btn small ghost" type="submit">Salva il turno</button>
        </form>
      </details>
    <?php endforeach; ?>
    <?php foreach ($resources as $resource): ?>
      <details class="card">
        <summary><?= e($resource['name']) ?> · <?= e($kinds[(string) $resource['kind']] ?? 'Altro') ?></summary>
        <form method="post" action="<?= e(url('/app/risorse/' . ((int) $resource['id']))) ?>">
          <?= csrf_field() ?>
          <?= redirect_field('/app/staff') ?>
          <label class="field">Nome <input name="name" required value="<?= e($resource['name']) ?>"></label>
          <label class="field">Tipo
            <select name="kind">
              <?php foreach ($kinds as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $resource['kind'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field">In parallelo <input name="capacity" value="<?= (int) $resource['capacity'] ?>"></label>
          <label class="checks"><input type="checkbox" name="active" value="1"<?= (int) $resource['active'] === 1 ? ' checked' : '' ?>> Attiva</label>
          <button class="btn small" type="submit">Aggiorna</button>
        </form>
      </details>
    <?php endforeach; ?>
  </div>
</div>
