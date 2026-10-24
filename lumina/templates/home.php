<section class="hero">
  <div>
    <p class="kicker">Parrucchieri · palestre · studi</p>
    <h1>L'agenda che protegge il margine.</h1>
    <p class="lede">Lumina non si limita a segnare gli appuntamenti. Chiude i buchi tra un cliente e l'altro, abbassa il prezzo nelle ore vuote e, se qualcuno disdice, offre il posto a chi è in lista.</p>
    <div class="actions">
      <a class="btn" href="<?= e(url('/registrati')) ?>">Apri la tua agenda</a>
      <a class="btn ghost" href="<?= e(url('/prezzi')) ?>">Guarda i piani</a>
      <a class="btn ghost" href="<?= e(url('/accedi')) ?>">Entra nella demo</a>
    </div>
  </div>
  <aside class="hero-card">
    <p class="kicker">Tre mosse, non un calendario in più</p>
    <h2>Slot ideale</h2>
    <p>Un orario che riempie esattamente lo spazio tra due impegni sale in cima. Uno scarto sotto i 30 minuti viene segnalato prima di prenotarlo.</p>
    <h2>Prezzo vivo</h2>
    <p>Una fascia, uno sconto, un servizio. Il cliente vede il prezzo pieno e quello nuovo prima di confermare.</p>
    <h2>Disdetta utile</h2>
    <p>Il posto liberato va al primo in lista, con un'offerta che scade. Se nessuno la prende, passa al successivo.</p>
  </aside>
</section>
<section class="section" id="piani">
  <h2>Bottega, Salone, Borgo.</h2>
  <p class="muted">0 €, 29 € o 79 € al mese, IVA esclusa. L'anno costa dieci mesi. I limiti partono subito, l'addebito no: il pagamento non è ancora collegato.</p>
  <p><a class="btn" href="<?= e(url('/prezzi')) ?>">Confronta i piani</a></p>
</section>
<section class="section" id="vetrina">
  <h2>Attività prenotabili</h2>
  <p class="muted">Ogni attività ha la sua pagina, i suoi operatori, le sue sale e le sue regole.</p>
  <div class="grid">
    <?php if ($businesses === []): ?>
      <p class="empty">Nessuna attività ancora. Apri la prima agenda.</p>
    <?php endif; ?>
    <?php foreach ($businesses as $business): ?>
      <a class="card" data-category="<?= e($business['category']) ?>" href="<?= e(url('/b/' . ($business['slug']))) ?>">
        <span class="pill"><?= e(category_label((string) $business['category'])) ?></span>
        <h3><?= e($business['name']) ?></h3>
        <p><?= e($business['tagline'] !== '' ? $business['tagline'] : $business['city']) ?></p>
        <p class="muted"><?= e($business['city']) ?> · <?= (int) $business['service_count'] ?> servizi</p>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<section class="section">
  <div class="grid">
    <article class="card">
      <h3>Persona e postazione</h3>
      <p>Un colore occupa il parrucchiere e una poltrona. Uno yoga occupa l'insegnante e la sala. Due vincoli, un solo orario.</p>
    </article>
    <article class="card">
      <h3>Tessere e corsi</h3>
      <p>Dieci personal, cinque pieghe, una classe da dodici. Il credito si scala alla prenotazione e torna indietro se annulli in tempo.</p>
    </article>
    <article class="card">
      <h3>Affidabilità</h3>
      <p>Dopo due appuntamenti conclusi, Lumina calcola quante volte la persona si è presentata. Sotto la soglia, la scheda dice: chiedi una caparra.</p>
    </article>
  </div>
</section>
