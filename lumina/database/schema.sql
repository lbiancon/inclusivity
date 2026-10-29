-- Lumina schema. All DATETIME values are UTC instants.
-- Wall-clock hours for a business live in weekly_hours (local time) plus businesses.timezone.

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    phone VARCHAR(40) NULL,
    role ENUM('owner', 'staff', 'client') NOT NULL,
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE businesses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(140) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    category ENUM('gym', 'hair', 'beauty', 'wellness', 'health', 'other') NOT NULL,
    tagline VARCHAR(180) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    address VARCHAR(180) NOT NULL DEFAULT '',
    city VARCHAR(80) NOT NULL DEFAULT '',
    phone VARCHAR(40) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL DEFAULT '',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Rome',
    cancel_hours SMALLINT UNSIGNED NOT NULL DEFAULT 12,
    slot_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    reliability_threshold TINYINT UNSIGNED NOT NULL DEFAULT 70,
    plan ENUM('bottega', 'salone', 'borgo') NOT NULL DEFAULT 'salone',
    plan_interval ENUM('month', 'year') NOT NULL DEFAULT 'month',
    logo_ext VARCHAR(4) NULL,
    accent CHAR(7) NOT NULL DEFAULT '#12151a',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_slug (slug),
    CONSTRAINT fk_business_owner FOREIGN KEY (owner_user_id) REFERENCES users (id),
    CONSTRAINT chk_cancel_hours CHECK (cancel_hours <= 168),
    CONSTRAINT chk_slot_minutes CHECK (slot_minutes BETWEEN 5 AND 60),
    CONSTRAINT chk_threshold CHECK (reliability_threshold BETWEEN 1 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    title VARCHAR(80) NOT NULL DEFAULT '',
    color CHAR(7) NOT NULL DEFAULT '#1f6b45',
    bio VARCHAR(400) NOT NULL DEFAULT '',
    active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_staff_user (user_id),
    KEY ix_staff_business (business_id, active),
    CONSTRAINT fk_staff_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(140) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    duration_min SMALLINT UNSIGNED NOT NULL,
    buffer_before SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    buffer_after SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    price_cents INT UNSIGNED NOT NULL,
    kind ENUM('appointment', 'class') NOT NULL DEFAULT 'appointment',
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY ix_services_business (business_id, active, kind),
    CONSTRAINT fk_services_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT chk_duration CHECK (duration_min BETWEEN 5 AND 480),
    CONSTRAINT chk_buffer CHECK (buffer_before <= 120 AND buffer_after <= 120),
    CONSTRAINT chk_capacity CHECK (capacity BETWEEN 1 AND 80)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    kind ENUM('chair', 'room', 'equipment', 'other') NOT NULL DEFAULT 'other',
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY ix_resources_business (business_id, active),
    CONSTRAINT fk_resources_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT chk_resource_capacity CHECK (capacity BETWEEN 1 AND 40)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_staff (
    service_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (service_id, staff_id),
    CONSTRAINT fk_ss_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE,
    CONSTRAINT fk_ss_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_resources (
    service_id BIGINT UNSIGNED NOT NULL,
    resource_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (service_id, resource_id),
    CONSTRAINT fk_sr_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE,
    CONSTRAINT fk_sr_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE weekly_hours (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    KEY ix_hours (business_id, staff_id, weekday),
    CONSTRAINT fk_hours_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_hours_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
    CONSTRAINT chk_weekday CHECK (weekday BETWEEN 1 AND 7),
    CONSTRAINT chk_hour_order CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE closures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    reason VARCHAR(180) NOT NULL DEFAULT '',
    KEY ix_closures (business_id, starts_at, ends_at),
    CONSTRAINT fk_closures_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_closures_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    preferred_staff_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    notes TEXT NOT NULL,
    preferences VARCHAR(300) NOT NULL DEFAULT '',
    allergies VARCHAR(300) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_email (business_id, email),
    KEY ix_clients_user (user_id),
    CONSTRAINT fk_clients_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_clients_staff FOREIGN KEY (preferred_staff_id) REFERENCES staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE packages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NULL,
    name VARCHAR(140) NOT NULL,
    credits SMALLINT UNSIGNED NOT NULL,
    price_cents INT UNSIGNED NOT NULL,
    valid_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_pkg_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_pkg_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL,
    CONSTRAINT chk_credits CHECK (credits BETWEEN 1 AND 200),
    CONSTRAINT chk_valid_days CHECK (valid_days BETWEEN 1 AND 730)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_packages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED NOT NULL,
    credits_total SMALLINT UNSIGNED NOT NULL,
    credits_left SMALLINT UNSIGNED NOT NULL,
    purchased_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    KEY ix_cp_client (client_id, expires_at),
    CONSTRAINT fk_cp_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_package FOREIGN KEY (package_id) REFERENCES packages (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE series (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    time_start TIME NOT NULL,
    occurrences SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_series_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    resource_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    block_starts_at DATETIME NOT NULL,
    block_ends_at DATETIME NOT NULL,
    status ENUM('confirmed', 'arrived', 'in_progress', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'confirmed',
    price_cents INT UNSIGNED NOT NULL,
    list_price_cents INT UNSIGNED NOT NULL,
    price_label VARCHAR(80) NOT NULL DEFAULT '',
    slot_quality ENUM('ideal', 'adjacent', 'open', 'fragment') NOT NULL DEFAULT 'open',
    deposit_advised TINYINT(1) NOT NULL DEFAULT 0,
    client_package_id BIGINT UNSIGNED NULL,
    series_id BIGINT UNSIGNED NULL,
    public_token CHAR(32) NOT NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    cancel_reason VARCHAR(180) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_appt_token (public_token),
    KEY ix_appt_staff_block (staff_id, block_starts_at, block_ends_at),
    KEY ix_appt_resource_block (resource_id, block_starts_at, block_ends_at),
    KEY ix_appt_business_start (business_id, starts_at),
    KEY ix_appt_client (client_id, starts_at),
    CONSTRAINT fk_appt_business FOREIGN KEY (business_id) REFERENCES businesses (id),
    CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services (id),
    CONSTRAINT fk_appt_staff FOREIGN KEY (staff_id) REFERENCES staff (id),
    CONSTRAINT fk_appt_resource FOREIGN KEY (resource_id) REFERENCES resources (id),
    CONSTRAINT fk_appt_client FOREIGN KEY (client_id) REFERENCES clients (id),
    CONSTRAINT fk_appt_package FOREIGN KEY (client_package_id) REFERENCES client_packages (id),
    CONSTRAINT fk_appt_series FOREIGN KEY (series_id) REFERENCES series (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    resource_id BIGINT UNSIGNED NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    block_starts_at DATETIME NOT NULL,
    block_ends_at DATETIME NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL,
    price_cents INT UNSIGNED NOT NULL,
    status ENUM('scheduled', 'cancelled', 'completed') NOT NULL DEFAULT 'scheduled',
    notes VARCHAR(300) NOT NULL DEFAULT '',
    KEY ix_class_staff (staff_id, block_starts_at, block_ends_at),
    KEY ix_class_resource (resource_id, block_starts_at, block_ends_at),
    KEY ix_class_business (business_id, starts_at),
    CONSTRAINT fk_class_business FOREIGN KEY (business_id) REFERENCES businesses (id),
    CONSTRAINT fk_class_service FOREIGN KEY (service_id) REFERENCES services (id),
    CONSTRAINT fk_class_staff FOREIGN KEY (staff_id) REFERENCES staff (id),
    CONSTRAINT fk_class_resource FOREIGN KEY (resource_id) REFERENCES resources (id),
    CONSTRAINT chk_session_capacity CHECK (capacity BETWEEN 1 AND 80)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    status ENUM('booked', 'cancelled', 'attended', 'no_show') NOT NULL DEFAULT 'booked',
    price_cents INT UNSIGNED NOT NULL,
    price_label VARCHAR(80) NOT NULL DEFAULT '',
    client_package_id BIGINT UNSIGNED NULL,
    public_token CHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_client (session_id, client_id),
    UNIQUE KEY uq_class_token (public_token),
    CONSTRAINT fk_cb_session FOREIGN KEY (session_id) REFERENCES class_sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_cb_client FOREIGN KEY (client_id) REFERENCES clients (id),
    CONSTRAINT fk_cb_package FOREIGN KEY (client_package_id) REFERENCES client_packages (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pricing_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    weekday TINYINT UNSIGNED NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    discount_percent TINYINT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_price_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_price_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE,
    CONSTRAINT chk_discount CHECK (discount_percent BETWEEN 1 AND 90),
    CONSTRAINT chk_price_weekday CHECK (weekday IS NULL OR weekday BETWEEN 1 AND 7),
    CONSTRAINT chk_price_hours CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE waitlist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    preferred_date DATE NOT NULL,
    time_from TIME NOT NULL,
    time_to TIME NOT NULL,
    status ENUM('waiting', 'offered', 'booked', 'expired', 'cancelled') NOT NULL DEFAULT 'waiting',
    offer_starts_at DATETIME NULL,
    offer_expires_at DATETIME NULL,
    appointment_id BIGINT UNSIGNED NULL,
    public_token CHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wait_token (public_token),
    KEY ix_wait (business_id, status, preferred_date),
    CONSTRAINT fk_wait_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_wait_service FOREIGN KEY (service_id) REFERENCES services (id),
    CONSTRAINT fk_wait_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL,
    CONSTRAINT fk_wait_client FOREIGN KEY (client_id) REFERENCES clients (id),
    CONSTRAINT fk_wait_appt FOREIGN KEY (appointment_id) REFERENCES appointments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    body VARCHAR(400) NOT NULL,
    href VARCHAR(240) NOT NULL DEFAULT '',
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_notif_user (user_id, read_at, id),
    CONSTRAINT fk_notif_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    KEY ix_codes_email (email, created_at),
    KEY ix_codes_ip (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nonce CHAR(64) NOT NULL,
    provider ENUM('google', 'apple') NOT NULL,
    verifier VARCHAR(128) NOT NULL,
    nonce_token VARCHAR(64) NOT NULL,
    intent VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    UNIQUE KEY uq_oauth_nonce (nonce)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider ENUM('google', 'apple') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_oauth_subject (provider, subject),
    KEY ix_oauth_user (user_id),
    CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_letters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    KEY ix_letters (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
