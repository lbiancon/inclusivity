<?php
declare(strict_types=1);

/**
 * Senza questo file il banco usa già MySQL di XAMPP:
 * host 127.0.0.1, porta 3306, database hxs, utente root, password vuota.
 * Copialo in config.local.php solo per cambiare host o password,
 * oppure per passare a Oracle (driver oci) o a un file (driver sqlite).
 *
 * Le tabelle hanno gli stessi nomi e le stesse colonne di Oracle:
 * oggi MySQL, quando spostate eseguite sql/oracle.sql come utente HXSIM.
 * Non puntare oracle_user allo schema di HYDRA 8.
 */
return [
    'driver' => 'mysql',
    'mysql_host' => '127.0.0.1',
    'mysql_port' => 3306,
    'mysql_database' => 'hxs',
    'mysql_user' => 'root',
    'mysql_password' => '',
    'oracle_dsn' => 'oci:dbname=//127.0.0.1:1521/FREEPDB1;charset=AL32UTF8',
    'oracle_user' => 'HXSIM',
    'oracle_password' => 'cambia-questa-password',
];
