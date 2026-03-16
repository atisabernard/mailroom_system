-- phpMyAdmin SQL Dump
-- version 5.2.3-1.fc43
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 09, 2026 at 10:04 AM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.4.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mailroom_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `distribution`
--

CREATE TABLE `distribution` (
  `id` int(11) NOT NULL,
  `newspaper_id` int(11) NOT NULL,
  `distributed_to` varchar(200) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `copies` int(11) DEFAULT 1,
  `date_distributed` date NOT NULL,
  `distributed_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `distribution`
--

INSERT INTO `distribution` (`id`, `newspaper_id`, `distributed_to`, `department`, `copies`, `date_distributed`, `distributed_by`) VALUES
(1, 1, 'Hollali Kelvin', 'IT Department', 1, '2026-03-07', 'Nadjat'),
(2, 2, 'Hollali Kelvin', 'IT Department', 1, '2026-03-08', 'Nadjat'),
(3, 2, 'Hollali Kelvin', 'IT Department', 1, '2026-03-08', 'Nadjat'),
(4, 2, 'Hollali Kelvin', 'IT Department', 1, '2026-03-08', 'Nadjat'),
(5, 2, 'Mr John', 'HR', 1, '2026-03-08', 'Nadjat'),
(6, 3, 'Mr John', 'HR', 1, '2026-03-08', 'Nadjat');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `document_name` varchar(200) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `origin` varchar(200) DEFAULT NULL,
  `copies_received` int(11) DEFAULT NULL,
  `date_received` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `document_name`, `type`, `type_id`, `origin`, `copies_received`, `date_received`) VALUES
(1, 'Bill', 'Legislative Documents', 1, 'Ministry of Education', 4, '2026-03-07');

-- --------------------------------------------------------

--
-- Table structure for table `document_distribution`
--

CREATE TABLE `document_distribution` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `department` varchar(150) NOT NULL,
  `recipient_name` varchar(150) NOT NULL,
  `number_received` int(11) NOT NULL,
  `number_distributed` int(11) NOT NULL,
  `date_distributed` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`id`, `type_name`, `description`, `created_at`) VALUES
(1, 'Legislative Documents', 'Official legislative documents including bills, acts, and parliamentary papers', '2026-03-08 03:56:46'),
(2, 'Administrative Documents', 'Internal administrative memos and correspondence', '2026-03-08 03:56:46'),
(3, 'Financial Documents', 'Budget statements, financial reports, and audit documents', '2026-03-08 03:56:46'),
(4, 'Policy Documents', 'Policy frameworks, guidelines, and strategic plans', '2026-03-08 03:56:46'),
(5, 'Reports', 'Annual reports, progress reports, and evaluation reports', '2026-03-08 03:56:46'),
(6, 'Correspondence', 'Official letters and correspondence', '2026-03-08 03:56:46'),
(7, 'Contracts', 'Contracts, MOUs, and agreements', '2026-03-08 03:56:46'),
(8, 'Personnel Records', 'Staff-related documents and records', '2026-03-08 03:56:46'),
(9, 'Project Documents', 'Project proposals, implementation plans, and project reports', '2026-03-08 03:56:46');

-- --------------------------------------------------------

--
-- Table structure for table `newspapers`
--

CREATE TABLE `newspapers` (
  `id` int(11) NOT NULL,
  `newspaper_name` varchar(100) DEFAULT NULL,
  `newspaper_number` varchar(50) DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `distributed_to` varchar(200) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `date_distributed` date DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `total_copies` int(11) DEFAULT 1,
  `available_copies` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `newspapers`
--

INSERT INTO `newspapers` (`id`, `newspaper_name`, `newspaper_number`, `date_received`, `received_by`, `status`, `distributed_to`, `department`, `date_distributed`, `category_id`, `total_copies`, `available_copies`) VALUES
(1, 'financial', '1234', '2026-03-07', 'Doreenda Abbey', 'distributed', NULL, NULL, NULL, 3, 1, 0),
(2, 'Daily Guide', 'DAI-20260308-015', '2026-03-08', 'Doreenda Abbey', 'available', NULL, NULL, NULL, 4, 1, 7),
(3, 'The Chronicle', 'THE-20260308-519', '2026-03-08', 'Doreenda Abbey', 'partial', NULL, NULL, NULL, 5, 1, 19),
(4, 'Ghanaian Times', 'GHA-20260308-923', '2026-03-08', 'Doreenda Abbey', 'available', NULL, NULL, NULL, 2, 1, 10);

-- --------------------------------------------------------

--
-- Table structure for table `newspaper_categories`
--

CREATE TABLE `newspaper_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `newspaper_categories`
--

INSERT INTO `newspaper_categories` (`id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Daily Graphic', NULL, '2026-03-07 18:32:15'),
(2, 'Ghanaian Times', NULL, '2026-03-07 18:32:15'),
(3, 'Business & Financial Times', NULL, '2026-03-07 18:32:15'),
(4, 'Daily Guide', NULL, '2026-03-07 18:32:15'),
(5, 'The Chronicle', NULL, '2026-03-07 18:32:15');

-- --------------------------------------------------------

--
-- Table structure for table `parcels_pickup`
--

CREATE TABLE `parcels_pickup` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) DEFAULT NULL,
  `picked_by` varchar(200) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `date_picked` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `parcels_pickup`
--

INSERT INTO `parcels_pickup` (`id`, `parcel_id`, `picked_by`, `phone_number`, `designation`, `date_picked`) VALUES
(1, 1, 'Doreenda Abbey', '0505306932', 'PVC', '2026-03-08');

-- --------------------------------------------------------

--
-- Table structure for table `parcels_received`
--

CREATE TABLE `parcels_received` (
  `id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `sender` varchar(200) DEFAULT NULL,
  `addressed_to` varchar(200) DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `tracking_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `parcels_received`
--

INSERT INTO `parcels_received` (`id`, `description`, `sender`, `addressed_to`, `date_received`, `received_by`, `tracking_id`) VALUES
(1, 'A gift', 'Hollali Kelvin', 'Mr Kelvin Hollali', '2026-03-07', 'Doreenda Abbey', 'PRCL-20260307-9B102A'),
(2, 'An Egonomic Chair', 'Archiver Asare', 'Mr Kelvin Hollali', '2026-03-08', 'Doreenda Abbey', 'PRCL-20260308-7D19FA');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `distribution`
--
ALTER TABLE `distribution`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_newspaper_id` (`newspaper_id`),
  ADD KEY `idx_date_distributed` (`date_distributed`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date_received` (`date_received`),
  ADD KEY `idx_document_name` (`document_name`),
  ADD KEY `fk_document_type` (`type_id`);

--
-- Indexes for table `document_distribution`
--
ALTER TABLE `document_distribution`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_type_name` (`type_name`);

--
-- Indexes for table `newspapers`
--
ALTER TABLE `newspapers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_newspaper_category` (`category_id`);

--
-- Indexes for table `newspaper_categories`
--
ALTER TABLE `newspaper_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcels_pickup`
--
ALTER TABLE `parcels_pickup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcels_received`
--
ALTER TABLE `parcels_received`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tracking_id` (`tracking_id`),
  ADD KEY `idx_date_received` (`date_received`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `distribution`
--
ALTER TABLE `distribution`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `document_distribution`
--
ALTER TABLE `document_distribution`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `newspapers`
--
ALTER TABLE `newspapers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `newspaper_categories`
--
ALTER TABLE `newspaper_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `parcels_pickup`
--
ALTER TABLE `parcels_pickup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `parcels_received`
--
ALTER TABLE `parcels_received`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `distribution`
--
ALTER TABLE `distribution`
  ADD CONSTRAINT `fk_distribution_newspaper` FOREIGN KEY (`newspaper_id`) REFERENCES `newspapers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_document_type` FOREIGN KEY (`type_id`) REFERENCES `document_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `newspapers`
--
ALTER TABLE `newspapers`
  ADD CONSTRAINT `fk_newspaper_category` FOREIGN KEY (`category_id`) REFERENCES `newspaper_categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;