-- Create activity_logs table if not exists
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    action_type VARCHAR(50) NOT NULL, -- 'login', 'logout', 'create', 'update', 'delete', 'approve', 'reject'
    entity_type VARCHAR(50) DEFAULT NULL, -- 'customer', 'loan', 'user', 'instalment'
    entity_id INT DEFAULT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (action_type),
    INDEX (entity_type),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add the 2 developer users (PIN: 123456)
-- Hash: $2y$12$AVnPsQrdA0pn.L23zMejWOgx1QoOj383bISQj067yHPpqG7m7iAZe
INSERT IGNORE INTO users (username, password, role, full_name, email, is_active) VALUES 
('developerwilly', '$2y$12$AVnPsQrdA0pn.L23zMejWOgx1QoOj383bISQj067yHPpqG7m7iAZe', 'Developer', 'Willy Developer', 'willy@cbfinance.rw', 1),
('developerghis', '$2y$12$AVnPsQrdA0pn.L23zMejWOgx1QoOj383bISQj067yHPpqG7m7iAZe', 'Developer', 'Ghislain Developer', 'ghis@cbfinance.rw', 1);
