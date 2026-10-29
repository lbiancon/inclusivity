<?php
$tz = new DateTimeZone((string) $business['timezone']);
$serviceId = $service === null ? 0 : (int) $service['id'];
$accent = hex_color($business['accent'] ?? '');
$keep = static function (array $extra) use ($business, $serviceId, $staffId, $email): string {
    $params = [
        'servizio' => $serviceId > 0 ? $serviceId : null,
        'staff' => $staffId > 0 ? $staffId : null,
        'email' => $email !== '' ? $email : null,
    ];
    foreach ($extra as $key => $value) {
        $params[$key] = $value;
    }
    $params = array_filter($params, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 0);
    $query = http_build_query($params);

    return url('/b/' . $business['slug'] . ($query === '' ? '' : '?' . $query));
};
$anchorDate = DateTimeImmutable::createFromFormat('!Y-m-d', $anchor) ?: new DateTimeImmutable('today');
$clientUser = $user !== null && (string) $user['role'] === 'client';
$ordered = [];
foreach (['ideal', 'adjacent', 'open', 'fragment'] as $quality) {
    foreach ($slots[$quality] ?? [] as $slot) {
        $slot['quality'] = $quality;
        $ordered[] = $slot;
    }
}
$bands = ['Mattina' => [], 'Pomeriggio' => []];
foreach ($ordered as $slot) {
    $hour = (int) $slot['starts_at']->setTimezone($tz)->format('G');
    $bands[$hour < 14 ? 'Mattina' : 'Pomeriggio'][] = $slot;
}
$initial = mb_strtoupper(mb_substr((string) $business['name'], 0, 1));
?>
<section class="section shop" style="--shop: <?= e($accent) ?>">
  <header class="shop-head">
    <?php if (($business['logo_ext'] ?? null) !== null && $business['logo_ext'] !== ''): ?>
      <img class="shop-logo" src="<?= e(url('/b/' . $business['slug'] . '/marchio')) ?>" alt="">
    <?php else: ?>
      <span class="shop-logo letter" aria-hidden="true"><?= e($initial) ?></span>
    <?php endif; ?>
    <div>
      <p class="kicker"><?= e(category_label((string) $business['category'])) ?> · <?= e($business['city']) ?></p>
      <h1><?= e($business['name']) ?></h1>
      <?php if ($business['tagline'] !== ''): ?><p class="lede"><?= e($business['tagline']) ?></p><?php endif; ?>
      <p class="muted">
        <?= e(trim($business['address'] . ' ' . $business['city'])) ?>
        <?php if ($business['phone'] !== ''): ?> · <a href="<?= e(url('tel:' . (preg_replace('/\s+/', '', (string) $business['phone']) ?? ''))) ?>"><?= e($business['phone']) ?></a><?php endif; ?>
      </p>
    </div>
  </header>
  <?php if ($business['description'] !== ''): ?><p><?= e($business['description']) ?></p><?php endif; ?>

  <h2>1. Cosa vuoi fare</h2>
  <div class="choices">
    <?php foreach ($services as $item): ?>
      <a class="choice<?= $serviceId === (int) $item['id'] ? ' on' : '' ?>" href="<?= e(url(($keep(['servizio' => (int) $item['id'], 'staff' => null, 'data' => null, 'da' => null])))) ?>">
        <span>
          <strong><?= e($item['name']) ?></strong>
          <small><?= (int) $item['duration_min'] ?> min<?php if ($item['description'] !== ''): ?> · <?= e($item['description']) ?><?php endif; ?></small>
        </span>
        <b><?= e(euro((int) $item['price_cents'])) ?></b>
      </a>
    <?php endforeach; ?>
    <?php if ($services === []): ?><p class="empty">Nessun servizio prenotabile in questo momento.</p><?php endif; ?>
  </div>

  <?php if ($service !== null): ?>
    <h2>2. Con chi</h2>
    <div class="people">
      <a class="staff-pill<?= $staffId === 0 ? ' on' : '' ?>" href="<?= e(url(($keep(['staff' => null])))) ?>">Il primo libero</a>
      <?php foreach ($eligible as $person): ?>
        <a class="staff-pill<?= $staffId === (int) $person['id'] ? ' on' : '' ?>" href="<?= e(url(($keep(['staff' => (int) $person['id']])))) ?>">
          <i class="dot" style="background: <?= e(hex_color($person['color'])) ?>"></i>
          <?= e($person['name']) ?><?php if ($person['title'] !== ''): ?> <small>· <?= e($person['title']) ?></small><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <h2>3. Quando</h2>
    <div class="daystrip">
      <a class="btn small ghost" href="<?= e(url(($keep(['da' => $anchorDate->modify('-10 days')->format('Y-m-d'), 'data' => $anchorDate->modify('-10 days')->format('Y-m-d')])))) ?>">Indietro</a>
      <div class="days">
        <?php foreach ($dots as $dot): ?>
          <?php $day = DateTimeImmutable::createFromFormat('!Y-m-d', $dot['date']); ?>
          <a class="day<?= $dot['date'] === $date ? ' on' : '' ?>" href="<?= e(url(($keep(['da' => $anchor, 'data' => $dot['date']])))) ?>">
            <span><?= e($day ? mb_substr(weekday_name((int) $day->format('N')), 0, 3) : '') ?></span>
            <strong><?= e($day ? $day->format('j') : '') ?></strong>
            <?php if ((int) $dot['count'] > 0): ?><em><?= (int) $dot['count'] ?></em><?php else: ?><em>chiuso</em><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
      <a class="btn small ghost" href="<?= e(url(($keep(['da' => $anchorDate->modify('+10 days')->format('Y-m-d'), 'data' => $anchorDate->modify('+10 days')->format('Y-m-d')])))) ?>">Avanti</a>
    </div>

    <div class="grid two">
      <div>
        <?php if ($slotCount === 0): ?>
          <p class="empty">Nessun orario in questo giorno. Scegline un altro, oppure lasciati il posto in lista.</p>
        <?php endif; ?>
        <?php foreach ($bands as $heading => $band): ?>
          <?php if ($band === []) continue; ?>
          <h3><?= e($heading) ?></h3>
          <div class="times">
            <?php foreach ($band as $slot): ?>
              <?php
                $start = $slot['starts_at']->setTimezone($tz);
                $token = (int) $slot['staff_id'] . '|' . (int) ($slot['resource_id'] ?? 0) . '|' . $slot['starts_at']->getTimestamp();
                $text = $start->format('H:i') . ' · ' . $slot['staff_name'] . ' · ' . euro((int) $slot['price_cents']);
              ?>
              <label class="slot time <?= e((string) $slot['quality']) ?>" data-label="<?= e($text) ?>">
                <input type="radio" name="slot" value="<?= e($token) ?>" required>
                <strong><?= e($start->format('H:i')) ?></strong>
                <small><?= e(first_name((string) $slot['staff_name'])) ?></small>
                <small class="price">
                  <?php if ((int) $slot['price_cents'] !== (int) $slot['list_price_cents']): ?><s><?= e(euro((int) $slot['list_price_cents'])) ?></s> <?php endif; ?>
                  <?= e(euro((int) $slot['price_cents'])) ?>
                </small>
                <?php if ($slot['quality'] === 'ideal'): ?><em>Consigliato</em><?php elseif ($slot['quality'] === 'fragment'): ?><em>Orario stretto</em><?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <aside>
        <form class="card" id="prenota" method="post" action="<?= e(url('/b/' . $business['slug'])) ?>">
          <?= csrf_field() ?>
          <?= redirect_field('/b/' . $business['slug'] . '?' . http_build_query(['servizio' => $serviceId, 'staff' => $staffId > 0 ? $staffId : null, 'da' => $anchor, 'data' => $date])) ?>
          <input type="hidden" name="service_id" value="<?= $serviceId ?>">
          <input type="hidden" name="slot" value="">
          <div class="honeypot" aria-hidden="true"><label>Sito <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
          <h2>Conferma</h2>
          <p data-slot-summary class="notice">Scegli un orario.</p>
          <?php if ($clientUser): ?>
            <p>Prenoti come <strong><?= e($user['name']) ?></strong></p>
          <?php else: ?>
            <label class="field">Nome e cognome <input name="name" required value="<?= e(field('name', $known['name'] ?? '')) ?>" autocomplete="name"></label>
            <label class="field">Telefono <input name="phone" value="<?= e(field('phone')) ?>" autocomplete="tel"></label>
            <label class="field">Email <input type="email" name="email" required value="<?= e(field('email', $email)) ?>" autocomplete="email"></label>
          <?php endif; ?>
          <?php if ($clientUser): ?>
            <label class="field">Telefono <input name="phone" value="<?= e(field('phone')) ?>" autocomplete="tel"></label>
          <?php endif; ?>
          <label class="field">Note <textarea name="notes" placeholder="Solo se serve, per esempio un'allergia"><?= e(field('notes')) ?></textarea></label>
          <?php if ($cards !== []): ?>
            <label class="checks"><input type="checkbox" name="use_package" value="1"> Usa la tessera (<?= (int) $cards[0]['credits_left'] ?> crediti, <?= e($cards[0]['name']) ?>)</label>
          <?php endif; ?>
          <button class="btn block" type="submit">Prenota</button>
          <p class="muted">Annulli gratis fino a <?= (int) $business['cancel_hours'] ?> ore prima. Qui non si paga: la caparra, se serve, si concorda in sede.</p>
        </form>
        <?php if (!$clientUser && $email === ''): ?>
          <form class="card" method="get" action="<?= e(url('/b/' . $business['slug'])) ?>">
            <input type="hidden" name="servizio" value="<?= $serviceId ?>">
            <?php if ($staffId > 0): ?><input type="hidden" name="staff" value="<?= $staffId ?>"><?php endif; ?>
            <input type="hidden" name="data" value="<?= e($date) ?>">
            <label class="field">Hai già una tessera? Inserisci l'email <input type="email" name="email" required></label>
            <button class="btn small ghost" type="submit">Cerca</button>
          </form>
        <?php endif; ?>
      </aside>
    </div>

    <details class="card wait">
      <summary>Nessun orario va bene? Mettiti in lista</summary>
      <form method="post" action="<?= e(url('/b/' . $business['slug'] . '/attesa')) ?>">
        <?= csrf_field() ?>
        <?= redirect_field('/b/' . $business['slug']) ?>
        <input type="hidden" name="service_id" value="<?= $serviceId ?>">
        <div class="honeypot" aria-hidden="true"><label>Sito <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <p>Se si libera un posto nel giorno che indichi, lo proponiamo a chi è in lista da più tempo. L'offerta resta aperta tre ore, o fino all'orario se manca meno.</p>
        <div class="split">
          <?php if (!$clientUser): ?>
            <label class="field">Nome <input name="name" required value="<?= e(field('name')) ?>"></label>
            <label class="field">Email <input type="email" name="email" required value="<?= e(field('email', $email)) ?>"></label>
          <?php endif; ?>
          <label class="field">Telefono <input name="phone" value="<?= e(field('phone')) ?>"></label>
          <label class="field">Giorno <input type="date" name="date" required value="<?= e($date) ?>"></label>
          <label class="field">Dalle <input type="time" name="time_from" required value="09:00"></label>
          <label class="field">Alle <input type="time" name="time_to" required value="19:00"></label>
          <label class="field">Operatore
            <select name="staff_id">
              <option value="0">Il primo libero</option>
              <?php foreach ($eligible as $person): ?>
                <option value="<?= (int) $person['id'] ?>"<?= $staffId === (int) $person['id'] ? ' selected' : '' ?>><?= e($person['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <button class="btn" type="submit">Avvisami</button>
      </form>
    </details>
  <?php endif; ?>

  <?php if ($sessions !== []): ?>
    <h2>Corsi in arrivo</h2>
    <div class="list">
      <?php foreach ($sessions as $session): ?>
        <?php $left = max(0, (int) $session['capacity'] - (int) $session['taken']); ?>
        <article class="card">
          <strong><?= e($session['service_name']) ?></strong>
          <p><?= e(dt((string) $session['starts_at'], (string) $business['timezone'])) ?> · <?= e($session['staff_name']) ?> · <?= $left ?> posti su <?= (int) $session['capacity'] ?></p>
          <?php if ($left > 0): ?>
            <form method="post" action="<?= e(url('/b/' . $business['slug'] . '/corso')) ?>">
              <?= csrf_field() ?>
              <?= redirect_field('/b/' . $business['slug']) ?>
              <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
              <div class="honeypot" aria-hidden="true"><label>Sito <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
              <?php if (!$clientUser): ?>
                <div class="split">
                  <label class="field">Nome <input name="name" required></label>
                  <label class="field">Email <input type="email" name="email" required value="<?= e($email) ?>"></label>
                </div>
              <?php else: ?>
                <p>Iscrizione a nome di <?= e($user['name']) ?>.</p>
              <?php endif; ?>
              <label class="field">Telefono <input name="phone"></label>
              <label class="checks"><input type="checkbox" name="use_package" value="1"> Scala un credito, se hai una tessera valida</label>
              <button class="btn small" type="submit">Iscriviti · <?= e(euro((int) $session['price_cents'])) ?></button>
            </form>
          <?php else: ?>
            <p class="muted">Completo.</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
