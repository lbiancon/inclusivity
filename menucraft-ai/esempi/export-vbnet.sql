-- ============================================================================
-- VB.NET + config.ini (Form1 TimerOra / function.vb SetIni)
-- ============================================================================
-- INI usati dal flusso mensa:
--   [MENSACONFIG] ini_send_file, ini_oraInvio, ini_nomeFile, ini_pathSave,
--                 ini_fileFormat, hour_ini_reminder, ...
--   [CONNESSIONE] ini_db_* → connectionDB() sul DB MenuCraft
--   [CONNESSIONE_M2] ini_company → slug azienda (opzionale, AziendaIdDaSlug)
--
-- Data pasto (sostituisce getIdPrenotazione che faceva solo +1 / ven+3):
--   DataPastoDaEsportare legge:
--     aziende.apri_sabato / apri_domenica
--     calendario_chiusure (feste nazionali azienda_id IS NULL + ferie servizio)
--     calendario_aperture (giorno reso lavorativo)
--   → primo giorno utile DOPO oggi. Es. ven→lun; lun con mar+mer chiusi→gio.
--
-- Snippet timer: esempi/Form1-mensa-snippet.vb
-- Module: esempi/ExportCucinaDataTable.vb → EseguiExportMensa(...)
--
-- Nome file: {ini_nomeFile}_{yy}{dayOfYear3}{ini_fileFormat}
--   getIdPrenotazione(giornoPasto) = ExportCucina::idGiornoPasto
-- ============================================================================

-- Verifica giorno utile (stessa regola di GiornoLavorativo in VB)
-- SELECT apri_sabato, apri_domenica FROM aziende WHERE id = ?;
-- SELECT 1 FROM calendario_aperture WHERE azienda_id = ? AND data_apertura = ?;
-- SELECT 1 FROM calendario_chiusure
--  WHERE data_chiusura = ? AND (azienda_id = ? OR azienda_id IS NULL);

SELECT badge, employee, ospite, giorno, time_stamp, payment,
       intestazione_json, celle_json
FROM export_cucina
WHERE data_pasto = ?          -- DataPastoIso(DataPastoDaEsportare(...))
  AND azienda_id = ?
ORDER BY CASE WHEN ospite = 'S' THEN 1 ELSE 0 END, employee;
