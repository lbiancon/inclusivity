<?php

use ItPortal\View;

/** @var string|null $error */
/** @var string $errorType */
?>
<section class="auth-shell">
    <div class="auth-card">
        <div class="auth-card__brand">
            <span class="brand__mark">JSS</span>
            <h1>Sign in</h1>
            <p>Badge magnetico, codice operatore o account LDAP aziendale.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert--<?= View::e($errorType ?: 'danger') ?>"><?= View::e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/login.php" class="auth-form" id="formLogin" autocomplete="off">
            <label class="field">
                <span>LDAP user <em>(opzionale)</em></span>
                <input type="text" name="user" id="user" placeholder="nome.cognome" autocapitalize="off">
            </label>
            <label class="field">
                <span>Badge / password</span>
                <input type="password" name="pass" id="pass" required autofocus placeholder="codice badge">
            </label>
            <input type="hidden" name="login" value="1">
            <button class="btn btn--primary btn--block" type="submit" id="btnlogin">Sign in</button>
        </form>

        <div class="auth-meta">
            <p>Swipe card abilitato su questa pagina (stesso protocollo Articona / `ò…_`).</p>
            <p class="muted">Demo locale senza Oracle: usa i badge mock dalla home.</p>
        </div>
    </div>
</section>
