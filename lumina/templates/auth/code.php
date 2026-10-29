<section class="gate">
  <div class="gate-copy">
    <p class="kicker">Codice</p>
    <h1>Sei cifre, poi sei dentro.</h1>
    <p class="lede">Il codice è stato chiesto per <strong><?= e($email) ?></strong>. Dura dieci minuti. Un codice nuovo spegne quello di prima.</p>
  </div>
  <div class="gate-panel card">
    <form method="post" action="<?= e(url('/registrati/codice')) ?>" id="codice">
      <?= csrf_field() ?>
      <?= redirect_field('/registrati/codice') ?>
      <div class="digits" data-digits>
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input class="digit" name="d<?= $i ?>" inputmode="numeric" autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>" maxlength="1" pattern="[0-9]" aria-label="Cifra <?= $i + 1 ?>" required>
        <?php endfor; ?>
      </div>
      <input type="hidden" name="code" value="">
      <button class="btn block" type="submit">Conferma il codice</button>
    </form>
    <form method="post" action="<?= e(url('/registrati/codice/invia')) ?>">
      <?= csrf_field() ?>
      <?= redirect_field('/registrati/codice') ?>
      <input type="hidden" name="email" value="<?= e($email) ?>">
      <button class="btn ghost block" type="submit">Invia un altro codice</button>
    </form>
    <?php if ($localMail && is_array($letter)): ?>
      <article class="letter">
        <p class="kicker">Posta di questa installazione</p>
        <h2><?= e($letter['subject']) ?></h2>
        <p class="muted">Nessun server SMTP è collegato, quindi la lettera resta qui. Con LUMINA_SMTP_HOST parte davvero e questo foglio non mostra il codice.</p>
        <pre><?= e($letter['body']) ?></pre>
      </article>
    <?php elseif (!$localMail): ?>
      <p class="muted">Apri la casella di <?= e($email) ?>. Il codice non compare su questa pagina.</p>
    <?php endif; ?>
  </div>
</section>
