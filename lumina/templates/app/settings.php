<div class="grid two">
  <form class="card" method="post" action="<?= e(url('/app/impostazioni')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?= redirect_field('/app/impostazioni') ?>
    <h2>Scheda pubblica</h2>
    <div class="brand-row">
      <?php if (($business['logo_ext'] ?? '') !== ''): ?>
        <img class="shop-logo" src="<?= e(url('/b/' . $business['slug'] . '/marchio')) ?>" alt="">
      <?php else: ?>
        <span class="shop-logo letter" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $business['name'], 0, 1))) ?></span>
      <?php endif; ?>
      <div>
        <label class="field">Logo, PNG o JPG fino a 2 MB <input type="file" name="logo" accept="image/png,image/jpeg"></label>
        <?php if (($business['logo_ext'] ?? '') !== ''): ?>
          <label class="checks"><input type="checkbox" name="remove_logo" value="1"> Togli il logo</label>
        <?php endif; ?>
      </div>
    </div>
    <label class="field">Colore del salone <input type="color" name="accent" value="<?= e(hex_color($business['accent'] ?? '')) ?>"></label>
    <label class="field">Nome <input name="name" required value="<?= e($business['name']) ?>"></label>
    <label class="field">Frase <input name="tagline" value="<?= e($business['tagline']) ?>"></label>
    <label class="field">Descrizione <textarea name="description"><?= e($business['description']) ?></textarea></label>
    <label class="field">Indirizzo <input name="address" value="<?= e($business['address']) ?>"></label>
    <div class="split">
      <label class="field">Città <input name="city" value="<?= e($business['city']) ?>"></label>
      <label class="field">Telefono <input name="phone" value="<?= e($business['phone']) ?>"></label>
      <label class="field">Email pubblica <input type="email" name="email" value="<?= e($business['email']) ?>"></label>
      <label class="field">Categoria
        <select name="category">
          <?php foreach ($categories as $category): ?>
            <option value="<?= e($category->value) ?>"<?= $business['category'] === $category->value ? ' selected' : '' ?>><?= e($category->label()) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">Ore per annullare <input name="cancel_hours" value="<?= (int) $business['cancel_hours'] ?>"></label>
      <label class="field">Passo degli slot
        <select name="slot_minutes">
          <?php foreach ([10, 15, 20, 30] as $step): ?>
            <option value="<?= $step ?>"<?= (int) $business['slot_minutes'] === $step ? ' selected' : '' ?>><?= $step ?> minuti</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">Soglia presenza % <input name="reliability_threshold" value="<?= (int) $business['reliability_threshold'] ?>"></label>
    </div>
    <p class="muted">Sotto questa percentuale, dopo almeno due esiti, la prenotazione segnala di chiedere una caparra. Non viene addebitato nulla.</p>
    <h2>Orario dell'attività</h2>
    <?php \Lumina\View::partial('partials/hours', ['hours' => $hours]); ?>
    <button class="btn" type="submit">Salva</button>
  </form>
  <div>
    <form class="card" method="post" action="<?= e(url('/app/chiusure')) ?>">
      <?= csrf_field() ?>
      <?= redirect_field('/app/impostazioni') ?>
      <h2>Chiusura</h2>
      <label class="field">Giorno <input type="date" name="date" required></label>
      <div class="split">
        <label class="field">Dalle <input type="time" name="start_time" required value="09:00"></label>
        <label class="field">Alle <input type="time" name="end_time" required value="13:00"></label>
      </div>
      <label class="field">Chi
        <select name="staff_id">
          <option value="0">Tutta l'attività</option>
          <?php foreach ($staff as $person): ?><option value="<?= (int) $person['id'] ?>"><?= e($person['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field">Motivo <input name="reason" placeholder="Festa, ferie, corso"></label>
      <button class="btn" type="submit">Segna chiuso</button>
    </form>
    <div class="list">
      <?php foreach ($closures as $closure): ?>
        <article class="card">
          <strong><?= e(dt((string) $closure['starts_at'], (string) $business['timezone'])) ?> – <?= e(dt((string) $closure['ends_at'], (string) $business['timezone'], 'HH:mm')) ?></strong>
          <p><?= e($closure['staff_name'] ?? 'Tutta l\'attività') ?> · <?= e($closure['reason']) ?></p>
          <form method="post" action="<?= e(url('/app/chiusure/' . ((int) $closure['id']) . '/elimina')) ?>" data-confirm="Riaprire questa fascia?"><?= csrf_field() ?><button class="btn small ghost">Riapri</button></form>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="muted">Indirizzo pubblico: <?= e(url('/b/' . $business['slug'])) ?>. Il fuso resta <?= e($business['timezone']) ?>: gli orari che scrivi sono quelli che vede il cliente.</p>
  </div>
</div>
