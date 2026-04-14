-- Database.sql
-- Consolidated database schema, migrations, and seed data for Goodwill Vietnam
-- This file combines: Final_DB.sql, all migrations, and sample data imports
-- Generated: 2026-03-06

SET NAMES utf8mb4;

-- Create/select database first to avoid "No database selected"
CREATE DATABASE IF NOT EXISTS goodwill_vietnam
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE goodwill_vietnam;

-- Reset existing objects
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ratings;   
DROP TABLE IF EXISTS order_tracking_events;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS campaign_volunteers;
DROP TABLE IF EXISTS campaign_donations;
DROP TABLE IF EXISTS campaign_items;
DROP TABLE IF EXISTS campaign_task_assignments;
DROP TABLE IF EXISTS campaign_tasks;
DROP TABLE IF EXISTS campaign_milestones;
DROP TABLE IF EXISTS volunteer_hours_logs;
DROP TABLE IF EXISTS campaigns;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS donations;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS beneficiaries;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS admin_notifications;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_sessions;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS backups;
DROP TABLE IF EXISTS social_accounts;
DROP TABLE IF EXISTS recruitment_positions;
DROP TABLE IF EXISTS recruitment_applications;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- Roles
CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(128) NULL,
    phone VARCHAR(20),
    address TEXT,
    avatar VARCHAR(255),
    role_id INT DEFAULT 2,
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_expires TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social Accounts
CREATE TABLE social_accounts (
    social_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    provider VARCHAR(20) NOT NULL,
    provider_user_id VARCHAR(191) NOT NULL,
    email VARCHAR(100),
    name VARCHAR(100),
    avatar TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_social_accounts_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_social_provider (provider, provider_user_id),
    INDEX idx_social_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(100),
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DONATION & INVENTORY TABLES
-- ============================================================================

-- Donations
CREATE TABLE donations (
    donation_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'item',
    condition_status ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'good',
    estimated_value DECIMAL(10,2),
    images JSON,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT,
    pickup_address TEXT,
    pickup_date DATE,
    pickup_time TIME,
    contact_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_donations_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_donations_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory
CREATE TABLE inventory (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    donation_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'item',
    condition_status ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'good',
    price_type ENUM('free', 'cheap', 'normal') DEFAULT 'free',
    sale_price DECIMAL(10,2) DEFAULT 0,
    estimated_value DECIMAL(10,2),
    actual_value DECIMAL(10,2),
    images JSON,
    location VARCHAR(100),
    average_rating DECIMAL(3,2) DEFAULT 0,
    rating_count INT DEFAULT 0,
    status ENUM('available', 'reserved', 'sold', 'damaged', 'disposed') DEFAULT 'available',
    is_for_sale BOOLEAN DEFAULT TRUE,
    reserved_by INT NULL,
    reserved_until TIMESTAMP NULL,
    sold_to INT NULL,
    sold_at TIMESTAMP NULL,
    sold_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_donation FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_reserved_by FOREIGN KEY (reserved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_sold_to FOREIGN KEY (sold_to) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Ratings
CREATE TABLE ratings (
    rating_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    rating_stars INT NOT NULL CHECK (rating_stars >= 1 AND rating_stars <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_ratings_item (item_id),
    INDEX idx_ratings_user (user_id),
    INDEX idx_ratings_created (created_at),
    UNIQUE KEY unique_user_item_rating (user_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beneficiaries
CREATE TABLE beneficiaries (
    beneficiary_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    organization_type ENUM('individual', 'ngo', 'charity', 'school', 'hospital', 'other') DEFAULT 'individual',
    description TEXT,
    verification_documents JSON,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_beneficiaries_verified_by FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions
CREATE TABLE transactions (
    trans_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT,
    beneficiary_id INT,
    type ENUM('donation', 'purchase', 'reservation', 'cancellation') NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cash', 'bank_transfer', 'credit_card', 'momo', 'zalopay', 'free') DEFAULT 'free',
    payment_reference VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_item FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE SET NULL,
    CONSTRAINT fk_transactions_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(beneficiary_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CAMPAIGN TABLES
-- ============================================================================

-- Campaigns
CREATE TABLE campaigns (
    campaign_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    video_type ENUM('none', 'upload', 'youtube', 'facebook', 'tiktok', 'multi') DEFAULT 'none',
    video_file VARCHAR(255),
    video_youtube VARCHAR(255),
    video_facebook VARCHAR(255),
    video_tiktok VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    target_amount DECIMAL(12,2),
    current_amount DECIMAL(12,2) DEFAULT 0,
    target_items INT,
    current_items INT DEFAULT 0,
    status ENUM('draft', 'pending', 'active', 'paused', 'completed', 'cancelled') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaigns_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign Items
CREATE TABLE campaign_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    category_id INT,
    quantity_needed INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit VARCHAR(50) DEFAULT 'item',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaign_items_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_items_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign Donations
CREATE TABLE campaign_donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    donation_id INT NOT NULL,
    campaign_item_id INT,
    quantity_contributed INT DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaign_donations_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_donations_donation FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_donations_item FOREIGN KEY (campaign_item_id) REFERENCES campaign_items(item_id) ON DELETE SET NULL,
    UNIQUE KEY unique_campaign_donation (campaign_id, donation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign Volunteers
CREATE TABLE campaign_volunteers (
    volunteer_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    message TEXT,
    skills TEXT,
    availability TEXT,
    role VARCHAR(100),
    approved_by INT,
    approved_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    hours_contributed INT DEFAULT 0,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaign_volunteers_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_volunteers_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_volunteers_approver FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_campaign_user (campaign_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign Tasks
CREATE TABLE campaign_tasks (
    task_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    task_type ENUM('on_site', 'online', 'support', 'logistics') DEFAULT 'support',
    required_volunteers INT DEFAULT 1,
    estimated_minutes INT DEFAULT 0,
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    status ENUM('open', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_campaign_tasks_campaign (campaign_id),
    KEY idx_campaign_tasks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign Task Assignments
CREATE TABLE campaign_task_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('leader', 'member') DEFAULT 'member',
    status ENUM('assigned', 'in_progress', 'completed', 'removed') DEFAULT 'assigned',
    assigned_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_task_assignments_task (task_id),
    KEY idx_task_assignments_user (user_id),
    KEY idx_task_assignments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volunteer Hours Logs
CREATE TABLE volunteer_hours_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    task_id INT NULL,
    user_id INT NOT NULL,
    check_in DATETIME NULL,
    check_out DATETIME NULL,
    minutes INT DEFAULT 0,
    note TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hours_campaign (campaign_id),
    KEY idx_hours_user (user_id),
    KEY idx_hours_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign Milestones
CREATE TABLE campaign_milestones (
    milestone_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    due_date DATE NULL,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_milestones_campaign (campaign_id),
    KEY idx_milestones_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- NOTIFICATIONS & COMMUNICATION TABLES
-- ============================================================================

-- Notifications
CREATE TABLE notifications (
    notify_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    sent_by INT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    category ENUM('system', 'campaign', 'donation', 'order', 'general') DEFAULT 'general',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_sender FOREIGN KEY (sent_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin Notifications
CREATE TABLE admin_notifications (
    admin_notify_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('system', 'campaign', 'donation', 'order', 'general') DEFAULT 'system',
    severity ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    target_type ENUM('all', 'selected') DEFAULT 'all',
    target_user_ids JSON,
    status ENUM('draft', 'scheduled', 'sent', 'cancelled') DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_notifications_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feedback
CREATE TABLE feedback (
    fb_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(100),
    email VARCHAR(100),
    subject VARCHAR(200),
    content TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    status ENUM('pending', 'read', 'replied', 'closed') DEFAULT 'pending',
    admin_reply TEXT,
    replied_by INT,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_feedback_replied_by FOREIGN KEY (replied_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat Sessions
CREATE TABLE chat_sessions (
    chat_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    guest_token VARCHAR(128) NULL,
    staff_id INT NULL,
    status ENUM('open', 'closed') DEFAULT 'open',
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chat_sessions_user (user_id),
    INDEX idx_chat_sessions_guest (guest_token),
    INDEX idx_chat_sessions_staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat Messages
CREATE TABLE chat_messages (
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

-- ============================================================================
-- ADMIN & STAFF TABLES
-- ============================================================================

-- Staff
CREATE TABLE staff (
    staff_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    employee_id VARCHAR(20) UNIQUE,
    position VARCHAR(100),
    department VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    hire_date DATE,
    salary DECIMAL(10,2),
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    assigned_area VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_staff_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recruitment Positions
CREATE TABLE recruitment_positions (
    position_id INT PRIMARY KEY AUTO_INCREMENT,
    position_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_recruitment_position_name (position_name),
    INDEX idx_recruitment_positions_active (is_active, sort_order, position_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recruitment Applications
CREATE TABLE recruitment_applications (
    application_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    position VARCHAR(100) NOT NULL,
    availability VARCHAR(30) DEFAULT NULL,
    message TEXT,
    cv_file VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_note TEXT,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recruitment_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_recruitment_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_recruitment_status (status),
    INDEX idx_recruitment_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Settings
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_system_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backups
CREATE TABLE backups (
    backup_id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    backup_type ENUM('full', 'incremental', 'manual') DEFAULT 'manual',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_backups_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- E-COMMERCE TABLES
-- ============================================================================

-- Cart
CREATE TABLE cart (
    cart_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_item FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE,
    user_id INT NOT NULL,
    shipping_name VARCHAR(100),
    shipping_phone VARCHAR(20),
    shipping_address TEXT,
    shipping_place_id VARCHAR(128) NULL,
    shipping_lat DECIMAL(10,7) NULL,
    shipping_lng DECIMAL(10,7) NULL,
    shipping_method ENUM('pickup', 'delivery') DEFAULT 'pickup',
    shipping_note TEXT,
    payment_method ENUM('cod', 'bank_transfer', 'credit_card', 'free') NOT NULL DEFAULT 'cod',
    payment_status ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_items INT DEFAULT 0,
    status ENUM('pending', 'confirmed', 'processing', 'shipping', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items
CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    item_name VARCHAR(255),
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2),
    price_type ENUM('free', 'cheap', 'normal'),
    subtotal DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_item FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Status History
CREATE TABLE order_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_status_history FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Tracking Events
CREATE TABLE order_tracking_events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    status_code VARCHAR(64) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL DEFAULT '',
    note TEXT NULL,
    location_address VARCHAR(255) NOT NULL DEFAULT '',
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,
    occurred_at DATETIME NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`event_id`),
    KEY `idx_order_tracking_order_time` (`order_id`, `occurred_at`),
    KEY `idx_order_tracking_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INDEXES
-- ============================================================================

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_remember_token ON users(remember_token);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_donations_user ON donations(user_id);
CREATE INDEX idx_donations_status ON donations(status);
CREATE INDEX idx_donations_created ON donations(created_at);
CREATE INDEX idx_inventory_status ON inventory(status);
CREATE INDEX idx_inventory_category ON inventory(category_id);
CREATE INDEX idx_inventory_price_type ON inventory(price_type);
CREATE INDEX idx_inventory_for_sale ON inventory(is_for_sale);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);
CREATE INDEX idx_campaigns_status ON campaigns(status);
CREATE INDEX idx_campaigns_video ON campaigns(video_type);
CREATE INDEX idx_campaign_items_campaign ON campaign_items(campaign_id);
CREATE INDEX idx_campaign_donations_campaign ON campaign_donations(campaign_id);
CREATE INDEX idx_campaign_volunteers_campaign ON campaign_volunteers(campaign_id);
CREATE INDEX idx_campaign_volunteers_user ON campaign_volunteers(user_id);
CREATE INDEX idx_campaign_volunteers_status ON campaign_volunteers(status);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_item ON order_items(item_id);
CREATE INDEX idx_cart_user ON cart(user_id);

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Seeds: Roles
INSERT INTO roles (role_id, role_name, description, permissions) VALUES
    (1, 'quản trị viên', 'Quản trị viên hệ thống', '{"all": true}'),
    (2, 'người dùng', 'Người dùng đã đăng ký', '{"donate": true, "browse": true, "order": true}'),
    (3, 'khách', 'Khách', '{"browse": true}'),
    (4, 'nhân viên', 'Nhân viên', '{"staff": true}')
ON DUPLICATE KEY UPDATE
    role_name = VALUES(role_name),
    description = VALUES(description),
    permissions = VALUES(permissions);

-- Seeds: Categories
INSERT INTO categories (category_id, name, description, icon, sort_order) VALUES
    (1, 'Quần áo', 'Các mặt hàng quần áo', 'bi-tshirt', 1),
    (2, 'Điện tử', 'Điện thoại và máy tính', 'bi-laptop', 2),
    (3, 'Sách', 'Sách và tài liệu', 'bi-book', 3),
    (4, 'Gia dụng', 'Các mặt hàng gia dụng', 'bi-house', 4),
    (5, 'Đồ chơi', 'Đồ chơi cho trẻ em', 'bi-toy', 5),
    (6, 'Thực phẩm', 'Thực phẩm và lương thực', 'bi-basket', 6),
    (7, 'Y tế', 'Vật tư y tế', 'bi-heart-pulse', 7),
    (8, 'Khác', 'Các mặt hàng khác', 'bi-box', 8)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    icon = VALUES(icon),
    sort_order = VALUES(sort_order);

-- Seeds: Admin Users
INSERT INTO users (user_id, name, email, password, role_id, status, email_verified)
SELECT 1, 'Administrator', 'admin@goodwillvietnam.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       1, 'active', TRUE
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@goodwillvietnam.com');

INSERT INTO users (user_id, name, email, password, role_id, status, email_verified)
SELECT 2, 'Admin2', 'admin2@goodwillvietnam.com',
       '$2y$10$eImiTXuWVxfM37uY4JANjQ==',
       1, 'active', TRUE
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin2@goodwillvietnam.com');

-- Seeds: Test Accounts (test1..test10 / password: 123456)
INSERT INTO users (name, email, password, phone, address, role_id, status, email_verified, created_at) VALUES
    ('Test User 1', 'test1@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW()),
    ('Test User 2', 'test2@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW()),
    ('Test User 3', 'test3@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW()),
    ('Test User 4', 'test4@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW()),
    ('Test User 5', 'test5@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW()),
    ('Test User 6', 'test6@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW()),
    ('Test User 7', 'test7@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW()),
    ('Test User 8', 'test8@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW()),
    ('Test User 9', 'test9@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW()),
    ('Test User 10', 'test10@goodwillvietnam.com', '$2y$10$.VjMYdqqksAnJf6Q/zWMp.IVAW3Xa0/WDwo0RAiicyomCabwh5RCO', '0123456789', 'Test Address', 2, 'active', TRUE, NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password = VALUES(password),
    phone = VALUES(phone),
    address = VALUES(address),
    role_id = VALUES(role_id),
    status = VALUES(status),
    email_verified = VALUES(email_verified),
    updated_at = CURRENT_TIMESTAMP;

-- Seeds: System Settings
INSERT INTO system_settings (setting_key, setting_value, description, type) VALUES
    ('site_name', 'Goodwill Vietnam', 'Site title', 'string'),
    ('site_description', 'Nen tang thien nguyen ket noi cong dong', 'Site description', 'string'),
    ('contact_email', 'info@goodwillvietnam.com', 'Contact email', 'string'),
    ('contact_phone', '+84 123 456 789', 'Contact phone', 'string'),
    ('max_file_size', '5242880', 'Max upload bytes', 'number'),
    ('allowed_file_types', '["jpg","jpeg","png","gif"]', 'Allowed upload types', 'json'),
    ('items_per_page', '12', 'Items per page', 'number'),
    ('enable_registration', 'true', 'Allow new registrations', 'boolean'),
    ('maintenance_mode', 'false', 'Maintenance mode', 'boolean'),
    ('enable_shop', 'true', 'Enable shop features', 'boolean'),
    ('cheap_price_threshold', '100000', 'Cheap price threshold (VND)', 'number'),
    ('free_shipping_threshold', '500000', 'Free shipping threshold (VND)', 'number'),
    ('order_prefix', 'GW', 'Order number prefix', 'string'),
    ('enable_campaigns', 'true', 'Enable campaigns module', 'boolean'),
    ('campaign_approval_required', 'true', 'Require campaign approval', 'boolean')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
    description = VALUES(description),
    type = VALUES(type);

-- Seeds: Recruitment Positions
INSERT INTO recruitment_positions (position_id, position_name, description, is_active, sort_order) VALUES
    (1, 'Quản lý kho hàng', 'Quản lý nhập/xuất và theo dõi tồn kho hàng hóa', TRUE, 1),
    (2, 'Quản lý đơn hàng', 'Tiếp nhận, xử lý và theo dõi trạng thái đơn hàng', TRUE, 2),
    (3, 'Quản lý chiến dịch', 'Lập kế hoạch, triển khai và giám sát các chiến dịch', TRUE, 3),
    (4, 'Tư vấn chăm sóc khách hàng', 'Tư vấn, hỗ trợ và chăm sóc khách hàng đa kênh', TRUE, 4),
    (5, 'Thu ngân', 'Thanh toán trực tiếp tại điểm bán và hỗ trợ giao dịch tại quầy', TRUE, 5)
ON DUPLICATE KEY UPDATE
    position_name = VALUES(position_name),
    description = VALUES(description),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);

-- ============================================================================
-- IMPORT PRODUCT CATALOG DATA (100 items) [REMOVED]
-- ============================================================================
-- Seed product data removed. You can add products manually after import.


-- ============================================================================
-- VIEWS
-- ============================================================================

CREATE OR REPLACE VIEW v_statistics AS
SELECT 
    (SELECT COUNT(*) FROM users WHERE status = 'active') AS total_users,
    (SELECT COUNT(*) FROM donations WHERE status != 'cancelled') AS total_donations,
    (SELECT COUNT(*) FROM inventory WHERE status = 'available') AS total_items,
    (SELECT COUNT(*) FROM campaigns WHERE status = 'active') AS active_campaigns,
    (SELECT COUNT(*) FROM transactions WHERE type = 'donation' AND status = 'completed') AS completed_donations,
    (SELECT SUM(amount) FROM transactions WHERE type = 'donation' AND status = 'completed') AS total_donation_value;

CREATE OR REPLACE VIEW v_donation_details AS
SELECT 
    d.*,
    u.name AS donor_name,
    u.email AS donor_email,
    u.phone AS donor_phone,
    c.name AS category_name,
    CASE 
        WHEN d.status = 'pending' THEN 'Cho duyet'
        WHEN d.status = 'approved' THEN 'Da duyet'
        WHEN d.status = 'rejected' THEN 'Tu choi'
        WHEN d.status = 'cancelled' THEN 'Da huy'
    END AS status_text
FROM donations d
LEFT JOIN users u ON d.user_id = u.user_id
LEFT JOIN categories c ON d.category_id = c.category_id;

CREATE OR REPLACE VIEW v_inventory_items AS
SELECT 
    i.*,
    d.item_name AS donation_name,
    d.description AS donation_description,
    u.name AS donor_name,
    c.name AS category_name,
    CASE 
        WHEN i.status = 'available' THEN 'Co san'
        WHEN i.status = 'reserved' THEN 'Da giu'
        WHEN i.status = 'sold' THEN 'Da ban'
        WHEN i.status = 'damaged' THEN 'Hu hong'
        WHEN i.status = 'disposed' THEN 'Da xu ly'
    END AS status_text
FROM inventory i
LEFT JOIN donations d ON i.donation_id = d.donation_id
LEFT JOIN users u ON d.user_id = u.user_id
LEFT JOIN categories c ON i.category_id = c.category_id;

CREATE OR REPLACE VIEW v_saleable_items AS
SELECT 
    i.*,
    c.name AS category_name,
    c.icon AS category_icon,
    d.item_name AS donation_name,
    u.name AS donor_name,
    CASE 
        WHEN i.price_type = 'free' THEN 'Mien phi'
        WHEN i.price_type = 'cheap' THEN 'Gia re'
        WHEN i.price_type = 'normal' THEN 'Gia thuong'
    END AS price_type_text,
    CASE 
        WHEN i.status = 'available' THEN 'Co san'
        WHEN i.status = 'reserved' THEN 'Da giu'
        WHEN i.status = 'sold' THEN 'Da ban'
    END AS status_text
FROM inventory i
LEFT JOIN categories c ON i.category_id = c.category_id
LEFT JOIN donations d ON i.donation_id = d.donation_id
LEFT JOIN users u ON d.user_id = u.user_id
WHERE i.is_for_sale = TRUE AND i.status IN ('available', 'reserved');

CREATE OR REPLACE VIEW v_order_details AS
SELECT 
    o.*,
    u.name AS customer_name,
    u.email AS customer_email,
    u.phone AS customer_phone,
    COUNT(oi.order_item_id) AS total_items_count
FROM orders o
LEFT JOIN users u ON o.user_id = u.user_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id
GROUP BY o.order_id;

CREATE OR REPLACE VIEW v_campaign_details AS
SELECT 
    c.*,
    u.name AS creator_name,
    u.email AS creator_email,
    COUNT(DISTINCT cv.volunteer_id) AS volunteer_count,
    COUNT(DISTINCT cd.donation_id) AS donation_count,
    SUM(ci.quantity_needed) AS total_items_needed,
    SUM(ci.quantity_received) AS total_items_received,
    CASE 
        WHEN c.status = 'draft' THEN 'Nhap'
        WHEN c.status = 'pending' THEN 'Cho duyet'
        WHEN c.status = 'active' THEN 'Dang hoat dong'
        WHEN c.status = 'paused' THEN 'Tam dung'
        WHEN c.status = 'completed' THEN 'Hoan thanh'
        WHEN c.status = 'cancelled' THEN 'Da huy'
    END AS status_text,
    DATEDIFF(c.end_date, CURDATE()) AS days_remaining,
    CASE 
        WHEN SUM(ci.quantity_needed) > 0 
        THEN ROUND((SUM(ci.quantity_received) / SUM(ci.quantity_needed)) * 100, 2)
        ELSE 0 
    END AS completion_percentage
FROM campaigns c
LEFT JOIN users u ON c.created_by = u.user_id
LEFT JOIN campaign_volunteers cv ON c.campaign_id = cv.campaign_id AND cv.status = 'approved'
LEFT JOIN campaign_donations cd ON c.campaign_id = cd.campaign_id
LEFT JOIN campaign_items ci ON c.campaign_id = ci.campaign_id
GROUP BY c.campaign_id;

CREATE OR REPLACE VIEW v_campaign_items_progress AS
SELECT 
    ci.*,
    c.name AS campaign_name,
    c.status AS campaign_status,
    cat.name AS category_name,
    ci.quantity_received AS received,
    ci.quantity_needed AS needed,
    (ci.quantity_needed - ci.quantity_received) AS remaining,
    CASE 
        WHEN ci.quantity_needed > 0 
        THEN ROUND((ci.quantity_received / ci.quantity_needed) * 100, 2)
        ELSE 0 
    END AS progress_percentage,
    CASE 
        WHEN ci.quantity_received >= ci.quantity_needed THEN 'Du'
        WHEN ci.quantity_received > 0 THEN 'Dang thieu'
        ELSE 'Chua co'
    END AS status_text
FROM campaign_items ci
LEFT JOIN campaigns c ON ci.campaign_id = c.campaign_id
LEFT JOIN categories cat ON ci.category_id = cat.category_id;

-- ============================================================================
-- TRIGGERS
-- ============================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS after_donation_approved$$
CREATE TRIGGER after_donation_approved
AFTER UPDATE ON donations
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' AND OLD.status <> 'approved' THEN
        IF NOT EXISTS (SELECT 1 FROM inventory WHERE donation_id = NEW.donation_id) THEN
            INSERT INTO inventory (
                donation_id, name, description, category_id, quantity, unit,
                condition_status, estimated_value, actual_value, images,
                status, price_type, sale_price, is_for_sale, created_at
            ) VALUES (
                NEW.donation_id,
                NEW.item_name,
                NEW.description,
                NEW.category_id,
                NEW.quantity,
                NEW.unit,
                NEW.condition_status,
                NEW.estimated_value,
                NEW.estimated_value,
                NEW.images,
                'available',
                'free',
                0,
                TRUE,
                NOW()
            );
        END IF;
    END IF;
END$$

DROP TRIGGER IF EXISTS before_order_insert$$
CREATE TRIGGER before_order_insert 
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    DECLARE order_prefix VARCHAR(10);
    
    SELECT setting_value INTO order_prefix 
    FROM system_settings 
    WHERE setting_key = 'order_prefix' 
    LIMIT 1;
    
    IF order_prefix IS NULL THEN
        SET order_prefix = 'GW';
    END IF;
    
    SET next_id = COALESCE((SELECT MAX(order_id) + 1 FROM orders), 1);
    SET NEW.order_number = COALESCE(NEW.order_number, CONCAT(order_prefix, DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(next_id, 4, '0')));
END$$

DROP TRIGGER IF EXISTS update_order_status_history$$
CREATE TRIGGER update_order_status_history 
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO order_status_history (order_id, old_status, new_status, note)
        VALUES (NEW.order_id, OLD.status, NEW.status, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END$$
  
DELIMITER ;

-- ============================================================================
-- SAMPLE RATINGS DATA
-- ============================================================================

-- Sample ratings removed to avoid FK errors when no seeded products are present.
-- Update inventory average ratings from existing ratings data (if any).
UPDATE inventory i
SET average_rating = (
    SELECT COALESCE(AVG(r.rating_stars), 0)
    FROM ratings r
    WHERE r.item_id = i.item_id
),
rating_count = (
    SELECT COUNT(*)
    FROM ratings r
    WHERE r.item_id = i.item_id
);

-- Ensure every product has at least one image path.
UPDATE donations
SET images = JSON_ARRAY('placeholder-default.svg')
WHERE images IS NULL
    OR JSON_VALID(images) = 0
    OR JSON_LENGTH(images) = 0;

UPDATE inventory
SET images = JSON_ARRAY('placeholder-default.svg')
WHERE images IS NULL
    OR JSON_VALID(images) = 0
    OR JSON_LENGTH(images) = 0;

-- ============================================================================
-- AI CONTENT MODERATION TABLES
-- (Phục vụ train AI kiểm duyệt hình ảnh thô tục & ngôn ngữ 18+)
-- ============================================================================

-- Kết quả kiểm duyệt nội dung tự động (ảnh + văn bản)
DROP TABLE IF EXISTS content_moderation_results;
CREATE TABLE content_moderation_results (
    result_id       INT PRIMARY KEY AUTO_INCREMENT,
    -- Tham chiếu nguồn nội dung
    content_type    ENUM('image', 'text') NOT NULL,
    source_table    VARCHAR(64) NOT NULL COMMENT 'donations | inventory | chat_messages | feedback',
    source_id       INT NOT NULL           COMMENT 'PK của bản ghi nguồn',
    source_field    VARCHAR(64) NOT NULL   COMMENT 'images | message | description | review_text ...',
    -- Dữ liệu gốc (ảnh: đường dẫn file; văn bản: nội dung)
    raw_value       TEXT NOT NULL,
    -- Kết quả AI
    model_name      VARCHAR(100) NOT NULL  COMMENT 'Tên/phiên bản model đã dùng',
    model_version   VARCHAR(50)  NULL,
    is_nsfw         BOOLEAN NOT NULL DEFAULT FALSE  COMMENT 'TRUE = vi phạm 18+',
    confidence      DECIMAL(5,4) NULL              COMMENT '0.0000 – 1.0000',
    labels          JSON         NULL              COMMENT 'Chi tiết nhãn / lý do vi phạm',
    -- Xử lý sau kiểm duyệt
    action_taken    ENUM('none', 'flagged', 'hidden', 'deleted', 'approved') DEFAULT 'none',
    reviewed_by     INT          NULL              COMMENT 'NULL = tự động; có giá trị = kiểm duyệt viên',
    reviewed_at     TIMESTAMP    NULL,
    review_note     TEXT         NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cmr_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_cmr_source    (source_table, source_id),
    INDEX idx_cmr_nsfw      (is_nsfw),
    INDEX idx_cmr_action    (action_taken),
    INDEX idx_cmr_model     (model_name),
    INDEX idx_cmr_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Lưu kết quả kiểm duyệt nội dung tự động (hình ảnh + văn bản 18+)';

-- Dữ liệu huấn luyện AI có nhãn (labeled dataset)
DROP TABLE IF EXISTS ai_training_data;
CREATE TABLE ai_training_data (
    sample_id       INT PRIMARY KEY AUTO_INCREMENT,
    data_type       ENUM('image', 'text') NOT NULL,
    -- Nội dung mẫu
    file_path       VARCHAR(500) NULL    COMMENT 'Đường dẫn ảnh (data_type = image)',
    text_content    TEXT         NULL    COMMENT 'Văn bản mẫu (data_type = text)',
    language        VARCHAR(10)  NULL DEFAULT 'vi' COMMENT 'vi | en | ...',
    -- Nhãn (label)
    label           ENUM('safe', 'nsfw', 'suggestive', 'hate_speech', 'spam', 'other') NOT NULL,
    label_detail    VARCHAR(255) NULL    COMMENT 'Mô tả chi tiết nhãn',
    confidence      DECIMAL(5,4) NULL    COMMENT 'Độ tin cậy của nhãn (1.0 = chắc chắn)',
    -- Nguồn gốc & kiểm tra chéo
    source          ENUM('manual', 'ai_flagged', 'user_report', 'import') DEFAULT 'manual',
    origin_result_id INT NULL            COMMENT 'FK tới content_moderation_results nếu lấy từ kết quả AI',
    is_verified     BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Đã được người kiểm tra xác nhận',
    verified_by     INT  NULL,
    verified_at     TIMESTAMP NULL,
    labeled_by      INT  NULL,
    labeled_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    -- Sử dụng trong training
    split_set       ENUM('train', 'validation', 'test') DEFAULT 'train',
    dataset_version VARCHAR(30) NULL     COMMENT 'v1.0, v1.1...',
    notes           TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_atd_labeled_by   FOREIGN KEY (labeled_by)   REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_atd_verified_by  FOREIGN KEY (verified_by)  REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_atd_origin       FOREIGN KEY (origin_result_id) REFERENCES content_moderation_results(result_id) ON DELETE SET NULL,
    INDEX idx_atd_type      (data_type),
    INDEX idx_atd_label     (label),
    INDEX idx_atd_split     (split_set),
    INDEX idx_atd_verified  (is_verified),
    INDEX idx_atd_version   (dataset_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Dataset có nhãn để huấn luyện mô hình kiểm duyệt nội dung';

-- Từ/cụm từ bị cấm (blacklist cho lọc văn bản 18+)
DROP TABLE IF EXISTS ai_moderation_keywords;
CREATE TABLE ai_moderation_keywords (
    keyword_id      INT PRIMARY KEY AUTO_INCREMENT,
    keyword         VARCHAR(255) NOT NULL,
    match_mode      ENUM('exact', 'partial', 'regex') DEFAULT 'partial'
                    COMMENT 'exact=khớp chính xác; partial=chứa chuỗi; regex=biểu thức',
    category        ENUM('nsfw', 'hate_speech', 'spam', 'violence', 'other') DEFAULT 'nsfw',
    severity        TINYINT UNSIGNED NOT NULL DEFAULT 1
                    COMMENT '1=nhẹ(cảnh báo), 2=trung bình(ẩn), 3=nặng(xóa ngay)',
    language        VARCHAR(10) NULL DEFAULT 'vi',
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    added_by        INT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_amk_added_by FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY uniq_keyword_lang (keyword, language),
    INDEX idx_amk_active    (is_active),
    INDEX idx_amk_category  (category),
    INDEX idx_amk_severity  (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Danh sách từ/cụm từ cấm dùng để lọc văn bản thô tục';

-- Báo cáo nội dung vi phạm từ người dùng
DROP TABLE IF EXISTS user_content_reports;
CREATE TABLE user_content_reports (
    report_id       INT PRIMARY KEY AUTO_INCREMENT,
    reporter_id     INT NOT NULL               COMMENT 'Người báo cáo',
    content_type    ENUM('image', 'text', 'profile', 'item', 'campaign') NOT NULL,
    source_table    VARCHAR(64) NOT NULL,
    source_id       INT NOT NULL,
    reason          ENUM('nsfw', 'hate_speech', 'spam', 'violence', 'misinformation', 'other') NOT NULL,
    description     TEXT NULL,
    status          ENUM('pending', 'reviewed', 'actioned', 'dismissed') DEFAULT 'pending',
    reviewed_by     INT NULL,
    reviewed_at     TIMESTAMP NULL,
    review_note     TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ucr_reporter    FOREIGN KEY (reporter_id)  REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_ucr_reviewer    FOREIGN KEY (reviewed_by)  REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_ucr_status   (status),
    INDEX idx_ucr_source   (source_table, source_id),
    INDEX idx_ucr_reporter (reporter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Báo cáo nội dung vi phạm do người dùng gửi';

-- Phiên bản và số liệu các mô hình AI đã triển khai
DROP TABLE IF EXISTS ai_model_versions;
CREATE TABLE ai_model_versions (
    version_id      INT PRIMARY KEY AUTO_INCREMENT,
    model_name      VARCHAR(100) NOT NULL,
    version_tag     VARCHAR(50)  NOT NULL COMMENT 'v1.0, v2.3-beta...',
    model_type      ENUM('image_nsfw', 'text_nsfw', 'combined') NOT NULL,
    description     TEXT NULL,
    file_path       VARCHAR(500) NULL     COMMENT 'Đường dẫn tới file model',
    -- Số liệu đánh giá
    accuracy        DECIMAL(5,4) NULL,
    precision_score DECIMAL(5,4) NULL,
    recall_score    DECIMAL(5,4) NULL,
    f1_score        DECIMAL(5,4) NULL,
    test_samples    INT NULL              COMMENT 'Số mẫu đã dùng để test',
    -- Trạng thái
    is_active       BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'TRUE = đang dùng trên production',
    trained_at      DATETIME NULL,
    deployed_at     DATETIME NULL,
    deprecated_at   DATETIME NULL,
    trained_by      INT NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_amv_trained_by FOREIGN KEY (trained_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY uniq_model_version (model_name, version_tag),
    INDEX idx_amv_active (is_active),
    INDEX idx_amv_type   (model_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Lịch sử và số liệu các phiên bản mô hình AI kiểm duyệt';

-- ============================================================================
-- CỘT KIỂM DUYỆT BỔ SUNG VÀO CÁC BẢNG HIỆN CÓ
-- ============================================================================

-- donations: đánh dấu ảnh quyên góp bị cờ vi phạm
ALTER TABLE donations
    ADD COLUMN IF NOT EXISTS moderation_status  ENUM('pending', 'clean', 'flagged', 'rejected') DEFAULT 'pending'
        COMMENT 'Trạng thái kiểm duyệt ảnh/mô tả quyên góp',
    ADD COLUMN IF NOT EXISTS moderation_score   DECIMAL(5,4) NULL
        COMMENT 'Điểm NSFW (0.0 = an toàn, 1.0 = vi phạm nặng)',
    ADD COLUMN IF NOT EXISTS moderation_at      TIMESTAMP NULL
        COMMENT 'Thời điểm kiểm duyệt gần nhất';

-- inventory: đánh dấu ảnh sản phẩm bị cờ vi phạm
ALTER TABLE inventory
    ADD COLUMN IF NOT EXISTS moderation_status  ENUM('pending', 'clean', 'flagged', 'rejected') DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS moderation_score   DECIMAL(5,4) NULL,
    ADD COLUMN IF NOT EXISTS moderation_at      TIMESTAMP NULL;

-- chat_messages: lọc ngôn ngữ thô tục trong chat
ALTER TABLE chat_messages
    ADD COLUMN IF NOT EXISTS is_flagged         BOOLEAN NOT NULL DEFAULT FALSE
        COMMENT 'TRUE = tin nhắn bị AI đánh dấu vi phạm',
    ADD COLUMN IF NOT EXISTS flag_reason        VARCHAR(255) NULL
        COMMENT 'Lý do bị cờ (từ khóa / nhãn AI)',
    ADD COLUMN IF NOT EXISTS moderation_status  ENUM('pending', 'clean', 'flagged', 'removed') DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS moderation_score   DECIMAL(5,4) NULL;

-- feedback: lọc nội dung phản hồi vi phạm
ALTER TABLE feedback
    ADD COLUMN IF NOT EXISTS moderation_status  ENUM('pending', 'clean', 'flagged', 'rejected') DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS moderation_score   DECIMAL(5,4) NULL,
    ADD COLUMN IF NOT EXISTS moderation_at      TIMESTAMP NULL;

-- ============================================================================
-- INDEX CHO CÁC CỘT KIỂM DUYỆT MỚI
-- ============================================================================

CREATE INDEX IF NOT EXISTS idx_donations_mod_status  ON donations   (moderation_status);
CREATE INDEX IF NOT EXISTS idx_inventory_mod_status  ON inventory   (moderation_status);
CREATE INDEX IF NOT EXISTS idx_chat_flagged          ON chat_messages (is_flagged);
CREATE INDEX IF NOT EXISTS idx_chat_mod_status       ON chat_messages (moderation_status);
CREATE INDEX IF NOT EXISTS idx_feedback_mod_status   ON feedback    (moderation_status);

-- ============================================================================
-- SEED DỮ LIỆU: Một số từ tiêu cực tiêu biểu (có thể mở rộng)
-- ============================================================================

INSERT INTO ai_moderation_keywords (keyword, match_mode, category, severity, language, is_active) VALUES
    ('địt',     'partial', 'nsfw',       3, 'vi', TRUE),
    ('lồn',     'partial', 'nsfw',       3, 'vi', TRUE),
    ('cặc',     'partial', 'nsfw',       3, 'vi', TRUE),
    ('đụ',      'partial', 'nsfw',       3, 'vi', TRUE),
    ('vãi lồn', 'partial', 'nsfw',       3, 'vi', TRUE),
    ('đéo',     'partial', 'nsfw',       2, 'vi', TRUE),
    ('fuck',    'partial', 'nsfw',       3, 'en', TRUE),
    ('shit',    'partial', 'nsfw',       2, 'en', TRUE),
    ('porn',    'partial', 'nsfw',       3, 'en', TRUE),
    ('nude',    'partial', 'nsfw',       2, 'en', TRUE),
    ('18+',     'exact',   'nsfw',       1, 'vi', TRUE),
    ('sex',     'exact',   'nsfw',       2, 'en', TRUE)
ON DUPLICATE KEY UPDATE
    category = VALUES(category),
    severity = VALUES(severity),
    is_active = VALUES(is_active);

-- ============================================================================
-- FINAL COMMIT
-- ============================================================================

COMMIT;







