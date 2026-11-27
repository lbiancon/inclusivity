<?php

use ItPortal\View;

/** @var array $user */
/** @var bool $canPickServer */
/** @var array|null $result */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Tracking</p>
        <h1>Serial Track</h1>
        <p class="lede">Cerca la genealogia pezzo / processo. <?= $user['nome'] !== '' ? 'Operatore: <strong>' . View::e(trim($user['cognome'] . ' ' . $user['nome'])) . '</strong>' : 'Accesso pubblico in sola lettura (come legacy serial.php).' ?></p>
    </div>
</section>

<section class="panel">
    <form id="formData" class="track-form" method="post" action="/api/tracking/serial.php">
        <div class="track-form__row">
            <label class="field field--grow">
                <span>Serial</span>
                <input type="text" name="part_sn" id="part_sn" placeholder="Scrivi o scansiona seriale" value="<?= View::e($_GET['firstSerial'] ?? '') ?>" autofocus required>
            </label>
            <label class="check">
                <input type="checkbox" name="searchplant" id="searchplant" value="y">
                <span>Parent part SN</span>
            </label>
            <?php if ($canPickServer): ?>
                <label class="field">
                    <span>Server</span>
                    <select name="server" id="server">
                        <option value="CFP1" selected>CFP1</option>
                        <option>NODO1</option>
                        <option>NODO2</option>
                        <option>NODO3</option>
                        <option>BREED</option>
                        <option>NODO0_DB_KSS_ONLINE</option>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="server" value="CFP1">
            <?php endif; ?>
            <button class="btn btn--primary" type="submit" id="searchbtn">Search</button>
        </div>
    </form>

    <div class="preloader" id="preloader" hidden>
        <div class="spinner"></div>
        <span>Interrogazione tracciabilità…</span>
    </div>

    <div id="wrapper" class="track-results" <?= empty($result) ? 'hidden' : '' ?>>
        <?php if (!empty($result)): ?>
            <?php require __DIR__ . '/_serial_result.php'; ?>
        <?php endif; ?>
    </div>
</section>
