<form class="actions" method="get" action="<?= e(url('/app/clienti')) ?>">
  <label class="field">Cerca <input name="q" value="<?= e($q) ?>" placeholder="Nome, email o telefono"></label>
  <button class="btn small" type="submit">Cerca</button>
  <a class="btn small ghost" href="<?= e(url('/app/clienti')) ?>">Azzera</a>
</form>
<div class="grid two">
  <form class="card" method="post" action="<?= e(url('/app/clienti')) ?>">
    <?= csrf_field() ?>
    <?= redirect_field('/app/clienti') ?>
    <h2>Nuova scheda</h2>
    <label class="field">Nome <input name="name" required value="<?= e(field('name')) ?>"></label>
    <label class="field">Email <input type="email" name="email" value="<?= e(field('email')) ?>"></label>
    <label class="field">Telefono <input name="phone" value="<?= e(field('phone')) ?>"></label>
    <label class="field">Preferenze <input name="preferences" value="<?= e(field('preferences')) ?>"></label>
    <label class="field">Allergie <input name="allergies" value="<?= e(field('allergies')) ?>"></label>
    <label class="field">Note <textarea name="notes"><?= e(field('notes')) ?></textarea></label>
    <button class="btn" type="submit">Crea</button>
  </form>
  <div class="list">
    <?php if ($clients === []): ?><p class="empty">Nessun cliente con questi criteri.</p><?php endif; ?>
    <?php foreach ($clients as $client): ?>
      <a class="card" href="<?= e(url('/app/clienti/' . ((int) $client['id']))) ?>">
        <strong><?= e($client['name']) ?></strong>
        <span class="muted"><?= e($client['email'] ?? 'senza email') ?> · <?= e($client['phone']) ?></span>
        <?php if ($client['allergies'] !== ''): ?><span class="allergy">Allergia in scheda</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
