-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 26, 2026 at 06:03 PM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `homehelp_dhomehelpb`
--

-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

CREATE TABLE `agents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_service_ids` varchar(255) DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `district` varchar(80) DEFAULT NULL,
  `neighbourhood` varchar(100) DEFAULT NULL,
  `start_date` date NOT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `rooms` tinyint(4) DEFAULT NULL,
  `outdoor_areas` tinyint(1) DEFAULT 0,
  `has_car` tinyint(1) DEFAULT 0,
  `has_pool` tinyint(1) DEFAULT 0,
  `dish_washing` tinyint(1) DEFAULT 0,
  `has_pets` tinyint(1) DEFAULT 0,
  `laundry` tinyint(1) DEFAULT 0,
  `chef_type` varchar(50) DEFAULT NULL,
  `num_people` tinyint(4) DEFAULT NULL,
  `num_adults` tinyint(4) DEFAULT NULL,
  `needs_menu` tinyint(1) DEFAULT 0,
  `languages` varchar(200) DEFAULT NULL,
  `live_in` tinyint(1) DEFAULT 0,
  `live_out` tinyint(1) DEFAULT 0,
  `living_area` varchar(50) DEFAULT NULL,
  `calculated_price` decimal(10,2) DEFAULT NULL,
  `selected_services` varchar(500) DEFAULT NULL,
  `budget` varchar(100) DEFAULT NULL,
  `status` enum('pending','under_review','completed','hold','approved','rejected','cancelled') DEFAULT 'pending',
  `agent_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `client_id`, `service_id`, `agent_id`, `location`, `district`, `neighbourhood`, `start_date`, `duration`, `frequency`, `requirements`, `rooms`, `outdoor_areas`, `has_car`, `has_pool`, `dish_washing`, `has_pets`, `laundry`, `chef_type`, `num_people`, `num_adults`, `needs_menu`, `languages`, `live_in`, `live_out`, `living_area`, `calculated_price`, `selected_services`, `budget`, `status`, `agent_notes`, `created_at`) VALUES
(9, 48, 6, NULL, 'Gikondo', 'Not specified', 'Gikondo', '2026-07-22', '', '6 days/week', '', 2, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, 'Kinyarwanda', 1, 0, '', 48000.00, '6,7', '', 'approved', '', '2026-07-22 06:47:43'),
(10, 48, 7, NULL, 'Gikondo', 'Not specified', 'Gikondo', '2026-07-22', '', '6 days/week', '', 2, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, 'Kinyarwanda', 1, 0, '', 48000.00, '6,7', '', 'pending', NULL, '2026-07-22 06:47:43'),
(12, 50, 7, NULL, 'Karongi', 'Not specified', 'Karongi', '2026-07-26', '', '6 days/week', '', 0, 1, 1, 1, 1, 1, 1, '', 0, 0, 0, 'English,Swahili,Kinyarwanda', 1, 0, '', 37500.00, '7', '', 'pending', NULL, '2026-07-26 15:52:16'),
(13, 51, 7, NULL, 'Gasabo', 'Not specified', 'Gasabo', '2026-07-26', '62', 'Temporary Work', '', 0, 0, 0, 0, 0, 0, 0, 'home', 8, 3, 0, '', 1, 0, '', 74500.00, '7,9', '', 'under_review', NULL, '2026-07-26 17:27:52'),
(14, 51, 9, NULL, 'Gasabo', 'Not specified', 'Gasabo', '2026-07-26', '62', 'Temporary Work', '', 0, 0, 0, 0, 0, 0, 0, 'home', 8, 3, 0, '', 1, 0, '', 74500.00, '7,9', '', 'pending', NULL, '2026-07-26 17:27:52'),
(22, 58, 9, NULL, 'Kibagabaga', 'Not specified', 'Kibagabaga', '2026-07-29', '', '6 days/week', '', 0, 0, 0, 0, 0, 0, 0, 'home', 5, 2, 0, 'English,French,Kinyarwanda', 0, 1, 'Within Kigali', 45000.00, '9', '', 'under_review', NULL, '2026-07-29 13:03:03'),
(29, 65, 9, NULL, 'Gisenyi', 'Not specified', 'Gisenyi', '2026-08-02', '', '6 days/week', '', 0, 0, 0, 0, 0, 0, 0, 'events', 0, 0, 0, '', 1, 0, '', 55000.00, '9', '', 'completed', 'We Appreciate   a paternship with you MUHIRE', '2026-08-02 12:53:10'),
(30, 66, 6, NULL, 'Kabeza', 'Not specified', 'Kabeza', '2026-08-02', '', '3 days/week', '', 0, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English', 0, 1, 'Within Kigali', 42500.00, '6,7', '', 'pending', NULL, '2026-08-02 18:14:17'),
(31, 66, 7, NULL, 'Kabeza', 'Not specified', 'Kabeza', '2026-08-02', '', '3 days/week', '', 0, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English', 0, 1, 'Within Kigali', 42500.00, '6,7', '', 'pending', NULL, '2026-08-02 18:14:17'),
(32, 67, 6, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-08-07', '', '6 days/week', '', 2, 0, 0, 0, 1, 0, 1, '', 0, 0, 0, 'English,Kinyarwanda', 1, 0, '', 51000.00, '6,7', '', 'under_review', NULL, '2026-08-07 11:06:37'),
(33, 67, 7, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-08-07', '', '6 days/week', '', 2, 0, 0, 0, 1, 0, 1, '', 0, 0, 0, 'English,Kinyarwanda', 1, 0, '', 51000.00, '6,7', '', 'pending', NULL, '2026-08-07 11:06:37'),
(34, 68, 6, NULL, 'Kagugu', 'Not specified', 'Kagugu', '2026-08-08', '30', 'Temporary Work', '', 0, 1, 0, 0, 1, 0, 1, 'home', 7, 5, 0, 'Kinyarwanda', 1, 0, '', 100500.00, '6,7,9', '', 'under_review', NULL, '2026-08-08 18:30:04'),
(35, 68, 7, NULL, 'Kagugu', 'Not specified', 'Kagugu', '2026-08-08', '30', 'Temporary Work', '', 0, 1, 0, 0, 1, 0, 1, 'home', 7, 5, 0, 'Kinyarwanda', 1, 0, '', 100500.00, '6,7,9', '', 'pending', NULL, '2026-08-08 18:30:04'),
(36, 68, 9, NULL, 'Kagugu', 'Not specified', 'Kagugu', '2026-08-08', '30', 'Temporary Work', '', 0, 1, 0, 0, 1, 0, 1, 'home', 7, 5, 0, 'Kinyarwanda', 1, 0, '', 100500.00, '6,7,9', '', 'pending', NULL, '2026-08-08 18:30:04'),
(37, 69, 7, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-08-09', '', '5 days/week', '', 3, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 'English,Kinyarwanda', 0, 1, 'Within Kigali', 25500.00, '7', '', 'under_review', NULL, '2026-08-09 15:57:36'),
(38, 70, 6, NULL, 'Gikondo', 'Not specified', 'Gikondo', '2026-08-13', '', 'Temporary Work', '', 0, 0, 0, 0, 0, 0, 0, 'events', 0, 0, 0, 'Kinyarwanda', 0, 0, '', 93500.00, '6,7,9', '', 'pending', NULL, '2026-08-13 15:34:25'),
(39, 70, 7, NULL, 'Gikondo', 'Not specified', 'Gikondo', '2026-08-13', '', 'Temporary Work', '', 0, 0, 0, 0, 0, 0, 0, 'events', 0, 0, 0, 'Kinyarwanda', 0, 0, '', 93500.00, '6,7,9', '', 'pending', NULL, '2026-08-13 15:34:25'),
(40, 70, 9, NULL, 'Gikondo', 'Not specified', 'Gikondo', '2026-08-13', '', 'Temporary Work', '', 0, 0, 0, 0, 0, 0, 0, 'events', 0, 0, 0, 'Kinyarwanda', 0, 0, '', 93500.00, '6,7,9', '', 'pending', NULL, '2026-08-13 15:34:25'),
(41, 72, 7, NULL, 'remera', 'Not specified', 'remera', '2026-08-19', '', '6 days/week', '', 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 'English,Kinyarwanda', 0, 0, '', 20500.00, '7', '', 'pending', NULL, '2026-08-19 10:57:22'),
(42, 73, 7, NULL, 'Gasabo', 'Not specified', 'Gasabo', '2026-08-19', '', '6 days/week', '', 0, 0, 1, 0, 0, 0, 0, '', 0, 0, 0, 'Kinyarwanda', 0, 0, '', 23500.00, '7', '', 'pending', NULL, '2026-08-19 19:38:34'),
(43, 50, 7, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-08-20', '7', 'Temporary Work', '', 5, 1, 1, 1, 1, 1, 1, '', 0, 0, 0, 'English,Swahili,Kinyarwanda', 1, 0, '', 47500.00, '7', '', 'pending', NULL, '2026-08-20 15:40:33'),
(44, 74, 6, NULL, 'Anyway', 'Not specified', 'Anyway', '2026-08-22', '4', '5 days/week', '', 4, 1, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English,French,Swahili', 0, 1, 'Within Kigali', 63000.00, '6,7', '', 'pending', NULL, '2026-08-22 08:14:19'),
(45, 74, 7, NULL, 'Anyway', 'Not specified', 'Anyway', '2026-08-22', '4', '5 days/week', '', 4, 1, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English,French,Swahili', 0, 1, 'Within Kigali', 63000.00, '6,7', '', 'pending', NULL, '2026-08-22 08:14:19'),
(46, 74, 9, NULL, 'Anyway', 'Not specified', 'Anyway', '2026-08-22', '', '6 days/week', '', 0, 0, 0, 0, 0, 0, 0, 'home', 2, 2, 0, 'English,Swahili', 0, 1, 'Within Kigali', 36000.00, '9', '', 'pending', NULL, '2026-08-22 08:18:45'),
(47, 75, 6, NULL, 'Ndera', 'Not specified', 'Ndera', '2026-08-23', '', '5 days/week', '', 5, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English,Swahili', 0, 1, 'Within Kigali', 64500.00, '6,7', '', 'pending', NULL, '2026-08-23 20:43:23'),
(48, 75, 7, NULL, 'Ndera', 'Not specified', 'Ndera', '2026-08-23', '', '5 days/week', '', 5, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English,Swahili', 0, 1, 'Within Kigali', 64500.00, '6,7', '', 'pending', NULL, '2026-08-23 20:43:23'),
(49, 76, 6, NULL, 'Kicukiru Kagarama, Muyange', 'Not specified', 'Kicukiru Kagarama, Muyange', '2026-08-24', '', '3 days/week', '', 2, 1, 0, 0, 1, 0, 1, '', 0, 0, 0, 'English', 0, 1, 'Within Kigali', 55000.00, '6,7', '', 'pending', NULL, '2026-08-24 09:08:32'),
(50, 76, 7, NULL, 'Kicukiru Kagarama, Muyange', 'Not specified', 'Kicukiru Kagarama, Muyange', '2026-08-24', '', '3 days/week', '', 2, 1, 0, 0, 1, 0, 1, '', 0, 0, 0, 'English', 0, 1, 'Within Kigali', 55000.00, '6,7', '', 'pending', NULL, '2026-08-24 09:08:32'),
(51, 77, 7, NULL, 'Gasabo/kacyiru', 'Not specified', 'Gasabo/kacyiru', '2026-08-24', '', '6 days/week', '', 10, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, '', 1, 0, '', 43000.00, '7', '', 'pending', NULL, '2026-08-24 11:27:22'),
(52, 78, 7, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-08-24', '', '6 days/week', '', 0, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, 'Kinyarwanda', 0, 0, '', 22500.00, '7', '', 'pending', NULL, '2026-08-24 15:31:37'),
(53, 79, 7, NULL, 'Ntarama', 'Not specified', 'Ntarama', '2026-08-26', '', '6 days/week', '', 0, 0, 0, 0, 0, 0, 1, '', 0, 0, 0, 'Kinyarwanda', 1, 0, '', 23500.00, '7', '', 'pending', NULL, '2026-08-26 21:48:14'),
(54, 82, 7, NULL, 'Biryogo', 'Not specified', 'Biryogo', '2026-09-02', '', '6 days/week', '', 1, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 'English', 1, 0, '', 20500.00, '7', '', 'pending', NULL, '2026-09-02 16:00:05'),
(55, 83, 9, NULL, 'Remera', 'Not specified', 'Remera', '2026-09-04', '14', 'Temporary Work', '', 0, 0, 0, 0, 0, 0, 0, 'home', 4, 2, 0, 'French,Kinyarwanda', 0, 1, 'Within Kigali', 42000.00, '9', '', 'pending', NULL, '2026-09-04 09:57:36'),
(56, 84, 6, NULL, 'Gihara', 'Not specified', 'Gihara', '2026-09-04', '', '6 days/week', '', 0, 1, 0, 0, 1, 0, 1, '', 0, 0, 0, 'Kinyarwanda', 1, 0, '', 49500.00, '6,7', '', 'pending', NULL, '2026-09-04 12:16:11'),
(57, 84, 7, NULL, 'Gihara', 'Not specified', 'Gihara', '2026-09-04', '', '6 days/week', '', 0, 1, 0, 0, 1, 0, 1, '', 0, 0, 0, 'Kinyarwanda', 1, 0, '', 49500.00, '6,7', '', 'pending', NULL, '2026-09-04 12:16:11'),
(58, 85, 6, NULL, 'gisosi, belle étoile', 'Not specified', 'gisosi, belle étoile', '2026-09-06', '', '5 days/week', '', 4, 1, 0, 0, 1, 0, 1, 'home', 4, 3, 0, 'English,French', 0, 1, 'Within Kigali', 108000.00, '6,7,9', '', 'pending', NULL, '2026-09-06 08:49:01'),
(59, 85, 7, NULL, 'gisosi, belle étoile', 'Not specified', 'gisosi, belle étoile', '2026-09-06', '', '5 days/week', '', 4, 1, 0, 0, 1, 0, 1, 'home', 4, 3, 0, 'English,French', 0, 1, 'Within Kigali', 108000.00, '6,7,9', '', 'pending', NULL, '2026-09-06 08:49:01'),
(60, 85, 9, NULL, 'gisosi, belle étoile', 'Not specified', 'gisosi, belle étoile', '2026-09-06', '', '5 days/week', '', 4, 1, 0, 0, 1, 0, 1, 'home', 4, 3, 0, 'English,French', 0, 1, 'Within Kigali', 108000.00, '6,7,9', '', 'pending', NULL, '2026-09-06 08:49:01'),
(61, 87, 6, NULL, 'Remera /kicukiro', 'Not specified', 'Remera /kicukiro', '2026-09-09', '', 'Temporary Work', '', 0, 0, 0, 0, 0, 1, 0, '', 0, 0, 0, 'English,Kinyarwanda', 0, 1, 'Within Kigali', 20000.00, '6', '', 'pending', NULL, '2026-09-09 15:47:47'),
(62, 87, 6, NULL, 'Not specified', 'Not specified', '', '2026-09-09', '', 'Temporary Work', '', 0, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English,Kinyarwanda', 0, 1, 'Within Kigali', 20000.00, '6', '', 'under_review', NULL, '2026-09-09 15:48:36'),
(63, 87, 7, NULL, 'Not specified', 'Not specified', '', '2026-09-09', '', 'Temporary Work', '', 0, 0, 0, 0, 1, 1, 1, '', 0, 0, 0, 'English,Kinyarwanda', 1, 1, 'Within Kigali', 27500.00, '7', '', 'pending', NULL, '2026-09-09 15:49:23'),
(64, 88, 7, NULL, 'Kanombe', 'Not specified', 'Kanombe', '2026-09-10', '', '6 days/week', '', 0, 1, 0, 0, 1, 0, 1, '', 0, 0, 0, 'English,French', 1, 0, '', 27500.00, '7', '', 'under_review', NULL, '2026-09-10 19:30:44'),
(65, 89, 6, NULL, 'Gisozi', 'Not specified', 'Gisozi', '2026-09-15', '', '5 days/week', '', 3, 1, 0, 0, 0, 0, 0, '', 0, 0, 0, '', 0, 1, 'Within Kigali', 26000.00, '6', '', 'pending', NULL, '2026-09-15 07:18:19'),
(66, 90, 6, NULL, 'Nyarugunga', 'Not specified', 'Nyarugunga', '2026-09-16', '', '6 days/week', '', 0, 1, 0, 0, 1, 0, 0, 'home', 6, 6, 0, 'Kinyarwanda', 1, 0, '', 70000.00, '6,9', '', 'under_review', NULL, '2026-09-16 14:35:49'),
(67, 90, 9, NULL, 'Nyarugunga', 'Not specified', 'Nyarugunga', '2026-09-16', '', '6 days/week', '', 0, 1, 0, 0, 1, 0, 0, 'home', 6, 6, 0, 'Kinyarwanda', 1, 0, '', 70000.00, '6,9', '', 'pending', NULL, '2026-09-16 14:35:49'),
(68, 91, 7, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-09-17', '30', 'Temporary Work', '', 0, 1, 0, 1, 1, 0, 1, '', 0, 0, 0, 'English,French,Swahili,Kinyarwanda', 1, 0, '', 32500.00, '7', '', 'pending', NULL, '2026-09-17 10:39:05'),
(69, 92, 9, NULL, 'Kukadege Murugunga', 'Not specified', 'Kukadege Murugunga', '2026-09-17', '1', 'Temporary Work', '', 0, 0, 0, 0, 0, 0, 0, 'home', 1, 1, 0, 'English,Swahili', 1, 0, 'Within Kigali', 33000.00, '9', '', 'under_review', NULL, '2026-09-17 19:05:21'),
(70, 96, 7, NULL, 'Gasabo', 'Not specified', 'Gasabo', '2026-09-19', '', 'Temporary Work', '', 0, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, '', 1, 0, '', 22500.00, '7', '', 'pending', NULL, '2026-09-19 16:56:25'),
(71, 97, 6, NULL, 'Not specified', 'Not specified', '', '2026-09-22', '', '5 days/week', '', 4, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 'English', 1, 0, '', 27000.00, '6', '', 'under_review', NULL, '2026-09-22 14:00:51'),
(72, 98, 7, NULL, 'Not specified', 'Not specified', '', '2026-09-27', '', '5 days/week', '', 0, 1, 0, 0, 1, 0, 1, '', 0, 0, 0, 'English,Kinyarwanda', 0, 1, 'Within Kigali', 27500.00, '7', '', 'pending', NULL, '2026-09-27 17:13:18'),
(73, 98, 6, NULL, 'Not specified', 'Not specified', '', '2026-09-27', '', '5 days/week', '', 0, 1, 0, 0, 1, 0, 0, '', 0, 0, 0, '', 0, 1, 'Within Kigali', 22000.00, '6', '', 'under_review', NULL, '2026-09-27 17:18:04'),
(74, 99, 6, NULL, 'Kanombe', 'Not specified', 'Kanombe', '2026-09-28', '30', 'Temporary Work', '', 0, 1, 0, 0, 1, 0, 0, '', 0, 0, 0, '', 1, 0, '', 46500.00, '6,7', '', 'cancelled', NULL, '2026-09-28 07:21:08'),
(75, 99, 7, NULL, 'Kanombe', 'Not specified', 'Kanombe', '2026-09-28', '30', 'Temporary Work', '', 0, 1, 0, 0, 1, 0, 0, '', 0, 0, 0, '', 1, 0, '', 46500.00, '6,7', '', 'cancelled', NULL, '2026-09-28 07:21:08'),
(76, 100, 6, NULL, 'Kanombe', 'Not specified', 'Kanombe', '2026-09-29', '', '6 days/week', '', 4, 1, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English,Kinyarwanda', 0, 1, 'Within Kigali', 31000.00, '6', '', 'under_review', NULL, '2026-09-28 23:19:21'),
(77, 100, 7, NULL, 'Not specified', 'Not specified', '', '2026-09-29', '', '5 days/week', '', 3, 1, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English,Kinyarwanda', 0, 1, 'Within Kigali', 29500.00, '7', '', 'under_review', NULL, '2026-09-28 23:20:43'),
(78, 40, 9, NULL, 'Not specified', 'Not specified', '', '2026-10-02', '', '5 days/week', '', 0, 0, 0, 0, 0, 0, 0, 'home', 8, 6, 1, 'Kinyarwanda', 0, 1, 'Within Kigali', 54000.00, '9', '', 'completed', 'This was a login test.', '2026-10-02 17:42:42'),
(79, 40, 6, NULL, 'Not specified', 'Not specified', '', '2026-10-02', '', '5 days/week', '', 0, 1, 1, 0, 1, 0, 1, 'home', 7, 5, 0, 'Kinyarwanda', 0, 1, 'Within Kigali', 106500.00, '6,7,9', '', 'completed', 'This was a login test.', '2026-10-02 17:50:09'),
(80, 40, 7, NULL, 'Not specified', 'Not specified', '', '2026-10-02', '', '5 days/week', '', 0, 1, 1, 0, 1, 0, 1, 'home', 7, 5, 0, 'Kinyarwanda', 0, 1, 'Within Kigali', 106500.00, '6,7,9', '', 'completed', 'This was a login test.', '2026-10-02 17:50:09'),
(81, 40, 9, NULL, 'Not specified', 'Not specified', '', '2026-10-02', '', '5 days/week', '', 0, 1, 1, 0, 1, 0, 1, 'home', 7, 5, 0, 'Kinyarwanda', 0, 1, 'Within Kigali', 106500.00, '6,7,9', '', 'completed', 'This was a login test.', '2026-10-02 17:50:09'),
(82, 101, 9, NULL, 'nyarutarama', 'Not specified', 'nyarutarama', '2026-10-02', '', '3 days/week', '', 0, 0, 0, 0, 0, 0, 0, 'home', 4, 2, 0, 'French', 0, 1, 'Within Kigali', 42000.00, '9', '', 'under_review', NULL, '2026-10-02 17:53:02'),
(83, 102, 6, NULL, 'Nyamata', 'Not specified', 'Nyamata', '2026-10-02', '', '6 days/week', '', 4, 1, 1, 0, 1, 0, 1, '', 0, 0, 0, 'English,Kinyarwanda', 1, 0, '', 72000.00, '6,7', '', 'completed', '', '2026-10-02 20:10:41'),
(84, 102, 7, NULL, 'Nyamata', 'Not specified', 'Nyamata', '2026-10-02', '', '6 days/week', '', 4, 1, 1, 0, 1, 0, 1, '', 0, 0, 0, 'English,Kinyarwanda', 1, 0, '', 72000.00, '6,7', '', 'under_review', NULL, '2026-10-02 20:10:41'),
(85, 103, 6, NULL, 'Kimihurura', 'Not specified', 'Kimihurura', '2026-10-05', '', '5 days/week', '', 0, 0, 0, 0, 1, 0, 1, 'home', 3, 0, 0, 'English', 0, 1, 'Within Kigali', 84500.00, '6,7,9', '', 'under_review', NULL, '2026-10-05 13:47:09'),
(86, 103, 7, NULL, 'Kimihurura', 'Not specified', 'Kimihurura', '2026-10-05', '', '5 days/week', '', 0, 0, 0, 0, 1, 0, 1, 'home', 3, 0, 0, 'English', 0, 1, 'Within Kigali', 84500.00, '6,7,9', '', 'pending', NULL, '2026-10-05 13:47:09'),
(87, 103, 9, NULL, 'Kimihurura', 'Not specified', 'Kimihurura', '2026-10-05', '', '5 days/week', '', 0, 0, 0, 0, 1, 0, 1, 'home', 3, 0, 0, 'English', 0, 1, 'Within Kigali', 84500.00, '6,7,9', '', 'pending', NULL, '2026-10-05 13:47:09'),
(88, 104, 6, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-10-06', '', '5 days/week', '', 4, 1, 1, 0, 1, 0, 0, 'home', 2, 2, 0, 'Kinyarwanda', 1, 1, 'Within Kigali', 105000.00, '6,7,9', '', 'under_review', NULL, '2026-10-06 16:03:07'),
(89, 104, 7, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-10-06', '', '5 days/week', '', 4, 1, 1, 0, 1, 0, 0, 'home', 2, 2, 0, 'Kinyarwanda', 1, 1, 'Within Kigali', 105000.00, '6,7,9', '', 'under_review', NULL, '2026-10-06 16:03:07'),
(90, 104, 9, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-10-06', '', '5 days/week', '', 4, 1, 1, 0, 1, 0, 0, 'home', 2, 2, 0, 'Kinyarwanda', 1, 1, 'Within Kigali', 105000.00, '6,7,9', '', 'under_review', NULL, '2026-10-06 16:03:07'),
(91, 106, 6, NULL, 'Nyamirambo', 'Not specified', 'Nyamirambo', '2026-10-08', '', '6 days/week', '', 0, 1, 1, 0, 1, 0, 0, '', 0, 0, 0, 'Kinyarwanda', 0, 1, 'Within Kigali', 25000.00, '6', '', 'under_review', NULL, '2026-10-08 19:13:49'),
(92, 107, 9, NULL, 'Kibagabaga', 'Not specified', 'Kibagabaga', '2026-10-09', '', '6 days/week', '', 0, 0, 0, 0, 0, 0, 0, 'home', 0, 2, 0, 'English', 0, 1, 'Within Kigali', 33000.00, '9', '', 'under_review', NULL, '2026-10-09 19:27:15'),
(93, 108, 9, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-10-12', '', '6 days/week', '', 0, 0, 0, 0, 0, 0, 0, 'home', 6, 6, 0, 'English,Kinyarwanda', 0, 1, 'Within Kigali', 48000.00, '9', '', 'under_review', NULL, '2026-10-12 15:26:04'),
(94, 109, 6, NULL, 'Kabuga', 'Not specified', 'Kabuga', '2026-10-13', '', '6 days/week', '', 0, 0, 1, 0, 1, 0, 0, '', 0, 0, 0, 'Kinyarwanda', 1, 0, '', 23000.00, '6', '', 'under_review', NULL, '2026-10-13 05:54:12'),
(95, 111, 7, NULL, 'Ruhango', 'Not specified', 'Ruhango', '2026-10-14', '', '5 days/week', '', 2, 1, 1, 1, 1, 1, 1, '', 0, 0, 0, 'Kinyarwanda', 0, 0, '', 40000.00, '7', '', 'under_review', NULL, '2026-10-14 08:27:46'),
(96, 111, 6, NULL, 'Ruhango', 'Not specified', 'Ruhango', '2026-10-14', '', '5 days/week', '', 3, 1, 1, 1, 1, 1, 0, '', 0, 0, 0, '', 1, 0, '', 38000.00, '6', '', 'under_review', NULL, '2026-10-14 08:33:43'),
(97, 112, 7, NULL, 'Kimisagara', 'Not specified', 'Kimisagara', '2026-10-16', '', '6 days/week', '', 3, 1, 0, 0, 1, 0, 1, '', 0, 0, 0, 'Kinyarwanda', 1, 0, '', 32500.00, '7', '', 'under_review', NULL, '2026-10-16 14:57:56'),
(98, 113, 6, NULL, 'Kimironko /Remera', 'Not specified', 'Kimironko /Remera', '2026-10-16', '', '3 days/week', '', 4, 1, 0, 0, 1, 0, 0, 'home', 4, 4, 0, '', 1, 0, '', 105000.00, '6,7,9', '', 'under_review', NULL, '2026-10-16 15:54:20'),
(99, 113, 7, NULL, 'Kimironko /Remera', 'Not specified', 'Kimironko /Remera', '2026-10-16', '', '3 days/week', '', 4, 1, 0, 0, 1, 0, 0, 'home', 4, 4, 0, '', 1, 0, '', 105000.00, '6,7,9', '', 'under_review', NULL, '2026-10-16 15:54:20'),
(100, 113, 9, NULL, 'Kimironko /Remera', 'Not specified', 'Kimironko /Remera', '2026-10-16', '', '3 days/week', '', 4, 1, 0, 0, 1, 0, 0, 'home', 4, 4, 0, '', 1, 0, '', 105000.00, '6,7,9', '', 'under_review', NULL, '2026-10-16 15:54:20'),
(101, 114, 7, NULL, 'Kicukiro', 'Not specified', 'Kicukiro', '2026-10-17', '', 'Temporary Work', '', 0, 1, 1, 1, 1, 1, 1, '', 0, 0, 0, 'English', 1, 0, '', 37500.00, '7', '', 'under_review', NULL, '2026-10-17 09:52:36'),
(102, 115, 7, NULL, 'Kimironko', 'Not specified', 'Kimironko', '2026-10-18', '', '3 days/week', '', 2, 0, 0, 0, 1, 0, 1, '', 0, 0, 0, 'English', 0, 1, '', 28000.00, '7', '', 'under_review', NULL, '2026-10-18 16:37:22'),
(103, 116, 9, NULL, 'Not specified', 'Not specified', '', '2026-10-20', '', '3 days/week', '', 0, 0, 0, 0, 0, 0, 0, 'home', 1, 1, 0, 'Kinyarwanda', 0, 1, 'Within Kigali', 33000.00, '9', '', 'under_review', NULL, '2026-10-20 16:40:46'),
(104, 117, 9, NULL, 'Gasharu', 'Not specified', 'Gasharu', '2026-10-21', '', '6 days/week', '', 0, 0, 0, 0, 0, 0, 0, 'home', 1, 1, 0, 'Kinyarwanda', 0, 1, 'Within Kigali', 33000.00, '9', '', 'under_review', NULL, '2026-10-21 19:07:26'),
(105, 118, 6, NULL, 'Musanze', 'Not specified', 'Musanze', '2026-10-22', '', '5 days/week', '', 0, 0, 0, 0, 1, 0, 0, '', 0, 0, 0, 'English,Kinyarwanda', 0, 0, '', 20000.00, '6', '', 'under_review', NULL, '2026-10-22 11:53:10'),
(106, 119, 6, NULL, 'Kinyinya. Gacuriro. Kagugu .nyarutarama.kibagabaga', 'Not specified', 'Kinyinya. Gacuriro. Kagugu .nyarutarama.kibagabaga', '2026-10-22', '', '3 days/week', '', 0, 1, 1, 0, 0, 0, 0, '', 0, 0, 0, 'English,Kinyarwanda', 0, 0, '', 23000.00, '6', '', 'under_review', NULL, '2026-10-22 21:28:33'),
(107, 120, 6, NULL, 'Kinyinya', 'Not specified', 'Kinyinya', '2026-10-23', '', '6 days/week', '', 0, 1, 1, 0, 1, 0, 1, 'home', 0, 2, 0, '', 1, 1, 'Within Kigali', 88500.00, '6,7,9', '', 'under_review', NULL, '2026-10-23 09:36:18'),
(108, 120, 7, NULL, 'Kinyinya', 'Not specified', 'Kinyinya', '2026-10-23', '', '6 days/week', '', 0, 1, 1, 0, 1, 0, 1, 'home', 0, 2, 0, '', 1, 1, 'Within Kigali', 88500.00, '6,7,9', '', 'under_review', NULL, '2026-10-23 09:36:18'),
(109, 120, 9, NULL, 'Kinyinya', 'Not specified', 'Kinyinya', '2026-10-23', '', '6 days/week', '', 0, 1, 1, 0, 1, 0, 1, 'home', 0, 2, 0, '', 1, 1, 'Within Kigali', 88500.00, '6,7,9', '', 'under_review', NULL, '2026-10-23 09:36:18'),
(110, 121, 7, NULL, 'Gasabo Rusororo', 'Not specified', 'Gasabo Rusororo', '2026-10-25', '', 'Temporary Work', '', 0, 0, 0, 1, 1, 0, 1, '', 0, 0, 0, 'Kinyarwanda', 1, 0, '', 30500.00, '7', '', 'under_review', NULL, '2026-10-25 16:04:21'),
(111, 122, 7, NULL, 'Bugesera Mayange', 'Not specified', 'Bugesera Mayange', '2026-10-26', '', '3 days/week', '', 15, 1, 0, 0, 1, 1, 1, '', 0, 0, 0, 'English,French', 1, 1, 'Outside Kigali', 64500.00, '7', '', 'under_review', NULL, '2026-10-26 12:44:28');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `topic` varchar(100) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `first_name`, `last_name`, `email`, `topic`, `message`, `ip_address`, `is_read`, `created_at`) VALUES
(5, 'Jeanne', 'Umutoni', 'janeumutoniwase@gmail.com', 'Job Application Help', 'Ndifuza akazi Ka housekeeping Gakora hagati yamasaha 8-10 \r\nNimero yanjye ni 0796337217\r\nMurakoze', '197.157.135.32', 1, '2026-08-21 15:10:20'),
(6, 'Irene', 'Umutesi', 'Umutesii300@gmail.com', 'Job Application Help', 'how can I apply for a nanny job', '41.186.134.251', 0, '2026-09-17 13:26:09'),
(7, 'Nyirabuntu', 'Kevine', 'ukevine55@gmail.com', '', 'I need a job', '41.186.133.192', 0, '2026-10-03 10:33:04'),
(8, 'Niyomushumba', 'Damascene', 'niyomushumbadamascene68@gmail.com', 'Job Application Help', 'I want job to be house keeper', '41.186.134.173', 0, '2026-10-03 11:58:56'),
(9, 'Esther', 'Ndirui', 'estherjoden@gmail.com', 'Job Application Help', 'Hello Home Help Rwanda team, my name is Esther. I am a Kenyan-certified Healthcare Assistant with clinical attachment experience in bedside care, vital signs monitoring, and elderly support. I am seeking live-in or home caregiving placements in Kigali for English-speaking families or private patients. Could you please let me know how I can list my profile or register on your caregiver network?', '154.159.237.248', 0, '2026-10-04 05:10:42'),
(10, 'NIYOKWIZERWA', 'Fideline', 'niyokwizerwafideline@gmail.com', 'Job Application Help', 'My name is Niyokwizerwa  Fideline. I studied nursing at kibogora polytechnic and I wanted to ask if there are any job opportunities available in home healthcare services.', '197.157.184.154', 0, '2026-10-07 18:56:53'),
(11, 'Eliezer', 'Bitegetsimana', 'eliezerbitegetsimana0@gmail.com', 'Job Application Help', 'I want job like house guide', '197.157.186.128', 0, '2026-10-09 14:12:49'),
(12, 'Joanna', 'Riggs', 'joannariggs25@gmail.com', 'Partnership', 'Hi,\r\n\r\nI was looking over homehelp.rw and wanted to reach out.\r\n\r\nWe help brands and businesses quickly tell their story through short promotional videos for websites and social media.\r\n\r\nWe keep things pretty lean — projects start from one hundred and ninety-five dollars USD, which includes the script, voice-over, and full production.\r\n\r\nHappy to send over a couple of samples if you\'re open to taking a look.\r\n\r\nBest,\r\nJo Riggs', '23.229.92.176', 0, '2026-10-17 20:15:12'),
(13, 'Doorine', 'Tchama', 'tchama.doorine@gmail.com', 'Job Application Help', 'I would like to know if you offer paid private chef internships? Or just Volunteer  private chef ready to show His talent and make it available for customers,\r\nThank you.', '102.22.141.247', 0, '2026-10-19 13:56:42'),
(14, 'Rwakari', 'Mose', 'rwakarirwakari8@gmail.com', '', 'Muraho neza. Nitwa Rwakari. Ndashaka kubaza niba mufite akazi ko gukora isuku cyangwa akandi kazi koroheje i Kigali. Niteguye gutangira akazi kandi nshobora gukora neza. Niba mufite umwanya uhari, mwambwira aho nakwiyandikisha cyangwa aho nasanga ushinzwe abakozi? Murakoze.', '197.157.145.82', 0, '2026-10-22 22:43:47');

-- --------------------------------------------------------

--
-- Table structure for table `email_log`
--

CREATE TABLE `email_log` (
  `id` int(11) NOT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `status` enum('sent','failed') DEFAULT 'sent',
  `created_at` datetime DEFAULT current_timestamp(),
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `email_log`
--

INSERT INTO `email_log` (`id`, `recipient`, `subject`, `status`, `created_at`, `error_message`) VALUES
(1, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-02 12:53:11', ''),
(2, 'muhirejean3@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-08-02 12:58:53', ''),
(3, 'muhirejean3@gmail.com', '? Great News! A HomeHelp Worker Has Been Assigned — Home Help Rwanda', 'sent', '2026-08-02 13:00:13', ''),
(4, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-02 18:14:18', ''),
(5, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-07 11:06:38', ''),
(6, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-08 18:30:05', ''),
(7, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-09 15:57:37', ''),
(8, 'zilfatiusanase@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-08-09 16:52:44', ''),
(9, 'kiraboayra63@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-08-09 16:58:22', ''),
(10, 'niyigenapamella@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-08-09 16:59:56', ''),
(11, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-13 15:34:26', ''),
(12, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-19 10:57:23', ''),
(13, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-19 19:38:37', ''),
(14, 'homehelpservicerw@gmail.com', '? New Booking — Home Help Rwanda', 'sent', '2026-08-20 15:40:35', ''),
(15, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-22 08:14:20', ''),
(16, 'homehelpservicerw@gmail.com', '? New Booking — Home Help Rwanda', 'sent', '2026-08-22 08:18:46', ''),
(17, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-23 20:43:25', ''),
(18, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-24 09:08:34', ''),
(19, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-24 11:27:23', ''),
(20, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-24 15:31:39', ''),
(21, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-08-26 21:48:17', ''),
(22, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-02 16:00:07', ''),
(23, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-04 09:57:37', ''),
(24, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-04 12:16:13', ''),
(25, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-06 08:49:02', ''),
(26, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-09 15:47:48', ''),
(27, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-09 15:48:37', ''),
(28, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-09 15:49:24', ''),
(29, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-10 19:30:45', ''),
(30, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-15 07:18:20', ''),
(31, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-16 14:35:51', ''),
(32, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-17 10:39:08', ''),
(33, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-17 19:05:22', ''),
(34, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-19 16:56:26', ''),
(35, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-22 14:00:54', ''),
(36, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-27 17:13:20', ''),
(37, 'homehelpservicerw@gmail.com', '? New Booking — Home Help Rwanda', 'sent', '2026-09-27 17:18:05', ''),
(38, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-28 07:21:09', ''),
(39, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-28 23:19:22', ''),
(40, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-09-28 23:20:43', ''),
(41, 'noedavis0104@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-01 19:36:40', ''),
(42, 'noedavis0104@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-01 19:37:46', ''),
(43, 'niyondagijejose@3gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-02 08:40:41', ''),
(44, 'niidkoff@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-02 08:42:15', ''),
(45, 'mahirwenorbert@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-02 08:43:19', ''),
(46, '17629814816@163.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-02 17:38:13', ''),
(47, 'kanyanapeace2020@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-02 17:38:38', ''),
(48, 'mojeedkhairat@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-02 17:39:01', ''),
(49, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-02 17:42:42', ''),
(50, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-02 17:50:10', ''),
(51, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-02 17:53:03', ''),
(52, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-02 20:10:43', ''),
(53, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-05 13:47:11', ''),
(54, 'moneerayas@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-05 14:34:07', ''),
(55, 'julesbo4@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-06 13:24:11', ''),
(56, 'ifiston.ishimwe@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-06 13:29:57', ''),
(57, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-06 16:03:08', ''),
(58, 'gisubizongabosolennel@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-06 20:55:32', ''),
(59, 'gisubizongabosolennel@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-06 21:18:45', ''),
(60, 'gisubizongabosolennel@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-06 21:19:01', ''),
(61, 'admin@homehelp.rw', 'Your HomeHelp Rwanda Request is Under Review', 'failed', '2026-10-06 21:19:31', 'SMTP Error: The following recipients failed: admin@homehelp.rw: No Such User Here\r\n'),
(62, 'admin@homehelp.rw', '? Great News! A HomeHelp Worker Has Been Assigned — Home Help Rwanda', 'failed', '2026-10-06 21:20:30', 'SMTP Error: The following recipients failed: admin@homehelp.rw: No Such User Here\r\n'),
(63, 'admin@homehelp.rw', 'Your HomeHelp Rwanda Request is Under Review', 'failed', '2026-10-06 21:20:37', 'SMTP Error: The following recipients failed: admin@homehelp.rw: No Such User Here\r\n'),
(64, 'admin@homehelp.rw', '? Great News! A HomeHelp Worker Has Been Assigned — Home Help Rwanda', 'failed', '2026-10-06 21:20:49', 'SMTP Error: The following recipients failed: admin@homehelp.rw: No Such User Here\r\n'),
(65, 'admin@homehelp.rw', 'Your HomeHelp Rwanda Request is Under Review', 'failed', '2026-10-06 21:21:03', 'SMTP Error: The following recipients failed: admin@homehelp.rw: No Such User Here\r\n'),
(66, 'admin@homehelp.rw', '? Great News! A HomeHelp Worker Has Been Assigned — Home Help Rwanda', 'failed', '2026-10-06 21:21:20', 'SMTP Error: The following recipients failed: admin@homehelp.rw: No Such User Here\r\n'),
(67, 'admin@homehelp.rw', 'Your HomeHelp Rwanda Request is Under Review', 'failed', '2026-10-06 21:21:29', 'SMTP Error: The following recipients failed: admin@homehelp.rw: No Such User Here\r\n'),
(68, 'admin@homehelp.rw', '? Great News! A HomeHelp Worker Has Been Assigned — Home Help Rwanda', 'failed', '2026-10-06 21:21:47', 'SMTP Error: The following recipients failed: admin@homehelp.rw: No Such User Here\r\n'),
(69, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-08 19:13:51', ''),
(70, 'jeanmarieemper@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-09 08:48:47', ''),
(71, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-09 19:27:17', ''),
(72, 'mavundused@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-10 09:36:59', ''),
(73, 'ifiston.ishimwe@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-12 14:47:24', ''),
(74, 'terrymumbi@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-12 15:18:26', ''),
(75, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-12 15:26:05', ''),
(76, 'queenethnah@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-12 15:28:25', ''),
(77, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-13 05:54:13', ''),
(78, 'tewonesiteniyomunjyeri@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-13 10:47:27', ''),
(79, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-14 08:27:48', ''),
(80, 'homehelpservicerw@gmail.com', '? New Booking — Home Help Rwanda', 'sent', '2026-10-14 08:33:45', ''),
(81, 'waki35435@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-14 12:12:15', ''),
(82, 'waki35435@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-14 12:17:05', ''),
(83, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-16 14:57:57', ''),
(84, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-16 15:54:21', ''),
(85, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-17 09:52:38', ''),
(86, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-18 16:37:25', ''),
(87, 'puwineza889@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-19 08:21:32', ''),
(88, 'ishaura08@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-19 08:22:41', ''),
(89, 'ishaura08@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-19 08:23:51', ''),
(90, 'ingabireisamia@icloud.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-19 08:24:40', ''),
(91, 'tetaange42@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-19 08:24:59', ''),
(92, 'ishaura08@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-20 13:16:27', ''),
(93, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-20 16:40:47', ''),
(94, 'umulisaadi@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-20 16:49:28', ''),
(95, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-21 19:07:29', ''),
(96, 'isaacsteven123@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-22 08:06:56', ''),
(97, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-22 11:53:11', ''),
(98, 'dorcasndayitegereje@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-22 20:51:51', ''),
(99, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-22 21:28:35', ''),
(100, 'manassendahimana@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-23 06:56:57', ''),
(101, 'ifiston.ishimwe@gmail.com', '? Great News! A HomeHelp Worker Has Been Assigned — Home Help Rwanda', 'sent', '2026-10-23 07:02:42', ''),
(102, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-23 09:36:19', ''),
(103, 'teta.mpyisi@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-23 10:12:49', ''),
(104, 'teta.mpyisi@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-23 10:13:05', ''),
(105, 'teta.mpyisi@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-23 10:13:16', ''),
(106, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-25 16:04:23', ''),
(107, 'ntakiyimanajosph4@gmal.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-26 09:11:16', ''),
(108, 'bookings@homehelp.rw, homehelpservicerw@gmail.com', 'New Booking - Home Help Rwanda', 'sent', '2026-10-26 12:44:30', ''),
(109, 'scamara1552@gmail.com', 'Your HomeHelp Rwanda Request is Under Review', 'sent', '2026-10-26 13:06:39', '');

-- --------------------------------------------------------

--
-- Table structure for table `homepage_content`
--

CREATE TABLE `homepage_content` (
  `id` int(11) NOT NULL,
  `section` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `homepage_content`
--

INSERT INTO `homepage_content` (`id`, `section`, `title`, `content`, `image_url`, `sort_order`, `updated_at`) VALUES
(1, 'carousel', 'Care That Feels Like Family', 'Connect with verified, loving nannies and caregivers who treat your children like their own — right here in Rwanda.', NULL, 1, '2026-03-16 09:54:46'),
(2, 'carousel', 'A Clean Home, Every Day', 'Our trained housekeepers bring order, cleanliness, and comfort to your home — reliably and on your schedule.', NULL, 2, '2026-03-16 09:54:46'),
(3, 'carousel', 'Delicious Meals, Made at Home', 'From daily cooking to special occasions, our talented home chefs prepare fresh, delicious meals tailored to your taste.', NULL, 3, '2026-03-16 09:54:46'),
(4, 'about', 'Transforming Domestic Employment in Rwanda', 'Home Help bridges the gap between households that need help and skilled domestic workers who need work — building trust through transparency.', NULL, 1, '2026-03-16 09:54:46'),
(5, 'faq', 'How do I book a domestic service?', 'Create a free account, log in as a Client, click \"Book a Service\", fill in the service type, location, start date, and requirements. Our agent will review and notify you within hours.', NULL, 1, '2026-03-16 09:54:46'),
(6, 'faq', 'Is it free to register?', 'Yes, registration is completely free for both clients and service providers. You only pay for the domestic services you receive.', NULL, 2, '2026-03-16 09:54:46'),
(7, 'faq', 'How are service providers verified?', 'All job applicants submit their national ID, CV, and references. Our trained agents review each application and conduct manual screening before any provider is approved.', NULL, 3, '2026-03-16 09:54:46'),
(8, 'faq', 'Can I track my booking status?', 'Yes. Your dashboard shows real-time status — Pending, Under Review, Approved, or Rejected. You also receive WhatsApp messages at every stage automatically.', NULL, 4, '2026-03-16 09:54:46'),
(9, 'faq', 'What areas does Home Help serve?', 'We currently serve clients across Rwanda including Kigali, Musanze, Rubavu, Butare, and other major cities.', NULL, 5, '2026-03-16 09:54:46'),
(10, 'faq', 'How do I apply for a job?', 'Register as a Service Provider, go to Career, select a job category, fill in your application form, upload your ID and CV, and submit. An agent will review and contact you via WhatsApp.', NULL, 6, '2026-03-16 09:54:46'),
(11, 'testimonial', 'Amina Uwase — Client, Kigali', 'I found a wonderful nanny for my daughter within just 2 days. The agent was professional, responsive, and the whole process was smooth from start to finish.', NULL, 1, '2026-03-16 09:54:46'),
(12, 'testimonial', 'Jean Claude Habimana — Housekeeper, Musanze', 'After months of struggling to find work, I applied on Home Help and got placed with a great family in less than a week. I highly recommend it.', NULL, 2, '2026-03-16 09:54:46'),
(13, 'testimonial', 'Marie Iradukunda — Client, Butare', 'The WhatsApp notifications are very convenient. I got updates on every step of the process. The platform is easy to use even on a phone.', NULL, 3, '2026-03-16 09:54:46');

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `cv_file` varchar(255) DEFAULT NULL,
  `id_document` varchar(255) DEFAULT NULL,
  `status` enum('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending',
  `agent_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `district` varchar(80) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `availability` varchar(100) DEFAULT NULL,
  `expected_pay` varchar(100) DEFAULT NULL,
  `languages` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_applications`
--

INSERT INTO `job_applications` (`id`, `provider_id`, `service_id`, `agent_id`, `full_name`, `age`, `location`, `experience`, `cv_file`, `id_document`, `status`, `agent_notes`, `created_at`, `district`, `bio`, `availability`, `expected_pay`, `languages`) VALUES
(4, 86, 9, NULL, '', NULL, 'Kigali', '3-5 years', NULL, NULL, 'pending', NULL, '2026-09-07 09:05:48', 'Gasabo', 'FULL NAME: Uwimbabazi Dianee (Diane)\nPHONE: 0790391978\nEMAIL: uwimbabazidiane123@gmail.com\nDOB: 2004-09-28\nEDUCATION: Secondary\n\nABOUT: I have over 3 years of hands-on experience working in professional kitchens, specializing in authentic Middle Eastern cuisine. I have prepared a wide range of dishes including falafel, hummus, foul, baba ghanoush, shakshuka, chicken shawarma, grilled meats, moussaka, mahshi, lentils, beef liver, and fresh pita bread.\r\n\r\nMy experience includes food preparation, cooking, plating, kitchen hygiene, ingredient management, and maintaining high standards of quality and presentation. I am also experienced in preparing meals for families, private dinners, celebrations, and small events.', 'Flexible', '300000', 'English, kinyarwanda'),
(5, 94, 7, NULL, '', NULL, 'Gikondo', '1-2 years', NULL, NULL, 'pending', NULL, '2026-09-18 13:49:55', 'Kicukiro', 'FULL NAME: Cyizere delice Pascale\nPHONE: 0787274789\nEMAIL: cyizered@gmail.com\nDOB: 2001-12-04\nEDUCATION: Bachelor&#039;s\nDOCS: education_cert, national_id\n\nABOUT: I have hands-on childcare experience from caring for my younger siblings from a young age. I regularly help with bathing, feeding, preparing meals, keeping children safe and comfortable, taking them to appointments when needed, and supporting daily routines. I&#039;m responsible, patient, and caring, and I enjoy working with children. I also have professional work experience in customer service and office environments, which has strengthened my communication, organization, and sense of responsibility', 'Full-time', '', 'Kinyarwanda,English and french');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`) VALUES
(5, 'cyizabea24@ gmail.com', '102.22.140.28', '2026-06-22 08:53:30'),
(9, 'muhirejean3@gmail.com', '102.22.142.210', '2026-08-02 06:17:28'),
(10, 'mmcalebfidele@gmail.com', '102.22.136.215', '2026-08-11 00:53:54'),
(11, 'mmcalebfidele@gmail.com', '102.22.136.215', '2026-08-11 00:54:12'),
(12, 'mmcalebfidele@gmail.com', '102.22.136.215', '2026-08-11 00:56:11'),
(13, 'mmcalebfidele@gmail.com', '102.22.136.215', '2026-08-11 00:56:24'),
(14, 'mmcalebfidele@gmail.com', '102.22.136.215', '2026-08-11 00:56:46'),
(15, 'uwimbabaziphoibedorcas@gmail.com', '41.186.136.147', '2026-08-16 07:22:24'),
(16, 'uwimbabaziphoibedorcas@gmail.com', '41.186.136.147', '2026-08-16 07:22:48'),
(24, 'niyokwizerwafideline176@gmail.com', '197.157.184.154', '2026-10-07 18:59:25'),
(25, 'tewonesiteniyomunjyeri@gmail.com', '129.222.149.127', '2026-10-13 06:01:50'),
(26, 'tewonesiteniyomunjyeri@gmail.com', '129.222.149.127', '2026-10-13 06:01:56'),
(27, 'tewonesiteniyomunjyeri@gmail.com', '129.222.149.127', '2026-10-13 06:02:02'),
(28, 'tewonesiteniyomunjyeri@gmail.com', '129.222.149.127', '2026-10-13 06:02:28'),
(29, 'tewonesiteniyomunjyeri@gmail.com', '129.222.149.127', '2026-10-13 06:02:53'),
(30, 'tewonesiteniyomunjyeri@gmail.com', '129.222.149.127', '2026-10-13 06:03:19'),
(31, 'dorcasndayitegereje@gmail.com', '197.157.145.193', '2026-10-22 13:15:48'),
(32, 'teta.mpyisi@gmail.com', '41.186.135.222', '2026-10-23 06:17:56');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `message`, `is_read`, `created_at`) VALUES
(11, 40, 'new_booking', 'New booking from Muhire Dazzling for Cleaning.', 0, '2026-06-21 15:55:30'),
(14, 40, 'new_booking', 'New booking from Muhire Brilliant for Cleaning.', 0, '2026-06-21 16:31:33'),
(17, 40, 'new_application', 'New job application from beloved beloved for Cleaning.', 0, '2026-06-21 16:41:07'),
(20, 40, 'new_booking', 'New booking from brawn eric for Cleaning.', 0, '2026-06-22 08:57:16'),
(22, 40, 'new_application', 'New job application from Gicumbi DAS for Cleaning.', 0, '2026-06-22 11:20:55'),
(25, 40, 'new_booking', 'New booking from Ephrem UWINGABIRE for Cleaning + Housekeeping.', 0, '2026-07-22 06:47:43'),
(26, 48, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-07-22 06:47:43'),
(27, 40, 'new_booking', 'New booking from batamuriza winnie for Cleaning.', 0, '2026-07-23 18:59:18'),
(31, 40, 'new_booking', 'New booking from Nizeyimana Gedeon for Housekeeping.', 0, '2026-07-26 15:52:16'),
(32, 50, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-07-26 15:52:16'),
(33, 40, 'new_booking', 'New booking from Arnaud for Housekeeping + Chefs.', 0, '2026-07-26 17:27:52'),
(34, 51, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-07-26 17:27:52'),
(35, 48, 'booking_approved', 'Your booking has been approved! Our team will be in touch shortly.', 0, '2026-07-28 07:14:26'),
(36, 48, 'booking_approved', 'Your booking has been approved! Our team will be in touch shortly.', 0, '2026-07-28 07:14:26'),
(37, 40, 'new_booking', 'New booking from Ndatuje Jacque for Chefs.', 0, '2026-07-28 16:19:13'),
(40, 40, 'new_booking', 'New booking from Muserebende for Chefs.', 0, '2026-07-28 19:35:33'),
(44, 40, 'new_booking', 'New booking from Cadette for Housekeeping + Chefs.', 0, '2026-07-28 19:57:48'),
(49, 40, 'new_booking', 'New booking from NKINZINGABO Germain for Chefs.', 0, '2026-07-28 20:44:19'),
(53, 40, 'new_booking', 'New booking from MUHIRE Jean for Chefs.', 0, '2026-07-28 20:53:33'),
(57, 40, 'new_booking', 'New booking from MUHIRE Jean for Chefs.', 0, '2026-07-28 21:15:11'),
(61, 40, 'new_booking', 'New booking from Fenan Nkusi for Chefs.', 0, '2026-07-29 13:03:03'),
(62, 58, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-07-29 13:03:03'),
(63, 40, 'new_booking', 'New booking from Ndatuje Jacque for Cleaning.', 0, '2026-08-02 05:51:45'),
(65, 40, 'new_booking', 'New booking from MUHIRE Jean for Chefs.', 0, '2026-08-02 06:50:38'),
(68, 40, 'new_booking', 'New booking from MUHIRE Jean for Chefs.', 0, '2026-08-02 07:13:26'),
(71, 40, 'new_booking', 'New booking from MUHIRE Jean for Chefs.', 0, '2026-08-02 07:20:17'),
(74, 58, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-08-02 07:51:50'),
(75, 40, 'new_booking', 'New booking from MUHIRE Jean for Chefs.', 0, '2026-08-02 12:00:16'),
(78, 40, 'new_booking', 'New booking from MUHIRE Jean for Chefs.', 0, '2026-08-02 12:28:49'),
(82, 40, 'new_booking', 'New booking from MUHIRE Jean for Chefs.', 0, '2026-08-02 12:53:10'),
(83, 65, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-02 12:53:10'),
(84, 65, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-08-02 12:58:52'),
(85, 65, 'booking_completed', 'Great news! A HomeHelp worker has been assigned to your request. We will be in contact shortly.', 1, '2026-08-02 13:00:12'),
(86, 40, 'new_booking', 'New booking from Amanda Musiimire for Cleaning + Housekeeping.', 0, '2026-08-02 18:14:17'),
(87, 66, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-02 18:14:17'),
(88, 40, 'new_booking', 'New booking from Niyigena Pamella for Cleaning + Housekeeping.', 0, '2026-08-07 11:06:37'),
(89, 67, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-07 11:06:37'),
(90, 40, 'new_booking', 'New booking from Ngabire grace for Cleaning + Housekeeping + Chefs.', 0, '2026-08-08 18:30:04'),
(91, 68, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-08 18:30:04'),
(92, 40, 'new_booking', 'New booking from Usanase zilfati for Housekeeping.', 0, '2026-08-09 15:57:36'),
(93, 69, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-09 15:57:36'),
(94, 69, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-08-09 16:52:41'),
(95, 68, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-08-09 16:58:21'),
(96, 67, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-08-09 16:59:55'),
(97, 40, 'new_booking', 'New booking from Keza ornella for Cleaning + Housekeeping + Chefs.', 0, '2026-08-13 15:34:25'),
(98, 70, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-13 15:34:25'),
(99, 40, 'new_booking', 'New booking from Ntawumvayino Laurence for Housekeeping.', 0, '2026-08-19 10:57:22'),
(100, 72, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-19 10:57:22'),
(101, 40, 'new_booking', 'New booking from Uwineza Aloysia for Housekeeping.', 0, '2026-08-19 19:38:34'),
(102, 73, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-19 19:38:34'),
(103, 40, 'new_booking', 'New booking from Nizeyimana Gedeon for Housekeeping.', 0, '2026-08-20 15:40:33'),
(104, 50, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-20 15:40:33'),
(105, 40, 'new_booking', 'New booking from Djennia Ines Shabani for Cleaning + Housekeeping.', 0, '2026-08-22 08:14:19'),
(106, 74, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-22 08:14:19'),
(107, 40, 'new_booking', 'New booking from Djennia Ines Shabani for Chefs.', 0, '2026-08-22 08:18:45'),
(108, 74, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-22 08:18:45'),
(109, 40, 'new_booking', 'New booking from Almond Kiruthu Murimi for Cleaning + Housekeeping.', 0, '2026-08-23 20:43:23'),
(110, 75, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-23 20:43:23'),
(111, 40, 'new_booking', 'New booking from Keng-Nshom Precious Te-eh Kefeyin for Cleaning + Housekeeping.', 0, '2026-08-24 09:08:32'),
(112, 76, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-24 09:08:32'),
(113, 40, 'new_booking', 'New booking from DUSHIMIMANA emmanuel for Housekeeping.', 0, '2026-08-24 11:27:22'),
(114, 77, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-24 11:27:22'),
(115, 40, 'new_booking', 'New booking from Nadia URWIBUTSO for Housekeeping.', 0, '2026-08-24 15:31:37'),
(116, 78, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-24 15:31:37'),
(117, 40, 'new_booking', 'New booking from Cynthia Ingabire for Housekeeping.', 0, '2026-08-26 21:48:14'),
(118, 79, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-08-26 21:48:14'),
(119, 40, 'new_booking', 'New booking from Susan Kamwendo for Housekeeping.', 0, '2026-09-02 16:00:05'),
(120, 82, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-02 16:00:05'),
(121, 40, 'new_booking', 'New booking from Erneste Niyonsaba for Chefs.', 0, '2026-09-04 09:57:36'),
(122, 83, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-04 09:57:36'),
(123, 40, 'new_booking', 'New booking from NIYONIZIGIYE Melance for Cleaning + Housekeeping.', 0, '2026-09-04 12:16:11'),
(124, 84, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-04 12:16:11'),
(125, 40, 'new_booking', 'New booking from Sten LIKIBI for Cleaning + Housekeeping + Chefs.', 0, '2026-09-06 08:49:01'),
(126, 85, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-06 08:49:01'),
(127, 40, 'new_application', 'New job application from Uwimbabazi Dianee (Diane) for Chefs.', 0, '2026-09-07 09:05:48'),
(128, 86, 'application_submitted', 'Your application has been submitted! Our team will review it and notify you by email.', 0, '2026-09-07 09:05:48'),
(129, 40, 'new_booking', 'New booking from MAHIRWE Norbert for Cleaning.', 0, '2026-09-09 15:47:47'),
(130, 87, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-09 15:47:47'),
(131, 40, 'new_booking', 'New booking from MAHIRWE Norbert for Cleaning.', 0, '2026-09-09 15:48:36'),
(132, 87, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-09 15:48:36'),
(133, 40, 'new_booking', 'New booking from MAHIRWE Norbert for Housekeeping.', 0, '2026-09-09 15:49:23'),
(134, 87, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-09 15:49:23'),
(135, 40, 'new_booking', 'New booking from Mojeed for Housekeeping.', 0, '2026-09-10 19:30:44'),
(136, 88, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-10 19:30:44'),
(137, 40, 'new_booking', 'New booking from Isabelle Nkurunziza-Manirakiza for Cleaning.', 0, '2026-09-15 07:18:19'),
(138, 89, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-15 07:18:19'),
(139, 40, 'new_booking', 'New booking from Peace Kanyana for Cleaning + Chefs.', 0, '2026-09-16 14:35:49'),
(140, 90, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-16 14:35:49'),
(141, 40, 'new_booking', 'New booking from Mutimawurugo Nuriati for Housekeeping.', 0, '2026-09-17 10:39:05'),
(142, 91, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-17 10:39:05'),
(143, 40, 'new_booking', 'New booking from Teresia Mwaniki for Chefs.', 0, '2026-09-17 19:05:21'),
(144, 92, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-17 19:05:21'),
(145, 40, 'new_application', 'New job application from Cyizere delice Pascale for Housekeeping.', 0, '2026-09-18 13:49:55'),
(146, 94, 'application_submitted', 'Your application has been submitted! Our team will review it and notify you by email.', 0, '2026-09-18 13:49:55'),
(147, 40, 'new_booking', 'New booking from Elia ngendahimana for Housekeeping.', 0, '2026-09-19 16:56:25'),
(148, 96, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-19 16:56:25'),
(149, 40, 'new_booking', 'New booking from xizhe for Cleaning.', 0, '2026-09-22 14:00:51'),
(150, 97, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-22 14:00:51'),
(151, 40, 'new_booking', 'New booking from Josephine Niyondagije for Housekeeping.', 0, '2026-09-27 17:13:18'),
(152, 98, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-27 17:13:18'),
(153, 40, 'new_booking', 'New booking from Josephine Niyondagije for Cleaning.', 0, '2026-09-27 17:18:04'),
(154, 98, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-27 17:18:04'),
(155, 40, 'new_booking', 'New booking from Acheton for Cleaning + Housekeeping.', 0, '2026-09-28 07:21:08'),
(156, 99, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-28 07:21:08'),
(157, 40, 'new_booking', 'New booking from Mukashema mary for Cleaning.', 0, '2026-09-28 23:19:21'),
(158, 100, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-28 23:19:21'),
(159, 40, 'new_booking', 'New booking from Mukashema mary for Housekeeping.', 0, '2026-09-28 23:20:43'),
(160, 100, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-09-28 23:20:43'),
(161, 100, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-10-01 19:36:39'),
(162, 100, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-10-01 19:37:45'),
(163, 98, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-02 08:40:40'),
(164, 51, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-02 08:42:13'),
(165, 87, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-02 08:43:18'),
(166, 97, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-02 17:38:12'),
(167, 90, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-10-02 17:38:37'),
(168, 88, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-02 17:39:00'),
(169, 40, 'new_booking', 'New booking from Admin for Chefs.', 0, '2026-10-02 17:42:42'),
(170, 40, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 0, '2026-10-02 17:42:42'),
(171, 40, 'new_booking', 'New booking from Admin for Cleaning + Housekeeping + Chefs.', 0, '2026-10-02 17:50:09'),
(172, 40, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 0, '2026-10-02 17:50:09'),
(173, 40, 'new_booking', 'New booking from jules bobo for Chefs.', 0, '2026-10-02 17:53:02'),
(174, 101, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-02 17:53:02'),
(175, 40, 'new_booking', 'New booking from Fiston ISHIMWE for Cleaning + Housekeeping.', 0, '2026-10-02 20:10:41'),
(176, 102, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-02 20:10:41'),
(177, 40, 'new_booking', 'New booking from Moneera for Cleaning + Housekeeping + Chefs.', 0, '2026-10-05 13:47:09'),
(178, 103, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-05 13:47:09'),
(179, 103, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-05 14:34:06'),
(180, 101, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-06 13:24:10'),
(181, 102, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-06 13:29:56'),
(182, 40, 'new_booking', 'New booking from Solennel GISUBIZO NGABO for Cleaning + Housekeeping + Chefs.', 0, '2026-10-06 16:03:07'),
(183, 104, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-06 16:03:07'),
(184, 104, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-06 20:55:31'),
(185, 104, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-06 21:18:44'),
(186, 104, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-06 21:19:00'),
(187, 40, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-06 21:19:31'),
(188, 40, 'booking_completed', 'Great news! A HomeHelp worker has been assigned to your request. We will be in contact shortly.', 0, '2026-10-06 21:20:30'),
(189, 40, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-06 21:20:37'),
(190, 40, 'booking_completed', 'Great news! A HomeHelp worker has been assigned to your request. We will be in contact shortly.', 0, '2026-10-06 21:20:49'),
(191, 40, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-06 21:21:03'),
(192, 40, 'booking_completed', 'Great news! A HomeHelp worker has been assigned to your request. We will be in contact shortly.', 0, '2026-10-06 21:21:20'),
(193, 40, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-06 21:21:29'),
(194, 40, 'booking_completed', 'Great news! A HomeHelp worker has been assigned to your request. We will be in contact shortly.', 0, '2026-10-06 21:21:47'),
(195, 40, 'new_booking', 'New booking from Niyongabo Jean Marie for Cleaning.', 0, '2026-10-08 19:13:49'),
(196, 106, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-08 19:13:49'),
(197, 106, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-10-09 08:48:46'),
(198, 40, 'new_booking', 'New booking from Donald Mavunduse for Chefs.', 0, '2026-10-09 19:27:15'),
(199, 107, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-09 19:27:15'),
(200, 107, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-10 09:36:58'),
(201, 102, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-12 14:47:21'),
(202, 92, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-12 15:18:25'),
(203, 40, 'new_booking', 'New booking from Mugisha Ethnah for Chefs.', 0, '2026-10-12 15:26:04'),
(204, 108, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-12 15:26:04'),
(205, 108, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-12 15:28:24'),
(206, 40, 'new_booking', 'New booking from Niyomunjyeri Tewonesite for Cleaning.', 0, '2026-10-13 05:54:12'),
(207, 109, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-13 05:54:12'),
(208, 109, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-13 10:47:24'),
(209, 40, 'new_booking', 'New booking from Iradukunda Eric for Housekeeping.', 0, '2026-10-14 08:27:46'),
(210, 111, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-14 08:27:46'),
(211, 40, 'new_booking', 'New booking from Iradukunda Eric for Cleaning.', 0, '2026-10-14 08:33:43'),
(212, 111, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-14 08:33:43'),
(213, 111, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-10-14 12:12:14'),
(214, 111, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-10-14 12:17:04'),
(215, 40, 'new_booking', 'New booking from uwineza providence for Housekeeping.', 0, '2026-10-16 14:57:56'),
(216, 112, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-16 14:57:56'),
(217, 40, 'new_booking', 'New booking from Ishimwe Sugira Aurore for Cleaning + Housekeeping + Chefs.', 0, '2026-10-16 15:54:20'),
(218, 113, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-16 15:54:20'),
(219, 40, 'new_booking', 'New booking from Samia for Housekeeping.', 0, '2026-10-17 09:52:36'),
(220, 114, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-17 09:52:36'),
(221, 40, 'new_booking', 'New booking from TETA ange for Housekeeping.', 0, '2026-10-18 16:37:22'),
(222, 115, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-18 16:37:22'),
(223, 112, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-10-19 08:21:31'),
(224, 113, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-19 08:22:40'),
(225, 113, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-19 08:23:50'),
(226, 114, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-10-19 08:24:37'),
(227, 115, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-19 08:24:58'),
(228, 113, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-20 13:16:25'),
(229, 40, 'new_booking', 'New booking from Adia Umulisa for Chefs.', 0, '2026-10-20 16:40:46'),
(230, 116, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-20 16:40:46'),
(231, 116, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-20 16:49:27'),
(232, 40, 'new_booking', 'New booking from Isaac Ndiyunguye for Chefs.', 0, '2026-10-21 19:07:26'),
(233, 117, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-21 19:07:26'),
(234, 117, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 1, '2026-10-22 08:06:55'),
(235, 40, 'new_booking', 'New booking from NDAYITEGEREJE Dorcas for Cleaning.', 0, '2026-10-22 11:53:10'),
(236, 118, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-22 11:53:10'),
(237, 118, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-22 20:51:50'),
(238, 40, 'new_booking', 'New booking from Ngendahimana manasseh for Cleaning.', 0, '2026-10-22 21:28:33'),
(239, 119, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-22 21:28:33'),
(240, 119, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-23 06:56:55'),
(241, 102, 'booking_completed', 'Great news! A HomeHelp worker has been assigned to your request. We will be in contact shortly.', 0, '2026-10-23 07:02:41'),
(242, 40, 'new_booking', 'New booking from Teta Mpyisi for Cleaning + Housekeeping + Chefs.', 0, '2026-10-23 09:36:18'),
(243, 120, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-23 09:36:18'),
(244, 120, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-23 10:12:48'),
(245, 120, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-23 10:13:04'),
(246, 120, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-23 10:13:15'),
(247, 40, 'new_booking', 'New booking from NTAKIYIMANA Joseph for Housekeeping.', 0, '2026-10-25 16:04:21'),
(248, 121, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-25 16:04:21'),
(249, 121, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-26 09:11:14'),
(250, 40, 'new_booking', 'New booking from Serene Camara for Housekeeping.', 0, '2026-10-26 12:44:29'),
(251, 122, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.', 1, '2026-10-26 12:44:29'),
(252, 122, 'booking_review', 'Your booking is under review. Our team will contact you shortly.', 0, '2026-10-26 13:06:38');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `used`, `created_at`) VALUES
(1, 'tewonesiteniyomunjyeri@gmail.com', '0e93d7d55f95adae8ad7cfc61b959f19d6c8417261d219aa8f7e1b52dbe38203', '2026-10-13 09:03:39', 0, '2026-10-13 06:03:39');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `icon`, `sort_order`, `status`, `created_at`) VALUES
(6, 'Cleaning', 'Professional home and office cleaning, laundry, organizing, and general housekeeping services.', '🧹', 1, 'active', '2026-03-16 14:21:48'),
(7, 'Housekeeping', 'Trusted nannies and babysitters providing safe, loving care for your children at home.', '👶', 2, 'active', '2026-03-16 14:22:21'),
(9, 'Chefs', 'Skilled professional chefs for special occasions, dinner parties, and premium in-home dining.', '👨‍🍳', 4, 'active', '2026-03-16 14:24:00');

-- --------------------------------------------------------

--
-- Table structure for table `service_fees`
--

CREATE TABLE `service_fees` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `fee_key` varchar(100) NOT NULL,
  `fee_label` varchar(200) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `service_fees`
--

INSERT INTO `service_fees` (`id`, `service_id`, `fee_key`, `fee_label`, `amount`, `created_at`) VALUES
(21, 6, 'base', 'Base cleaning fee', 15000.00, '2026-06-15 15:05:41'),
(22, 6, 'per_room', 'Per room fee', 3000.00, '2026-06-15 15:05:41'),
(23, 6, 'outdoor', 'Outdoor areas fee', 2000.00, '2026-06-15 15:05:41'),
(24, 6, 'pool', 'Pool cleaning fee', 5000.00, '2026-06-15 15:05:41'),
(25, 6, 'car', 'Car cleaning fee', 3000.00, '2026-06-15 15:05:41'),
(26, 6, 'dishes', 'Dish washing fee', 2000.00, '2026-06-15 15:05:41'),
(27, 6, 'pets', 'Pets fee', 2000.00, '2026-06-15 15:05:41'),
(28, 7, 'base', 'Base housekeeping fee', 18000.00, '2026-06-15 15:05:41'),
(29, 7, 'per_room', 'Per room fee', 2500.00, '2026-06-15 15:05:41'),
(30, 7, 'outdoor', 'Outdoor areas fee', 2000.00, '2026-06-15 15:05:41'),
(31, 7, 'pool', 'Pool fee', 5000.00, '2026-06-15 15:05:41'),
(32, 7, 'car', 'Car cleaning fee', 3000.00, '2026-06-15 15:05:41'),
(33, 7, 'dishes', 'Dish washing fee', 2000.00, '2026-06-15 15:05:41'),
(34, 7, 'pets', 'Pets fee', 2000.00, '2026-06-15 15:05:41'),
(35, 7, 'laundry', 'Laundry & ironing fee', 3000.00, '2026-06-15 15:05:41'),
(36, 9, 'event_base', 'Chef for events base fee', 50000.00, '2026-06-15 15:05:41'),
(37, 9, 'per_person', 'Per person fee (events)', 5000.00, '2026-06-15 15:05:41'),
(38, 9, 'menu', 'Menu planning fee', 10000.00, '2026-06-15 15:05:41'),
(39, 9, 'home_base', 'Chef for home base fee', 30000.00, '2026-06-15 15:05:41'),
(40, 9, 'home_per_person', 'Per person fee (home)', 3000.00, '2026-06-15 15:05:41');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('client','provider','agent','admin') NOT NULL DEFAULT 'client',
  `google_id` varchar(100) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `status` enum('active','blocked') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `google_id`, `profile_photo`, `status`, `created_at`, `photo`) VALUES
(40, 'Admin', 'admin@homehelp.rw', '+250789239908', '$2y$10$8AVP.6aY2RpcnlOSwUGJLOMmEKQn0urNHfhKugOhHAJTuCARhQH2q', 'admin', NULL, NULL, 'active', '2026-06-16 10:59:43', NULL),
(48, 'Ephrem UWINGABIRE', 'uwingabire87@gmail.com', '+250783169702', '$2y$10$202PwEhMPryXVvX3wAHMvuvBM3baKHHkE7LfWCfXn1ya49G1Sh0cy', 'client', NULL, NULL, 'active', '2026-07-22 06:47:43', NULL),
(50, 'Nizeyimana Gedeon', 'nizeyimanagedeon094@gmail.com', '+250735884985', '$2y$10$4SagD5FrLgnjmVpVQKRRau99rVGAWs9ja.Pei4EjXGeNprnx93Uhm', 'client', NULL, NULL, 'active', '2026-07-26 15:52:16', NULL),
(51, 'Arnaud', 'niidkoff@gmail.com', '+250782057233', '$2y$10$drXvSUfoMVklkh3mg/KahOyh5xRy5Nm2zJ8bAuadU3JueQvl/Ulqm', 'client', NULL, NULL, 'active', '2026-07-26 17:27:52', NULL),
(58, 'Fenan Nkusi', 'fenan.nkusi@yahoo.com', '250795464618', '$2y$10$ixjFSC.lYQ3KxMZjfA029e5NmKaBcN6gNhQ55tNapbiE/N4e0B6oy', 'client', NULL, NULL, 'active', '2026-07-29 13:03:03', NULL),
(65, 'MUHIRE Jean', 'muhirejean3@gmail.com', '0789239908', '$2y$10$rciCKPrn2M3HS6cdPOTPoeada6U3PLbAMxxa2RTgArFkru5JUoexe', 'client', NULL, NULL, 'active', '2026-08-02 12:53:10', NULL),
(66, 'Amanda Musiimire', 'amusiimire@gmail.com', '250781446742', '$2y$10$S6EVNw71i0RR4EPLv7Yv4.KwZqhGH84.pnIE1fj4vSx1N/CuvNfju', 'client', NULL, NULL, 'active', '2026-08-02 18:14:17', NULL),
(67, 'Niyigena Pamella', 'niyigenapamella@gmail.com', '+250789242861', '$2y$10$yplrCL2w40s8778dh4cRCeoxZm8ZE0AneDxVMHy7LOGhwHnpmEUGm', 'client', NULL, NULL, 'active', '2026-08-07 11:06:37', NULL),
(68, 'Ngabire grace', 'kiraboayra63@gmail.com', '0791393886', '$2y$10$QiYbcod7ODbkClvYk5dx8u1AUKmFBF1HFDJ5keKPC.W2UR7rBlYfC', 'client', NULL, NULL, 'active', '2026-08-08 18:30:04', NULL),
(69, 'Usanase zilfati', 'zilfatiusanase@gmail.com', '0798643002', '$2y$10$onOsmkECt0ex/mLFHIcLtuiIbvEi42DD9u2PQ9AETAU7G13b36MNy', 'client', NULL, NULL, 'active', '2026-08-09 15:57:36', NULL),
(70, 'Keza ornella', 'keza7874@gmail.com', '0793243593', '$2y$10$WGP04Yk8R/tXgyMXS4Y6pe3Yd.faARKgI3efML7oPqGC.AVdTZtr.', 'client', NULL, NULL, 'active', '2026-08-13 15:34:25', NULL),
(71, 'MUREKATETE Collete', 'uwimbabaziphoibedorcas@gmail.com', '0785010073', '$2y$10$y6iUEtrPSjIXy0Z103EWFeiVloPHJAzI.bWEa7hDrBCVIrkqG78/e', 'provider', NULL, NULL, 'active', '2026-08-16 07:24:20', NULL),
(72, 'Ntawumvayino Laurence', 'ulaurence2000@gmail.com', '0787715165', '$2y$10$c/vD48FigVJ8mPG6N.lFXe1myA1fOn3WR035Cr0v/NYSnjlH6ZL1G', 'client', NULL, NULL, 'active', '2026-08-19 10:57:22', NULL),
(73, 'Uwineza Aloysia', 'uwinezaaloysia91@gmail.com', '0786054037', '$2y$10$Sh8axSx7UcQ9s967XjQaqeu6G5u99UDSl1XKAp5DAF9C6cyLLFGKy', 'client', NULL, NULL, 'active', '2026-08-19 19:38:34', NULL),
(74, 'Djennia Ines Shabani', 'sdjennia@gmail.com', '+250796156488', '$2y$10$2SW3Xiz8ZMnsMv.JK70Xk.JrD8.hxXIsC3JQWN4vxOy3HcHCoVB32', 'client', NULL, NULL, 'active', '2026-08-22 08:14:19', NULL),
(75, 'Almond Kiruthu Murimi', 'almondkiruthu@gmail.com', '+250796699041', '$2y$10$YuOeUJlmcq4CpdoLm4fJ/.NzhdUY7QuPeAVBOFnBsqKXV/EsuFxC.', 'client', NULL, NULL, 'active', '2026-08-23 20:43:23', NULL),
(76, 'Keng-Nshom Precious Te-eh Kefeyin', 'kefeyinnshomprecious@gmail.com', '+250795308721', '$2y$10$pO0hxK6T3E.NcflvIAfegOJnZRSRKQcSb0pkM1YXgvAIbN3RtPFdG', 'client', NULL, NULL, 'active', '2026-08-24 09:08:32', NULL),
(77, 'DUSHIMIMANA emmanuel', 'dushimimana919@gmail.com', '0735851132/079203962', '$2y$10$ugCmBaZSvCZUY5gf38v.WujKGkRB3wqzcV1lmcpSz9oaNVZW6pH7u', 'client', NULL, NULL, 'active', '2026-08-24 11:27:22', NULL),
(78, 'Nadia URWIBUTSO', 'urwibutsonadia@gmail.com', '0781992352', '$2y$10$DQw7zd8uBcXYfTNaYyrQJe.KyWAbdY7fDrpCn0U/BeqJ2xl6s7MMq', 'client', NULL, NULL, 'active', '2026-08-24 15:31:37', NULL),
(79, 'Cynthia Ingabire', 'cynthiaingabire07@gmail.com', '0787061606', '$2y$10$LaEzs/SW9QeZABxRehcYkehlcvGyMt3vzh.BwDsBLSn02vvndTSAi', 'client', NULL, NULL, 'active', '2026-08-26 21:48:14', NULL),
(80, 'Sabine Mukazi Rwibutso', 'rwibutsosabine@gmail.com', '+250791042776', '$2y$10$wouP9.I7RqAHu.oF5A6jXOTsvqmhu.6PsbNbAT6LJl1ntEYIX5djK', 'provider', NULL, NULL, 'active', '2026-08-29 17:59:13', NULL),
(81, 'Umutoniwase Aline', 'ualine116@gmail.com', '', '$2y$10$GyY09mv/3pryf4Nbi/JxmuTL9uItSoyZ0q8T1OU2hR93d7NiPDoRu', 'provider', NULL, NULL, 'active', '2026-09-02 13:38:57', NULL),
(82, 'Susan Kamwendo', 'susankamwendo7@gmail.com', '+265882048999', '$2y$10$/Ef2Nq9YbYIxVHTomLtYheaMVh5DMQ5AjKgnjiPqW68UMCgZ8fh96', 'client', NULL, NULL, 'active', '2026-09-02 16:00:05', NULL),
(83, 'Erneste Niyonsaba', 'ernesteniyonsaba9@gmail.com', '0785128924', '$2y$10$GgJqMPgU3aQt.ZZGWguD8Oad/1KHQWQlPaRQPvYb0A69SCKtYPMuG', 'client', NULL, NULL, 'active', '2026-09-04 09:57:36', NULL),
(84, 'NIYONIZIGIYE Melance', 'melaniyonizeye@gmail.com', '0786908562', '$2y$10$5HFcTH2i1BvQwxBUQnaKp.Pf0Py1JMJiZtjxdk1XFKqXjYnfPWNoK', 'client', NULL, NULL, 'active', '2026-09-04 12:16:11', NULL),
(85, 'Sten LIKIBI', 'stenlikibi@gmail.com', '+242064028778', '$2y$10$XftwgwlrYUGVWRK7Rh/zQuzUz7NYR5sMChmBpi.FJIQdJYWNvL9Ae', 'client', NULL, NULL, 'active', '2026-09-06 08:49:01', NULL),
(86, 'Uwimbabazi Dianee (Diane)', 'uwimbabazidiane123@gmail.com', '', '$2y$10$Mx/4AoS4m2Ct6OKvvyKRHObXqfhAyBQZGlQn/qs9vgEMboVkprF/m', 'provider', NULL, NULL, 'active', '2026-09-07 08:59:53', NULL),
(87, 'MAHIRWE Norbert', 'mahirwenorbert@gmail.com', '+250786457009', '$2y$10$bUr6LSkC/n08J6m/mk5RCeJ2hfv1ip0Y8eKGo5KBC5MbTbHakd9Pq', 'client', NULL, NULL, 'active', '2026-09-09 15:47:47', 'user_87_1788969123.jpg'),
(88, 'Mojeed', 'mojeedkhairat@gmail.com', '+250792557447', '$2y$10$CmeEmlaKAn9uh4Gsjq/d7urMjBH0MhOzgiJZc8FUUOWw0hdaZTwXu', 'client', NULL, NULL, 'active', '2026-09-10 19:30:44', NULL),
(89, 'Isabelle Nkurunziza-Manirakiza', 'nkiza@hotmail.com', '0795302624', '$2y$10$Xc9dQ9OTnnE3kH3T1SraZ.jCNHTarrVjiNu9YgMrzepG82Rx4CRVy', 'client', NULL, NULL, 'active', '2026-09-15 07:18:19', NULL),
(90, 'Peace Kanyana', 'kanyanapeace2020@gmail.com', '+250798437532', '$2y$10$QZb10Du5QhXbCHqD8St6muu0nORapq5G6Bhb4mpNmEsbnSVvP.Eii', 'client', NULL, NULL, 'active', '2026-09-16 14:35:49', NULL),
(91, 'Mutimawurugo Nuriati', 'mutimawurugon@gmail.com', '+250 794677004', '$2y$10$6Lw4ZEYKEs6MXZcDPW1KwOiKZGdMKDc8hWVdtfZhZ.I/nIjgF3Ooe', 'client', NULL, NULL, 'active', '2026-09-17 10:39:05', NULL),
(92, 'Teresia Mwaniki', 'terrymumbi@gmail.com', '+250793900719', '$2y$10$Xbpjw7AAUBW9rer40Vz0U.ANgaUk2t4XQ72g7ZrlMukiWbB/m41aO', 'client', NULL, NULL, 'active', '2026-09-17 19:05:21', NULL),
(93, 'OMAH LAY', 'olay29957@gmail.com', '', '$2y$10$4JoxHUkyH0HIu5.Wh.BNn.FRMas33LsLyVeHs31YHcfsXe.wUFQXK', 'provider', NULL, NULL, 'active', '2026-09-18 00:07:04', NULL),
(94, 'Delice', 'cyizered@gmail.com', '', '$2y$10$FN6HV5BfWAFOXSvph606KecFDKdFOkzUeTjlGx66fc9JfC5b8jTVi', 'provider', NULL, NULL, 'active', '2026-09-18 13:23:50', NULL),
(95, 'Umutesi Irene', 'Umutesii300@gmail.com', '+250789967021', '$2y$10$M6T63KHfohS.tFWo4FbRN.zQDHje.WdPJey1MhhpXrAOV/e1WONSq', 'provider', NULL, NULL, 'active', '2026-09-19 11:24:33', NULL),
(96, 'Elia ngendahimana', 'eliakimtusosonnmanzi@gmail.com', '07936589719', '$2y$10$XmfPSRhzGysIxGgQpaLUR.522Z8AUZWXO13Bq21TVIwOJwYbFmgYW', 'client', NULL, NULL, 'active', '2026-09-19 16:56:25', NULL),
(97, 'xizhe', '17629814816@163.com', '0799650686', '$2y$10$N4kCbZdqUK1v/oKY2L2UD.sbdpkPaiRqajOKGdP7gPNbPzXwhpLnm', 'client', NULL, NULL, 'active', '2026-09-22 14:00:51', NULL),
(98, 'Josephine Niyondagije', 'niyondagijejose@3gmail.com', '+250782159657', '$2y$10$ro.tmXDFxrPNmvP1E0sBfehuj17Ys2EWzReqU1Vx/KCHSYUw.X/Ja', 'client', NULL, NULL, 'active', '2026-09-27 17:13:18', NULL),
(99, 'Acheton', 'pazzoacheton1@gmail.com', '0785112227', '$2y$10$KkdQmiwI2N2w1IeRrcDGie/dE32VDJEp/B97jNdKXhwkaNErikfSm', 'client', NULL, NULL, 'active', '2026-09-28 07:21:08', NULL),
(100, 'Mukashema mary', 'noedavis0104@gmail.com', '0793024209', '$2y$10$.7IxdtErDDdh7xwTzaXl6eaL2Ux4dZZvEE1UstYJIj0Bh0ClEVxSi', 'client', NULL, NULL, 'active', '2026-09-28 23:19:21', NULL),
(101, 'jules bobo', 'julesbo4@gmail.com', '0788453180', '$2y$10$08Bvm3sLyrQSPzQ.dDLeZ.AxRe17mpPQZh7wET6ftyP6.YhEIuWlW', 'client', NULL, NULL, 'active', '2026-10-02 17:53:02', NULL),
(102, 'Fiston ISHIMWE', 'ifiston.ishimwe@gmail.com', '0788336050', '$2y$10$gC06p0g/5Kiipt6X46O11eXR4RxNh/tNW484u7K.TzYU9s0vSekQ2', 'client', NULL, NULL, 'active', '2026-10-02 20:10:41', NULL),
(103, 'Moneera', 'moneerayas@gmail.com', '0799902437', '$2y$10$jAvSqLvElNAxM/BcBnPeDeCfvDa8ZVng4aNXQjlvcFWkj24w9GnjS', 'client', NULL, NULL, 'active', '2026-10-05 13:47:09', NULL),
(104, 'Solennel GISUBIZO NGABO', 'gisubizongabosolennel@gmail.com', '+250786326711', '$2y$10$eGjlag6sTH5vK5kgn3cxKe3HYI64k/DWQgdm3f.MyGt8EmU54uUFO', 'client', NULL, NULL, 'active', '2026-10-06 16:03:07', NULL),
(105, 'Niyokwizerwa Fideline', 'niyokwizerwafideline176@gmail.com', '+250781092728', '$2y$10$nkS1jB106DaPD14SysGUf.1g0neRYLxHTrIVGPUEHwAKwsQHk7yza', 'provider', NULL, NULL, 'active', '2026-10-07 19:02:14', NULL),
(106, 'Niyongabo Jean Marie', 'jeanmarieemper@gmail.com', '+250791841255', '$2y$10$k9OLP6XZW9Oo7luN9jmefOeS9cFRMnxiEsNg6pq/BLG.TyDLcF2IO', 'client', NULL, NULL, 'active', '2026-10-08 19:13:49', NULL),
(107, 'Donald Mavunduse', 'mavundused@gmail.com', '0789582362', '$2y$10$vlakJ0aBzKJxW.45pWkLS.hWEsfwUubTdnfvTJcb5uOhIUbQQsB2m', 'client', NULL, NULL, 'active', '2026-10-09 19:27:15', NULL),
(108, 'Mugisha Ethnah', 'queenethnah@gmail.com', '+250787006271', '$2y$10$y5hpRFp.vHDv0Z59Zd2VDeOfJ0o5nM3iiqJN33sTIN3jMU0fRKCJO', 'client', NULL, NULL, 'active', '2026-10-12 15:26:04', NULL),
(109, 'Niyomunjyeri Tewonesite', 'tewonesiteniyomunjyeri@gmail.com', '0799724967', '$2y$10$wwyakfEfHfXTtm43tetYPOyt8z5XqObEZsqqIsFgjumbMNdSc7oP6', 'client', NULL, NULL, 'active', '2026-10-13 05:54:12', NULL),
(110, 'Confiance Ira', 'confianceira77@gmail.com', '', '$2y$10$qrdx3hGu7G7LRqkUvkFVnOkFZ.GlD4cbycOnMlz7fvmb01OvZk9qm', 'provider', NULL, NULL, 'active', '2026-10-13 11:19:40', NULL),
(111, 'Iradukunda Eric', 'waki35435@gmail.com', '0739113438', '$2y$10$Z4Ia5IXSp8GYz0WyxYrFWel.kPeJwbtofbzGexVWaes0Uln4QFBC6', 'client', NULL, NULL, 'active', '2026-10-14 08:27:46', 'user_111_1791966535.jpg'),
(112, 'uwineza providence', 'puwineza889@gmail.com', '+250783997794', '$2y$10$mRH32uoR93LTiez09kOhveElpmE3zAmiUKQyMQg8vfN0kadWye1gK', 'client', NULL, NULL, 'active', '2026-10-16 14:57:56', NULL),
(113, 'Ishimwe Sugira Aurore', 'ishaura08@gmail.com', '+393519446380', '$2y$10$oc0P6hIft6BQ6QwUHEqaOutjLW.WN.UjL4YU8Q2yH192OOB6L0jbi', 'client', NULL, NULL, 'active', '2026-10-16 15:54:20', NULL),
(114, 'Samia', 'ingabireisamia@icloud.com', '0789497093', '$2y$10$kizSXMjAQH8gUAhD/WwkveDsZebhlDIrAhcntmdE8yI74BXs6DIvO', 'client', NULL, NULL, 'active', '2026-10-17 09:52:36', NULL),
(115, 'TETA ange', 'tetaange42@gmail.com', '0798755269', '$2y$10$LChNGIoFOExZs82cTDg7X.Xv0oaBYsG3589DPbZH8ZlcvYjRcYyDa', 'client', NULL, NULL, 'active', '2026-10-18 16:37:22', NULL),
(116, 'Adia Umulisa', 'umulisaadi@gmail.com', '+250789448342', '$2y$10$Jfr96B8Ebl2t6rXpEPtHW.jqUgYubMcOCwaExSeTWq2ofgioSceQ.', 'client', NULL, NULL, 'active', '2026-10-20 16:40:46', NULL),
(117, 'Isaac Ndiyunguye', 'isaacsteven123@gmail.com', '7132838268', '$2y$10$rM59ufgSRx28Y54WGh0d8uJJg9Hl5Q5kA47ZJ7khiJZRr5aYmhOc6', 'client', NULL, NULL, 'active', '2026-10-21 19:07:26', NULL),
(118, 'NDAYITEGEREJE Dorcas', 'dorcasndayitegereje@gmail.com', '+250780732527', '$2y$10$qomDYjBFwmVNP55D8PRu6uiGMpNFwk/H7Cf5Tik3cZRVZ19Sp4LWW', 'client', NULL, NULL, 'active', '2026-10-22 11:53:10', NULL),
(119, 'Ngendahimana manasseh', 'manassendahimana@gmail.com', '0780505301', '$2y$10$0x.NF3j9DVIaujkHlDZksedT/.WfpqAkGPYQpDQ.sMazwDJKv9Ek6', 'client', NULL, NULL, 'active', '2026-10-22 21:28:33', NULL),
(120, 'Teta Mpyisi', 'teta.mpyisi@gmail.com', '+250788312537', '$2y$10$5baxFfymSJZwf/Y5OBPPzeg2BdUwWhBl.ijDxGoj3m6sy8NEnEJHK', 'client', NULL, NULL, 'active', '2026-10-23 09:36:18', NULL),
(121, 'NTAKIYIMANA Joseph', 'ntakiyimanajosph4@gmal.com', '+250783024434', '$2y$10$c7zqT1TR9B6VpDxtX.JMl.8G4ibm42mNsPVtR/VOsD7.drmu7jPd2', 'client', NULL, NULL, 'active', '2026-10-25 16:04:21', NULL),
(122, 'Serene Camara', 'scamara1552@gmail.com', '0787297402', '$2y$10$uKaGBk4VjprKGhMh6ketf.Jn38DJsQFPtfJGEQr7.cOFxLzV.yfIO', 'client', NULL, NULL, 'active', '2026-10-26 12:44:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_log`
--

CREATE TABLE `whatsapp_log` (
  `id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('sent','failed') DEFAULT 'sent',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `whatsapp_log`
--

INSERT INTO `whatsapp_log` (`id`, `phone`, `message`, `status`, `created_at`) VALUES
(1, '+250789239908', '? *NEW BOOKING — Home Help*\n\n? *Client:* MUHIRE Jean\n? *Phone:* 0789239908\n?? *Email:* muhirejean3@gmail.com\n? *Services:* Cleaning\n? *Neighbourhood:* Mbugangari\n? *Frequency:* 3 days/week\n?? *Languages:* English,Swahili,Kinyarwanda\n? *Rooms:* 5\n? *Accommodation:* Live Out (Within Kigali)\n? *Est. Monthly Fee:* 34,000 RWF\n\n? Please review on the admin dashboard.', 'failed', '2026-06-16 08:14:40'),
(2, '+250789239908', '? *NEW BOOKING — Home Help*\n\n? *Client:* MUHIRE Jean\n? *Phone:* 0789239908\n?? *Email:* muhirejean3@gmail.com\n? *Services:* Cleaning\n? *Neighbourhood:* Mbugangari\n? *Frequency:* 3 days/week\n?? *Languages:* English,Kinyarwanda\n? *Rooms:* 5\n? *Accommodation:* Live In\n? *Est. Monthly Fee:* 32,000 RWF\n\n? Please review on the admin dashboard.', 'failed', '2026-06-16 08:20:56'),
(3, '+250789239908', '? *TEST* — WhatsApp integration is working!', 'failed', '2026-06-16 08:25:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_agent_user` (`user_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_start` (`start_date`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `email_log`
--
ALTER TABLE `email_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `homepage_content`
--
ALTER TABLE `homepage_content`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_sort_order` (`sort_order`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provider` (`provider_id`),
  ADD KEY `idx_ja_service` (`service_id`),
  ADD KEY `idx_ja_agent` (`agent_id`),
  ADD KEY `idx_ja_status` (`status`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_la_email` (`email`),
  ADD KEY `idx_la_ip` (`ip_address`),
  ADD KEY `idx_la_time` (`attempted_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_read` (`is_read`),
  ADD KEY `idx_notif_created` (`created_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `service_fees`
--
ALTER TABLE `service_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD UNIQUE KEY `uq_google_id` (`google_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `whatsapp_log`
--
ALTER TABLE `whatsapp_log`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agents`
--
ALTER TABLE `agents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `email_log`
--
ALTER TABLE `email_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `homepage_content`
--
ALTER TABLE `homepage_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `service_fees`
--
ALTER TABLE `service_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `whatsapp_log`
--
ALTER TABLE `whatsapp_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agents`
--
ALTER TABLE `agents`
  ADD CONSTRAINT `agents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_applications_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `job_applications_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_fees`
--
ALTER TABLE `service_fees`
  ADD CONSTRAINT `service_fees_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
