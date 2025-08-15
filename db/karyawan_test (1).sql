-- phpMyAdmin SQL Dump
-- version 6.0.0-dev+20250718.d42db65a1e
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 15, 2025 at 05:38 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `absensi`
--

-- --------------------------------------------------------

--
-- Table structure for table `karyawan_test`
--

CREATE TABLE `karyawan_test` (
  `id` int NOT NULL,
  `pin` varchar(20) NOT NULL,
  `nip` varchar(50) NOT NULL,
  `bagian` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `karyawan_test`
--

INSERT INTO `karyawan_test` (`id`, `pin`, `nip`, `bagian`) VALUES
(5, '508', '291101000158', 'IT Maintenance'),
(6, '1', 'LMG-2015-001', 'Bahan Baku'),
(7, '2', 'LMG-2015-003', 'Cabut'),
(8, '3', 'LMG-2015-005', 'Cabut'),
(9, '4', 'LMG-2015-008', 'Bahan Baku'),
(10, '5', 'LMG-2015-012', 'Moulding'),
(11, '6', 'LMG-2015-013', 'Dry A'),
(12, '7', 'LMG-2015-014', 'Moulding'),
(13, '8', 'LMG-2015-015', 'Cabut'),
(14, '9', 'LMG-2016-016', 'Moulding'),
(15, '10', 'LMG-2016-017', 'Cabut'),
(16, '11', 'LMG-2016-019', 'Cabut'),
(17, '12', 'LMG-2016-022', 'Cabut'),
(18, '13', 'LMG-2016-022', 'Cabut'),
(19, '14', 'LMG-2017-023', 'Cuci Bersih'),
(20, '15', 'LMG-2017-027', 'Manager Produksi'),
(21, '16', 'LMG-2017-028', 'Bahan Baku'),
(22, '17', 'LMG-2017-030', 'Cabut'),
(23, '18', 'LMG-2017-032', 'Cuci Kotor'),
(24, '19', 'LMG-2017-034', 'Cuci Bersih'),
(25, '20', 'LMG-2017-035', 'Cabut');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `karyawan_test`
--
ALTER TABLE `karyawan_test`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `karyawan_test`
--
ALTER TABLE `karyawan_test`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
