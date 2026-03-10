-- Drop table if it exists to ensure correct schema
DROP TABLE IF EXISTS users;

-- Create the users table with the correct columns
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert the 4 specific users (director, md, accountant, secretary)
-- All passwords are '123' (hashed using standard bcrypt PHP algorithm)
INSERT INTO users (username, password, role, full_name, email, is_active) VALUES 
('director', '$2y$12$ecP129QBOikbBaZEp/.swup3kbd1FT9FEvRErygvJTPagr1Rka1kq', 'Director', 'Company Director', 'director@cbfinance.rw', 1),
('md', '$2y$12$ecP129QBOikbBaZEp/.swup3kbd1FT9FEvRErygvJTPagr1Rka1kq', 'MD', 'Managing Director', 'md@cbfinance.rw', 1),
('accountant', '$2y$12$ecP129QBOikbBaZEp/.swup3kbd1FT9FEvRErygvJTPagr1Rka1kq', 'Accountant', 'Senior Accountant', 'accountant@cbfinance.rw', 1),
('secretary', '$2y$12$ecP129QBOikbBaZEp/.swup3kbd1FT9FEvRErygvJTPagr1Rka1kq', 'Secretary', 'Company Secretary', 'secretary@cbfinance.rw', 1);