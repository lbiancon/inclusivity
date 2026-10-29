<div class="grid two">
  <form class="card" method="post" action="<?= e(url('/app/prezzi')) ?>">
    <?= csrf_field() ?>
    <?= redirect_field('/app/prezzi') ?>
    <h2>Nuova fascia</h2>
    <p class="muted">Si applica lo sconto più alto che coincide con servizio, giorno e ora. Gli sconti non si sommano. Il cliente vede prezzo pieno e prezzo nuovo prima di confermare.</p>
    <label class="field">Nome <input name="name" required placeholder="Mattina leggera" value="<?= e(field('name')) ?>"></label>
    <label class="field">Servizio
      <select name="service_id">
        <option value="0">Tutti gli appuntamenti</option>
        <?php foreach ($services as $service): ?><option value="<?= (int) $service['id'] ?>"><?= e($service['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="field">Giorno
      <select name="weekday">
        <option value="0">Tutti i giorni</option>
        <?php for ($day = 1; $day <= 7; $day++): ?><option value="<?= $day ?>"><?= e(weekday_name($day)) ?></option><?php endfor; ?>
      </select>
    </label>
    <div class="split">
      <label class="field">Dalle <input type="time" name="start_time" required value="07:00"></label>
      <label class="field">Alle <input type="time" name="end_time" required value="11:00"></label>
      <label class="field">Sconto % <input name="discount_percent" required value="20"></label>
    </div>
    <button class="btn" type="submit">Attiva la fascia</button>
  </form>
  <div class="list">
    <?php foreach ($rules as $rule): ?>
      <article class="card">
        <strong><?= e($rule['name']) ?></strong>
        <span class="badge<?= (int) $rule['active'] === 1 ? ' ideal' : '' ?>"><?= (int) $rule['active'] === 1 ? 'Attiva' : 'Spenta' ?></span>
        <p><?= e($rule['service_name'] ?? 'Tutti i servizi') ?> · <?= $rule['weekday'] === null ? 'ogni giorno' : e(weekday_name((int) $rule['weekday'])) ?> · <?= e(substr((string) $rule['start_time'], 0, 5)) ?>–<?= e(substr((string) $rule['end_time'], 0, 5)) ?> · −<?= (int) $rule['discount_percent'] ?>%</p>
        <form method="post" action="<?= e(url('/app/prezzi/' . ((int) $rule['id']))) ?>"><?= csrf_field() ?><button class="btn small ghost" type="submit"><?= (int) $rule['active'] === 1 ? 'Spegni' : 'Riaccendi' ?></button></form>
      </article>
    <?php endforeach; ?>
    <?php if ($rules === []): ?><p class="empty">Nessuna fascia. Il listino resta quello del servizio.</p><?php endif; ?>
  </div>
</div>
