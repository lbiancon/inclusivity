-- Export cucina dinamico per VB.NET.
-- Le colonne pietanze (PRIMO, CONTORNO2, YOGURT, …) dipendono dalle regole
-- del servizio (max_primo, max_contorno, max_yogurt, …) e sono in JSON.

-- Righe già pronte: intestazione_json e celle_json allineati.
SELECT
    badge AS BADGE,
    employee AS EMPLOYEE,
    giorno AS GIORNO,
    time_stamp AS TIME_STAMP,
    payment AS PAYMENT,
    allergie AS ALLERGIE,
    intestazione_json,
    celle_json
FROM export_cucina
WHERE azienda_id = ?          -- oppure JOIN aziende a ON a.id = export_cucina.azienda_id AND a.slug = ?
  AND data_pasto = ?          -- 'AAAA-MM-GG'
ORDER BY id;

-- Esempio: intestazione_json = ["PRIMO","PRIMO2","SECONDO","CONTORNO","CONTORNO2","CONTORNO3","FRUTTA","YOGURT","DESSERT","BEVANDE"]
--          celle_json       = ["A","B","D","E","","","F","","","H"]
-- In VB.NET: deserializzare i due JSON e scrivere le colonne nell'XLS nello stesso ordine.

-- Vista piatta (lettera del giorno, non codice catalogo) se preferisci fare il pivot tu.
SELECT badge, employee, lettera, categoria, piatto, payment, allergie, creato_il
FROM v_export_prenotazioni_righe
WHERE azienda_slug = ?
  AND data_pasto = ?
ORDER BY ordine_id, lettera;
