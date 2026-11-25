' ============================================================================
' Allineato al tuo config.ini reale + Form1 / function.vb
'
' INI → variabili (già in SetIni):
'   [SETUP]            ini_method          = 2  → MenuCraft
'   [CONNESSIONE_M2]   connectionDBM2()    + ini_company=5 (aziende.id)
'   [EXCEL]            nameFile→ini_nomeFile, pathFile→ini_pathSave, ini_fileFormat
'   [ORARIO_INVIO]     oraInvio_ini→ini_oraInvio   (es. 21:56)
'   [MENSACONFIG]      ini_send_file=Y
'
' 1) TIMER (TimerOra) — sostituisci il blocco che chiama DatatableToExcel()
' 2) start_Click manuale — ramo Else (ini_method <> "1")
' ============================================================================

' ---------- 1) TimerOra ----------
' Dim oraStart = Convert.ToDateTime(ini_oraInvio).ToString("HH:mm")
' Dim oraNow = dataNow.ToString("HH:mm")

If oraNow = oraStart And ini_send_file = "Y" Then

    If boolEntrySendMensa = False Then
        boolEntrySendMensa = True
        messages = messages & Now & " In Esecuzione mensa MenuCraft .. .." & vbCrLf
        TextBox1.Text = messages

        If ini_method = "1" Then
            ' Vecchio DB mensa / prenotazioni
            DatatableToExcel()
        Else
            Dim nameFile As String = Nothing
            Dim dataPastoScelta As Date = Nothing
            Dim connMc As String = CStr(connectionDBM2())
            Dim aziendaId As Object = ExportCucinaDataTable.AziendaIdDaIni(connMc, ini_company)

            Dim percorso As String = ExportCucinaDataTable.EseguiExportMensa(
                connMc,
                dataNow,
                ini_pathSave,
                ini_nomeFile,
                ini_fileFormat,
                nameFile,
                aziendaId,
                dataPastoScelta)

            If percorso <> "" Then
                messages = messages & Now & " Export pasto " & dataPastoScelta.ToString("dd/MM/yyyy") &
                    " → " & nameFile & vbCrLf
                messages = messages & Now & " Invio il file Creato per Email" & vbCrLf
                TextBox1.Text = messages
                writeLog.ScriviLog(
                    " [MENSA]: Excel " & nameFile & " data_pasto=" & dataPastoScelta.ToString("yyyy-MM-dd") &
                    " azienda=" & If(aziendaId, "").ToString(),
                    LogPath, True)

                sendMailSMTPKSS(
                    "Ordini-mensa@eu.joysonsafety.com", "",
                    "ordine pasti JOYSONSAFETY",
                    "Ciao siamo la JoysonSafety in allegato il file in excel che contiente gli ordini del giorno",
                    nameFile, ini_pathSave, "", "", listInvioMail, "mensa")
            Else
                messages = messages & Now &
                    " Nessun export (oggi chiuso oppure nessuna prenotazione sul prossimo giorno utile)." & vbCrLf
                TextBox1.Text = messages
                writeLog.ScriviLog(" [MENSA]: skip export " & Now, LogPath, True)
            End If
        End If
    End If

Else
    boolEntrySendMensa = False
End If


' ---------- 2) start_Click (invio manuale) — sostituisci il ramo Else ----------
'
' If ini_method = "1" Then
'     DatatableToExcel()
' Else
'     Dim nameFile As String = Nothing
'     Dim dataPastoScelta As Date = Nothing
'     Dim connMc As String = CStr(connectionDBM2())
'     Dim aziendaId As Object = ExportCucinaDataTable.AziendaIdDaIni(connMc, ini_company)
'     Dim percorso As String = ExportCucinaDataTable.EseguiExportMensa(
'         connMc, dataNow, ini_pathSave, ini_nomeFile, ini_fileFormat,
'         nameFile, aziendaId, dataPastoScelta)
'     If percorso <> "" Then
'         sendMailSMTPKSS("Ordini-mensa@eu.joysonsafety.com", "",
'             "ordine pasti JOYSONSAFETY",
'             "Ciao siamo la JoysonSafety in allegato il file ...",
'             nameFile, ini_pathSave, "", "", listInvioMail, "mensa")
'     End If
' End If
