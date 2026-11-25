-- ============================================================================
-- VB.NET + config.ini reale (SetIni in function.vb)
-- ============================================================================
-- [SETUP]            ini_method=2
-- [CONNESSIONE_M2]   connectionDBM2() → menucraft @ u341web03
--                    ini_company=5 → aziende.id (AziendaIdDaIni)
-- [EXCEL]            nameFile→ini_nomeFile, pathFile→ini_pathSave, ini_fileFormat
-- [ORARIO_INVIO]     oraInvio_ini→ini_oraInvio (es. 21:56)
-- [MENSACONFIG]      ini_send_file=Y
-- [DESTINATARI]      mail=... → listInvioMail
--
-- EseguiExportMensa(connectionDBM2, dataNow, ini_pathSave, ini_nomeFile, ...)
-- DataPastoDaEsportare: primo giorno utile dopo oggi (weekend/ferie/chiusure).
-- Snippet: Form1-mensa-snippet.vb | esempio INI: config.mensa.example.ini
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
