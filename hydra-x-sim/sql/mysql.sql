-- Banco X — stesso disegno di sql/oracle.sql, tipi MySQL.
-- L'applicazione lo esegue da sola al primo avvio con driver mysql
-- (crea anche il database). Puoi lanciarlo a mano in phpMyAdmin.
--
-- Quando sposti su Oracle non porti questo file: porti le stesse
-- tabelle e le stesse colonne, con sql/oracle.sql.
-- Non eseguirlo nello schema di HYDRA.

CREATE TABLE IF NOT EXISTS hxs_workplace (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    state VARCHAR(16) NOT NULL,
    ideal_sec INT NOT NULL DEFAULT 90
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hxs_person (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    role VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hxs_order (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    material_code VARCHAR(40) NOT NULL,
    material_name VARCHAR(120) NOT NULL,
    qty_planned INT NOT NULL,
    status VARCHAR(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hxs_operation (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    workplace_id INT NOT NULL,
    seq INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    status VARCHAR(16) NOT NULL,
    started_at VARCHAR(19) NULL,
    finished_at VARCHAR(19) NULL,
    operator_id INT NULL,
    CONSTRAINT fk_hxs_operation_order FOREIGN KEY (order_id) REFERENCES hxs_order (id),
    CONSTRAINT fk_hxs_operation_workplace FOREIGN KEY (workplace_id) REFERENCES hxs_workplace (id),
    CONSTRAINT fk_hxs_operation_person FOREIGN KEY (operator_id) REFERENCES hxs_person (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hxs_booking (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    operation_id INT NOT NULL,
    kind VARCHAR(8) NOT NULL,
    qty INT NOT NULL,
    reason VARCHAR(80) NULL,
    operator_id INT NULL,
    created_at VARCHAR(19) NOT NULL,
    CONSTRAINT fk_hxs_booking_operation FOREIGN KEY (operation_id) REFERENCES hxs_operation (id),
    CONSTRAINT fk_hxs_booking_person FOREIGN KEY (operator_id) REFERENCES hxs_person (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hxs_downtime (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    workplace_id INT NOT NULL,
    reason_code VARCHAR(40) NOT NULL,
    reason_text VARCHAR(80) NOT NULL,
    started_at VARCHAR(19) NOT NULL,
    ended_at VARCHAR(19) NULL,
    CONSTRAINT fk_hxs_downtime_workplace FOREIGN KEY (workplace_id) REFERENCES hxs_workplace (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hxs_signal (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    workplace_id INT NOT NULL,
    `signal` VARCHAR(40) NOT NULL,
    value VARCHAR(80) NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    applied VARCHAR(200) NOT NULL,
    CONSTRAINT fk_hxs_signal_workplace FOREIGN KEY (workplace_id) REFERENCES hxs_workplace (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hxs_service_log (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    service VARCHAR(80) NOT NULL,
    request_json VARCHAR(4000) NOT NULL,
    response_json VARCHAR(4000) NOT NULL,
    ok TINYINT NOT NULL,
    created_at VARCHAR(19) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hxs_list (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(16) NOT NULL UNIQUE,
    title VARCHAR(120) NOT NULL,
    base_object VARCHAR(32) NOT NULL,
    columns_json VARCHAR(4000) NOT NULL,
    filter_json VARCHAR(4000) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
