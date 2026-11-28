<?php

use ItPortal\View;

/** @var array $user */
/** @var string $plant */
$plant = $plant ?? 'IF';
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Production</p>
        <h1>Manpower <?= View::e($plant) ?></h1>
        <p class="lede">Core di <code>SELECT_TABLE_MP</code> (<code>functionTools.php</code>). Operatore: <strong><?= View::e(trim($user['cognome'] . ' ' . $user['nome'])) ?></strong></p>
    </div>
</section>

<section class="panel">
    <form id="formMp" class="track-form" method="post" action="/api/production/mp.php">
        <input type="hidden" name="plant" value="<?= View::e($plant) ?>">
        <div class="track-form__row">
            <button class="btn btn--primary" type="submit">Load roster</button>
        </div>
    </form>
    <div id="mpResults" class="track-results" hidden></div>
</section>
