<?php
$tz = (string) $business['timezone'];
$prev = (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
$next = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
?>
<div class="actions noprint">
  <a class="btn small ghost" href="<?= e(url('/app/giornata?data=' . ($prev))) ?>">Ieri</a>
  <strong><?= e(dt($date . ' 12:00:00', $tz, 'EEEE d MMMM')) ?></strong>
  <a class="btn small ghost" href="<?= e(url('/app/giornata?data=' . ($next))) ?>">Domani</a>
</div>
<?php if ($frame['closed']): ?>
  <p class="empty">Giorno chiuso: non ci sono turni dell'attività.</p>
<?php else: ?>
  <?php
    $windows = [];
    $cursor = (int) $frame['open_min'];
    foreach ($frame['breaks'] as $break) {
        if ((int) $break['start_min'] > $cursor) {
            $windows[] = [$cursor, (int) $break['start_min']];
        }
        $cursor = (int) $break['end_min'];
    }
    if ((int) $frame['close_min'] > $cursor) {
        $windows[] = [$cursor, (int) $frame['close_min']];
    }
  ?>
  <div class="board">
    <?php foreach ($windows as [$winStart, $winEnd]): ?>
      <?php $winSpan = max(1, $winEnd - $winStart); ?>
      <p class="window-label"><?= e(sprintf('%02d:%02d – %02d:%02d', intdiv($winStart, 60), $winStart % 60, intdiv($winEnd, 60), $winEnd % 60)) ?></p>
      <div class="scale"><span></span><div class="track">
        <?php for ($minute = $winStart; $minute <= $winEnd; $minute += 60): ?>
          <span class="tick" style="left: <?= (($minute - $winStart) / $winSpan) * 100 ?>%"><?= e(sprintf('%02d', intdiv($minute, 60))) ?></span>
        <?php endfor; ?>
      </div></div>
      <?php foreach ($lanes as $lane): ?>
        <div class="lane">
          <strong><i class="dot" style="background: <?= e(hex_color($lane['staff']['color'])) ?>"></i> <?= e($lane['staff']['name']) ?></strong>
          <div class="track">
            <?php if ($nowMinute !== null && $nowMinute >= $winStart && $nowMinute <= $winEnd): ?>
              <span class="nowline" style="left: <?= (($nowMinute - $winStart) / $winSpan) * 100 ?>%"></span>
            <?php endif; ?>
            <?php foreach ($lane['blocks'] as $block): ?>
              <?php
                $leftMin = max($winStart, (int) $block['start_min']);
                $rightMin = min($winEnd, (int) $block['end_min']);
                if ($rightMin <= $leftMin) {
                    continue;
                }
                $title = $block['time'] . ' ' . $block['who'] . ' · ' . $block['what'] . ($block['allergy'] ? ' · allergia' : '');
              ?>
              <a class="block status-<?= e($block['status']) ?>" href="<?= e(url(($block['href']))) ?>" title="<?= e($title) ?>" style="left: <?= (($leftMin - $winStart) / $winSpan) * 100 ?>%; width: <?= (($rightMin - $leftMin) / $winSpan) * 100 ?>%; background: <?= e(hex_color($lane['staff']['color'])) ?>22">
                <strong><?= e($block['time']) ?> <?= e($block['who']) ?></strong>
                <small><?= e($block['what']) ?><?php if ($block['allergy']): ?> · <span class="allergy">allergia</span><?php endif; ?></small>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="panel noprint">
  <h2>Metti in agenda</h2>
  <form method="get" action="<?= e(url('/app/giornata')) ?>" class="split">
    <input type="hidden" name="data" value="<?= e($date) ?>">
    <label class="field">Servizio
      <select name="servizio" data-submit>
        <option value="0">Scegli</option>
        <?php foreach ($services as $service): ?>
          <option value="<?= (int) $service['id'] ?>"<?= $serviceId === (int) $service['id'] ? ' selected' : '' ?>><?= e($service['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">Persona
      <select name="staff" data-submit>
        <option value="0">Chi è libero</option>
        <?php foreach ($staff as $person): ?>
          <option value="<?= (int) $person['id'] ?>"<?= $staffId === (int) $person['id'] ? ' selected' : '' ?>><?= e($person['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>
  <?php if ($serviceId > 0): ?>
    <form method="post" action="<?= e(url('/app/appuntamenti')) ?>">
      <?= csrf_field() ?>
      <?= redirect_field('/app/giornata?data=' . $date . '&servizio=' . $serviceId . '&staff=' . $staffId) ?>
      <input type="hidden" name="service_id" value="<?= $serviceId ?>">
      <div class="slots">
        <?php if ($slots === []): ?><p class="empty">Nessuno slot in questo giorno.</p><?php endif; ?>
        <?php foreach ($slots as $slot): ?>
          <?php
            $start = $slot['starts_at']->setTimezone(new DateTimeZone($tz));
            $token = (int) $slot['staff_id'] . '|' . (int) ($slot['resource_id'] ?? 0) . '|' . $slot['starts_at']->getTimestamp();
          ?>
          <label class="slot <?= e($slot['quality']) ?>" data-label="<?= e($start->format('H:i') . ' ' . $slot['staff_name']) ?>">
            <span><input type="radio" name="slot" value="<?= e($token) ?>" required> <strong><?= e($start->format('H:i')) ?></strong> <?= e(quality_label((string) $slot['quality'])) ?></span>
            <span><?= e($slot['staff_name']) ?><?php if ($slot['resource_name']): ?> · <?= e($slot['resource_name']) ?><?php endif; ?></span>
            <span><?php if ((int) $slot['price_cents'] !== (int) $slot['list_price_cents']): ?><s><?= e(euro((int) $slot['list_price_cents'])) ?></s> <?php endif; ?><?= e(euro((int) $slot['price_cents'])) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="split">
        <label class="field">Cliente già in scheda
          <select name="client_id">
            <option value="0">Nuovo cliente</option>
            <?php foreach ($clients as $client): ?>
              <option value="<?= (int) $client['id'] ?>"><?= e($client['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">Nome, se è nuovo <input name="client_name" value="<?= e(field('client_name')) ?>"></label>
        <label class="field">Email <input type="email" name="client_email" value="<?= e(field('client_email')) ?>"></label>
        <label class="field">Telefono <input name="client_phone" value="<?= e(field('client_phone')) ?>"></label>
        <label class="field">Ripeti ogni settimana
          <select name="occurrences">
            <?php for ($n = 1; $n <= 12; $n++): ?>
              <option value="<?= $n ?>"><?= $n === 1 ? 'Solo questa' : $n . ' settimane' ?></option>
            <?php endfor; ?>
          </select>
        </label>
        <label class="field">Note <input name="notes" value="<?= e(field('notes')) ?>"></label>
      </div>
      <label class="checks"><input type="checkbox" name="use_package" value="1"> Scala un credito della tessera</label>
      <p class="muted">La serie salta le settimane già occupate. Il prezzo vivo e la caparra si calcolano su ogni occorrenza.</p>
      <p data-slot-summary class="notice">Scegli l'orario.</p>
      <button class="btn" type="submit">Crea la prenotazione</button>
    </form>
  <?php endif; ?>
</section>
