-- SQL-дамп для создания базы данных TableSpot
-- Требования: MySQL 8+

CREATE DATABASE IF NOT EXISTS tablespot
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE tablespot;

-- Пользователи
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  email_marketing TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Рестораны
CREATE TABLE IF NOT EXISTS restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  address VARCHAR(255) NOT NULL,
  description TEXT,
  cuisine_type VARCHAR(50),
  image VARCHAR(255),
  city VARCHAR(100) NOT NULL DEFAULT 'Курск',
  rating DECIMAL(2,1) DEFAULT 4.5,
  lat DECIMAL(10,7) DEFAULT NULL,
  lng DECIMAL(10,7) DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  work_hours_start TIME DEFAULT '10:00:00',
  work_hours_end TIME DEFAULT '23:00:00',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Столики
CREATE TABLE IF NOT EXISTS `tables` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  table_number INT NOT NULL,
  capacity INT NOT NULL,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_restaurant_table_number (restaurant_id, table_number)
);

-- Бронирования
CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  table_id INT NOT NULL,
  booking_date DATE NOT NULL,
  time_start TIME NOT NULL,
  time_end TIME NOT NULL,
  guests_count INT NOT NULL,
  status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (table_id) REFERENCES `tables`(id) ON DELETE CASCADE,
  KEY idx_booking_date_table (booking_date, table_id)
);

-- Администраторы (каждый админ привязан к одному ресторану)
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  login VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
);

-- Баннеры
CREATE TABLE IF NOT EXISTS banners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  subtitle VARCHAR(300) DEFAULT NULL,
  button_text VARCHAR(100) DEFAULT NULL,
  button_url VARCHAR(500) DEFAULT NULL,
  bg_color VARCHAR(20) DEFAULT '#2D6A4F',
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Сброс пароля
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_token (token),
  KEY idx_email (email)
);

-- Избранное
CREATE TABLE IF NOT EXISTS favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  restaurant_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_restaurant (user_id, restaurant_id)
);

-- Заявки на размещение
CREATE TABLE IF NOT EXISTS placement_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_name VARCHAR(200) NOT NULL,
  contact_name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(100) NOT NULL,
  city VARCHAR(100) DEFAULT 'Курск',
  address VARCHAR(255) DEFAULT NULL,
  message TEXT DEFAULT NULL,
  status ENUM('new','reviewed','approved','rejected') DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================
-- Тестовые данные
-- =====================

-- 5 ресторанов Курска
INSERT INTO restaurants (id, name, address, description, cuisine_type, image, city, rating, lat, lng, is_active, work_hours_start, work_hours_end)
VALUES
  (1, 'Итальяно', 'ул. Ленина, 15', 'Уютный итальянский ресторан с домашней пастой и пиццей на дровах', 'Итальянская', 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=600', 'Курск', 4.8, 51.7305000, 36.1918000, 1, '10:00:00', '23:00:00'),
  (2, 'Сакура', 'ул. Кирова, 42', 'Аутентичная японская кухня: суши, роллы, рамен', 'Японская', 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600', 'Курск', 4.7, 51.7274000, 36.1879000, 1, '10:00:00', '23:00:00'),
  (3, 'Бургер Хаус', 'пр. Победы, 8', 'Сочные бургеры из фермерской говядины и крафтовые напитки', 'Американская', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600', 'Курск', 4.6, 51.7390000, 36.1862000, 1, '10:00:00', '23:00:00'),
  (4, 'Курская антоновка', 'ул. Дзержинского, 28', 'Ресторан региональной кухни Курской области. Фирменные блюда с антоновскими яблоками, местные продукты и авторская подача', 'Русская', 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600', 'Курск', 4.9, 51.7320000, 36.2001000, 1, '10:00:00', '23:00:00'),
  (5, 'Пивной дворик', 'ул. Гайдара, 3', 'Европейская кухня, крафтовое пиво и уютная атмосфера', 'Европейская', 'https://images.unsplash.com/photo-1514933651103-005eec06c04b?w=600', 'Курск', 4.5, 51.7350000, 36.1950000, 1, '11:00:00', '00:00:00')
ON DUPLICATE KEY UPDATE
  name=VALUES(name),
  address=VALUES(address),
  description=VALUES(description),
  cuisine_type=VALUES(cuisine_type),
  image=VALUES(image),
  city=VALUES(city),
  rating=VALUES(rating),
  lat=VALUES(lat),
  lng=VALUES(lng),
  is_active=VALUES(is_active),
  work_hours_start=VALUES(work_hours_start),
  work_hours_end=VALUES(work_hours_end);

-- Столики
INSERT INTO `tables` (restaurant_id, table_number, capacity)
VALUES
  (1, 1, 2), (1, 2, 2), (1, 3, 4), (1, 4, 4), (1, 5, 6),
  (2, 1, 2), (2, 2, 4), (2, 3, 4), (2, 4, 6),
  (3, 1, 2), (3, 2, 2), (3, 3, 4), (3, 4, 8),
  (4, 1, 2), (4, 2, 4), (4, 3, 4), (4, 4, 6), (4, 5, 8),
  (5, 1, 2), (5, 2, 2), (5, 3, 4), (5, 4, 6)
ON DUPLICATE KEY UPDATE
  capacity=VALUES(capacity);

-- Администраторы (пароль по умолчанию: admin123)
INSERT INTO admins (restaurant_id, login, password_hash)
VALUES
  (1, 'admin_italiano',  '$2y$12$KH1ZEnkXEnFSd6gBpydNz.2DGHZyKDBwM3N.ibGub.2iw6t5UJSjS'),
  (2, 'admin_sakura',    '$2y$12$KH1ZEnkXEnFSd6gBpydNz.2DGHZyKDBwM3N.ibGub.2iw6t5UJSjS'),
  (3, 'admin_burger',    '$2y$12$KH1ZEnkXEnFSd6gBpydNz.2DGHZyKDBwM3N.ibGub.2iw6t5UJSjS'),
  (4, 'admin_antonovka', '$2y$12$KH1ZEnkXEnFSd6gBpydNz.2DGHZyKDBwM3N.ibGub.2iw6t5UJSjS'),
  (5, 'admin_pivnoy',    '$2y$12$KH1ZEnkXEnFSd6gBpydNz.2DGHZyKDBwM3N.ibGub.2iw6t5UJSjS')
ON DUPLICATE KEY UPDATE
  restaurant_id=VALUES(restaurant_id),
  password_hash=VALUES(password_hash);

-- Тестовые баннеры
INSERT INTO banners (title, subtitle, button_text, button_url, bg_color, is_active, sort_order)
VALUES
  ('🎉 Скидка 10% на первое бронирование', 'Зарегистрируйтесь и получите скидку на первый визит в любой ресторан', 'Зарегистрироваться', '/auth/register.php', '#2D6A4F', 1, 1),
  ('🍽 Курская антоновка — ресторан недели', 'Авторская кухня Курской области с фирменными блюдами из антоновских яблок', 'Забронировать', '/restaurant.php?id=4', '#8B4513', 1, 2),
  ('📍 TableSpot — сервис для ресторанов Курска', 'Разместите своё заведение бесплатно и получайте онлайн-бронирования', 'Узнать больше', '/placement.php', '#1a5c8a', 1, 3)
ON DUPLICATE KEY UPDATE
  title=VALUES(title),
  subtitle=VALUES(subtitle),
  button_text=VALUES(button_text),
  button_url=VALUES(button_url),
  bg_color=VALUES(bg_color);
