-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 02, 2026 at 09:06 AM
-- Server version: 8.0.17
-- PHP Version: 7.3.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `draw1`
--

-- --------------------------------------------------------

--
-- Table structure for table `drawings`
--

CREATE TABLE `drawings` (
  `id` int(11) NOT NULL,
  `upload_id` int(11) NOT NULL COMMENT 'FK → uploads.id',
  `user_id` int(11) NOT NULL COMMENT 'FK → users.id',
  `page_number` int(11) NOT NULL DEFAULT '1',
  `drawing_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'base64 drawing PNG',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drawing_logs`
--

CREATE TABLE `drawing_logs` (
  `id` int(11) NOT NULL,
  `upload_id` int(11) NOT NULL,
  `page_num` int(11) NOT NULL DEFAULT '1',
  `user_id` int(11) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `saved_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uploads`
--

CREATE TABLE `uploads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'pdf, jpg, png, gif, webp',
  `file_mime` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'bytes',
  `file_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'base64 encoded',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'รอตรวจ' COMMENT 'รอตรวจ, กำลังตรวจ, ผ่าน, ไม่ผ่าน',
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `upload_drawings`
--

CREATE TABLE `upload_drawings` (
  `id` int(11) NOT NULL,
  `upload_id` int(11) NOT NULL COMMENT 'FK → uploads.id',
  `page_num` int(11) NOT NULL DEFAULT '1',
  `drawing_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'base64 drawing layer',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `microsoft_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` longtext COLLATE utf8mb4_unicode_ci COMMENT 'base64 profile image',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_phones` text COLLATE utf8mb4_unicode_ci,
  `access_token` text COLLATE utf8mb4_unicode_ci,
  `refresh_token` text COLLATE utf8mb4_unicode_ci,
  `token_expires_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `drawings`
--
ALTER TABLE `drawings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_upload_page` (`upload_id`,`page_number`),
  ADD KEY `idx_upload` (`upload_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `drawing_logs`
--
ALTER TABLE `drawing_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_upload_page` (`upload_id`,`page_num`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`file_type`);

--
-- Indexes for table `upload_drawings`
--
ALTER TABLE `upload_drawings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_upload_page` (`upload_id`,`page_num`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `microsoft_id` (`microsoft_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_microsoft_id` (`microsoft_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `drawings`
--
ALTER TABLE `drawings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drawing_logs`
--
ALTER TABLE `drawing_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `upload_drawings`
--
ALTER TABLE `upload_drawings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `drawing_logs`
--
ALTER TABLE `drawing_logs`
  ADD CONSTRAINT `drawing_logs_ibfk_1` FOREIGN KEY (`upload_id`) REFERENCES `uploads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `drawing_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uploads`
--
ALTER TABLE `uploads`
  ADD CONSTRAINT `uploads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `upload_drawings`
--
ALTER TABLE `upload_drawings`
  ADD CONSTRAINT `upload_drawings_ibfk_1` FOREIGN KEY (`upload_id`) REFERENCES `uploads` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
