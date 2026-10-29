<section class="gate">
  <div class="gate-copy">
    <p class="kicker"><?= $mode === 'login' ? 'Bentornato' : 'Registrazione' ?></p>
    <h1><?= $mode === 'login' ? 'Entra con un codice, oppure con Google o Apple.' : 'Apri l\'agenda. La password può aspettare.' ?></h1>
    <p class="lede">Il codice dura dieci minuti, si usa una volta e muore dopo cinque errori. Google e Apple entrano solo se loro hanno già verificato l\'email. Un indirizzo non confermato non viene agganciato a un account esistente.</p>
    <ol class="steps">
      <li><strong>1</strong> Scegli come entrare.</li>
      <li><strong>2</strong> Leggi il codice nella posta, se passi dall\'email.</li>
      <li><strong>3</strong> Se sei nuovo, dai un nome all\'attività e scegli il piano.</li>
    </ol>
  </div>
  <div class="gate-panel card">
    <a class="provider" href="<?= e(url('/auth/google')) ?>">
      <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.2 35.1 26.7 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.2-3.5 5.7-6.6 7.1l6.3 5.3C37.4 38.4 44 33 44 24c0-1.2-.1-2.3-.4-3.5z"/></svg>
      Continua con Google
    </a>
    <a class="provider apple" href="<?= e(url('/auth/apple')) ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.2-2.8.9-3.5.9s-1.8-.8-3-.8c-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.3 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7 2-.1 2.9-2.3c.7-1 1.2-2.1 1.5-3.2-3.9-1.5-3.8-5.4-2.8-6.2zM14.7 6.5c.6-.8 1.1-1.9.9-3-1 .1-2.1.6-2.8 1.4-.6.7-1.2 1.8-.9 2.9 1.1.1 2.1-.5 2.8-1.3z"/></svg>
      Continua con Apple
    </a>
    <?php if (!$google || !$apple): ?>
      <p class="muted">Google <?= $google ? 'è pronto' : 'aspetta le chiavi' ?>. Apple <?= $apple ? 'è pronto' : 'aspetta le chiavi' ?>. Senza chiavi il pulsante spiega cosa manca, e non fa entrare nessuno.</p>
    <?php endif; ?>
    <p class="or">oppure l'email</p>
    <form method="post" action="<?= e(url('/registrati/codice/invia')) ?>">
      <?= csrf_field() ?>
      <?= redirect_field($mode === 'login' ? '/accedi' : '/registrati') ?>
      <label class="field">Email
        <input type="email" name="email" required autocomplete="username" value="<?= e(field('email')) ?>" placeholder="tu@salone.it">
      </label>
      <div class="honeypot" aria-hidden="true"><label>Azienda <input name="company" tabindex="-1" autocomplete="off"></label></div>
      <button class="btn block" type="submit">Inviami il codice</button>
    </form>
    <?php if ($mode === 'login'): ?>
      <details class="password-fold">
        <summary>Ho già una password</summary>
        <form method="post" action="<?= e(url('/accedi')) ?>">
          <?= csrf_field() ?>
          <?= redirect_field('/app') ?>
          <label class="field">Email <input type="email" name="email" required autocomplete="username" value="<?= e(field('email')) ?>"></label>
          <label class="field">Password <input type="password" name="password" required autocomplete="current-password"></label>
          <button class="btn ghost block" type="submit">Entra</button>
        </form>
      </details>
    <?php else: ?>
      <p class="muted"><a href="<?= e(url('/accedi')) ?>">Ho già la password della demo</a></p>
    <?php endif; ?>
    <?php if (!empty($demo)): ?>
      <div class="demo-note">
        <p class="kicker">Demo</p>
        <p>Password <strong>Lumina-demo-2026</strong></p>
        <p>giulia@atelierluce.test · marco@atelierluce.test · elena@palestraborgo.test · bianca@email.test</p>
      </div>
    <?php endif; ?>
  </div>
</section>
