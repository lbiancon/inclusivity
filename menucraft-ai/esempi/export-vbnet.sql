-- Export stabile per VB.NET / applicazione esterna.
-- Non usare piatti.codice come chiave del giorno: i codici catalogo restano fissi,
-- mentre nel file cucina vanno le lettere A B C del pasto.

-- 1) Tabella già pivotata (consigliata): si aggiorna a ogni prenotazione e a ogni scarico.
--    Colonne allineate al foglio XLS/CSV cucina.
SELECT
    badge AS BADGE,
    employee AS EMPLOYEE,
    primo AS PRIMO,
    secondo AS SECONDO,
    contorno1 AS CONTORNO,
    contorno2 AS CONTORNO2,
    frutta AS FRUTTA,
    yogurt AS YOGURT,
    dessert AS DESSERT,
    bevande AS BEVANDE,
    bevande2 AS BEVANDE2,
    giorno AS GIORNO,
    time_stamp AS TIME_STAMP,
    payment AS PAYMENT,
    allergie AS ALLERGIE
FROM export_cucina
WHERE azienda_id = ?          -- oppure: JOIN aziende a ON a.id = export_cucina.azienda_id AND a.slug = ?
  AND data_pasto = ?          -- 'AAAA-MM-GG'
ORDER BY id;

-- 2) Vista piatta se preferisci costruire tu il pivot in VB.NET.
--    Usa colonna `lettera` (non codice_catalogo) nelle celle PRIMO/SECONDO/...
SELECT
    badge,
    employee,
    lettera,
    categoria,
    piatto,
    payment,
    allergie,
    creato_il
FROM v_export_prenotazioni_righe
WHERE azienda_slug = ?
  AND data_pasto = ?
ORDER BY ordine_id, lettera;

-- 3) Menu del giorno con lettera (per controllo cucina).
SELECT lettera, categoria, piatto, codice_catalogo, prezzo
FROM v_export_menu_giorno
WHERE azienda_slug = ?
  AND data_pasto = ?
ORDER BY lettera;
