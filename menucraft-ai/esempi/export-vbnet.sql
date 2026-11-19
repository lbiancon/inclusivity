-- ============================================================================
-- VB.NET semplificato (un foglio, ospiti in fondo)
-- ============================================================================
-- 1. MenuCraft scrive export_cucina (intestazione_json + celle_json).
-- 2. In VB: esempi/ExportCucinaDataTable.vb
--
--    Dim dt = dataMensaCompleto(conn, "2026-11-13", aziendaId)
--    ' dipendenti prima, ospiti (BADGE=OSPITE) in coda
--    DatatableToExcel(dt, "C:\temp\mensa.xls")
--
-- Oppure in un colpo:
--    EsportaGiorno(conn, "2026-11-13", "C:\temp\mensa.xls", aziendaId)
--
-- DatatableToExcel è dinamico: colonne = JSON del servizio, titolo da
-- GIORNO/TIME_STAMP per nome (niente colIndex 15/16, niente 2 fogli).
-- ============================================================================

SELECT badge, employee, ospite, giorno, time_stamp, payment,
       intestazione_json, celle_json
FROM export_cucina
WHERE data_pasto = ?
  AND azienda_id = ?
ORDER BY CASE WHEN ospite = 'S' THEN 1 ELSE 0 END, employee;
