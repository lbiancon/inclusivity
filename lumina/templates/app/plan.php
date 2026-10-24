<p class="muted">Piano attuale: <strong><?= e($offers[\Lumina\Plan::id($business)]['name']) ?></strong>, <?= ($business['plan_interval'] ?? 'month') === 'year' ? 'annuale' : 'mensile' ?>. Cambiarlo non addebita nulla.</p>
<form method="post" action="<?= e(url('/app/piano')) ?>" class="plan-grid">
  <?= csrf_field() ?>
  <?= redirect_field('/app/piano') ?>
  <?php foreach ($offers as $id => $offer): ?>
    <label class="plan-sheet<?= \Lumina\Plan::id($business) === $id ? ' featured' : '' ?>">
      <input type="radio" name="plan" value="<?= e($id) ?>"<?= \Lumina\Plan::id($business) === $id ? ' checked' : '' ?>>
      <h2><?= e($offer['name']) ?></h2>
      <p class="plan-price"><?= (int) $offer['month'] === 0 ? '0 €' : e(euro((int) $offer['month'])) ?></p>
      <p class="muted"><?= (int) $offer['month'] === 0 ? 'Nessun canone' : euro((int) $offer['year']) . ' l\'anno' ?></p>
      <p><?= e($offer['line']) ?></p>
    </label>
  <?php endforeach; ?>
  <label class="field">Intervallo, per quando il pagamento sarà collegato
    <select name="plan_interval">
      <option value="month"<?= ($business['plan_interval'] ?? 'month') === 'month' ? ' selected' : '' ?>>Ogni mese</option>
      <option value="year"<?= ($business['plan_interval'] ?? '') === 'year' ? ' selected' : '' ?>>Ogni anno</option>
    </select>
  </label>
  <button class="btn" type="submit">Attiva questo piano</button>
</form>
<p class="muted"><a href="<?= e(url('/prezzi')) ?>">Vedi il confronto completo</a></p>
