Imports Microsoft.Office.Interop
Imports System
Imports System.Data
Imports System.Reflection
Imports MySql.Data
Imports MySql.Data.MySqlClient
Imports System.Globalization
Imports System.IO
Imports System.Threading

Imports System.Collections.Generic
Imports System.Text.RegularExpressions
Imports System.Drawing
Imports Excel = Microsoft.Office.Interop.Excel


Public Class Form1

    Public dataNow As Date
    Dim oraNow As String
    Public messages As String
    Public messagesInterventi As String
    Public messagesDashScreen As String
    Public messagesProdTotInf As String
    Public messagesChecklist As String
    Dim idPrenotazione As String
    Public startTicket As String
    Public startMensa As String
    Public startDashScreen As String
    Public startProdTotInf As String
    Public startCheck As String
    Public startQStatusCheck As String
    Public time As String
    Public arrayListScreen As New List(Of String)
    Dim xPointForm As Integer
    Dim yPointForm As Integer
    Public sender As String = "N"
    Public writeLog As New readWriteIni
    'variabili private per fare in modo che si verifichi solo una volta evento nelle funzioni Dash inf / ab Mensa Timbratrice Prod Luca 29/09/2020 
    Private boolEntrySendDashInf = False
    Private boolEntrySendDashAb = False
    Private boolEntrySendMensa = False
    Private boolEntrySendTimbrature = False
    Private boolEntrySendProd = False
    Private boolEntrySendReminder = False
    Private boolEntrySendReminderMenu = False
    Public boolEntrySendCheckList = False

    Public LogPath = System.IO.Path.GetFullPath("SenderDebug.log")
    'Dim filenm As String = Nothing

    Private Sub TimerOra_Tick(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles TimerOra.Tick
        TimerOra.Enabled = False
        dataNow = Now

        'dataNow = Convert.ToDateTime(dataNow).ToString("dd/MM/yyyy")
        'Dim format As String = "dd/MM/yyyy HH:mm:ss"
        'ora.Text = Convert.ToDateTime(dataNow).ToString(format)

        ora.Text = dataNow

        Dim oraNow = dataNow.ToString("HH:mm")
        Dim second = dataNow.ToString("ss")
        Dim minutes = dataNow.ToString("mm")
        ' Dim dataParseNow = DateTime.ParseExact(dataNow, format, System.Globalization.CultureInfo.InvariantCulture)
        Dim DataNowFormat = dataNow.AddDays(-1).ToString("dd/MM/yyyy HH:mm:ss")
        'Dim DataNowFormat = Convert.ToDateTime(DateAdd(DateInterval.Day, -1, CDate(dataNow))).ToString(ini_format_data_String & " " & "06:00:00")
        'serve per formattare la data per dourly report 
        Dim dDaTaXlsProdNome = dataNow.AddDays(-1).ToString("dd/MM/yyyy")
        'serve per passare la data di riavvio del server dopo lo stop db backup ' deprecato da quando messo CFP1 
        Dim oraStart = Convert.ToDateTime(ini_oraInvio).ToString("HH:mm")
        Dim oraStartProdTotInf = Convert.ToDateTime(ini_oraInvioProdTotInf).ToString("HH:mm")
        Dim oraRestartORCL = Convert.ToDateTime(ini_ora_restartORCL).ToString("HH:mm")

        'setta il nome giorno in lingua English 
        Dim day = (dataNow.ToString("dddd", New CultureInfo("en-GB")))

        time = Now.ToString("HH:mm")

        'Dim dt = DateTime.ParseExact(dataNow, "ddd, MM/dd/yyyy", Thread.CurrentThread.CurrentCulture)

        'setta gli orari presi dal file config.ini per gli Inflator
        Dim timeSendTurno1 As String = Convert.ToDateTime(ini_send_turno1).ToString("HH:mm")
        Dim timeSendTurno2 As String = Convert.ToDateTime(ini_send_turno2).ToString("HH:mm")
        Dim timeSendTurno3 As String = Convert.ToDateTime(ini_send_turno3).ToString("HH:mm")

        'setta gli orari presi dal file config.ini per gli airbag 
        Dim timeSendTurno1_Airbag As String = Convert.ToDateTime(ini_send_turno1_Airbag).ToString("HH:mm")
        Dim timeSendTurno2_Airbag As String = Convert.ToDateTime(ini_send_turno2_Airbag).ToString("HH:mm")
        Dim timeSendTurno3_Airbag As String = Convert.ToDateTime(ini_send_turno3_Airbag).ToString("HH:mm")

        ' Export mensa: method 1 = DB vecchio; method 2 = MenuCraft (primo giorno utile)
        If oraNow = oraStart And ini_send_file = "Y" Then

            If boolEntrySendMensa = False Then
                boolEntrySendMensa = True
                messages = messages & Now & " In Esecuzione mensa .. .." & vbCrLf
                TextBox1.Text = messages

                If ini_method = "1" Then
                    If day <> "Saturday" And day <> "Sunday" Then
                        DatatableToExcel()
                        writeLog.ScriviLog(" [MENSA]: FILE Excel DatatableToExcel() " & minutes, LogPath, True)
                    End If
                Else
                    Dim nameFileMc As String = Nothing
                    Dim dataPastoScelta As Date = Nothing
                    Dim connMc As String = CStr(connectionDBM2())
                    Dim aziendaIdMc As Object = ExportCucinaDataTable.AziendaIdDaIni(connMc, ini_company)
                    Dim percorsoMc As String = ExportCucinaDataTable.EseguiExportMensa(
                        connMc, dataNow, ini_pathSave, ini_nomeFile, ini_fileFormat,
                        nameFileMc, aziendaIdMc, dataPastoScelta)
                    If percorsoMc <> "" Then
                        messages = messages & Now & " Export pasto " & dataPastoScelta.ToString("dd/MM/yyyy") &
                            " → " & nameFileMc & vbCrLf
                        TextBox1.Text = messages
                        writeLog.ScriviLog(
                            " [MENSA]: Excel " & nameFileMc & " data_pasto=" & dataPastoScelta.ToString("yyyy-MM-dd"),
                            LogPath, True)
                        sendMailSMTPKSS("Ordini-mensa@eu.joysonsafety.com", "", "ordine pasti JOYSONSAFETY",
                            "Ciao siamo la JoysonSafety in allegato il file in excel che contiente gli ordini del giorno",
                            nameFileMc, ini_pathSave, "", "", listInvioMail, "mensa")
                    Else
                        messages = messages & Now & " Nessun export (oggi chiuso o nessuna prenotazione)." & vbCrLf
                        TextBox1.Text = messages
                        writeLog.ScriviLog(" [MENSA]: skip export " & Now, LogPath, True)
                    End If
                End If
            End If

        Else
            boolEntrySendMensa = False
            If ini_send_file = "N" And ini_mensa_enable = "N" Then
                messages = "INI DISABLED and not setting send ini file" & vbCrLf
                TextBox1.Text = messages
                messages = ""
            End If
        End If

        If oraNow = ini_oraInvioCtrl_ini And day <> "Sunday" And day <> "Monday" And ini_send_file = "Y" Then

            If boolEntrySendTimbrature = False Then

                boolEntrySendTimbrature = True
                'messages = messages & Now & " In Esecuzione Timbrature Mensa .. .." & vbCrLf
                'TextBox1.Text = messages
                'writeLog.ScriviLog(" [TIMBRATURE ]: eseguo la selezione del giorno  " & Now, LogPath, True)
                'selectDatiDay(Now)

                'chiudiDBAccess()

                'writeLog.ScriviLog(" [TIMBRATURE ]: giorno scelto e database Access chiuso   " & Now, LogPath, True)
                'DatatableToExcelTimbratureMensa(nameMensaTimbrature, DtdataTimbrature) ' COMMENTATA IL GIORNO 17/09/2025 PERCHE' NON SERVE PIU' INVIARLI LUCA 

                'writeLog.ScriviLog(" [TIMBRATURE ]: file excel creato funzione   DatatableToExcelTimbratureMensa(nameMensaTimbrature, DtdataTimbrature)  " & Now, LogPath, True)

            End If

        Else

            boolEntrySendTimbrature = False

            If ini_send_file = "N" And ini_mensa_enable = "N" Then
                messages = "INI DISABLED and not setting send ini file" & vbCrLf
                TextBox1.Text = messages
                messages = ""
            Else
                'messages = messages & Now & " In Esecuzione .. .." & vbCrLf
                'TextBox1.Text = messages
                'messages = ""
            End If
        End If
        'messages = ""
        'messages = messages & oraNow & " = " & hour_ini_reminder & " or " & oraNow & " = " & hour_ini_reminder2 & vbCrLf
        'TextBox1.Text = messages
        'prova reminder mensa 24/10/2018 

        ' Reminder mensa: method 1 = DB vecchio (MAIL=Y + mail ricostruita);
        ' method 2 = MenuCraft (utenti.mail_reminder=Y + utenti.email che inserisci tu).
        ' Mattina = pasto di oggi; pomeriggio = primo giorno utile (ferie/chiusure/weekend).
        If (oraNow = hour_ini_reminder And ini_send_file = "Y") Or
                (oraNow = hour_ini_reminder2 And ini_send_file = "Y") Then

            If boolEntrySendReminder = False Then
                boolEntrySendReminder = True
                messages = messages & Now & " invio Reminder .. .." & oraNow & vbCrLf
                TextBox1.Text = messages

                Dim reminderMail As New List(Of String)
                Dim userSender As String = "Reminder_Mensa@eu.joysonsafety.com"
                Dim subject As String = "Reminder Prenotazione Mensa"
                Dim textMail As String = ""
                Dim linkMensa As String = "http://341portal01/menucraft-ai/"
                Dim limOggi As String = "09:30"
                Dim limDomani As String = "18:00"
                Dim tipoReminder As String = If(oraNow = hour_ini_reminder, "mattina", "pomeriggio")

                If ini_method = "1" Then
                    If day = "Saturday" Or day = "Sunday" Then
                        reminderMail = New List(Of String)
                    Else
                        reminderMail = getReminderListMail(tipoReminder, dataNow, day)
                    End If
                Else
                    Dim connMcR As String = CStr(connectionDBM2())
                    Dim aziendaIdR As Object = ExportCucinaDataTable.AziendaIdDaIni(connMcR, ini_company)
                    Dim limiti() As String = ExportCucinaDataTable.LimitiPrenotazione(connMcR, aziendaIdR)
                    limOggi = limiti(0)
                    limDomani = limiti(1)
                    reminderMail = ExportCucinaDataTable.ListaReminderMail(connMcR, tipoReminder, dataNow, aziendaIdR)
                End If

                If tipoReminder = "mattina" Then
                    textMail = "Buongiorno,<br>"
                    textMail &= "Se hai dimenticato di prenotare il <b>pasto per oggi</b>, puoi ancora farlo <b>entro e non oltre le ore " & limOggi & "</b><br>"
                    textMail &= "MANDA UN MESSAGGIO WHATSAPP al seguente numero telefonico: <br>"
                    textMail &= "<b>Due di Picche (unico contatto ufficiale: solo richieste via <b><mark>WhatsApp</mark></b>) <abbr title='Phone'><a href='tel:+393298987862'>3298987862</a></abbr></b><br>"
                    textMail &= "Ti ricordiamo anche di prenotare subito, e comunque <b>entro e non oltre le ore " & limDomani & "</b>, il servizio mensa per il prossimo giorno utile, se vuoi usufruirne<br>"
                    textMail &= "Questo è il link per effettuare la prenotazione: <a href='" & linkMensa & "'><b>Vai al Servizio Mensa</b></a><br><br>"
                    textMail &= "Grazie e buona giornata<br><br>"
                    writeLog.ScriviLog(" [reminder Mensa]: mattina destinatari=" & reminderMail.Count & " " & Now, LogPath, True)
                Else
                    textMail = "Buon pomeriggio,<br>"
                    textMail &= "Ti ricordiamo di prenotare subito, e comunque <b>entro e non oltre le ore " & limDomani & "</b>, il servizio mensa per il prossimo giorno utile, se vuoi usufruirne<br>"
                    textMail &= "Questo è il link per effettuare la prenotazione: <a href='" & linkMensa & "'><b>Vai al Servizio Mensa</b></a><br><br>"
                    textMail &= "Grazie e buona giornata<br><br>"
                    writeLog.ScriviLog(" [reminder Mensa]: pomeriggio destinatari=" & reminderMail.Count & " " & Now, LogPath, True)
                End If

                Threading.Thread.Sleep(1000)
                If reminderMail.Count > 0 Then
                    sendMailSMTPKSS(userSender, "", subject, textMail, "", "", "", "", reminderMail, "Reminder Mensa")
                End If
                writeLog.ScriviLog(" [reminder Mensa]: fine invio " & Now, LogPath, True)
            End If
        Else
            boolEntrySendReminder = False
        End If

        'serve ad inviare al gestore della mensa il reminder del menu' da inviarci in settimana Luca 09/08/2019 
        If (oraNow = hour_ini_menu And day = day_ini_menu And ini_send_file = "Y") Then

            If boolEntrySendReminderMenu = False Then

                boolEntrySendReminderMenu = True

                messages = messages & Now & " invio Reminder Menu Gestore .. .." & hour_ini_menu & vbCrLf
                TextBox1.Text = messages

                Dim userSender As String = "JSS_Colleferro@eu.joysonsafety.com"
                Dim subject As String = "Reminder invio menu Settimanale"
                Dim textMail As String = ""

                textMail = "Buongiorno,<br>"
                textMail = textMail & "Vi ricordiamo di inviare entro le ore 17.00  di oggi il menu settimanale a Joyson Safety Systems</b><br>"
                textMail = textMail & "Grazie e buona giornata<br><br>"

                sendMailSMTPKSS(userSender, "", subject, textMail, "", "", "", "", listInvioMail, "Reminder Menu Mensa")
                writeLog.ScriviLog(" [reminder Mensa]: entrato e inviato il reminder gestore   " & Now, LogPath, True)
            End If

        Else
            boolEntrySendReminderMenu = False
        End If
        'writeLog.ScriviLog(" 1: prova", LogPath, True)

        ' serve per inviare i screenshot delle dash che sono sul server e se inviate salvarle nel backup
        ' attiva se messo a Y l invio dei file *png presenti sul server alla posta 
        If ini_saveScreen = "Y" Then
            If (ini_cicle_continuos = "Y") Then
                LabelInvioType.Text = "CONTINUOS"
            Else
                LabelInvioType.Text = "SHIFT END"
            End If
            'per inflator 
            ' se trovo vero uno di questi spedisco e se ok faccio anche il backup dei file 
            ' se e' presente ini_cicle_conitnuo praticamente passo qui oggni secondo del timer per default sull ini e' a N 
            'modifica effettuata per fare in maniera ch ogni 15 min il sender contrlli le directory e se nel caso ci sono file li invia 
            'Luca 22/09/2020 prova per elimanre il problema del non invio dei file Dash 
            If ((time = timeSendTurno1) Or (time = timeSendTurno2) Or (time = timeSendTurno3) Or (startDashScreen = "Y") Or
                (ini_cicle_continuos = "Y")) Then
                'Or
                '(time = getSecondSend(time, timeSendTurno1, 15, 16) Or
                ' time = getSecondSend(time, timeSendTurno2, 15, 16) Or
                ' time = getSecondSend(time, timeSendTurno3, 15, 16))
                If boolEntrySendDashInf = False Then

                    boolEntrySendDashInf = True
                    'If boolEntrySend = False Then
                    '    boolEntrySend = True
                    'Else
                    '    boolEntrySend = False
                    'End If

                    'If boolEntrySend = True Then
                    If ini_checkQStatus_enabled_if = "Y" Then
                        dataQualityStatus(Now, "59", "Inflator")
                    End If

                    writeLog.ScriviLog(" 1[Inflator]: entro orario di lettura screen inflator minutes " & minutes, LogPath, True)
                    inviaScreenDash("Inflator", listInvioDash)

                    'End If
                End If


            Else
                boolEntrySendDashInf = False
            End If
            'per Airbag 

            If ((time = timeSendTurno1_Airbag) Or (time = timeSendTurno2_Airbag) Or (time = timeSendTurno3_Airbag) Or (startDashScreen = "Y") Or
                (ini_cicle_continuos = "Y")) Then
                'Or
                '(time = getSecondSend(time, timeSendTurno1_Airbag, 15, 16) Or
                ' time = getSecondSend(time, timeSendTurno2_Airbag, 15, 16) Or
                ' time = getSecondSend(time, timeSendTurno3_Airbag, 15, 16))
                If boolEntrySendDashAb = False Then

                    boolEntrySendDashAb = True

                    If ini_checkQStatus_enabled = "Y" Then
                        dataQualityStatus(Now, "59", "Airbag")
                    End If

                    writeLog.ScriviLog(" 1[Airbag]: entro orario di lettura screen Airbag minutes " & minutes, LogPath, True)
                    inviaScreenDash("Airbag", listInvioDashAirbag)

                End If

            Else
                boolEntrySendDashAb = False
            End If
            'per Multipanel
            'If ((time = ini_send_turno1_Multi_inf) Or (time = ini_send_turno2_Multi_inf) Or (time = ini_send_turno3_Multi_inf) Or (startDashScreen = "Y") Or (ini_cicle_continuos = "Y")) Then
            '    inviaScreenDash("Inflator_Multipanel", listInvioMultiPanelInf)
            'End If
        End If
        ' serve per controllare se sia attivo sull'ini la funzione che exporta i dati e invia gli stessi della produzione infLAtor 
        If ini_exportProdTorInf = "Y" Then

            LabelInvioTotProdInf.Text = ini_oraInvioProdTotInf

            If oraNow = oraStartProdTotInf Or startProdTotInf = "Y" Then

                If boolEntrySendProd = False Then

                    boolEntrySendProd = True

                    messagesProdTotInf = messagesProdTotInf & Now & " In Esecuzione .. .." & vbCrLf
                    TextBoxDayProdPck.Text = messagesProdTotInf
                    ' mi connetto a oracle passando l'ini del nome database
                    connectionORACLE(ini_db_name_oracle_Prod)
                    'creo un array datatable
                    Dim dtProdCompleto As New DataTable
                    'prelevo i dati dalla funzione e li associo al datatable
                    dtProdCompleto = getDataProdTotInf(dataNow, ini_hour_setting)

                    'devo creare una funzione che manda per curl i dati in pagina web per hourly central 
                    Dim stDateCurl = dataNow.ToString("MM/dd/yyyy")

                    '############## INVIA I DATA PER CURL IN ROMANIA DEPRECATED 17/06/2020 
                    'Dim resultData = sendCurlHourlyData("Colleferro", "0bfb6a6eb52ba2338137210099dbc5c3", stDateCurl, dtProdCompleto)
                    'If resultData = "" Then
                    '    resultData = "Success"
                    'Else
                    '    resultData = " error " & resultData
                    'End If
                    'messagesProdTotInf = messagesProdTotInf & Now & " Data send on webPage CODE : " & resultData & vbCrLf

                    'creo i dati excel passando 
                    createXlsDataProdTotInf("CLF", dDaTaXlsProdNome, dtProdCompleto)
                    'chiudo connessione oracle
                    closeDBOracle()
                    startProdTotInf = "N"
                    'rilancio i ticket poiche chiudo la connessione appena 
                    startTicket_Cfg()
                    'inserisco i dati in un db per poi estrapolarli con il web 
                    connectionDB()
                    insertDbHourly(dtProdCompleto, dataNow)
                    messagesProdTotInf = messagesProdTotInf & Now & "_Record inseriti in mysql db mensa .." & vbCrLf

                    TextBoxDayProdPck.Text = messagesProdTotInf

                    TextBoxDayProdPck.ScrollToCaret()
                    chiudiDb()
                    writeLog.ScriviLog(" [EXPORT PRODUCT]: entrato MANUALE e inviato  " & Now, LogPath, True)
                    ' DatatableToXlsProdTot()
                End If

            Else
                boolEntrySendProd = False
            End If
        Else
            LabelInvioTotProdInf.Text = "inactive"
        End If
        ' creo un if che mi serve a far riaprire la connessione a Oracle dato che il sabato si chiude automaticamente
        'If oraNow = oraRestartORCL And day = "Monday" Then
        '    messagesInterventi = Now & " Restarted Oracle " & vbCrLf
        '    TextBoxInfoTicket.Text = messagesInterventi
        '    'APRO LA CONNESSIONE DB ORACLE WEB_INTERVENTI
        '    connectionORACLE()
        '    ' getStauts_Ticket("VALID", 0)
        '    'DatatableToExcel()
        '    startTicket = "Y"
        'End If
        If ini_ticket_enabled = "Y" Then
            startTicket_Cfg()
        End If
        'Se e' a y signifuca che ho cliccato il tasto start interventi 
        If startTicket = "Y" Then
            ' controlla se ci sono interventi aperti 
            Dim ctrlContentTicket As Boolean = getStauts_Ticket("OPEN", 0)
            ticket(ctrlContentTicket, "OPEN", second, 0)

            ctrlContentTicket = getStauts_Ticket("OPEN", 1)
            ticket(ctrlContentTicket, "OPEN", second, 1)

            ' CONTROLLO I MINUTI CHE PASSO SU WORK DI UN TICKET
            ctrlContentTicket = getStauts_Ticket("WORK", 1)
            ticket(ctrlContentTicket, "WORK", second, 1)

            ' CONTROLLO I MINUTI CHE PASSO SU WORK DI UN TICKET
            'ctrlContentTicket = getStauts_Ticket("CLOSE", 2)
            'ticket(ctrlContentTicket, "CLOSE", second)

            ' CONTROLLO I MINUTI CHE PASSO SU WORK DI UN TICKET
            ctrlContentTicket = getStauts_Ticket("CLOSE", 1)
            ticket(ctrlContentTicket, "CLOSE", second, 1)

            'writeLog.ScriviLog(" [Ticket ]: entrato e inviato il ticket getReminderListMail  " & Now, LogPath, True)
        End If
        'se ho a Y nell'ini leggo la funzione 
        If ini_checklist_enabled = "Y" Then
            startCheckList_Cfg()
        End If
        'se e' Y leggo nel db checkdb e faccio i controlli per invio maiul superato il tempo max confiugurato sempre nel file ini 15/05/2020
        If startCheck = "Y" Then
            getCtrlCheckList(Now, second)
        End If
        ' qstatus check 
        If ini_checkQStatus_enabled = "Y" Then
            'startCheckQStatus()
        End If
        If startQStatusCheck = "Y" Then
            'dataQualityStatus(Now, second)
        End If
        'controlo che sia attivo a Y il flag per vedere i not_o check_list Luca 24/08/2021 
        If ini_checklist_not_ok_ctrl = "Y" Then
            ' startCheckList_Cfg()
            'getCtrlCheckList_NotOk(Now, second)
        End If

        TimerOra.Enabled = True
    End Sub
    'controlla dal second ad intervallo  dopo il tempo di invio e prova ad inviare di nuovo i file della dashboard Luca 23/09/2020 
    Public Function getSecondSend(ByVal _time As String, ByVal _dataRef As String, ByVal _second As Int32, ByVal _interval As Int32)
        Dim refSecond As String = Nothing

        For i = _second To _interval

            Dim second = 5

            refSecond = Convert.ToDateTime(DateAdd(DateInterval.Second, i, CDate(_dataRef))).ToString("HH:mm:ss")

            second += 5

            If _time = refSecond Then
                i = _interval
                writeLog.ScriviLog(" 1 [Inflator-Airbag]: entro orario di lettura funzione  getSecondSend " & refSecond, LogPath, True)
                Me.messagesDashScreen = Me.messagesDashScreen & Now & " Dashboard Screen Inviato con Successo getSecondSend " & vbCrLf
                Me.TextBoxInfoDashScreenMail.Text = Me.messagesDashScreen
                Me.NotifyIcon1.BalloonTipText = "Dashboard Screen Inviato con Successo getSecondSend"
            End If
        Next

        Return refSecond
    End Function
    Sub ticket(ByVal _ctrlTicket As Boolean, ByVal _status As String, ByVal second As String, ByVal _ctrl As Int32)

        ' se esistono interveniti 
        If _ctrlTicket = True And _status = "OPEN" Then
            ' prendo i campi che mi servono per poterli inviare per mail 
            For i = 0 To listaTicketOpen.Rows.Count - 1

                ' scrivo a video cosa succede 

                Dim department As String = listaTicketOpen.Rows(i)(2).ToString()
                Dim idTicket As String = listaTicketOpen.Rows(i)(0).ToString()
                Dim dataOpen As String = listaTicketOpen.Rows(i)(10).ToString()
                Dim plant As String = listaTicketOpen.Rows(i)(16).ToString()
                Dim line As String = listaTicketOpen.Rows(i)(3).ToString()

                Dim station As String = listaTicketOpen.Rows(i)(17).ToString()
                Dim stationNo As String = listaTicketOpen.Rows(i)(18).ToString()
                Dim stationNoInt As String = listaTicketOpen.Rows(i)(1).ToString()
                station = station & " - " & listaTicketOpen.Rows(i)(4).ToString()
                Dim description As String = listaTicketOpen.Rows(i)(9).ToString()
                Dim timeWork As String = listaTicketOpen.Rows(i)(20).ToString()

                ' creo una lista di mail prendendole sul db 
                Dim listMAilTicket As IList(Of String) = getListMailTicket(department, "0")
                If _ctrl = 0 Then
                    ' se la lista esiste ed' e' diversa da zero comnpongo la mail e la invio
                    If listMAilTicket.Count <> 0 Then

                        messagesInterventi = messagesInterventi & "IDTK : " & idTicket & vbCrLf
                        TextBoxInfoTicket.Text = messagesInterventi

                        Dim userSender As String = "TICKET_CLF_ALERT@eu.joysonsafety.com"
                        Dim subject As String = "ALERT OPEN CL-ID Ticket: " & idTicket & " Data OPEN : " & dataOpen
                        Dim textMail As String = "ALERT TICKET OPEN : " & idTicket & "<br>"
                        textMail = textMail & "PLANT :" & plant & "<br>"
                        textMail = textMail & "POSTAZIONE: " & line & " " & station & "<br>"
                        textMail = textMail & "Descrizione Problema : " & description & "<br>"
                        ' costruingo la string per il link cliccabile e vef
                        Dim linkTicket As String = "<a href=""" & ini_urlLink & line & "&STATION_NO=" & stationNo & "&SITE=" & plant & "&STATION_NO_INT=" & stationNoInt & """>OPEN TICKET</a>" & "<br>"
                        textMail = textMail & vbCrLf & "link Ticket : " & linkTicket & "<br>"
                        'invio la mail
                        Dim ctrlBoolEmailOk As Boolean = sendMailSMTPKSS(userSender, "", subject, textMail, "", "", "", "", listMAilTicket, "ticket")
                        ' se e' staTA inviata aggiorrno i record mettendo i flag a 1 cosi da non doverli reinviare di nuovo
                        If ctrlBoolEmailOk = True Then
                            Dim ctrlBoolFlag As Boolean
                            ctrlBoolFlag = updateFlagTicket(idTicket, 1)
                            If ctrlBoolFlag = True Then
                                'TextBoxInfoTicket.Text = messagesInterventi & vbCrLf & "TICKET INVIATO " & vbCrLf
                                ' MessageBox.Show("aggiornato con successo")
                            Else

                            End If
                        End If
                    End If
                End If
                If _ctrl = 1 Then
                    ' se la lista esiste ed' e' diversa da zero comnpongo la mail e la invio
                    If listMAilTicket.Count <> 0 Then
                        If listMAilTicket.Count <> 0 And (timeWork = ini_maxDurationTicket Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 30).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 60).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 90).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 120).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 150).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 180).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 210).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 240).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 270).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 300).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 330).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 360).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 390).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 420).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 450).ToString) Or
                                                   (timeWork = (CInt(ini_maxDurationTicket) + 480).ToString)
                                                   ) And second = "59" Then
                            messagesInterventi = messagesInterventi & "IDTK : " & idTicket & vbCrLf
                            TextBoxInfoTicket.Text = messagesInterventi

                            writeLog.ScriviLog(" [Ticket ]: entrato e inviato il ticket getReminderListMail  " & Now & " |  Linea: " & line & " | Descrizione : " & description, LogPath, True)

                            Dim userSender As String = "TICKET_CLF_ALERT@eu.joysonsafety.com"
                            Dim subject As String = "ALERT OPEN CL-ID Ticket: " & idTicket & " Data OPEN : " & dataOpen
                            Dim textMail As String = "ALERT TICKET " & _status & " : " & idTicket & "<br>DURATION " & timeWork & " minuti <br>"
                            textMail = textMail & "PLANT :" & plant & "<br>"
                            textMail = textMail & "POSTAZIONE: " & line & " " & station & "<br>"
                            textMail = textMail & "Descrizione Problema : " & description & "<br>"
                            ' costruingo la string per il link cliccabile e vef
                            Dim linkTicket As String = "<a href=""" & ini_urlLink & line & "&STATION_NO=" & stationNo & "&SITE=" & plant & "&STATION_NO_INT=" & stationNoInt & """>OPEN TICKET</a>" & "<br>"
                            textMail = textMail & vbCrLf & "link Ticket : " & linkTicket & "<br>"


                            If CInt(timeWork) >= ini_maxDurationTicket And CInt(timeWork) <= 480 Then
                                listMAilTicket = getListMailTicket(department, "1")
                                If CInt(timeWork) >= 180 And CInt(timeWork) <= 480 Then
                                    listMAilTicket.Add(ini_mail_plant_manager)
                                End If

                            End If
                            'invio la mail
                            Dim ctrlBoolEmailOk As Boolean = sendMailSMTPKSS(userSender, "", subject, textMail, "", "", "", "", listMAilTicket, "ticket")
                            ' se e' staTA inviata aggiorrno i record mettendo i flag a 1 cosi da non doverli reinviare di nuovo
                            If ctrlBoolEmailOk = True Then
                                Dim ctrlBoolFlag As Boolean
                                ctrlBoolFlag = updateFlagTicket(idTicket, 1)
                                If ctrlBoolFlag = True Then
                                    'TextBoxInfoTicket.Text = messagesInterventi & vbCrLf & "TICKET INVIATO " & vbCrLf
                                    ' MessageBox.Show("aggiornato con successo")
                                Else

                                End If
                            End If
                        End If
                    End If
                End If

            Next
        End If
        If _ctrlTicket = True And (_status = "WORK") Then
            TextBoxInfoTicket.Text = messagesInterventi
            ' prendo i campi che mi servono per poterli inviare per mail 
            For i = 0 To listaTicketOpen.Rows.Count - 1

                ' scrivo a video cosa succede 
                Dim department As String = listaTicketOpen.Rows(i)(2).ToString()
                Dim idTicket As String = listaTicketOpen.Rows(i)(0).ToString()
                Dim dataWork As String = listaTicketOpen.Rows(i)(11).ToString()
                Dim plant As String = listaTicketOpen.Rows(i)(16).ToString()
                Dim line As String = listaTicketOpen.Rows(i)(3).ToString()

                Dim station As String = listaTicketOpen.Rows(i)(17).ToString()
                Dim stationNo As String = listaTicketOpen.Rows(i)(18).ToString()
                Dim stationNoInt As String = listaTicketOpen.Rows(i)(1).ToString()
                station = station & " - " & listaTicketOpen.Rows(i)(4).ToString()
                Dim description As String = listaTicketOpen.Rows(i)(9).ToString()
                Dim timeWork As String = listaTicketOpen.Rows(i)(20).ToString()


                ' creo una lista di mail prendendole sul db 
                Dim listMAilTicket As IList(Of String) = getListMailTicket(department, "0")
                ' se la lista esiste ed' e' diversa da zero comnpongo la mail e la invio
                If _ctrl = 1 Then
                    If listMAilTicket.Count <> 0 And (timeWork = ini_maxDurationTicket Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 30).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 60).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 90).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 120).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 150).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 180).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 210).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 240).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 270).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 300).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 330).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 360).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 390).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 420).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 450).ToString) Or
                                                                       (timeWork = (CInt(ini_maxDurationTicket) + 480).ToString)
                                                                       ) And second = "59" Then

                        messagesInterventi = messagesInterventi & "IDTK : " & idTicket & " " & _status & " TIME INI: " & timeWork & vbCrLf
                        TextBoxInfoTicket.Text = messagesInterventi

                        writeLog.ScriviLog(" [Ticket ]: entrato e inviato il ticket getReminderListMail  " & Now, LogPath, True)

                        Dim userSender As String = "TICKET_CLF_ALERT@eu.joysonsafety.com"
                        Dim subject As String = "ALERT " & _status & " CL-ID Ticket: " & idTicket & " Data  " & _status & " : " & dataWork
                        Dim textMail As String = "ALERT TICKET " & _status & " : " & idTicket & "<br>DURATION " & timeWork & " minuti <br>"
                        textMail = textMail & "PLANT :" & plant & "<br>"
                        textMail = textMail & "POSTAZIONE: " & line & " " & station & "<br>"
                        textMail = textMail & "Descrizione Problema : " & description & "<br>"
                        ' costruingo la string per il link cliccabile e vef
                        Dim linkTicket As String = "<a href=""" & ini_urlLink & line & "&STATION_NO=" & stationNo & "&SITE=" & plant & "&STATION_NO_INT=" & stationNoInt & """>OPEN TICKET</a>" & "<br>"
                        textMail = textMail & vbCrLf & "link Ticket : " & linkTicket & "<br>"

                        If CInt(timeWork) >= ini_maxDurationTicket And CInt(timeWork) <= 480 Then
                            listMAilTicket = getListMailTicket(department, "1")
                            If CInt(timeWork) >= 180 And CInt(timeWork) <= 480 Then
                                listMAilTicket.Add(ini_mail_plant_manager)
                            End If

                        End If

                        'invio la mail
                        Dim ctrlBoolEmailOk As Boolean = sendMailSMTPKSS(userSender, "", subject, textMail, "", "", "", "", listMAilTicket, "ticket")
                        ' se e' staTA inviata aggiorrno i record mettendo i flag a 1 cosi da non doverli reinviare di nuovo
                        If ctrlBoolEmailOk = True Then
                            Dim ctrlBoolFlag As Boolean
                            'ctrlBoolFlag = updateFlagTicket(idTicket, 2)
                            If ctrlBoolFlag = True Then
                                'TextBoxInfoTicket.Text = messagesInterventi & vbCrLf & "TICKET INVIATO " & vbCrLf
                                ' MessageBox.Show("aggiornato con successo")
                            Else

                            End If
                        End If
                    End If
                End If

            Next

        End If
        If _ctrlTicket = True And _status = "CLOSE" Then
            TextBoxInfoTicket.Text = messagesInterventi
            ' prendo i campi che mi servono per poterli inviare per mail 
            For i = 0 To listaTicketOpen.Rows.Count - 1

                ' scrivo a video cosa succede 

                Dim department As String = listaTicketOpen.Rows(i)(2).ToString()
                Dim idTicket As String = listaTicketOpen.Rows(i)(0).ToString()
                Dim dataClosed As String = listaTicketOpen.Rows(i)(12).ToString()
                Dim plant As String = listaTicketOpen.Rows(i)(16).ToString()
                Dim line As String = listaTicketOpen.Rows(i)(3).ToString()

                Dim station As String = listaTicketOpen.Rows(i)(17).ToString()
                Dim stationNo As String = listaTicketOpen.Rows(i)(18).ToString()
                Dim stationNoInt As String = listaTicketOpen.Rows(i)(1).ToString()
                station = station & " - " & listaTicketOpen.Rows(i)(4).ToString()
                Dim description As String = listaTicketOpen.Rows(i)(9).ToString()
                Dim solution As String = listaTicketOpen.Rows(i)(14).ToString()
                Dim timeWork As String = listaTicketOpen.Rows(i)(20).ToString()
                Dim comments As String = listaTicketOpen.Rows(i)(21).ToString()
                ' creo una lista di mail prendendole sul db 
                Dim listMAilTicket As IList(Of String) = getListMailTicket(department, "0")
                ' se la lista esiste ed' e' diversa da zero comnpongo la mail e la invio
                If listMAilTicket.Count <> 0 Then

                    messagesInterventi = messagesInterventi & "IDTK : " & idTicket & " VALID TIME MAX: " & timeWork & " MIN" & vbCrLf
                    TextBoxInfoTicket.Text = messagesInterventi

                    Dim userSender As String = "TICKET_CLF_ALERT@eu.joysonsafety.com"
                    Dim subject As String = "ALERT CLOSE CL-ID Ticket: " & idTicket & " Data CLOSE : " & dataClosed
                    Dim textMail As String = "ALERT TICKET CLOSE: " & idTicket & "<br>DURATION " & timeWork & " minuti <br>"
                    textMail = textMail & "PLANT :" & plant & "<br>"
                    textMail = textMail & "POSTAZIONE: " & line & " " & station & "<br>"
                    textMail = textMail & "Descrizione Problema : " & description & "<br>"
                    textMail = textMail & "Soluzione Problema : " & solution & "<br>"
                    textMail = textMail & "Commenti : " & comments & "<br>"
                    If CInt(timeWork) >= ini_maxDurationTicket And CInt(timeWork) <= 480 Then
                        listMAilTicket = getListMailTicket(department, "1")
                        If CInt(timeWork) >= 180 And CInt(timeWork) <= 480 Then
                            listMAilTicket.Add(ini_mail_plant_manager)
                        End If

                    End If
                    writeLog.ScriviLog(" [Ticket ]: entrato e inviato il ticket getReminderListMail  " & Now, LogPath, True)
                    ' costruingo la string per il link cliccabile e vef
                    'Dim linkTicket As String = "<a href=""" & ini_urlLink & line & "&STATION_NO=" & stationNo & "&SITE=" & plant & "&STATION_NO_INT=" & stationNoInt & """>OPEN TICKET</a>" & "<br>"
                    'textMail = textMail & vbCrLf & "link Ticket : " & linkTicket & "<br>"
                    'invio la mail
                    Dim ctrlBoolEmailOk As Boolean = sendMailSMTPKSS(userSender, "", subject, textMail, "", "", "", "", listMAilTicket, "ticket")
                    ' se e' staTA inviata aggiorrno i record mettendo i flag a 1 cosi da non doverli reinviare di nuovo
                    If ctrlBoolEmailOk = True Then
                        Dim ctrlBoolFlag As Boolean
                        ctrlBoolFlag = updateFlagTicket(idTicket, 2)
                        If ctrlBoolFlag = True Then
                            'TextBoxInfoTicket.Text = messagesInterventi & vbCrLf & "TICKET INVIATO " & vbCrLf
                            ' MessageBox.Show("aggiornato con successo")
                        Else

                        End If
                    End If
                End If
            Next
        End If
    End Sub

    Sub inviaScreenDash(ByVal type As String, ByVal _listInvio As List(Of String))

        Dim path As String = Nothing
        Dim fileName As String = "MultiScreenDash"
        Dim mittente As String = "prova"
        Dim strTitleMail As String = "prova"
        Dim strTestMail As String = "prova"
        Dim strObjectMail As String = "prova"
        arrayListScreen.Clear()
        'manda email di prova del file log creatoi alla fine del 3 turno NOtte 
        'creo un immagine screenshot 
        Dim dataString As String = Now.ToString("dd_MM_yyyy")
        If type = "Inflator" Then
            path = ini_pathScreenLocal
            mittente = "I.T-Dashboard@eu.joysonsafety.com"
            strTitleMail = "Report DashBoard Shift-End"
            strTestMail = "I.T Report Colleferro in allegato i files Immagine che contengono Dati delle Dashboard in Data : "
            strObjectMail = "Dashboard"
            writeLog.ScriviLog(" 2[inflator]: prendo il type | " & path & " : " & mittente & " : " & strTitleMail & " : " & strTestMail & " : " & strObjectMail & " | ", LogPath, True)
        End If
        If type = "Airbag" Then
            path = ini_pathScreenLocalAirbag
            mittente = "I.T-Dashboard@eu.joysonsafety.com"
            strTitleMail = "Report DashBoard Shift-End"
            strTestMail = "I.T Report Colleferro in allegato i files Immagine che contengono Dati delle Dashboard in Data : "
            strObjectMail = "Dashboard"
            writeLog.ScriviLog(" 2[Airbag]: prendo il type | " & path & " : " & mittente & " : " & strTitleMail & " : " & strTestMail & " : " & strObjectMail & " | ", LogPath, True)
        End If
        If type = "Inflator_Multipanel" Then
            path = ini_pathScreenLocalMultiInf
            mittente = "I.T-Multipanel@eu.joysonsafety.com"
            strTitleMail = "Report Multipanel Shift-End"
            strTestMail = "I.T Report Colleferro in allegato i files Immagine che contengono Dati Multipanel in Data : "
            strObjectMail = "Multipanel"
        End If

        'fileName = "\" & ini_dashname & ini_dashLine & "_turno_" & numeroTurno & "_Data_" & dataString & ".png"

        Dim networkDirectory = "NET USE K: \\341repo01\ /user:"

        Dim boolNetworkAccess = networkLogin(path, ini_server_user, ini_server_pwd, networkDirectory)
        'Dim fileScreen As String = path & fileName
        ' crea e controlla  che sia stato creato 
        'Dim ScreenOk As Boolean = saveScreen(fileScreen)
        Dim txtFiles
        If ini_saveScreen = "Y" Then
            Try
                txtFiles = Directory.GetFiles(path, "*.png", SearchOption.TopDirectoryOnly)

            Catch e As Exception

                MessageBox.Show(e.ToString)

            Finally

            End Try


            For Each filenm As String In txtFiles
                arrayListScreen.Add(filenm)
            Next

            arrayListScreen.Sort()

            txtFiles = Nothing
            ' devo andare a prendere i file e passarli alla mail sotto
        End If
        ' se tutto ok invio mail con il file immagine appena creato in allegato solo se maggiore di 0 i file nella cartella 
        If arrayListScreen.Count > 0 Then

            'manda email di prova del file log creatoi alla fine del 1 turno MAttina 
            sendMailSMTPKSS(mittente, "", strTitleMail,
                        strTestMail & dataNow,
                        fileName, path, "", "", _listInvio, strObjectMail, arrayListScreen) ' la list la creo quando leggo il file ini

        End If

        If boolEmailOk = True Then
            ' pilusco array dei file 
            arrayListScreen.Clear()
            ' chiamo il metodo che sposta i file nella cartella backup dopo averli inviati
            moveDirectoryContent(path, ini_pathScreenBackup)

        End If
        ' cambio lo stato del startDashScreen 
        If type = "Airbag" And startDashScreen = "Y" Then
            startDashScreen = ""
        End If

    End Sub

    Private Sub Form1_Resize(ByVal sender As Object, ByVal e As System.EventArgs) Handles MyBase.Resize
        If Me.WindowState = FormWindowState.Minimized Then
            NotifyIcon1.Visible = True
            Me.Hide()
            NotifyIcon1.BalloonTipText = "In esecuzione"
            'NotifyIcon1.BalloonTipText = "Hi from right system tray"
            NotifyIcon1.ShowBalloonTip(200)
        End If
    End Sub
    Private Sub NotifyIcon1_DoubleClick(ByVal sender As Object, ByVal e As System.EventArgs) Handles NotifyIcon1.DoubleClick
        Me.Show()
        Me.WindowState = FormWindowState.Normal
        xPointForm = Screen.PrimaryScreen.WorkingArea.Width - Me.Width
        yPointForm = Screen.PrimaryScreen.WorkingArea.Height - Me.Height
        Me.Location = New Point(xPointForm, yPointForm)
        NotifyIcon1.Visible = False
    End Sub

    Public Function dataMensa(ByVal _opt As String)
        Dim dataLoad As DataTable = Nothing
        Dim con As MySqlConnection
        Dim strCon As New MySqlConnectionStringBuilder
        'Dim connectionString As String
        Dim sql As String
        Try
            idPrenotazione = getIdPrenotazione(dataNow)
            'idPrenotazione = "13326"
            'prendo la connection builder dalla classe connectionDB 
            strCon = connectionDB()
            'passo la builder come connectionString
            con = New MySqlConnection(strCon.ConnectionString)
            con.Open()
            sql = "SELECT CODE_EMPLOYEE as BADGE,EMPLOYEE,PRIMO,PRIMO2,PRIMO3,SECONDO,SECONDO2,"
            sql = sql & "CONTORNO,CONTORNO2,FRUTTA,YOGURT,DESSERT,BEVANDE,BEVANDE2,GIORNO,TIME_STAMP,PAYMENT FROM prenotazioni WHERE RIGHT(ID_PRENOTAZIONE , 5) = '" & idPrenotazione & "' "
            sql = sql & "AND CODE_EMPLOYEE " & _opt & " ORDER BY EMPLOYEE ASC"
            'sql = "SELECT CODE_EMPLOYEE as BADGE,EMPLOYEE,GOURMET,PRIMO,PRIMO2,PRIMO3,SECONDO,SECONDO2,"
            'sql = sql & "CONTORNO,CONTORNO2,FRUTTA,YOGURT,DESSERT,GIORNO,TIME_STAMP,PAYMENT FROM prenotazioni WHERE RIGHT(ID_PRENOTAZIONE , 5) = '" & idPrenotazione & "' "
            'sql = sql & "AND CODE_EMPLOYEE " & _opt & " ORDER BY EMPLOYEE ASC"
            Dim DataAdapter As New MySqlDataAdapter(sql, con)
            Dim dt As New DataTable("prenotazioni")
            DataAdapter.Fill(dt)
            dataLoad = dt
            con.Close()

            'MsgBox("Data has been successfully exported!")

        Catch myerror As Exception
            MessageBox.Show(myerror.Message)
        End Try

        Return dataLoad
        messages = messages & Now & " Prelevo i Dati dal DB" & vbCrLf
        TextBox1.Text = messages
    End Function

    Private Sub createXlsDataProdTotInf(ByVal nameFile As String, ByVal DataName As String, ByVal dt As DataTable)

        messagesProdTotInf = messagesProdTotInf & Now & " Creo il File Excel Production Inflator" & vbCrLf
        TextBoxDayProdPck.Text = messagesProdTotInf

        Dim dFilqNameXlsDataProd = Replace(DataName, "/", "_")
        'Dim dt As System.Data.DataTable = Nothing
        Dim dc As System.Data.DataColumn = Nothing
        Dim dr As System.Data.DataRow = Nothing
        Dim colIndex As Integer = 0
        Dim rowIndex As Integer = 1
        Dim val As String = Nothing
        Dim valBadge As String = Nothing
        ' serve per prendere le colonne e formattarle 
        Dim _excel As New Microsoft.Office.Interop.Excel.Application
        Dim wBook As Microsoft.Office.Interop.Excel.Workbook
        Dim wSheet As Microsoft.Office.Interop.Excel.Worksheet
        Dim dataMese As String = Nothing
        Dim nomeMese As String = Nothing
        wBook = _excel.Workbooks.Add()
        'Dim wSheet2 As Excel.Worksheet = CType(wBook.Worksheets(2), Excel.Worksheet)
        ' Dim wSheet2 As Excel.Worksheet
        wSheet = wBook.Worksheets.Add
        ' wSheet2 = wBook.Worksheets.Add

        'wSheet2 = wBook.Worksheets.Add

        'Dim i As Integer
        'For i = 1 To 2 Step 1
        '    Dim optOspiti As String
        'Add a worksheet to the workbook.
        wSheet = CType(wBook.Worksheets.Add(Missing.Value, Missing.Value, Missing.Value, Missing.Value), Excel.Worksheet)

        'Name the sheet.
        ' If i = 1 Then
        'optOspiti = "=" & "'OSPITE'"
        'dt = dataMensa(optOspiti)
        wSheet.Name = "CLF"
        wSheet = wBook.ActiveSheet()
        If dt.Rows.Count > 0 Then
            For Each dc In dt.Columns
                colIndex = colIndex + 1

                _excel.Cells(2, colIndex) = dc.ColumnName
                With wSheet.Cells.Item(2, colIndex)
                    wSheet.Cells.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                    .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                    .Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                    .Font.size = 11
                    .Font.Bold = True
                    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                End With
            Next
            ' serve a prendere le cell e metterle sotto le colonne  e dare i bordi e le formattazioni 
            rowIndex = rowIndex + 3
            For Each dr In dt.Rows
                rowIndex = rowIndex + 1
                colIndex = 0
                For Each dc In dt.Columns
                    colIndex = colIndex + 1
                    _excel.Cells(rowIndex + 1, colIndex) = dr(dc.ColumnName)

                    If colIndex = 13 Then

                        nomeMese = UCase(wSheet.Cells.Item(rowIndex + 1, 13).value.ToString)

                    End If

                    If colIndex = 6 Then

                        'wSheet2.Cells.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter

                        ' wSheet2.Range("A1:O1").Merge()
                        'dataMese = wSheet2.Cells.Item(rowIndex + 1, 14).value.ToString
                        'dataMese = Convert.ToDateTime(dataMese).ToString("dd/MM/yyyy")
                        wSheet.Range("A1").Value = "Date: " & DataName
                        wSheet.Range("A1").Borders.LineStyle = Excel.XlLineStyle.xlLineStyleNone
                        ' wSheet2.Range("A1").Borders.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                        wSheet.Range("A1").Interior.Color = RGB(255, 255, 0)
                        wSheet.Range("A1").Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                        wSheet.Range("A1").Font.Size = 11
                        wSheet.Range("A1").Font.Bold = False


                        wSheet.Range("A2").Value = "Machine"
                        wSheet.Range("B2").Value = "HPH - D"
                        wSheet.Range("C2").Value = "HPH - J"
                        wSheet.Range("D2").Value = "CGI - 1"
                        wSheet.Range("E2").Value = "CGI - 2"
                        wSheet.Range("F2").Value = "ACI - 4"
                        wSheet.Range("G2").Value = "ACI2 - 5"

                        wSheet.Range("A2:G2").HorizontalAlignment = Excel.XlHAlign.xlHAlignLeft
                        wSheet.Range("A2:G2").Borders.LineStyle = Excel.XlLineStyle.xlLineStyleNone
                        'wSheet2.Range("A2:F2").Borders.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                        wSheet.Range("A2:G2").Interior.Color = RGB(255, 192, 0)
                        wSheet.Range("A2:G2").Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                        wSheet.Range("A2:G2").Font.Size = 11
                        wSheet.Range("A2:G2").Font.Bold = False

                        wSheet.Range("A3").HorizontalAlignment = Excel.XlHAlign.xlHAlignLeft
                        wSheet.Range("A3").Value = "Operational Max/hour:"

                        wSheet.Range("B3").Value = "185"
                        wSheet.Range("C3").Value = "237"
                        wSheet.Range("D3").Value = "170"
                        wSheet.Range("E3").Value = "170"
                        wSheet.Range("F3").Value = "370"
                        wSheet.Range("G3").Value = "370"

                        wSheet.Range("B3:G3").HorizontalAlignment = Excel.XlHAlign.xlHAlignRight
                        wSheet.Range("A3:G3").Borders.LineStyle = Excel.XlLineStyle.xlLineStyleNone
                        ' wSheet2.Range("A3:F3").Borders.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                        ' wSheet.Range("A3:F3").Interior.Color = System.Drawing.ColorTranslator.ToOle(Color.Green)
                        wSheet.Range("A3:G3").Interior.Color = RGB(146, 208, 80)
                        wSheet.Range("A3:G3").Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                        wSheet.Range("A3:G3").Font.Size = 11
                        wSheet.Range("A3:G3").Font.Bold = False
                        ' qui c'e' un riga vuota 

                        wSheet.Range("A5").HorizontalAlignment = Excel.XlHAlign.xlHAlignLeft
                        wSheet.Range("A5").Value = "Hours: "

                        wSheet.Range("A5").Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                        wSheet.Range("A5").Borders.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                        wSheet.Range("A5").Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.White)
                        wSheet.Range("A5").Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                        wSheet.Range("A5").Font.Size = 11
                        wSheet.Range("A5").Font.Bold = False

                        ' ULTIMA RIGA CONTENENTE I TOTALI 
                        wSheet.Range("A31").HorizontalAlignment = Excel.XlHAlign.xlHAlignLeft
                        wSheet.Range("A31").Value = "Totals"

                        wSheet.Range("B31:G31").HorizontalAlignment = Excel.XlHAlign.xlHAlignRight
                        wSheet.Range("A31:G31").Borders.LineStyle = Excel.XlLineStyle.xlLineStyleNone
                        ' wSheet2.Range("A3:F3").Borders.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                        ' wSheet.Range("A3:F3").Interior.Color = System.Drawing.ColorTranslator.ToOle(Color.Green)
                        wSheet.Range("A31:G31").Interior.Color = RGB(146, 208, 80)
                        wSheet.Range("A31:G31").Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                        wSheet.Range("A31:G31").Font.Size = 11
                        wSheet.Range("A31:G31").Font.Bold = True
                        'CALCOLA LA SOMMA DELLE COLONNE 
                        Dim sumB, sumC, sumD, sumE, sumF, sumG As Double

                        sumB = _excel.WorksheetFunction.Sum(wSheet.Range("B6:B29"))
                        sumC = _excel.WorksheetFunction.Sum(wSheet.Range("C6:C29"))
                        sumD = _excel.WorksheetFunction.Sum(wSheet.Range("D6:D29"))
                        sumE = _excel.WorksheetFunction.Sum(wSheet.Range("E6:E29"))
                        sumF = _excel.WorksheetFunction.Sum(wSheet.Range("F6:F29"))
                        sumG = _excel.WorksheetFunction.Sum(wSheet.Range("G6:G29"))

                        wSheet.Range("B31").Value = sumB.ToString
                        wSheet.Range("C31").Value = sumC.ToString
                        wSheet.Range("D31").Value = sumD.ToString
                        wSheet.Range("E31").Value = sumE.ToString
                        wSheet.Range("F31").Value = sumF.ToString
                        wSheet.Range("G31").Value = sumG.ToString
                    End If

                    With wSheet.Cells.Item(rowIndex + 1, colIndex)
                        'bordi e align delle celle
                        .HorizontalAlignment = Excel.XlHAlign.xlHAlignRight
                        .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                        .Borders.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                        .Font.Size = 11
                        .Font.Bold = False
                        Try
                            'associo il valore della cell rows e la coloro come voglio solo se non e' null
                            val = wSheet.Cells.Item(rowIndex + 1, colIndex).value.ToString
                            valBadge = wSheet.Cells.Item(rowIndex + 1, colIndex).value.ToString
                            If val <> "" Then
                                If colIndex = 1 Then
                                    .HorizontalAlignment = Excel.XlHAlign.xlHAlignLeft
                                Else
                                    .HorizontalAlignment = Excel.XlHAlign.xlHAlignRight
                                End If
                                'wSheet.Range("A:A").HorizontalAlignment = Excel.XlHAlign.xlHAlignLeft

                                '.Font.Bold = True
                                'If val <> "" Then

                                '    ''.Font.Bold = True
                                '    'If val = "A" Or val = "Ax2" Or val = "B" Or val = "Bx2" Or val = "C" Or val = "Cx2" Then
                                '    '    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.PapayaWhip)
                                '    'End If
                                '    'If val = "D" Or val = "E" Or val = "F" Then
                                '    '    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Aqua)
                                '    'End If
                                '    'If val = "G" Or val = "H" Or val = "Gx2" Or val = "Hx2" Then
                                '    '    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.LimeGreen)
                                '    'End If
                                '    'If val = "I" Or val = "Ix2" Or val = "L" Then
                                '    '    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.SaddleBrown)
                                '    'End If
                                '    'If val = "P" Then
                                '    '    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Yellow)
                                '    'End If

                                'End If
                            End If
                        Catch e As Exception
                            val = ""
                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.White)
                        End Try

                    End With

                Next
            Next

            wSheet.Columns.AutoFit()

            ' wSheet.Range("A1:O1").Merge()

            'faccio il merge del campo SECONDO
            ' wSheet2.Range("G2:H2").Clear()
            'wSheet2.Range("F2:G2:H2").Merge()

            'faccio il merge del campo CONTORNO
            'wSheet2.Range("J2").Clear()
            ' wSheet2.Range("I2:J2").Merge()
            'faccio il merge del campo PRIMO

            'wSheet2.Range("D2:E2").Clear()
            'wSheet2.Range("C2:D2:E2").Merge()

            messagesProdTotInf = messagesProdTotInf & Now & " File Excel Prod Tot Inflator Successo " & rowIndex - 1 & " record" & vbCrLf
            TextBoxDayProdPck.Text = messagesProdTotInf
            Me.NotifyIcon1.BalloonTipText = " File Excel Prod Tot Inflator Creato con Successo " & rowIndex - 1 & " record"
            NotifyIcon1.ShowBalloonTip(300)
            'resetto le colonne e le righe
            colIndex = 0
            rowIndex = 1

            'optOspiti = ""
            dt = Nothing
            dr = Nothing
            dc = Nothing
        End If
        ' End If
        'Next


        'formatto la pagina xls 
        nameFile = ini_nameFileProd & "_" & dFilqNameXlsDataProd & ini_fileFormat
        ' path per creare e salvare il file 
        Dim strFileName As String = ini_pathFileProd & nameFile
        If System.IO.File.Exists(strFileName) Then
            System.IO.File.Delete(strFileName)
        End If
        'salvo nella directory
        wBook.SaveAs(strFileName)
        'chiudo il file
        wBook.Close()
        'chiudo excell

        _excel.Quit()
        'serve ad inviare una mail 

        ' classe che accetta argomenti per mandare inf turno 
        messagesProdTotInf = messagesProdTotInf & Now & " Invio il file Creato per Email" & vbCrLf
        TextBoxDayProdPck.Text = messagesProdTotInf

        sendMailSMTPKSS("CLF-JSS@eu.eu.joysonsafety.com", "", "Hourly Production Reports CLF",
                        "attachment file content data production CLF Inflator",
                         nameFile, ini_pathFileProd, "", "", listInvioProdTotInf, "") ' la list la creo quando leggo il file ini

        'sendMailSMTPKSS("Ordini-mensa-KSS@CLFSmtp.com", "", "prova smtp Colleferro", _
        '                "prova Smtp invio mail ssl", _
        '                 nameFile, ini_pathSave, "", "", listInvioMail, "mensa") ' la list la creo quando leggo il file ini


    End Sub
    'creo il file mensa compreso gli ospiti
    Private Sub DatatableToExcel()

        messages = messages & Now & " Creo il File Excel" & vbCrLf
        TextBox1.Text = messages
        Dim dt As System.Data.DataTable = Nothing
        Dim dc As System.Data.DataColumn = Nothing
        Dim dr As System.Data.DataRow = Nothing
        Dim colIndex As Integer = 0
        Dim rowIndex As Integer = 1
        Dim val As String = Nothing
        Dim valBadge As String = Nothing
        ' serve per prendere le colonne e formattarle 
        Dim _excel As New Microsoft.Office.Interop.Excel.Application
        Dim wBook As Microsoft.Office.Interop.Excel.Workbook
        Dim wSheet As Microsoft.Office.Interop.Excel.Worksheet
        Dim dataMese As String = Nothing
        Dim nomeMese As String = Nothing
        wBook = _excel.Workbooks.Add()
        'Dim wSheet2 As Excel.Worksheet = CType(wBook.Worksheets(2), Excel.Worksheet)
        Dim wSheet2 As Excel.Worksheet
        wSheet = wBook.Worksheets.Add
        wSheet2 = wBook.Worksheets.Add

        'SETTO LE VARIABILI CE CONTENGONO I TOTALI PARZIALI DEI PASTI SCELTI LUCA 02/02/2023
        Dim valATotal As Int32 = 0
        Dim valAX2Total As Int32 = 0
        Dim valBTotal As Int32 = 0
        Dim valBX2Total As Int32 = 0
        Dim valCTotal As Int32 = 0
        Dim valCX2Total As Int32 = 0
        Dim valDTotal As Int32 = 0
        Dim valDX2Total As Int32 = 0
        Dim valETotal As Int32 = 0
        Dim valEX2Total As Int32 = 0
        Dim valFTotal As Int32 = 0
        Dim valFX2Total As Int32 = 0
        Dim valGTotal As Int32 = 0
        Dim valGX2Total As Int32 = 0
        Dim valHTotal As Int32 = 0
        Dim valHX2Total As Int32 = 0
        Dim valITotal As Int32 = 0
        Dim valIX2Total As Int32 = 0
        Dim valLTotal As Int32 = 0
        Dim valLX2Total As Int32 = 0
        Dim valMTotal As Int32 = 0
        Dim valMX2Total As Int32 = 0
        Dim valNTotal As Int32 = 0
        Dim valNX2Total As Int32 = 0
        Dim valPTotal As Int32 = 0
        'wSheet2 = wBook.Worksheets.Add

        Dim i As Integer
        For i = 1 To 2 Step 1
            Dim optOspiti As String
            'Add a worksheet to the workbook.
            wSheet = CType(wBook.Worksheets.Add(Missing.Value, Missing.Value, Missing.Value, Missing.Value), Excel.Worksheet)

            'Name the sheet.
            If i = 1 Then
                optOspiti = "=" & "'OSPITE'"
                dt = dataMensa(optOspiti)

                wSheet.Name = "Ospiti"
                wSheet2 = wBook.ActiveSheet()
                If dt.Rows.Count > 0 Then
                    For Each dc In dt.Columns
                        colIndex = colIndex + 1

                        _excel.Cells(2, colIndex) = dc.ColumnName
                        With wSheet2.Cells.Item(2, colIndex)
                            wSheet2.Cells.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                            .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                            .Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                            .Font.size = 11
                            .Font.Bold = True
                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                        End With
                    Next
                    ' serve a prendere le cell e metterle sotto le colonne  e dare i bordi e le formattazioni 
                    For Each dr In dt.Rows

                        rowIndex = rowIndex + 1
                        colIndex = 0
                        For Each dc In dt.Columns
                            colIndex = colIndex + 1
                            _excel.Cells(rowIndex + 1, colIndex) = dr(dc.ColumnName)

                            If colIndex = 15 Then

                                nomeMese = UCase(wSheet2.Cells.Item(rowIndex + 1, 15).value.ToString)

                            End If

                            If colIndex = 16 Then

                                wSheet2.Cells.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                                wSheet2.Range("A1:Q1").Merge()
                                dataMese = wSheet2.Cells.Item(rowIndex + 1, 16).value.ToString
                                dataMese = Convert.ToDateTime(dataMese).ToString("dd/MM/yyyy")
                                wSheet2.Range("A1:Q1").Value = "PRENOTAZIONE MENSA " & nomeMese & " " & dataMese & " TOT. " & dt.Rows.Count.ToString
                                wSheet2.Range("A1:Q1").Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                                wSheet2.Range("A1:Q1").Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.YellowGreen)
                                wSheet2.Range("A1:Q1").Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                                wSheet2.Range("A1:Q1").Font.Size = 12
                                wSheet2.Range("A1:Q1").Font.Bold = True

                            End If

                            With wSheet2.Cells.Item(rowIndex + 1, colIndex)
                                'bordi e align delle celle
                                .HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                                .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                                Try
                                    'associo il valore della cell rows e la coloro come voglio solo se non e' null

                                    'If String.IsNullOrEmpty(wSheet2.Cells.Item(rowIndex + 1, colIndex).value) = False Then
                                    '    .Font.Bold = True
                                    '    val = wSheet2.Cells.Item(rowIndex + 1, colIndex).value.ToString

                                    '    valBadge = wSheet2.Cells.Item(rowIndex + 1, colIndex).value.ToString
                                    '    'If val <> "" Then
                                    '    .Font.Bold = True
                                    '    If val = "A" Or val = "Ax2" Or val = "B" Or val = "Bx2" Or val = "C" Or val = "Cx2" Or val = "D" Or val = "Dx2" Then
                                    '        .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.PapayaWhip)
                                    '    End If
                                    '    If val = "E" Or val = "Ex2" Or val = "F" Or val = "Fx2" Then
                                    '        .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Aqua)
                                    '    End If
                                    '    If val = "G" Or val = "H" Or val = "Gx2" Or val = "Hx2" Then
                                    '        .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.LimeGreen)
                                    '    End If
                                    '    If val = "I" Or val = "Ix2" Or val = "L" Or val = "Lx2" Or val = "M" Or val = "Mx2" Then
                                    '        .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.SaddleBrown)
                                    '    End If
                                    '    If val = "P" Then
                                    '        .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Yellow)
                                    '    End If

                                    '    'End If
                                    'End If
                                    If String.IsNullOrEmpty(wSheet.Cells.Item(rowIndex + 1, colIndex).value) = False Then
                                        'Do something
                                        val = wSheet.Cells.Item(rowIndex + 1, colIndex).value
                                        valBadge = wSheet2.Cells.Item(rowIndex + 1, colIndex).value.ToString
                                        'If val <> "" Then
                                        .Font.Bold = True
                                        If val = "A" Or val = "Ax2" Or val = "B" Or val = "Bx2" Or val = "C" Or val = "Cx2" Or val = "D" Or val = "Dx2" Or val = "E" Or val = "Ex2" Then
                                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.PapayaWhip)
                                        End If
                                        If val = "F" Or val = "Fx2" Or val = "G" Or val = "H" Or val = "Gx2" Or val = "Hx2" Or val = "I" Or val = "Ix2" Or val = "L" Or val = "Lx2" Then
                                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.LimeGreen)
                                        End If
                                        'If  Then
                                        '    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.LimeGreen)
                                        'End If
                                        If val = "M" Or val = "Mx2" Or val = "N" Or val = "Nx2" Then
                                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Aqua)
                                        End If
                                        If val = "P" Then
                                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Yellow)
                                        End If

                                        'End If
                                    End If
                                    If val <> "" Then

                                    End If
                                Catch e As Exception
                                    val = ""
                                    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.White)
                                End Try

                            End With

                        Next
                    Next

                    wSheet2.Columns.AutoFit()

                    ' wSheet.Range("A1:O1").Merge()

                    'faccio il merge del campo SECONDO
                    wSheet.Range("G2").Clear()
                    wSheet.Range("F2:G2").Merge()

                    'faccio il merge del campo CONTORNO
                    wSheet.Range("I2").Clear()
                    wSheet.Range("H2:I2").Merge()

                    'faccio il merge del campo PRIMO
                    wSheet.Range("D2:E2").Clear()
                    wSheet.Range("C2:D2:E2").Merge()

                    messages = messages & Now & " File Excel Ospiti Creato con Successo " & rowIndex - 1 & " record" & vbCrLf
                    TextBox1.Text = messages
                    Me.NotifyIcon1.BalloonTipText = " File Excel Ospiti Creato con Successo " & rowIndex - 1 & " record"
                    NotifyIcon1.ShowBalloonTip(300)
                    'resetto le colonne e le righe
                    colIndex = 0
                    rowIndex = 1

                    optOspiti = ""
                    dt = Nothing
                    dr = Nothing
                    dc = Nothing
                End If
            End If



            If i = 2 Then

                optOspiti = "<>" & "'OSPITE'"
                dt = dataMensa(optOspiti)

                wSheet.Name = "Dipendenti"
                wSheet = wBook.ActiveSheet()
                If dt.Rows.Count > 0 Then
                    For Each dc In dt.Columns
                        colIndex = colIndex + 1

                        _excel.Cells(2, colIndex) = dc.ColumnName
                        With wSheet.Cells.Item(2, colIndex)
                            wSheet.Cells.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                            .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                            .Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                            .Font.size = 11
                            .Font.Bold = True
                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                        End With
                    Next
                    ' serve a prendere le cell e metterle sotto le colonne  e dare i bordi e le formattazioni 
                    For Each dr In dt.Rows

                        rowIndex = rowIndex + 1
                        colIndex = 0
                        For Each dc In dt.Columns
                            colIndex = colIndex + 1
                            _excel.Cells(rowIndex + 1, colIndex) = dr(dc.ColumnName)

                            If colIndex = 15 Then

                                nomeMese = UCase(wSheet.Cells.Item(rowIndex + 1, 15).value.ToString)

                            End If

                            If colIndex = 16 Then

                                wSheet.Cells.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                                wSheet.Range("A1:Q1").Merge()
                                dataMese = wSheet.Cells.Item(rowIndex + 1, 16).value.ToString
                                dataMese = Convert.ToDateTime(dataMese).ToString("dd/MM/yyyy")
                                wSheet.Range("A1:Q1").Value = "PRENOTAZIONE MENSA " & nomeMese & " " & dataMese & " TOT. " & dt.Rows.Count.ToString
                                wSheet.Range("A1:Q1").Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                                wSheet.Range("A1:Q1").Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.YellowGreen)
                                wSheet.Range("A1:Q1").Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                                wSheet.Range("A1:Q1").Font.Size = 12
                                wSheet.Range("A1:Q1").Font.Bold = True

                            End If


                            With wSheet.Cells.Item(rowIndex + 1, colIndex)
                                'bordi e align delle celle
                                .HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                                .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                                Try
                                    'associo il valore della cell rows e la coloro come voglio solo se non e' null
                                    'val = wSheet.Cells.Item(rowIndex + 1, colIndex).value
                                    If String.IsNullOrEmpty(wSheet.Cells.Item(rowIndex + 1, colIndex).value) = False Then
                                        'Do something
                                        val = wSheet.Cells.Item(rowIndex + 1, colIndex).value
                                        'If val <> "" Then
                                        .Font.Bold = True
                                        If val = "A" Or val = "Ax2" Or val = "B" Or val = "Bx2" Or val = "C" Or val = "Cx2" Or val = "D" Or val = "Dx2" Or val = "E" Or val = "Ex2" Then
                                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.PapayaWhip)

                                            If val = "A" Then
                                                valATotal = valATotal + 1
                                            End If

                                            If val = "Ax2" Then
                                                valAX2Total = valAX2Total + 2
                                            End If

                                            If val = "B" Then
                                                valBTotal = valBTotal + 1
                                            End If

                                            If val = "Bx2" Then
                                                valBX2Total = valBX2Total + 2
                                            End If

                                            If val = "C" Then
                                                valCTotal = valCTotal + 1
                                            End If

                                            If val = "Cx2" Then
                                                valCX2Total = valCX2Total + 2
                                            End If

                                            If val = "D" Then
                                                valDTotal = valDTotal + 1
                                            End If

                                            If val = "Dx2" Then
                                                valDX2Total = valDX2Total + 2
                                            End If

                                            If val = "E" Then
                                                valETotal = valETotal + 1
                                            End If
                                            If val = "Ex2" Then
                                                valEX2Total = valEX2Total + 2
                                            End If

                                        End If

                                        If val = "F" Or val = "Fx2" Or val = "G" Or val = "H" Or val = "Gx2" Or val = "Hx2" Or val = "I" Or val = "Ix2" Or val = "L" Or val = "Lx2" Then
                                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.LimeGreen)

                                            If val = "F" Then
                                                valFTotal = valFTotal + 1
                                            End If

                                            If val = "Fx2" Then
                                                valFX2Total = valFX2Total + 2
                                            End If

                                            If val = "G" Then
                                                valGTotal = valGTotal + 1
                                            End If

                                            If val = "Gx2" Then
                                                valGX2Total = valGX2Total + 2
                                            End If

                                            If val = "H" Then
                                                valHTotal = valHTotal + 1
                                            End If

                                            If val = "Hx2" Then
                                                valHX2Total = valHX2Total + 2
                                            End If

                                            If val = "I" Then
                                                valITotal = valITotal + 1
                                            End If

                                            If val = "Ix2" Then
                                                valIX2Total = valIX2Total + 2
                                            End If

                                            If val = "L" Then
                                                valLTotal = valLTotal + 1
                                            End If

                                            If val = "Lx2" Then
                                                valLX2Total = valLX2Total + 2
                                            End If

                                        End If
                                        'If  Then
                                        '    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.LimeGreen)
                                        'End If
                                        If val = "M" Or val = "Mx2" Or val = "N" Or val = "Nx2" Then
                                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Aqua)

                                            If val = "M" Then
                                                valMTotal = valMTotal + 1
                                            End If
                                            If val = "Mx2" Then
                                                valMX2Total = valMX2Total + 2
                                            End If

                                            If val = "N" Then
                                                valNTotal = valNTotal + 1
                                            End If
                                            If val = "Nx2" Then
                                                valNX2Total = valNX2Total + 2
                                            End If

                                        End If
                                        If val = "P" Then
                                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Yellow)

                                            valPTotal = valPTotal + 1
                                        End If

                                        'End If
                                    End If

                                Catch e As Exception
                                    val = ""
                                    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.White)
                                End Try

                            End With

                        Next

                    Next

                    'formatto la pagina xls 
                    wSheet.Columns.AutoFit()

                    ' wSheet.Range("A1:M1").Merge()

                    'faccio il merge del campo SECONDO
                    wSheet.Range("G2").Clear()
                    wSheet.Range("F2:G2").Merge()

                    'faccio il merge del campo CONTORNO
                    wSheet.Range("I2").Clear()
                    wSheet.Range("H2:I2").Merge()

                    'faccio il merge del campo PRIMO
                    wSheet.Range("D2:E2").Clear()
                    wSheet.Range("C2:D2:E2").Merge()

                    messages = messages & Now & " File Excel Dipendenti Creato con Successo " & rowIndex - 1 & " record" & vbCrLf
                    TextBox1.Text = messages
                    Me.NotifyIcon1.BalloonTipText = " File Excel Dipendenti Creato con Successo " & rowIndex - 1 & " record"
                    NotifyIcon1.ShowBalloonTip(300)


                    ' CREA INTESTAZIONE DEI TOTALI  LUCA 02/02/2023
                    wSheet.Cells.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter


                    Dim cellnA1 As String = "A" & rowIndex + 3
                    Dim cellnB1 As String = "B" & rowIndex + 3
                    Dim rangeAB1 As String = cellnA1 & ":" & cellnB1
                    wSheet.Range(rangeAB1).Merge()

                    wSheet.Range("A" & rowIndex + 3 & ":" & "B" & rowIndex + 3).Value = "TOT. PER TIPO"
                    wSheet.Range("A" & rowIndex + 3 & ":" & "B" & rowIndex + 3).Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                    wSheet.Range("A" & rowIndex + 3 & ":" & "B" & rowIndex + 3).Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.YellowGreen)
                    wSheet.Range("A" & rowIndex + 3 & ":" & "B" & rowIndex + 3).Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                    wSheet.Range("A" & rowIndex + 3 & ":" & "B" & rowIndex + 3).Font.Size = 12
                    wSheet.Range("A" & rowIndex + 3 & ":" & "B" & rowIndex + 3).Font.Bold = True

                    wSheet.Range("C" & rowIndex + 3).Value = "A"
                    wSheet.Range("D" & rowIndex + 3).Value = "B"
                    wSheet.Range("E" & rowIndex + 3).Value = "C"
                    wSheet.Range("F" & rowIndex + 3).Value = "D"
                    wSheet.Range("G" & rowIndex + 3).Value = "E"
                    wSheet.Range("H" & rowIndex + 3).Value = "F"
                    wSheet.Range("I" & rowIndex + 3).Value = "G"
                    wSheet.Range("J" & rowIndex + 3).Value = "H"
                    wSheet.Range("K" & rowIndex + 3).Value = "I"
                    wSheet.Range("L" & rowIndex + 3).Value = "L"
                    wSheet.Range("M" & rowIndex + 3).Value = "M"
                    wSheet.Range("N" & rowIndex + 3).Value = "N"
                    wSheet.Range("O" & rowIndex + 3).Value = "GIORNO"
                    wSheet.Range("P" & rowIndex + 3).Value = "DATA"
                    wSheet.Range("Q" & rowIndex + 3).Value = "PAGANTI"

                    wSheet.Range("C" & rowIndex + 3 & ":" & "Q" & rowIndex + 3).Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                    wSheet.Range("C" & rowIndex + 3 & ":" & "Q" & rowIndex + 3).Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.YellowGreen)
                    wSheet.Range("C" & rowIndex + 3 & ":" & "Q" & rowIndex + 3).Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                    wSheet.Range("C" & rowIndex + 3 & ":" & "Q" & rowIndex + 3).Font.Size = 12
                    wSheet.Range("C" & rowIndex + 3 & ":" & "Q" & rowIndex + 3).Font.Bold = True


                    ' CREA I TOTIALI IN NUMERO  LUCA 02/02/2023
                    Dim cellnA As String = "A" & rowIndex + 4
                    Dim cellnB As String = "B" & rowIndex + 4
                    Dim rangeAB As String = cellnA & ":" & cellnB
                    wSheet.Range(rangeAB).Merge()
                    wSheet.Range("A" & rowIndex + 4 & ":" & "B" & rowIndex + 4).Value = ""
                    wSheet.Range("A" & rowIndex + 4 & ":" & "B" & rowIndex + 4).Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                    wSheet.Range("A" & rowIndex + 4 & ":" & "B" & rowIndex + 4).Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Silver)
                    wSheet.Range("A" & rowIndex + 4 & ":" & "B" & rowIndex + 4).Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                    wSheet.Range("A" & rowIndex + 4 & ":" & "B" & rowIndex + 4).Font.Size = 12
                    wSheet.Range("A" & rowIndex + 4 & ":" & "B" & rowIndex + 4).Font.Bold = True

                    wSheet.Range("C" & rowIndex + 4).Value = valATotal + valAX2Total
                    wSheet.Range("D" & rowIndex + 4).Value = valBTotal + valBX2Total
                    wSheet.Range("E" & rowIndex + 4).Value = valCTotal + valCX2Total
                    wSheet.Range("F" & rowIndex + 4).Value = valDTotal + valDX2Total
                    wSheet.Range("G" & rowIndex + 4).Value = valETotal + valEX2Total
                    wSheet.Range("H" & rowIndex + 4).Value = valFTotal + valFX2Total
                    wSheet.Range("I" & rowIndex + 4).Value = valGTotal + valGX2Total
                    wSheet.Range("J" & rowIndex + 4).Value = valHTotal + valHX2Total
                    wSheet.Range("K" & rowIndex + 4).Value = valITotal + valIX2Total
                    wSheet.Range("L" & rowIndex + 4).Value = valLTotal + valLX2Total
                    wSheet.Range("M" & rowIndex + 4).Value = valMTotal + valMX2Total
                    wSheet.Range("N" & rowIndex + 4).Value = valNTotal + valNX2Total
                    wSheet.Range("O" & rowIndex + 4).Value = nomeMese
                    wSheet.Range("P" & rowIndex + 4).Value = dataMese
                    wSheet.Range("Q" & rowIndex + 4).Value = valPTotal

                    wSheet.Range("C" & rowIndex + 4 & ":" & "Q" & rowIndex + 4).Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                    wSheet.Range("C" & rowIndex + 4 & ":" & "Q" & rowIndex + 4).Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Silver)
                    wSheet.Range("C" & rowIndex + 4 & ":" & "Q" & rowIndex + 4).Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                    wSheet.Range("C" & rowIndex + 4 & ":" & "Q" & rowIndex + 4).Font.Size = 12
                    wSheet.Range("C" & rowIndex + 4 & ":" & "Q" & rowIndex + 4).Font.Bold = True


                    colIndex = 0
                    rowIndex = 1

                    optOspiti = ""
                    dt = Nothing
                    dr = Nothing
                    dc = Nothing

                End If


            End If
        Next

        'formatto la pagina xls  
        Dim nameFile As String = ini_nomeFile & "_" & idPrenotazione & ini_fileFormat
        ' path per creare e salvare il file 
        Dim strFileName As String = ini_pathSave & nameFile
        If System.IO.File.Exists(strFileName) Then
            System.IO.File.Delete(strFileName)
        End If
        'salvo nella directory
        wBook.SaveAs(strFileName)
        'chiudo il file
        wBook.Close()
        'chiudo excell

        _excel.Quit()
        'serve ad inviare una mail 

        ' classe che accetta argomenti per mandare inf turno 
        messages = messages & Now & " Invio il file Creato per Email" & vbCrLf
        TextBox1.Text = messages

        sendMailSMTPKSS("Ordini-mensa@eu.joysonsafety.com", "", "ordine pasti JOYSONSAFETY",
                        "Ciao siamo la JoysonSafety in allegato il file in excel che contiente gli ordini del giorno",
                         nameFile, ini_pathSave, "", "", listInvioMail, "mensa") ' la list la creo quando leggo il file ini

        'sendMailSMTPKSS("Ordini-mensa-KSS@CLFSmtp.com", "", "prova smtp Colleferro", _
        '                "prova Smtp invio mail ssl", _
        '                 nameFile, ini_pathSave, "", "", listInvioMail, "mensa") ' la list la creo quando leggo il file ini
    End Sub
    'creo il file tibrature mensa 
    Private Sub DatatableToExcelTimbratureMensa(ByVal nameFile As String, ByVal dt As DataTable)

        messages = messages & Now & " Creo il File Excel Timbrature Mensa" & vbCrLf
        TextBox1.Text = messages
        Dim dFileTimbrature = nameFile
        'Dim dt As System.Data.DataTable = Nothing
        Dim dc As System.Data.DataColumn = Nothing
        Dim dr As System.Data.DataRow = Nothing
        Dim colIndex As Integer = 0
        Dim rowIndex As Integer = 1
        Dim val As String = Nothing
        Dim valBadge As String = Nothing
        ' serve per prendere le colonne e formattarle 
        Dim _excel As New Microsoft.Office.Interop.Excel.Application
        Dim wBook As Microsoft.Office.Interop.Excel.Workbook
        Dim wSheet As Microsoft.Office.Interop.Excel.Worksheet
        Dim dataMese As String = Nothing
        Dim nomeMese As String = Nothing
        wBook = _excel.Workbooks.Add()

        wSheet = wBook.Worksheets.Add

        wSheet = CType(wBook.Worksheets.Add(Missing.Value, Missing.Value, Missing.Value, Missing.Value), Excel.Worksheet)

        wSheet.Name = "Timbrature"
        wSheet.Cells.Font.Size = 8
        wSheet = wBook.ActiveSheet()
        If dt.Rows.Count > 0 Then
            For Each dc In dt.Columns
                colIndex = colIndex + 1

                _excel.Cells(1, colIndex) = dc.ColumnName
                With wSheet.Cells.Item(1, colIndex)
                    wSheet.Cells.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                    .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                    .Font.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Black)
                    .Font.size = 8
                    .Font.Bold = True
                    .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                End With
            Next
            ' serve a prendere le cell e metterle sotto le colonne  e dare i bordi e le formattazioni 
            rowIndex = rowIndex + 0
            For Each dr In dt.Rows
                rowIndex = rowIndex + 1
                colIndex = 0
                For Each dc In dt.Columns
                    colIndex = colIndex + 1
                    _excel.Cells(rowIndex, colIndex) = dr(dc.ColumnName)

                    'If colIndex = 13 Then
                    '    'UCase(wSheet.Cells.Item(rowIndex + 1, 13).value.ToString)
                    '    nomeMese = "1"

                    'End If

                    If colIndex = 3 Then

                        wSheet.Cells.HorizontalAlignment = Excel.XlHAlign.xlHAlignRight

                    End If

                    With wSheet.Cells.Item(rowIndex + 1, colIndex)
                        'bordi e align delle celle
                        .HorizontalAlignment = Excel.XlHAlign.xlHAlignRight
                        .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                        .Borders.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.Gainsboro)
                        .Font.Size = 8
                        .Font.Bold = False
                        Try
                            'associo il valore della cell rows e la coloro come voglio solo se non e' null
                            'If Not IsNothing(wSheet.Cells.Item(rowIndex + 1, colIndex).value.ToString) Then

                            val = wSheet.Cells.Item(rowIndex + 1, colIndex).value.ToString
                                valBadge = wSheet.Cells.Item(rowIndex + 1, colIndex).value.ToString

                                If val <> "" Then
                                    If colIndex = 1 Then
                                        .HorizontalAlignment = Excel.XlHAlign.xlHAlignRight
                                    Else
                                        .HorizontalAlignment = Excel.XlHAlign.xlHAlignRight
                                    End If

                                End If
                            'End If

                        Catch e As Exception
                            val = ""
                            .Interior.Color = System.Drawing.ColorTranslator.ToOle(System.Drawing.Color.White)
                        End Try

                    End With

                Next
            Next

            wSheet.Columns.AutoFit()


            messagesProdTotInf = messages & Now & " File Excel Timbrature Mensa creato con Successo " & rowIndex - 1 & " record" & vbCrLf
            TextBox1.Text = messagesProdTotInf
            Me.NotifyIcon1.BalloonTipText = " File Excel Timbrature Mensr Creato con Successo " & rowIndex - 1 & " record"
            NotifyIcon1.ShowBalloonTip(300)
            'resetto le colonne e le righe
            colIndex = 0
            rowIndex = 1

            'optOspiti = ""
            dt = Nothing
            dr = Nothing
            dc = Nothing
        End If
        ' End If
        'Next


        'formatto la pagina xls 
        nameFile = ini_nameFileProd & "_" & dFileTimbrature & ini_fileFormat
        ' path per creare e salvare il file 
        Dim strFileName As String = ini_pathFileProd & nameFile
        If System.IO.File.Exists(strFileName) Then
            System.IO.File.Delete(strFileName)
        End If
        'salvo nella directory
        wBook.SaveAs(strFileName)
        'chiudo il file
        wBook.Close()
        'chiudo excell

        _excel.Quit()
        'serve ad inviare una mail 

        ' classe che accetta argomenti per mandare inf turno 
        messagesProdTotInf = messages & Now & " Invio il file Timbrature Creato per Email" & vbCrLf
        TextBox1.Text = messagesProdTotInf
        ' la list la creo quando leggo il file ini
        sendMailSMTPKSS("JSS-CLF-IT@eu.joysonsafety.com", "", "File Timbrature Mensa",
                        "attachment file content data Timbrature Mensa",
                         nameFile, ini_pathFileProd, "", "", listInvioGblCtrlMensa, "timbratureMensa")

        'sendMailSMTPKSS("Ordini-mensa-KSS@CLFSmtp.com", "", "prova smtp Colleferro", _
        '                "prova Smtp invio mail ssl", _
        '                 nameFile, ini_pathSave, "", "", listInvioMail, "mensa") ' la list la creo quando leggo il file ini
    End Sub

    Private Sub Form1_Load(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles MyBase.Load

        messages = " Program started .. .. "
        messages = Now & messages & vbCrLf
        TextBox1.Text = messages
        'PRENDO TUTTI I VAORI DEL FILE CONFIG.INI
        SetIni("config.ini")
        LabelInvioTimbrature.Text = ini_oraInvioCtrl_ini
        ' SE DISABILITATO BLOCCO LA PARTE DELLA MENSA 
        If ini_mensa_enable = "N" Then
            messages = "INI DISABLED "
            ini_send_file = "N"
            TextBox1.Text = messages
            TextBox1.Enabled = False
            start.Enabled = False
            stopMensa.Enabled = False
        End If
        'SE E' DISABILITATO NON BLOCCO LA PARTE INTERESSATA 
        If ini_hourly_enabled = "N" Then
            messagesProdTotInf = "INI DISABLED"
            startProdTotInf = "N"
            TextBoxDayProdPck.Text = messagesProdTotInf
            TextBoxDayProdPck.Enabled = False
            ButtonStartProdTotInf.Enabled = False
            ButtonStopProdTotInf.Enabled = False
        End If
        'SE E' DISABILITATO BLOCCO LA PARTE TICKET 
        If ini_ticket_enabled = "N" Then
            messagesInterventi = "INI DISABLED"
            startTicket = "N"
            TextBoxInfoTicket.Text = messagesInterventi
            TextBoxInfoTicket.Enabled = False
            startDash.Enabled = False
            stopDash.Enabled = False
        End If
        ' SE DISABILITATO BLOCCO LA PARTE DASHBOARD 
        If ini_dash_enabled = "N" Then
            messagesDashScreen = "INI DISABLED "
            ini_saveScreen = "N"
            TextBoxInfoDashScreenMail.Text = messagesDashScreen
            TextBoxInfoDashScreenMail.Enabled = False
            ButtonStartDashScreen.Enabled = False
            ButtonStopDashScreen.Enabled = False
        End If
        ' SE DISABILITATO BLOCCO LA PARTE CHECKLIST
        If ini_checklist_enabled = "N" Then
            messagesChecklist = "INI DISABLED "
            'ini_saveScreen = "N"
            TextBoxCheclist.Text = messagesChecklist
            TextBoxCheclist.Enabled = False
            ButtonStartCheclist.Enabled = False
            ButtonStopChecklist.Enabled = False
        End If
        'SetIni("ticket.ini")
        'SetIni("dash.ini")
        'lancio la funzione crea excell mensa passando i dati del db mensa
        'DatatableToExcel(dataMensa())
    End Sub

    Private Sub start_Click(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles start.Click

        messages = Now & " Started in manuale " & vbCrLf
        TextBox1.Text = messages
        startMensa = "Y"
        If ini_method = "1" Then
            DatatableToExcel()
        Else
            Dim nameFile As String = Nothing
            Dim dataPastoScelta As Date = Nothing
            Dim connMc As String = CStr(connectionDBM2())
            Dim aziendaId As Object = ExportCucinaDataTable.AziendaIdDaIni(connMc, ini_company)
            Dim percorso As String = ExportCucinaDataTable.EseguiExportMensa(
                connMc, dataNow, ini_pathSave, ini_nomeFile, ini_fileFormat,
                nameFile, aziendaId, dataPastoScelta, True)
            If percorso <> "" Then
                messages = messages & Now & " Export pasto " & dataPastoScelta.ToString("dd/MM/yyyy") &
                    " → " & nameFile & vbCrLf
                messages = messages & Now & " Invio il file Creato per Email" & vbCrLf
                TextBox1.Text = messages
                sendMailSMTPKSS("Ordini-mensa@eu.joysonsafety.com", "", "ordine pasti JOYSONSAFETY",
                    "Ciao siamo la JoysonSafety in allegato il file in excel che contiente gli ordini del giorno",
                    nameFile, ini_pathSave, "", "", listInvioMail, "mensa")
            Else
                messages = messages & Now & " Nessuna prenotazione da esportare." & vbCrLf
                TextBox1.Text = messages
            End If
        End If

        start.Enabled = False

    End Sub

    Sub startTicket_Cfg()
        messagesInterventi = " Started Ticket " & vbCrLf
        TextBoxInfoTicket.Text = messagesInterventi
        'APRO LA CONNESSIONE DB ORACLE WEB_INTERVENTI

        If connGlobal.State = "0" Then
            connectionORACLE(ini_db_name_oracle)
        End If

        getStauts_Ticket("VALID", 0)

        'closeDBOracle()
        'DatatableToExcel()
        startTicket = "Y"
        startDash.Enabled = False
    End Sub

    Sub stopTicket_Cfg()
        startDash.Enabled = True
        startTicket = "N"
        ini_ticket_enabled = "N"
        closeDBOracle()
        messagesInterventi = messagesInterventi & " Stopped " & vbCrLf
        TextBoxInfoTicket.Text = messagesInterventi
    End Sub

    Sub startProdTotInf_Cfg()
        messagesProdTotInf = Now & " Started in manuale " & vbCrLf
        TextBoxDayProdPck.Text = messagesProdTotInf

        startProdTotInf = "Y"
        ButtonStopProdTotInf.Enabled = False
    End Sub
    Sub stopProdTotInf_Cfg()
        ButtonStartProdTotInf.Enabled = True
        startProdTotInf = "N"

        messagesProdTotInf = Now & " Stopped " & vbCrLf
        TextBoxDayProdPck.Text = messagesProdTotInf
    End Sub
    Sub startDashScreen_Cfg()
        messagesDashScreen = Now & " Started in manuale " & vbCrLf
        TextBoxInfoDashScreenMail.Text = messagesDashScreen

        startDashScreen = "Y"
        ButtonStopDashScreen.Enabled = False
    End Sub
    Sub stopDashScreen_Cfg()
        ButtonStartDashScreen.Enabled = True
        startDashScreen = "N"

        messagesDashScreen = Now & " Stopped " & vbCrLf
        TextBoxInfoDashScreenMail.Text = messagesDashScreen
    End Sub

    Sub startCheckList_Cfg()

        ButtonStartCheclist.Enabled = True
        startCheck = "Y"

        If connGlobalCFP2.State = "0" Then
            connectionORACLECFP2(ini_db_name_oracle_Prod)
        End If

        messagesChecklist = Now & " Started in manuale " & vbCrLf
        'closeDBOracleCFPW()
        TextBoxCheclist.Text = messagesChecklist

    End Sub
    Sub startCheckQStatus()

        ButtonCtrlQStatus.Enabled = True
        startQStatusCheck = "Y"

        If connGlobalCFP2.State = "0" Then
            connectionORACLECFP2(ini_db_name_oracle_Prod)
        End If

        messagesChecklist = Now & " Started in manuale " & vbCrLf
        'closeDBOracleCFPW()
        TextBoxCheclist.Text = messagesChecklist

    End Sub

    Private Sub startDash_Click(sender As System.Object, e As System.EventArgs) Handles startDash.Click
        startTicket_Cfg()

    End Sub
    Private Sub stopDash_Click(sender As System.Object, e As System.EventArgs) Handles stopDash.Click
        stopTicket_Cfg()
    End Sub

    Private Sub stopMensa_Click(sender As System.Object, e As System.EventArgs) Handles stopMensa.Click
        start.Enabled = True

    End Sub

    Private Sub ButtonStartDashScreen_Click(sender As System.Object, e As System.EventArgs) Handles ButtonStartDashScreen.Click
        startDashScreen_Cfg()
    End Sub

    Private Sub ButtonStopDashScreen_Click_Click(sender As System.Object, e As System.EventArgs) Handles ButtonStopDashScreen.Click
        stopDashScreen_Cfg()
    End Sub

    Private Sub ButtonStartProdTotInf_Click(sender As System.Object, e As System.EventArgs) Handles ButtonStartProdTotInf.Click
        startProdTotInf_Cfg()
    End Sub

    Private Sub ButtonStopProdTotInf_Click(sender As System.Object, e As System.EventArgs) Handles ButtonStopProdTotInf.Click
        stopProdTotInf_Cfg()
    End Sub

    Private Sub LabelInvioTimbrature_Click(sender As System.Object, e As System.EventArgs) Handles LabelInvioTimbrature.Click
        'selectDatiDay(Now)
        'chiudiDBAccess()

        'DatatableToExcelTimbratureMensa(nameMensaTimbrature, DtdataTimbrature)
        'writeLog.ScriviLog(" [ Tibrature  ]: entrato MANUALE e inviato  " & Now, LogPath, True)
    End Sub

    Private Sub ButtonStartCheclist_Click(sender As System.Object, e As System.EventArgs) Handles ButtonStartCheclist.Click
        startCheckList_Cfg()
        writeLog.ScriviLog(" [ Checklist  ]: entrato MANUALE e inviato  " & Now, LogPath, True)
    End Sub

    Private Sub Button1_Click(sender As Object, e As EventArgs) Handles ButtonCtrlQStatus.Click

        If ini_checkQStatus_enabled = "Y" Then
            dataQualityStatus(Now, "59", "Airbag")
        End If
        If ini_checkQStatus_enabled_if = "Y" Then
            dataQualityStatus(Now, "59", "Inflator")
        End If

    End Sub

End Class

' ============================================================================
' MenuCraft → VB.NET (allineato a Form1 + function.vb + config.ini)
'
' config.ini (i tuoi nomi chiave reali):
'   [SETUP] ini_method=2                  → usa CONNESSIONE_M2 / MenuCraft
'   [CONNESSIONE_M2] ini_db_*2, ini_company=5  → id aziende (numero) o slug
'   [EXCEL] nameFile, pathFile, ini_fileFormat → ini_nomeFile / ini_pathSave
'   [ORARIO_INVIO] oraInvio_ini           → ini_oraInvio (timer sera)
'   [MENSACONFIG] ini_send_file=Y
'   [DESTINATARI] mail=...                → listInvioMail
'
' Data pasto: DataPastoDaEsportare (primo lavorativo dopo oggi sul DB MenuCraft).
' Snippet timer + start manuale: Form1-mensa-snippet.vb
' ============================================================================


Public Module ExportCucinaDataTable

#Region "Data / nome file (come il tuo getIdPrenotazione)"

    ''' <summary>
    ''' Chiave giorno a 5 cifre sul GIORNO PASTO già risolto: yy + giorno anno.
    ''' Non avanza più la data (il salto weekend/ferie è in DataPastoDaEsportare).
    ''' Allineato a MenuCraft ExportCucina::idGiornoPasto.
    ''' </summary>
    Public Function getIdPrenotazione(dataPasto As Date) As String
        Return dataPasto.ToString("yy") & dataPasto.DayOfYear.ToString("000")
    End Function

    ''' <summary>Data pasto ISO per il filtro SQL su export_cucina.data_pasto.</summary>
    Public Function DataPastoIso(dataPasto As Date) As String
        Return dataPasto.ToString("yyyy-MM-dd")
    End Function

    ''' <summary>Stesso nome file di prima: {ini_nomeFile}_{id5}{ini_fileFormat}.</summary>
    Public Function NomeFileMensa(ini_nomeFile As String, idPrenotazione As String, ini_fileFormat As String) As String
        Return ini_nomeFile & "_" & idPrenotazione & ini_fileFormat
    End Function

    ''' <summary>Percorso completo sul server/cartella remota (ini_pathSave).</summary>
    Public Function PercorsoFileMensa(ini_pathSave As String, nameFile As String) As String
        If String.IsNullOrEmpty(ini_pathSave) Then
            Return nameFile
        End If
        If ini_pathSave.EndsWith("\") OrElse ini_pathSave.EndsWith("/") Then
            Return ini_pathSave & nameFile
        End If
        Return ini_pathSave.TrimEnd() & "\" & nameFile
    End Function

    ''' <summary>
    ''' ini_company da [CONNESSIONE_M2]: se è numerico (es. "5") è già aziende.id;
    ''' altrimenti cerca per slug. Se vuoto → Nothing (tutte le aziende in export).
    ''' </summary>
    Public Function AziendaIdDaIni(connessione As String, iniCompany As String) As Object
        If String.IsNullOrWhiteSpace(iniCompany) Then
            Return Nothing
        End If
        Dim grezzo As String = iniCompany.Trim()
        Dim idNum As Long
        If Long.TryParse(grezzo, idNum) AndAlso idNum > 0 Then
            Return idNum
        End If
        Return AziendaIdDaSlug(connessione, grezzo)
    End Function

    ''' <summary>Risolve aziende.id da slug (es. "aurora-welfare").</summary>
    Public Function AziendaIdDaSlug(connessione As String, slug As String) As Object
        If String.IsNullOrWhiteSpace(slug) Then
            Return Nothing
        End If
        Using cn As New MySqlConnection(connessione)
            Using cmd As New MySqlCommand("SELECT id FROM aziende WHERE slug = @slug LIMIT 1", cn)
                cmd.Parameters.AddWithValue("@slug", slug.Trim().ToLowerInvariant())
                cn.Open()
                Dim v = cmd.ExecuteScalar()
                If v Is Nothing OrElse IsDBNull(v) Then
                    Return Nothing
                End If
                Return Convert.ToInt64(v)
            End Using
        End Using
    End Function

    ''' <summary>
    ''' True se il giorno è prenotabile/esportabile per l'azienda MenuCraft:
    ''' apertura forzata, oppure non weekend (salvo apri_sabato/domenica),
    ''' e non in calendario_chiusure (nazionali + ferie servizio).
    ''' </summary>
    Public Function GiornoLavorativo(
        connessione As String,
        giorno As Date,
        Optional aziendaId As Object = Nothing
    ) As Boolean
        Dim iso As String = DataPastoIso(giorno.Date)
        Dim dow As Integer = Weekday(giorno.Date, FirstDayOfWeek.Monday) ' 1=lun .. 7=dom

        Using cn As New MySqlConnection(connessione)
            cn.Open()

            Dim apriSabato As Boolean = False
            Dim apriDomenica As Boolean = False
            If aziendaId IsNot Nothing AndAlso Not IsDBNull(aziendaId) Then
                Using cmdA As New MySqlCommand(
                    "SELECT apri_sabato, apri_domenica FROM aziende WHERE id = @id LIMIT 1", cn)
                    cmdA.Parameters.AddWithValue("@id", aziendaId)
                    Using rd = cmdA.ExecuteReader()
                        If rd.Read() Then
                            apriSabato = Convert.ToInt32(rd("apri_sabato")) = 1
                            apriDomenica = Convert.ToInt32(rd("apri_domenica")) = 1
                        End If
                    End Using
                End Using

                Using cmdOpen As New MySqlCommand(
                    "SELECT 1 FROM calendario_aperture WHERE azienda_id = @id AND data_apertura = @d LIMIT 1", cn)
                    cmdOpen.Parameters.AddWithValue("@id", aziendaId)
                    cmdOpen.Parameters.AddWithValue("@d", iso)
                    Dim aperto = cmdOpen.ExecuteScalar()
                    If aperto IsNot Nothing AndAlso Not IsDBNull(aperto) Then
                        Return True
                    End If
                End Using
            End If

            If dow = 6 AndAlso Not apriSabato Then
                Return False
            End If
            If dow = 7 AndAlso Not apriDomenica Then
                Return False
            End If

            Dim sqlChiusura As String =
                "SELECT 1 FROM calendario_chiusure WHERE data_chiusura = @d AND ("
            If aziendaId IsNot Nothing AndAlso Not IsDBNull(aziendaId) Then
                sqlChiusura &= "azienda_id = @id OR azienda_id IS NULL"
            Else
                sqlChiusura &= "azienda_id IS NULL"
            End If
            sqlChiusura &= ") LIMIT 1"

            Using cmdC As New MySqlCommand(sqlChiusura, cn)
                cmdC.Parameters.AddWithValue("@d", iso)
                If aziendaId IsNot Nothing AndAlso Not IsDBNull(aziendaId) Then
                    cmdC.Parameters.AddWithValue("@id", aziendaId)
                End If
                Dim chiuso = cmdC.ExecuteScalar()
                If chiuso IsNot Nothing AndAlso Not IsDBNull(chiuso) Then
                    Return False
                End If
            End Using
        End Using

        Return True
    End Function

    ''' <summary>
    ''' Giorno pasto da esportare alla sera (come il tuo getIdPrenotazione, ma
    ''' con ferie/chiusure MenuCraft): primo giorno lavorativo DOPO riferimento
    ''' (default = oggi del timer Form1).
    ''' </summary>
    Public Function DataPastoDaEsportare(
        connessione As String,
        Optional riferimento As Date = Nothing,
        Optional aziendaId As Object = Nothing,
        Optional maxGiorni As Integer = 60
    ) As Date
        Dim baseDay As Date = If(riferimento = Nothing OrElse riferimento = Date.MinValue, Date.Today, riferimento.Date)
        Dim candidato As Date = baseDay.AddDays(1)
        For i As Integer = 0 To Math.Max(1, maxGiorni) - 1
            If GiornoLavorativo(connessione, candidato, aziendaId) Then
                Return candidato
            End If
            candidato = candidato.AddDays(1)
        Next
        Throw New InvalidOperationException(
            "Nessun giorno utile da esportare nei prossimi " & maxGiorni & " giorni.")
    End Function

#End Region

#Region "DataTable da export_cucina"

    ''' <summary>
    ''' Carica dipendenti + ospiti in un unico DataTable (ospiti in coda).
    ''' </summary>
    Public Function dataMensaCompleto(connessione As String, dataPasto As String, Optional aziendaId As Object = Nothing) As DataTable
        Dim dipendenti As DataTable = dataMensaDaExportCucina(connessione, dataPasto, aziendaId, soloOspiti:=False)
        Dim ospiti As DataTable = dataMensaDaExportCucina(connessione, dataPasto, aziendaId, soloOspiti:=True)

        If dipendenti.Columns.Count = 0 AndAlso ospiti.Columns.Count > 0 Then
            Return ospiti
        End If
        If ospiti.Rows.Count = 0 Then
            Return dipendenti
        End If

        ' Stesse colonne: appende le righe ospite in fondo
        For Each r As DataRow In ospiti.Rows
            dipendenti.ImportRow(r)
        Next
        Return dipendenti
    End Function

    ''' <summary>
    ''' Filtro ospiti/dipendenti da export_cucina.
    ''' </summary>
    Public Function dataMensaDaExportCucina(
        connessione As String,
        dataPasto As String,
        aziendaId As Object,
        soloOspiti As Boolean
    ) As DataTable

        Dim sql As String =
            "SELECT badge, employee, ospite, host, giorno, time_stamp, payment, allergie, " &
            "intestazione_json, celle_json " &
            "FROM export_cucina WHERE data_pasto = @data "

        If aziendaId IsNot Nothing AndAlso Not IsDBNull(aziendaId) Then
            sql &= "AND azienda_id = @azienda "
        End If
        If soloOspiti Then
            sql &= "AND ospite = 'S' "
        Else
            sql &= "AND (ospite IS NULL OR ospite <> 'S') "
        End If
        sql &= "ORDER BY employee ASC"

        Dim dt As New DataTable("prenotazioni")
        Dim colonneCibo As List(Of String) = Nothing

        Using cn As New MySqlConnection(connessione)
            Using cmd As New MySqlCommand(sql, cn)
                cmd.Parameters.AddWithValue("@data", dataPasto)
                If aziendaId IsNot Nothing AndAlso Not IsDBNull(aziendaId) Then
                    cmd.Parameters.AddWithValue("@azienda", aziendaId)
                End If
                cn.Open()
                Using rd As MySqlDataReader = cmd.ExecuteReader()
                    While rd.Read()
                        Dim headers As List(Of String) = ParseJsonStringArray(If(rd("intestazione_json"), "").ToString())
                        Dim valori As List(Of String) = ParseJsonStringArray(If(rd("celle_json"), "").ToString())

                        If colonneCibo Is Nothing Then
                            colonneCibo = headers
                            dt.Columns.Add("BADGE", GetType(String))
                            dt.Columns.Add("EMPLOYEE", GetType(String))
                            For Each h As String In headers
                                If Not dt.Columns.Contains(h) Then
                                    dt.Columns.Add(h, GetType(String))
                                End If
                            Next
                            dt.Columns.Add("GIORNO", GetType(String))
                            dt.Columns.Add("TIME_STAMP", GetType(String))
                            dt.Columns.Add("PAYMENT", GetType(String))
                        End If

                        Dim row As DataRow = dt.NewRow()
                        Dim eOspite As Boolean = (If(rd("ospite"), "").ToString() = "S")
                        row("BADGE") = If(eOspite, "OSPITE", If(rd("badge"), "").ToString())
                        row("EMPLOYEE") = If(rd("employee"), "").ToString()
                        For i As Integer = 0 To colonneCibo.Count - 1
                            Dim nomeCol As String = colonneCibo(i)
                            Dim val As String = If(i < valori.Count, valori(i), "")
                            If dt.Columns.Contains(nomeCol) Then
                                row(nomeCol) = val
                            End If
                        Next
                        row("GIORNO") = If(rd("giorno"), "").ToString()
                        row("TIME_STAMP") = If(rd("time_stamp"), "").ToString()
                        row("PAYMENT") = If(rd("payment"), "").ToString()
                        dt.Rows.Add(row)
                    End While
                End Using
            End Using
        End Using

        If dt.Columns.Count = 0 Then
            dt.Columns.Add("BADGE", GetType(String))
            dt.Columns.Add("EMPLOYEE", GetType(String))
            dt.Columns.Add("GIORNO", GetType(String))
            dt.Columns.Add("TIME_STAMP", GetType(String))
            dt.Columns.Add("PAYMENT", GetType(String))
        End If
        Return dt
    End Function

    ''' <summary>Compat: _opt come il vecchio dataMensa ("='OSPITE'" / "&lt;&gt;'OSPITE'").</summary>
    Public Function dataMensa(connessione As String, dataPasto As String, ByVal _opt As String, Optional aziendaId As Object = Nothing) As DataTable
        Dim soloOspiti As Boolean = _opt.Replace(" ", "").ToUpper().Contains("='OSPITE'") OrElse
            _opt.Replace(" ", "").ToUpper().Contains("=""OSPITE""")
        Return dataMensaDaExportCucina(connessione, dataPasto, aziendaId, soloOspiti)
    End Function

#End Region

#Region "Excel dinamico — un foglio, ospiti in fondo"

    ''' <summary>
    ''' Crea il file Excel da un DataTable già completo (dipendenti + ospiti in coda).
    ''' Colonne = quelle del DataTable (dinamiche dal JSON).
    ''' </summary>
    Public Sub DatatableToExcel(
        dt As DataTable,
        percorsoFile As String,
        Optional inviaMail As Boolean = False
    )
        If dt Is Nothing OrElse dt.Rows.Count = 0 Then
            Return
        End If

        Dim _excel As New Excel.Application
        Dim wBook As Excel.Workbook = _excel.Workbooks.Add()
        Dim wSheet As Excel.Worksheet = CType(wBook.Worksheets(1), Excel.Worksheet)
        wSheet.Name = "Prenotazioni"

        Dim nCol As Integer = dt.Columns.Count
        Dim lastColLetter As String = ColonnaExcel(nCol)

        ' --- Titolo riga 1 (GIORNO + TIME_STAMP per nome, non per indice) ---
        Dim nomeGiorno As String = UCase(ValoreColonna(dt.Rows(0), "GIORNO"))
        Dim dataMese As String = ValoreColonna(dt.Rows(0), "TIME_STAMP")
        Try
            If dataMese <> "" Then
                dataMese = Convert.ToDateTime(dataMese).ToString("dd/MM/yyyy")
            End If
        Catch
        End Try

        Dim titolo As String = "PRENOTAZIONE MENSA " & nomeGiorno & " " & dataMese & " TOT. " & dt.Rows.Count.ToString()
        wSheet.Range("A1:" & lastColLetter & "1").Merge()
        wSheet.Range("A1").Value = titolo
        With wSheet.Range("A1:" & lastColLetter & "1")
            .HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
            .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
            .Interior.Color = ColorTranslator.ToOle(Color.YellowGreen)
            .Font.Bold = True
            .Font.Size = 12
        End With

        ' --- Intestazioni riga 2 ---
        For c As Integer = 0 To nCol - 1
            Dim cell As Excel.Range = CType(wSheet.Cells(2, c + 1), Excel.Range)
            cell.Value = dt.Columns(c).ColumnName
            cell.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
            cell.Borders.LineStyle = Excel.XlLineStyle.xlContinuous
            cell.Font.Bold = True
            cell.Interior.Color = ColorTranslator.ToOle(Color.Gainsboro)
        Next

        ' --- Dati da riga 3; conteggi lettere per il riepilogo ---
        Dim totali As New Dictionary(Of String, Integer)(StringComparer.OrdinalIgnoreCase)
        Dim paganti As Integer = 0
        Dim colonneMeta As New HashSet(Of String)(StringComparer.OrdinalIgnoreCase) From {
            "BADGE", "EMPLOYEE", "GIORNO", "TIME_STAMP", "PAYMENT", "OSPITE", "HOST", "ALLERGIE"
        }

        For r As Integer = 0 To dt.Rows.Count - 1
            Dim excelRow As Integer = r + 3
            For c As Integer = 0 To nCol - 1
                Dim nomeCol As String = dt.Columns(c).ColumnName
                Dim val As String = ValoreColonna(dt.Rows(r), nomeCol)
                Dim cell As Excel.Range = CType(wSheet.Cells(excelRow, c + 1), Excel.Range)
                cell.Value = val
                cell.HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                cell.Borders.LineStyle = Excel.XlLineStyle.xlContinuous

                ' Riga ospite: sfondo leggero sulla colonna BADGE
                If nomeCol = "BADGE" AndAlso val = "OSPITE" Then
                    cell.Interior.Color = ColorTranslator.ToOle(Color.LightYellow)
                    cell.Font.Bold = True
                End If

                If Not colonneMeta.Contains(nomeCol) AndAlso val <> "" Then
                    ApplicaColorePasto(cell, val)
                    AccumulaTotale(totali, val)
                End If
                If nomeCol = "PAYMENT" AndAlso val = "P" Then
                    cell.Interior.Color = ColorTranslator.ToOle(Color.Yellow)
                    cell.Font.Bold = True
                    paganti += 1
                End If
            Next
        Next

        wSheet.Columns.AutoFit()

        ' --- Riepilogo totali sotto i dati (dinamico sulle lettere trovate) ---
        Dim startTot As Integer = dt.Rows.Count + 4
        wSheet.Range("A" & startTot & ":B" & startTot).Merge()
        wSheet.Range("A" & startTot).Value = "TOT. PER TIPO"
        With wSheet.Range("A" & startTot & ":B" & startTot)
            .Interior.Color = ColorTranslator.ToOle(Color.YellowGreen)
            .Font.Bold = True
            .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
        End With

        Dim lettereOrdinate As New List(Of String) From {
            "A", "B", "C", "D", "E", "F", "G", "H", "I", "L", "M", "N"
        }
        Dim colTot As Integer = 3
        For Each lettera As String In lettereOrdinate
            Dim q As Integer = 0
            If totali.ContainsKey(lettera) Then
                q = totali(lettera)
            End If
            CType(wSheet.Cells(startTot, colTot), Excel.Range).Value = lettera
            CType(wSheet.Cells(startTot + 1, colTot), Excel.Range).Value = q
            With wSheet.Range(wSheet.Cells(startTot, colTot), wSheet.Cells(startTot + 1, colTot))
                .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
                .HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
                .Font.Bold = True
            End With
            CType(wSheet.Cells(startTot, colTot), Excel.Range).Interior.Color = ColorTranslator.ToOle(Color.YellowGreen)
            CType(wSheet.Cells(startTot + 1, colTot), Excel.Range).Interior.Color = ColorTranslator.ToOle(Color.Silver)
            colTot += 1
        Next

        CType(wSheet.Cells(startTot, colTot), Excel.Range).Value = "GIORNO"
        CType(wSheet.Cells(startTot + 1, colTot), Excel.Range).Value = nomeGiorno
        CType(wSheet.Cells(startTot, colTot + 1), Excel.Range).Value = "DATA"
        CType(wSheet.Cells(startTot + 1, colTot + 1), Excel.Range).Value = dataMese
        CType(wSheet.Cells(startTot, colTot + 2), Excel.Range).Value = "PAGANTI"
        CType(wSheet.Cells(startTot + 1, colTot + 2), Excel.Range).Value = paganti
        With wSheet.Range(wSheet.Cells(startTot, colTot), wSheet.Cells(startTot + 1, colTot + 2))
            .Borders.LineStyle = Excel.XlLineStyle.xlContinuous
            .HorizontalAlignment = Excel.XlHAlign.xlHAlignCenter
            .Font.Bold = True
        End With
        wSheet.Range(wSheet.Cells(startTot, colTot), wSheet.Cells(startTot, colTot + 2)).Interior.Color =
            ColorTranslator.ToOle(Color.YellowGreen)
        wSheet.Range(wSheet.Cells(startTot + 1, colTot), wSheet.Cells(startTot + 1, colTot + 2)).Interior.Color =
            ColorTranslator.ToOle(Color.Silver)

        If System.IO.File.Exists(percorsoFile) Then
            System.IO.File.Delete(percorsoFile)
        End If
        wBook.SaveAs(percorsoFile)
        wBook.Close(False)
        _excel.Quit()
        ReleaseCom(wSheet)
        ReleaseCom(wBook)
        ReleaseCom(_excel)
    End Sub

    ''' <summary>
    ''' Flusso completo: DB → DataTable (dipendenti+ospiti) → Excel un foglio.
    ''' </summary>
    Public Sub EsportaGiorno(
        connessione As String,
        dataPasto As String,
        percorsoFile As String,
        Optional aziendaId As Object = Nothing
    )
        Dim dt As DataTable = dataMensaCompleto(connessione, dataPasto, aziendaId)
        DatatableToExcel(dt, percorsoFile)
    End Sub

#End Region

#Region "Reminder mensa (MenuCraft)"

    ''' <summary>
    ''' Sostituisce getReminderListMail sul DB vecchio.
    ''' Mattina: pasto di oggi (se lavorativo). Pomeriggio: primo giorno utile dopo oggi.
    ''' Solo utenti con mail_reminder='Y' e email valorizzata, senza ordine personale (ospite=0).
    ''' </summary>
    Public Function ListaReminderMail(
        connessione As String,
        remainderType As String,
        dataNow As Date,
        Optional aziendaId As Object = Nothing
    ) As List(Of String)
        Dim lista As New List(Of String)()
        Dim tipo As String = If(remainderType, "").Trim().ToLowerInvariant()
        Dim target As Date

        If tipo = "mattina" Then
            If Not GiornoLavorativo(connessione, dataNow.Date, aziendaId) Then
                Return lista
            End If
            target = dataNow.Date
        ElseIf tipo = "pomeriggio" Then
            target = DataPastoDaEsportare(connessione, dataNow.Date, aziendaId)
        Else
            Return lista
        End If

        Dim sql As String =
            "SELECT u.email FROM utenti u " &
            "WHERE u.ruolo = 'dipendente' " &
            "AND u.mail_reminder = 'Y' " &
            "AND u.email IS NOT NULL AND TRIM(u.email) <> '' "
        If aziendaId IsNot Nothing AndAlso Not IsDBNull(aziendaId) Then
            sql &= "AND u.azienda_id = @azienda "
        End If
        sql &=
            "AND NOT EXISTS (" &
            "  SELECT 1 FROM ordini o " &
            "  WHERE o.utente_id = u.id AND o.azienda_id = u.azienda_id " &
            "    AND o.data_pasto = @pasto AND o.ospite = 0" &
            ") " &
            "ORDER BY u.nome ASC"

        Using cn As New MySqlConnection(connessione)
            Using cmd As New MySqlCommand(sql, cn)
                cmd.Parameters.AddWithValue("@pasto", DataPastoIso(target))
                If aziendaId IsNot Nothing AndAlso Not IsDBNull(aziendaId) Then
                    cmd.Parameters.AddWithValue("@azienda", aziendaId)
                End If
                cn.Open()
                Using rd = cmd.ExecuteReader()
                    While rd.Read()
                        Dim mail As String = If(rd("email"), "").ToString().Trim().ToLowerInvariant()
                        If mail <> "" AndAlso mail.Contains("@") AndAlso Not lista.Contains(mail) Then
                            lista.Add(mail)
                        End If
                    End While
                End Using
            End Using
        End Using
        Return lista
    End Function

    ''' <summary>limite_oggi / limite_domani (HH:mm) da aziende.</summary>
    Public Function LimitiPrenotazione(
        connessione As String,
        Optional aziendaId As Object = Nothing
    ) As String()
        Dim oggi As String = "09:30"
        Dim domani As String = "18:00"
        Dim sql As String = "SELECT limite_oggi, limite_domani FROM aziende "
        If aziendaId IsNot Nothing AndAlso Not IsDBNull(aziendaId) Then
            sql &= "WHERE id = @id "
        End If
        sql &= "LIMIT 1"
        Using cn As New MySqlConnection(connessione)
            Using cmd As New MySqlCommand(sql, cn)
                If aziendaId IsNot Nothing AndAlso Not IsDBNull(aziendaId) Then
                    cmd.Parameters.AddWithValue("@id", aziendaId)
                End If
                cn.Open()
                Using rd = cmd.ExecuteReader()
                    If rd.Read() Then
                        Dim lo = If(rd("limite_oggi"), "").ToString()
                        Dim ld = If(rd("limite_domani"), "").ToString()
                        If lo.Length >= 5 Then oggi = lo.Substring(0, 5)
                        If ld.Length >= 5 Then domani = ld.Substring(0, 5)
                    End If
                End Using
            End Using
        End Using
        Return New String() {oggi, domani}
    End Function

    Public Function DataPastoReminderIso(
        connessione As String,
        remainderType As String,
        dataNow As Date,
        Optional aziendaId As Object = Nothing
    ) As String
        Dim tipo As String = If(remainderType, "").Trim().ToLowerInvariant()
        If tipo = "mattina" Then
            If Not GiornoLavorativo(connessione, dataNow.Date, aziendaId) Then
                Return ""
            End If
            Return DataPastoIso(dataNow.Date)
        End If
        If tipo = "pomeriggio" Then
            Return DataPastoIso(DataPastoDaEsportare(connessione, dataNow.Date, aziendaId))
        End If
        Return ""
    End Function

#End Region

#Region "Export orchestratore"

    ''' <summary>
    ''' Orchestratore come DatatableToExcel di Form1:
    ''' dataNow (oggi del timer) → DataPastoDaEsportare (primo utile) →
    ''' id 5 cifre → export_cucina → Excel → SaveAs(ini_pathSave\nome).
    ''' Se oggi non è lavorativo (sab/dom/festa) non invia (come il tuo
    ''' day &lt;&gt; Saturday/Sunday), salvo forzaAncheOggiChiuso=True.
    ''' </summary>
    Public Function EseguiExportMensa(
        connessione As String,
        dataNow As Date,
        ini_pathSave As String,
        ini_nomeFile As String,
        ini_fileFormat As String,
        ByRef nameFile As String,
        Optional aziendaId As Object = Nothing,
        Optional ByRef dataPastoScelta As Date = Nothing,
        Optional forzaAncheOggiChiuso As Boolean = False
    ) As String

        nameFile = ""
        If Not forzaAncheOggiChiuso AndAlso Not GiornoLavorativo(connessione, dataNow.Date, aziendaId) Then
            Return ""
        End If

        Dim giornoPasto As Date = DataPastoDaEsportare(connessione, dataNow.Date, aziendaId)
        dataPastoScelta = giornoPasto

        Dim idPrenotazione As String = getIdPrenotazione(giornoPasto)
        Dim dataPasto As String = DataPastoIso(giornoPasto)
        nameFile = NomeFileMensa(ini_nomeFile, idPrenotazione, ini_fileFormat)
        Dim strFileName As String = PercorsoFileMensa(ini_pathSave, nameFile)

        Dim dt As DataTable = dataMensaCompleto(connessione, dataPasto, aziendaId)
        If dt Is Nothing OrElse dt.Rows.Count = 0 Then
            nameFile = ""
            Return ""
        End If

        DatatableToExcel(dt, strFileName)
        Return strFileName
    End Function

#End Region

#Region "Helpers"

    Public Function ParseJsonStringArray(json As String) As List(Of String)
        Dim result As New List(Of String)()
        If String.IsNullOrWhiteSpace(json) Then
            Return result
        End If
        For Each m As Match In Regex.Matches(json, """((?:\\.|[^""])*)""")
            Dim raw As String = m.Groups(1).Value
            raw = raw.Replace("\\""", """").Replace("\\/", "/").Replace("\\n", vbLf)
            result.Add(raw)
        Next
        Return result
    End Function

    Public Function ValoreColonna(row As DataRow, nome As String) As String
        If row Is Nothing OrElse Not row.Table.Columns.Contains(nome) Then
            Return ""
        End If
        If IsDBNull(row(nome)) Then
            Return ""
        End If
        Return row(nome).ToString()
    End Function

    Private Function ColonnaExcel(indice1Based As Integer) As String
        Dim n As Integer = indice1Based
        Dim s As String = ""
        While n > 0
            Dim r As Integer = (n - 1) Mod 26
            s = Chr(Asc("A"c) + r) & s
            n = (n - 1) \ 26
        End While
        Return s
    End Function

    Private Sub ApplicaColorePasto(cell As Excel.Range, val As String)
        cell.Font.Bold = True
        Select Case val
            Case "A", "Ax2", "B", "Bx2", "C", "Cx2", "D", "Dx2", "E", "Ex2"
                cell.Interior.Color = ColorTranslator.ToOle(Color.PapayaWhip)
            Case "F", "Fx2", "G", "Gx2", "H", "Hx2", "I", "Ix2", "L", "Lx2"
                cell.Interior.Color = ColorTranslator.ToOle(Color.LimeGreen)
            Case "M", "Mx2", "N", "Nx2"
                cell.Interior.Color = ColorTranslator.ToOle(Color.Aqua)
            Case "P"
                cell.Interior.Color = ColorTranslator.ToOle(Color.Yellow)
        End Select
    End Sub

    Private Sub AccumulaTotale(totali As Dictionary(Of String, Integer), val As String)
        If String.IsNullOrEmpty(val) Then
            Return
        End If
        Dim lettera As String = val.Substring(0, 1).ToUpper()
        Dim q As Integer = 1
        If val.EndsWith("x2", StringComparison.OrdinalIgnoreCase) Then
            q = 2
        End If
        If Not totali.ContainsKey(lettera) Then
            totali(lettera) = 0
        End If
        totali(lettera) += q
    End Sub

    Private Sub ReleaseCom(ByVal o As Object)
        Try
            If o IsNot Nothing Then
                System.Runtime.InteropServices.Marshal.ReleaseComObject(o)
            End If
        Catch
        End Try
    End Sub

#End Region

End Module
