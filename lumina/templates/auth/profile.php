<section class="gate profile">
  <div class="gate-copy">
    <p class="kicker">Email confermata</p>
    <h1>Come vuoi usare <?= e($email) ?>?</h1>
    <p class="lede">Il piano si attiva subito. Lumina non addebita la carta: i limiti però sono quelli del piano che scegli. I prezzi, IVA esclusa, sono quelli della pagina piani.</p>
  </div>
  <form class="gate-panel card" method="post" action="<?= e(url('/registrati/profilo')) ?>">
    <?= csrf_field() ?>
    <?= redirect_field('/registrati/profilo') ?>
    <div class="switch">
      <label><input type="radio" name="account" value="business" checked data-account> Apro un'attività</label>
      <label><input type="radio" name="account" value="client" data-account> Prenoto, ho già un'email in agenda</label>
    </div>
    <label class="field">Il tuo nome <input name="name" required value="<?= e(field('name', $name)) ?>"></label>
    <label class="field">Telefono <input name="phone" value="<?= e(field('phone')) ?>"></label>
    <div data-business>
      <label class="field">Nome attività <input name="business_name" value="<?= e(field('business_name')) ?>"></label>
      <div class="split">
        <label class="field">Città <input name="city" value="<?= e(field('city', 'Carpineto Romano')) ?>"></label>
        <label class="field">Tipo
          <select name="category">
            <?php foreach ($categories as $category): ?>
              <option value="<?= e($category->value) ?>"<?= selected('category', $category->value, 'hair') ?>><?= e($category->label()) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <fieldset class="plans">
        <legend>Piano</legend>
        <?php foreach ($offers as $id => $offer): ?>
          <label class="plan<?= $id === 'salone' ? ' featured' : '' ?>">
            <input type="radio" name="plan" value="<?= e($id) ?>"<?= $id === 'salone' ? ' checked' : '' ?>>
            <span class="plan-name"><?= e($offer['name']) ?></span>
            <span class="plan-price"><?= (int) $offer['month'] === 0 ? '0 €' : e(euro((int) $offer['month'])) ?></span>
            <span class="muted"><?= (int) $offer['month'] === 0 ? 'per sempre' : 'al mese, oppure ' . euro((int) $offer['year']) . ' l\'anno' ?></span>
            <span><?= e($offer['line']) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <label class="field">Pagamento, quando sarà collegato
        <select name="plan_interval">
          <option value="month">Ogni mese</option>
          <option value="year">Ogni anno, due mesi in omaggio</option>
        </select>
      </label>
      <p class="muted">Dal lunedì al venerdì prepariamo già 9–13 e 15–19.</p>
    </div>
    <button class="btn block" type="submit">Crea l'accesso</button>
  </form>
</section>
