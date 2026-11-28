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

    /** get_login_check() */
    public const LOGIN_CHECK = <<<'SQL'
SELECT *
  FROM lot_user_check
 WHERE line = :line
   AND checkname = :checkname
   AND user_id LIKE :user_id
   AND (time_stamp BETWEEN :date1 AND :date2)
 ORDER BY time_stamp DESC
 LIMIT 3
SQL;

    /** insert_login_check() — deactivate previous */
    public const DEACTIVATE_LINE_LOGINS = <<<'SQL'
UPDATE lot_user_check
   SET status_flag = 'N'
 WHERE line = :line
   AND checkname = :checkname
SQL;

    /** insert_login_check() */
    public const INSERT_LOGIN = <<<'SQL'
INSERT INTO lot_user_check
  (user_id, empl_last, empl_first, status_flag, roles, checkname, line, rev)
VALUES
  (:user_id, :empl_last, :empl_first, 'Y', :roles, :checkname, :line, :rev)
SQL;

    /** logout() for checklist operators */
    public const LOGOUT_LINE = <<<'SQL'
UPDATE lot_user_check
   SET status_flag = 'N'
 WHERE line = :line
   AND checkname = :checkname
SQL;

    /** save_check() */
    public const SAVE_CHECK = <<<'SQL'
INSERT INTO lot_check_list
  (user_id, line, id_question, check_part, check_value, status, note, question, rev)
VALUES
  (:user_id, :line, :id_question, :check_part, :check_value, :status, :note, :question, :rev)
SQL;

    /** Oracle lot_employee lookup used by insert_login_check */
    public const EMPLOYEE_BY_BADGE = <<<'SQL'
SELECT EMPL_LAST, EMPL_FIRST, SUPERUSER, ROLES, TIP
  FROM lot_employee
 WHERE USER_ID = :user_id
SQL;
}
