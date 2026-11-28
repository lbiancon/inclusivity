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

    /**
     * From functionDashboard.php SELECT_DASHBOARD_LINE (by dashboard id).
     */
    public const DASHBOARD_BY_ID = <<<'SQL'
SELECT ID,
       WC_LINE_CODE,
       WC_MACHINE_GROUP_CODE,
       WC_MACHINE_NUMBER,
       TO_CHAR(START_TIME, 'DD/MM/YYYY HH24:MI') AS START_TIME_STRING,
       TO_CHAR(END_TIME, 'DD/MM/YYYY HH24:MI') AS END_TIME_STRING,
       QTY,
       SCRAP_0, SCRAP_1, SCRAP_2, SCRAP_3, SCRAP_4,
       SCRAP_5, SCRAP_6, SCRAP_7, SCRAP_8, SCRAP_9, SCRAP_10,
       NOTE,
       SCRAP_15, SCRAP_16, SCRAP_17, SCRAP_18, SCRAP_19, SCRAP_20
  FROM DASHBOARD
 WHERE ID LIKE :dash_id
   AND WC_LINE_CODE LIKE :wc_line
 ORDER BY START_TIME ASC
SQL;

    /**
     * From function2.php GET_DESCRIPTION_SCRAP.
     */
    public const SCRAP_DESCRIPTIONS = <<<'SQL'
SELECT DESC_01, DESC_02, DESC_03, DESC_04, DESC_05,
       DESC_06, DESC_07, DESC_08, DESC_09, DESC_10, DESC_11,
       DESC_12, DESC_13, DESC_14, DESC_15, DESC_16, DESC_17, DESC_18, DESC_19
  FROM LOT_PROCESS_VARIABLES_DETAIL
 WHERE MACHINE_TYPE LIKE :machine_type
   AND (DESC_11 LIKE 'NOTE' OR DESC_20 LIKE 'NOTE')
SQL;
}
