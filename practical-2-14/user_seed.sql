CREATE DATABASE IF NOT EXISTS studenthub;
USE studenthub;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    enrollment_number VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    last_login TIMESTAMP NULL,
    remember_token_hash VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (full_name, enrollment_number, email, password_hash, role)
VALUES
('Admin User', 'ADMIN001', 'admin@apex.edu.in', '$2y$10$NYCrflFUJsiIvDAiKrdjzOyfTcfkwAjEIJ9M1rK5okvmpePDqNy3m', 'admin'),
('Rahul Sharma', '24012011001', 'rahul.sharma@apex.edu.in', '$2y$10$Wm6dS.V1WlyfiuO6tkMvI.1bunN3DAPB2h49zXRV4aBpVAsd8/J8S', 'student')
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    role = VALUES(role);

CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_created_at (created_at)
) ENGINE=InnoDB;
