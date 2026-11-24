-- ============================================================================
-- VB.NET: data + nome file + server + mail (come il tuo flusso originale)
-- ============================================================================
-- 1. MenuCraft scrive export_cucina (JSON) quando qualcuno prenota / aggiorna.
-- 2. Nel tuo VB, dove già leggevi l'INI e avevi dataNow:
--
--    ' dataNow = stessa variabile di prima (data del pasto da esportare)
--    ' es. la sera: dataNow = Date.Today.AddDays(1)
--    Dim nameFile As String = Nothing
--    Dim percorso = EseguiExportMensa(
--        connectionDB().ConnectionString, dataNow,
--        ini_pathSave, ini_nomeFile, ini_fileFormat, nameFile, aziendaId)
--    If percorso <> "" Then
--        sendMailSMTPKSS("Ordini-mensa@eu.joysonsafety.com", "",
--            "ordine pasti JOYSONSAFETY",
--            "Ciao siamo la JoysonSafety in allegato il file ...",
--            nameFile, ini_pathSave, "", "", listInvioMail, "mensa")
--    End If
--
-- Nome file (identico a prima): {ini_nomeFile}_{yy}{dayOfYear3}{ini_fileFormat}
-- getIdPrenotazione = yy+DayOfYear (5 cifre) = ExportCucina::idGiornoPasto
-- ini_pathSave / ini_nomeFile / listInvioMail / sendMailSMTPKSS = i tuoi di sempre
-- Un foglio: dipendenti + ospiti in fondo. Vedi ExportCucinaDataTable.vb
-- ============================================================================

SELECT badge, employee, ospite, giorno, time_stamp, payment,
       intestazione_json, celle_json
FROM export_cucina
WHERE data_pasto = ?          -- DataPastoIso(dataNow) = yyyy-MM-dd
  AND azienda_id = ?
ORDER BY CASE WHEN ospite = 'S' THEN 1 ELSE 0 END, employee;
