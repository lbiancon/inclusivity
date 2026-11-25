' ============================================================================
' Preferisci il file completo: esempi/Form1.vb
' Qui i pezzi chiave (timer export + reminder + start) già integrati lì.
' ============================================================================

' Export sera (ini_method=2) → EseguiExportMensa + DataPastoDaEsportare
' Reminder (hour_ini_reminder / hour_ini_reminder2):
'   ListaReminderMail("mattina"|"pomeriggio") su utenti.email con mail_reminder=Y
'   mattina = pasto oggi; pomeriggio = primo giorno utile (ferie/chiusure/weekend)
