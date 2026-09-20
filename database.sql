CREATE DATABASE IF NOT EXISTS smart_tourism_ai
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE smart_tourism_ai;

CREATE TABLE IF NOT EXISTS app_users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_name VARCHAR(120) NOT NULL,
    email_address VARCHAR(180) NOT NULL UNIQUE,
    passwd_hash VARCHAR(255) NOT NULL,
    admin_flag TINYINT(1) NOT NULL DEFAULT 0,
    created_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_threads (
    thread_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    conversation_key VARCHAR(64) NOT NULL UNIQUE,
    conversation_title VARCHAR(255) NOT NULL,
    created_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_threads_user_updated (user_id, updated_on),
    CONSTRAINT fk_threads_user FOREIGN KEY (user_id) REFERENCES app_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_entries (
    entry_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_key VARCHAR(64) NOT NULL,
    sender_role ENUM('user','bot') NOT NULL,
    message_body TEXT NOT NULL,
    created_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entries_conv_created (conversation_key, created_on),
    INDEX idx_entries_sender (sender_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tour_districts (
    district_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    district_title VARCHAR(120) NOT NULL UNIQUE,
    created_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tour_places (
    place_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    district_id INT UNSIGNED NOT NULL,
    place_title VARCHAR(150) NOT NULL,
    place_type VARCHAR(80) NOT NULL,
    place_description TEXT NOT NULL,
    best_season VARCHAR(100) NOT NULL,
    average_budget_lkr INT(11) NOT NULL,
    created_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_places_district (district_id),
    CONSTRAINT fk_places_district FOREIGN KEY (district_id) REFERENCES tour_districts(district_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_users (display_name, email_address, passwd_hash, admin_flag)
VALUES ('System Admin', 'admin@travelai.local', '$2y$10$IIuK3Y1HPedNzexZEAaBfO6O3bbZxz4AabapTdEAO4ApwhmhBMsMW', 1)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    passwd_hash = VALUES(passwd_hash),
    admin_flag = VALUES(admin_flag);
