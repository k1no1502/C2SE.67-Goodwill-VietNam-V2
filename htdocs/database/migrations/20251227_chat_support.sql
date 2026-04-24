CREATE TABLE IF NOT EXISTS chat_sessions (
    chat_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    guest_token VARCHAR(128) NULL,
    staff_id INT NULL,
    status ENUM('open', 'closed') DEFAULT 'open',
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_chat_sessions_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_chat_sessions_staff FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_chat_sessions_user (user_id),
    INDEX idx_chat_sessions_guest (guest_token),
    INDEX idx_chat_sessions_staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    chat_id INT NOT NULL,
    sender_type ENUM('user', 'staff', 'system') NOT NULL,
    sender_id INT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chat_messages_chat FOREIGN KEY (chat_id) REFERENCES chat_sessions(chat_id) ON DELETE CASCADE,
    INDEX idx_chat_messages_chat (chat_id),
    INDEX idx_chat_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
