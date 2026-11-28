<?php

use ItPortal\View;

/** @var array $user */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Dashboard</p>
        <h1>Dashboard Line</h1>
        <p class="lede">Core di <code>SELECT_DASHBOARD_LINE</code> (<code>functionDashboard.php</code>). Operatore: <strong><?= View::e(trim($user['cognome'] . ' ' . $user['nome'])) ?></strong></p>
    </div>
</section>

<section class="panel">
    <form id="formDashLine" class="track-form" method="post" action="/api/dashboard/line.php">
        <div class="track-form__row">
            <label class="field">
                <span>Dashboard ID</span>
                <input type="text" name="dash_id" value="AB1" required>
            </label>
            <label class="field">
                <span>WC line</span>
                <input type="text" name="wc_line" value="%">
            </label>
            <button class="btn btn--primary" type="submit">Load</button>
        </div>
    </form>
    <div id="dashLineResults" class="track-results" hidden></div>
</section>
