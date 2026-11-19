-- ============================================================================
-- Export per VB.NET (stessa logica del vecchio software)
-- ============================================================================
-- MenuCraft riempie la tabella piatta `export_xls` a ogni prenotazione
-- e a ogni download cucina. Lo schema delle colonne è FISSO, così la
-- query produce un DataTable uguale a prima e la tua ExportExcel
-- non va toccata.
--
-- Colonne fisse (come il file che mandavi al servizio):
--   BADGE, EMPLOYEE, PRIMO, SECONDO, CONTORNO, CONTORNO2,
--   FRUTTA, YOGURT, DESSERT, BEVANDE, BEVANDE2,
--   GIORNO, TIME_STAMP, PAYMENT
-- Extra utili (ignora in SELECT se non servono all'Excel):
--   OSPITE ('S' se ospite), HOST (dipendente che ospita), ALLERGIE
-- ============================================================================

-- Query tipica da usare in VB.NET (MySqlDataAdapter.Fill → DataTable):
SELECT
    BADGE,
    EMPLOYEE,
    PRIMO,
    SECONDO,
    CONTORNO,
    CONTORNO2,
    FRUTTA,
    YOGURT,
    DESSERT,
    BEVANDE,
    BEVANDE2,
    GIORNO,
    TIME_STAMP,
    PAYMENT
FROM v_export_xls
WHERE azienda_slug = ?          -- es. 'officine-rossi'
  AND data_pasto = ?            -- 'AAAA-MM-GG'
ORDER BY BADGE, EMPLOYEE;

-- Stessa cosa sulla tabella grezza (nomi colonna lowercase):
-- SELECT badge AS BADGE, employee AS EMPLOYEE, primo AS PRIMO, ...
-- FROM export_xls
-- WHERE azienda_id = ? AND data_pasto = ?
-- ORDER BY id;

-- Se ti serve anche l'ospite nell'Excel (senza cambiare ExportExcel,
-- aggiungi le colonne nel DataTable solo se la tua funzione le mappa
-- per nome; altrimenti lasciale fuori dalla SELECT sopra):
--   OSPITE, HOST, ALLERGIE
