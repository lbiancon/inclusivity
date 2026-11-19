' Compatibile con dataMensa attuale: stessa SELECT su tabella prenotazioni.
' DatatableToExcel non va toccata: riceve lo stesso DataTable di colonne.

Imports MySql.Data.MySqlClient
Imports System.Data

Public Module ExportCucinaDataTable

    ''' <summary>
    ''' Equivalente di dataMensa: _opt = "='OSPITE'" oppure "&lt;&gt;'OSPITE'"
    ''' idGiorno = RIGHT(ID_PRENOTAZIONE,5) = yy + giorno dell'anno (es. 13/11/2026 → 24317)
    ''' </summary>
    Public Function dataMensa(
        connessione As String,
        idGiorno As String,
        ByVal _opt As String
    ) As DataTable

        Dim sql As String =
            "SELECT CODE_EMPLOYEE as BADGE,EMPLOYEE,PRIMO,PRIMO2,PRIMO3,SECONDO,SECONDO2," &
            "CONTORNO,CONTORNO2,FRUTTA,YOGURT,DESSERT,BEVANDE,BEVANDE2,GIORNO,TIME_STAMP,PAYMENT " &
            "FROM prenotazioni WHERE RIGHT(ID_PRENOTAZIONE , 5) = @id " &
            "AND CODE_EMPLOYEE " & _opt & " ORDER BY EMPLOYEE ASC"

        Dim dt As New DataTable("prenotazioni")
        Using cn As New MySqlConnection(connessione)
            Using cmd As New MySqlCommand(sql, cn)
                cmd.Parameters.AddWithValue("@id", idGiorno)
                Using da As New MySqlDataAdapter(cmd)
                    da.Fill(dt)
                End Using
            End Using
        End Using
        Return dt
    End Function

    ''' <summary>
    ''' Calcolo id giorno allineato a MenuCraft (ExportCucina::idGiornoPasto).
    ''' Usa questo in getIdPrenotazione se vuoi restare su RIGHT(ID,5).
    ''' </summary>
    Public Function idGiornoPasto(giorno As Date) As String
        Return giorno.ToString("yy") & (giorno.DayOfYear).ToString("000")
    End Function

End Module
