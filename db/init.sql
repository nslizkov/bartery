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


-- Дефолтные значения
-- ==========================
-- Категории
-- ==========================
INSERT INTO categories (name, description) VALUES
('Языки', 'Иностранные языки и лингвистика'),
('Программирование', 'Языки программирования и разработка'),
('Дизайн', 'Графический и веб-дизайн'),
('Музыка', 'Игра на инструментах, вокал'),
('Спорт', 'Физическая активность, фитнес, боевые искусства'),
('Бизнес', 'Управление, маркетинг, финансы, стартапы'),
('Кулинария', 'Приготовление пищи, кондитерское дело'),
('Фотография', 'Фотография и обработка изображений'),
('Видео', 'Монтаж видео, съемка, анимация'),
('Математика', 'Математика и аналитика'),
('Психология', 'Личностное развитие и психология'),
('Ремесла', 'Хендмейд, DIY, рукоделие'),
('Игры', 'Настольные игры, шахматы, видеоигры'),
('Наука', 'Физика, химия, биология, астрономия'),
('Маркетинг и соцсети', 'Продвижение брендов, SMM, контент');

-- ==========================
-- Навыки
-- ==========================
INSERT INTO skills (name, description, category_id, created_by) VALUES
-- Языки
('Английский', 'Английский язык', 1, NULL),
('Французский', 'Французский язык', 1, NULL),
('Испанский', 'Испанский язык', 1, NULL),
('Немецкий', 'Немецкий язык', 1, NULL),
('Китайский', 'Китайский язык', 1, NULL),
('Японский', 'Японский язык', 1, NULL),
('Русский для иностранцев', 'Преподавание русского', 1, NULL),

-- Программирование
('Python', 'Язык программирования Python', 2, NULL),
('JavaScript', 'JavaScript и веб-разработка', 2, NULL),
('Kotlin', 'Язык программирования Kotlin', 2, NULL),
('Java', 'Язык программирования Java', 2, NULL),
('C++', 'Программирование на C++', 2, NULL),
('C#', 'Программирование на C#', 2, NULL),
('Go', 'Язык программирования Go', 2, NULL),
('SQL', 'Работа с базами данных', 2, NULL),
('HTML/CSS', 'Веб-разметка и стили', 2, NULL),
('React', 'Frontend на React', 2, NULL),
('Node.js', 'Backend на Node.js', 2, NULL),

-- Дизайн
('Фотошоп', 'Adobe Photoshop', 3, NULL),
('Иллюстратор', 'Adobe Illustrator', 3, NULL),
('UI/UX дизайн', 'Проектирование интерфейсов', 3, NULL),
('Моушн графика', 'Анимация и графика', 3, NULL),
('Веб-дизайн', 'Создание сайтов', 3, NULL),
('3D-моделирование', 'Blender и другие программы', 3, NULL),

-- Музыка
('Гитара', 'Игра на гитаре', 4, NULL),
('Пианино', 'Игра на фортепиано', 4, NULL),
('Вокал', 'Пение и вокальные техники', 4, NULL),
('Скрипка', 'Игра на скрипке', 4, NULL),
('Барабаны', 'Игра на ударных', 4, NULL),
('Музыкальная теория', 'Основы композиции и гармонии', 4, NULL),

-- Спорт
('Йога', 'Практика йоги', 5, NULL),
('Плавание', 'Навыки плавания', 5, NULL),
('Футбол', 'Игровые навыки в футболе', 5, NULL),
('Бег', 'Техника бега и тренировки', 5, NULL),
('Боевые искусства', 'Каратэ, тхэквондо и др.', 5, NULL),
('Фитнес', 'Общие тренировки и упражнения', 5, NULL),

-- Бизнес
('Управление проектами', 'Методологии управления проектами', 6, NULL),
('Маркетинг', 'Продвижение продуктов и брендов', 6, NULL),
('Финансовый анализ', 'Анализ финансовых показателей', 6, NULL),
('Стартапы', 'Запуск и ведение стартапов', 6, NULL),
('Продажи', 'Навыки эффективных продаж', 6, NULL),
('Лидерство', 'Развитие навыков управления людьми', 6, NULL),

-- Кулинария
('Итальянская кухня', 'Приготовление итальянских блюд', 7, NULL),
('Выпечка', 'Кондитерские изделия и хлеб', 7, NULL),
('Суши', 'Приготовление японских суши', 7, NULL),
('Веганская кухня', 'Рецепты без продуктов животного происхождения', 7, NULL),
('Бариста', 'Приготовление кофе и напитков', 7, NULL),

-- Фотография
('Портретная фотография', 'Съемка портретов', 8, NULL),
('Пейзажная фотография', 'Съемка природы и городов', 8, NULL),
('Обработка фото в Lightroom', 'Редактирование фотографий', 8, NULL),
('Обработка фото в Photoshop', 'Редактирование фотографий', 8, NULL),

-- Видео
('Монтаж видео', 'Работа с Adobe Premiere и другими программами', 9, NULL),
('Анимация', '2D и 3D анимация', 9, NULL),
('Съемка видео', 'Техника съемки и композиция', 9, NULL),
('YouTube контент', 'Создание контента для YouTube', 9, NULL),

-- Математика
('Алгебра', 'Базовая и продвинутая алгебра', 10, NULL),
('Геометрия', 'Геометрические задачи', 10, NULL),
('Статистика', 'Сбор, анализ и визуализация данных', 10, NULL),
('Математический анализ', 'Пределы, интегралы, производные', 10, NULL),
('Логика', 'Развитие логического мышления', 10, NULL),

-- Психология
('Эмоциональный интеллект', 'Развитие навыков эмоционального интеллекта', 11, NULL),
('Психология общения', 'Навыки общения и убеждения', 11, NULL),
('Мотивация', 'Методы самоподдержки и мотивации', 11, NULL),
('Терапия', 'Основы психологической помощи', 11, NULL),

-- Ремесла
('Вязание', 'Вязание спицами и крючком', 12, NULL),
('Рисование', 'Рисование карандашом и красками', 12, NULL),
('Декупаж', 'Украшение предметов с помощью техники декупаж', 12, NULL),
('Скрапбукинг', 'Создание фотокниг и открыток', 12, NULL),
('Моделирование', 'Создание моделей из бумаги и пластика', 12, NULL),

-- Игры
('Шахматы', 'Настольная игра', 13, NULL),
('Покер', 'Настольная карточная игра', 13, NULL),
('Видеоигры', 'Разные жанры видеоигр', 13, NULL),
('Настольные RPG', 'Ролевые настольные игры', 13, NULL),

-- Наука
('Физика', 'Основы физики', 14, NULL),
('Химия', 'Основы химии', 14, NULL),
('Биология', 'Основы биологии', 14, NULL),
('Астрономия', 'Изучение космоса', 14, NULL),

-- Маркетинг и соцсети
('SMM', 'Продвижение в социальных сетях', 15, NULL),
('Контент-маркетинг', 'Создание контента для продвижения', 15, NULL),
('SEO', 'Оптимизация сайтов для поисковых систем', 15, NULL),
('Таргетированная реклама', 'Реклама в соцсетях и Google Ads', 15, NULL);

-- ==========================
-- Дефолтные пользователи (для отладки)
-- ==========================
INSERT INTO users (username, email, password_hash, full_name, bio, role, points)
VALUES
('alice', 'alice@example.com', 'hashed_pass1', 'Alice Smith', 'Люблю обучать языкам и программированию', 'user', 50),
('bob', 'bob@example.com', 'hashed_pass2', 'Bob Johnson', 'Музыка и спорт — моя страсть', 'user', 40),
('carol', 'carol@example.com', 'hashed_pass3', 'Carol Williams', 'Дизайн и видео', 'user', 60),
('dave', 'dave@example.com', 'hashed_pass4', 'Dave Brown', 'Финансы, стартапы и аналитика', 'user', 70),
('eve', 'eve@example.com', 'hashed_pass5', 'Eve Davis', 'Психология, кулинария и ремесла', 'user', 30);

-- ==========================
-- Навыки пользователей
-- ==========================
-- Alice
INSERT INTO user_skills (user_id, skill_id, type, proficiency_level) VALUES
(1, 1, 'teach', 5), -- Английский
(1, 2, 'teach', 4), -- Французский
(1, 8, 'teach', 5), -- Python
(1, 9, 'learn', 2); -- JavaScript

-- Bob
INSERT INTO user_skills (user_id, skill_id, type, proficiency_level) VALUES
(2, 24, 'teach', 4), -- Гитара
(2, 25, 'teach', 3), -- Пианино
(2, 29, 'learn', 2), -- Йога
(2, 28, 'learn', 1); -- Футбол

-- Carol
INSERT INTO user_skills (user_id, skill_id, type, proficiency_level) VALUES
(3, 14, 'teach', 5), -- Фотошоп
(3, 15, 'teach', 4), -- Иллюстратор
(3, 31, 'teach', 4), -- Монтаж видео
(3, 33, 'learn', 2); -- YouTube контент

-- Dave
INSERT INTO user_skills (user_id, skill_id, type, proficiency_level) VALUES
(4, 35, 'teach', 5), -- Управление проектами
(4, 36, 'teach', 4), -- Маркетинг
(4, 38, 'learn', 2), -- Стартапы
(4, 37, 'learn', 3); -- Финансовый анализ

-- Eve
INSERT INTO user_skills (user_id, skill_id, type, proficiency_level) VALUES
(5, 58, 'teach', 4), -- Эмоциональный интеллект
(5, 61, 'teach', 3), -- Мотивация
(5, 43, 'learn', 2), -- Выпечка
(5, 44, 'learn', 1); -- Суши