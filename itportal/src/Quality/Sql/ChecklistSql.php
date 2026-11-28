<?php

declare(strict_types=1);

namespace ItPortal\Quality\Sql;

/**
 * MySQL checklist queries from function.php (checkdb / lot_check_*).
 * Parameterized — no string concat of user input.
 */
final class ChecklistSql
{
    /** get_check_list_config() */
    public const LIST_LINES = <<<'SQL'
SELECT DISTINCT LINE, MAX(rev) AS REV
  FROM lot_check_config
 GROUP BY LINE
 ORDER BY LINE DESC
SQL;

    /** get_check_list_config_LCS() */
    public const LIST_LINES_LCS = <<<'SQL'
SELECT CONCAT_WS('||', line, cp_link, check_part) AS LINE,
       MAX(CAST(rev AS UNSIGNED)) AS REV
  FROM lot_check_config
 WHERE line LIKE 'LCS%'
 GROUP BY LINE, cp_link, check_part
SQL;

    /** get_check_config() */
    public const CONFIG_ACTIVE = <<<'SQL'
SELECT *
  FROM lot_check_config
 WHERE line = :line
   AND check_part = :check_part
   AND status = 'Y'
   AND rev = :rev
SQL;

    /** view_check_config() */
    public const CONFIG_BY_REV_LIKE = <<<'SQL'
SELECT *
  FROM lot_check_config
 WHERE line = :line
   AND check_part = :check_part
   AND rev LIKE :rev
SQL;

    /** get_value_check() core */
    public const CHECK_VALUES = <<<'SQL'
SELECT *
  FROM lot_check_list
 WHERE line = :line
   AND check_part = :check_part
   AND id_question = :check_name
   AND (time_stamp BETWEEN :date1 AND :date2)
 ORDER BY time_stamp DESC
SQL;

    /** select_ctrl_time() */
    public const CTRL_TIME_LATEST = <<<'SQL'
SELECT *
  FROM lot_ctrl_time
 WHERE line = :line
   AND user_id LIKE :user_id
   AND compiled = :compiled
   AND (start_time BETWEEN :date1 AND :date2)
 ORDER BY start_time DESC
 LIMIT 1
SQL;
}
