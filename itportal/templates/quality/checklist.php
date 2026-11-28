<?php

use ItPortal\View;

/** @var array $user */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Quality</p>
        <h1>Checklist</h1>
        <p class="lede">Config linee da <code>lot_check_config</code> (legacy <code>get_check_list_config</code>). Operatore: <strong><?= View::e(trim($user['cognome'] . ' ' . $user['nome'])) ?></strong></p>
    </div>
</section>

<section class="panel">
    <div class="track-form__row">
        <button type="button" class="btn btn--primary" id="btnLoadLines">Load lines</button>
        <span class="muted">Mock senza MySQL checkdb · in stabilimento: <code>MYSQL_CHECK_*</code></span>
    </div>
    <div id="checklistLines" class="track-results" style="margin-top:1rem"></div>
    <div id="checklistConfig" class="track-results" style="margin-top:1rem" hidden></div>
</section>
