-- Skills Exchange Platform - Database Schema
-- MySQL 8.0+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    bio TEXT,
    avatar_url VARCHAR(500),
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    points INT DEFAULT 0,
    api_token VARCHAR(64) NULL UNIQUE,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_api_token (api_token)
) ENGINE=InnoDB;

-- Categories for skills
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB;

-- Skills
CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category_id INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category_id)
) ENGINE=InnoDB;

-- User skills (teach/learn)
CREATE TABLE user_skills (
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    type ENUM('teach', 'learn') NOT NULL,
    proficiency_level TINYINT DEFAULT 1,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, skill_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    INDEX idx_skill_type (skill_id, type)
) ENGINE=InnoDB;

-- Messages
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation (sender_id, receiver_id),
    INDEX idx_receiver (receiver_id, is_read)
) ENGINE=InnoDB;

-- Reviews (one review per reviewer-reviewed pair)
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT NOT NULL,
    reviewed_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_review_pair (reviewer_id, reviewed_id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reviewed (reviewed_id)
) ENGINE=InnoDB;

-- Video calls
CREATE TABLE video_calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caller_id INT NOT NULL,
    callee_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    duration INT NULL,
    status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (callee_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_caller (caller_id),
    INDEX idx_callee (callee_id)
) ENGINE=InnoDB;

-- Badges
CREATE TABLE badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image_url VARCHAR(500),
    criteria TEXT
) ENGINE=InnoDB;

-- User badges
CREATE TABLE user_badges (
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed: basic categories and skills
INSERT INTO categories (name, description) VALUES
('Языки', 'Иностранные языки и лингвистика'),
('Программирование', 'Языки программирования и разработка'),
('Дизайн', 'Графический и веб-дизайн'),
('Музыка', 'Игра на инструментах, вокал'),
('Другое', 'Прочие навыки');

INSERT INTO skills (name, description, category_id) VALUES
('Python', 'Язык программирования Python', 2),
('JavaScript', 'JavaScript и веб-разработка', 2),
('Kotlin', 'Язык программирования Kotlin', 2),
('Французский', 'Французский язык', 1),
('Английский', 'Английский язык', 1),
('Фотошоп', 'Adobe Photoshop', 3),
('Гитара', 'Игра на гитаре', 4);
