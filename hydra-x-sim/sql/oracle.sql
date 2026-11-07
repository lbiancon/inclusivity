-- Banco X — schema didattico per un utente Oracle DEDICATO.
-- Non eseguirlo nello schema di HYDRA 8.
--
-- Come DBA, una volta sola:
--   CREATE USER HXSIM IDENTIFIED BY "una-password";
--   GRANT CREATE SESSION, CREATE TABLE, CREATE SEQUENCE, CREATE TRIGGER TO HXSIM;
--   ALTER USER HXSIM QUOTA UNLIMITED ON USERS;
--
-- Poi, connesso come HXSIM (SQL*Plus o SQLcl):
--   @sql/oracle.sql
--
-- Le tabelle nascono vuote. Al primo avvio l'applicazione inserisce
-- le anagrafiche di esempio (tornio, fresatrice, due ordini).
-- Compatibile con Oracle 11g e successivi: niente IDENTITY.

CREATE SEQUENCE hxs_workplace_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE hxs_person_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE hxs_order_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE hxs_operation_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE hxs_booking_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE hxs_downtime_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE hxs_signal_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE hxs_service_log_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE hxs_list_seq START WITH 1 INCREMENT BY 1;

CREATE TABLE hxs_workplace (
    id NUMBER PRIMARY KEY,
    code VARCHAR2(32) NOT NULL UNIQUE,
    name VARCHAR2(120) NOT NULL,
    state VARCHAR2(16) NOT NULL,
    ideal_sec NUMBER(6) DEFAULT 90 NOT NULL
);

CREATE TABLE hxs_person (
    id NUMBER PRIMARY KEY,
    code VARCHAR2(32) NOT NULL UNIQUE,
    name VARCHAR2(120) NOT NULL,
    role VARCHAR2(40) NOT NULL
);

CREATE TABLE hxs_order (
    id NUMBER PRIMARY KEY,
    code VARCHAR2(32) NOT NULL UNIQUE,
    material_code VARCHAR2(40) NOT NULL,
    material_name VARCHAR2(120) NOT NULL,
    qty_planned NUMBER(10) NOT NULL,
    status VARCHAR2(16) NOT NULL
);

CREATE TABLE hxs_operation (
    id NUMBER PRIMARY KEY,
    code VARCHAR2(40) NOT NULL UNIQUE,
    order_id NUMBER NOT NULL REFERENCES hxs_order (id),
    workplace_id NUMBER NOT NULL REFERENCES hxs_workplace (id),
    seq NUMBER(4) NOT NULL,
    name VARCHAR2(80) NOT NULL,
    status VARCHAR2(16) NOT NULL,
    started_at VARCHAR2(19),
    finished_at VARCHAR2(19),
    operator_id NUMBER REFERENCES hxs_person (id)
);

CREATE TABLE hxs_booking (
    id NUMBER PRIMARY KEY,
    operation_id NUMBER NOT NULL REFERENCES hxs_operation (id),
    kind VARCHAR2(8) NOT NULL,
    qty NUMBER(10) NOT NULL,
    reason VARCHAR2(80),
    operator_id NUMBER REFERENCES hxs_person (id),
    created_at VARCHAR2(19) NOT NULL
);

CREATE TABLE hxs_downtime (
    id NUMBER PRIMARY KEY,
    workplace_id NUMBER NOT NULL REFERENCES hxs_workplace (id),
    reason_code VARCHAR2(40) NOT NULL,
    reason_text VARCHAR2(80) NOT NULL,
    started_at VARCHAR2(19) NOT NULL,
    ended_at VARCHAR2(19)
);

CREATE TABLE hxs_signal (
    id NUMBER PRIMARY KEY,
    workplace_id NUMBER NOT NULL REFERENCES hxs_workplace (id),
    signal VARCHAR2(40) NOT NULL,
    value VARCHAR2(80) NOT NULL,
    created_at VARCHAR2(19) NOT NULL,
    applied VARCHAR2(200) NOT NULL
);

CREATE TABLE hxs_service_log (
    id NUMBER PRIMARY KEY,
    service VARCHAR2(80) NOT NULL,
    request_json VARCHAR2(4000) NOT NULL,
    response_json VARCHAR2(4000) NOT NULL,
    ok NUMBER(1) NOT NULL,
    created_at VARCHAR2(19) NOT NULL
);

CREATE TABLE hxs_list (
    id NUMBER PRIMARY KEY,
    code VARCHAR2(16) NOT NULL UNIQUE,
    title VARCHAR2(120) NOT NULL,
    base_object VARCHAR2(32) NOT NULL,
    columns_json VARCHAR2(4000) NOT NULL,
    filter_json VARCHAR2(4000) NOT NULL
);

CREATE OR REPLACE TRIGGER hxs_workplace_bi
BEFORE INSERT ON hxs_workplace FOR EACH ROW
WHEN (new.id IS NULL)
BEGIN
    SELECT hxs_workplace_seq.NEXTVAL INTO :new.id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER hxs_person_bi
BEFORE INSERT ON hxs_person FOR EACH ROW
WHEN (new.id IS NULL)
BEGIN
    SELECT hxs_person_seq.NEXTVAL INTO :new.id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER hxs_order_bi
BEFORE INSERT ON hxs_order FOR EACH ROW
WHEN (new.id IS NULL)
BEGIN
    SELECT hxs_order_seq.NEXTVAL INTO :new.id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER hxs_operation_bi
BEFORE INSERT ON hxs_operation FOR EACH ROW
WHEN (new.id IS NULL)
BEGIN
    SELECT hxs_operation_seq.NEXTVAL INTO :new.id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER hxs_booking_bi
BEFORE INSERT ON hxs_booking FOR EACH ROW
WHEN (new.id IS NULL)
BEGIN
    SELECT hxs_booking_seq.NEXTVAL INTO :new.id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER hxs_downtime_bi
BEFORE INSERT ON hxs_downtime FOR EACH ROW
WHEN (new.id IS NULL)
BEGIN
    SELECT hxs_downtime_seq.NEXTVAL INTO :new.id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER hxs_signal_bi
BEFORE INSERT ON hxs_signal FOR EACH ROW
WHEN (new.id IS NULL)
BEGIN
    SELECT hxs_signal_seq.NEXTVAL INTO :new.id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER hxs_service_log_bi
BEFORE INSERT ON hxs_service_log FOR EACH ROW
WHEN (new.id IS NULL)
BEGIN
    SELECT hxs_service_log_seq.NEXTVAL INTO :new.id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER hxs_list_bi
BEFORE INSERT ON hxs_list FOR EACH ROW
WHEN (new.id IS NULL)
BEGIN
    SELECT hxs_list_seq.NEXTVAL INTO :new.id FROM dual;
END;
/
