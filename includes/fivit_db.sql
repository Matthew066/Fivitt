-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 28, 2026 at 05:39 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fivit_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE `articles` (
  `id_articles` bigint(20) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id_badges` bigint(20) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bmi_records`
--

CREATE TABLE `bmi_records` (
  `id_bmi_records` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `height_cm` int(11) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `bmi_value` decimal(5,2) DEFAULT NULL,
  `recorded_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bmi_records`
--

INSERT INTO `bmi_records` (`id_bmi_records`, `user_id`, `height_cm`, `weight_kg`, `bmi_value`, `recorded_at`) VALUES
(1, 2, 170, 65.00, 22.49, '2026-02-18'),
(2, 1, 170, 55.00, 19.03, '2026-02-18'),
(3, 1, 150, 55.00, 24.44, '2026-02-18'),
(4, 1, 170, 55.00, 19.03, '2026-02-18'),
(5, 1, 170, 65.00, 22.49, '2026-02-18'),
(6, 1, 170, 90.00, 31.14, '2026-02-18'),
(7, 1, 170, 90.00, 31.14, '2026-02-18'),
(8, 1, 170, 90.00, 31.14, '2026-02-18'),
(9, 1, 170, 90.00, 31.14, '2026-02-18'),
(10, 1, 170, 90.00, 31.14, '2026-02-18'),
(11, 1, 170, 80.00, 27.68, '2026-02-18'),
(12, 1, 170, 50.00, 17.30, '2026-02-18'),
(13, 1, 170, 60.00, 20.76, '2026-02-18'),
(14, 1, 170, 60.00, 20.76, '2026-02-18'),
(15, 1, 170, 60.00, 20.76, '2026-02-18'),
(16, 1, 170, 60.00, 20.76, '2026-02-18'),
(17, 1, 155, 60.00, 24.97, '2026-02-18'),
(18, 1, 155, 60.00, 24.97, '2026-02-18'),
(19, 1, 155, 80.00, 33.30, '2026-02-18'),
(20, 1, 155, 80.00, 33.30, '2026-02-18'),
(21, 1, 155, 80.00, 33.30, '2026-02-18'),
(22, 1, 175, 80.00, 26.12, '2026-02-18'),
(23, 1, 190, 80.00, 22.16, '2026-02-18'),
(24, 1, 190, 80.00, 22.16, '2026-02-18'),
(25, 1, 190, 80.00, 22.16, '2026-02-18'),
(26, 1, 150, 80.00, 35.56, '2026-02-18'),
(27, 1, 150, 80.00, 35.56, '2026-02-18'),
(28, 1, 150, 70.00, 31.11, '2026-02-18'),
(29, 1, 130, 70.00, 41.42, '2026-02-18'),
(30, 1, 130, 70.00, 41.42, '2026-02-18');

-- --------------------------------------------------------

--
-- Table structure for table `daily_checkins`
--

CREATE TABLE `daily_checkins` (
  `id_daily_checkins` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `activity_minutes` int(11) DEFAULT NULL,
  `water_intake_ml` int(11) DEFAULT NULL,
  `checkin_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_checkins`
--

INSERT INTO `daily_checkins` (`id_daily_checkins`, `user_id`, `activity_minutes`, `water_intake_ml`, `checkin_date`, `created_at`) VALUES
(1, 2, 60, 250, '2026-02-18', '2026-02-18 05:30:08'),
(2, 1, 0, 500, '2026-02-18', '2026-02-18 06:13:09');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id_events` bigint(20) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `reward_points` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id_events`, `title`, `description`, `event_type`, `start_date`, `end_date`, `reward_points`, `is_active`) VALUES
(1, 'Morning Health Run 5K', 'Event lari santai sejauh 5KM untuk meningkatkan kebugaran dan menjaga kesehatan jantung. Terbuka untuk semua mahasiswa.', 'Santai', '2026-02-12', '2026-02-18', 30, 1);

-- --------------------------------------------------------

--
-- Table structure for table `event_participants`
--

CREATE TABLE `event_participants` (
  `id_event_participants` bigint(20) NOT NULL,
  `event_id` bigint(20) DEFAULT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_participants`
--

INSERT INTO `event_participants` (`id_event_participants`, `event_id`, `user_id`, `status`) VALUES
(1, 1, 1, 'registered');

-- --------------------------------------------------------

--
-- Table structure for table `feature_settings`
--

CREATE TABLE `feature_settings` (
  `id_feature_settings` bigint(20) NOT NULL,
  `feature_name` varchar(255) DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT NULL,
  `updated_by` bigint(20) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `foods`
--

CREATE TABLE `foods` (
  `id_foods` bigint(20) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `calories` int(11) DEFAULT NULL,
  `protein` decimal(5,2) DEFAULT NULL,
  `fat` decimal(5,2) DEFAULT NULL,
  `carbs` decimal(5,2) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `food_logs`
--

CREATE TABLE `food_logs` (
  `id_food_logs` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `food_id` bigint(20) DEFAULT NULL,
  `consumed_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gyms`
--

CREATE TABLE `gyms` (
  `id_gyms` bigint(20) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gym_bookings`
--

CREATE TABLE `gym_bookings` (
  `id_gym_bookings` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `gym_id` bigint(20) DEFAULT NULL,
  `booking_date` date DEFAULT NULL,
  `time_slot` varchar(100) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gym_equipments`
--

CREATE TABLE `gym_equipments` (
  `id_gym_equipments` bigint(20) NOT NULL,
  `gym_id` bigint(20) DEFAULT NULL,
  `equipment_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `point_logs`
--

CREATE TABLE `point_logs` (
  `id_point_logs` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sleep_logs`
--

CREATE TABLE `sleep_logs` (
  `id_sleep_logs` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `sleep_date` date DEFAULT NULL,
  `sleep_start` time DEFAULT NULL,
  `sleep_end` time DEFAULT NULL,
  `total_sleep_hours` decimal(5,2) DEFAULT NULL,
  `sleep_quality` varchar(100) DEFAULT NULL,
  `late_night` tinyint(1) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sleep_logs`
--

INSERT INTO `sleep_logs` (`id_sleep_logs`, `user_id`, `sleep_date`, `sleep_start`, `sleep_end`, `total_sleep_hours`, `sleep_quality`, `late_night`, `note`, `created_at`) VALUES
(1, 1, '2026-02-18', '00:00:00', '06:00:00', 8.00, NULL, 0, NULL, '2026-02-18 03:49:27'),
(3, 2, '2026-02-18', '22:00:00', '06:00:00', NULL, NULL, NULL, NULL, '2026-02-18 05:12:28'),
(4, 1, '2026-02-23', '22:00:00', '06:00:00', NULL, NULL, NULL, NULL, '2026-02-23 11:45:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_users` bigint(20) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_users`, `name`, `email`, `password_hash`, `role`, `department`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'phylicia', 'phylicia@gmail.com', '$2y$10$vy2FD/27VdAKJ2APgux03O29ReA74urQyFHFwPsKQAffj0rZp7ZQC', 'user', 'General', 1, '2026-02-18 03:13:16', '2026-02-18 03:13:16'),
(2, 'phyliciatiffany456', 'phyliciatiffany456@gmail.com', '$2y$10$XiWi.XgrUWT5.8BLm8QpBeZojdYLIGFGH3BSyVgO.Vxdej0VO9u26', 'user', 'General', 1, '2026-02-18 05:12:28', '2026-02-18 05:12:28');

-- --------------------------------------------------------

--
-- Table structure for table `user_badges`
--

CREATE TABLE `user_badges` (
  `id_user_badges` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `badge_id` bigint(20) DEFAULT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_points`
--

CREATE TABLE `user_points` (
  `id_user_points` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `total_points` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id_user_sessions` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `login_at` timestamp NULL DEFAULT NULL,
  `logout_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_streaks`
--

CREATE TABLE `user_streaks` (
  `id_user_streaks` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `streak_type` varchar(100) DEFAULT NULL,
  `current_streak` int(11) DEFAULT NULL,
  `longest_streak` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workout_personalizations`
--

CREATE TABLE `workout_personalizations` (
  `id_workout` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `goal` varchar(255) DEFAULT NULL,
  `fitness_level` varchar(100) DEFAULT NULL,
  `detail_workout` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workout_personalizations`
--

INSERT INTO `workout_personalizations` (`id_workout`, `user_id`, `goal`, `fitness_level`, `detail_workout`, `notes`, `created_at`, `name`) VALUES
(1, 1, '', '', '', '', '2026-02-18 08:22:46', NULL),
(2, 1, 'Fat Loss', 'Beginner', 'Jumping Jacks - 3 set x 14 repetisi\r\nHigh Knees - 3 set x 12 repetisi\r\nMountain Climbers - 3 set x 9 repetisi\r\nBurpees - 3 set x 9 repetisi\r\nSquats - 3 set x 11 repetisi\r\nLunges - 3 set x 9 repetisi\r\nPush Ups - 3 set x 11 repetisi\r\nPlank - 3 set x 10 repetisi\r\nGlute Bridge - 3 set x 9 repetisi\r\nBicycle Crunches - 13 menit\r\nJump Rope - 3 set x 10 repetisi\r\nJog in Place - 18 menit', '', '2026-02-18 08:41:10', NULL),
(3, 1, 'Fat Loss', 'Beginner', 'Jump Rope - 3 set x 13 repetisi\r\nHigh Knees - 3 set x 15 repetisi\r\nMountain Climbers - 3 set x 14 repetisi\r\nBurpees - 3 set x 15 repetisi\r\nJumping Jacks - 3 set x 13 repetisi\r\nJog in Place - 12 menit\r\nPlank - 3 set x 9 repetisi\r\nBicycle Crunches - 15 menit', '', '2026-02-18 08:44:50', NULL),
(4, 1, 'Fat Loss', 'Beginner', 'Jump Rope - 3 set x 15 repetisi\r\nHigh Knees - 3 set x 9 repetisi\r\nMountain Climbers - 3 set x 9 repetisi\r\nBurpees - 3 set x 11 repetisi\r\nJumping Jacks - 3 set x 14 repetisi\r\nJog in Place - 15 menit\r\nPlank - 3 set x 14 repetisi\r\nBicycle Crunches - 17 menit', '', '2026-02-18 08:47:00', NULL),
(5, 1, 'Fat Loss', 'Beginner', 'Jump Rope - 3 set x 11 repetisi\r\nHigh Knees - 3 set x 10 repetisi\r\nMountain Climbers - 3 set x 9 repetisi\r\nBurpees - 3 set x 12 repetisi\r\nJumping Jacks - 3 set x 12 repetisi\r\nJog in Place - 19 menit', '', '2026-02-18 08:50:02', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id_articles`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id_badges`);

--
-- Indexes for table `bmi_records`
--
ALTER TABLE `bmi_records`
  ADD PRIMARY KEY (`id_bmi_records`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `daily_checkins`
--
ALTER TABLE `daily_checkins`
  ADD PRIMARY KEY (`id_daily_checkins`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id_events`);

--
-- Indexes for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`id_event_participants`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feature_settings`
--
ALTER TABLE `feature_settings`
  ADD PRIMARY KEY (`id_feature_settings`),
  ADD UNIQUE KEY `feature_name` (`feature_name`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `foods`
--
ALTER TABLE `foods`
  ADD PRIMARY KEY (`id_foods`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `food_logs`
--
ALTER TABLE `food_logs`
  ADD PRIMARY KEY (`id_food_logs`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `food_id` (`food_id`);

--
-- Indexes for table `gyms`
--
ALTER TABLE `gyms`
  ADD PRIMARY KEY (`id_gyms`);

--
-- Indexes for table `gym_bookings`
--
ALTER TABLE `gym_bookings`
  ADD PRIMARY KEY (`id_gym_bookings`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `gym_id` (`gym_id`);

--
-- Indexes for table `gym_equipments`
--
ALTER TABLE `gym_equipments`
  ADD PRIMARY KEY (`id_gym_equipments`),
  ADD KEY `gym_id` (`gym_id`);

--
-- Indexes for table `point_logs`
--
ALTER TABLE `point_logs`
  ADD PRIMARY KEY (`id_point_logs`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sleep_logs`
--
ALTER TABLE `sleep_logs`
  ADD PRIMARY KEY (`id_sleep_logs`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_users`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id_user_badges`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `badge_id` (`badge_id`);

--
-- Indexes for table `user_points`
--
ALTER TABLE `user_points`
  ADD PRIMARY KEY (`id_user_points`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id_user_sessions`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_streaks`
--
ALTER TABLE `user_streaks`
  ADD PRIMARY KEY (`id_user_streaks`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `workout_personalizations`
--
ALTER TABLE `workout_personalizations`
  ADD PRIMARY KEY (`id_workout`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `articles`
--
ALTER TABLE `articles`
  MODIFY `id_articles` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id_badges` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bmi_records`
--
ALTER TABLE `bmi_records`
  MODIFY `id_bmi_records` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `daily_checkins`
--
ALTER TABLE `daily_checkins`
  MODIFY `id_daily_checkins` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id_events` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `event_participants`
--
ALTER TABLE `event_participants`
  MODIFY `id_event_participants` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feature_settings`
--
ALTER TABLE `feature_settings`
  MODIFY `id_feature_settings` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `foods`
--
ALTER TABLE `foods`
  MODIFY `id_foods` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `food_logs`
--
ALTER TABLE `food_logs`
  MODIFY `id_food_logs` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gyms`
--
ALTER TABLE `gyms`
  MODIFY `id_gyms` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gym_bookings`
--
ALTER TABLE `gym_bookings`
  MODIFY `id_gym_bookings` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gym_equipments`
--
ALTER TABLE `gym_equipments`
  MODIFY `id_gym_equipments` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `point_logs`
--
ALTER TABLE `point_logs`
  MODIFY `id_point_logs` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sleep_logs`
--
ALTER TABLE `sleep_logs`
  MODIFY `id_sleep_logs` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_users` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id_user_badges` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_points`
--
ALTER TABLE `user_points`
  MODIFY `id_user_points` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id_user_sessions` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_streaks`
--
ALTER TABLE `user_streaks`
  MODIFY `id_user_streaks` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workout_personalizations`
--
ALTER TABLE `workout_personalizations`
  MODIFY `id_workout` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id_users`) ON DELETE SET NULL;

--
-- Constraints for table `bmi_records`
--
ALTER TABLE `bmi_records`
  ADD CONSTRAINT `bmi_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE;

--
-- Constraints for table `daily_checkins`
--
ALTER TABLE `daily_checkins`
  ADD CONSTRAINT `daily_checkins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE;

--
-- Constraints for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id_events`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE;

--
-- Constraints for table `feature_settings`
--
ALTER TABLE `feature_settings`
  ADD CONSTRAINT `feature_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id_users`) ON DELETE SET NULL;

--
-- Constraints for table `foods`
--
ALTER TABLE `foods`
  ADD CONSTRAINT `foods_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id_users`) ON DELETE SET NULL;

--
-- Constraints for table `food_logs`
--
ALTER TABLE `food_logs`
  ADD CONSTRAINT `food_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_logs_ibfk_2` FOREIGN KEY (`food_id`) REFERENCES `foods` (`id_foods`) ON DELETE CASCADE;

--
-- Constraints for table `gym_bookings`
--
ALTER TABLE `gym_bookings`
  ADD CONSTRAINT `gym_bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE,
  ADD CONSTRAINT `gym_bookings_ibfk_2` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`id_gyms`) ON DELETE CASCADE;

--
-- Constraints for table `gym_equipments`
--
ALTER TABLE `gym_equipments`
  ADD CONSTRAINT `gym_equipments_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`id_gyms`) ON DELETE CASCADE;

--
-- Constraints for table `point_logs`
--
ALTER TABLE `point_logs`
  ADD CONSTRAINT `point_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE;

--
-- Constraints for table `sleep_logs`
--
ALTER TABLE `sleep_logs`
  ADD CONSTRAINT `sleep_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id_badges`) ON DELETE CASCADE;

--
-- Constraints for table `user_points`
--
ALTER TABLE `user_points`
  ADD CONSTRAINT `user_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE;

--
-- Constraints for table `user_streaks`
--
ALTER TABLE `user_streaks`
  ADD CONSTRAINT `user_streaks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE;

--
-- Constraints for table `workout_personalizations`
--
ALTER TABLE `workout_personalizations`
  ADD CONSTRAINT `workout_personalizations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
