<?php
$tz = (string) $business['timezone'];
$status = (string) $appointment['status'];
$open = !in_array($status, ['cancelled', 'completed', 'no_show'], true);
?>
<div class="grid two">
  <article class="panel">
    <p>
      <span class="badge <?= e($appointment['slot_quality']) ?>"><?= e(quality_label((string) $appointment['slot_quality'])) ?></span>
      <span class="badge <?= e($status) ?>"><?= e(appointment_status_label($status)) ?></span>
      <?php if ((int) $appointment['deposit_advised'] === 1): ?><span class="badge fragment">Caparra consigliata</span><?php endif; ?>
    </p>
    <p><?= e(dt((string) $appointment['starts_at'], $tz)) ?> – <?= e(dt((string) $appointment['ends_at'], $tz, 'HH:mm')) ?></p>
    <p><?= e($appointment['service_name']) ?> · <?= (int) $appointment['duration_min'] ?> min · <i class="dot" style="background: <?= e(hex_color($appointment['color'])) ?>"></i> <?= e($appointment['staff_name']) ?></p>
    <?php if ($appointment['resource_name']): ?><p>Postazione: <?= e($appointment['resource_name']) ?></p><?php endif; ?>
    <p class="price">
      <?php if ((int) $appointment['price_cents'] !== (int) $appointment['list_price_cents']): ?><s><?= e(euro((int) $appointment['list_price_cents'])) ?></s> <?php endif; ?>
      <strong><?= e(euro((int) $appointment['price_cents'])) ?></strong>
      <?php if ($appointment['price_label'] !== ''): ?><span class="badge"><?= e($appointment['price_label']) ?></span><?php endif; ?>
    </p>
    <?php if ($appointment['notes'] !== ''): ?><p><?= e($appointment['notes']) ?></p><?php endif; ?>
    <?php if ($appointment['cancel_reason'] !== ''): ?><p class="muted">Annullamento: <?= e($appointment['cancel_reason']) ?></p><?php endif; ?>
    <p><a href="<?= e(url('/app/clienti/' . ((int) $appointment['client_id']))) ?>"><?= e($appointment['client_name']) ?></a> · <?= e($appointment['phone']) ?> · <?= e($appointment['email'] ?? '') ?></p>
    <?php if ($appointment['allergies'] !== ''): ?><p class="callout">Allergie: <?= e($appointment['allergies']) ?></p><?php endif; ?>
    <?php if ($appointment['preferences'] !== ''): ?><p>Preferenze: <?= e($appointment['preferences']) ?></p><?php endif; ?>
    <p class="muted">Presenze registrate: <?= (int) $score['kept'] ?> tenute, <?= (int) $score['missed'] ?> assenze. <?= $score['percent'] === null ? 'Servono almeno due esiti per un giudizio.' : 'Affidabilità ' . (int) $score['percent'] . '%.' ?></p>
  </article>
  <div class="noprint">
    <?php if ($open): ?>
      <form class="card" method="post" action="<?= e(url('/app/appuntamenti/' . ((int) $appointment['id']) . '/stato')) ?>">
        <?= csrf_field() ?>
        <h2>Stato</h2>
        <div class="actions">
          <?php foreach (['arrived' => 'Arrivato', 'in_progress' => 'In corso', 'completed' => 'Completato', 'no_show' => 'Assente'] as $value => $label): ?>
            <button class="btn small ghost" name="status" value="<?= e($value) ?>" type="submit"><?= e($label) ?></button>
          <?php endforeach; ?>
        </div>
        <p class="muted">L'assenza non restituisce il credito. Puoi farlo dopo, se è il caso.</p>
      </form>
      <form class="card" method="post" action="<?= e(url('/app/appuntamenti/' . ((int) $appointment['id']) . '/annulla')) ?>" data-confirm="Annullare e offrire il posto a chi è in lista?">
        <?= csrf_field() ?>
        <label class="field">Motivo <input name="reason" maxlength="180"></label>
        <button class="btn danger" type="submit">Annulla e libera il posto</button>
      </form>
    <?php endif; ?>
    <?php if ($appointment['client_package_id'] !== null): ?>
      <form class="card" method="post" action="<?= e(url('/app/appuntamenti/' . ((int) $appointment['id']) . '/rimborsa')) ?>" data-confirm="Restituire il credito?">
        <?= csrf_field() ?>
        <p>Questa prenotazione ha scalato una tessera.</p>
        <button class="btn ghost" type="submit">Restituisci il credito</button>
      </form>
    <?php endif; ?>
  </div>
</div>
