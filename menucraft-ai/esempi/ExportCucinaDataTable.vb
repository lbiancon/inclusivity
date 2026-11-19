' ============================================================================
' Nuovo dataMensa: legge export_cucina (JSON) e costruisce un DataTable
' con colonne dinamiche → lo passi a DatatableToExcel senza toccarla.
'
' Uso tipico (come oggi):
'   dt = dataMensa("='OSPITE'")      ' foglio Ospiti
'   dt = dataMensa("<>'OSPITE'")     ' foglio Dipendenti
'   ' poi DatatableToExcel come ora
'
' Requisiti: MySql.Data. Il JSON è un array di stringhe semplice
'   intestazione_json = ["PRIMO","PRIMO2","SECONDO",...]
'   celle_json        = ["A","Bx2","D",...]
' ============================================================================

Imports MySql.Data.MySqlClient
Imports System.Data
Imports System.Collections.Generic
Imports System.Text.RegularExpressions

Public Module ExportCucinaDataTable

    ''' <summary>
    ''' Sostituto di dataMensa: stessa firma (_opt ospiti/dipendenti).
    ''' Legge export_cucina del giorno e crea il DataTable dalle colonne JSON.
    ''' </summary>
    ''' <param name="_opt">Come oggi: "='OSPITE'" oppure "&lt;&gt;'OSPITE'"</param>
    Public Function dataMensa(ByVal _opt As String) As DataTable
        Dim strCon As MySqlConnectionStringBuilder = connectionDB()
        Dim dataPasto As String = dataNow.ToString("yyyy-MM-dd")
        ' Se hai più aziende sullo stesso DB, passa anche lo slug/id:
        Return dataMensaDaExportCucina(strCon.ConnectionString, dataPasto, Nothing, _opt)
    End Function

    ''' <summary>
    ''' Versione esplicita: connection string, data pasto, azienda opzionale, filtro ospiti.
    ''' </summary>
    Public Function dataMensaDaExportCucina(
        connessione As String,
        dataPasto As String,
        aziendaId As Object,
        ByVal _opt As String
    ) As DataTable

        Dim soloOspiti As Boolean = _opt.Replace(" ", "").StartsWith("='") OrElse
            _opt.Replace(" ", "").StartsWith("=""") OrElse
            _opt.ToUpper().Contains("= 'OSPITE'") OrElse
            _opt.ToUpper().Contains("='OSPITE'")

        Dim sql As String =
            "SELECT badge, employee, ospite, host, giorno, time_stamp, payment, allergie, " &
            "intestazione_json, celle_json " &
            "FROM export_cucina " &
            "WHERE data_pasto = @data "

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
                            ' Stesso ordine “logico” di prima: BADGE, EMPLOYEE, piatti…, GIORNO, TIME_STAMP, PAYMENT
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
                        ' Come il vecchio CODE_EMPLOYEE AS BADGE: ospiti → "OSPITE"
                        row("BADGE") = If(eOspite, "OSPITE", If(rd("badge"), "").ToString())
                        row("EMPLOYEE") = If(rd("employee"), "").ToString()

                        For i As Integer = 0 To colonneCibo.Count - 1
                            Dim nomeCol As String = colonneCibo(i)
                            Dim val As String = ""
                            If i < valori.Count Then
                                val = valori(i)
                            End If
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

        ' Nessuna riga: DataTable vuoto ma con colonne minime (Excel non esplode)
        If dt.Columns.Count = 0 Then
            dt.Columns.Add("BADGE", GetType(String))
            dt.Columns.Add("EMPLOYEE", GetType(String))
            dt.Columns.Add("GIORNO", GetType(String))
            dt.Columns.Add("TIME_STAMP", GetType(String))
            dt.Columns.Add("PAYMENT", GetType(String))
        End If

        Return dt
    End Function

    ''' <summary>
    ''' Parser minimale di JSON array di stringhe: ["A","B x2"].
    ''' Niente dipendenze extra (Newtonsoft non serve).
    ''' </summary>
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

    ''' <summary>
    ''' Helper per DatatableToExcel: trova GIORNO/TIME_STAMP per nome
    ''' invece che colIndex fisso 15/16 (con colonne dinamiche l’indice cambia).
    ''' Esempio: nomeMese = dt.Rows(0)("GIORNO").ToString()
    ''' </summary>
    Public Function ValoreColonna(row As DataRow, nome As String) As String
        If row Is Nothing OrElse Not row.Table.Columns.Contains(nome) Then
            Return ""
        End If
        If IsDBNull(row(nome)) Then
            Return ""
        End If
        Return row(nome).ToString()
    End Function

End Module
