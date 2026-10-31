<?php
$map = [];
foreach ($hours as $row) {
    $map[(int) $row['weekday']][] = $row;
}
$slice = static function (?array $row, string $key): string {
    if ($row === null) {
        return '';
    }

    return substr((string) $row[$key], 0, 5);
};
?>
<div class="list">
  <?php for ($day = 1; $day <= 7; $day++): ?>
    <?php
      $shifts = array_values($map[$day] ?? []);
      $first = $shifts[0] ?? null;
      $second = $shifts[1] ?? null;
    ?>
    <div class="split">
      <p><strong><?= e(weekday_name($day)) ?></strong></p>
      <div class="split">
        <label class="field">Prima fascia
          <span class="split">
            <input type="time" name="<?= $day ?>_a_start" value="<?= e($slice($first, 'start_time')) ?>">
            <input type="time" name="<?= $day ?>_a_end" value="<?= e($slice($first, 'end_time')) ?>">
          </span>
        </label>
        <label class="field">Seconda fascia
          <span class="split">
            <input type="time" name="<?= $day ?>_b_start" value="<?= e($slice($second, 'start_time')) ?>">
            <input type="time" name="<?= $day ?>_b_end" value="<?= e($slice($second, 'end_time')) ?>">
          </span>
        </label>
      </div>
    </div>
  <?php endfor; ?>
</div>
<p class="muted">Lascia vuota una fascia per non usarla. Gli orari sono quelli del fuso dell'attività, Europe/Roma se non l'hai cambiato.</p>
