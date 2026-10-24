<section class="section plans-page">
  <p class="kicker">Piani</p>
  <h1>Tre piani, un'agenda.</h1>
  <p class="lede">Bottega per provare. Salone per lavorarci. Borgo per leggere i numeri. IVA esclusa. Oggi non partono addebiti: scegli il piano e i limiti sono già quelli.</p>
  <div class="plan-grid">
    <?php foreach ($offers as $id => $offer): ?>
      <article class="plan-sheet<?= $id === 'salone' ? ' featured' : '' ?>">
        <?php if ($id === 'salone'): ?><span class="pill">Il più usato</span><?php endif; ?>
        <h2><?= e($offer['name']) ?></h2>
        <p class="plan-price"><?= (int) $offer['month'] === 0 ? '0 €' : e(euro((int) $offer['month'])) ?></p>
        <p class="muted"><?= (int) $offer['month'] === 0 ? 'Nessun canone' : euro((int) $offer['year']) . ' l\'anno, due mesi in omaggio' ?></p>
        <p><?= e($offer['line']) ?></p>
        <ul>
          <li><?= (int) $offer['staff'] === 1 ? '1 persona in agenda' : (int) $offer['staff'] . ' persone in agenda' ?></li>
          <li><?= $offer['bookings'] === null ? 'Prenotazioni senza tetto' : (int) $offer['bookings'] . ' prenotazioni al mese' ?></li>
          <li>Pagina pubblica e slot ideale</li>
          <li><?= $offer['rules'] === null ? 'Prezzo vivo senza tetto di regole' : ((int) $offer['rules'] === 0 ? 'Prezzo vivo nel piano successivo' : 'Prezzo vivo, fino a ' . (int) $offer['rules'] . ' regole') ?></li>
          <li><?= $offer['waitlist'] ? 'Lista d\'attesa, tessere, corsi e serie' : 'Lista, tessere, corsi e serie nel Salone' ?></li>
          <li><?= $offer['export'] ? 'Export della settimana' : 'Export nel Salone' ?></li>
          <li><?= $offer['analytics'] ? 'Analisi su incasso, ore e assenze' : 'Analisi nel Borgo' ?></li>
          <li>Codice email, Google e Apple</li>
        </ul>
      </article>
    <?php endforeach; ?>
  </div>
  <p class="actions"><a class="btn" href="<?= e(url('/registrati')) ?>">Apri l'agenda</a> <a class="btn ghost" href="<?= e(url('/accedi')) ?>">Entra</a></p>
  <p class="muted">Il pagamento online non è collegato. Quando lo sarà, questi sono i prezzi. La caparra in agenda resta un avviso, non un incasso.</p>
</section>
