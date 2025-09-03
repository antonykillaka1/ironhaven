-- setup/database.sql
CREATE DATABASE IF NOT EXISTS ironhaven_game CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ironhaven_game;

-- Tabella giocatori
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    level INT NOT NULL DEFAULT 1,
    experience INT NOT NULL DEFAULT 0,
    fame INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_admin TINYINT(1) DEFAULT 0
);

-- Tabella insediamenti
CREATE TABLE settlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    founded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    coordinates_x INT NOT NULL,
    coordinates_y INT NOT NULL,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
);

-- Tabella risorse
CREATE TABLE resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    settlement_id INT NOT NULL,
    wood INT NOT NULL DEFAULT 200,
    stone INT NOT NULL DEFAULT 200,
    food INT NOT NULL DEFAULT 200,
    water INT NOT NULL DEFAULT 200,
    iron INT NOT NULL DEFAULT 0,
    gold INT NOT NULL DEFAULT 0,
    coal INT NOT NULL DEFAULT 0,
    steel INT NOT NULL DEFAULT 0,
    coins INT NOT NULL DEFAULT 0,
    jewels INT NOT NULL DEFAULT 0,
    potions INT NOT NULL DEFAULT 0,
    crystals INT NOT NULL DEFAULT 0,
    premium_meat INT NOT NULL DEFAULT 0,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (settlement_id) REFERENCES settlements(id) ON DELETE CASCADE
);

-- Tabella edifici
CREATE TABLE buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    settlement_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    level INT NOT NULL DEFAULT 1,
    position_x INT NOT NULL,
    position_y INT NOT NULL,
    construction_started TIMESTAMP NULL,
    construction_ends TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (settlement_id) REFERENCES settlements(id) ON DELETE CASCADE
);

-- Tabella abitanti
CREATE TABLE population (
    id INT AUTO_INCREMENT PRIMARY KEY,
    settlement_id INT NOT NULL,
    total INT NOT NULL DEFAULT 5,
    workers INT NOT NULL DEFAULT 0,
    available INT NOT NULL DEFAULT 5,
    satisfaction FLOAT NOT NULL DEFAULT 100.0,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (settlement_id) REFERENCES settlements(id) ON DELETE CASCADE
);

-- Tabella missioni
CREATE TABLE missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    fame_cost INT NOT NULL DEFAULT 0,
    experience_reward INT NOT NULL DEFAULT 0,
    fame_reward INT NOT NULL DEFAULT 0,
    resource_rewards JSON NULL,
    special_rewards JSON NULL,
    difficulty ENUM('easy', 'medium', 'hard', 'epic') NOT NULL,
    duration_hours INT NOT NULL,
    unlock_level INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1
);

-- Tabella missioni giocatore
CREATE TABLE player_missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    mission_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ends_at TIMESTAMP NULL,
    status ENUM('active', 'completed', 'failed') DEFAULT 'active',
    rewards_claimed TINYINT(1) DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (mission_id) REFERENCES missions(id)
);

-- Tabella log di gioco
CREATE TABLE game_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NULL,
    settlement_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_data JSON NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL,
    FOREIGN KEY (settlement_id) REFERENCES settlements(id) ON DELETE SET NULL
);