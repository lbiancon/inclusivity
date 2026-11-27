<?php

use ItPortal\View;

/** @var array $result */
?>
<?php if (!$result['ok']): ?>
    <div class="alert alert--danger"><?= View::e($result['message']) ?></div>
<?php else: ?>
    <div class="result-meta">
        <div><span>Serial</span><strong><?= View::e($result['serial']) ?></strong></div>
        <div><span>Part</span><strong><?= View::e($result['meta']['part'] ?? '—') ?></strong></div>
        <div><span>Parent</span><strong><?= View::e($result['meta']['parent_part_sn'] ?? '—') ?></strong></div>
        <div><span>Server</span><strong><?= View::e($result['meta']['server'] ?? 'CFP1') ?></strong></div>
        <div><span>Mode</span><strong><?= View::e($result['meta']['mode'] ?? 'live') ?></strong></div>
    </div>
    <p class="muted"><?= View::e($result['message']) ?></p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Station</th>
                <th>Op</th>
                <th>Result</th>
                <th>Part</th>
                <th>Timestamp</th>
                <th>Operator</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($result['rows'] as $row): ?>
                <tr>
                    <td><?= View::e($row['station']) ?></td>
                    <td><?= View::e($row['op']) ?></td>
                    <td><span class="pill pill--ok"><?= View::e($row['result']) ?></span></td>
                    <td><?= View::e($row['part']) ?></td>
                    <td><?= View::e($row['timestamp']) ?></td>
                    <td><?= View::e($row['operator']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
