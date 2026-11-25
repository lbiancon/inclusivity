' ============================================================================
' Sostituisci il blocco mensa nel TimerOra di Form1 (quello con
' day <> "Saturday" And day <> "Sunday" And DatatableToExcel()).
'
' Prerequisiti:
' - Copia ExportCucinaDataTable.vb nel progetto (o unisci il Module).
' - connectionDB() punta al DB MenuCraft (stesso host/user dell'INI).
' - Opzionale: ini_company = slug azienda MenuCraft (es. joyson-safety).
' ============================================================================

' Dim oraStart = Convert.ToDateTime(ini_oraInvio).ToString("HH:mm")
' Dim oraNow = dataNow.ToString("HH:mm")

If oraNow = oraStart And ini_send_file = "Y" Then

    If boolEntrySendMensa = False Then
        boolEntrySendMensa = True
        messages = messages & Now & " In Esecuzione mensa MenuCraft .. .." & vbCrLf
        TextBox1.Text = messages

        Dim nameFile As String = Nothing
        Dim dataPastoScelta As Date = Nothing
        Dim aziendaId As Object = ExportCucinaDataTable.AziendaIdDaSlug(
            connectionDB().ConnectionString, ini_company)

        Dim percorso As String = ExportCucinaDataTable.EseguiExportMensa(
            connectionDB().ConnectionString,
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
                " [MENSA]: Excel " & nameFile & " data_pasto=" & dataPastoScelta.ToString("yyyy-MM-dd"),
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

Else
    boolEntrySendMensa = False
End If
