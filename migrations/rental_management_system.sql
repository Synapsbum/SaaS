-- ============================================
-- Rental Management System - Database Migration
-- ============================================

-- Rental Analytics Tablosu (Gerçek zamanlı ziyaretçi verileri)
CREATE TABLE IF NOT EXISTS `rental_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rental_id` int(11) NOT NULL,
  `visitor_ip` varchar(45) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `country` varchar(2) DEFAULT 'TR',
  `page_url` varchar(500) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(64) NOT NULL,
  `visit_date` date NOT NULL,
  `visit_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_unique` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `rental_id` (`rental_id`),
  KEY `visit_date` (`visit_date`),
  KEY `session_id` (`session_id`),
  KEY `visitor_ip` (`visitor_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rental Analytics Summary (Günlük özet)
CREATE TABLE IF NOT EXISTS `rental_analytics_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rental_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `unique_visitors` int(11) DEFAULT 0,
  `total_pageviews` int(11) DEFAULT 0,
  `active_users_now` int(11) DEFAULT 0,
  `total_deposits_try` decimal(15,2) DEFAULT 0.00,
  `deposit_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rental_date` (`rental_id`, `date`),
  KEY `rental_id` (`rental_id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Şehir bazlı analytics
CREATE TABLE IF NOT EXISTS `rental_analytics_by_city` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rental_id` int(11) NOT NULL,
  `city` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `visitor_count` int(11) DEFAULT 0,
  `pageview_count` int(11) DEFAULT 0,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rental_city_date` (`rental_id`, `city`, `date`),
  KEY `rental_id` (`rental_id`),
  KEY `city` (`city`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Para yatırma işlemleri (Analytics için)
CREATE TABLE IF NOT EXISTS `rental_deposits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rental_id` int(11) NOT NULL,
  `user_ip` varchar(45) DEFAULT NULL,
  `amount_try` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rental_id` (`rental_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kripto Cüzdan Ayarları
CREATE TABLE IF NOT EXISTS `rental_crypto_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rental_id` int(11) NOT NULL,
  `wallet_type` enum('USDT_TRC20','TRX_TRON','BTC') NOT NULL,
  `wallet_address` varchar(255) NOT NULL,
  `qr_code_path` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rental_wallet_type` (`rental_id`, `wallet_type`),
  KEY `rental_id` (`rental_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İBAN Bilgileri
CREATE TABLE IF NOT EXISTS `rental_ibans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rental_id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_holder` varchar(150) NOT NULL,
  `iban` varchar(34) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rental_id` (`rental_id`),
  KEY `status` (`status`),
  KEY `display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rental Settings (Tawk.to, çekim limiti vb.)
CREATE TABLE IF NOT EXISTS `rental_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rental_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rental_setting` (`rental_id`, `setting_key`),
  KEY `rental_id` (`rental_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Active Sessions (Son 5 dakika içindeki aktif kullanıcılar)
CREATE TABLE IF NOT EXISTS `rental_active_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rental_id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `visitor_ip` varchar(45) NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `rental_id` (`rental_id`),
  KEY `last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Indexes for Performance
-- ============================================

ALTER TABLE `rentals` ADD INDEX IF NOT EXISTS `user_status` (`user_id`, `status`);
ALTER TABLE `rentals` ADD INDEX IF NOT EXISTS `expires_at` (`expires_at`);

-- ============================================
-- Sample Data for Testing
-- ============================================

-- Türkiye şehir koordinatları (harita için)
INSERT INTO `rental_analytics_by_city` (`rental_id`, `city`, `date`, `visitor_count`, `pageview_count`, `latitude`, `longitude`) VALUES
(3, 'İstanbul', CURDATE(), 0, 0, 41.0082, 28.9784),
(3, 'Ankara', CURDATE(), 0, 0, 39.9334, 32.8597),
(3, 'İzmir', CURDATE(), 0, 0, 38.4237, 27.1428),
(3, 'Bursa', CURDATE(), 0, 0, 40.1885, 29.0610),
(3, 'Antalya', CURDATE(), 0, 0, 36.8969, 30.7133)
ON DUPLICATE KEY UPDATE `visitor_count` = `visitor_count`;
