-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 28, 2025 at 04:50 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u654420946_Healthbase`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('Pending','Confirmed','Declined','Ongoing','Completed') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `urgency` enum('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
  `preferred_window` enum('Any','Morning','Afternoon') NOT NULL DEFAULT 'Any',
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `doctor_id`, `patient_id`, `service_id`, `appointment_date`, `status`, `created_at`, `updated_at`, `urgency`, `preferred_window`, `notes`) VALUES
(1, 5, 1, NULL, '2025-08-27 18:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(7, 6, 15, NULL, '2025-09-01 19:00:00', 'Declined', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(8, 6, 15, NULL, '2025-09-06 19:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(9, 5, 15, NULL, '2025-09-17 20:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(12, 3, 1, NULL, '2025-09-04 18:00:00', 'Declined', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(13, 3, 15, NULL, '2025-09-03 18:00:00', 'Declined', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(14, 3, 1, NULL, '2025-09-05 18:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(15, 5, 16, NULL, '2025-09-01 00:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(16, 5, 17, NULL, '2025-09-10 13:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(17, 3, 17, NULL, '2025-08-31 16:00:00', 'Declined', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(18, 3, 18, NULL, '2025-09-05 00:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(20, 3, 15, NULL, '2025-09-10 17:00:00', 'Declined', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(21, 3, 15, NULL, '2025-09-08 05:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(22, 5, 15, NULL, '2025-09-08 16:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(23, 5, 15, NULL, '2025-09-09 07:00:00', 'Declined', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(24, 5, 15, NULL, '2025-09-09 06:00:00', 'Confirmed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(25, 19, 17, NULL, '2025-10-16 16:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(26, 19, 20, NULL, '2025-10-29 14:00:00', 'Completed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(27, 19, 20, NULL, '2025-10-29 14:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(28, 19, 20, NULL, '2025-10-29 14:00:00', 'Declined', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(29, 19, 20, NULL, '2025-10-29 14:00:00', 'Confirmed', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(30, 19, 21, NULL, '2025-10-27 09:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(31, 19, 21, NULL, '2025-10-28 09:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(32, 5, 21, NULL, '2025-10-27 09:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(33, 19, 20, NULL, '2025-10-28 15:20:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(34, 19, 17, NULL, '2025-10-29 17:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(35, 19, 17, NULL, '2025-10-27 12:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(36, 3, 22, NULL, '2025-10-31 10:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(37, 3, 22, NULL, '2025-10-27 12:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(38, 5, 17, NULL, '2025-10-28 12:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(39, 5, 17, NULL, '2025-10-29 14:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(40, 5, 17, NULL, '2025-10-29 14:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL),
(41, 5, 23, NULL, '2025-10-29 13:00:00', 'Pending', '2025-10-28 09:08:30', '2025-10-28 09:08:30', 'Normal', 'Any', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointment_changes`
--

CREATE TABLE `appointment_changes` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `changed_by_user_id` int(11) DEFAULT NULL,
  `change_type` enum('reschedule','cancel') NOT NULL,
  `old_datetime` datetime DEFAULT NULL,
  `new_datetime` datetime DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_reports`
--

CREATE TABLE `appointment_reports` (
  `id` int(11) NOT NULL,
  `report_type` enum('Daily','Weekly','Monthly') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `total_appointments` int(11) NOT NULL DEFAULT 0,
  `confirmed_count` int(11) NOT NULL DEFAULT 0,
  `completed_count` int(11) NOT NULL DEFAULT 0,
  `cancelled_count` int(11) NOT NULL DEFAULT 0,
  `no_show_count` int(11) NOT NULL DEFAULT 0,
  `generated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `entity_type` enum('patient','appointment','medication','allergy','vital','history','problem','user') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `action` enum('create','update','delete') NOT NULL,
  `changed_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changed_fields`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_holidays`
--

CREATE TABLE `clinic_holidays` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `visit_date` datetime NOT NULL,
  `chief_complaint` text DEFAULT NULL,
  `consultation_notes` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `next_visit_risk_score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_availability`
--

CREATE TABLE `doctor_availability` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday',
  `is_available` tinyint(1) NOT NULL DEFAULT 0,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_availability`
--

INSERT INTO `doctor_availability` (`id`, `doctor_id`, `day_of_week`, `is_available`, `start_time`, `end_time`, `created_at`, `updated_at`) VALUES
(6, 19, 0, 1, '00:00:00', '20:00:00', '2025-10-27 04:36:54', '2025-10-27 04:36:54'),
(8, 19, 1, 1, '00:00:00', '00:00:00', '2025-10-27 04:37:00', '2025-10-27 04:37:00'),
(9, 19, 2, 1, '09:00:00', '17:00:00', '2025-10-27 04:37:03', '2025-10-27 04:37:03'),
(10, 19, 3, 1, '09:00:00', '17:00:00', '2025-10-27 04:37:05', '2025-10-27 04:37:05'),
(11, 19, 4, 1, '07:00:00', '05:00:00', '2025-10-27 04:37:18', '2025-10-27 04:37:18'),
(12, 19, 5, 1, '09:00:00', '17:00:00', '2025-10-27 04:37:23', '2025-10-27 04:37:23'),
(13, 19, 6, 1, '09:00:00', '17:00:00', '2025-10-27 04:37:27', '2025-10-27 04:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `medical_history_entries`
--

CREATE TABLE `medical_history_entries` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `entry_type` enum('Diagnosis','Treatment','Lab_Result','Procedure','Note') NOT NULL,
  `entry_text` text NOT NULL,
  `entry_date` datetime NOT NULL,
  `physician_name` varchar(255) DEFAULT NULL,
  `related_consultation_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `ticket_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `link`, `is_read`, `created_at`, `ticket_id`, `type`) VALUES
(1, 1, 'Your appointment #4 has been accepted.', NULL, 0, '2025-08-30 05:47:14', NULL, 'general'),
(2, 4, 'Your appointment #4 has been accepted.', NULL, 0, '2025-08-30 05:59:50', NULL, 'general'),
(3, 5, 'Your appointment #5 has been accepted.', NULL, 1, '2025-08-30 06:22:43', NULL, 'general'),
(5, 1, 'Your appointment #6 has been accepted.', NULL, 0, '2025-08-30 06:50:43', NULL, 'general'),
(6, 1, 'Your appointment #5 has been accepted.', NULL, 0, '2025-08-30 06:50:45', NULL, 'general'),
(9, 10, 'Your appointment #7 has been accepted.', NULL, 1, '2025-08-30 07:22:29', NULL, 'general'),
(10, 10, 'Your appointment #7 has been accepted.', NULL, 1, '2025-08-30 07:22:30', NULL, 'general'),
(11, 1, 'Princess Salazar, your appointment on September 17, 2025 08:00 AM has been accepted.', NULL, 0, '2025-08-30 07:40:49', NULL, 'general'),
(12, 1, 'Rhoxelle Legaspi, your appointment on September 11, 2025 05:00 PM has been accepted.', NULL, 0, '2025-08-30 07:40:53', NULL, 'general'),
(13, 1, 'Princess Salazar, your appointment on September 17, 2025 08:00 AM has been accepted.', NULL, 0, '2025-08-30 07:40:54', NULL, 'general'),
(14, 10, 'Alex Verian, your appointment on September 17, 2025 08:00 PM has been accepted.', NULL, 1, '2025-08-30 07:40:54', NULL, 'general'),
(15, 1, 'Angelo Villanueva, your appointment on September 5, 2025 06:00 PM has been accepted.', NULL, 0, '2025-08-30 08:13:16', NULL, 'general'),
(16, 13, 'ashuude azyth, your appointment on September 1, 2025 12:00 AM has been accepted.', NULL, 0, '2025-08-31 13:56:50', NULL, 'general'),
(17, 12, 'Vince Gabrielle Morales, your appointment on August 31, 2025 04:00 PM has been accepted.', NULL, 0, '2025-08-31 14:04:05', NULL, 'general'),
(18, 12, 'Vince Gabrielle Morales, your appointment on August 31, 2025 04:00 PM has been accepted.', NULL, 1, '2025-08-31 14:24:46', NULL, 'general'),
(19, 14, 'pao agikerism, your appointment on September 5, 2025 12:00 AM has been accepted.', NULL, 0, '2025-09-04 13:21:14', NULL, 'general'),
(20, 14, 'pao agikerism, your appointment on September 5, 2025 12:00 AM has been accepted.', NULL, 0, '2025-09-04 13:49:43', NULL, 'general'),
(21, 10, 'Your support ticket #1 has been replied to.', NULL, 1, '2025-09-05 04:05:37', NULL, 'general'),
(22, 10, 'Your support ticket #1 has been replied to.', NULL, 1, '2025-09-05 04:16:56', NULL, 'general'),
(23, 10, 'Your support ticket #2 has been replied to.', NULL, 1, '2025-09-05 04:33:10', NULL, 'general'),
(24, 10, 'Your support ticket #2 has been replied to.', NULL, 1, '2025-09-05 04:37:50', 2, 'general'),
(25, 7, 'Your support ticket #3 has been replied to.', NULL, 0, '2025-09-06 01:16:01', 3, 'general'),
(26, 10, 'Your support ticket #2 has been replied to.', NULL, 1, '2025-09-06 05:43:52', 2, 'general'),
(27, 16, 'Your support ticket #4 has been replied to.', NULL, 0, '2025-09-06 08:45:35', 4, 'general'),
(28, 16, 'Your support ticket #4 has been replied to.', NULL, 0, '2025-09-06 09:02:41', 4, 'general'),
(29, 10, 'Your support ticket #5 has been replied to.', NULL, 1, '2025-09-06 12:30:29', 5, 'general'),
(30, 3, 'Your support ticket #7 has been replied to.', NULL, 1, '2025-09-08 06:10:22', 7, 'general'),
(31, 3, '📅 You have a new appointment request.', 'appointments.php?id=20', 1, '2025-09-08 06:36:17', NULL, 'appointment'),
(32, 10, 'Sammuel Gregorio, your appointment on September 10, 2025 05:00 PM has been accepted.', 'appointments.php', 1, '2025-09-08 06:40:09', NULL, 'appointment'),
(33, 15, 'pao agikerism, your appointment on September 5, 2025 12:00 AM has been accepted.', 'appointments.php?appointment_id=19', 0, '2025-09-08 06:49:49', NULL, 'appointment'),
(34, 10, 'Sammuel Gregorio, your appointment on September 10, 2025 05:00 PM has been accepted.', 'appointments.php?appointment_id=20', 1, '2025-09-08 06:49:50', NULL, 'appointment'),
(35, 3, '📅 You have a new appointment request.', 'appointments.php?id=21', 0, '2025-09-08 06:50:34', NULL, 'appointment'),
(36, 10, 'Arkileto Tralala, your appointment on September 8, 2025 05:00 AM has been accepted.', 'appointments.php?appointment_id=21', 0, '2025-09-08 06:50:44', NULL, 'appointment'),
(37, 3, 'You received a new support message: Test', 'view_ticket.php?id=8', 0, '2025-09-08 06:53:16', NULL, 'general'),
(38, 3, '📩 You received a new support message: Test', 'view_ticket.php?id=9', 0, '2025-09-08 06:58:40', NULL, 'support'),
(39, 12, 'Vince Gabrielle Morales, your appointment on September 10, 2025 01:00 PM has been accepted.', 'appointments.php?appointment_id=16', 1, '2025-09-08 07:27:02', NULL, 'appointment'),
(40, 5, '📩 You received a new support message: Test', 'view_ticket.php?id=10', 1, '2025-09-08 08:47:53', NULL, 'support'),
(41, 5, '📅 You have a new appointment request.', 'appointments.php?id=22', 1, '2025-09-08 08:59:10', NULL, 'appointment'),
(42, 10, 'Yumee Nakida, your appointment on September 8, 2025 04:00 PM has been accepted.', 'appointments.php?appointment_id=22', 1, '2025-09-08 08:59:17', NULL, 'appointment'),
(43, 5, '📅 You have a new appointment request.', 'appointments.php?id=23', 0, '2025-09-08 10:42:02', NULL, 'appointment'),
(44, 5, '📩 You received a new support message: Test', 'view_ticket.php?id=11', 1, '2025-09-08 10:43:25', NULL, 'support'),
(45, 10, 'Yumee Nahida, your appointment on September 9, 2025 07:00 AM has been accepted.', 'appointments.php?appointment_id=23', 1, '2025-09-08 11:13:14', NULL, 'appointment'),
(46, 10, 'Yumee Nahida, your appointment on September 9, 2025 07:00 AM has been accepted.', 'appointments.php?appointment_id=23', 1, '2025-09-08 11:14:03', NULL, 'appointment'),
(47, 10, 'Yumee Nahida, your appointment on September 9, 2025 07:00 AM has been accepted.', 'appointments.php?appointment_id=23', 1, '2025-09-08 11:14:04', NULL, 'appointment'),
(48, 10, 'Yumee Nahida, your appointment on September 9, 2025 07:00 AM has been accepted.', 'appointments.php?appointment_id=23', 0, '2025-09-08 11:14:27', NULL, 'appointment'),
(49, 10, 'Yumee Nahida, unfortunately your appointment on September 9, 2025 07:00 AM has been declined.', 'appointments.php?appointment_id=23', 0, '2025-09-08 11:21:35', NULL, 'appointment'),
(50, 10, 'Your support ticket #12 has been replied to.', NULL, 0, '2025-09-08 11:23:03', 12, 'general'),
(51, 10, 'Your support ticket #15 has been replied to.', NULL, 1, '2025-09-08 12:28:30', 15, 'general'),
(52, 6, '📩 You received a new support message: Test', 'view_ticket.php?id=16', 0, '2025-09-08 12:49:35', NULL, 'support'),
(53, 10, '📩 You received a new support message: test', 'view_ticket.php?id=17', 1, '2025-09-08 12:51:17', NULL, 'support'),
(54, 5, '📅 You have a new appointment request.', 'appointments.php?id=24', 0, '2025-09-09 09:25:48', NULL, 'appointment'),
(55, 10, 'Shawn Marty Pineda, your appointment on September 9, 2025 06:00 AM has been accepted.', 'appointments.php?appointment_id=24', 0, '2025-09-09 09:31:07', NULL, 'appointment'),
(56, 19, '📅 You have a new appointment request.', 'appointments.php?id=25', 1, '2025-10-09 13:03:27', NULL, 'appointment'),
(57, 19, '📅 New appointment scheduled by bharon: bharon candelaria on October 29, 2025 2:00 PM (Status: Pending)', '../dashboard/doctor_dashboard.php', 1, '2025-10-27 01:45:53', NULL, 'appointment'),
(58, 19, '📅 New appointment scheduled by bharon: bharon candelaria on October 29, 2025 2:00 PM (Status: Pending)', '../dashboard/doctor_dashboard.php', 1, '2025-10-27 01:54:02', NULL, 'appointment'),
(59, 19, '📅 New appointment scheduled by bharon: bharon candelaria on October 29, 2025 2:00 PM (Status: Pending)', '../dashboard/doctor_dashboard.php', 1, '2025-10-27 01:54:35', NULL, 'appointment'),
(60, 19, 'Appointment status updated by assistant: bharon candelaria on Oct 29, 2025 02:00 PM is now Completed', '../appointments/appointments.php?appointment_id=29', 1, '2025-10-27 02:55:13', NULL, 'appointment'),
(61, 19, 'Appointment status updated by assistant: bharon candelaria on Oct 29, 2025 02:00 PM is now Confirmed', '../appointments/appointments.php?appointment_id=29', 1, '2025-10-27 02:55:17', NULL, 'appointment'),
(62, 19, 'Appointment status updated by assistant: bharon candelaria on Oct 29, 2025 02:00 PM is now Declined', '../appointments/appointments.php?appointment_id=28', 1, '2025-10-27 02:59:10', NULL, 'appointment'),
(63, 19, 'Appointment status updated by assistant: bharon candelaria on Oct 29, 2025 02:00 PM is now Completed', '../appointments/appointments.php?appointment_id=26', 1, '2025-10-27 02:59:31', NULL, 'appointment'),
(64, 19, 'Appointment status updated by assistant: bharon candelaria on Oct 29, 2025 02:00 PM is now Completed', '../appointments/appointments.php?appointment_id=26', 1, '2025-10-27 03:02:44', NULL, 'appointment'),
(65, 19, 'New appointment created: bharon candelaria on Oct 28, 2025 03:20 PM', '../appointments/appointments.php', 1, '2025-10-27 07:20:03', NULL, 'appointment'),
(66, 5, '📅 You have a new appointment request.', 'appointments.php?id=40', 0, '2025-10-28 03:36:30', NULL, 'appointment'),
(67, 5, '📅 You have a new appointment request.', 'appointments.php?id=41', 0, '2025-10-28 09:02:53', NULL, 'appointment');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires`) VALUES
(27, 'ledorespinoza@gmail.com', '541bf0ecc739ee419a19456bd193b4ed560f6d86398e8a01790d8b2b61410823', 1761264505),
(28, 'johnmarmiranda58@gmail.com', '25d07d26f0d0dc6e77faef2b618a1e2a58c9cebd8b6371fe316222229cb5dd39', 1761266495),
(34, 'ivanjameshernandez19@gmail.com', '6ec9d9f7758d7fa4bede4976dcba3008f63cd0f035f94c3215edc5e5f887f57c', 1761624016),
(38, 'yumeenakida@gmail.com', '66123b214e9fcb263295485eb5db8398278e469c93fc12d6495ea58d48260509', 1761668473);

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `age` int(11) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `health_concern` varchar(100) NOT NULL,
  `consent_accepted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `first_name`, `last_name`, `date_of_birth`, `phone`, `address_line`, `city`, `province`, `postal_code`, `age`, `gender`, `health_concern`, `consent_accepted_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'Angelo', 'Villanueva', NULL, NULL, NULL, NULL, NULL, NULL, 21, 'Male', 'CONSULTATION : INTERNAL MEDICINE - GASTROENTEROLOGY - Stomach Ulcer', NULL, '2025-10-28 09:07:48', '2025-10-28 09:07:48'),
(15, 10, 'Shawn Marty', 'Pineda', NULL, NULL, NULL, NULL, NULL, NULL, 21, 'Male', 'CONSULTATION : DERMATOLOGY - Acne', NULL, '2025-10-28 09:07:48', '2025-10-28 09:07:48'),
(16, 13, 'ashuude', 'azyth', NULL, NULL, NULL, NULL, NULL, NULL, 20, 'Male', 'CONSULTATION : DERMATOLOGY - Acne', NULL, '2025-10-28 09:07:48', '2025-10-28 09:07:48'),
(17, 12, 'VInce', 'Morales', NULL, NULL, NULL, NULL, NULL, NULL, 21, 'Male', 'CONSULTATION : DERMATOLOGY - Acne', NULL, '2025-10-28 09:07:48', '2025-10-28 14:27:33'),
(18, 14, 'pao', 'agikerism', NULL, NULL, NULL, NULL, NULL, NULL, 69, 'Male', 'CONSULTATION : INTERNAL MEDICINE - GASTROENTEROLOGY - Stomach Ulcer', NULL, '2025-10-28 09:07:48', '2025-10-28 09:07:48'),
(20, 22, 'bharon', 'candelaria', NULL, NULL, NULL, NULL, NULL, NULL, 25, 'Male', 'General Consultation', NULL, '2025-10-28 09:07:48', '2025-10-28 09:07:48'),
(21, 26, 'Ivan James', 'Hernandez', NULL, NULL, NULL, NULL, NULL, NULL, 21, 'Male', 'CONSULTATION : DERMATOLOGY - Acne', NULL, '2025-10-28 09:07:48', '2025-10-28 09:07:48'),
(22, 28, 'Mark', 'Barroga', NULL, NULL, NULL, NULL, NULL, NULL, 25, 'Male', '', NULL, '2025-10-28 09:07:48', '2025-10-28 09:07:48'),
(23, 27, 'chris', 'candelaria', NULL, NULL, NULL, NULL, NULL, NULL, 25, 'Male', 'CONSULTATION : ORTHOPEDIC SURGERY - Arthritis', NULL, '2025-10-28 09:07:48', '2025-10-28 09:07:48'),
(24, 29, 'John Paul', 'Quinto', NULL, NULL, NULL, NULL, NULL, NULL, 22, 'Male', 'CONSULTATION : ORTHOPEDIC SURGERY - Arthritis', NULL, '2025-10-28 15:39:08', '2025-10-28 16:29:38');

-- --------------------------------------------------------

--
-- Table structure for table `patient_allergies`
--

CREATE TABLE `patient_allergies` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `substance` varchar(150) NOT NULL,
  `reaction` varchar(255) DEFAULT NULL,
  `severity` enum('Mild','Moderate','Severe','Unknown') DEFAULT 'Unknown',
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_histories`
--

CREATE TABLE `patient_histories` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `type` enum('PMH','PSH','Family','Social','Immunization','Other') NOT NULL,
  `condition` varchar(200) NOT NULL,
  `details` text DEFAULT NULL,
  `onset_date` date DEFAULT NULL,
  `resolved_date` date DEFAULT NULL,
  `status` enum('Active','Resolved','Unknown') DEFAULT 'Active',
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_medications`
--

CREATE TABLE `patient_medications` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `drug_name` varchar(150) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `route` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_preferences`
--

CREATE TABLE `patient_preferences` (
  `patient_id` int(11) NOT NULL,
  `preferred_window` enum('Any','Morning','Afternoon') NOT NULL DEFAULT 'Any',
  `preferred_doctor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_problems`
--

CREATE TABLE `patient_problems` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `problem` varchar(200) NOT NULL,
  `status` enum('Active','Resolved') NOT NULL DEFAULT 'Active',
  `onset_date` date DEFAULT NULL,
  `resolution_date` date DEFAULT NULL,
  `last_reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_vitals`
--

CREATE TABLE `patient_vitals` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `systolic_mmHg` smallint(6) DEFAULT NULL,
  `diastolic_mmHg` smallint(6) DEFAULT NULL,
  `heart_rate_bpm` smallint(6) DEFAULT NULL,
  `resp_rate_bpm` smallint(6) DEFAULT NULL,
  `temperature_c` decimal(4,1) DEFAULT NULL,
  `spo2_percent` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `quantity` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_overrides`
--

CREATE TABLE `provider_overrides` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 0,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` varchar(200) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_schedules`
--

CREATE TABLE `provider_schedules` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_minutes` smallint(6) NOT NULL DEFAULT 60,
  `location` varchar(100) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `risk_predictions`
--

CREATE TABLE `risk_predictions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `prediction_date` datetime NOT NULL,
  `risk_level` enum('Low','Medium','High','Critical') NOT NULL,
  `risk_score` decimal(5,2) NOT NULL,
  `factors` text DEFAULT NULL,
  `predicted_return_days` int(11) DEFAULT NULL,
  `actual_return_date` datetime DEFAULT NULL,
  `accuracy_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `price` decimal(10,2) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `specialization`, `duration_minutes`, `price`, `active`, `created_at`) VALUES
(1, 'General Consultation', NULL, 60, 0.00, 1, '2025-10-28 16:14:21'),
(2, 'Dermatology Consultation', 'Dermatology', 60, 0.00, 1, '2025-10-28 16:14:21'),
(3, 'Gastroenterology Consultation', 'Gastroenterology', 60, 0.00, 1, '2025-10-28 16:14:21'),
(4, 'Orthopedic Consultation', 'Orthopedics', 60, 0.00, 1, '2025-10-28 16:14:21'),
(5, 'Follow-up Visit', NULL, 30, 0.00, 1, '2025-10-28 16:14:21');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','read','closed') DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `is_starred` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `user_id`, `subject`, `message`, `status`, `created_at`, `is_read`, `is_deleted`, `is_starred`) VALUES
(1, 10, 'Appointment issue', 'a sudden change in my schedule because doctor is not available', '', '2025-09-05 02:39:48', 0, 1, 0),
(2, 10, 'Appointment Reschedule', 'Hello i would like to reschedule my appointment', '', '2025-09-05 04:32:21', 1, 1, 0),
(3, 7, 'Hello', 'A message heh', '', '2025-09-06 01:07:43', 1, 1, 0),
(4, 16, 'Nababaliw na', 'Nakakabaliw na po yung subjects ko, may gamot pa ba dito?', '', '2025-09-06 08:45:23', 0, 1, 0),
(5, 10, 'Test', 'Testing For Services', 'open', '2025-09-06 12:30:11', 1, 1, 1),
(6, 10, 'Test', 'Testing For Services', 'open', '2025-09-06 12:30:35', 1, 1, 0),
(7, 3, 'Test', 'Hello', '', '2025-09-08 06:09:44', 1, 1, 0),
(8, 3, 'Test', 'Subject', 'open', '2025-09-08 06:53:16', 1, 1, 0),
(9, 3, 'Test', 'Hello!', 'open', '2025-09-08 06:58:40', 1, 1, 0),
(10, 5, 'Test', 'hellO!', 'open', '2025-09-08 08:47:53', 1, 1, 0),
(11, 5, 'Test', 'Hello!', 'open', '2025-09-08 10:43:25', 1, 1, 0),
(12, 10, 'Change appointment', 'i would like to change my appointment!', '', '2025-09-08 11:22:32', 1, 1, 0),
(13, 10, 'Test', 'Hello', 'open', '2025-09-08 12:08:48', 1, 1, 0),
(14, 6, 'Test', 'Hello Friend!', 'open', '2025-09-08 12:10:11', 1, 1, 0),
(15, 10, 'Change appointment', 'Testing', '', '2025-09-08 12:14:31', 1, 1, 0),
(16, 6, 'Test', 'hello', 'open', '2025-09-08 12:49:35', 1, 1, 0),
(17, 10, 'test', 'hello', 'open', '2025-09-08 12:51:17', 1, 0, 0),
(18, 10, 'Change appointment', 'Change appointment schedule', 'open', '2025-09-09 09:48:59', 1, 0, 0),
(19, 12, 'Time', 'fdsbfhdsfhuyadsvfuadsyvfau', 'open', '2025-10-09 13:07:22', 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_replies`
--

CREATE TABLE `ticket_replies` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `assistant_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_replies`
--

INSERT INTO `ticket_replies` (`id`, `ticket_id`, `assistant_id`, `message`, `created_at`, `user_id`) VALUES
(1, 1, 1, 'ill notify you once the doctor is available!!', '2025-09-05 03:11:21', NULL),
(2, 1, 1, 'the doctor said will be available soon', '2025-09-05 04:05:37', NULL),
(3, 1, 1, 'sige na knbg', '2025-09-05 04:16:56', NULL),
(4, 2, 1, 'hello! when will you reschedule your appointment?', '2025-09-05 04:33:10', NULL),
(5, 2, 1, 'hello! when will you reschedule your appointment?', '2025-09-05 04:37:50', NULL),
(6, 3, 1, 'Hellooo what can I help you with?', '2025-09-06 01:16:01', NULL),
(8, 2, NULL, 'i would like to reschedule my appointment on september 10!', '2025-09-06 05:36:04', 10),
(9, 2, 1, 'alright ill let the doctor know!', '2025-09-06 05:43:52', NULL),
(10, 4, 1, 'edi baliw kana', '2025-09-06 08:45:35', NULL),
(11, 4, 1, 'pagamot kana', '2025-09-06 09:02:41', NULL),
(12, 5, 1, 'what can i help you with?', '2025-09-06 12:30:29', NULL),
(13, 5, NULL, 'in coding', '2025-09-06 12:31:37', 10),
(14, 5, NULL, 'test', '2025-09-06 12:32:00', 10),
(15, 7, 1, 'hello', '2025-09-08 06:10:22', NULL),
(16, 12, 1, 'hello! what appointment will you change into?', '2025-09-08 11:23:03', NULL),
(17, 14, NULL, 'hello', '2025-09-08 12:12:04', 1),
(18, 15, NULL, 'TEST', '2025-09-08 12:14:54', 1),
(19, 15, 1, 'test hello', '2025-09-08 12:28:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `role` enum('user','doctor','admin','assistant') NOT NULL DEFAULT 'user',
  `specialization` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `password`, `created_at`, `first_name`, `last_name`, `gender`, `status`, `role`, `specialization`) VALUES
(1, 'yumeenakida@gmail.com', 'Shawn21', 'admins12345', '2025-08-24 07:39:46', 'Shawn Marty', 'Pineda', 'Male', 'active', 'assistant', NULL),
(2, 'guildtech04@gmail.com', 'RimuruAdmin', '$2y$10$kTy9W83qlyHuPIJFbGwei.euaIehXfINaSAPBygDexGUIWkSbPWlm', '2025-08-24 07:44:01', 'Rimuru', 'Tempest', 'Male', 'active', 'admin', NULL),
(3, 'kurushiminakida@gmail.com', 'KenzoT', '$2y$10$fwQ9MbkbGJqdrWxXQ2rgsuC/pOdyEA8ruQhgkno06hrrWcL8PNKmC', '2025-08-24 07:48:00', 'Kenzo', 'Tenma', 'Male', 'active', 'doctor', 'Gastroenterology'),
(4, 'mpineda2625@gmail.com', 'marvinpineda26', '$2y$10$P12YX379/KsXBezLorbJM.7x4LW3Rqqcoby8MIXDJe8TSzIKEYQGK', '2025-08-24 07:54:01', 'Marvin', 'Pineda', 'Male', 'active', 'user', NULL),
(5, 'ledorespinoza@gmail.com', 'Megumi', '$2y$10$XGupT.ADc/L2B142UF0P9OFn5FVSqr8JHZh0FNvT4ryPUdl3HNcHi', '2025-08-24 08:19:57', 'Megumi', 'Takani', 'Female', 'active', 'doctor', 'Dermatology'),
(6, 'shawnmarty.pineda@my.jru.edu', 'KarlH', '$2y$10$tsKaJHDEmVhknGooyqvwzOHhkfRu.M3B7O/TaSCtBVTdCE403tR96', '2025-08-24 08:22:23', 'Karl', 'Howards', 'Male', 'active', 'user', NULL),
(7, 'jhasminetabs@gmail.com', 'Mari', '$2y$10$CuCPKf.C.dsz9LaM3aLFXurv//.qAXAmwEtB.sn9LMrobO/wA/2lq', '2025-08-24 08:27:50', 'Jhasmine Mariel', 'Tababa', 'Female', 'active', 'user', NULL),
(10, 'guildtech21@gmail.com', 'Rosalind', '$2y$10$eSp2cwR1qg16/njDig1kNO8O38yHJoH/GwtUkd/3BgTvM9waMiEuq', '2025-08-24 09:56:02', 'Rosalind', 'Legaspi', 'Female', 'active', 'user', NULL),
(11, 'johnmarmiranda58@gmail.com', 'Jmar', '$2y$10$t7PvHFMfCa7q.3ztHB727u53duebBjWYMi1Rv5OTIeNL4tXryCeWO', '2025-08-25 05:46:47', 'John Mar', 'Miranda', 'Male', 'active', 'doctor', NULL),
(12, 'vincemorales99@gmail.com', 'Feitaann', '$2y$10$7URqToDsZJ32DMlOyvUG1u3pSLPTii3Rd2XslhTYfK.iXdOulZozC', '2025-08-31 13:37:29', 'Vince Gabrielle', 'Morales', 'Male', 'active', 'user', NULL),
(13, 'ashuraangelyuzo@gmail.com', 'hipoakoto', '$2y$10$J5pmJO10srny8ObTAxTN6.nYU7Bz/DO8rrF9Zcy8BUMcjZ0fWHFTK', '2025-08-31 13:55:20', 'ashuude', 'azyth', 'Male', 'active', 'user', NULL),
(14, 'shirolty24@gmail.com', 'paoagik', '$2y$10$350x.xzCCLwTYHwzc8YszeNpJr1GglTd0tnNUMgcstViaHQJnJmaC', '2025-09-04 13:16:20', 'pao', 'agikerism', 'Male', 'active', 'user', NULL),
(15, 'msrb0409@gmail.com', 'vinagik', '$2y$10$QIFst8cc5B1M.rRJ3OyGXu4nOdZwesmiHSCuoQo8AzPBYhuP.u9W.', '2025-09-04 15:05:02', 'vin', 'agikerism', 'Male', 'active', 'user', NULL),
(16, 'rhoannejoylegaspi@gmail.com', 'Rhoanne_Eyyy', '$2y$10$/AMmVCDyzeKgLVs4od.joeTDRNE30Aiu5PeeNIODGljFWzaYHuHQO', '2025-09-06 08:43:59', 'Hulaan', 'mo.ey', 'Female', 'active', 'user', NULL),
(17, 'ashuraangely@gmail.com', 'Samuely', '$2y$10$n4UeOPigjcbMVc8yKTz63u7fUelbRK9kFesWCAfHCd58YNuWzZwea', '2025-09-06 15:52:51', 'Samuel Marty', 'Pineda', 'Male', 'active', 'user', NULL),
(18, 'onlycheisz@gmail.com', 'Chase', '$2y$10$hqRYIVtq02ICka0Vq.I7tuGnbYFS3GrWnPjALmRTMt9kiAF5ONeRi', '2025-09-09 08:54:53', 'Chase', 'Ramos', 'Male', 'active', 'user', NULL),
(19, 'vincemorales1007@gmail.com', 'Vince', '$2y$10$zfzO/XbQ4d9uOeOA7KhseeOfjxq3aejp8cG6vQW3mBQVAv8WJw88e', '2025-10-09 12:15:05', 'Gabrielle', 'Morales', 'Male', 'active', 'doctor', 'Orthopedics'),
(20, 'joshua.anoos@my.jru.edu', 'Joshua Anoos', '$2y$10$kgLKzXZHZJ6VE/ochC.d/.pAKtRuHdpYCXFnVN5pMiw743or0OrFi', '2025-10-10 07:27:58', 'Joshua', 'Anoos', 'Male', 'active', 'user', NULL),
(21, 'jp12quinto@gmail.com', 'pol12', '$2y$10$mIkVEkNW7V3RJU/UAoHF6OIgG68Pi/hajROkucPmVDCYyzWasa6U6', '2025-10-16 15:13:33', 'John', 'Quinto', 'Male', 'active', 'user', NULL),
(22, 'candelariabharon0014@gmail.com', 'bharon', '$2y$10$whqxxXb0Zu.0k7BSPZK1/eAmcN0KaGD97YyDKUYHNIPl8tammkUCK', '2025-10-23 16:20:30', 'bharon', 'candelaria', 'Male', 'active', 'assistant', NULL),
(24, 'candelariabharon14@gmail.com', 'VinceGabrielle', '$2y$10$1ADaZmZSBFb77t97IpFbp.mnw1l3hg8Pd4l4j9s9.irgKNQANF0cm', '2025-10-26 06:03:29', 'Vince Gabrielle', 'Morales', 'Male', 'active', 'assistant', NULL),
(25, 'ivanjameshernendez1123@gmail.com', 'Ivan', '$2y$10$ZWsjMSyJwztsnJr8ygmRW.8C7CkOGfBMTGTgZawR5D2OROPJFhpi.', '2025-10-26 17:15:33', '', '', 'Male', 'active', 'assistant', NULL),
(26, 'ivanjameshernandez19@gmail.com', 'Ivan James', '$2y$10$gSvzKVDp9J6Kge8BZhe3Mu1PVcmgxNkuisZei2.zoBpA1pNqzXSmS', '2025-10-27 03:11:12', 'Ivan James', 'Hernandez', 'Male', 'active', 'user', NULL),
(27, 'bharonchristopher.candelaria@my.jru.edu', 'Bharon1', '$2y$10$XtJ3OHnUeXZfRDNW5zVslu4kEATlMAapR9id8iivIN3VpjeR3P85.', '2025-10-27 06:57:24', '', '', 'Male', 'active', 'user', NULL),
(28, 'ivanjameshernandez1123@gmail.com', 'Mark', '$2y$10$Q8oJPAYrbKJn35ml4Z72meQczHsZFxN53mfTA1ztkUv/iLJPE4bQa', '2025-10-27 11:00:08', 'Mark Luigi', 'Barroga', 'Male', 'active', 'user', NULL),
(29, 'johnpaul.quinto@my.jru.edu', 'John Paul', '$2y$10$veHgkybxt4FSZnsHzYUPj.gerMDqDxmk9OwfGHxMigt11ecPawDma', '2025-10-28 15:30:53', 'John Paul', 'Quinto', 'Male', 'active', 'user', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appt_status_date` (`status`,`appointment_date`),
  ADD KEY `idx_appt_doctor_date` (`doctor_id`,`appointment_date`),
  ADD KEY `idx_appt_patient_date` (`patient_id`,`appointment_date`),
  ADD KEY `idx_appt_service` (`service_id`);

--
-- Indexes for table `appointment_changes`
--
ALTER TABLE `appointment_changes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_changes_appt` (`appointment_id`,`change_type`,`created_at`),
  ADD KEY `fk_changes_user` (`changed_by_user_id`);

--
-- Indexes for table `appointment_reports`
--
ALTER TABLE `appointment_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_report_daterange` (`period_start`,`period_end`),
  ADD KEY `idx_report_doctor` (`doctor_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_user_time` (`user_id`,`created_at`);

--
-- Indexes for table `clinic_holidays`
--
ALTER TABLE `clinic_holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_holiday_date` (`date`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consultation_appt` (`appointment_id`),
  ADD KEY `idx_consultation_patient` (`patient_id`),
  ADD KEY `idx_consultation_doctor` (`doctor_id`),
  ADD KEY `idx_consultation_date` (`visit_date`);

--
-- Indexes for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_doctor_day` (`doctor_id`,`day_of_week`),
  ADD KEY `idx_doctor` (`doctor_id`);

--
-- Indexes for table `medical_history_entries`
--
ALTER TABLE `medical_history_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_patient` (`patient_id`),
  ADD KEY `idx_history_type` (`entry_type`),
  ADD KEY `idx_history_date` (`entry_date`),
  ADD KEY `idx_history_consult` (`related_consultation_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_patients_user` (`user_id`);

--
-- Indexes for table `patient_allergies`
--
ALTER TABLE `patient_allergies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_allergies_patient` (`patient_id`);

--
-- Indexes for table `patient_histories`
--
ALTER TABLE `patient_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hist_patient` (`patient_id`);

--
-- Indexes for table `patient_medications`
--
ALTER TABLE `patient_medications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_meds_patient` (`patient_id`);

--
-- Indexes for table `patient_preferences`
--
ALTER TABLE `patient_preferences`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `idx_pref_doctor` (`preferred_doctor_id`);

--
-- Indexes for table `patient_problems`
--
ALTER TABLE `patient_problems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_problems_patient` (`patient_id`);

--
-- Indexes for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vitals_patient_time` (`patient_id`,`recorded_at`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_presc_consult` (`consultation_id`),
  ADD KEY `idx_presc_appt` (`appointment_id`),
  ADD KEY `idx_presc_patient` (`patient_id`),
  ADD KEY `idx_presc_doctor` (`doctor_id`);

--
-- Indexes for table `provider_overrides`
--
ALTER TABLE `provider_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_doctor_date_window` (`doctor_id`,`date`,`start_time`,`end_time`),
  ADD KEY `idx_override_doctor_date` (`doctor_id`,`date`);

--
-- Indexes for table `provider_schedules`
--
ALTER TABLE `provider_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sched_doctor_day` (`doctor_id`,`day_of_week`,`active`);

--
-- Indexes for table `risk_predictions`
--
ALTER TABLE `risk_predictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_risk_patient` (`patient_id`),
  ADD KEY `idx_risk_date` (`prediction_date`),
  ADD KEY `idx_risk_level` (`risk_level`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_service_name` (`name`),
  ADD KEY `idx_service_spec` (`specialization`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `assistant_id` (`assistant_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `appointment_changes`
--
ALTER TABLE `appointment_changes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_reports`
--
ALTER TABLE `appointment_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clinic_holidays`
--
ALTER TABLE `clinic_holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `medical_history_entries`
--
ALTER TABLE `medical_history_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `patient_allergies`
--
ALTER TABLE `patient_allergies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_histories`
--
ALTER TABLE `patient_histories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_medications`
--
ALTER TABLE `patient_medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_problems`
--
ALTER TABLE `patient_problems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provider_overrides`
--
ALTER TABLE `provider_overrides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provider_schedules`
--
ALTER TABLE `provider_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `risk_predictions`
--
ALTER TABLE `risk_predictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointment_changes`
--
ALTER TABLE `appointment_changes`
  ADD CONSTRAINT `fk_changes_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_changes_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointment_reports`
--
ALTER TABLE `appointment_reports`
  ADD CONSTRAINT `fk_report_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consultation_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_consultation_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_consultation_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_history_entries`
--
ALTER TABLE `medical_history_entries`
  ADD CONSTRAINT `fk_history_consult` FOREIGN KEY (`related_consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_allergies`
--
ALTER TABLE `patient_allergies`
  ADD CONSTRAINT `fk_allergies_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_histories`
--
ALTER TABLE `patient_histories`
  ADD CONSTRAINT `fk_hist_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_medications`
--
ALTER TABLE `patient_medications`
  ADD CONSTRAINT `fk_meds_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_preferences`
--
ALTER TABLE `patient_preferences`
  ADD CONSTRAINT `fk_pref_doctor` FOREIGN KEY (`preferred_doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pref_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_problems`
--
ALTER TABLE `patient_problems`
  ADD CONSTRAINT `fk_problems_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  ADD CONSTRAINT `fk_vitals_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_presc_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_presc_consult` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_presc_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_presc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_overrides`
--
ALTER TABLE `provider_overrides`
  ADD CONSTRAINT `fk_override_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_schedules`
--
ALTER TABLE `provider_schedules`
  ADD CONSTRAINT `fk_sched_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `risk_predictions`
--
ALTER TABLE `risk_predictions`
  ADD CONSTRAINT `fk_risk_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD CONSTRAINT `ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`),
  ADD CONSTRAINT `ticket_replies_ibfk_2` FOREIGN KEY (`assistant_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
