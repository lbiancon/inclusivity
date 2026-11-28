<?php

declare(strict_types=1);

namespace ItPortal\Engineering\Sql;

/**
 * Core DASHBOARD scrap/qty SQL from function.php SELECT_REPORT_LINE_SCRAP / SELECT_REPORT_LINE.
 * Full HTML report engines stay out — these are the reusable aggregates.
 */
final class LegacySql
{
    /**
     * Scrap/qty totals for a line id prefix in a time window.
     * Legacy: SUM(QTY), SUM(SCRAP_0..10) FROM DASHBOARD WHERE ID like :line% …
     */
    public const DASHBOARD_SCRAP_SUM = <<<'SQL'
SELECT SUM(QTY) AS QTY,
       SUM(SCRAP_0) AS SCRAP_0,
       SUM(SCRAP_1) AS SCRAP_1,
       SUM(SCRAP_2) AS SCRAP_2,
       SUM(SCRAP_3) AS SCRAP_3,
       SUM(SCRAP_4) AS SCRAP_4,
       SUM(SCRAP_5) AS SCRAP_5,
       SUM(SCRAP_6) AS SCRAP_6,
       SUM(SCRAP_7) AS SCRAP_7,
       SUM(SCRAP_8) AS SCRAP_8,
       SUM(SCRAP_9) AS SCRAP_9,
       SUM(SCRAP_10) AS SCRAP_10
  FROM DASHBOARD
 WHERE ID LIKE :line_id
   AND WC_LINE_CODE LIKE :wc_line
   AND START_TIME >= TO_DATE(:start_ts, 'DD/MM/YYYY HH24:MI:SS')
   AND START_TIME <  TO_DATE(:end_ts, 'DD/MM/YYYY HH24:MI:SS')
SQL;

    /** Shift qty count from SELECT_REPORT_LINE_SCRAP */
    public const DASHBOARD_SHIFT_QTY = <<<'SQL'
SELECT COUNT(*) AS CNT,
       SUM(QTY) AS QTY
  FROM DASHBOARD
 WHERE ID LIKE :line_id
   AND WC_LINE_CODE LIKE :wc_line
   AND START_TIME >= TO_DATE(:start_ts, 'DD/MM/YYYY HH24:MI:SS')
   AND START_TIME <  TO_DATE(:end_ts, 'DD/MM/YYYY HH24:MI:SS')
 GROUP BY WC_LINE_CODE
SQL;

    /** Buoni vs scarti for a shift window */
    public const DASHBOARD_SHIFT_GOOD_SCRAP = <<<'SQL'
SELECT SUM(qty) AS BUONI,
       SUM(SCRAP_0) AS SCARTI
  FROM DASHBOARD
 WHERE ID LIKE :line_id
   AND WC_LINE_CODE LIKE :wc_line
   AND START_TIME >= TO_DATE(:start_ts, 'DD/MM/YYYY HH24:MI:SS')
   AND START_TIME <  TO_DATE(:end_ts, 'DD/MM/YYYY HH24:MI:SS')
SQL;
}
