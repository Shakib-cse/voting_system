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

-- --------------------------------------------------------

--
-- Table structure for table `participants_9_11`
--

CREATE TABLE `participants_9_11` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username_id` varchar(50) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `page_1` varchar(255) NOT NULL,
  `page_2` varchar(255) DEFAULT NULL,
  `page_3` varchar(255) DEFAULT NULL,
  `views` int NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participants_12_14`
--

CREATE TABLE `participants_12_14` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username_id` varchar(50) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `page_1` varchar(255) NOT NULL,
  `page_2` varchar(255) DEFAULT NULL,
  `page_3` varchar(255) DEFAULT NULL,
  `views` int NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `participants_12_14`
--

INSERT INTO `participants_12_14` (`id`, `username_id`, `name`, `email`, `page_1`, `page_2`, `page_3`, `views`, `created_at`) VALUES
(1, 'fawujip', 'Brynn Ayala', 'wubyz@mailinator.com', 'uploads/images/fawujip_page_1_1779042451_431.png', NULL, NULL, 0, '2026-05-17 18:27:31');

-- --------------------------------------------------------

--
-- Table structure for table `participants_15_17`
--

CREATE TABLE `participants_15_17` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username_id` varchar(50) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `page_1` varchar(255) NOT NULL,
  `page_2` varchar(255) DEFAULT NULL,
  `page_3` varchar(255) DEFAULT NULL,
  `views` int NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `participants_15_17`
--

INSERT INTO `participants_15_17` (`id`, `username_id`, `name`, `email`, `page_1`, `page_2`, `page_3`, `views`, `created_at`) VALUES
(1, 'wofytyz', 'Yoshi Swanson', 'fadoqowo@mailinator.com', 'uploads/images/wofytyz_page_1_1779041093_363.png', 'uploads/images/wofytyz_page_2_1779041093_714.jpeg', 'uploads/images/wofytyz_page_3_1779041093_169.png', 0, '2026-05-17 18:04:53');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username_id` varchar(50) NOT NULL,
  `age_category` varchar(20) NOT NULL,
  `voter_name` varchar(100) DEFAULT NULL,
  `voter_email` varchar(100) NOT NULL,
  `voter_ip` varchar(45) NOT NULL,
  `is_confirmed` tinyint NOT NULL DEFAULT 0,
  `confirmation_token` varchar(100) DEFAULT NULL,
  `voted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `username_id`, `age_category`, `voter_name`, `voter_email`, `voter_ip`, `is_confirmed`, `confirmation_token`, `voted_at`) VALUES
(1, 'wofytyz', '15-17', 'Voter One', 'qadihid@mailinator.com', '127.0.0.1', 1, NULL, '2026-05-17 18:05:57'),
(2, 'wofytyz', '15-17', 'Voter Two', 'basad@mailinator.com', '127.0.0.1', 1, NULL, '2026-05-17 18:28:31');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
