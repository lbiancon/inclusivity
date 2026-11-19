-- ============================================================================
-- Percorso consigliato per VB.NET (dinamico)
-- ============================================================================
-- 1) MenuCraft scrive export_cucina a ogni prenotazione/download:
--      badge, employee, ospite ('S'|''), giorno, time_stamp, payment,
--      intestazione_json = ["PRIMO","CONTORNO","BEVANDE",...]
--      celle_json        = ["A","Fx2","M",...]
--
-- 2) In VB usa esempi/ExportCucinaDataTable.vb → dataMensa(_opt)
--    che costruisce il DataTable dalle colonne JSON e lo passi a
--    DatatableToExcel (invariata sulla scrittura celle).
--
-- 3) Filtro ospiti: ospite = 'S'  (come CODE_EMPLOYEE = 'OSPITE')
--    Filtro dipendenti: ospite <> 'S'
--
-- Attenzione: in DatatableToExcel i merge PRIMO/SECONDO e colIndex 15/16
-- per il titolo erano legati allo schema fisso. Con colonne dinamiche
-- leggi GIORNO e TIME_STAMP per nome (helper ValoreColonna nel .vb).
-- ============================================================================

SELECT
    badge,
    employee,
    ospite,
    host,
    giorno,
    time_stamp,
    payment,
    intestazione_json,
    celle_json
FROM export_cucina
WHERE data_pasto = ?                 -- 'AAAA-MM-GG'
  AND azienda_id = ?                 -- opzionale
  AND ospite = 'S'                   -- oppure <> 'S'
ORDER BY employee;

-- Tabella legacy `prenotazioni` (schema fisso) resta popolata in parallelo
-- se ti serve un periodo di transizione; il percorso nuovo è export_cucina.
