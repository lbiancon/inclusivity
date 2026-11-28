<?php

use ItPortal\View;

/** @var array $user */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Quality</p>
        <h1>Checklist</h1>
        <p class="lede">Config + login operatore + save risposte (legacy <code>get_check_*</code> / <code>insert_login_check</code> / <code>save_check</code>). Operatore portale: <strong><?= View::e(trim($user['cognome'] . ' ' . $user['nome'])) ?></strong></p>
    </div>
</section>

<section class="panel">
    <div class="track-form__row">
        <button type="button" class="btn btn--primary" id="btnLoadLines">Load lines</button>
        <span class="muted">Mock senza MySQL checkdb · in stabilimento: <code>MYSQL_CHECK_*</code> + Oracle badge</span>
    </div>
    <div id="checklistLines" class="track-results" style="margin-top:1rem"></div>

    <div id="checklistWorkbench" class="track-results" style="margin-top:1.2rem" hidden>
        <div class="track-form__row">
            <label class="field">
                <span>Line</span>
                <input type="text" id="wbLine" readonly>
            </label>
            <label class="field">
                <span>Rev</span>
                <input type="text" id="wbRev" readonly>
            </label>
            <label class="field">
                <span>Badge operatore</span>
                <input type="text" id="wbBadge" placeholder="badge / user_id">
            </label>
            <button type="button" class="btn btn--primary" id="btnOpLogin">Login op</button>
            <button type="button" class="btn btn--secondary" id="btnOpLogout">Logout op</button>
        </div>
        <p id="wbOpStatus" class="muted"></p>
        <div id="checklistConfig"></div>
        <p id="wbSaveStatus" class="muted"></p>
    </div>
</section>
