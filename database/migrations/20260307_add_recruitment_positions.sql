-- Migration: Add recruitment_positions table
-- Date: 2026-03-07
-- Purpose: Manage recruitment positions dynamically

CREATE TABLE IF NOT EXISTS recruitment_positions (
    position_id INT PRIMARY KEY AUTO_INCREMENT,
    position_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    requirements TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed initial positions
INSERT INTO recruitment_positions (position_name, description, is_active) VALUES
    ('Quản lý vận hành', 'Quản lý các hoạt động vận hành của tổ chức', 1),
    ('Chăm sóc cộng đồng', 'Chăm sóc và hỗ trợ cộng đồng', 1),
    ('Content - Truyền thông', 'Tạo nội dung và truyền thông', 1),
    ('Tư vấn viên', 'Cung cấp tư vấn cho các dự án', 1),
    ('Hỗ trợ kho vận', 'Hỗ trợ quản lý kho và vận chuyển', 1)
ON DUPLICATE KEY UPDATE
    is_active = VALUES(is_active);
