<?php

declare(strict_types=1);

namespace ItPortal\Tracking\Sql;

/**
 * SQL copied from legacy function.php — same tables/joins/filters, bind params instead of concat.
 * Source line refs refer to the uploaded function.php inventory.
 */
final class LegacySql
{
    /**
     * From PARENT_PART_SN() — genealogy lookup on LOT_SERIAL.
     * Legacy used dynamic $typePartSn column; we whitelist columns.
     */
    public const PARENT_BY_PART_SN = <<<'SQL'
SELECT part,
       part_sn,
       parent_part_sn,
       child_sn,
       op_seq_number,
       TO_CHAR(TIME_STAMP, 'DD-MM-YYYY HH24:MI:SS') AS TIME_STAMP_STRING
  FROM lot_serial
 WHERE part_sn = :part_sn
   AND op_seq_number <> 90
   AND op_seq_number <> 52
   AND parent_part_sn IS NOT NULL
   AND part LIKE :part_like
 ORDER BY time_stamp DESC
SQL;

    public const PARENT_BY_PARENT_PART_SN = <<<'SQL'
SELECT part,
       part_sn,
       parent_part_sn,
       child_sn,
       op_seq_number,
       TO_CHAR(TIME_STAMP, 'DD-MM-YYYY HH24:MI:SS') AS TIME_STAMP_STRING
  FROM lot_serial
 WHERE parent_part_sn = :part_sn
   AND op_seq_number <> 90
   AND op_seq_number <> 52
   AND parent_part_sn IS NOT NULL
   AND part LIKE :part_like
 ORDER BY time_stamp DESC
SQL;

    /**
     * From LOT_SERIAL() — assembly / serial header rows.
     */
    public const LOT_SERIAL_BY_PART_SN = <<<'SQL'
SELECT PART,
       PART_SN,
       PARENT_PART,
       PARENT_PART_SN,
       CONT_ID,
       OP_SEQ_NUMBER,
       CHILD_SN,
       IGNITER_TYPE,
       TRAY_ID,
       CHILD_PART,
       TO_CHAR(TIME_STAMP, 'DD-MM-YYYY HH24:MI:SS') AS TIME_STAMP_STRING
  FROM LOT_SERIAL
 WHERE PART_SN = :part_sn
   AND PART LIKE :part_like
 ORDER BY OP_SEQ_NUMBER ASC
SQL;

    /**
     * From SELECT_PALLET_CONT_AIR() — primary pallet search.
     */
    public const PALLET_BY_PALLET_ID = <<<'SQL'
SELECT LOT_SERIAL.PART,
       LOT_PALLET.PALLET_ID,
       LOT_PALLET.CONT_ID,
       LOT_SERIAL.PART_SN,
       TO_CHAR(LOT_SERIAL.TIME_STAMP, 'DD/MM/YYYY HH24:MI:SS') AS TIME_STAMP
  FROM LOT_PALLET,
       LOT_SERIAL,
       LOT_CURRENT_FINISHED_GOODS
 WHERE (LOT_PALLET.CONT_ID = LOT_SERIAL.CONT_ID)
   AND (LOT_CURRENT_FINISHED_GOODS.PART = LOT_SERIAL.PART)
   AND (LOT_PALLET.CONT_ID = LOT_CURRENT_FINISHED_GOODS.CONT_ID)
   AND (LOT_PALLET.PALLET_ID = :pallet)
   AND (LOT_SERIAL.OP_SEQ_NUMBER = 90)
 ORDER BY LOT_SERIAL.TIME_STAMP ASC
SQL;

    /**
     * From SELECT_PALLET_CONT_AIR() — fallback when searching by CONT_ID.
     */
    public const PALLET_BY_CONT_ID = <<<'SQL'
SELECT LOT_SERIAL.PART,
       LOT_PALLET.PALLET_ID,
       LOT_PALLET.CONT_ID,
       LOT_SERIAL.PART_SN,
       TO_CHAR(LOT_SERIAL.TIME_STAMP, 'DD/MM/YYYY HH24:MI:SS') AS TIME_STAMP
  FROM LOT_PALLET,
       LOT_SERIAL,
       LOT_CURRENT_FINISHED_GOODS
 WHERE (LOT_PALLET.CONT_ID = LOT_SERIAL.CONT_ID)
   AND (LOT_CURRENT_FINISHED_GOODS.PART = LOT_SERIAL.PART)
   AND (LOT_PALLET.CONT_ID = LOT_CURRENT_FINISHED_GOODS.CONT_ID)
   AND (LOT_SERIAL.CONT_ID = :pallet)
   AND (LOT_SERIAL.OP_SEQ_NUMBER = 90)
 ORDER BY LOT_SERIAL.TIME_STAMP ASC
SQL;

    /**
     * From SELECT_PACKOUT() — packout row + later pallet lookup.
     */
    public const PACKOUT_BY_PART_SN = <<<'SQL'
SELECT cont_id,
       part,
       TO_CHAR(TIME_STAMP, 'DD-MM-YYYY HH24:MI:SS') AS TIME_STAMP_STRING
  FROM lot_serial
 WHERE part LIKE :part
   AND part_sn = :part_sn
   AND cont_id LIKE :wc_line
   AND op_seq_number = :op_seq
SQL;

    public const PALLET_ID_BY_CONT = <<<'SQL'
SELECT pallet_id,
       TO_CHAR(TIME_STAMP, 'DD-MM-YYYY HH24:MI:SS') AS TIME_STAMP_STRING_PALLET
  FROM lot_pallet
 WHERE cont_id LIKE :cont_id
SQL;

    /**
     * From CHILD_SN().
     */
    public const CHILD_PARENT_FROM_PART_SN = <<<'SQL'
SELECT PARENT_PART,
       PARENT_PART_SN
  FROM lot_serial
 WHERE part LIKE :part
   AND part_sn = :part_sn
   AND parent_part_sn IS NOT NULL
   AND parent_part_sn <> part_sn
 ORDER BY op_seq_number ASC
SQL;

    /**
     * From SELECT_SINGLE_PROCESS() core — process variables by serial.
     * Table name is chosen by get_name_table_proc (whitelist only).
     */
    public static function processVariables(string $table, bool $extendedFields): string
    {
        $cols = 'PART, PART_SN, WC_LINE_CODE, WC_MACHINE_GROUP_CODE, WC_MACHINE_NUMBER, MACHINE_TYPE,
                 TO_CHAR(TIME_STAMP, \'DD-MM-YYYY HH24:MI:SS\') AS TIME_STAMP_STRING, TIME_STAMP,
                 VALUE_01, VALUE_02, VALUE_03, VALUE_04, VALUE_05,
                 VALUE_06, VALUE_07, VALUE_08, VALUE_09, VALUE_10';
        if ($extendedFields) {
            $cols .= ', VALUE_11, VALUE_12, VALUE_13, VALUE_14, VALUE_15,
                       VALUE_16, VALUE_17, VALUE_18, VALUE_19, VALUE_20,
                       VALUE_21, VALUE_22, VALUE_23, VALUE_24, VALUE_25';
        }

        return "SELECT {$cols} FROM {$table} WHERE part_sn LIKE :part_sn ORDER BY time_stamp ASC";
    }

    /** Whitelist from get_name_table_proc(). */
    public static function processTable(string $type): string
    {
        return match ($type) {
            '0' => 'LOT_PROCESS_VARIABLES',
            '1' => 'LOT_PROCESS_VARIABLES_HIST',
            '2' => 'LOT_PROCESS_VARIABLES_ACI',
            '3' => 'LOT_PROCESS_VARIABLES_AB',
            default => 'LOT_PROCESS_VARIABLES',
        };
    }
}
