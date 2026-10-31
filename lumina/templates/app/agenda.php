<div class="actions noprint">
  <a class="btn small ghost" href="<?= e(url('/app/agenda?data=' . ($previous))) ?>">Settimana prima</a>
  <a class="btn small ghost" href="<?= e(url('/app/agenda?data=' . ($next))) ?>">Settimana dopo</a>
  <a class="btn small ghost" href="<?= e(url('/app/esporta.csv?data=' . ($monday))) ?>">Esporta CSV</a>
</div>
<div class="week">
  <?php foreach ($days as $day): ?>
    <?php $local = DateTimeImmutable::createFromFormat('!Y-m-d', $day); ?>
    <article class="card">
      <h3><a href="<?= e(url('/app/giornata?data=' . ($day))) ?>"><?= e($local ? weekday_name((int) $local->format('N')) : $day) ?> <?= e($local ? $local->format('j/n') : '') ?></a></h3>
      <?php foreach ($byDay[$day] as $row): ?>
        <a class="mini" href="<?= e(url('/app/appuntamenti/' . ((int) $row['id']))) ?>">
          <i class="dot" style="background: <?= e(hex_color($row['color'])) ?>"></i>
          <?= e(dt((string) $row['starts_at'], (string) $business['timezone'], 'HH:mm')) ?>
          <?= e($row['client_name']) ?>
          <span class="muted"><?= e($row['service_name']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if ($byDay[$day] === []): ?><p class="muted">Vuoto</p><?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
