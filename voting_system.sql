-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 17, 2026 at 06:30 PM
-- Server version: 8.0.44
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `voting_system`
--

CREATE DATABASE IF NOT EXISTS `voting_system`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `voting_system`;

-- --------------------------------------------------------

--
-- Table structure for table `participants`
--

DROP TABLE IF EXISTS `participants`;
CREATE TABLE `participants` (
  `id` int NOT NULL,
  `username_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `age_category` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `page_1` varchar(255) NOT NULL,
  `page_2` varchar(255) DEFAULT NULL,
  `page_3` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `participants`
--

INSERT INTO `participants` (`id`, `username_id`, `name`, `age_category`, `email`, `page_1`, `page_2`, `page_3`, `created_at`) VALUES
(1, 'wofytyz', 'Yoshi Swanson', '15-17', 'fadoqowo@mailinator.com', 'uploads/images/wofytyz_page_1_1779041093_363.png', 'uploads/images/wofytyz_page_2_1779041093_714.jpeg', 'uploads/images/wofytyz_page_3_1779041093_169.png', '2026-05-17 18:04:53'),
(2, 'yoshi_swanson', 'Yoshi Swanson', '15-17', 'fadoqowo@mailinator.com', 'uploads/images/yoshi_swanson_page_1_1779041328_581.jpg', 'uploads/images/yoshi_swanson_page_2_1779041328_501.jpg', 'uploads/images/yoshi_swanson_page_3_1779041328_433.jpg', '2026-05-17 18:08:48'),
(3, 'fawujip', 'Brynn Ayala', '12-14', 'wubyz@mailinator.com', 'uploads/images/fawujip_page_1_1779042451_431.png', NULL, NULL, '2026-05-17 18:27:31');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int NOT NULL,
  `username_id` varchar(50) NOT NULL,
  `voter_email` varchar(100) NOT NULL,
  `voter_ip` varchar(45) NOT NULL,
  `voted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `username_id`, `voter_email`, `voter_ip`, `voted_at`) VALUES
(1, 'wofytyz', 'qadihid@mailinator.com', '127.0.0.1', '2026-05-17 18:05:57'),
(2, 'wofytyz', 'basad@mailinator.com', '127.0.0.1', '2026-05-17 18:28:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `participants`
--
ALTER TABLE `participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username_id` (`username_id`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username_id` (`username_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `participants`
--
ALTER TABLE `participants`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`username_id`) REFERENCES `participants` (`username_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
