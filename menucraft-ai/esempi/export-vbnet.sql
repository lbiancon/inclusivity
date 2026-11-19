-- ============================================================================
-- Compatibilità VB.NET (dataMensa + DatatableToExcel invariate)
-- ============================================================================
-- MenuCraft riempie la tabella `prenotazioni` con lo stesso schema del
-- tuo software attuale. La tua query può restare quasi uguale.
--
-- JSON (export_cucina): solo per il download CSV/XLS dinamico in PHP,
-- in base ai massimi del servizio. Il VB.NET continua sulla tabella piatta.
--
-- ID_PRENOTAZIONE termina sempre con 5 cifre = id giorno (yy + giorno anno).
-- Esempio 2026-11-13 → 24317. In VB: getIdPrenotazione deve restituire
-- lo stesso valore, oppure usa il filtro su data_pasto qui sotto.
-- ============================================================================

-- Query come la tua dataMensa (ospiti / dipendenti):
SELECT
    CODE_EMPLOYEE AS BADGE,
    EMPLOYEE,
    PRIMO, PRIMO2, PRIMO3,
    SECONDO, SECONDO2,
    CONTORNO, CONTORNO2,
    FRUTTA, YOGURT, DESSERT,
    BEVANDE, BEVANDE2,
    GIORNO, TIME_STAMP, PAYMENT
FROM prenotazioni
WHERE RIGHT(ID_PRENOTAZIONE, 5) = ?          -- id giorno a 5 cifre
  AND CODE_EMPLOYEE = 'OSPITE'              -- oppure <> 'OSPITE'
ORDER BY EMPLOYEE ASC;

-- Alternativa più semplice (consigliata se aggiorni solo la WHERE):
-- WHERE data_pasto = 'AAAA-MM-GG'
--   AND azienda_id = ?
--   AND CODE_EMPLOYEE = 'OSPITE'

-- Ospite: CODE_EMPLOYEE = 'OSPITE', EMPL_RIF = matricola del dipendente host.
-- Doppi: Ax2 nella stessa colonna (PRIMO / CONTORNO / …).
