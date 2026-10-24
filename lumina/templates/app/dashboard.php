<?php $rows = $insights['today_rows']; ?>
<div class="metrics">
  <article class="metric card"><span>Incasso previsto oggi</span><strong><?= e(euro((int) $insights['expected_today'])) ?></strong></article>
  <article class="metric card"><span>Completato questa settimana</span><strong><?= e(euro((int) $insights['completed_week'])) ?></strong></article>
  <article class="metric card"><span>Slot ideali ancora aperti</span><strong><?= (int) $insights['ideals'] ?></strong></article>
  <article class="metric card"><span>In lista</span><strong><?= (int) $insights['waiting'] ?></strong> <span><?= (int) $insights['offered'] ?> offerte aperte</span></article>
</div>
<div class="grid two">
  <section class="panel">
    <h2>Oggi, <?= e(dt($insights['today'] . ' 12:00:00', (string) $business['timezone'], 'EEEE d MMMM')) ?></h2>
    <?php if ((int) $insights['deposit_today'] > 0): ?>
      <p class="callout"><?= (int) $insights['deposit_today'] ?> prenotazioni di oggi hanno una presenza sotto la soglia. Vale la pena chiedere una caparra in sede.</p>
    <?php endif; ?>
    <div class="list">
      <?php if ($rows === []): ?><p class="empty">Nessun appuntamento in giornata.</p><?php endif; ?>
      <?php foreach ($rows as $row): ?>
        <a href="<?= e(url('/app/appuntamenti/' . ((int) $row['id']))) ?>">
          <time><?= e(dt((string) $row['starts_at'], (string) $business['timezone'], 'HH:mm')) ?></time>
          <span><strong><?= e($row['client_name']) ?></strong><br><span class="muted"><?= e($row['service_name']) ?> · <?= e($row['staff_name']) ?></span></span>
          <span>
            <?php if ((int) $row['deposit_advised'] === 1): ?><span class="badge fragment">Caparra</span><?php endif; ?>
            <span class="badge <?= e($row['status']) ?>"><?= e(appointment_status_label((string) $row['status'])) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="panel">
    <h2>Riempimento</h2>
    <div class="bars">
      <?php foreach ($insights['occupancy'] as $lane): ?>
        <div>
          <span><?= e($lane['name']) ?> · <?= (int) $lane['ratio'] ?>%</span>
          <div class="bar"><span style="width: <?= (int) $lane['ratio'] ?>%; background: <?= e(hex_color($lane['color'])) ?>"></span></div>
        </div>
      <?php endforeach; ?>
      <?php if ($insights['occupancy'] === []): ?><p class="empty">Aggiungi almeno una persona.</p><?php endif; ?>
    </div>
    <p class="muted">Assenze negli ultimi 30 giorni: <?= $insights['noshow_percent'] === null ? 'ancora pochi dati' : (int) $insights['noshow_percent'] . '%' ?>.</p>
    <h3>Prossimi</h3>
    <div class="list">
      <?php foreach ($insights['next'] as $row): ?>
        <a href="<?= e(url('/app/appuntamenti/' . ((int) $row['id']))) ?>">
          <time><?= e(dt((string) $row['starts_at'], (string) $business['timezone'], 'd MMM HH:mm')) ?></time>
          <span><?= e($row['client_name']) ?> · <?= e($row['service_name']) ?></span>
          <span><?= e(euro((int) $row['price_cents'])) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <p><a class="btn small" href="<?= e(url('/app/giornata')) ?>">Apri la bacheca</a></p>
  </section>
</div>
