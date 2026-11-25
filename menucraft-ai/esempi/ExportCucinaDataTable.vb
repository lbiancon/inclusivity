' ============================================================================
' MenuCraft → VB.NET (allineato a Form1 + function.vb + config.ini)
'
' Orario: ini_oraInvio (MENSACONFIG) come nel TimerOra di Form1.
' Data pasto: NON più solo “+1 / venerdì +3”.
'   Usa DataPastoDaEsportare: primo giorno LAVORATIVO dopo oggi, leggendo
'   aziende.apri_sabato/apri_domenica, calendario_chiusure e calendario_aperture.
'   Esempi: ven→lun; lun con mar+mer chiusi→gio.
' File: ini_nomeFile & "_" & getIdPrenotazione(dataPasto) & ini_fileFormat
'       in ini_pathSave, poi sendMailSMTPKSS come già fai.
'
' Nel TimerOra (al posto di day <> Saturday/Sunday + DatatableToExcel()):
'
'   If oraNow = oraStart And ini_send_file = "Y" Then
'       If boolEntrySendMensa = False Then
'           boolEntrySendMensa = True
'           Dim nameFile As String = Nothing
'           Dim percorso As String = EseguiExportMensa(
'               connectionDB().ConnectionString, dataNow,
'               ini_pathSave, ini_nomeFile, ini_fileFormat, nameFile,
'               Nothing)  ' oppure AziendaIdDaSlug(..., ini_company)
'           If percorso <> "" Then
'               sendMailSMTPKSS("Ordini-mensa@eu.joysonsafety.com", "",
'                   "ordine pasti JOYSONSAFETY",
'                   "Ciao siamo la JoysonSafety in allegato il file ...",
'                   nameFile, ini_pathSave, "", "", listInvioMail, "mensa")
'           End If
'       End If
'   Else
'       boolEntrySendMensa = False
'   End If
' ============================================================================

Imports MySql.Data.MySqlClient
Imports System.Data
Imports System.Collections.Generic
Imports System.Text.RegularExpressions
Imports System.Drawing
Imports Excel = Microsoft.Office.Interop.Excel
Imports System.Reflection

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

    ''' <summary>Risolve aziende.id da slug (es. ini_company o "joyson-safety").</summary>
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
