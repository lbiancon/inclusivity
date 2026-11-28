<?php

use ItPortal\View;

/** @var array $user */
/** @var bool $canPickServer */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Tracking</p>
        <h1>Pallet / Cont Track</h1>
        <p class="lede">Operatore: <strong><?= View::e(trim($user['cognome'] . ' ' . $user['nome'])) ?></strong> · ruolo <?= View::e($user['roles']) ?></p>
    </div>
</section>

<section class="panel">
    <form id="formPallet" class="track-form" method="post" action="/api/tracking/pallet.php">
        <div class="track-form__row">
            <label class="field field--grow">
                <span>Pallet / Container</span>
                <input type="text" name="pallet_sn" id="pallet_sn" placeholder="Scrivi pallet o cont" autofocus required>
            </label>
            <?php if ($canPickServer): ?>
                <label class="field">
                    <span>Server</span>
                    <select name="server">
                        <option value="CFP1" selected>CFP1</option>
                        <option>NODO1</option>
                        <option>NODO2</option>
                        <option>NODO3</option>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="server" value="CFP1">
            <?php endif; ?>
            <button class="btn btn--primary" type="submit">Search</button>
        </div>
    </form>
    <div id="palletResults" class="track-results" hidden></div>
</section>
