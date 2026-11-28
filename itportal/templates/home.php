<?php

use ItPortal\View;

/** @var bool $loggedIn */
/** @var array $user */
?>
<section class="hero">
    <div class="hero__copy">
        <p class="eyebrow">Joyson Safety Systems · Plant 341</p>
        <h1 class="hero__title">IT Portal</h1>
        <p class="hero__lead">
            Tracciabilità, qualità, produzione e servizi di stabilimento — stesso perimetro funzionale del portale legacy, interfaccia e stack rinnovati.
        </p>
        <div class="hero__actions">
            <a class="btn btn--primary btn--lg" href="/tracking/serial.php">Serial Track</a>
            <?php if (!$loggedIn): ?>
                <a class="btn btn--secondary btn--lg" href="/login.php">Accedi</a>
            <?php else: ?>
                <a class="btn btn--secondary btn--lg" href="/tracking/pallet.php">Pallet Track</a>
            <?php endif; ?>
        </div>
        <?php if ($loggedIn): ?>
            <p class="hero__user">Signed in as <strong><?= View::e($user['login']) ?></strong> · role <?= View::e($user['roles']) ?></p>
        <?php else: ?>
            <p class="hero__hint">Demo badges: <code>demo</code>, <code>12345</code>, <code>71875</code>, <code>guard</code> · LDAP mock: <code>ldap.user</code></p>
        <?php endif; ?>
    </div>
    <div class="hero__panel" aria-hidden="true">
        <div class="hero-grid">
            <div class="hero-tile"><span>TRACK</span><strong>Serial · Pallet</strong></div>
            <div class="hero-tile"><span>QUALITY</span><strong>Checklist · Lab</strong></div>
            <div class="hero-tile"><span>PROD</span><strong>Airbag · Inflator</strong></div>
            <div class="hero-tile"><span>ENG</span><strong>OEE · Scrap</strong></div>
        </div>
    </div>
</section>

<section class="info-strip">
    <article>
        <h2>Compliance</h2>
        <p>Codice etico, data integrity e policy compliance restano raggiungibili dal modulo HR/documenti (migrazione in corso).</p>
    </article>
    <article>
        <h2>IT Service</h2>
        <p>Richieste supporto tramite ServiceNow aziendale. Downtime IT e action items nel menu I.T. (dopo login SU/IT).</p>
    </article>
    <article>
        <h2>Utility</h2>
        <p>Contatti JSS, prenotazione pasto e DMS documenti: collegati come nel portale precedente.</p>
    </article>
</section>
