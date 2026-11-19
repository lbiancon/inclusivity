-- Export cucina dinamico per VB.NET.
-- Le colonne pietanze (PRIMO, CONTORNO2, …) dipendono dai massimi del servizio:
-- max 0 = portata assente (niente colonna FRUTTA se frutta=0).
-- OSPITE = 'S' se il buono è di un ospite; HOST = nome del dipendente che lo ospita.

SELECT
    badge AS BADGE,
    employee AS EMPLOYEE,
    ospite AS OSPITE,
    host AS HOST,
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

-- intestazione_json e celle_json sono allineati (stesso ordine di colonne).
-- In VB.NET: deserializzare i due JSON e scrivere le colonne nell'XLS.

-- Vista piatta se preferisci fare il pivot tu.
SELECT badge, employee, ospite, host, lettera, categoria, piatto, payment, allergie, creato_il
FROM v_export_prenotazioni_righe
WHERE azienda_slug = ?
  AND data_pasto = ?
ORDER BY ordine_id, lettera;
