' Esempio: come prima, query → DataTable → ExportExcel (invariata).
' Adatta connection string, parametri azienda/data e il nome della tua ExportExcel.

Imports MySql.Data.MySqlClient
Imports System.Data

Public Module ExportCucinaDataTable

    ''' <summary>
    ''' Carica le prenotazioni del giorno in un DataTable con le colonne
    ''' classiche (BADGE, EMPLOYEE, PRIMO, … PAYMENT) da passare a ExportExcel.
    ''' </summary>
    Public Function CaricaPrenotazioniGiorno(
        connessione As String,
        aziendaSlug As String,
        dataPasto As Date
    ) As DataTable

        Dim sql As String =
            "SELECT BADGE, EMPLOYEE, PRIMO, SECONDO, CONTORNO, CONTORNO2, " &
            "FRUTTA, YOGURT, DESSERT, BEVANDE, BEVANDE2, GIORNO, TIME_STAMP, PAYMENT " &
            "FROM v_export_xls " &
            "WHERE azienda_slug = @slug AND data_pasto = @data " &
            "ORDER BY BADGE, EMPLOYEE"

        Dim tabella As New DataTable()
        Using cn As New MySqlConnection(connessione)
            Using cmd As New MySqlCommand(sql, cn)
                cmd.Parameters.AddWithValue("@slug", aziendaSlug)
                cmd.Parameters.AddWithValue("@data", dataPasto.ToString("yyyy-MM-dd"))
                Using da As New MySqlDataAdapter(cmd)
                    da.Fill(tabella)
                End Using
            End Using
        End Using
        Return tabella
    End Function

    ''' <summary>
    ''' Uso tipico: non modificare ExportExcel, passa solo il DataTable.
    ''' </summary>
    Public Sub EsempioUso(connessione As String, slug As String, giorno As Date, percorsoXls As String)
        Dim dt As DataTable = CaricaPrenotazioniGiorno(connessione, slug, giorno)
        ' ExportExcel(dt, percorsoXls)   ' ← la tua funzione esistente, invariata
    End Sub

End Module
