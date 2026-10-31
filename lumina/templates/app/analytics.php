<div class="metrics">
  <article class="metric card"><span>Incassato, 30 giorni</span><strong><?= e(euro((int) $picture['done30'])) ?></strong></article>
  <article class="metric card"><span>Ultimi 7 giorni</span><strong><?= e(euro((int) $picture['done7'])) ?></strong></article>
  <article class="metric card"><span>Già in agenda, 7 giorni</span><strong><?= e(euro((int) $picture['ahead'])) ?></strong></article>
  <article class="metric card"><span>Assenze</span><strong><?= $picture['noshow_percent'] === null ? '—' : (int) $picture['noshow_percent'] . '%' ?></strong><span><?= (int) $picture['sample'] ?> esiti</span></article>
</div>
<div class="grid two">
  <section class="panel">
    <h2>Ore più piene</h2>
    <p class="muted">Appuntamenti degli ultimi 30 giorni, ora locale. I corsi contano nell'incasso, non in queste barre.</p>
    <div class="hour-chart">
      <?php foreach ($picture['hours'] as $hour => $count): ?>
        <div>
          <i style="height: <?= (int) round($count / $picture['hour_max'] * 100) ?>%"></i>
          <span><?= (int) $hour ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="panel">
    <h2>Servizi richiesti</h2>
    <div class="bars">
      <?php $max = max(1, $picture['services'] === [] ? 1 : max($picture['services'])); ?>
      <?php foreach ($picture['services'] as $name => $count): ?>
        <div>
          <span><?= e((string) $name) ?> · <?= (int) $count ?></span>
          <div class="bar"><span style="width: <?= (int) round($count / $max * 100) ?>%"></span></div>
        </div>
      <?php endforeach; ?>
      <?php if ($picture['services'] === []): ?><p class="empty">Ancora nessuno storico.</p><?php endif; ?>
    </div>
    <h3>Oggi</h3>
    <?php foreach ($picture['occupancy'] as $lane): ?>
      <p><?= e($lane['name']) ?> · <?= (int) $lane['busy_minutes'] ?> min su <?= (int) $lane['open_minutes'] ?></p>
    <?php endforeach; ?>
  </section>
</div>
