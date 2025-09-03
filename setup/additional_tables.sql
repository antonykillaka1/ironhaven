-- setup/additional_tables.sql

-- Tabella per gli effetti temporanei
CREATE TABLE temporary_effects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    settlement_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    amount FLOAT NOT NULL,
    starts_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (settlement_id) REFERENCES settlements(id) ON DELETE CASCADE
);

-- Tabella per le notifiche
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
);

-- Tabella per gli oggetti speciali (drop leggendari)
CREATE TABLE special_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    settlement_id INT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    description TEXT NULL,
    bonus_type VARCHAR(50) NULL,
    bonus_amount FLOAT NULL,
    is_equipped TINYINT(1) DEFAULT 0,
    obtained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (settlement_id) REFERENCES settlements(id) ON DELETE SET NULL
);

-- Tabella per le relazioni diplomatiche
CREATE TABLE diplomatic_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    target_player_id INT NOT NULL,
    relation_type ENUM('ally', 'neutral', 'enemy') DEFAULT 'neutral',
    alliance_date TIMESTAMP NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (target_player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY (player_id, target_player_id)
);

-- Tabella per le missioni speciali (sbloccate con fama)
CREATE TABLE special_missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    fame_cost INT NOT NULL DEFAULT 0,
    duration_hours INT NOT NULL,
    mission_type ENUM('exploration', 'defense', 'diplomatic', 'trade', 'technological') NOT NULL,
    difficulty ENUM('normal', 'rare', 'legendary') NOT NULL,
    rewards JSON NOT NULL,
    unlock_level INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1
);
-- Aggiungi queste linee alla fine del file setup/additional_tables.sql
ALTER TABLE buildings ADD COLUMN zone_type VARCHAR(50);
ALTER TABLE buildings ADD COLUMN slot_index INT;
ALTER TABLE buildings ADD COLUMN zone_x INT DEFAULT 0;
ALTER TABLE buildings ADD COLUMN zone_y INT DEFAULT 0;