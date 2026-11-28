<?php

use ItPortal\View;

/** @var array $user */
$today = date('d/m/Y');
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Engineering</p>
        <h1>Report Scrap Line</h1>
        <p class="lede">Aggregato <code>DASHBOARD</code> (core di <code>SELECT_REPORT_LINE_SCRAP</code>). Operatore: <strong><?= View::e(trim($user['cognome'] . ' ' . $user['nome'])) ?></strong></p>
    </div>
</section>

<section class="panel">
    <form id="formScrap" class="track-form" method="post" action="/api/engineering/scrap.php">
        <div class="track-form__row">
            <label class="field">
                <span>Line ID</span>
                <input type="text" name="line_id" value="AB1" required>
            </label>
            <label class="field">
                <span>WC line</span>
                <input type="text" name="wc_line" value="%">
            </label>
            <label class="field">
                <span>Start</span>
                <input type="text" name="start_ts" value="<?= View::e($today) ?> 06:00:00" required>
            </label>
            <label class="field">
                <span>End</span>
                <input type="text" name="end_ts" value="<?= View::e($today) ?> 22:00:00" required>
            </label>
            <button class="btn btn--primary" type="submit">Run</button>
        </div>
    </form>
    <div id="scrapResults" class="track-results" hidden></div>
</section>
