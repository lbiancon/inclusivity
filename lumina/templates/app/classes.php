<?php $classStatus = ['scheduled' => 'In programma', 'cancelled' => 'Annullato', 'completed' => 'Chiuso']; ?>
<div class="grid two">
  <form class="card" method="post" action="<?= e(url('/app/classi')) ?>">
    <?= csrf_field() ?>
    <?= redirect_field('/app/classi') ?>
    <h2>Nuovo corso</h2>
    <?php if ($services === []): ?><p class="callout">Serve un servizio di tipo corso, dal catalogo.</p><?php endif; ?>
    <label class="field">Corso
      <select name="service_id" required>
        <?php foreach ($services as $service): ?>
          <option value="<?= (int) $service['id'] ?>"><?= e($service['name']) ?> · <?= (int) $service['duration_min'] ?> min</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">Chi lo tiene
      <select name="staff_id" required>
        <?php foreach ($staff as $person): ?><option value="<?= (int) $person['id'] ?>"><?= e($person['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="field">Sala
      <select name="resource_id">
        <option value="0">Nessuna</option>
        <?php foreach ($resources as $resource): ?><option value="<?= (int) $resource['id'] ?>"><?= e($resource['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <div class="split">
      <label class="field">Giorno <input type="date" name="date" required value="<?= e(\Lumina\App::$clock->today(\Lumina\App::$engine->zone($business))) ?>"></label>
      <label class="field">Ora <input type="time" name="time" required value="18:15"></label>
      <label class="field">Posti <input name="capacity" required value="12"></label>
    </div>
    <label class="field">Note <input name="notes"></label>
    <button class="btn" type="submit">Metti in agenda</button>
  </form>
  <div class="list">
    <?php foreach ($sessions as $session): ?>
      <a class="card" href="<?= e(url('/app/classi/' . ((int) $session['id']))) ?>">
        <strong><?= e($session['service_name']) ?></strong>
        <span><?= e(dt((string) $session['starts_at'], (string) $business['timezone'])) ?> · <?= e($session['staff_name']) ?></span>
        <span class="muted"><?= (int) $session['taken'] ?>/<?= (int) $session['capacity'] ?> · <?= e($classStatus[(string) $session['status']] ?? '') ?></span>
      </a>
    <?php endforeach; ?>
    <?php if ($sessions === []): ?><p class="empty">Nessun corso in agenda.</p><?php endif; ?>
  </div>
</div>
