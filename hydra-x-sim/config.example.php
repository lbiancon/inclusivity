<?php
declare(strict_types=1);

/**
 * Copia questo file in config.local.php solo se vuoi Oracle.
 * Senza quel file il banco usa SQLite in data/sim.sqlite.
 *
 * Non puntare oracle_user allo schema di HYDRA 8.
 * Crea un utente dedicato ed esegui sql/oracle.sql.
 */
return [
    'driver' => 'oci',
    'oracle_dsn' => 'oci:dbname=//127.0.0.1:1521/FREEPDB1;charset=AL32UTF8',
    'oracle_user' => 'HXSIM',
    'oracle_password' => 'cambia-questa-password',
];
