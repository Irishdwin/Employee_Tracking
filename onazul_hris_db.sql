-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 19, 2026 at 04:38 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `onazul_hris_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `address_barangay_lookup`
--

CREATE TABLE `address_barangay_lookup` (
  `BarangayID` int(11) NOT NULL,
  `CityID` int(11) NOT NULL,
  `Name` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `address_barangay_lookup`
--

INSERT INTO `address_barangay_lookup` (`BarangayID`, `CityID`, `Name`) VALUES
(351, 1, 'Agusan'),
(352, 1, 'Baikingon'),
(354, 1, 'Balubal'),
(355, 1, 'Balulang'),
(422, 1, 'Barangay 1'),
(356, 1, 'Barangay 10'),
(357, 1, 'Barangay 11'),
(358, 1, 'Barangay 12'),
(359, 1, 'Barangay 13'),
(360, 1, 'Barangay 14'),
(361, 1, 'Barangay 15'),
(362, 1, 'Barangay 16'),
(363, 1, 'Barangay 17'),
(364, 1, 'Barangay 18'),
(365, 1, 'Barangay 19'),
(366, 1, 'Barangay 2'),
(424, 1, 'Barangay 20'),
(367, 1, 'Barangay 21'),
(368, 1, 'Barangay 22'),
(369, 1, 'Barangay 23'),
(370, 1, 'Barangay 24'),
(425, 1, 'Barangay 25'),
(371, 1, 'Barangay 26'),
(372, 1, 'Barangay 27'),
(373, 1, 'Barangay 28'),
(426, 1, 'Barangay 29'),
(374, 1, 'Barangay 3'),
(375, 1, 'Barangay 30'),
(427, 1, 'Barangay 31'),
(376, 1, 'Barangay 32'),
(377, 1, 'Barangay 33'),
(378, 1, 'Barangay 34'),
(428, 1, 'Barangay 35'),
(429, 1, 'Barangay 36'),
(430, 1, 'Barangay 37'),
(379, 1, 'Barangay 38'),
(380, 1, 'Barangay 39'),
(381, 1, 'Barangay 4'),
(382, 1, 'Barangay 40'),
(386, 1, 'Barangay 5'),
(383, 1, 'Barangay 6'),
(423, 1, 'Barangay 7'),
(384, 1, 'Barangay 8'),
(385, 1, 'Barangay 9'),
(387, 1, 'Bayabas'),
(388, 1, 'Bayanga'),
(389, 1, 'Besigan'),
(390, 1, 'Bonbon'),
(391, 1, 'Bugo'),
(353, 1, 'Bulua'),
(392, 1, 'Camaman-an'),
(393, 1, 'Canito-an'),
(394, 1, 'Carmen'),
(395, 1, 'Consolacion'),
(396, 1, 'Cugman'),
(397, 1, 'Dansolihon'),
(398, 1, 'F. S. Catanico'),
(399, 1, 'Gusa'),
(400, 1, 'Indahag'),
(401, 1, 'Iponan'),
(402, 1, 'Kauswagan'),
(403, 1, 'Lapasan'),
(404, 1, 'Lumbia'),
(405, 1, 'Macabalan'),
(406, 1, 'Macasandig'),
(407, 1, 'Mambuaya'),
(408, 1, 'Nazareth'),
(409, 1, 'Pagalungan'),
(410, 1, 'Pagatpat'),
(411, 1, 'Patag'),
(412, 1, 'Pigsag-an'),
(413, 1, 'Puerto'),
(414, 1, 'Puntod'),
(415, 1, 'San Simon'),
(416, 1, 'Tablon'),
(417, 1, 'Taglimao'),
(418, 1, 'Tagpangi'),
(419, 1, 'Tignapoloan'),
(420, 1, 'Tuburan'),
(421, 1, 'Tumpagon'),
(431, 2, 'Amoros'),
(432, 2, 'Bolisong'),
(433, 2, 'Bolobolo'),
(434, 2, 'Calongonan'),
(435, 2, 'Cogon'),
(436, 2, 'Himaya'),
(437, 2, 'Hinigdaan'),
(438, 2, 'Kalabaylabay'),
(441, 2, 'Kibonbon'),
(439, 2, 'Molugan'),
(440, 2, 'Poblacion'),
(442, 2, 'Sambulawan'),
(443, 2, 'Sinaloc'),
(444, 2, 'Taytay'),
(445, 2, 'Ulaliman'),
(446, 3, 'Agay-ayan'),
(447, 3, 'Alagatan'),
(448, 3, 'Anakan'),
(449, 3, 'Bagubad'),
(450, 3, 'Bakidbakid'),
(451, 3, 'Bal-ason'),
(452, 3, 'Bantaawan'),
(477, 3, 'Barangay 1'),
(478, 3, 'Barangay 10'),
(479, 3, 'Barangay 11'),
(480, 3, 'Barangay 12'),
(481, 3, 'Barangay 13'),
(482, 3, 'Barangay 14'),
(483, 3, 'Barangay 15'),
(484, 3, 'Barangay 16'),
(485, 3, 'Barangay 17'),
(514, 3, 'Barangay 18'),
(486, 3, 'Barangay 18-A'),
(487, 3, 'Barangay 19'),
(488, 3, 'Barangay 2'),
(489, 3, 'Barangay 20'),
(490, 3, 'Barangay 21'),
(515, 3, 'Barangay 22'),
(491, 3, 'Barangay 22-A'),
(492, 3, 'Barangay 23'),
(493, 3, 'Barangay 24'),
(516, 3, 'Barangay 24-A'),
(494, 3, 'Barangay 25'),
(495, 3, 'Barangay 26'),
(496, 3, 'Barangay 3'),
(497, 3, 'Barangay 4'),
(498, 3, 'Barangay 5'),
(499, 3, 'Barangay 6'),
(500, 3, 'Barangay 7'),
(501, 3, 'Barangay 8'),
(502, 3, 'Barangay 9'),
(453, 3, 'Binakalan'),
(454, 3, 'Capitulangan'),
(455, 3, 'Daan-Lungsod'),
(517, 3, 'Dinawehan'),
(518, 3, 'Eureka'),
(456, 3, 'Hindangon'),
(457, 3, 'Kalagonoy'),
(519, 3, 'Kalipay'),
(520, 3, 'Kamanikan'),
(521, 3, 'Kianlagan'),
(458, 3, 'Kibuging'),
(459, 3, 'Kipuntos'),
(460, 3, 'Lawaan'),
(461, 3, 'Lawit'),
(462, 3, 'Libertad'),
(463, 3, 'Libon'),
(464, 3, 'Lunao'),
(465, 3, 'Lunotan'),
(466, 3, 'Malibud'),
(467, 3, 'Malinao'),
(468, 3, 'Maribucao'),
(470, 3, 'Mimbalagon'),
(471, 3, 'Mimbunga'),
(469, 3, 'Mimbuntong'),
(472, 3, 'Minsapinit'),
(473, 3, 'Murallon'),
(474, 3, 'Odiongan'),
(475, 3, 'Pangasihan'),
(476, 3, 'Pigsaluhan'),
(503, 3, 'Punong'),
(504, 3, 'Ricoro'),
(505, 3, 'Samay'),
(522, 3, 'San Jose'),
(506, 3, 'San Juan'),
(507, 3, 'San Luis'),
(508, 3, 'San Miguel'),
(523, 3, 'Sangalan'),
(509, 3, 'Santiago'),
(524, 3, 'Tagpako'),
(510, 3, 'Talisay'),
(511, 3, 'Talon'),
(512, 3, 'Tinabalan'),
(513, 3, 'Tinulongan'),
(11, 4, 'Aglayan'),
(12, 4, 'Busdi'),
(13, 4, 'Casisang'),
(14, 5, 'Bagontaas'),
(15, 5, 'Lurugan'),
(16, 5, 'Poblacion'),
(17, 6, 'Buhangin'),
(19, 6, 'Poblacion District'),
(18, 6, 'Talomo'),
(20, 7, 'Aplaya'),
(21, 7, 'Cogon'),
(22, 7, 'Tres de Mayo');

-- --------------------------------------------------------

--
-- Table structure for table `address_city_lookup`
--

CREATE TABLE `address_city_lookup` (
  `CityID` int(11) NOT NULL,
  `ProvinceID` int(11) NOT NULL,
  `Name` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `address_city_lookup`
--

INSERT INTO `address_city_lookup` (`CityID`, `ProvinceID`, `Name`) VALUES
(3, 1, 'Balingasag'),
(1, 1, 'Cagayan de Oro City'),
(2, 1, 'Tagoloan'),
(4, 2, 'Malaybalay City'),
(5, 2, 'Valencia City'),
(6, 3, 'Davao City'),
(7, 3, 'Digos City');

-- --------------------------------------------------------

--
-- Table structure for table `address_country_lookup`
--

CREATE TABLE `address_country_lookup` (
  `CountryID` int(11) NOT NULL,
  `Name` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `address_country_lookup`
--

INSERT INTO `address_country_lookup` (`CountryID`, `Name`) VALUES
(1, 'Philippines');

-- --------------------------------------------------------

--
-- Table structure for table `address_province_lookup`
--

CREATE TABLE `address_province_lookup` (
  `ProvinceID` int(11) NOT NULL,
  `CountryID` int(11) NOT NULL,
  `Name` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `address_province_lookup`
--

INSERT INTO `address_province_lookup` (`ProvinceID`, `CountryID`, `Name`) VALUES
(2, 1, 'Bukidnon'),
(3, 1, 'Davao del Sur'),
(1, 1, 'Misamis Oriental');

-- --------------------------------------------------------

--
-- Table structure for table `applicantdocuments`
--

CREATE TABLE `applicantdocuments` (
  `DocumentID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `DocumentationType` varchar(100) DEFAULT NULL,
  `DocumentPath` varchar(255) DEFAULT NULL,
  `UploadedDate` datetime DEFAULT current_timestamp(),
  `VerifiedBy` int(11) DEFAULT NULL,
  `VerifiedDate` datetime DEFAULT NULL,
  `IsVerified` tinyint(1) DEFAULT 0,
  `ExpirationDate` date DEFAULT NULL,
  `StatusID` int(11) DEFAULT NULL,
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `ApplicantID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `FullName` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `MobileNumber` varchar(20) DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `BirthDate` date DEFAULT NULL,
  `Gender` varchar(20) DEFAULT NULL,
  `ProfilePhotoPath` varchar(255) DEFAULT NULL,
  `ProfileCompleted` tinyint(1) DEFAULT 0,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `FirstName` varchar(50) DEFAULT NULL,
  `LastName` varchar(50) DEFAULT NULL,
  `HomeLocation` varchar(150) DEFAULT NULL,
  `HasRecentExperience` tinyint(1) DEFAULT 0,
  `JobTitle` varchar(150) DEFAULT NULL,
  `CompanyName` varchar(150) DEFAULT NULL,
  `ExperienceStartMonth` int(11) DEFAULT NULL,
  `ExperienceStartYear` int(11) DEFAULT NULL,
  `ExperienceEndMonth` int(11) DEFAULT NULL,
  `ExperienceEndYear` int(11) DEFAULT NULL,
  `StillInRole` tinyint(1) DEFAULT 0,
  `PreferredClassification` varchar(100) DEFAULT NULL,
  `PreferredSubClassification` varchar(100) DEFAULT NULL,
  `ProfileVisibility` varchar(20) DEFAULT NULL,
  `ProfileDetailsCompleted` tinyint(1) DEFAULT 0,
  `ProfileVisibilityCompleted` tinyint(1) DEFAULT 0,
  `RegistrationTermsAccepted` tinyint(1) DEFAULT 0,
  `RegistrationTermsAcceptedAt` datetime DEFAULT NULL,
  `ProfileStatus` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=new,1=terms accepted,2=profile setup complete',
  `JobCategory` varchar(255) DEFAULT NULL,
  `StreetAddress` varchar(255) DEFAULT NULL,
  `City` varchar(120) DEFAULT NULL,
  `StateProvince` varchar(120) DEFAULT NULL,
  `PostalCode` varchar(40) DEFAULT NULL,
  `Country` varchar(120) DEFAULT NULL,
  `Barangay` varchar(120) DEFAULT NULL,
  `MiddleName` varchar(50) DEFAULT NULL,
  `Suffix` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`ApplicantID`, `UserID`, `FullName`, `Email`, `MobileNumber`, `Address`, `BirthDate`, `Gender`, `ProfilePhotoPath`, `ProfileCompleted`, `CreatedAt`, `FirstName`, `LastName`, `HomeLocation`, `HasRecentExperience`, `JobTitle`, `CompanyName`, `ExperienceStartMonth`, `ExperienceStartYear`, `ExperienceEndMonth`, `ExperienceEndYear`, `StillInRole`, `PreferredClassification`, `PreferredSubClassification`, `ProfileVisibility`, `ProfileDetailsCompleted`, `ProfileVisibilityCompleted`, `RegistrationTermsAccepted`, `RegistrationTermsAcceptedAt`, `ProfileStatus`, `JobCategory`, `StreetAddress`, `City`, `StateProvince`, `PostalCode`, `Country`, `Barangay`, `MiddleName`, `Suffix`) VALUES
(1, 5, 'Applicant User', 'applicant@onazul.com', NULL, NULL, NULL, NULL, NULL, 0, '2026-02-21 01:05:49', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 7, 'Irina Gamurio', 'irinagamurio@gmail.com', '09234966660', 'Phase 2, Indahag, City of Cagayan De Oro, Misamis Oriental 9000, Philippines', '0200-01-21', NULL, NULL, 1, '2026-03-24 12:06:30', 'Irina', 'Gamurio', 'Indahag, City of Cagayan De Oro, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Cashier', 'Cashier', 'Public', 1, 1, 0, NULL, 2, 'Retail & Sales', 'Phase 2', 'City of Cagayan De Oro', 'Misamis Oriental', '9000', 'Philippines', 'Indahag', NULL, NULL),
(3, 9, 'Irishdwin Gamutan', 'irishdwin143@gmail.com', '09234966660', 'Cagayan de Oro City', '2004-02-15', NULL, NULL, 1, '2026-03-26 14:20:49', 'Irishdwin', 'Gamutan', 'Cagayan de Oro City', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'IT', 'IT Support', 'Public', 1, 1, 0, NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 10, 'Irishdwin Gamutan', 'gamutan.irishdwin04@gmail.com', '09853252944', 'Cagayan de Oro City', '2004-02-10', NULL, NULL, 1, '2026-03-29 13:27:38', 'Irishdwin', 'Gamutan', 'Cagayan de Oro City', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'IT', 'Software Developer', 'Public', 1, 1, 1, '2026-03-29 15:16:29', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 11, 'Irishdwin Gamutan', 'tomntoms28@gmail.com', NULL, 'Cagayan de Oro City', '2000-02-02', NULL, NULL, 1, '2026-03-29 15:32:10', 'Irishdwin', 'Gamutan', 'Cagayan de Oro City', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'IT', 'Software Developer', 'Public', 1, 1, 1, '2026-03-29 15:32:13', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 14, 'Selena Gomez', 'selenamain811@gmail.com', NULL, 'Cagayan de Oro City', '2000-02-21', NULL, NULL, 1, '2026-03-30 12:34:40', 'Selena', 'Gomez', 'Cagayan de Oro City', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Other', 'Other', 'Public', 1, 1, 1, '2026-03-30 12:34:43', 2, 'Retail & Sales', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 15, 'Sherrish Mae', 'hayabusa.main.28@gmail.com', NULL, 'Cagayan de Oro City', '2000-02-21', NULL, NULL, 1, '2026-03-30 15:28:40', 'Sherrish', 'Mae', 'Cagayan de Oro City', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Other', 'Other', 'Public', 1, 1, 1, '2026-03-30 15:28:45', 2, 'Security & Protective Services', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 20, 'Karina Sanchez', 'khulas812@gmail.com', '09234966660', NULL, '2000-02-15', NULL, NULL, 1, '2026-04-25 14:29:33', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, 0, 1, '2026-04-25 14:30:44', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 25, 'Irishdwin Natumag', 'irishdwin28@gmail.com', NULL, 'phase 1, Indahag, City of Cagayan De Oro, Misamis Oriental 9000, Philippines', '2000-02-10', NULL, NULL, 1, '2026-06-10 21:58:59', 'Irishdwin', 'Natumag', 'Indahag, City of Cagayan De Oro, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Cashier', 'Retail Cashier', 'Public', 1, 1, 1, '2026-06-10 21:59:05', 2, 'Retail & Sales', 'phase 1', 'City of Cagayan De Oro', 'Misamis Oriental', '9000', 'Philippines', 'Indahag', NULL, NULL),
(10, 26, 'Chevy Camaro', 'c4471892@gmail.com', NULL, 'Phase 2, Indahag, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-02-12', NULL, NULL, 1, '2026-06-30 19:19:28', 'Chevy', 'Camaro', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Security & Safety', 'Security Guard', 'Public', 1, 1, 1, '2026-06-30 19:19:34', 2, 'Food & Beverage / Hospitality', 'Phase 2', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Indahag', NULL, NULL),
(11, 27, 'Nicolas Jhonson', 'gmailtest010203@gmail.com', '09234966660', 'Phase 2, Indahag, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-12-12', NULL, NULL, 1, '2026-07-02 11:33:22', 'Nicolas', 'Jhonson', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Food & Beverage', 'Kitchen Staff', 'Hired', 1, 1, 1, '2026-07-02 11:34:10', 2, 'Security & Protective Services', 'Phase 2', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Indahag', NULL, NULL),
(12, 28, 'Jack Dawson', 'merryjoy010203@gmail.com', '09234966660', 'purok 2, Indahag, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-03-15', NULL, NULL, 1, '2026-07-02 23:06:43', 'Jack', 'Dawson', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Retail & Sales', 'Cashier', 'Public', 1, 1, 1, '2026-07-02 23:07:13', 2, 'Food & Beverage / Hospitality', 'purok 2', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Indahag', NULL, NULL),
(13, 29, 'Toyota Supra', 'dumpnavs0@gmail.com', '09277273669', 'Zone 5, Carmen, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-09-28', NULL, NULL, 1, '2026-07-06 22:05:55', 'Toyota', 'Supra', 'Carmen, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Logistics & Warehouse', 'Delivery Rider', 'Hired', 1, 1, 1, '2026-07-06 22:06:38', 2, 'Logistics, Warehousing & Driving', 'Zone 5', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Carmen', NULL, NULL),
(14, 31, 'Mariane Mildred', 'caelmax30@gmail.com', '09234966660', 'Zone 3, Carmen, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-09-26', NULL, NULL, 1, '2026-07-08 16:56:06', 'Mariane', 'Mildred', 'Carmen, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Retail & Sales', 'Sales Clerk', 'Hired', 1, 1, 1, '2026-07-08 16:57:25', 2, 'sales', 'Zone 3', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Carmen', NULL, NULL),
(15, 32, 'Chevrolet Camarro', 'marcar8879@gmail.com', '09853252944', '12th street, Nazareth, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-12-12', NULL, NULL, 1, '2026-07-23 23:30:03', 'Chevrolet', 'Camarro', 'Nazareth, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Retail & Sales', 'Sales Clerk', 'Hired', 1, 1, 1, '2026-07-23 23:30:50', 2, 'Retail & Sales', '12th street', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Nazareth', NULL, NULL),
(16, 35, 'Emmanuel Cruz', 'amille.5437@gmail.com', '09234966660', 'tibasak, Macasandig, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-07-24', NULL, NULL, 1, '2026-07-30 21:27:34', 'Emmanuel', 'Cruz', 'Macasandig, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Retail & Sales', 'Promodiser', 'Hired', 1, 1, 1, '2026-07-30 22:25:10', 2, 'sales', 'tibasak', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Macasandig', NULL, NULL),
(17, 37, 'Style91998', 'Style91998@gmail.com', '09234966660', NULL, NULL, NULL, NULL, 0, '2026-08-05 11:01:43', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 38, 'Cammille Roxas', 'carsmar781@gmail.com', '09234966660', '12 Street, Nazareth, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-02-02', NULL, NULL, 1, '2026-08-05 11:05:54', 'Cammille', 'Roxas', 'Nazareth, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Retail & Sales', 'Promodiser', 'Hired', 1, 1, 1, '2026-08-05 11:07:58', 2, 'Retail & Sales', '12 Street', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Nazareth', NULL, NULL),
(19, 40, 'F04615934', 'f04615934@gmail.com', '+639234966660', NULL, NULL, NULL, NULL, 0, '2026-08-06 11:01:22', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 41, 'Jerome Dela Cruz Jr.', 'july83767@gmail.com', '09234966660', 'Sitio Taguanao, Indahag, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-02-02', NULL, NULL, 1, '2026-08-06 11:04:29', 'Jerome', 'Cruz', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Retail & Sales', 'Promodiser', 'Hired', 1, 1, 1, '2026-08-06 11:05:04', 2, 'Retail & Sales', 'Sitio Taguanao', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Indahag', 'Dela', 'Jr.'),
(21, 42, 'Jasleralchua67', 'jasleralchua67@gmail.com', '+639853252945', NULL, NULL, NULL, NULL, 0, '2026-08-13 23:06:50', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, 0, 1, '2026-08-13 23:07:08', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 43, 'Aaron Dela Cruz X', 'unodostress0099@gmail.com', '09234966660', 'Zone1, Balubal, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-12-05', NULL, NULL, 1, '2026-08-13 23:11:22', 'Aaron', 'Cruz', 'Balubal, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Retail & Sales', 'Promodiser', 'Hired', 1, 1, 1, '2026-08-13 23:16:44', 2, 'Retail & Sales', 'Zone1', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Balubal', 'Dela', 'X'),
(23, 44, 'Andrew Dela Cruz Jr.', 'damnyoustark77@gmail.com', '09234966660', 'Phase 2, Indahag, Cagayan de Oro City, Misamis Oriental 9000, Philippines', '2000-02-21', NULL, NULL, 1, '2026-08-14 14:31:18', 'Andrew', 'Cruz', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Retail & Sales', 'Merchandiser', 'Hired', 1, 1, 1, '2026-08-14 14:31:59', 2, 'Retail & Sales', 'Phase 2', 'Cagayan de Oro City', 'Misamis Oriental', '9000', 'Philippines', 'Indahag', 'Dela', 'Jr.');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_profiles`
--

CREATE TABLE `applicant_profiles` (
  `UserID` int(11) NOT NULL,
  `FirstName` varchar(50) DEFAULT NULL,
  `LastName` varchar(50) DEFAULT NULL,
  `BirthDate` date DEFAULT NULL,
  `HomeLocation` varchar(150) DEFAULT NULL,
  `PreferredClassification` varchar(100) DEFAULT NULL,
  `PreferredSubClassification` varchar(100) DEFAULT NULL,
  `ProfileDetailsCompleted` tinyint(1) DEFAULT 0,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `preferred_category` varchar(100) DEFAULT NULL COMMENT 'Retail, Cashier, Accounting, Driver, etc.',
  `MiddleName` varchar(50) DEFAULT NULL,
  `Suffix` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicant_profiles`
--

INSERT INTO `applicant_profiles` (`UserID`, `FirstName`, `LastName`, `BirthDate`, `HomeLocation`, `PreferredClassification`, `PreferredSubClassification`, `ProfileDetailsCompleted`, `UpdatedAt`, `preferred_category`, `MiddleName`, `Suffix`) VALUES
(7, 'Irina', 'Gamurio', '0200-01-21', 'Indahag, City of Cagayan De Oro, Misamis Oriental, Philippines', 'Cashier', 'Cashier', 1, '2026-04-28 04:06:43', NULL, NULL, NULL),
(11, 'Irishdwin', 'Gamutan', '2000-02-02', 'Cagayan de Oro City', 'IT', 'Software Developer', 1, '2026-03-29 07:54:41', NULL, NULL, NULL),
(14, 'Selena', 'Gomez', '2000-02-21', 'Cagayan de Oro City', 'Other', 'Other', 1, '2026-03-30 04:35:24', NULL, NULL, NULL),
(15, 'Sherrish', 'Mae', '2000-02-21', 'Cagayan de Oro City', 'Other', 'Other', 1, '2026-03-30 07:31:35', NULL, NULL, NULL),
(25, 'Irishdwin', 'Natumag', '2000-02-10', 'Indahag, City of Cagayan De Oro, Misamis Oriental, Philippines', 'Cashier', 'Retail Cashier', 1, '2026-06-10 14:00:36', NULL, NULL, NULL),
(26, 'Chevy', 'Camaro', '2000-02-12', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 'Security & Safety', 'Security Guard', 1, '2026-06-30 14:01:58', NULL, NULL, NULL),
(27, 'Nicolas', 'Jhonson', '2000-12-12', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 'Food & Beverage', 'Kitchen Staff', 1, '2026-07-02 03:35:37', NULL, NULL, NULL),
(28, 'Jack', 'Dawson', '2000-03-15', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 'Retail & Sales', 'Cashier', 1, '2026-07-02 15:08:13', NULL, NULL, NULL),
(29, 'Toyota', 'Supra', '2000-09-28', 'Carmen, Cagayan de Oro City, Misamis Oriental, Philippines', 'Logistics & Warehouse', 'Delivery Rider', 1, '2026-07-06 14:07:46', NULL, NULL, NULL),
(31, 'Mariane', 'Mildred', '2000-09-26', 'Carmen, Cagayan de Oro City, Misamis Oriental, Philippines', 'Retail & Sales', 'Sales Clerk', 1, '2026-07-08 08:58:42', NULL, NULL, NULL),
(32, 'Chevrolet', 'Camarro', '2000-12-12', 'Nazareth, Cagayan de Oro City, Misamis Oriental, Philippines', 'Retail & Sales', 'Sales Clerk', 1, '2026-07-23 15:35:13', NULL, NULL, NULL),
(35, 'Emmanuel', 'Cruz', '2000-07-24', 'Macasandig, Cagayan de Oro City, Misamis Oriental, Philippines', 'Retail & Sales', 'Promodiser', 1, '2026-07-30 16:43:33', NULL, NULL, NULL),
(38, 'Cammille', 'Roxas', '2000-02-02', 'Nazareth, Cagayan de Oro City, Misamis Oriental, Philippines', 'Retail & Sales', 'Promodiser', 1, '2026-08-05 03:09:16', NULL, NULL, NULL),
(41, 'Jerome', 'Cruz', '2000-02-02', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 'Retail & Sales', 'Promodiser', 1, '2026-08-11 15:00:54', NULL, 'Dela', 'Jr.'),
(43, 'Aaron', 'Cruz', '2000-12-05', 'Balubal, Cagayan de Oro City, Misamis Oriental, Philippines', 'Retail & Sales', 'Promodiser', 1, '2026-08-13 16:06:34', NULL, 'Dela', 'X'),
(44, 'Andrew', 'Cruz', '2000-02-21', 'Indahag, Cagayan de Oro City, Misamis Oriental, Philippines', 'Retail & Sales', 'Merchandiser', 1, '2026-08-14 06:40:59', NULL, 'Dela', 'Jr.');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_uploaded_docs`
--

CREATE TABLE `applicant_uploaded_docs` (
  `DocID` int(11) NOT NULL,
  `ApplicationID` int(11) DEFAULT NULL,
  `ApplicantID` int(11) NOT NULL,
  `DocType` varchar(100) NOT NULL,
  `DocCategory` varchar(32) NOT NULL DEFAULT 'general',
  `FilePath` varchar(255) NOT NULL,
  `OriginalFileName` varchar(255) DEFAULT NULL,
  `FileSize` int(11) DEFAULT NULL,
  `IsVerified` tinyint(1) DEFAULT 0,
  `VerifiedBy` int(11) DEFAULT NULL,
  `VerifiedDate` datetime DEFAULT NULL,
  `UploadedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicant_uploaded_docs`
--

INSERT INTO `applicant_uploaded_docs` (`DocID`, `ApplicationID`, `ApplicantID`, `DocType`, `DocCategory`, `FilePath`, `OriginalFileName`, `FileSize`, `IsVerified`, `VerifiedBy`, `VerifiedDate`, `UploadedAt`) VALUES
(15, NULL, 1, 'Resume', 'general', '../../uploads/applicant_documents/doc_1_1774064700_69be143cee866.pdf', 'Grey White Aesthetic Fashion Designer Resume.pdf', 1761098, 0, NULL, NULL, '2026-03-21 11:45:00'),
(16, NULL, 1, 'Bio-data', 'general', '../../uploads/applicant_documents/doc_1_1774064739_69be1463b236f.pdf', 'Bio Data Doc in Denim Blue Grey Simple and Minimal Style.pdf', 1321552, 0, NULL, NULL, '2026-03-21 11:45:39'),
(17, NULL, 6, '656374496_1472735111533852_7351616698660452171_n', 'identity', '../../Uploads/Documents/doc_6_1774847004_69ca041cda6a79.45877522.jpg', '656374496_1472735111533852_7351616698660452171_n.jpg', 236064, 0, NULL, NULL, '2026-03-30 13:03:24'),
(18, NULL, 6, 'Resume', 'employment', '../../Uploads/Documents/doc_6_1774847018_69ca042ae23fd3.92365918.jpg', '656374496_1472735111533852_7351616698660452171_n.jpg', 236064, 0, NULL, NULL, '2026-03-30 13:03:38'),
(19, NULL, 6, 'Government ID', 'identity', '../../Uploads/Documents/doc_6_1774847027_69ca04334d2445.13412502.jpg', '658914557_1472735474867149_527320497625667341_n.jpg', 248151, 0, NULL, NULL, '2026-03-30 13:03:47'),
(20, NULL, 6, 'Certification', 'academic', '../../Uploads/Documents/doc_6_1774847033_69ca04393c75c4.87635283.jpg', '656374496_1472735111533852_7351616698660452171_n.jpg', 236064, 0, NULL, NULL, '2026-03-30 13:03:53'),
(21, NULL, 7, 'Resume', 'employment', '../../Uploads/Documents/doc_7_1774855887_69ca26cf30dc57.65575273.jpg', '656374496_1472735111533852_7351616698660452171_n.jpg', 236064, 0, NULL, NULL, '2026-03-30 15:31:27'),
(22, NULL, 7, 'Government ID', 'identity', '../../Uploads/Documents/doc_7_1774855890_69ca26d2e1e2a0.99343188.jpg', '658914557_1472735474867149_527320497625667341_n.jpg', 248151, 0, NULL, NULL, '2026-03-30 15:31:30'),
(23, NULL, 7, 'Certification', 'academic', '../../Uploads/Documents/doc_7_1774855894_69ca26d6800370.36442142.jpg', '656374496_1472735111533852_7351616698660452171_n.jpg', 236064, 0, NULL, NULL, '2026-03-30 15:31:34'),
(24, NULL, 7, 'Resume', 'employment', '../../Uploads/Documents/doc_7_1774857188_69ca2be4855669.21773381.jpg', '656374496_1472735111533852_7351616698660452171_n.jpg', 236064, 0, NULL, NULL, '2026-03-30 15:53:08'),
(25, NULL, 2, 'Resume', 'employment', '../../Uploads/Documents/doc_2_1777349004_69f0318c9da857.25323465.jpg', 'b558a208-8d77-4691-b368-5a9fc4abe8d7.jpg', 253934, 0, NULL, NULL, '2026-04-28 12:03:24'),
(26, NULL, 2, 'Government ID', 'identity', '../../Uploads/Documents/doc_2_1777349010_69f03192cba614.54073227.jpg', 'a6b860e9-b2de-445b-9375-a226ec34b1fb.jpg', 222411, 0, NULL, NULL, '2026-04-28 12:03:30'),
(27, NULL, 2, 'Certification', 'academic', '../../Uploads/Documents/doc_2_1777349029_69f031a5955850.28072068.jpg', 'f2885dfa-6c38-4ebc-8bee-fdda7778629b.jpg', 267355, 0, NULL, NULL, '2026-04-28 12:03:49'),
(28, NULL, 2, 'Resume', 'employment', '../../Uploads/Documents/doc_2_1777349068_69f031cc869d24.50667804.jpg', 'b558a208-8d77-4691-b368-5a9fc4abe8d7.jpg', 253934, 0, NULL, NULL, '2026-04-28 12:04:28'),
(29, NULL, 2, 'Government ID', 'identity', '../../Uploads/Documents/doc_2_1777349073_69f031d126df45.29965965.jpg', 'a6b860e9-b2de-445b-9375-a226ec34b1fb.jpg', 222411, 0, NULL, NULL, '2026-04-28 12:04:33'),
(30, NULL, 2, 'Certification', 'academic', '../../Uploads/Documents/doc_2_1777349077_69f031d5e07ef1.84971254.jpg', 'f2885dfa-6c38-4ebc-8bee-fdda7778629b.jpg', 267355, 0, NULL, NULL, '2026-04-28 12:04:37'),
(31, NULL, 9, 'Resume', 'employment', '../../Uploads/Documents/doc_9_1781100026_6a296dfa8c1987.18837016.jpg', '656374496_1472735111533852_7351616698660452171_n.jpg', 236064, 0, NULL, NULL, '2026-06-10 22:00:26'),
(32, NULL, 9, 'Government ID', 'identity', '../../Uploads/Documents/doc_9_1781100029_6a296dfde091b5.82181680.jpg', '657807805_1473654711441892_516076555343809583_n.jpg', 247548, 0, NULL, NULL, '2026-06-10 22:00:29'),
(33, NULL, 9, 'Certification', 'academic', '../../Uploads/Documents/doc_9_1781100033_6a296e01873bc6.92078169.jpg', '658914557_1472735474867149_527320497625667341_n.jpg', 248151, 0, NULL, NULL, '2026-06-10 22:00:33'),
(34, NULL, 10, 'Resume', 'employment', '../../Uploads/Documents/doc_10_1782828087_6a43cc375d8e16.54308916.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-06-30 22:01:27'),
(35, NULL, 10, 'Government ID', 'identity', '../../Uploads/Documents/doc_10_1782828091_6a43cc3b9ebbd6.21637927.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-06-30 22:01:31'),
(36, NULL, 10, 'Certification', 'academic', '../../Uploads/Documents/doc_10_1782828095_6a43cc3f4fbb34.02194517.png', 'download.png', 6697, 0, NULL, NULL, '2026-06-30 22:01:35'),
(37, NULL, 11, 'Resume', 'employment', '../../Uploads/Documents/doc_11_1782963324_6a45dc7cedd681.24677882.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-07-02 11:35:24'),
(38, NULL, 11, 'Government ID', 'identity', '../../Uploads/Documents/doc_11_1782963329_6a45dc81930772.85234060.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-07-02 11:35:29'),
(39, NULL, 11, 'Certification', 'academic', '../../Uploads/Documents/doc_11_1782963334_6a45dc861154c5.24371835.png', 'download.png', 6697, 0, NULL, NULL, '2026-07-02 11:35:34'),
(40, NULL, 12, 'Resume', 'employment', '../../Uploads/Documents/doc_12_1783004884_6a467ed42cb025.34779391.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-07-02 23:08:04'),
(41, NULL, 12, 'Government ID', 'identity', '../../Uploads/Documents/doc_12_1783004888_6a467ed805e0f0.41369555.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-07-02 23:08:08'),
(42, NULL, 12, 'Certification', 'academic', '../../Uploads/Documents/doc_12_1783004891_6a467edbee1eb1.96975493.png', 'download.png', 6697, 0, NULL, NULL, '2026-07-02 23:08:11'),
(43, NULL, 13, 'Resume', 'employment', '../../Uploads/Documents/doc_13_1783346855_6a4bb6a7b41973.36712639.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-07-06 22:07:35'),
(44, NULL, 13, 'Government ID', 'identity', '../../Uploads/Documents/doc_13_1783346860_6a4bb6ac4b9b70.85518850.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-07-06 22:07:40'),
(45, NULL, 13, 'Certification', 'academic', '../../Uploads/Documents/doc_13_1783346863_6a4bb6afe7a327.73120873.png', 'download.png', 6697, 0, NULL, NULL, '2026-07-06 22:07:43'),
(46, NULL, 14, 'Resume', 'employment', '../../Uploads/Documents/doc_14_1783501102_6a4e112e712f76.40184852.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-07-08 16:58:22'),
(47, NULL, 14, 'Government ID', 'identity', '../../Uploads/Documents/doc_14_1783501111_6a4e11373742f5.45531773.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-07-08 16:58:31'),
(48, NULL, 14, 'Certification', 'academic', '../../Uploads/Documents/doc_14_1783501120_6a4e114002c2d6.75770078.png', 'download.png', 6697, 0, NULL, NULL, '2026-07-08 16:58:40'),
(49, NULL, 15, 'Resume', 'employment', '../../Uploads/Documents/doc_15_1784820872_6a6234887eeeb6.70662048.jpg', '66bb257a-fb87-4565-ad84-8dc2289bcd8a.jpg', 117118, 0, NULL, NULL, '2026-07-23 23:34:32'),
(50, NULL, 15, 'Resume', 'employment', '../../Uploads/Documents/doc_15_1784820903_6a6234a7675ed9.74086361.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-07-23 23:35:03'),
(51, NULL, 15, 'Government ID', 'identity', '../../Uploads/Documents/doc_15_1784820908_6a6234aca250e0.92137618.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-07-23 23:35:08'),
(52, NULL, 15, 'Certification', 'academic', '../../Uploads/Documents/doc_15_1784820911_6a6234afdb7244.84013194.png', 'download.png', 6697, 0, NULL, NULL, '2026-07-23 23:35:11'),
(53, NULL, 16, 'Resume', 'employment', '../../Uploads/Documents/doc_16_1785429772_6a6b7f0c743799.89809989.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-07-31 00:42:52'),
(54, NULL, 16, 'Government ID', 'identity', '../../Uploads/Documents/doc_16_1785429777_6a6b7f116adfe1.78102737.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-07-31 00:42:57'),
(55, NULL, 16, 'Certification', 'academic', '../../Uploads/Documents/doc_16_1785429781_6a6b7f15779130.54979884.png', 'download.png', 6697, 0, NULL, NULL, '2026-07-31 00:43:01'),
(56, NULL, 18, 'Resume', 'employment', '../../Uploads/Documents/doc_18_1785899342_6a72a94e264783.75178250.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-08-05 11:09:02'),
(57, NULL, 18, 'Government ID', 'identity', '../../Uploads/Documents/doc_18_1785899346_6a72a952aeadd9.12617566.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-08-05 11:09:06'),
(58, NULL, 18, 'Certification', 'academic', '../../Uploads/Documents/doc_18_1785899351_6a72a957e952e3.86211291.png', 'download.png', 6697, 0, NULL, NULL, '2026-08-05 11:09:11'),
(59, NULL, 20, 'Resume', 'employment', '../../Uploads/Documents/doc_20_1786459319_6a7b34b71ea0c5.76988943.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-08-11 22:41:59'),
(60, NULL, 20, 'Government ID', 'identity', '../../Uploads/Documents/doc_20_1786459481_6a7b3559e75090.11071167.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-08-11 22:44:41'),
(61, NULL, 20, 'Certification', 'academic', '../../Uploads/Documents/doc_20_1786459619_91361874.png', 'download.png', 6697, 0, NULL, NULL, '2026-08-11 22:46:59'),
(63, NULL, 22, 'Resume', 'employment', '../../Uploads/Documents/doc_22_1786634290_6a7de032135e81.76868770.pdf', 'SERVICE-AGREEMENT-CONTRACT-DRAFT-DML.pdf', 1571487, 0, NULL, NULL, '2026-08-13 23:18:10'),
(64, NULL, 22, 'Resume', 'employment', '../../Uploads/Documents/doc_22_1786634290_f7c795bd.pdf', 'SERVICE-AGREEMENT-CONTRACT-DRAFT-DML.pdf', 1571487, 0, NULL, NULL, '2026-08-13 23:18:10'),
(65, NULL, 22, 'Government ID', 'identity', '../../Uploads/Documents/doc_22_1786634304_1466a008.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-08-13 23:18:24'),
(66, NULL, 22, 'Government ID', 'identity', '../../Uploads/Documents/doc_22_1786634304_6a7de040a60d89.21643137.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-08-13 23:18:24'),
(67, NULL, 22, 'Resume', 'employment', '../../Uploads/Documents/doc_22_1786634309_6a7de0452a59e5.93618534.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-08-13 23:18:29'),
(68, NULL, 22, 'Resume', 'employment', '../../Uploads/Documents/doc_22_1786634309_e33f1785.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-08-13 23:18:29'),
(69, NULL, 22, 'Certification', 'academic', '../../Uploads/Documents/doc_22_1786634313_6a7de049c91bd6.55136187.png', 'download.png', 6697, 0, NULL, NULL, '2026-08-13 23:18:33'),
(70, NULL, 22, 'Certification', 'academic', '../../Uploads/Documents/doc_22_1786634313_ccc81fdf.png', 'download.png', 6697, 0, NULL, NULL, '2026-08-13 23:18:33'),
(71, NULL, 23, 'Resume', 'employment', '../../Uploads/Documents/doc_23_1786689632_6a7eb860d0ec29.93370481.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-08-14 14:40:32'),
(72, NULL, 23, 'Resume', 'employment', '../../Uploads/Documents/doc_23_1786689632_98ce907b.jpg', 'download (1).jpg', 6727, 0, NULL, NULL, '2026-08-14 14:40:32'),
(73, NULL, 23, 'Government ID', 'identity', '../../Uploads/Documents/doc_23_1786689638_6a7eb866340b74.37207753.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-08-14 14:40:38'),
(74, NULL, 23, 'Government ID', 'identity', '../../Uploads/Documents/doc_23_1786689638_52baf669.jpg', 'download.jpg', 7725, 0, NULL, NULL, '2026-08-14 14:40:38'),
(75, NULL, 23, 'Certification', 'academic', '../../Uploads/Documents/doc_23_1786689642_6a7eb86a676a38.83155179.png', 'download.png', 6697, 0, NULL, NULL, '2026-08-14 14:40:42'),
(76, NULL, 23, 'Certification', 'academic', '../../Uploads/Documents/doc_23_1786689642_97f9c5c4.png', 'download.png', 6697, 0, NULL, NULL, '2026-08-14 14:40:42');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `AttendanceID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `Date` date NOT NULL,
  `TimeIn` datetime DEFAULT NULL,
  `TimeOut` datetime DEFAULT NULL,
  `clockin_latitude` decimal(10,8) DEFAULT NULL,
  `clockin_longitude` decimal(11,8) DEFAULT NULL,
  `clockout_latitude` decimal(10,8) DEFAULT NULL,
  `clockout_longitude` decimal(10,8) DEFAULT NULL,
  `gps_accuracy` decimal(8,2) DEFAULT NULL,
  `distance_from_site` decimal(8,2) DEFAULT NULL,
  `clockin_photo` varchar(255) DEFAULT NULL,
  `clockout_photo` varchar(255) DEFAULT NULL,
  `browser_name` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `geofence_status` varchar(50) DEFAULT 'Inside Geofence',
  `StatusID` int(11) DEFAULT NULL,
  `TotalHours` decimal(5,2) DEFAULT NULL,
  `DTRSubmissionID` int(11) DEFAULT NULL,
  `BreakStart` datetime DEFAULT NULL,
  `BreakEnd` datetime DEFAULT NULL,
  `LateMinutes` int(11) DEFAULT 0,
  `UndertimeMinutes` int(11) DEFAULT 0,
  `IsRestDay` tinyint(1) DEFAULT 0,
  `HolidayID` int(11) DEFAULT NULL,
  `IsHolidayWork` tinyint(1) DEFAULT 0,
  `HolidayPayRate` decimal(4,2) DEFAULT NULL,
  `OvertimeHours` decimal(5,2) DEFAULT NULL,
  `OvertimePayRate` decimal(4,2) DEFAULT NULL,
  `ApprovedDate` datetime DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `Remarks` text DEFAULT NULL,
  `attendance_status` varchar(50) DEFAULT 'Present',
  `scheduled_time_in` time DEFAULT NULL,
  `scheduled_time_out` time DEFAULT NULL,
  `minutes_late` int(11) DEFAULT 0,
  `server_timestamp` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`AttendanceID`, `EmployeeID`, `Date`, `TimeIn`, `TimeOut`, `clockin_latitude`, `clockin_longitude`, `clockout_latitude`, `clockout_longitude`, `gps_accuracy`, `distance_from_site`, `clockin_photo`, `clockout_photo`, `browser_name`, `operating_system`, `device_type`, `user_agent`, `geofence_status`, `StatusID`, `TotalHours`, `DTRSubmissionID`, `BreakStart`, `BreakEnd`, `LateMinutes`, `UndertimeMinutes`, `IsRestDay`, `HolidayID`, `IsHolidayWork`, `HolidayPayRate`, `OvertimeHours`, `OvertimePayRate`, `ApprovedDate`, `ApprovedBy`, `Remarks`, `attendance_status`, `scheduled_time_in`, `scheduled_time_out`, `minutes_late`, `server_timestamp`) VALUES
(1, 12, '2026-08-14', '2026-08-14 02:51:09', NULL, 8.43153773, 124.65716642, NULL, NULL, 127.00, 14.12, 'uploads/attendance_photos/clock_in_32_1786668669_6a7e667d99138.jpg', NULL, 'Chrome', 'Windows 10/11', 'Desktop', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'Inside Geofence', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Present', NULL, NULL, 0, NULL),
(2, 13, '2026-08-14', '2026-08-14 10:09:36', NULL, 8.43150249, 124.65704750, NULL, NULL, 182.00, 0.47, 'uploads/attendance_photos/clock_in_43_1786673376_6a7e78e0ac9fe.jpg', NULL, 'Chrome', 'Windows 10/11', 'Desktop', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'Inside Geofence', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Late', '08:00:00', '00:00:17', 130, '0000-00-00 00:00:00'),
(3, 14, '2026-08-17', '2026-08-17 23:01:20', NULL, 8.43151214, 124.65712755, NULL, NULL, 116.00, 21.48, 'uploads/attendance_photos/clock_in_44_1786978880_6a83224043cb6.jpg', NULL, 'Chrome', 'Windows 10/11', 'Desktop', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'Inside Geofence', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Present', NULL, NULL, 0, NULL),
(4, 13, '2026-08-18', '2026-08-18 09:51:07', '2026-08-18 12:00:59', 8.43143096, 124.65701938, 8.43149100, 99.99999999, 132.00, 0.00, 'uploads/attendance_photos/clock_in_43_1787017867_6a83ba8b3e4f6.jpg', '0', 'Chrome', 'Windows 10/11', 'Desktop', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'Inside Geofence', NULL, 2.16, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, 111, NULL),
(5, 14, '2026-08-14', '2026-08-14 08:00:00', '2026-08-14 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 8.00, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Andrew Cruz', 'Present', '08:00:00', '17:00:00', 0, '2026-08-14 17:00:00'),
(6, 14, '2026-08-15', '2026-08-15 08:00:00', '2026-08-15 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 8.00, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Andrew Cruz', 'Present', '08:00:00', '17:00:00', 0, '2026-08-15 17:00:00'),
(7, 14, '2026-08-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0, 0, 1, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Rest Day - Test DTR', 'Rest Day', '08:00:00', '17:00:00', 0, '2026-08-16 00:00:00'),
(8, 14, '2026-08-17', '2026-08-17 08:00:00', '2026-08-17 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 8.00, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Andrew Cruz', 'Present', '08:00:00', '17:00:00', 0, '2026-08-17 17:00:00'),
(9, 14, '2026-08-18', '2026-08-18 08:15:00', '2026-08-18 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 7.75, NULL, NULL, NULL, 15, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Late 15 minutes', 'Late', '08:00:00', '17:00:00', 15, '2026-08-18 17:00:00'),
(10, 14, '2026-08-19', '2026-08-19 08:10:00', '2026-08-19 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 7.83, NULL, NULL, NULL, 10, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Late 10 minutes', 'Late', '08:00:00', '17:00:00', 10, '2026-08-19 17:00:00'),
(11, 14, '2026-08-20', '2026-08-20 08:00:00', '2026-08-20 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 8.00, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Andrew Cruz', 'Present', '08:00:00', '17:00:00', 0, '2026-08-20 17:00:00'),
(12, 14, '2026-08-21', '2026-08-21 08:00:00', '2026-08-21 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 8.00, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Andrew Cruz', 'Present', '08:00:00', '17:00:00', 0, '2026-08-21 17:00:00'),
(13, 14, '2026-08-22', '2026-08-22 08:00:00', '2026-08-22 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 8.00, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Andrew Cruz', 'Present', '08:00:00', '17:00:00', 0, '2026-08-22 17:00:00'),
(14, 14, '2026-08-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0, 0, 1, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Rest Day - Test DTR', 'Rest Day', '08:00:00', '17:00:00', 0, '2026-08-23 00:00:00'),
(15, 14, '2026-08-24', '2026-08-24 08:00:00', '2026-08-24 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 8.00, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Andrew Cruz', 'Present', '08:00:00', '17:00:00', 0, '2026-08-24 17:00:00'),
(16, 14, '2026-08-25', '2026-08-25 08:20:00', '2026-08-25 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 7.67, NULL, NULL, NULL, 20, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Late 20 minutes', 'Late', '08:00:00', '17:00:00', 20, '2026-08-25 17:00:00'),
(17, 14, '2026-08-26', '2026-08-26 08:00:00', '2026-08-26 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 8.00, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Andrew Cruz', 'Present', '08:00:00', '17:00:00', 0, '2026-08-26 17:00:00'),
(18, 14, '2026-08-27', '2026-08-27 08:00:00', '2026-08-27 16:30:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 7.50, NULL, NULL, NULL, 0, 30, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Undertime 30 minutes', 'Undertime', '08:00:00', '17:00:00', 0, '2026-08-27 16:30:00'),
(19, 14, '2026-08-28', '2026-08-28 08:00:00', '2026-08-28 17:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Inside Geofence', NULL, 8.00, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, 0.00, NULL, NULL, NULL, 'Test DTR - Andrew Cruz', 'Present', '08:00:00', '17:00:00', 0, '2026-08-28 17:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `bonus`
--

CREATE TABLE `bonus` (
  `BonusID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `Payroll_ID` int(11) DEFAULT NULL,
  `BonusType` varchar(50) DEFAULT NULL,
  `Amount` decimal(12,2) DEFAULT NULL,
  `StatusID` int(11) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashadvance`
--

CREATE TABLE `cashadvance` (
  `CashAdvanceID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `RequestDate` date DEFAULT NULL,
  `Amount` decimal(10,2) DEFAULT NULL,
  `Purpose` text DEFAULT NULL,
  `Approval` varchar(50) DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `ApprovedDate` datetime DEFAULT NULL,
  `TotalAmount` decimal(10,2) DEFAULT NULL,
  `RemainingBalance` decimal(10,2) DEFAULT NULL,
  `InstallmentAmount` decimal(10,2) DEFAULT NULL,
  `InstallmentCount` int(11) DEFAULT 0,
  `PaidInstallments` int(11) DEFAULT 0,
  `StartDeductionDate` date DEFAULT NULL,
  `Payment` varchar(50) DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `CreatedDate` datetime DEFAULT current_timestamp(),
  `Payroll_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashadvancededuction`
--

CREATE TABLE `cashadvancededuction` (
  `DeductionID` int(11) NOT NULL,
  `CashAdvanceID` int(11) NOT NULL,
  `Payroll_ID` int(11) NOT NULL,
  `DeductionAmount` decimal(10,2) DEFAULT NULL,
  `DeductionDate` date DEFAULT NULL,
  `RemainingBalanceAfter` decimal(10,2) DEFAULT NULL,
  `PayrollPeriod` varchar(50) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `MessageID` int(11) NOT NULL,
  `ThreadID` int(11) NOT NULL,
  `SenderUserID` int(11) NOT NULL,
  `MessageText` text NOT NULL,
  `IsSystemMessage` tinyint(1) DEFAULT 0,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`MessageID`, `ThreadID`, `SenderUserID`, `MessageText`, `IsSystemMessage`, `CreatedAt`) VALUES
(1, 1, 1, 'Interview scheduled for your application on 2026-03-30 09:00.', 1, '2026-03-23 22:58:37'),
(2, 1, 1, 'Interview scheduled for your application on 2026-03-30 09:00.', 1, '2026-03-23 22:59:08'),
(3, 1, 2, 'Application status updated to: For Interview.', 1, '2026-03-23 22:59:44'),
(4, 1, 2, 'Application status updated to: For Interview.', 1, '2026-03-23 22:59:58'),
(5, 1, 2, 'Application has been endorsed to the client for review.', 1, '2026-03-23 23:00:15'),
(6, 2, 2, 'Application status updated to: For Interview.', 1, '2026-03-29 23:47:05'),
(7, 2, 2, 'Interview scheduled for your application on 2026-03-30 09:00.', 1, '2026-03-29 23:47:25'),
(8, 2, 2, 'Application has been endorsed to the client for review.', 1, '2026-03-29 23:48:23'),
(9, 3, 1, 'Interview scheduled for your application on 2026-03-31 09:00.', 1, '2026-03-30 14:09:49'),
(10, 3, 1, 'Application status updated to: For Interview.', 1, '2026-03-30 14:10:16'),
(11, 3, 1, 'Application has been endorsed to the client for review.', 1, '2026-03-30 14:10:34'),
(12, 4, 1, 'Interview scheduled for your application on 2026-04-21 09:00.', 1, '2026-03-30 16:25:38'),
(13, 4, 1, 'Application status updated to: For Interview.', 1, '2026-03-30 16:25:57'),
(14, 4, 1, 'Application has been endorsed to the client for review.', 1, '2026-03-30 16:26:21'),
(15, 5, 1, 'Interview scheduled for your application on 2026-04-23 10:00.', 1, '2026-03-30 16:28:03'),
(16, 5, 1, 'Application status updated to: For Interview.', 1, '2026-03-30 16:28:25'),
(17, 5, 1, 'Application status updated to: For Interview.', 1, '2026-03-30 16:28:55'),
(18, 5, 1, 'Application has been endorsed to the client for review.', 1, '2026-03-30 16:29:04'),
(19, 6, 1, 'Interview scheduled for your application on 2026-03-31 09:00.', 1, '2026-03-30 16:52:02'),
(20, 6, 1, 'Application status updated to: For Interview.', 1, '2026-03-30 16:53:12'),
(21, 6, 1, 'Application status updated to: For Interview.', 1, '2026-03-30 16:54:22'),
(22, 6, 1, 'Application has been endorsed to the client for review.', 1, '2026-03-30 16:55:26'),
(23, 5, 1, 'Interview scheduled for your application on 2026-04-29 09:00.', 1, '2026-04-28 10:22:35'),
(24, 5, 1, 'Application status updated to: For Interview.', 1, '2026-04-28 10:24:19'),
(25, 5, 1, 'Application has been endorsed to the client for review.', 1, '2026-04-28 10:25:08'),
(26, 7, 1, 'Interview scheduled for your application on 2026-04-29 09:00.', 1, '2026-04-28 10:25:57'),
(27, 7, 1, 'Application status updated to: For Interview.', 1, '2026-04-28 10:26:25'),
(28, 7, 1, 'Application has been endorsed to the client for review.', 1, '2026-04-28 10:27:00'),
(29, 7, 1, 'Application status updated to: For Interview.', 1, '2026-04-28 10:27:53'),
(30, 7, 1, 'Interview scheduled for your application on 2026-04-29 10:00.', 1, '2026-04-28 10:30:38'),
(31, 8, 1, 'Interview scheduled for your application on 2026-04-29 16:00.', 1, '2026-04-28 10:31:03'),
(32, 8, 1, 'Application status updated to: For Interview.', 1, '2026-04-28 10:31:31'),
(33, 8, 1, 'Application status updated to: For Interview.', 1, '2026-04-28 10:33:20'),
(34, 8, 1, 'Application has been endorsed to the client for review.', 1, '2026-04-28 10:33:52'),
(35, 9, 1, 'Interview scheduled for your application on 2026-04-29 10:00.', 1, '2026-04-28 12:07:29'),
(36, 9, 1, 'Application status updated to: For Interview.', 1, '2026-04-28 12:08:59'),
(37, 9, 1, 'Application has been endorsed to the client for review.', 1, '2026-04-28 12:12:43'),
(38, 9, 1, 'Application has been endorsed to the client for review.', 1, '2026-04-28 12:14:24'),
(39, 10, 1, 'Hello', 0, '2026-06-03 22:25:16'),
(40, 10, 2, 'hi', 0, '2026-06-03 23:10:15'),
(41, 10, 1, 'how are you ?', 0, '2026-06-03 23:10:38'),
(42, 10, 2, 'hello', 0, '2026-06-03 23:28:41'),
(43, 10, 2, 'Hello', 0, '2026-06-04 21:08:09'),
(44, 10, 2, 'hi', 0, '2026-06-04 22:40:08'),
(45, 10, 1, 'hello', 0, '2026-06-05 01:07:48'),
(46, 20, 24, 'hello', 0, '2026-06-09 20:37:02'),
(47, 21, 1, 'Interview scheduled for your application on 2026-07-02 09:00.', 1, '2026-06-30 22:03:44'),
(48, 21, 1, 'Application has been endorsed to the client for review.', 1, '2026-06-30 22:05:15'),
(49, 22, 1, 'Interview scheduled for your application on 2026-07-03 09:00.', 1, '2026-07-02 14:15:23'),
(50, 22, 1, 'Application status updated to: For Interview.', 1, '2026-07-02 14:15:37'),
(51, 23, 1, 'Application status updated to: For Interview.', 1, '2026-07-02 14:16:07'),
(52, 22, 1, 'Application status updated to: For Interview.', 1, '2026-07-02 14:58:44'),
(53, 22, 1, 'Application has been endorsed to the client for review.', 1, '2026-07-02 14:58:58'),
(54, 24, 1, 'Interview scheduled for your application on 2026-07-03 15:00.', 1, '2026-07-02 15:01:51'),
(55, 25, 1, 'Interview scheduled for your application on 2026-07-03 15:00.', 1, '2026-07-02 15:40:14'),
(56, 25, 1, 'Application status updated to: For Interview.', 1, '2026-07-02 15:40:30'),
(57, 26, 1, 'Interview scheduled for your application on 2026-07-07 10:00 (status: Scheduled for Interview).', 1, '2026-07-06 22:09:04'),
(58, 26, 1, 'Application has been endorsed to the client for review (status: Endorsed to Client).', 1, '2026-07-06 22:09:35'),
(59, 27, 1, 'Interview scheduled for your application on 2026-07-09 09:00 (status: Scheduled for Interview).', 1, '2026-07-08 17:00:52'),
(60, 27, 1, 'Application has been endorsed to the client for review (status: Endorsed to Client).', 1, '2026-07-08 17:02:59'),
(61, 32, 1, 'Interview scheduled for your application on 2026-08-01 10:00 (status: Scheduled for Interview).', 1, '2026-07-31 00:53:28'),
(62, 32, 1, 'Application has been endorsed to the client for review (status: Endorsed to Client).', 1, '2026-07-31 00:53:40'),
(63, 33, 1, 'Interview scheduled for your application on 2026-01-08 15:00 (status: Scheduled for Interview).', 1, '2026-07-31 01:07:04'),
(64, 33, 1, 'Application has been endorsed to the client for review (status: Endorsed to Client).', 1, '2026-07-31 01:07:07'),
(65, 33, 1, 'Application approved by the client company.', 1, '2026-07-31 01:07:12'),
(66, 34, 1, 'Interview scheduled for your application on 2026-08-06 10:00 (status: Scheduled for Interview).', 1, '2026-08-05 11:10:05'),
(67, 35, 1, 'Interview scheduled for your application on 2026-08-13 09:00 (status: Scheduled for Interview).', 1, '2026-08-12 16:30:13'),
(68, 36, 1, 'Interview scheduled for your application on 2026-08-14 09:00 (status: Scheduled for Interview).', 1, '2026-08-13 14:04:04'),
(69, 37, 1, 'Interview scheduled for your application on 2026-08-15 10:00 (status: Scheduled for Interview).', 1, '2026-08-14 00:09:06'),
(70, 38, 1, 'Interview scheduled for your application on 2026-08-15 10:00 (status: Scheduled for Interview).', 1, '2026-08-14 14:42:58');

-- --------------------------------------------------------

--
-- Table structure for table `chat_threads`
--

CREATE TABLE `chat_threads` (
  `ThreadID` int(11) NOT NULL,
  `ThreadType` varchar(30) NOT NULL,
  `RelatedModule` varchar(100) DEFAULT NULL,
  `RelatedRecord` int(11) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_threads`
--

INSERT INTO `chat_threads` (`ThreadID`, `ThreadType`, `RelatedModule`, `RelatedRecord`, `CreatedBy`, `CreatedAt`, `UpdatedAt`) VALUES
(1, 'APPLICATION_EVENT', 'job_application', 4, 1, '2026-03-23 22:58:37', '2026-03-23 23:00:15'),
(2, 'APPLICATION_EVENT', 'job_application', 7, 2, '2026-03-29 23:47:05', '2026-03-29 23:48:23'),
(3, 'APPLICATION_EVENT', 'job_application', 8, 1, '2026-03-30 14:09:49', '2026-03-30 14:10:34'),
(4, 'APPLICATION_EVENT', 'job_application', 9, 1, '2026-03-30 16:25:38', '2026-03-30 16:26:21'),
(5, 'APPLICATION_EVENT', 'job_application', 10, 1, '2026-03-30 16:28:03', '2026-04-28 10:25:08'),
(6, 'APPLICATION_EVENT', 'job_application', 11, 1, '2026-03-30 16:52:02', '2026-03-30 16:55:26'),
(7, 'APPLICATION_EVENT', 'job_application', 12, 1, '2026-04-28 10:25:57', '2026-04-28 10:30:38'),
(8, 'APPLICATION_EVENT', 'job_application', 13, 1, '2026-04-28 10:31:03', '2026-04-28 10:33:52'),
(9, 'APPLICATION_EVENT', 'job_application', 16, 1, '2026-04-28 12:07:29', '2026-04-28 12:14:24'),
(10, 'DIRECT', 'dm_1_2', 2, 1, '2026-06-03 22:25:12', '2026-06-05 01:07:48'),
(11, 'DIRECT', 'dm_2_3', 3, 2, '2026-06-03 23:09:29', '2026-06-03 23:09:29'),
(12, 'DIRECT', 'dm_2_9', 9, 2, '2026-06-03 23:10:54', '2026-06-03 23:10:54'),
(13, 'DIRECT', 'dm_1_3', 3, 1, '2026-06-03 23:28:28', '2026-06-03 23:28:28'),
(14, 'DIRECT', 'dm_2_8', 8, 2, '2026-06-04 21:46:02', '2026-06-04 21:46:02'),
(15, 'DIRECT', 'dm_2_18', 18, 2, '2026-06-04 21:46:03', '2026-06-04 21:46:03'),
(16, 'DIRECT', 'dm_2_13', 13, 2, '2026-06-04 21:46:03', '2026-06-04 21:46:03'),
(17, 'DIRECT', 'dm_2_11', 11, 2, '2026-06-04 22:09:04', '2026-06-04 22:09:04'),
(18, 'DIRECT', 'dm_2_20', 20, 2, '2026-06-04 22:09:05', '2026-06-04 22:09:05'),
(19, 'DIRECT', 'dm_2_5', 5, 2, '2026-06-04 22:09:05', '2026-06-04 22:09:05'),
(20, 'DIRECT', 'dm_2_24', 2, 24, '2026-06-09 20:36:56', '2026-06-09 20:37:02'),
(21, 'APPLICATION_EVENT', 'job_application', 20, 1, '2026-06-30 22:03:44', '2026-06-30 22:05:15'),
(22, 'APPLICATION_EVENT', 'job_application', 21, 1, '2026-07-02 14:15:23', '2026-07-02 14:58:58'),
(23, 'APPLICATION_EVENT', 'job_application', 19, 1, '2026-07-02 14:16:07', '2026-07-02 14:16:07'),
(24, 'APPLICATION_EVENT', 'job_application', 22, 1, '2026-07-02 15:01:51', '2026-07-02 15:01:51'),
(25, 'APPLICATION_EVENT', 'job_application', 23, 1, '2026-07-02 15:40:14', '2026-07-02 15:40:30'),
(26, 'APPLICATION_EVENT', 'job_application', 25, 1, '2026-07-06 22:09:04', '2026-07-06 22:09:35'),
(27, 'APPLICATION_EVENT', 'job_application', 26, 1, '2026-07-08 17:00:52', '2026-07-08 17:02:59'),
(28, 'DIRECT', 'dm_21_31', 21, 31, '2026-07-08 17:34:45', '2026-07-08 17:34:45'),
(29, 'DIRECT', 'dm_13_31', 13, 31, '2026-07-08 17:34:45', '2026-07-08 17:34:45'),
(30, 'DIRECT', 'dm_17_31', 17, 31, '2026-07-08 17:34:46', '2026-07-08 17:34:46'),
(31, 'DIRECT', 'dm_12_31', 12, 31, '2026-07-08 17:34:46', '2026-07-08 17:34:46'),
(32, 'APPLICATION_EVENT', 'job_application', 27, 1, '2026-07-31 00:53:28', '2026-07-31 00:53:40'),
(33, 'APPLICATION_EVENT', 'job_application', 28, 1, '2026-07-31 01:07:04', '2026-07-31 01:07:12'),
(34, 'APPLICATION_EVENT', 'job_application', 29, 1, '2026-08-05 11:10:05', '2026-08-05 11:10:05'),
(35, 'APPLICATION_EVENT', 'job_application', 31, 1, '2026-08-12 16:30:13', '2026-08-12 16:30:13'),
(36, 'APPLICATION_EVENT', 'job_application', 32, 1, '2026-08-13 14:04:04', '2026-08-13 14:04:04'),
(37, 'APPLICATION_EVENT', 'job_application', 33, 1, '2026-08-14 00:09:06', '2026-08-14 00:09:06'),
(38, 'APPLICATION_EVENT', 'job_application', 34, 1, '2026-08-14 14:42:58', '2026-08-14 14:42:58');

-- --------------------------------------------------------

--
-- Table structure for table `chat_thread_participants`
--

CREATE TABLE `chat_thread_participants` (
  `ThreadID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_thread_participants`
--

INSERT INTO `chat_thread_participants` (`ThreadID`, `UserID`) VALUES
(1, 1),
(1, 2),
(1, 5),
(2, 2),
(2, 11),
(3, 1),
(3, 2),
(3, 14),
(4, 1),
(4, 2),
(4, 15),
(5, 1),
(5, 2),
(5, 15),
(6, 1),
(6, 2),
(6, 15),
(7, 1),
(7, 2),
(7, 15),
(8, 1),
(8, 2),
(8, 15),
(9, 1),
(9, 2),
(9, 7),
(10, 1),
(10, 2),
(11, 2),
(11, 3),
(12, 2),
(12, 9),
(13, 1),
(13, 3),
(14, 2),
(14, 8),
(15, 2),
(15, 18),
(16, 2),
(16, 13),
(17, 2),
(17, 11),
(18, 2),
(18, 20),
(19, 2),
(19, 5),
(20, 2),
(20, 24),
(21, 1),
(21, 2),
(21, 26),
(22, 1),
(22, 2),
(22, 27),
(23, 1),
(23, 2),
(23, 9),
(24, 1),
(24, 2),
(24, 27),
(25, 1),
(25, 2),
(25, 27),
(26, 1),
(26, 2),
(26, 29),
(27, 1),
(27, 2),
(27, 31),
(28, 21),
(28, 31),
(29, 13),
(29, 31),
(30, 17),
(30, 31),
(31, 12),
(31, 31),
(32, 1),
(32, 2),
(32, 35),
(33, 1),
(33, 2),
(33, 35),
(34, 1),
(34, 2),
(34, 38),
(35, 1),
(35, 2),
(35, 41),
(36, 1),
(36, 2),
(36, 32),
(37, 1),
(37, 2),
(37, 43),
(38, 1),
(38, 2),
(38, 44);

-- --------------------------------------------------------

--
-- Table structure for table `chat_thread_reads`
--

CREATE TABLE `chat_thread_reads` (
  `ThreadID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `LastReadAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_thread_reads`
--

INSERT INTO `chat_thread_reads` (`ThreadID`, `UserID`, `LastReadAt`) VALUES
(1, 1, '2026-03-23 22:59:08'),
(1, 2, '2026-03-24 15:07:57'),
(1, 5, '2026-03-24 11:45:47'),
(2, 2, '2026-03-29 23:48:23'),
(2, 11, NULL),
(3, 1, '2026-03-30 14:10:34'),
(3, 2, NULL),
(3, 14, NULL),
(4, 1, '2026-03-30 16:26:21'),
(4, 2, NULL),
(4, 15, NULL),
(5, 1, '2026-04-28 10:25:08'),
(5, 2, NULL),
(5, 15, NULL),
(6, 1, '2026-03-30 16:55:26'),
(6, 2, NULL),
(6, 15, NULL),
(7, 1, '2026-04-28 10:30:38'),
(7, 2, NULL),
(7, 15, NULL),
(8, 1, '2026-04-28 10:33:52'),
(8, 2, NULL),
(8, 15, NULL),
(9, 1, '2026-04-28 12:14:24'),
(9, 2, NULL),
(9, 7, NULL),
(10, 1, '2026-07-08 17:34:21'),
(10, 2, '2026-06-05 01:07:58'),
(11, 2, '2026-06-04 22:09:03'),
(11, 3, NULL),
(12, 2, '2026-06-04 22:09:04'),
(12, 9, NULL),
(13, 1, '2026-07-08 17:34:21'),
(13, 3, NULL),
(14, 2, '2026-06-04 21:46:02'),
(14, 8, NULL),
(15, 2, '2026-06-04 21:46:03'),
(15, 18, NULL),
(16, 2, '2026-06-04 21:46:03'),
(16, 13, NULL),
(17, 2, '2026-06-04 22:09:04'),
(17, 11, NULL),
(18, 2, '2026-06-04 22:09:05'),
(18, 20, NULL),
(19, 2, '2026-06-04 22:09:05'),
(19, 5, NULL),
(20, 2, NULL),
(20, 24, '2026-06-09 20:37:02'),
(21, 1, '2026-06-30 22:05:15'),
(21, 2, NULL),
(21, 26, NULL),
(22, 1, '2026-07-02 14:58:58'),
(22, 2, NULL),
(22, 27, NULL),
(23, 1, '2026-07-02 14:16:07'),
(23, 2, NULL),
(23, 9, NULL),
(24, 1, '2026-07-02 15:01:51'),
(24, 2, NULL),
(24, 27, NULL),
(25, 1, '2026-07-02 15:40:30'),
(25, 2, NULL),
(25, 27, NULL),
(26, 1, '2026-07-06 22:09:35'),
(26, 2, NULL),
(26, 29, NULL),
(27, 1, '2026-07-08 17:02:59'),
(27, 2, NULL),
(27, 31, NULL),
(28, 21, NULL),
(28, 31, '2026-07-08 17:34:45'),
(29, 13, NULL),
(29, 31, '2026-07-08 17:34:45'),
(30, 17, NULL),
(30, 31, '2026-07-08 17:34:46'),
(31, 12, NULL),
(31, 31, '2026-07-08 17:34:46'),
(32, 1, '2026-07-31 00:53:40'),
(32, 2, NULL),
(32, 35, NULL),
(33, 1, '2026-07-31 01:07:12'),
(33, 2, NULL),
(33, 35, NULL),
(34, 1, '2026-08-05 11:10:05'),
(34, 2, NULL),
(34, 38, NULL),
(35, 1, '2026-08-12 16:30:13'),
(35, 2, NULL),
(35, 41, '2026-08-12 16:30:29'),
(36, 1, '2026-08-13 14:04:04'),
(36, 2, NULL),
(36, 32, NULL),
(37, 1, '2026-08-14 00:09:06'),
(37, 2, NULL),
(37, 43, NULL),
(38, 1, '2026-08-14 14:42:58'),
(38, 2, NULL),
(38, 44, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clearance_items`
--

CREATE TABLE `clearance_items` (
  `ClearanceItemID` int(11) NOT NULL,
  `ResignationID` int(11) NOT NULL,
  `ItemName` varchar(150) NOT NULL,
  `IsCleared` tinyint(1) DEFAULT 0,
  `ClearedDate` datetime DEFAULT NULL,
  `ClearedBy` int(11) DEFAULT NULL,
  `Remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clientcompany`
--

CREATE TABLE `clientcompany` (
  `CompanyID` int(11) NOT NULL,
  `CompanyTypeID` int(11) DEFAULT NULL,
  `Name` varchar(150) NOT NULL,
  `Address` text DEFAULT NULL,
  `registered_latitude` decimal(10,8) DEFAULT NULL,
  `registered_longitude` decimal(11,8) DEFAULT NULL,
  `geofence_radius` int(11) DEFAULT 100,
  `site_registered_at` datetime DEFAULT NULL,
  `registered_by_tl_id` int(11) DEFAULT NULL,
  `ContactPerson` varchar(100) DEFAULT NULL,
  `ContactNumber` varchar(20) DEFAULT NULL,
  `Industry` varchar(100) DEFAULT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `UserID` int(11) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clientcompany`
--

INSERT INTO `clientcompany` (`CompanyID`, `CompanyTypeID`, `Name`, `Address`, `registered_latitude`, `registered_longitude`, `geofence_radius`, `site_registered_at`, `registered_by_tl_id`, `ContactPerson`, `ContactNumber`, `Industry`, `IsActive`, `UserID`, `latitude`, `longitude`) VALUES
(1, NULL, 'Jollibee', 'COC', NULL, NULL, 100, NULL, NULL, 'Irish', '09234966666', 'Food Industry', 1, NULL, 8.43143321, 124.65703303),
(2, NULL, 'Mang Inasal', 'Divisoria', NULL, NULL, 100, NULL, NULL, 'Dwight Jhonson', '092349666660', 'Food Industry', 1, 22, NULL, NULL),
(3, NULL, '7Eleven', 'COC', NULL, NULL, 100, NULL, NULL, 'TeamLead', '092349666660', 'Food Industry', 1, NULL, NULL, NULL),
(4, NULL, 'Mercury Drug', 'Carmen', NULL, NULL, 100, NULL, NULL, 'Supervisor', '09234966660', 'Healthcare & Pharmaceutical', 1, NULL, NULL, NULL),
(5, NULL, 'TomnToms', 'Uptown, CDO', NULL, NULL, 100, NULL, NULL, 'Team Lead User', '09234966660', 'Food Industry', 1, 4, NULL, NULL),
(6, NULL, 'Villa Tuna', 'Uptown, CDO', NULL, NULL, 100, NULL, NULL, 'Merry Joy Ucat', '09234966660', 'Food Industry', 0, 8, NULL, NULL),
(7, NULL, 'Rose Pharmacy', 'CDO', NULL, NULL, 100, NULL, NULL, 'Merry Joy Ucat', '09234966660', 'Healthcare & Pharmaceutical', 1, 8, NULL, NULL),
(8, NULL, 'Limketkai', 'Lapasan, CDO', NULL, NULL, 100, NULL, NULL, 'Irishdwin', '092349666660', 'Food Industry', 1, 12, NULL, NULL),
(9, NULL, 'SM uptown', 'Sm uptown, CDo', NULL, NULL, 100, NULL, NULL, 'Juan Dela Cruz', '09234966660', 'Food Industry', 1, 13, NULL, NULL),
(10, NULL, 'Gaisano', 'Carmen, CDO', NULL, NULL, 100, NULL, NULL, 'Dwin', '09234966660', 'Food Industry', 1, 16, NULL, NULL),
(11, NULL, 'Ororama', 'Carmen, CDO', NULL, NULL, 100, NULL, NULL, 'Irishdwin Gamutan', '09234966660', 'Food Industry', 1, 17, NULL, NULL),
(12, NULL, 'Cogon Market', 'Cogon, CDO', NULL, NULL, 100, NULL, NULL, 'Khulas', '09234966660', 'Food Industry', 1, 18, NULL, NULL),
(13, NULL, 'Gaisano Carmen', 'Carmen, CDO', NULL, NULL, 100, NULL, NULL, 'Khulas D. Buenas', '09234966660', 'Food and Drug', 1, 19, NULL, NULL),
(14, NULL, 'COC', 'carmen,CDO', NULL, NULL, 100, NULL, NULL, 'Juan Dela Cruz', '09234966660', 'Food Industry', 1, 21, NULL, NULL),
(15, NULL, 'Minute Burger', 'Macanhan, Carmen', NULL, NULL, 100, NULL, NULL, 'Charmaine', '09234966660', 'Food Industry', 1, 23, NULL, NULL),
(16, NULL, 'Morebites', 'Macanhan, Carmen, CDO', NULL, NULL, 100, NULL, NULL, 'Iris Nota', '09234966660', 'Food Industry', 1, 24, NULL, NULL),
(17, NULL, 'Gaisano Uptown', 'Uptown, CDO', NULL, NULL, 100, NULL, NULL, 'Trisia Navarro', '09234966660', 'Food Industry', 1, 30, NULL, NULL),
(18, NULL, 'Chams', 'Tomasacco St. Cagayan de Oro City', NULL, NULL, 100, NULL, NULL, 'Marry James', '09364126756', 'Food Industry', 1, 33, NULL, NULL),
(19, NULL, 'Gaisano - Bulua', 'Bulua,  CDO', 8.43141297, 124.65695999, 100, '2026-08-17 15:21:50', 34, 'Jhonel Dela Cruz', '09234966660', 'retail and commercial shopping mall industry', 1, 34, 8.43141297, 124.65695999),
(20, NULL, 'Gaisano Puerto', 'Puerto, Cagayan de Oro City', NULL, NULL, 100, NULL, NULL, 'Merry Joy', '09853252944', 'retail and commercial shopping mall industry', 1, 36, NULL, NULL),
(21, NULL, 'coc', 'Carmen, CDO', 8.43143096, 124.65701938, 100, '2026-08-18 09:50:23', 39, 'Drueew', '+639234966660', 'Food and Drug', 1, 39, 8.43143096, 124.65701938);

-- --------------------------------------------------------

--
-- Table structure for table `daily_time_records`
--

CREATE TABLE `daily_time_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(11) NOT NULL,
  `team_lead_user_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `break_time` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `total_hours_rendered` decimal(6,2) DEFAULT NULL,
  `status` enum('Absent','Clocked In','On Break','Clocked Out','Late','Present') NOT NULL DEFAULT 'Absent',
  `qr_scan_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_time_records`
--

INSERT INTO `daily_time_records` (`id`, `employee_id`, `team_lead_user_id`, `date`, `time_in`, `break_time`, `time_out`, `total_hours_rendered`, `status`, `qr_scan_count`, `created_at`, `updated_at`) VALUES
(1, 6, 24, '2026-07-06', '2026-07-06 16:03:37', NULL, NULL, NULL, 'Clocked In', 1, '2026-07-06 22:03:37', '2026-07-06 22:03:37'),
(2, 8, 30, '2026-07-23', '2026-07-23 17:44:06', '2026-07-23 17:44:10', '2026-07-23 17:44:13', 0.00, 'Clocked Out', 3, '2026-07-23 23:44:06', '2026-07-23 23:44:13'),
(3, 12, NULL, '2026-08-14', '2026-08-14 02:51:09', NULL, NULL, NULL, 'Clocked In', 0, '2026-08-14 08:51:09', '2026-08-14 08:51:09'),
(4, 13, NULL, '2026-08-14', '2026-08-14 10:09:36', NULL, NULL, NULL, '', 0, '2026-08-14 10:09:36', '2026-08-14 10:09:36'),
(5, 14, NULL, '2026-08-17', '2026-08-17 23:01:20', NULL, NULL, NULL, '', 0, '2026-08-17 23:01:20', '2026-08-17 23:01:20'),
(6, 13, NULL, '2026-08-18', '2026-08-18 09:51:07', NULL, '2026-08-18 12:00:59', 2.16, 'Clocked Out', 0, '2026-08-18 09:51:07', '2026-08-18 12:00:59');

-- --------------------------------------------------------

--
-- Table structure for table `deductions`
--

CREATE TABLE `deductions` (
  `DeductionsID` int(11) NOT NULL,
  `Payroll_ID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `DeductionType` varchar(100) DEFAULT NULL,
  `DeductionCategory` varchar(100) DEFAULT NULL,
  `Amount` decimal(10,2) DEFAULT NULL,
  `DeductionDate` date DEFAULT NULL,
  `Remarks` text DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `PeriodStart` date DEFAULT NULL,
  `PeriodEnd` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `DepartmentID` int(11) NOT NULL,
  `ManagerID` int(11) DEFAULT NULL,
  `Budget` decimal(15,2) DEFAULT NULL,
  `Location` varchar(150) DEFAULT NULL,
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deployment`
--

CREATE TABLE `deployment` (
  `DeploymentID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `CompanyID` int(11) NOT NULL,
  `Position` varchar(100) DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `Location` varchar(150) DEFAULT NULL,
  `StatusID` int(11) DEFAULT NULL,
  `DeploymentNotes` text DEFAULT NULL,
  `AllowDTRSubmission` tinyint(1) DEFAULT 1,
  `RequiresApproval` tinyint(1) DEFAULT 0,
  `SiteSuperVisorID` int(11) DEFAULT NULL,
  `ServiceAgreementID` int(11) DEFAULT NULL,
  `TimeIn` time DEFAULT '08:00:00',
  `TimeOut` time DEFAULT '17:00:00',
  `BreakDuration` int(10) UNSIGNED DEFAULT 60 COMMENT 'Break duration in minutes',
  `GracePeriod` int(10) UNSIGNED DEFAULT 5 COMMENT 'Grace period in minutes',
  `WorkingDays` varchar(100) DEFAULT 'Mon-Fri' COMMENT 'Scheduled working days',
  `ShiftName` varchar(100) DEFAULT 'Regular Shift'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deployment`
--

INSERT INTO `deployment` (`DeploymentID`, `EmployeeID`, `CompanyID`, `Position`, `StartDate`, `EndDate`, `Location`, `StatusID`, `DeploymentNotes`, `AllowDTRSubmission`, `RequiresApproval`, `SiteSuperVisorID`, `ServiceAgreementID`, `TimeIn`, `TimeOut`, `BreakDuration`, `GracePeriod`, `WorkingDays`, `ShiftName`) VALUES
(1, 1, 1, 'Cashier', '2026-02-24', '2030-10-21', 'Velez', NULL, NULL, 1, 0, 4, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(2, 2, 1, 'Kitchen Staff', '2026-03-30', '2026-03-24', 'Velez', NULL, NULL, 1, 0, 4, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(3, 3, 9, 'Security Guard', '2026-03-30', NULL, 'Sm uptown, CDo', NULL, NULL, 1, 0, 13, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(4, 4, 12, 'Security Guard', '2026-04-28', NULL, 'Cogon, CDO', NULL, NULL, 1, 0, 18, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(5, 5, 9, 'Head Chef', '2026-04-28', NULL, 'Sm uptown, CDo', NULL, NULL, 1, 0, 13, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(6, 6, 16, 'cashier', '2026-07-02', NULL, 'Multiple sites', NULL, NULL, 1, 0, 24, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(7, 7, 16, 'Delivery Rider', '2026-07-06', NULL, 'Macanhan, Carmen, CDO', NULL, NULL, 1, 0, 24, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(8, 8, 17, 'Diser', '2026-07-08', NULL, 'Uptown, CDO', NULL, NULL, 1, 0, 30, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(9, 9, 19, 'Promo diser', '2026-07-31', NULL, 'Bulua,  CDO', NULL, NULL, 1, 0, 34, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(10, 10, 20, 'Cashier', '2026-08-05', NULL, 'Puerto, Cagayan de Oro City', NULL, NULL, 1, 0, 36, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(11, 11, 21, 'Janitor', '2026-08-13', NULL, 'Carmen, CDO', NULL, NULL, 1, 0, 39, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(12, 12, 21, 'Teacher', '2026-08-13', NULL, 'Carmen, CDO', NULL, NULL, 1, 0, 39, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(13, 13, 21, 'Teacher', '2026-08-13', NULL, 'Carmen, CDO', NULL, NULL, 1, 0, 39, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(14, 14, 19, 'Promo Diser', '2026-08-14', NULL, 'Bulua,  CDO', NULL, NULL, 1, 0, 34, NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift');

-- --------------------------------------------------------

--
-- Table structure for table `dtr_submissions`
--

CREATE TABLE `dtr_submissions` (
  `DTR_SubmissionID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `DeploymentID` int(11) DEFAULT NULL,
  `SiteSupervisorID` int(11) DEFAULT NULL,
  `CutoffPeriod` varchar(50) DEFAULT NULL,
  `CutoffStartDate` date DEFAULT NULL,
  `CutoffEndDate` date DEFAULT NULL,
  `TotalHours` decimal(7,2) DEFAULT NULL,
  `OvertimeHours` decimal(7,2) DEFAULT NULL,
  `TotalOvertimeHours` decimal(7,2) DEFAULT NULL,
  `SubmittedBy` int(11) DEFAULT NULL,
  `ReviewedBy` int(11) DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `ApprovalDate` datetime DEFAULT NULL,
  `ReviewDate` datetime DEFAULT NULL,
  `Remarks` text DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `StatusID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `EmployeeID` int(11) NOT NULL,
  `StatusID` int(11) DEFAULT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `EmployeeNumber` varchar(20) DEFAULT NULL,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Position` varchar(100) DEFAULT NULL,
  `EmployeeType` varchar(50) DEFAULT NULL,
  `HiredDate` date DEFAULT NULL,
  `BasicSalary` decimal(10,2) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `allowance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monthly_salary_credit` decimal(10,2) DEFAULT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `PayrollType` varchar(50) DEFAULT NULL,
  `BankName` varchar(100) DEFAULT NULL,
  `SSSNumber` varchar(20) DEFAULT NULL,
  `PhilHealthNumber` varchar(20) DEFAULT NULL,
  `PagIBIGNumber` varchar(20) DEFAULT NULL,
  `TINNumber` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`EmployeeID`, `StatusID`, `DepartmentID`, `EmployeeNumber`, `FirstName`, `LastName`, `Email`, `Position`, `EmployeeType`, `HiredDate`, `BasicSalary`, `daily_rate`, `hourly_rate`, `allowance`, `monthly_salary_credit`, `IsActive`, `PayrollType`, `BankName`, `SSSNumber`, `PhilHealthNumber`, `PagIBIGNumber`, `TINNumber`) VALUES
(1, NULL, NULL, 'EMP001', 'Employee', 'User', 'employee@onazul.com', NULL, NULL, '2026-02-21', 25000.00, NULL, NULL, 0.00, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(2, NULL, NULL, 'EMP2026000004', 'Applicant', 'User', 'applicant@onazul.com', 'Kitchen Staff', NULL, '2026-03-24', NULL, NULL, NULL, 0.00, NULL, 1, NULL, NULL, '123123', '132123123', '123123123', NULL),
(3, NULL, NULL, 'EMP2026000008', 'Selena', 'Gomez', 'selenamain811@gmail.com', 'Security Guard', NULL, '2026-03-30', NULL, NULL, NULL, 0.00, NULL, 1, NULL, NULL, 'a12312', '3123123', '123213', NULL),
(4, NULL, NULL, 'EMP2026000013', 'Sherrish', 'Mae', 'hayabusa.main.28@gmail.com', 'Security Guard', NULL, '2026-04-28', NULL, NULL, NULL, 0.00, NULL, 1, NULL, NULL, '3213123', '123123123', '12312313213', NULL),
(5, NULL, NULL, 'EMP2026000016', 'Irina', 'Gamurio', 'irinagamurio@gmail.com', 'Head Chef', NULL, '2026-04-28', NULL, NULL, NULL, 0.00, NULL, 1, NULL, NULL, '123123', '41324324535', '3523545', NULL),
(6, NULL, NULL, 'EMP2026000021', 'Nicolas', 'Jhonson', 'gmailtest010203@gmail.com', 'cashier', NULL, '2026-07-02', NULL, NULL, NULL, 0.00, NULL, 1, NULL, NULL, '23123123', '123123123', '123123123123', NULL),
(7, NULL, NULL, 'EMP2026000025', 'Toyota', 'Supra', 'dumpnavs0@gmail.com', 'Delivery Rider', NULL, '2026-07-06', NULL, NULL, NULL, 0.00, NULL, 1, NULL, NULL, '123123123', '123123123', '131312313123', NULL),
(8, NULL, NULL, 'EMP2026000026', 'Mariane', 'Mildred', 'caelmax30@gmail.com', 'Diser', NULL, '2026-07-08', NULL, NULL, NULL, 0.00, NULL, 1, NULL, NULL, '332231321321', '46546546', '31221312', NULL),
(9, NULL, NULL, 'EMP2026000027', 'Emmanuel', 'Cruz', 'amille.5437@gmail.com', 'Promo diser', NULL, '2026-07-31', 18200.00, 700.00, 87.50, 1000.00, 18200.00, 1, NULL, NULL, '123123123123', '12312312312', '312312312313', NULL),
(10, NULL, NULL, 'EMP2026000029', 'Cammille', 'Roxas', 'carsmar781@gmail.com', 'Cashier', NULL, '2026-08-05', 18200.00, 700.00, 87.50, 0.00, 18200.00, 1, NULL, NULL, '14124124', '21421421412', '1242141414124', NULL),
(11, NULL, NULL, 'EMP2026000031', 'Jerome', 'Cruz', 'july83767@gmail.com', 'Janitor', NULL, '2026-08-13', 18200.00, 700.00, 87.50, 0.00, 18200.00, 1, NULL, NULL, '14124124', '24214214', '234124124', NULL),
(12, NULL, NULL, 'EMP2026000032', 'Chevrolet', 'Camarro', 'marcar8879@gmail.com', 'Teacher', NULL, '2026-08-13', 15600.00, 600.00, 75.00, 0.00, 15600.00, 1, NULL, NULL, '31316165465464', '65464644654654', '6546454646654', NULL),
(13, NULL, NULL, 'EMP2026000033', 'Aaron', 'Cruz', 'unodostress0099@gmail.com', 'Teacher', NULL, '2026-08-14', 20800.00, 800.00, 100.00, 0.00, 20800.00, 1, NULL, NULL, '123123', '123123123', '123123123123123', NULL),
(14, NULL, NULL, 'EMP2026000034', 'Andrew', 'Cruz', 'damnyoustark77@gmail.com', 'Promo Diser', NULL, '2026-08-14', 18200.00, 700.00, 87.50, 0.00, 18200.00, 1, NULL, NULL, '132132313', '1231312313', '12312312313', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employmentcontract`
--

CREATE TABLE `employmentcontract` (
  `EmploymentContractID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `ContractNumber` varchar(50) DEFAULT NULL,
  `ContractType` varchar(50) DEFAULT NULL,
  `ContractStartDate` date DEFAULT NULL,
  `ContractEndDate` date DEFAULT NULL,
  `SignedDocumentPath` varchar(255) DEFAULT NULL,
  `Position` varchar(100) DEFAULT NULL,
  `BasicSalary` decimal(10,2) DEFAULT NULL,
  `SalaryType` varchar(50) DEFAULT NULL,
  `WorkSchedule` text DEFAULT NULL,
  `DeploymentTerms` text DEFAULT NULL,
  `BenefitsClause` text DEFAULT NULL,
  `ResponsibilitiesClause` text DEFAULT NULL,
  `TerminationClause` text DEFAULT NULL,
  `SignedDate` date DEFAULT NULL,
  `WitnessName` varchar(100) DEFAULT NULL,
  `WitnessSignature` varchar(255) DEFAULT NULL,
  `StatusID` int(11) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT current_timestamp(),
  `ApprovedBy` int(11) DEFAULT NULL,
  `ApprovedDate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gov_percentage_rates`
--

CREATE TABLE `gov_percentage_rates` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `philhealth_ee_pct` decimal(6,4) NOT NULL DEFAULT 2.5000,
  `philhealth_er_pct` decimal(6,4) NOT NULL DEFAULT 2.5000,
  `pagibig_ee_fixed` decimal(10,2) NOT NULL DEFAULT 200.00,
  `pagibig_er_fixed` decimal(10,2) NOT NULL DEFAULT 200.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gov_percentage_rates`
--

INSERT INTO `gov_percentage_rates` (`id`, `philhealth_ee_pct`, `philhealth_er_pct`, `pagibig_ee_fixed`, `pagibig_er_fixed`, `updated_at`) VALUES
(1, 2.5000, 2.5000, 200.00, 200.00, '2026-07-16 15:02:18');

-- --------------------------------------------------------

--
-- Table structure for table `gu_tags`
--

CREATE TABLE `gu_tags` (
  `GUTagID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `DeploymentID` int(11) NOT NULL,
  `TagType` enum('Time-In','Time-Out','Overtime-Out') NOT NULL,
  `TagDateTime` datetime NOT NULL,
  `PhotoPath` varchar(255) DEFAULT NULL,
  `Latitude` decimal(10,7) DEFAULT NULL,
  `Longitude` decimal(10,7) DEFAULT NULL,
  `LocationAddress` varchar(255) DEFAULT NULL,
  `DeviceInfo` varchar(255) DEFAULT NULL,
  `IsVerified` tinyint(1) DEFAULT 0,
  `VerifiedBy` int(11) DEFAULT NULL,
  `VerifiedDate` datetime DEFAULT NULL,
  `AttendanceID` int(11) DEFAULT NULL,
  `Remarks` text DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `HolidayID` int(11) NOT NULL,
  `HolidayName` varchar(100) NOT NULL,
  `HolidayDate` date NOT NULL,
  `HolidayType` varchar(50) DEFAULT NULL,
  `PayMultiplier` decimal(4,2) DEFAULT 1.00,
  `IsNationwide` tinyint(1) DEFAULT 1,
  `Region` varchar(100) DEFAULT NULL,
  `Year` int(11) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `industry_lookup`
--

CREATE TABLE `industry_lookup` (
  `IndustryID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `industry_lookup`
--

INSERT INTO `industry_lookup` (`IndustryID`, `Name`, `CreatedAt`) VALUES
(1, 'Food Industry', '2026-03-20 21:58:13'),
(3, 'Healthcare & Pharmaceutical', '2026-03-20 21:59:59'),
(255, 'Food and Drug', '2026-04-25 03:16:26'),
(860, 'retail and commercial shopping mall industry', '2026-07-30 11:55:13');

-- --------------------------------------------------------

--
-- Table structure for table `joborder`
--

CREATE TABLE `joborder` (
  `JobOrderID` int(11) NOT NULL,
  `CompanyID` int(11) NOT NULL,
  `Position` varchar(100) DEFAULT NULL,
  `RequiredSkills` text DEFAULT NULL,
  `Qualifications` text DEFAULT NULL,
  `ID` int(11) DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `ServiceAgreementID` int(11) DEFAULT NULL,
  `RequiredCount` int(11) DEFAULT 0,
  `Location` varchar(150) DEFAULT NULL,
  `WorkSchedule` text DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `RequestDate` date DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `PublishedDate` date DEFAULT NULL,
  `JobCategory` varchar(255) DEFAULT NULL,
  `Status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `TimeIn` time DEFAULT '08:00:00',
  `TimeOut` time DEFAULT '17:00:00',
  `BreakDuration` int(10) UNSIGNED DEFAULT 60 COMMENT 'Break duration in minutes',
  `GracePeriod` int(10) UNSIGNED DEFAULT 5 COMMENT 'Grace period in minutes',
  `WorkingDays` varchar(100) DEFAULT 'Mon-Fri' COMMENT 'Scheduled working days',
  `ShiftName` varchar(100) DEFAULT 'Regular Shift'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `joborder`
--

INSERT INTO `joborder` (`JobOrderID`, `CompanyID`, `Position`, `RequiredSkills`, `Qualifications`, `ID`, `CreatedAt`, `ServiceAgreementID`, `RequiredCount`, `Location`, `WorkSchedule`, `CreatedBy`, `RequestDate`, `ApprovedBy`, `PublishedDate`, `JobCategory`, `Status`, `category_id`, `TimeIn`, `TimeOut`, `BreakDuration`, `GracePeriod`, `WorkingDays`, `ShiftName`) VALUES
(1, 1, 'Cashier', NULL, NULL, NULL, '2026-02-21 14:45:47', 1, 2, 'Velez', 'Mon-Fri 8am-5pm', 2, '0000-00-00', NULL, NULL, NULL, 'Open', NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(2, 1, 'cashier', NULL, NULL, NULL, '2026-02-21 14:53:55', 1, 2, 'vamenta', 'mon - fri 8am - 5pm', 2, '0000-00-00', NULL, NULL, NULL, 'Open', NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(3, 3, 'Cashier', NULL, NULL, NULL, '2026-02-21 16:23:28', 2, 1, 'COC', 'Mon-Fri 8AM-5PM', 2, '0000-00-00', NULL, NULL, NULL, 'Open', NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(4, 1, 'Security Guard', NULL, NULL, NULL, '2026-03-21 06:59:47', 1, 2, 'Velez', 'Mon-Fri 8AM-5PM', 1, '0000-00-00', NULL, NULL, 'Security & Protective Services', 'Open', 4, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(5, 1, 'Kitchen Staff', NULL, NULL, NULL, '2026-03-23 22:36:27', 1, 2, 'Velez', 'Mon-Fri 8AM-5PM', 1, '0000-00-00', NULL, NULL, 'Food & Beverage / Hospitality', 'Open', 2, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(6, 1, 'Chef', NULL, NULL, NULL, '2026-03-24 15:34:38', 1, 2, 'Velez', 'Mon-Fri 8AM-5PM', 1, '0000-00-00', NULL, NULL, 'Food & Beverage / Hospitality', 'Open', 2, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(7, 1, 'cashier', NULL, NULL, NULL, '2026-03-29 23:44:49', 1, 3, 'Velez', 'Mon-Fri 8AM-5PM', 1, '0000-00-00', NULL, NULL, NULL, 'Open', NULL, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(8, 9, 'Security Guard', NULL, NULL, NULL, '2026-03-30 12:33:02', 6, 2, 'Sm uptown, CDo', 'Flexible', 1, '2026-03-30', NULL, NULL, 'Security & Protective Services', 'Open', 4, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(9, 10, 'Lady Guard', NULL, NULL, NULL, '2026-03-30 15:41:32', 7, 1, 'Carmen, CDO', 'Night Shift (10PM-6AM)', 1, '2026-03-30', NULL, NULL, 'Security & Protective Services', 'Open', 4, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(10, 10, 'Cashier', NULL, NULL, NULL, '2026-03-30 15:41:34', 7, 2, 'Carmen, CDO', 'Mid Shift (2PM-10PM)', 1, '2026-03-30', NULL, NULL, 'Retail & Sales', 'Open', 1, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(11, 9, 'Head Chef', NULL, NULL, NULL, '2026-03-30 16:24:33', 6, 2, 'Sm uptown, CDo', 'Morning Shift (6AM-2PM)', 1, '2026-03-30', NULL, NULL, 'Food & Beverage / Hospitality', 'Open', 2, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(12, 11, 'Security Guard', NULL, NULL, NULL, '2026-03-30 16:51:00', 8, 2, 'Carmen, CDO', 'Night Shift (10PM-6AM)', 1, '2026-03-30', NULL, NULL, 'Security & Protective Services', 'Open', 4, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(13, 12, 'Security Guard', NULL, NULL, NULL, '2026-04-25 11:03:01', 9, 2, 'Cogon, CDO', 'Mon-Fri 8AM-5PM', 1, '2026-04-25', NULL, NULL, 'Security & Protective Services', 'Open', 4, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(14, 13, 'Security Guard', NULL, NULL, NULL, '2026-04-25 11:21:52', 10, 2, 'Carmen, CDO', 'Mon-Sat 8AM-5PM', 1, '2026-04-25', NULL, NULL, 'Security & Protective Services', 'Open', 4, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(15, 14, 'Security Guard', NULL, NULL, NULL, '2026-04-28 11:57:41', 11, 3, 'carmen,CDO', 'Mon-Fri 8AM-5PM', 1, '2026-04-28', NULL, NULL, 'Security & Protective Services', 'Open', 4, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(16, 16, 'Sales clerk', NULL, NULL, NULL, '2026-06-10 23:01:54', 15, 1, 'Macanhan, Carmen, CDO', 'Flexible', 1, '2026-06-10', NULL, NULL, 'Retail & Sales', 'Open', 1, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(17, 16, 'Sales clerk', NULL, NULL, NULL, '2026-06-10 23:35:19', 15, 1, 'Macanhan, Carmen, CDO', 'Rotating', 1, '2026-06-10', NULL, NULL, 'Food & Beverage / Hospitality', 'Open', 2, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(18, 16, 'Cashier', NULL, NULL, NULL, '2026-06-20 23:47:07', 15, 4, 'Multiple sites', 'Flexible', 1, '2026-06-20', NULL, NULL, 'Food & Beverage / Hospitality', 'Open', 2, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(19, 16, 'cashier', NULL, NULL, NULL, '2026-06-24 23:55:16', 15, 2, 'Multiple sites', 'Flexible', 1, '2026-06-24', NULL, NULL, 'Food & Beverage / Hospitality', 'Open', 2, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(20, 16, 'Delivery Rider', NULL, NULL, NULL, '2026-07-06 22:08:14', 15, 1, 'Macanhan, Carmen, CDO', 'Mon-Fri 8AM-5PM', 1, '2026-07-06', NULL, NULL, 'Logistics, Warehousing & Driving', 'Open', 3, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(21, 17, 'Diser', NULL, NULL, NULL, '2026-07-08 16:54:22', 16, 2, 'Multiple sites', 'Mon-Fri 8AM-5PM', 1, '2026-07-08', NULL, NULL, 'sales', 'Open', 1338, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(22, 19, 'Promo diser', NULL, NULL, NULL, '2026-07-30 20:46:57', 18, 2, 'Bulua,  CDO', 'Flexible', 1, '2026-07-30', NULL, NULL, 'Retail & Sales', 'Open', 1, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(23, 19, 'Promo Diser', NULL, NULL, NULL, '2026-07-31 08:16:27', 18, 2, 'Bulua,  CDO', 'Flexible', 1, '2026-07-31', NULL, NULL, 'Retail & Sales', 'Open', 1, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(24, 19, 'Cashier', NULL, NULL, NULL, '2026-08-05 10:38:39', 18, 2, 'Bulua,  CDO', 'Flexible', 1, '2026-08-05', NULL, NULL, 'sales', 'Open', 1338, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(25, 20, 'Cashier', NULL, NULL, NULL, '2026-08-05 10:40:53', 19, 3, 'Multiple sites', 'Mon-Fri 8AM-5PM', 1, '2026-08-05', NULL, NULL, 'Retail & Sales', 'Open', 1, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(26, 20, 'Cashier', NULL, NULL, NULL, '2026-08-05 10:42:37', 19, 1, 'Puerto, Cagayan de Oro City', 'Mon-Fri 8AM-5PM', 1, '2026-08-05', NULL, NULL, 'Retail & Sales', 'Open', 1, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(27, 20, 'Security Guard', NULL, NULL, NULL, '2026-08-05 10:56:43', 19, 3, 'Multiple sites', 'Mon-Fri 8AM-5PM', 1, '2026-08-05', NULL, NULL, 'Security & Protective Services', 'Open', 4, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(28, 21, 'Teacher', NULL, NULL, NULL, '2026-08-06 10:32:14', 20, 4, 'Multiple sites', 'Mon-Fri 8AM-5PM', 1, '2026-08-06', NULL, NULL, 'Teacher', 'Open', 2207, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(29, 21, 'Janitor', NULL, NULL, NULL, '2026-08-06 10:53:26', 20, 1, 'Carmen, CDO', 'Mon-Fri 8AM-5PM', 1, '2026-08-06', NULL, NULL, 'Building and Grounds Cleaning and Maintenance', 'Open', 318125, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(30, 21, 'Teacher', NULL, NULL, NULL, '2026-08-13 12:29:08', 20, 3, 'Carmen, CDO', 'Mon-Fri 8AM-5PM', 1, '2026-08-13', NULL, NULL, 'Teacher', 'Open', 2207, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift'),
(31, 19, 'Promo Diser', NULL, NULL, NULL, '2026-08-14 14:28:25', 18, 4, 'Multiple sites', 'Mon-Fri 8AM-5PM', 1, '2026-08-14', NULL, NULL, 'Retail & Sales', 'Open', 1, '08:00:00', '17:00:00', 60, 5, 'Mon-Fri', 'Regular Shift');

-- --------------------------------------------------------

--
-- Table structure for table `joborderpositions`
--

CREATE TABLE `joborderpositions` (
  `JobOrderPositionID` int(11) NOT NULL,
  `JobOrderID` int(11) NOT NULL,
  `Position` varchar(100) DEFAULT NULL,
  `RequiredCount` int(11) DEFAULT 0,
  `FilledCount` int(11) DEFAULT 0,
  `RemainingCount` int(11) DEFAULT 0,
  `QualificationRequirements` text DEFAULT NULL,
  `SalaryRates` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobrequirements`
--

CREATE TABLE `jobrequirements` (
  `JobRequirementsID` int(11) NOT NULL,
  `JobOrderID` int(11) NOT NULL,
  `RequirementType` varchar(100) DEFAULT NULL,
  `IsMandatory` tinyint(1) DEFAULT 0,
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `ApplicationID` int(11) NOT NULL,
  `ApplicantID` int(11) NOT NULL,
  `JobOrderID` int(11) NOT NULL,
  `target_detail_id` int(11) DEFAULT NULL,
  `ApplicationDate` datetime DEFAULT current_timestamp(),
  `StatusID` int(11) DEFAULT NULL,
  `InterviewDate` datetime DEFAULT NULL,
  `InterviewNotes` text DEFAULT NULL,
  `EndorsedDate` datetime DEFAULT NULL,
  `EndorsedBy` int(11) DEFAULT NULL,
  `ClientApprovalDate` datetime DEFAULT NULL,
  `ClientApprovalStatus` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `HiredDate` datetime DEFAULT NULL,
  `HiredBy` int(11) DEFAULT NULL,
  `Remarks` text DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_applications`
--

INSERT INTO `job_applications` (`ApplicationID`, `ApplicantID`, `JobOrderID`, `target_detail_id`, `ApplicationDate`, `StatusID`, `InterviewDate`, `InterviewNotes`, `EndorsedDate`, `EndorsedBy`, `ClientApprovalDate`, `ClientApprovalStatus`, `HiredDate`, `HiredBy`, `Remarks`, `CreatedAt`) VALUES
(1, 1, 2, NULL, '2026-02-21 14:54:59', 1, '2026-02-22 11:00:00', NULL, '2026-02-21 09:00:49', 2, '2026-02-26 16:44:59', 'Approved', NULL, NULL, '', '2026-02-21 14:54:59'),
(2, 1, 3, NULL, '2026-02-21 16:23:44', 2, NULL, NULL, '2026-02-26 16:11:05', 2, '2026-02-26 16:28:25', 'Approved', NULL, NULL, '', '2026-02-21 16:23:44'),
(3, 1, 4, NULL, '2026-03-21 07:03:51', 1, '2026-03-23 09:00:00', NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-03-21 07:03:51'),
(4, 1, 5, NULL, '2026-03-23 22:37:07', 2, '2026-03-30 09:00:00', NULL, '2026-03-23 16:00:15', 2, '2026-03-23 23:00:35', 'Approved', '2026-03-24 08:40:15', 1, '\nContract uploaded: ../../uploads/employment_contracts/contract_hr_4_1774279097_69c159b9575da.png', '2026-03-23 22:37:07'),
(5, 2, 6, NULL, '2026-03-24 15:35:18', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-03-24 15:35:18'),
(6, 5, 5, NULL, '2026-03-29 21:50:40', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-03-29 21:50:40'),
(7, 5, 7, NULL, '2026-03-29 23:45:48', 2, '2026-03-30 09:00:00', NULL, '2026-03-29 17:48:23', 2, '2026-03-29 23:48:43', 'Approved', NULL, NULL, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-06-03; expiry_date=; uploaded_at=2026-06-03 05:46:11; path=../../uploads/employment_contracts/contract_hr_7_1780458371_6a1fa383545a3.pdf', '2026-03-29 23:45:48'),
(8, 6, 8, NULL, '2026-03-30 12:36:19', 2, '2026-03-31 09:00:00', NULL, '2026-03-30 08:10:34', 1, '2026-03-30 14:10:51', 'Approved', '2026-03-30 14:40:18', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-03-30; expiry_date=; uploaded_at=2026-03-30 08:39:51; path=../../uploads/employment_contracts/contract_hr_8_1774852791_69ca1ab77400e.jpg', '2026-03-30 12:36:19'),
(9, 7, 11, NULL, '2026-03-30 16:25:16', 2, '2026-04-21 09:00:00', NULL, '2026-03-30 10:26:21', 1, '2026-03-30 16:27:25', 'Rejected', NULL, NULL, NULL, '2026-03-30 16:25:16'),
(10, 7, 9, NULL, '2026-03-30 16:27:33', 2, '2026-04-29 09:00:00', NULL, '2026-04-28 04:25:08', 1, NULL, 'Pending', NULL, NULL, NULL, '2026-03-30 16:27:33'),
(11, 7, 12, NULL, '2026-03-30 16:51:19', 2, '2026-03-31 09:00:00', NULL, '2026-03-30 10:55:26', 1, '2026-03-30 16:56:46', 'Approved', NULL, NULL, NULL, '2026-03-30 16:51:19'),
(12, 7, 14, NULL, '2026-04-28 10:25:36', 1, '2026-04-29 10:00:00', NULL, '2026-04-28 04:27:00', 1, NULL, 'Pending', NULL, NULL, NULL, '2026-04-28 10:25:36'),
(13, 7, 13, NULL, '2026-04-28 10:30:51', 2, '2026-04-29 16:00:00', NULL, '2026-04-28 04:33:52', 1, '2026-04-28 10:43:47', 'Approved', '2026-04-28 10:44:25', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-04-28; expiry_date=; uploaded_at=2026-04-28 04:44:13; path=../../uploads/employment_contracts/contract_hr_13_1777344253_69f01efd6f5e9.pdf', '2026-04-28 10:30:51'),
(14, 2, 15, NULL, '2026-04-28 11:59:35', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-04-28 11:59:35'),
(15, 2, 5, NULL, '2026-04-28 12:00:47', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-04-28 12:00:47'),
(16, 2, 11, NULL, '2026-04-28 12:01:18', 2, '2026-04-29 10:00:00', NULL, '2026-04-28 06:14:24', 1, '2026-04-28 12:17:56', 'Approved', '2026-04-28 12:21:04', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-04-28; expiry_date=; uploaded_at=2026-04-28 06:19:42; path=../../uploads/employment_contracts/contract_hr_16_1777349982_69f0355e403e8.pdf', '2026-04-28 12:01:18'),
(17, 9, 16, NULL, '2026-06-20 23:49:13', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-06-20 23:49:13'),
(18, 3, 18, 2, '2026-06-24 23:39:19', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-06-24 23:39:19'),
(19, 3, 19, 4, '2026-06-24 23:55:29', 1, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-06-24 23:55:29'),
(20, 10, 19, 3, '2026-06-30 22:03:15', 2, '2026-07-02 09:00:00', NULL, '2026-06-30 16:05:15', 1, NULL, 'Pending', NULL, NULL, NULL, '2026-06-30 22:03:15'),
(21, 11, 19, 3, '2026-07-02 13:43:04', 3, '2026-07-03 09:00:00', NULL, '2026-07-02 08:58:58', 1, '2026-07-02 15:53:36', 'Approved', '2026-07-02 15:55:07', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-07-02; expiry_date=; uploaded_at=2026-07-02 09:54:49; path=../../uploads/employment_contracts/contract_hr_21_1782978889_6a4619493c5b0.pdf', '2026-07-02 13:43:04'),
(22, 11, 18, 1, '2026-07-02 14:59:45', 1, '2026-07-03 15:00:00', NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-07-02 14:59:45'),
(23, 11, 17, NULL, '2026-07-02 15:39:59', 1, '2026-07-03 15:00:00', NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-07-02 15:39:59'),
(24, 12, 19, 4, '2026-07-02 23:18:38', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-07-02 23:18:38'),
(25, 13, 20, 5, '2026-07-06 22:08:22', 3, '2026-07-07 10:00:00', NULL, '2026-07-06 16:09:35', 1, '2026-07-06 22:09:50', 'Approved', '2026-07-06 22:10:52', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-07-06; expiry_date=; uploaded_at=2026-07-06 16:10:22; path=../../uploads/employment_contracts/contract_hr_25_1783347022_6a4bb74e62673.pdf', '2026-07-06 22:08:22'),
(26, 14, 21, 6, '2026-07-08 16:59:21', 3, '2026-07-09 09:00:00', NULL, '2026-07-08 11:02:59', 1, '2026-07-08 17:06:11', 'Approved', '2026-07-08 17:08:21', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-07-08; expiry_date=; uploaded_at=2026-07-08 11:07:48; path=../../uploads/employment_contracts/contract_hr_26_1783501668_6a4e1364796c5.pdf', '2026-07-08 16:59:21'),
(27, 16, 22, 8, '2026-07-31 00:49:27', 3, '2026-08-01 10:00:00', NULL, '2026-07-30 18:53:40', 1, '2026-07-31 01:36:13', 'Approved', '2026-07-31 09:03:12', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-07-31; expiry_date=; uploaded_at=2026-07-31 03:02:46; path=../../uploads/employment_contracts/contract_hr_27_1785459766_6a6bf43630383.pdf', '2026-07-31 00:49:27'),
(28, 16, 21, 7, '2026-07-31 00:52:42', 2, '2026-01-08 15:00:00', NULL, '2026-07-30 19:07:07', 1, '2026-07-30 19:07:12', 'Approved', NULL, NULL, NULL, '2026-07-31 00:52:42'),
(29, 18, 26, 13, '2026-08-05 11:09:29', 3, '2026-08-06 10:00:00', NULL, '2026-08-05 05:10:24', 1, '2026-08-05 11:25:23', 'Approved', '2026-08-05 11:26:11', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-08-05; expiry_date=; uploaded_at=2026-08-05 05:25:44; path=../../uploads/employment_contracts/contract_hr_29_1785900344_6a72ad3896a19.pdf', '2026-08-05 11:09:29'),
(30, 20, 24, 10, '2026-08-11 23:22:02', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-08-11 23:22:02'),
(31, 20, 29, 18, '2026-08-12 16:04:08', 3, '2026-08-13 09:00:00', NULL, '2026-08-12 10:53:00', 1, '2026-08-13 12:15:28', 'Approved', '2026-08-13 12:16:20', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-08-13; expiry_date=; uploaded_at=2026-08-13 06:15:59; path=../../uploads/employment_contracts/contract_hr_31_1786594559_6a7d44ff1a2d6.pdf', '2026-08-12 16:04:08'),
(32, 15, 30, 19, '2026-08-13 14:03:18', 3, '2026-08-14 09:00:00', NULL, '2026-08-13 08:05:05', 1, '2026-08-13 14:05:46', 'Approved', '2026-08-13 14:06:29', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-08-13; expiry_date=; uploaded_at=2026-08-13 08:06:11; path=../../uploads/employment_contracts/contract_hr_32_1786601171_6a7d5ed329436.pdf', '2026-08-13 14:03:18'),
(33, 22, 30, 19, '2026-08-14 00:08:28', 3, '2026-08-15 10:00:00', NULL, '2026-08-13 18:09:40', 1, '2026-08-14 00:10:41', 'Approved', '2026-08-14 00:11:34', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-08-14; expiry_date=; uploaded_at=2026-08-13 18:11:12; path=../../uploads/employment_contracts/contract_hr_33_1786637472_6a7deca0b5c26.pdf', '2026-08-14 00:08:28'),
(34, 23, 31, 20, '2026-08-14 14:41:38', 3, '2026-08-15 10:00:00', NULL, '2026-08-14 14:43:14', 1, '2026-08-14 14:44:07', 'Approved', '2026-08-14 14:45:10', 1, 'Contract uploaded: type=Open Contract; years=2; months=0; signed_date=2026-08-14; expiry_date=; uploaded_at=2026-08-14 14:44:29; path=../../uploads/employment_contracts/contract_hr_34_1786689869_6a7eb94d96b71.pdf', '2026-08-14 14:41:38');

-- --------------------------------------------------------

--
-- Table structure for table `job_categories`
--

CREATE TABLE `job_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_categories`
--

INSERT INTO `job_categories` (`id`, `category_name`, `created_at`) VALUES
(1, 'Retail & Sales', '2026-06-10 22:57:39'),
(2, 'Food & Beverage / Hospitality', '2026-06-10 22:57:39'),
(3, 'Logistics, Warehousing & Driving', '2026-06-10 22:57:39'),
(4, 'Security & Protective Services', '2026-06-10 22:57:39'),
(5, 'Customer Service & BPO', '2026-06-10 22:57:39'),
(6, 'Corporate, Office & Administrative', '2026-06-10 22:57:39'),
(7, 'General Utilities & Maintenance', '2026-06-10 22:57:39'),
(1338, 'sales', '2026-06-10 23:03:47'),
(2207, 'Teacher', '2026-06-10 23:16:13'),
(318125, 'Building and Grounds Cleaning and Maintenance', '2026-08-06 10:53:05');

-- --------------------------------------------------------

--
-- Table structure for table `job_order_details`
--

CREATE TABLE `job_order_details` (
  `detail_id` int(11) NOT NULL,
  `RequestID` int(11) NOT NULL,
  `position_title` varchar(255) NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `job_category` varchar(255) DEFAULT NULL,
  `required_workers` int(11) NOT NULL DEFAULT 1,
  `deployment_site` varchar(255) NOT NULL,
  `work_schedule` varchar(150) NOT NULL,
  `JobOrderID` int(11) DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_order_details`
--

INSERT INTO `job_order_details` (`detail_id`, `RequestID`, `position_title`, `category_id`, `job_category`, `required_workers`, `deployment_site`, `work_schedule`, `JobOrderID`, `CreatedAt`) VALUES
(1, 22, 'Cashier', 2, 'Food & Beverage / Hospitality', 2, 'Macanhan, Carmen, CDO', 'Flexible', 18, '2026-06-20 23:27:39'),
(2, 22, 'cashier', 2, 'Food & Beverage / Hospitality', 2, 'Indahag, CDO', 'Flexible', 18, '2026-06-20 23:27:39'),
(3, 23, 'cashier', 2, 'Food & Beverage / Hospitality', 1, 'Macanhan, Carmen, CDO', 'Flexible', 19, '2026-06-24 20:08:34'),
(4, 23, 'cashier', 2, 'Food & Beverage / Hospitality', 1, 'Puerto, Carmen, CDO', 'Rotating', 19, '2026-06-24 20:08:34'),
(5, 24, 'Delivery Rider', 3, 'Logistics, Warehousing & Driving', 1, 'Macanhan, Carmen, CDO', 'Mon-Fri 8AM-5PM', 20, '2026-07-06 22:04:59'),
(6, 25, 'Diser', 1338, 'sales', 1, 'Uptown, CDO', 'Mon-Fri 8AM-5PM', 21, '2026-07-08 16:52:26'),
(7, 25, 'Diser', 1338, 'sales', 1, 'Cogon, CDO', 'Mon-Fri 8AM-5PM', 21, '2026-07-08 16:52:26'),
(8, 26, 'Promo diser', 1, 'Retail & Sales', 2, 'Bulua,  CDO', 'Flexible', 22, '2026-07-30 20:38:47'),
(9, 27, 'Promo Diser', 1, 'Retail & Sales', 2, 'Bulua,  CDO', 'Flexible', 23, '2026-07-31 08:16:04'),
(10, 28, 'Cashier', 1338, 'sales', 2, 'Bulua,  CDO', 'Flexible', 24, '2026-07-31 09:02:03'),
(11, 29, 'Cashier', 1, 'Retail & Sales', 1, 'Puerto, Cagayan de Oro City', 'Mon-Fri 8AM-5PM', 25, '2026-08-05 10:26:27'),
(12, 29, 'Promo Diser', 1, 'Retail & Sales', 2, 'Puerto, Cagayan de Oro City', 'Mon-Fri 8AM-5PM', 25, '2026-08-05 10:26:27'),
(13, 30, 'Cashier', 1, 'Retail & Sales', 1, 'Puerto, Cagayan de Oro City', 'Mon-Fri 8AM-5PM', 26, '2026-08-05 10:40:47'),
(14, 31, 'Security Guard', 4, 'Security & Protective Services', 1, 'Puerto, Cagayan de Oro City', 'Mon-Fri 8AM-5PM', 27, '2026-08-05 10:56:34'),
(15, 31, 'Promo Diser', 1, 'Retail & Sales', 2, 'Puerto, Cagayan de Oro City', 'Mon-Fri 8AM-5PM', 27, '2026-08-05 10:56:34'),
(16, 32, 'Teacher', 2207, 'Teacher', 2, 'Carmen, CDO', 'Mon-Fri 8AM-5PM', 28, '2026-08-06 10:31:56'),
(17, 32, 'Teacher', 2207, 'Teacher', 2, 'Puerto, CDO', 'Mon-Fri 8AM-5PM', 28, '2026-08-06 10:31:56'),
(18, 33, 'Janitor', 318125, 'Building and Grounds Cleaning and Maintenance', 1, 'Carmen, CDO', 'Mon-Fri 8AM-5PM', 29, '2026-08-06 10:53:15'),
(19, 34, 'Teacher', 2207, 'Teacher', 3, 'Carmen, CDO', 'Mon-Fri 8AM-5PM', 30, '2026-08-13 12:12:53'),
(20, 35, 'Promo Diser', 1, 'Retail & Sales', 2, 'Bulua,  CDO', 'Mon-Fri 8AM-5PM', 31, '2026-08-14 14:28:12'),
(21, 35, 'Cashier', 1, 'Retail & Sales', 2, 'Bulua,  CDO', 'Mon-Sat 8AM-5PM', 31, '2026-08-14 14:28:12');

-- --------------------------------------------------------

--
-- Table structure for table `job_order_requests`
--

CREATE TABLE `job_order_requests` (
  `RequestID` int(11) NOT NULL,
  `RequestedBy` int(11) NOT NULL,
  `CompanyID` int(11) NOT NULL,
  `Position` varchar(100) NOT NULL,
  `RequiredCount` int(11) NOT NULL DEFAULT 1,
  `Location` varchar(150) DEFAULT NULL,
  `WorkSchedule` varchar(100) DEFAULT NULL,
  `Remarks` text DEFAULT NULL,
  `IsConsolidated` tinyint(1) NOT NULL DEFAULT 0,
  `Status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `ProcessedBy` int(11) DEFAULT NULL,
  `ProcessedAt` datetime DEFAULT NULL,
  `JobOrderID` int(11) DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `JobCategory` varchar(255) DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_order_requests`
--

INSERT INTO `job_order_requests` (`RequestID`, `RequestedBy`, `CompanyID`, `Position`, `RequiredCount`, `Location`, `WorkSchedule`, `Remarks`, `IsConsolidated`, `Status`, `ProcessedBy`, `ProcessedAt`, `JobOrderID`, `CreatedAt`, `JobCategory`, `category_id`) VALUES
(1, 4, 1, 'Security Guard', 2, 'Velez', 'Mon-Fri 8AM-5PM', '', 0, 'Approved', 1, '2026-03-20 23:59:47', 4, '2026-03-21 06:59:36', NULL, NULL),
(2, 4, 1, 'Kitchen Staff', 2, 'Velez', 'Mon-Fri 8AM-5PM', 'With Experience', 0, 'Approved', 1, '2026-03-23 15:36:27', 5, '2026-03-23 22:34:32', NULL, NULL),
(3, 4, 1, 'Chef', 2, 'Velez', 'Mon-Fri 8AM-5PM', '', 0, 'Approved', 1, '2026-03-24 08:34:38', 6, '2026-03-24 15:34:22', NULL, NULL),
(4, 4, 1, 'cashier', 3, 'Velez', 'Mon-Fri 8AM-5PM', 'asdadad', 0, 'Approved', 1, '2026-03-29 17:44:49', 7, '2026-03-29 23:44:29', NULL, NULL),
(5, 13, 9, 'Security Guard', 2, 'Sm uptown, CDo', 'Flexible', '', 0, 'Approved', 1, '2026-03-30 06:33:02', 8, '2026-03-30 12:32:42', '0', NULL),
(6, 4, 5, 'Head Chef', 2, 'Uptown, CDO', 'Mon-Fri 8AM-5PM', '', 0, 'Rejected', 1, '2026-03-30 09:34:14', NULL, '2026-03-30 15:19:36', '0', NULL),
(7, 4, 1, 'Lady Guard', 1, 'Velez', 'Night Shift (10PM-6AM)', '', 0, 'Rejected', 1, '2026-03-30 09:34:13', NULL, '2026-03-30 15:19:54', '0', NULL),
(8, 4, 5, 'Cashier', 1, 'Uptown, CDO', 'Morning Shift (6AM-2PM)', '', 0, 'Rejected', 1, '2026-03-30 09:34:11', NULL, '2026-03-30 15:20:10', '0', NULL),
(9, 16, 10, 'Cashier', 2, 'Carmen, CDO', 'Mid Shift (2PM-10PM)', '', 0, 'Approved', 1, '2026-03-30 09:41:34', 10, '2026-03-30 15:34:45', '0', NULL),
(10, 16, 10, 'Lady Guard', 1, 'Carmen, CDO', 'Night Shift (10PM-6AM)', '', 0, 'Approved', 1, '2026-03-30 09:41:31', 9, '2026-03-30 15:34:57', '0', NULL),
(11, 13, 9, 'Head Chef', 2, 'Sm uptown, CDo', 'Morning Shift (6AM-2PM)', '', 0, 'Approved', 1, '2026-03-30 10:24:33', 11, '2026-03-30 16:24:23', '0', NULL),
(12, 17, 11, 'Security Guard', 2, 'Carmen, CDO', 'Night Shift (10PM-6AM)', '', 0, 'Approved', 1, '2026-03-30 10:51:00', 12, '2026-03-30 16:50:49', '0', NULL),
(13, 18, 12, 'Security Guard', 2, 'Cogon, CDO', '10am  - 5 pm', '', 0, 'Rejected', 1, '2026-04-25 04:50:10', NULL, '2026-04-25 10:45:50', '0', NULL),
(14, 17, 11, 'Sales clerk', 2, 'Carmen, CDO', 'Flexible', '', 0, 'Rejected', 1, '2026-04-25 05:05:14', NULL, '2026-04-25 10:54:30', '0', NULL),
(15, 18, 12, 'Security Guard', 2, 'Cogon, CDO', 'Mon-Fri 8AM-5PM', '', 0, 'Approved', 1, '2026-04-25 05:03:01', 13, '2026-04-25 11:02:16', '0', NULL),
(16, 19, 13, 'Security Guard', 2, 'Carmen, CDO', 'Mon-Sat 8AM-5PM', '', 0, 'Approved', 1, '2026-04-25 05:21:52', 14, '2026-04-25 11:20:35', '0', NULL),
(17, 21, 14, 'Teacher', 5, 'carmen,CDO', 'Mon-Fri 8AM-5PM', '', 0, 'Rejected', 1, '2026-06-10 17:14:02', NULL, '2026-04-28 11:52:18', '0', NULL),
(18, 21, 14, 'Security Guard', 3, 'carmen,CDO', 'Mon-Fri 8AM-5PM', '', 0, 'Approved', 1, '2026-04-28 05:57:41', 15, '2026-04-28 11:57:31', '0', NULL),
(19, 24, 16, 'Sales clerk', 1, 'Macanhan, Carmen, CDO', 'Flexible', 'I need female', 0, 'Approved', 1, '2026-06-10 17:01:54', 16, '2026-06-09 23:39:24', '0', NULL),
(20, 24, 16, '0', 1, 'Macanhan, Carmen, CDO', 'Rotating', '', 0, 'Rejected', 1, '2026-06-10 17:14:01', NULL, '2026-06-10 23:06:17', '0', 2),
(21, 24, 16, 'Sales clerk', 1, 'Macanhan, Carmen, CDO', 'Rotating', '', 0, 'Approved', 1, '2026-06-10 17:35:19', 17, '2026-06-10 23:34:51', 'Food & Beverage / Hospitality', 2),
(22, 24, 16, 'Cashier (+1 more)', 4, 'Multiple sites', 'Flexible', '', 1, 'Approved', 1, '2026-06-20 17:47:07', 18, '2026-06-20 23:27:39', 'Food & Beverage / Hospitality', 2),
(23, 24, 16, 'cashier (+1 more)', 2, 'Multiple sites', 'Flexible', '', 1, 'Approved', 1, '2026-06-24 17:55:16', 19, '2026-06-24 20:08:34', 'Food & Beverage / Hospitality', 2),
(24, 24, 16, 'Delivery Rider', 1, 'Macanhan, Carmen, CDO', 'Mon-Fri 8AM-5PM', 'Male Only!', 1, 'Approved', 1, '2026-07-06 16:08:13', 20, '2026-07-06 22:04:59', 'Logistics, Warehousing & Driving', 3),
(25, 30, 17, 'Diser (+1 more)', 2, 'Multiple sites', 'Mon-Fri 8AM-5PM', '', 1, 'Approved', 1, '2026-07-08 10:54:21', 21, '2026-07-08 16:52:26', 'sales', 1338),
(26, 34, 19, 'Promo diser', 2, 'Bulua,  CDO', 'Flexible', '', 1, 'Approved', 1, '2026-07-30 14:46:57', 22, '2026-07-30 20:38:47', 'Retail & Sales', 1),
(27, 34, 19, 'Promo Diser', 2, 'Bulua,  CDO', 'Flexible', '', 1, 'Approved', 1, '2026-07-31 02:16:26', 23, '2026-07-31 08:16:04', 'Retail & Sales', 1),
(28, 34, 19, 'Cashier', 2, 'Bulua,  CDO', 'Flexible', '', 1, 'Approved', 1, '2026-08-05 04:38:39', 24, '2026-07-31 09:02:03', 'sales', 1338),
(29, 36, 20, 'Cashier (+1 more)', 3, 'Multiple sites', 'Mon-Fri 8AM-5PM', '', 1, 'Approved', 1, '2026-08-05 04:40:53', 25, '2026-08-05 10:26:27', 'Retail & Sales', 1),
(30, 36, 20, 'Cashier', 1, 'Puerto, Cagayan de Oro City', 'Mon-Fri 8AM-5PM', '', 1, 'Approved', 1, '2026-08-05 04:42:37', 26, '2026-08-05 10:40:47', 'Retail & Sales', 1),
(31, 36, 20, 'Security Guard (+1 more)', 3, 'Multiple sites', 'Mon-Fri 8AM-5PM', '', 1, 'Approved', 1, '2026-08-05 04:56:43', 27, '2026-08-05 10:56:34', 'Security & Protective Services', 4),
(32, 39, 21, 'Teacher (+1 more)', 4, 'Multiple sites', 'Mon-Fri 8AM-5PM', '', 1, 'Approved', 1, '2026-08-06 04:32:13', 28, '2026-08-06 10:31:56', 'Teacher', 2207),
(33, 39, 21, 'Janitor', 1, 'Carmen, CDO', 'Mon-Fri 8AM-5PM', '', 1, 'Approved', 1, '2026-08-06 04:53:26', 29, '2026-08-06 10:53:15', 'Building and Grounds Cleaning and Maintenance', 318125),
(34, 39, 21, 'Teacher', 3, 'Carmen, CDO', 'Mon-Fri 8AM-5PM', '', 1, 'Approved', 1, '2026-08-13 06:29:08', 30, '2026-08-13 12:12:53', 'Teacher', 2207),
(35, 34, 19, 'Promo Diser (+1 more)', 4, 'Multiple sites', 'Mon-Fri 8AM-5PM', '', 1, 'Approved', 1, '2026-08-14 14:28:25', 31, '2026-08-14 14:28:12', 'Retail & Sales', 1);

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `LeaveID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `LeaveType` varchar(50) DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `TotalDays` decimal(4,1) DEFAULT NULL,
  `Reason` text DEFAULT NULL,
  `SupportingDocuments` varchar(255) DEFAULT NULL,
  `FiledDate` date DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `ApprovalDate` datetime DEFAULT NULL,
  `StatusID` int(11) DEFAULT NULL,
  `RejectionReason` text DEFAULT NULL,
  `AffectsPayroll` tinyint(1) DEFAULT 1,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `NotificationID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `NotificationType` varchar(50) DEFAULT NULL,
  `Title` varchar(200) DEFAULT NULL,
  `Message` text DEFAULT NULL,
  `RelateedModule` varchar(100) DEFAULT NULL,
  `RelatedRecord` int(11) DEFAULT NULL,
  `IsRead` tinyint(1) DEFAULT 0,
  `Priority` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`NotificationID`, `UserID`, `NotificationType`, `Title`, `Message`, `RelateedModule`, `RelatedRecord`, `IsRead`, `Priority`) VALUES
(1, 5, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 1, 1, NULL),
(2, 5, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 1, 1, NULL),
(3, 5, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 1, 1, NULL),
(4, 5, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 1, 1, NULL),
(5, 5, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Feb 22, 2026 at 11:00 AM', 'job_application', 1, 1, NULL),
(6, 5, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 2, 1, NULL),
(7, 5, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 2, 1, NULL),
(8, 5, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 2, 1, NULL),
(9, 5, 'client_approved', 'Application Approved by Client', 'Good news! Your application for Cashier at 7Eleven has been approved by the client.', 'job_application', 2, 1, NULL),
(10, 5, 'client_approved', 'Application Approved by Client', 'Good news! Your application for cashier at Jollibee has been approved by the client.', 'job_application', 1, 1, NULL),
(11, 5, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 3, 1, NULL),
(12, 5, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Mar 23, 2026 at 9:00 AM', 'job_application', 3, 1, NULL),
(13, 5, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Mar 30, 2026 at 9:00 AM', 'job_application', 4, 1, NULL),
(14, 5, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Mar 30, 2026 at 9:00 AM', 'job_application', 4, 1, NULL),
(15, 5, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 4, 1, NULL),
(16, 5, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 4, 0, NULL),
(17, 5, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 4, 1, NULL),
(18, 11, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 7, 0, NULL),
(19, 11, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Mar 30, 2026 at 9:00 AM', 'job_application', 7, 0, NULL),
(20, 11, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 7, 0, NULL),
(21, 13, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is juan@onazul.com. You are assigned to SM uptown. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 9, 0, 'Normal'),
(22, 13, 'company_now_active', 'Client company is Active', '\"SM uptown\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 9, 0, 'Normal'),
(23, 14, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Mar 31, 2026 at 9:00 AM', 'job_application', 8, 1, NULL),
(24, 14, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 8, 0, NULL),
(25, 14, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 8, 0, NULL),
(26, 16, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is Dwin@onazul.com. You are assigned to Gaisano. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 10, 0, 'Normal'),
(27, 16, 'company_now_active', 'Client company is Active', '\"Gaisano\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 10, 0, 'Normal'),
(28, 15, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Apr 21, 2026 at 9:00 AM', 'job_application', 9, 0, NULL),
(29, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 9, 0, NULL),
(30, 15, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 9, 0, NULL),
(31, 15, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Apr 23, 2026 at 10:00 AM', 'job_application', 10, 0, NULL),
(32, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 10, 0, NULL),
(33, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 10, 0, NULL),
(34, 15, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 10, 0, NULL),
(35, 17, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is Irishdwin@onazul.com. You are assigned to Ororama. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 11, 0, 'Normal'),
(36, 17, 'company_now_active', 'Client company is Active', '\"Ororama\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 11, 0, 'Normal'),
(37, 15, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Mar 31, 2026 at 9:00 AM', 'job_application', 11, 0, NULL),
(38, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 11, 0, NULL),
(39, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 11, 0, NULL),
(40, 15, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 11, 0, NULL),
(41, 18, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is m76413283@gmail.com. You are assigned to Cogon Market. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 12, 0, 'Normal'),
(42, 18, 'company_now_active', 'Client company is Active', '\"Cogon Market\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 12, 0, 'Normal'),
(43, 19, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is adele.elle28@gmail.com. You are assigned to Gaisano Carmen. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 13, 0, 'Normal'),
(44, 19, 'company_now_active', 'Client company is Active', '\"Gaisano Carmen\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 13, 0, 'Normal'),
(45, 15, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Apr 29, 2026 at 9:00 AM', 'job_application', 10, 0, NULL),
(46, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 10, 1, NULL),
(47, 15, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 10, 0, NULL),
(48, 15, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Apr 29, 2026 at 9:00 AM', 'job_application', 12, 0, NULL),
(49, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 12, 0, NULL),
(50, 15, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 12, 0, NULL),
(51, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 12, 0, NULL),
(52, 15, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Apr 29, 2026 at 10:00 AM', 'job_application', 12, 0, NULL),
(53, 15, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Apr 29, 2026 at 4:00 PM', 'job_application', 13, 0, NULL),
(54, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 13, 0, NULL),
(55, 15, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 13, 0, NULL),
(56, 15, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 13, 0, NULL),
(57, 21, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is j93011462@gmail.com. You are assigned to COC. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 14, 0, 'Normal'),
(58, 21, 'company_now_active', 'Client company is Active', '\"COC\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 14, 0, 'Normal'),
(59, 7, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Apr 29, 2026 at 10:00 AM', 'job_application', 16, 0, NULL),
(60, 7, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 16, 0, NULL),
(61, 7, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 16, 0, NULL),
(62, 7, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 16, 0, NULL),
(63, 22, 'team_lead_company_assigned', 'Assigned as Team Lead', 'You are now the Team Lead contact for Mang Inasal. When the client is Active, you can request job orders and work with endorsed applicants.', 'client_company', 2, 0, 'Normal'),
(64, 22, 'company_now_active', 'Client company is Active', '\"Mang Inasal\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 2, 0, 'Normal'),
(65, 23, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is v77336013@gmail.com. You are assigned to Minute Burger. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 15, 0, 'Normal'),
(66, 23, 'company_now_active', 'Client company is Active', '\"Minute Burger\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 15, 0, 'Normal'),
(67, 2, 'hr_message', 'New message from System Administrator', 'Hello', 'chat_thread', 10, 1, NULL),
(68, 1, 'hr_message', 'New message from HR User', 'hi', 'chat_thread', 10, 1, NULL),
(69, 2, 'hr_message', 'New message from System Administrator', 'how are you ?', 'chat_thread', 10, 1, NULL),
(70, 1, 'hr_message', 'New message from HR User', 'hello', 'chat_thread', 10, 1, NULL),
(71, 1, 'hr_message', 'New message from HR User', 'Hello', 'chat_thread', 10, 1, NULL),
(72, 1, 'hr_message', 'New message from HR User', 'hi', 'chat_thread', 10, 1, NULL),
(73, 8, 'company_now_active', 'Client company is Active', '\"Rose Pharmacy\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 7, 0, 'Normal'),
(74, 2, 'hr_message', 'New message from System Administrator', 'hello', 'chat_thread', 10, 1, NULL),
(75, 24, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is irisaton25@gmail.com. You are assigned to Morebites. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 16, 1, 'Normal'),
(76, 24, 'company_now_active', 'Client company is Active', '\"Morebites\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 16, 1, 'Normal'),
(77, 1, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 1, 'Normal'),
(78, 2, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(79, 4, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(80, 8, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(81, 12, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(82, 13, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(83, 16, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(84, 17, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(85, 18, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(86, 19, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(87, 21, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(88, 23, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 0, 'Normal'),
(89, 24, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 123455 is now active. Contract duration: 2026-06-09 to 2027-06-09.', 'service_agreement', 15, 1, 'Normal'),
(90, 2, 'hr_message', 'New message from Iris Nota', 'hello', 'chat_thread', 20, 0, NULL),
(91, 1, 'job_application_submitted', 'New Job Application', 'Chevy Camaro applied for cashier at Morebites · Site: Macanhan, Carmen, CDO.', 'job_application', 20, 1, 'Normal'),
(92, 2, 'job_application_submitted', 'New Job Application', 'Chevy Camaro applied for cashier at Morebites · Site: Macanhan, Carmen, CDO.', 'job_application', 20, 0, 'Normal'),
(93, 26, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Jul 02, 2026 at 9:00 AM', 'job_application', 20, 0, NULL),
(94, 26, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 20, 1, NULL),
(95, 1, 'job_application_submitted', 'New Job Application', 'Nicolas Jhonson applied for cashier at Morebites · Site: Macanhan, Carmen, CDO.', 'job_application', 21, 1, 'Normal'),
(96, 2, 'job_application_submitted', 'New Job Application', 'Nicolas Jhonson applied for cashier at Morebites · Site: Macanhan, Carmen, CDO.', 'job_application', 21, 0, 'Normal'),
(97, 27, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Jul 03, 2026 at 9:00 AM', 'job_application', 21, 1, NULL),
(98, 27, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 21, 1, NULL),
(99, 9, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 19, 0, NULL),
(100, 27, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 21, 1, NULL),
(101, 27, 'application_endorsed', 'Application Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 21, 1, NULL),
(102, 1, 'job_application_submitted', 'New Job Application', 'Nicolas Jhonson applied for Cashier at Morebites · Site: Macanhan, Carmen, CDO.', 'job_application', 22, 1, 'Normal'),
(103, 2, 'job_application_submitted', 'New Job Application', 'Nicolas Jhonson applied for Cashier at Morebites · Site: Macanhan, Carmen, CDO.', 'job_application', 22, 0, 'Normal'),
(104, 27, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Jul 03, 2026 at 3:00 PM', 'job_application', 22, 1, NULL),
(105, 1, 'job_application_submitted', 'New Job Application', 'Nicolas Jhonson applied for Sales clerk at Morebites.', 'job_application', 23, 1, 'Normal'),
(106, 2, 'job_application_submitted', 'New Job Application', 'Nicolas Jhonson applied for Sales clerk at Morebites.', 'job_application', 23, 0, 'Normal'),
(107, 27, 'interview_scheduled', 'Interview Scheduled', 'An interview has been scheduled for your application on Jul 03, 2026 at 3:00 PM', 'job_application', 23, 1, NULL),
(108, 27, 'application_status', 'Application Status Updated', 'Your application status has been updated to: For Interview', 'job_application', 23, 1, NULL),
(109, 27, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 21, 1, NULL),
(110, 1, 'job_application_submitted', 'New Job Application', 'Jack Dawson applied for cashier at Morebites · Site: Puerto, Carmen, CDO.', 'job_application', 24, 1, 'Normal'),
(111, 2, 'job_application_submitted', 'New Job Application', 'Jack Dawson applied for cashier at Morebites · Site: Puerto, Carmen, CDO.', 'job_application', 24, 0, 'Normal'),
(112, 1, 'job_application_submitted', 'New Job Application', 'Toyota Supra applied for Delivery Rider at Morebites · Site: Macanhan, Carmen, CDO.', 'job_application', 25, 1, 'Normal'),
(113, 2, 'job_application_submitted', 'New Job Application', 'Toyota Supra applied for Delivery Rider at Morebites · Site: Macanhan, Carmen, CDO.', 'job_application', 25, 0, 'Normal'),
(114, 29, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on Jul 07, 2026 at 10:00 AM', 'job_application', 25, 0, NULL),
(115, 29, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 25, 0, NULL),
(116, 29, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 25, 0, NULL),
(117, 29, 'application_hired', 'Congratulations! You are Hired!', 'Welcome to the Onazul team! Your account has been upgraded to an Employee profile. Sign in with your current email and password to access your Employee Dashboard.', 'job_application', 25, 0, NULL),
(118, 30, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is undru6767@gmail.com. You are assigned to Gaisano Uptown. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 17, 0, 'Normal'),
(119, 30, 'company_now_active', 'Client company is Active', '\"Gaisano Uptown\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 17, 0, 'Normal'),
(120, 1, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 1, 'Normal'),
(121, 2, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(122, 4, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(123, 8, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(124, 12, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(125, 13, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(126, 16, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(127, 17, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(128, 18, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(129, 19, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(130, 21, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(131, 23, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(132, 24, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(133, 30, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1234567 is now active. Contract duration: 2026-07-08 to 2028-07-08.', 'service_agreement', 16, 0, 'Normal'),
(134, 1, 'job_application_submitted', 'New Job Application', 'Mariane Mildred applied for Diser at Gaisano Uptown · Site: Uptown, CDO.', 'job_application', 26, 1, 'Normal'),
(135, 2, 'job_application_submitted', 'New Job Application', 'Mariane Mildred applied for Diser at Gaisano Uptown · Site: Uptown, CDO.', 'job_application', 26, 0, 'Normal'),
(136, 31, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on Jul 09, 2026 at 9:00 AM', 'job_application', 26, 0, NULL),
(137, 31, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 26, 0, NULL),
(138, 31, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 26, 0, NULL),
(139, 31, 'application_hired', 'Congratulations! You are Hired!', 'Welcome to the Onazul team! Your account has been upgraded to an Employee profile. Sign in with your current email and password to access your Employee Dashboard.', 'job_application', 26, 0, NULL),
(140, 33, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is mjucat3@gmail.com. You are assigned to Chams. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 18, 1, 'Normal'),
(141, 33, 'company_now_active', 'Client company is Active', '\"Chams\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 18, 1, 'Normal'),
(142, 1, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 1, 'Normal'),
(143, 2, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(144, 4, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(145, 8, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(146, 12, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(147, 13, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(148, 16, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(149, 17, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(150, 18, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(151, 19, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(152, 21, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(153, 23, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(154, 24, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(155, 30, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 0, 'Normal'),
(156, 33, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 1231312312312313 is now active. Contract duration: 2026-07-23 to 2030-07-23.', 'service_agreement', 17, 1, 'Normal'),
(157, 34, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is druuw67@gmail.com. You are assigned to Gaisano - Bulua. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 19, 1, 'Normal'),
(158, 34, 'company_now_active', 'Client company is Active', '\"Gaisano - Bulua\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 19, 1, 'Normal'),
(159, 1, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 1, 'Normal'),
(160, 2, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(161, 4, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(162, 8, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(163, 12, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(164, 13, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(165, 16, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(166, 17, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(167, 18, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(168, 19, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(169, 21, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(170, 23, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(171, 24, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(172, 30, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(173, 33, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 0, 'Normal'),
(174, 34, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 231313123123123123123123 is now active. Contract duration: 2026-07-30 to 2030-07-30.', 'service_agreement', 18, 1, 'Normal'),
(175, 1, 'job_application_submitted', 'New Job Application', 'Emmanuel Cruz applied for Promo diser at Gaisano - Bulua · Site: Bulua,  CDO.', 'job_application', 27, 1, 'Normal'),
(176, 2, 'job_application_submitted', 'New Job Application', 'Emmanuel Cruz applied for Promo diser at Gaisano - Bulua · Site: Bulua,  CDO.', 'job_application', 27, 0, 'Normal'),
(177, 1, 'job_application_submitted', 'New Job Application', 'Emmanuel Cruz applied for Diser at Gaisano Uptown · Site: Cogon, CDO.', 'job_application', 28, 1, 'Normal'),
(178, 2, 'job_application_submitted', 'New Job Application', 'Emmanuel Cruz applied for Diser at Gaisano Uptown · Site: Cogon, CDO.', 'job_application', 28, 0, 'Normal'),
(179, 35, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on Aug 01, 2026 at 10:00 AM', 'job_application', 27, 1, NULL),
(180, 35, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 27, 1, NULL),
(181, 35, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on Jan 08, 2026 at 3:00 PM', 'job_application', 28, 1, NULL),
(182, 35, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 28, 1, NULL),
(183, 35, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 28, 1, NULL),
(184, 35, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 27, 1, NULL),
(185, 35, 'application_hired', 'Congratulations! You are Hired!', 'Welcome to the Onazul team! Your account has been upgraded to an Employee profile. Sign in with your current email and password to access your Employee Dashboard.', 'job_application', 27, 1, NULL),
(186, 36, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is ellimac618@gmail.com. You are assigned to Gaisano Puerto. The client stays Pending until a Service Agreement is uploaded. When the company is Active, you can request job orders and work with endorsed applicants.', 'client_company', 20, 0, 'Normal'),
(187, 36, 'company_now_active', 'Client company is Active', '\"Gaisano Puerto\" is now Active. You can request job orders and work with endorsed applicants.', 'client_company', 20, 0, 'Normal'),
(188, 1, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 1, 'Normal'),
(189, 2, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(190, 4, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(191, 8, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(192, 12, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(193, 13, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(194, 16, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(195, 17, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(196, 18, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(197, 19, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(198, 21, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(199, 23, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(200, 24, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(201, 30, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(202, 33, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(203, 34, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 1, 'Normal'),
(204, 36, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 13123123123123 is now active. Contract duration: 2026-08-05 to 2026-08-05.', 'service_agreement', 19, 0, 'Normal'),
(205, 36, 'job_order_request', 'Job Order Request Approved', 'Your Job Order Request for Gaisano Puerto has been approved.', 'job_order', 27, 0, 'Normal'),
(206, 1, 'job_application_submitted', 'New Job Application', 'Cammille Roxas applied for Cashier at Gaisano Puerto · Site: Puerto, Cagayan de Oro City.', 'job_application', 29, 1, 'Normal'),
(207, 2, 'job_application_submitted', 'New Job Application', 'Cammille Roxas applied for Cashier at Gaisano Puerto · Site: Puerto, Cagayan de Oro City.', 'job_application', 29, 0, 'Normal'),
(208, 38, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on Aug 06, 2026 at 10:00 AM', 'job_application', 29, 0, NULL),
(209, 38, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 29, 0, NULL),
(211, 38, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 29, 0, NULL),
(212, 38, 'application_hired', 'Congratulations! You are Hired!', 'Welcome to the Onazul team! Your account has been upgraded to an Employee profile. Sign in with your current email and password to access your Employee Dashboard.', 'job_application', 29, 0, NULL),
(213, 39, 'team_lead_company_assigned', 'Welcome — Team Lead account created', 'Your Team Lead login is mejoyu0@gmail.com. You are assigned to coc. Your service agreement contract number is SA-2026-000001.', 'client_company', 21, 1, 'Normal'),
(214, 1, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 1, 'Normal'),
(215, 2, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(216, 4, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(217, 8, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(218, 12, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(219, 13, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(220, 16, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(221, 17, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(222, 18, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(223, 19, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(224, 21, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(225, 23, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(226, 24, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(227, 30, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(228, 33, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(229, 34, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 1, 'Normal'),
(230, 36, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 0, 'Normal'),
(231, 39, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement SA-2026-000001 is now active. Contract duration: 2026-08-06 to 2026-08-06.', 'service_agreement', 20, 1, 'Normal'),
(232, 39, 'job_order_request', 'Job Order Request Approved', 'Your Job Order Request for coc has been approved.', 'job_order', 28, 1, 'Normal'),
(233, 39, 'job_order_request', 'Job Order Request Approved', 'Your Job Order Request for coc has been approved.', 'job_order', 29, 1, 'Normal'),
(234, 1, 'job_application_submitted', 'New Job Application', 'Jerome Dela Cruz Jr. applied for Cashier at Gaisano - Bulua · Site: Bulua,  CDO.', 'job_application', 30, 1, 'Normal'),
(235, 2, 'job_application_submitted', 'New Job Application', 'Jerome Dela Cruz Jr. applied for Cashier at Gaisano - Bulua · Site: Bulua,  CDO.', 'job_application', 30, 0, 'Normal'),
(236, 1, 'job_application_submitted', 'New Job Application', 'Jerome Dela Cruz Jr. applied for Janitor at coc · Site: Carmen, CDO.', 'job_application', 31, 1, 'Normal'),
(237, 2, 'job_application_submitted', 'New Job Application', 'Jerome Dela Cruz Jr. applied for Janitor at coc · Site: Carmen, CDO.', 'job_application', 31, 0, 'Normal'),
(238, 41, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on Aug 13, 2026 at 9:00 AM', 'job_application', 31, 1, NULL),
(239, 41, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 31, 0, NULL),
(240, 39, 'endorsed_applicant', 'New Applicant Endorsed', 'Applicant Jerome Dela Cruz Jr. has been endorsed for Janitor at coc.', 'job_application', 31, 1, 'Normal'),
(241, 41, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 31, 0, NULL),
(242, 41, 'application_hired', 'Congratulations! You are Hired!', 'Welcome to the Onazul team! Your account has been upgraded to an Employee profile. Sign in with your current email and password to access your Employee Dashboard.', 'job_application', 31, 0, NULL),
(243, 39, 'job_order_request', 'Job Order Request Approved', 'Your Job Order Request for coc has been approved.', 'job_order', 30, 1, 'Normal'),
(244, 1, 'job_application_submitted', 'New Job Application', 'Chevrolet Camarro applied for Teacher at coc · Site: Carmen, CDO.', 'job_application', 32, 1, 'Normal'),
(245, 2, 'job_application_submitted', 'New Job Application', 'Chevrolet Camarro applied for Teacher at coc · Site: Carmen, CDO.', 'job_application', 32, 0, 'Normal'),
(246, 32, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on Aug 14, 2026 at 9:00 AM', 'job_application', 32, 1, NULL),
(247, 32, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 32, 1, NULL),
(248, 39, 'endorsed_applicant', 'New Applicant Endorsed', 'Applicant Chevrolet Camarro has been endorsed for Teacher at coc.', 'job_application', 32, 1, 'Normal'),
(249, 32, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 32, 1, NULL),
(250, 32, 'application_hired', 'Congratulations! You are Hired!', 'Welcome to the Onazul team! Your account has been upgraded to an Employee profile. Sign in with your current email and password to access your Employee Dashboard.', 'job_application', 32, 1, NULL),
(251, 1, 'job_application_submitted', 'New Job Application', 'Aaron Dela Cruz X applied for Teacher at coc · Site: Carmen, CDO.', 'job_application', 33, 1, 'Normal'),
(252, 2, 'job_application_submitted', 'New Job Application', 'Aaron Dela Cruz X applied for Teacher at coc · Site: Carmen, CDO.', 'job_application', 33, 0, 'Normal'),
(253, 43, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on Aug 15, 2026 at 10:00 AM', 'job_application', 33, 1, NULL),
(254, 43, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 33, 1, NULL),
(255, 39, 'endorsed_applicant', 'New Applicant Endorsed', 'Applicant Aaron Dela Cruz X has been endorsed for Teacher at coc.', 'job_application', 33, 1, 'Normal'),
(256, 43, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 33, 1, NULL),
(257, 43, 'application_hired', 'Congratulations! You are Hired!', 'Welcome to the Onazul team! Your account has been upgraded to an Employee profile. Sign in with your current email and password to access your Employee Dashboard.', 'job_application', 33, 1, NULL),
(258, 1, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(259, 2, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(260, 4, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(261, 8, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(262, 12, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(263, 13, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(264, 16, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(265, 17, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(266, 18, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(267, 19, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(268, 21, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal');
INSERT INTO `notifications` (`NotificationID`, `UserID`, `NotificationType`, `Title`, `Message`, `RelateedModule`, `RelatedRecord`, `IsRead`, `Priority`) VALUES
(269, 23, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(270, 24, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(271, 30, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(272, 33, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(273, 34, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 1, 'Normal'),
(274, 36, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 0, 'Normal'),
(275, 39, 'service_agreement_uploaded', 'New Active Service Agreement', 'Agreement 00000001 is now active. Contract duration: 2026-08-14 to 2030-08-14.', 'service_agreement', 21, 1, 'Normal'),
(276, 34, 'job_order_request', 'Job Order Request Approved', 'Your Job Order Request for Gaisano - Bulua has been approved.', 'job_order', 31, 1, 'Normal'),
(277, 1, 'job_application_submitted', 'New Job Application', 'Andrew Dela Cruz Jr. applied for Promo Diser at Gaisano - Bulua · Site: Bulua,  CDO.', 'job_application', 34, 0, 'Normal'),
(278, 2, 'job_application_submitted', 'New Job Application', 'Andrew Dela Cruz Jr. applied for Promo Diser at Gaisano - Bulua · Site: Bulua,  CDO.', 'job_application', 34, 0, 'Normal'),
(279, 44, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on Aug 15, 2026 at 10:00 AM', 'job_application', 34, 1, NULL),
(280, 44, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.', 'job_application', 34, 1, NULL),
(281, 34, 'endorsed_applicant', 'New Applicant Endorsed', 'Applicant Andrew Dela Cruz Jr. has been endorsed for Promo Diser at Gaisano - Bulua.', 'job_application', 34, 1, 'Normal'),
(282, 44, 'client_approved', 'Approved by Company', 'Your application has been approved by the client company.', 'job_application', 34, 1, NULL),
(283, 44, 'application_hired', 'Congratulations! You are Hired!', 'Welcome to the Onazul team! Your account has been upgraded to an Employee profile. Sign in with your current email and password to access your Employee Dashboard.', 'job_application', 34, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `overtime`
--

CREATE TABLE `overtime` (
  `OvertimeID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `AttendanceID` int(11) DEFAULT NULL,
  `DeploymentID` int(11) DEFAULT NULL,
  `OvertimeDate` date DEFAULT NULL,
  `RegularHours` decimal(5,2) DEFAULT NULL,
  `OvertimeHours` decimal(5,2) DEFAULT NULL,
  `NightDiffHours` decimal(5,2) DEFAULT NULL,
  `OvertimeType` varchar(50) DEFAULT NULL,
  `OvertimeRate` decimal(5,2) DEFAULT NULL,
  `TotalOvertimePay` decimal(10,2) DEFAULT NULL,
  `RequestedBy` int(11) DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `ApprovalDate` datetime DEFAULT NULL,
  `StatusID` int(11) DEFAULT NULL,
  `Remarks` text DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `Payroll_ID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `PayPeriodStart` date DEFAULT NULL,
  `PayPeriodEnd` date DEFAULT NULL,
  `TotalDaysWorked` decimal(5,2) DEFAULT NULL,
  `TotalHoursWorked` decimal(7,2) DEFAULT NULL,
  `RegularHours` decimal(7,2) DEFAULT NULL,
  `OvertimeHours` decimal(7,2) DEFAULT NULL,
  `BasicSalary` decimal(12,2) DEFAULT NULL,
  `OvertimePay` decimal(12,2) DEFAULT NULL,
  `NightDifferentialPay` decimal(12,2) DEFAULT NULL,
  `HolidayPay` decimal(12,2) DEFAULT NULL,
  `Allowances` decimal(12,2) DEFAULT NULL,
  `GrossPay` decimal(12,2) DEFAULT NULL,
  `SSSDeduction` decimal(10,2) DEFAULT NULL,
  `PhilHealthDeduction` decimal(10,2) DEFAULT NULL,
  `PagIBIGDeduction` decimal(10,2) DEFAULT NULL,
  `TaxDeduction` decimal(10,2) DEFAULT NULL,
  `LoanDeduction` decimal(10,2) DEFAULT NULL,
  `TotalDeductions` decimal(12,2) DEFAULT NULL,
  `NetPay` decimal(12,2) DEFAULT NULL,
  `PaymentDate` date DEFAULT NULL,
  `Payment` varchar(50) DEFAULT NULL,
  `ProcessedBy` int(11) DEFAULT NULL,
  `ProcessedDate` datetime DEFAULT NULL,
  `PayslipGenerated` tinyint(1) DEFAULT 0,
  `PayslipSent` tinyint(1) DEFAULT 0,
  `Remarks` text DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `HolidayID` int(11) DEFAULT NULL,
  `SalaryRatesID` int(11) DEFAULT NULL,
  `DTR_SubmissionID` int(11) DEFAULT NULL,
  `StatusID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_adjustments`
--

CREATE TABLE `payroll_adjustments` (
  `AdjustmentID` int(11) NOT NULL,
  `Payroll_ID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `AdjustmentType` varchar(50) DEFAULT NULL,
  `Amount` decimal(10,2) DEFAULT NULL,
  `IsAddition` tinyint(1) DEFAULT 1,
  `Reason` text DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_audit_log`
--

CREATE TABLE `payroll_audit_log` (
  `LogID` int(11) NOT NULL,
  `Payroll_ID` int(11) NOT NULL,
  `Action` varchar(100) DEFAULT NULL,
  `PerformedBy` int(11) DEFAULT NULL,
  `ActionDate` datetime DEFAULT current_timestamp(),
  `OldValue` text DEFAULT NULL,
  `NewValue` text DEFAULT NULL,
  `IPAddress` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_runs`
--

CREATE TABLE `payroll_runs` (
  `PayrollRunID` int(10) UNSIGNED NOT NULL,
  `RunCode` varchar(32) NOT NULL,
  `CompanyID` int(11) NOT NULL,
  `PeriodStart` date NOT NULL,
  `PeriodEnd` date NOT NULL,
  `CutoffLabel` varchar(120) DEFAULT NULL,
  `Status` varchar(32) NOT NULL DEFAULT 'Draft',
  `AdminOverheadPct` decimal(5,2) NOT NULL DEFAULT 10.00,
  `VatPct` decimal(5,2) NOT NULL DEFAULT 12.00,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `ApprovedAt` datetime DEFAULT NULL,
  `BillingRefNo` varchar(32) DEFAULT NULL,
  `DeploymentSite` varchar(255) DEFAULT NULL,
  `TotalInvoice` decimal(14,2) DEFAULT NULL,
  `CurrentStatus` varchar(64) NOT NULL DEFAULT 'Pending DTR'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll_runs`
--

INSERT INTO `payroll_runs` (`PayrollRunID`, `RunCode`, `CompanyID`, `PeriodStart`, `PeriodEnd`, `CutoffLabel`, `Status`, `AdminOverheadPct`, `VatPct`, `CreatedBy`, `CreatedAt`, `ApprovedAt`, `BillingRefNo`, `DeploymentSite`, `TotalInvoice`, `CurrentStatus`) VALUES
(1, 'PR-2026-07B-C12', 12, '2026-07-16', '2026-07-31', 'Jul 16, 2026 – Jul 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', NULL, 'BR-2026-07B-C12', 'Cogon, CDO', NULL, 'Pending DTR'),
(2, 'PR-2026-07A-C12', 12, '2026-07-01', '2026-07-15', 'Jul 1, 2026 – Jul 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', '2026-07-23 12:39:38', 'BR-2026-07A-C12', 'Cogon, CDO', NULL, 'Pending DTR'),
(3, 'PR-2026-07B-C17', 17, '2026-07-16', '2026-07-31', 'Jul 16, 2026 – Jul 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', NULL, 'BR-2026-07B-C17', 'Uptown, CDO', NULL, 'Pending DTR'),
(4, 'PR-2026-07A-C17', 17, '2026-07-01', '2026-07-15', 'Jul 1, 2026 – Jul 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', '2026-07-23 12:39:38', 'BR-2026-07A-C17', 'Uptown, CDO', NULL, 'Pending DTR'),
(5, 'PR-2026-07B-C1', 1, '2026-07-16', '2026-07-31', 'Jul 16, 2026 – Jul 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', NULL, 'BR-2026-07B-C1', 'Velez', NULL, 'Pending DTR'),
(6, 'PR-2026-07A-C1', 1, '2026-07-01', '2026-07-15', 'Jul 1, 2026 – Jul 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', '2026-07-23 12:39:38', 'BR-2026-07A-C1', 'Velez', NULL, 'Pending DTR'),
(7, 'PR-2026-07B-C16', 16, '2026-07-16', '2026-07-31', 'Jul 16, 2026 – Jul 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', NULL, 'BR-2026-07B-C16', 'Macanhan, Carmen, CDO', NULL, 'Pending DTR'),
(8, 'PR-2026-07A-C16', 16, '2026-07-01', '2026-07-15', 'Jul 1, 2026 – Jul 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', '2026-07-23 12:39:38', 'BR-2026-07A-C16', 'Macanhan, Carmen, CDO', NULL, 'Pending DTR'),
(9, 'PR-2026-07B-C9', 9, '2026-07-16', '2026-07-31', 'Jul 16, 2026 – Jul 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', NULL, 'BR-2026-07B-C9', 'Sm uptown, CDo', NULL, 'Pending DTR'),
(10, 'PR-2026-07A-C9', 9, '2026-07-01', '2026-07-15', 'Jul 1, 2026 – Jul 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-07-23 18:39:38', '2026-07-23 12:39:38', 'BR-2026-07A-C9', 'Sm uptown, CDo', NULL, 'Pending DTR'),
(91, 'PR-2026-08A-C12', 12, '2026-08-01', '2026-08-15', 'Aug 1, 2026 – Aug 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-04 15:23:08', NULL, 'BR-2026-08A-C12', 'Cogon, CDO', 394.76, 'Pending DTR'),
(93, 'PR-2026-08A-C19', 19, '2026-08-01', '2026-08-15', 'Aug 1, 2026 – Aug 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-04 15:23:08', NULL, 'BR-2026-08A-C19', 'Bulua,  CDO', 1937.66, 'Pending DTR'),
(94, 'PR-2026-07B-C19', 19, '2026-07-16', '2026-07-31', 'Jul 16, 2026 – Jul 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-04 15:23:09', '2026-08-04 09:23:09', 'BR-2026-07B-C19', 'Bulua,  CDO', NULL, 'Pending DTR'),
(95, 'PR-2026-08A-C17', 17, '2026-08-01', '2026-08-15', 'Aug 1, 2026 – Aug 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-04 15:23:09', NULL, 'BR-2026-08A-C17', 'Uptown, CDO', 394.76, 'Pending DTR'),
(97, 'PR-2026-08A-C1', 1, '2026-08-01', '2026-08-15', 'Aug 1, 2026 – Aug 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-04 15:23:09', NULL, 'BR-2026-08A-C1', 'Velez', NULL, 'Pending DTR'),
(99, 'PR-2026-08A-C16', 16, '2026-08-01', '2026-08-15', 'Aug 1, 2026 – Aug 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-04 15:23:09', NULL, 'BR-2026-08A-C16', 'Macanhan, Carmen, CDO', NULL, 'Pending DTR'),
(101, 'PR-2026-08A-C9', 9, '2026-08-01', '2026-08-15', 'Aug 1, 2026 – Aug 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-04 15:23:09', NULL, 'BR-2026-08A-C9', 'Sm uptown, CDo', NULL, 'Pending DTR'),
(251, 'PR-2026-08A-C20', 20, '2026-08-01', '2026-08-15', 'Aug 1, 2026 – Aug 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-11 18:35:36', NULL, 'BR-2026-08A-C20', 'Puerto, Cagayan de Oro City', NULL, 'Payroll Computed'),
(312, 'PR-2026-08A-C21', 21, '2026-08-01', '2026-08-15', 'Aug 1, 2026 – Aug 15, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-14 14:51:15', NULL, 'BR-2026-08A-C21', 'Carmen, CDO', 5136.52, 'Payroll Computed'),
(522, 'PR-2026-08B-C21', 21, '2026-08-16', '2026-08-31', 'Aug 16, 2026 – Aug 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-17 13:28:00', NULL, 'BR-2026-08B-C21', 'Carmen, CDO', NULL, 'Payroll Computed'),
(524, 'PR-2026-08B-C12', 12, '2026-08-16', '2026-08-31', 'Aug 16, 2026 – Aug 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-17 13:28:00', NULL, 'BR-2026-08B-C12', 'Cogon, CDO', NULL, 'Payroll Computed'),
(526, 'PR-2026-08B-C19', 19, '2026-08-16', '2026-08-31', 'Aug 16, 2026 – Aug 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-17 13:28:01', NULL, 'BR-2026-08B-C19', 'Bulua,  CDO', NULL, 'Payroll Computed'),
(528, 'PR-2026-08B-C20', 20, '2026-08-16', '2026-08-31', 'Aug 16, 2026 – Aug 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-17 13:28:01', NULL, 'BR-2026-08B-C20', 'Puerto, Cagayan de Oro City', NULL, 'Payroll Computed'),
(530, 'PR-2026-08B-C17', 17, '2026-08-16', '2026-08-31', 'Aug 16, 2026 – Aug 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-17 13:28:01', NULL, 'BR-2026-08B-C17', 'Uptown, CDO', NULL, 'Payroll Computed'),
(532, 'PR-2026-08B-C1', 1, '2026-08-16', '2026-08-31', 'Aug 16, 2026 – Aug 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-17 13:28:01', NULL, 'BR-2026-08B-C1', 'Velez', NULL, 'Payroll Computed'),
(534, 'PR-2026-08B-C16', 16, '2026-08-16', '2026-08-31', 'Aug 16, 2026 – Aug 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-17 13:28:01', NULL, 'BR-2026-08B-C16', 'Macanhan, Carmen, CDO', NULL, 'Payroll Computed'),
(536, 'PR-2026-08B-C9', 9, '2026-08-16', '2026-08-31', 'Aug 16, 2026 – Aug 31, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-17 13:28:02', NULL, 'BR-2026-08B-C9', 'Sm uptown, CDo', NULL, 'Payroll Computed'),
(570, 'PR-20260811-20260825-C1', 1, '2026-08-11', '2026-08-25', 'Aug 11, 2026 – Aug 25, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260811-20260825-C1', 'Velez', NULL, 'Ready for Salary Release'),
(571, 'PR-20260726-20260810-C1', 1, '2026-07-26', '2026-08-10', 'Jul 26, 2026 – Aug 10, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260726-20260810-C1', 'Velez', NULL, 'Ready for Salary Release'),
(572, 'PR-20260811-20260825-C9', 9, '2026-08-11', '2026-08-25', 'Aug 11, 2026 – Aug 25, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260811-20260825-C9', 'Sm uptown, CDo', NULL, 'Ready for Salary Release'),
(573, 'PR-20260726-20260810-C9', 9, '2026-07-26', '2026-08-10', 'Jul 26, 2026 – Aug 10, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260726-20260810-C9', 'Sm uptown, CDo', NULL, 'Ready for Salary Release'),
(574, 'PR-20260811-20260825-C12', 12, '2026-08-11', '2026-08-25', 'Aug 11, 2026 – Aug 25, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260811-20260825-C12', 'Cogon, CDO', NULL, 'Ready for Salary Release'),
(575, 'PR-20260726-20260810-C12', 12, '2026-07-26', '2026-08-10', 'Jul 26, 2026 – Aug 10, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260726-20260810-C12', 'Cogon, CDO', NULL, 'Ready for Salary Release'),
(576, 'PR-20260811-20260825-C16', 16, '2026-08-11', '2026-08-25', 'Aug 11, 2026 – Aug 25, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260811-20260825-C16', 'Macanhan, Carmen, CDO', NULL, 'Ready for Salary Release'),
(577, 'PR-20260726-20260810-C16', 16, '2026-07-26', '2026-08-10', 'Jul 26, 2026 – Aug 10, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260726-20260810-C16', 'Macanhan, Carmen, CDO', NULL, 'Ready for Salary Release'),
(578, 'PR-20260811-20260825-C17', 17, '2026-08-11', '2026-08-25', 'Aug 11, 2026 – Aug 25, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260811-20260825-C17', 'Uptown, CDO', NULL, 'Ready for Salary Release'),
(579, 'PR-20260726-20260810-C17', 17, '2026-07-26', '2026-08-10', 'Jul 26, 2026 – Aug 10, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:12', NULL, 'BR-20260726-20260810-C17', 'Uptown, CDO', NULL, 'Ready for Salary Release'),
(580, 'PR-20260811-20260825-C19', 19, '2026-08-11', '2026-08-25', 'Aug 11, 2026 – Aug 25, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:13', NULL, 'BR-20260811-20260825-C19', 'Bulua,  CDO', NULL, 'Ready for Salary Release'),
(581, 'PR-20260726-20260810-C19', 19, '2026-07-26', '2026-08-10', 'Jul 26, 2026 – Aug 10, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:13', NULL, 'BR-20260726-20260810-C19', 'Bulua,  CDO', NULL, 'Ready for Salary Release'),
(582, 'PR-20260811-20260825-C20', 20, '2026-08-11', '2026-08-25', 'Aug 11, 2026 – Aug 25, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:13', NULL, 'BR-20260811-20260825-C20', 'Puerto, Cagayan de Oro City', NULL, 'Ready for Salary Release'),
(583, 'PR-20260726-20260810-C20', 20, '2026-07-26', '2026-08-10', 'Jul 26, 2026 – Aug 10, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:13', NULL, 'BR-20260726-20260810-C20', 'Puerto, Cagayan de Oro City', NULL, 'Ready for Salary Release'),
(584, 'PR-20260811-20260825-C21', 21, '2026-08-11', '2026-08-25', 'Aug 11, 2026 – Aug 25, 2026', 'Approved', 10.00, 12.00, NULL, '2026-08-19 09:49:13', NULL, 'BR-20260811-20260825-C21', 'Carmen, CDO', NULL, 'Ready for Salary Release');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_status_history`
--

CREATE TABLE `payroll_status_history` (
  `HistoryID` int(10) UNSIGNED NOT NULL,
  `PayrollRunID` int(10) UNSIGNED NOT NULL,
  `Status` varchar(64) NOT NULL,
  `Remarks` text DEFAULT NULL,
  `AttachmentPath` varchar(255) DEFAULT NULL,
  `UpdatedBy` int(11) NOT NULL,
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payslip`
--

CREATE TABLE `payslip` (
  `PayslipID` int(11) NOT NULL,
  `Payroll_ID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `PayslipNumber` varchar(50) DEFAULT NULL,
  `GeneratedDate` datetime DEFAULT NULL,
  `SentDate` datetime DEFAULT NULL,
  `ViewedByEmployee` tinyint(1) DEFAULT 0,
  `ViewDate` datetime DEFAULT NULL,
  `DownloadCount` int(11) DEFAULT 0,
  `LastDownloadDate` datetime DEFAULT NULL,
  `PDFFilePath` varchar(255) DEFAULT NULL,
  `DeliveryMethod` varchar(50) DEFAULT NULL,
  `EmailSent` tinyint(1) DEFAULT 0,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance`
--

CREATE TABLE `performance` (
  `PerformanceID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `ReviewerID` int(11) DEFAULT NULL,
  `ReviewerPeriod` varchar(50) DEFAULT NULL,
  `OverallScore` decimal(5,2) DEFAULT NULL,
  `Rating` varchar(20) DEFAULT NULL,
  `Goals` text DEFAULT NULL,
  `Achievements` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resignationrequest`
--

CREATE TABLE `resignationrequest` (
  `ResignationID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `SubmissionDate` date DEFAULT NULL,
  `EffectiveDate` date DEFAULT NULL,
  `ResignationType` varchar(50) DEFAULT NULL,
  `Reason` text DEFAULT NULL,
  `ClearanceFormPath` varchar(255) DEFAULT NULL,
  `ClearanceApprovedDate` date DEFAULT NULL,
  `QuitclaimDocumentPath` varchar(255) DEFAULT NULL,
  `QuitclaimSignedDate` date DEFAULT NULL,
  `FinalPayAmount` decimal(12,2) DEFAULT NULL,
  `FinalPayComputationDetails` text DEFAULT NULL,
  `FinalPay` decimal(12,2) DEFAULT NULL,
  `FinalPayDate` date DEFAULT NULL,
  `ProcessedBy` int(11) DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `CompletedDate` date DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `StatusID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `RolesID` int(11) NOT NULL,
  `RoleName` varchar(50) NOT NULL,
  `Description` text DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `ModifiedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`RolesID`, `RoleName`, `Description`, `CreatedAt`, `ModifiedAt`) VALUES
(1, 'Admin', 'Administrator role (auto-created by create_admin.php)', '2026-02-20 21:05:17', '2026-02-20 21:05:17'),
(2, 'HR', 'HR role (auto-created by create_hr.php)', '2026-02-20 21:05:27', '2026-02-20 21:05:27'),
(3, 'Accounting', 'Test role', '2026-02-21 01:05:48', '2026-02-21 01:05:48'),
(4, 'TeamLead', 'Test role', '2026-02-21 01:05:48', '2026-02-21 01:05:48'),
(5, 'Applicant', 'Test role', '2026-02-21 01:05:49', '2026-02-21 01:05:49'),
(6, 'Employee', 'Test role', '2026-02-21 01:05:49', '2026-02-21 01:05:49');

-- --------------------------------------------------------

--
-- Table structure for table `salary_rates`
--

CREATE TABLE `salary_rates` (
  `SalaryRatesID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `DeploymentID` int(11) DEFAULT NULL,
  `CompanyID` int(11) DEFAULT NULL,
  `RateType` varchar(50) DEFAULT NULL,
  `BasicRate` decimal(10,2) DEFAULT NULL,
  `OvertimeRate` decimal(10,2) DEFAULT NULL,
  `NightDiffRate` decimal(10,2) DEFAULT NULL,
  `HolidayRate` decimal(10,2) DEFAULT NULL,
  `EffectiveDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `CreatedBy` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `serviceagreement`
--

CREATE TABLE `serviceagreement` (
  `ServiceAgreementID` int(11) NOT NULL,
  `ClientCompanyID` int(11) NOT NULL,
  `ContractNumber` varchar(50) DEFAULT NULL,
  `ContractStartDate` date DEFAULT NULL,
  `ContractEndDate` date DEFAULT NULL,
  `Contract` text DEFAULT NULL,
  `SignedDocumentPath` varchar(255) DEFAULT NULL,
  `BillingTerms` text DEFAULT NULL,
  `PaymentTerms` text DEFAULT NULL,
  `AgencyFeePercentage` decimal(5,2) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT current_timestamp(),
  `ApprovedBy` int(11) DEFAULT NULL,
  `ApprovedDate` datetime DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `Status` enum('Active','Expired') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `serviceagreement`
--

INSERT INTO `serviceagreement` (`ServiceAgreementID`, `ClientCompanyID`, `ContractNumber`, `ContractStartDate`, `ContractEndDate`, `Contract`, `SignedDocumentPath`, `BillingTerms`, `PaymentTerms`, `AgencyFeePercentage`, `CreatedBy`, `CreatedDate`, `ApprovedBy`, `ApprovedDate`, `Notes`, `Status`) VALUES
(1, 1, '12345', '2026-02-21', '2030-01-01', NULL, 'uploads/service_agreements/sa_1_1771655555.jpg', NULL, NULL, NULL, 1, '2026-02-21 14:32:35', NULL, NULL, '', 'Active'),
(2, 3, '0923496660', '2026-02-21', '2030-02-21', NULL, 'uploads/service_agreements/sa_3_1771662164.jpg', NULL, NULL, NULL, 1, '2026-02-21 16:22:44', NULL, NULL, '', 'Active'),
(3, 4, '09234966660', '2026-03-21', '2030-03-21', NULL, 'uploads/service_agreements/sa_4_1774044129.png', NULL, NULL, NULL, 1, '2026-03-21 06:02:09', NULL, NULL, '', 'Active'),
(4, 5, '123456798', '2026-03-24', '2030-06-24', NULL, 'uploads/service_agreements/sa_5_1774336535.pdf', NULL, NULL, NULL, 1, '2026-03-24 15:15:35', NULL, NULL, '', 'Active'),
(5, 8, '09234966660', '2026-03-30', '2030-07-10', NULL, 'uploads/service_agreements/sa_8_1774843055.jpg', NULL, NULL, NULL, 1, '2026-03-30 11:57:35', NULL, NULL, '', 'Active'),
(6, 9, '12234', '2026-03-30', '2030-03-30', NULL, 'uploads/service_agreements/sa_9_1774844584.jpg', NULL, NULL, NULL, 1, '2026-03-30 12:23:04', NULL, NULL, '', 'Active'),
(7, 10, '09231469666', '2026-03-30', '2030-07-30', NULL, 'uploads/service_agreements/sa_10_1774856034.jpg', NULL, NULL, NULL, 1, '2026-03-30 15:33:54', NULL, NULL, '', 'Active'),
(8, 11, '092349666660', '2026-03-30', '2027-03-30', NULL, 'uploads/service_agreements/sa_11_1774860420.jpg', NULL, NULL, NULL, 1, '2026-03-30 16:47:00', NULL, NULL, '', 'Active'),
(9, 12, '123456575678', '2026-04-25', '2028-04-25', NULL, 'uploads/service_agreements/sa_12_1777086073.jpg', NULL, NULL, NULL, 1, '2026-04-25 11:01:13', NULL, NULL, '', 'Active'),
(10, 13, '12345678', '2026-04-25', '2028-04-25', NULL, 'uploads/service_agreements/sa_13_1777087194.jpg', NULL, NULL, NULL, 1, '2026-04-25 11:19:54', NULL, NULL, '', 'Active'),
(11, 14, '123456', '2026-04-28', '2028-04-28', NULL, 'uploads/service_agreements/sa_14_1777347937.pdf', NULL, NULL, NULL, 1, '2026-04-28 11:45:37', NULL, NULL, '', 'Active'),
(12, 2, '123123123', '2026-05-18', '2030-05-18', NULL, 'uploads/service_agreements/sa_2_1779115495.pdf', NULL, NULL, NULL, 1, '2026-05-18 22:44:55', NULL, NULL, '', 'Active'),
(13, 15, '123456789', '2026-06-03', '2028-06-03', NULL, 'uploads/service_agreements/sa_15_1780469391.pdf', NULL, NULL, NULL, 1, '2026-06-03 14:49:51', NULL, NULL, '', 'Active'),
(14, 7, '3123214124124', '2026-02-02', '2027-06-05', NULL, 'uploads/service_agreements/sa_7_1780584292.png', NULL, NULL, NULL, 1, '2026-06-04 22:44:52', NULL, NULL, '', 'Active'),
(15, 16, '123455', '2026-06-09', '2027-06-09', NULL, 'uploads/service_agreements/sa_16_1781008541.pdf', NULL, NULL, NULL, 1, '2026-06-09 20:35:41', NULL, NULL, '', 'Active'),
(16, 17, '1234567', '2026-07-08', '2028-07-08', NULL, 'uploads/service_agreements/sa_17_1783500634.pdf', NULL, NULL, NULL, 1, '2026-07-08 16:50:34', NULL, NULL, '', 'Active'),
(17, 18, '1231312312312313', '2026-07-23', '2030-07-23', NULL, 'uploads/service_agreements/sa_18_1784821704.pdf', NULL, NULL, NULL, 1, '2026-07-23 23:48:24', NULL, NULL, '', 'Active'),
(18, 19, '231313123123123123123123', '2026-07-30', '2030-07-30', NULL, 'uploads/service_agreements/sa_19_1785415029.pdf', NULL, NULL, NULL, 1, '2026-07-30 20:37:09', NULL, NULL, '', 'Active'),
(19, 20, '13123123123123', '2026-08-05', '2026-08-05', NULL, 'uploads/service_agreements/sa_20_1785896602.pdf', NULL, NULL, NULL, 1, '2026-08-05 10:23:22', NULL, NULL, '', 'Active'),
(20, 21, 'SA-2026-000001', '2026-08-06', '2026-08-06', NULL, 'uploads/service_agreements/sa_client_1785982559.pdf', NULL, NULL, NULL, 1, '2026-08-06 10:15:59', NULL, NULL, '', 'Active'),
(21, 21, '00000001', '2026-08-14', '2030-08-14', NULL, 'uploads/service_agreements/sa_21_1786638133.pdf', NULL, NULL, NULL, 1, '2026-08-14 00:22:13', NULL, NULL, '', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `sss_rates_config`
--

CREATE TABLE `sss_rates_config` (
  `id` int(10) UNSIGNED NOT NULL,
  `salary_min` decimal(12,2) NOT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `monthly_salary_credit` decimal(12,2) NOT NULL,
  `regular_ss_er` decimal(10,2) NOT NULL DEFAULT 0.00,
  `regular_ss_ee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mpf_er` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mpf_ee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ec_er` decimal(10,2) NOT NULL DEFAULT 10.00,
  `effective_year` smallint(5) UNSIGNED NOT NULL DEFAULT 2025,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sss_rates_config`
--

INSERT INTO `sss_rates_config` (`id`, `salary_min`, `salary_max`, `monthly_salary_credit`, `regular_ss_er`, `regular_ss_ee`, `mpf_er`, `mpf_ee`, `ec_er`, `effective_year`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 0.00, 5249.99, 5000.00, 500.00, 250.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(2, 5250.00, 5749.99, 5500.00, 550.00, 275.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(3, 5750.00, 6249.99, 6000.00, 600.00, 300.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(4, 6250.00, 6749.99, 6500.00, 650.00, 325.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(5, 6750.00, 7249.99, 7000.00, 700.00, 350.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(6, 7250.00, 7749.99, 7500.00, 750.00, 375.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(7, 7750.00, 8249.99, 8000.00, 800.00, 400.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(8, 8250.00, 8749.99, 8500.00, 850.00, 425.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(9, 8750.00, 9249.99, 9000.00, 900.00, 450.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(10, 9250.00, 9749.99, 9500.00, 950.00, 475.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(11, 9750.00, 10249.99, 10000.00, 1000.00, 500.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(12, 10250.00, 10749.99, 10500.00, 1050.00, 525.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(13, 10750.00, 11249.99, 11000.00, 1100.00, 550.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(14, 11250.00, 11749.99, 11500.00, 1150.00, 575.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(15, 11750.00, 12249.99, 12000.00, 1200.00, 600.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(16, 12250.00, 12749.99, 12500.00, 1250.00, 625.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(17, 12750.00, 13249.99, 13000.00, 1300.00, 650.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(18, 13250.00, 13749.99, 13500.00, 1350.00, 675.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(19, 13750.00, 14249.99, 14000.00, 1400.00, 700.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(20, 14250.00, 14749.99, 14500.00, 1450.00, 725.00, 0.00, 0.00, 10.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(21, 14750.00, 15249.99, 15000.00, 1500.00, 750.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(22, 15250.00, 15749.99, 15500.00, 1550.00, 775.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(23, 15750.00, 16249.99, 16000.00, 1600.00, 800.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(24, 16250.00, 16749.99, 16500.00, 1650.00, 825.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(25, 16750.00, 17249.99, 17000.00, 1700.00, 850.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(26, 17250.00, 17749.99, 17500.00, 1750.00, 875.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(27, 17750.00, 18249.99, 18000.00, 1800.00, 900.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(28, 18250.00, 18749.99, 18500.00, 1850.00, 925.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(29, 18750.00, 19249.99, 19000.00, 1900.00, 950.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(30, 19250.00, 19749.99, 19500.00, 1950.00, 975.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(31, 19750.00, 20249.99, 20000.00, 2000.00, 1000.00, 0.00, 0.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(32, 20250.00, 20749.99, 20500.00, 2000.00, 1000.00, 50.00, 25.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(33, 20750.00, 21249.99, 21000.00, 2000.00, 1000.00, 100.00, 50.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(34, 21250.00, 21749.99, 21500.00, 2000.00, 1000.00, 150.00, 75.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(35, 21750.00, 22249.99, 22000.00, 2000.00, 1000.00, 200.00, 100.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(36, 22250.00, 22749.99, 22500.00, 2000.00, 1000.00, 250.00, 125.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(37, 22750.00, 23249.99, 23000.00, 2000.00, 1000.00, 300.00, 150.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(38, 23250.00, 23749.99, 23500.00, 2000.00, 1000.00, 350.00, 175.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(39, 23750.00, 24249.99, 24000.00, 2000.00, 1000.00, 400.00, 200.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(40, 24250.00, 24749.99, 24500.00, 2000.00, 1000.00, 450.00, 225.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(41, 24750.00, 25249.99, 25000.00, 2000.00, 1000.00, 500.00, 250.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(42, 25250.00, 25749.99, 25500.00, 2000.00, 1000.00, 550.00, 275.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(43, 25750.00, 26249.99, 26000.00, 2000.00, 1000.00, 600.00, 300.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(44, 26250.00, 26749.99, 26500.00, 2000.00, 1000.00, 650.00, 325.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(45, 26750.00, 27249.99, 27000.00, 2000.00, 1000.00, 700.00, 350.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(46, 27250.00, 27749.99, 27500.00, 2000.00, 1000.00, 750.00, 375.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(47, 27750.00, 28249.99, 28000.00, 2000.00, 1000.00, 800.00, 400.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(48, 28250.00, 28749.99, 28500.00, 2000.00, 1000.00, 850.00, 425.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(49, 28750.00, 29249.99, 29000.00, 2000.00, 1000.00, 900.00, 450.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(50, 29250.00, 29749.99, 29500.00, 2000.00, 1000.00, 950.00, 475.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(51, 29750.00, 30249.99, 30000.00, 2000.00, 1000.00, 1000.00, 500.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(52, 30250.00, 30749.99, 30500.00, 2000.00, 1000.00, 1050.00, 525.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(53, 30750.00, 31249.99, 31000.00, 2000.00, 1000.00, 1100.00, 550.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(54, 31250.00, 31749.99, 31500.00, 2000.00, 1000.00, 1150.00, 575.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(55, 31750.00, 32249.99, 32000.00, 2000.00, 1000.00, 1200.00, 600.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(56, 32250.00, 32749.99, 32500.00, 2000.00, 1000.00, 1250.00, 625.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(57, 32750.00, 33249.99, 33000.00, 2000.00, 1000.00, 1300.00, 650.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(58, 33250.00, 33749.99, 33500.00, 2000.00, 1000.00, 1350.00, 675.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(59, 33750.00, 34249.99, 34000.00, 2000.00, 1000.00, 1400.00, 700.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(60, 34250.00, 34749.99, 34500.00, 2000.00, 1000.00, 1450.00, 725.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18'),
(61, 34750.00, NULL, 35000.00, 2000.00, 1000.00, 1500.00, 750.00, 30.00, 2025, 1, '2026-07-16 15:02:18', '2026-07-16 15:02:18');

-- --------------------------------------------------------

--
-- Table structure for table `status`
--

CREATE TABLE `status` (
  `StatusID` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status`
--

INSERT INTO `status` (`StatusID`, `Name`) VALUES
(1, 'For Interview'),
(2, 'Endorsed'),
(3, 'Hired');

-- --------------------------------------------------------

--
-- Table structure for table `systemsettings`
--

CREATE TABLE `systemsettings` (
  `SettingID` int(11) NOT NULL,
  `SettingKey` varchar(100) NOT NULL,
  `SettingValue` text DEFAULT NULL,
  `SettingType` varchar(50) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Category` varchar(50) DEFAULT NULL,
  `IsEditable` tinyint(1) DEFAULT 1,
  `UpdatedBy` int(11) DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `username` varchar(100) NOT NULL DEFAULT 'System/Guest',
  `role` varchar(50) NOT NULL DEFAULT 'Visitor',
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '127.0.0.1',
  `browser` varchar(100) DEFAULT 'Chrome',
  `operating_system` varchar(100) DEFAULT 'Windows 10/11',
  `device` varchar(50) DEFAULT 'Desktop',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`log_id`, `user_id`, `username`, `role`, `module`, `action`, `description`, `reference_id`, `ip_address`, `browser`, `operating_system`, `device`, `created_at`) VALUES
(1, 1, 'System Administrator', 'Administrator', 'Auth', 'Login', 'System Administrator logged in to Command Center.', '1', '127.0.0.1', 'Chrome', 'Windows 10/11', 'Desktop', '2026-08-03 13:15:54'),
(2, 1, 'System Administrator', 'Administrator', 'Settings', 'Update', 'Government deduction settings updated for 2026.', '1', '127.0.0.1', 'Chrome', 'Windows 10/11', 'Desktop', '2026-08-03 13:15:54'),
(3, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-03 13:26:59'),
(4, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-03 13:34:58'),
(5, 1, 'System Administrator', 'Admin', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-03 13:37:47'),
(6, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-03 13:37:58'),
(7, 1, 'System Administrator', 'Admin', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-03 15:56:10'),
(8, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-03 15:56:19'),
(9, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-03 16:04:34'),
(10, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-04 05:57:17'),
(11, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Login', 'User Jhonel Dela Cruz logged in successfully.', '34', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-04 06:20:25'),
(12, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-04 06:48:50'),
(13, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Login', 'User Jhonel Dela Cruz logged in successfully.', '34', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-04 06:49:10'),
(14, 3, 'Accounting User', 'Accounting', 'Auth', 'Login', 'User Accounting User logged in successfully.', '3', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-04 07:23:05'),
(15, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-05 00:21:45'),
(16, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Login', 'User Jhonel Dela Cruz logged in successfully.', '34', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-05 00:44:30'),
(17, 1, 'System Administrator', 'Admin', 'Company', 'Create', 'Client Company \'Gaisano Puerto\' was registered.', '20', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-05 02:19:53'),
(18, 1, 'System Administrator', 'Admin', 'Company', 'Update', 'Client Company \'Gaisano Puerto\' was updated.', '20', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-05 02:23:22'),
(19, 1, 'System Administrator', 'Admin', 'Service Agreement', 'Approve', 'Service Agreement #13123123123123 uploaded and approved for Gaisano Puerto.', '19', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-05 02:23:22'),
(20, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-05 02:23:29'),
(21, 36, 'Merry Joy', 'TeamLead', 'Auth', 'Login', 'User Merry Joy logged in successfully.', '36', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-05 02:24:01'),
(22, 37, 'Style91998', 'Applicant', 'Applicant', 'Registration', 'Applicant Style91998 registered an account.', '37', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-05 03:01:43'),
(23, 1, 'System Administrator', 'Admin', 'Applicant', 'Endorse', 'Applicant Cammille Roxas was endorsed to Gaisano Puerto.', '29', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-05 03:10:24'),
(25, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-06 02:02:10'),
(26, 1, 'System Administrator', 'Admin', 'Company', 'Create', 'Client Company \'coc\' was registered with Service Agreement #SA-2026-000001.', '21', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-06 02:15:59'),
(27, 39, 'Drueew', 'TeamLead', 'Auth', 'Login', 'User Drueew logged in successfully.', '39', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-06 02:16:52'),
(28, 40, 'F04615934', 'Applicant', 'Applicant', 'Registration', 'Applicant F04615934 registered an account.', '40', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-06 03:01:22'),
(29, 40, 'F04615934', 'Applicant', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-06 03:01:45'),
(30, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-08 12:35:26'),
(31, 3, 'Accounting User', 'Accounting', 'Auth', 'Login', 'User Accounting User logged in successfully.', '3', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-08 12:38:09'),
(32, 39, 'Drueew', 'TeamLead', 'Auth', 'Login', 'User Drueew logged in successfully.', '39', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-08 14:46:31'),
(33, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-11 10:34:08'),
(34, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Login', 'User Jhonel Dela Cruz logged in successfully.', '34', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-11 10:34:47'),
(35, 3, 'Accounting User', 'Accounting', 'Auth', 'Login', 'User Accounting User logged in successfully.', '3', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-11 10:35:33'),
(36, 41, 'Jerome Dela Cruz Jr.', 'Applicant', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-12 08:21:38'),
(37, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-12 08:52:32'),
(38, 39, 'Drueew', 'TeamLead', 'Auth', 'Login', 'User Drueew logged in successfully.', '39', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-12 08:52:41'),
(39, 1, 'System Administrator', 'Admin', 'Applicant', 'Endorse', 'Applicant Jerome Dela Cruz Jr. was endorsed to coc.', '31', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-12 08:53:00'),
(40, NULL, 'System Administrator', 'Administrator', 'Auth', 'Failed Login', 'Failed login attempt for account: admin@onazul.com', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 02:55:31'),
(41, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 02:55:40'),
(42, 39, 'Drueew', 'TeamLead', 'Auth', 'Login', 'User Drueew logged in successfully.', '39', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 02:56:33'),
(43, 1, 'System Administrator', 'Admin', 'Applicant', 'Endorse', 'Applicant Chevrolet Camarro was endorsed to coc.', '32', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 06:05:05'),
(44, 42, 'Jasleralchua67', 'Applicant', 'Applicant', 'Registration', 'Applicant Jasleralchua67 registered an account.', '42', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 15:06:50'),
(45, 42, 'Jasleralchua67', 'Applicant', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 15:07:25'),
(46, 1, 'System Administrator', 'Admin', 'Applicant', 'Endorse', 'Applicant Aaron Dela Cruz X was endorsed to coc.', '33', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 16:09:40'),
(47, 1, 'System Administrator', 'Admin', 'Company', 'Update', 'Client Company \'coc\' was updated.', '21', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 16:21:23'),
(48, 1, 'System Administrator', 'Admin', 'Company', 'Update', 'Client Company \'coc\' was updated.', '21', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 16:22:13'),
(49, 1, 'System Administrator', 'Admin', 'Service Agreement', 'Approve', 'Service Agreement #00000001 uploaded and approved for coc.', '21', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-13 16:22:13'),
(50, 32, 'Chevrolet Camarro', 'Employee', 'Attendance', 'Clock In', 'Attendance recorded: Clocked In at coc.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-14 00:51:09'),
(51, 43, 'Aaron Dela Cruz X', 'Employee', 'Attendance', 'Clock In', 'Attendance recorded: Clocked In at coc.', '2', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-14 02:09:36'),
(52, 39, 'Drueew', 'TeamLead', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-14 06:26:15'),
(53, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Login', 'User Jhonel Dela Cruz logged in successfully.', '34', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-14 06:26:23'),
(54, 43, 'Aaron Dela Cruz X', 'Employee', 'Auth', 'Logout', 'User logged out.', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-14 06:28:32'),
(55, 1, 'System Administrator', 'Admin', 'Applicant', 'Endorse', 'Applicant Andrew Dela Cruz Jr. was endorsed to Gaisano - Bulua.', '34', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-14 06:43:14'),
(56, 3, 'Accounting User', 'Accounting', 'Auth', 'Login', 'User Accounting User logged in successfully.', '3', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-14 06:50:50'),
(57, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 05:27:09'),
(58, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 05:27:41'),
(59, 3, 'Accounting User', 'Accounting', 'Auth', 'Login', 'User Accounting User logged in successfully.', '3', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 05:27:56'),
(60, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Login', 'User Jhonel Dela Cruz logged in successfully.', '34', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 05:28:57'),
(61, NULL, 'System Administrator', 'Administrator', 'Auth', 'Failed Login', 'Failed login attempt for account: damnyoustark77@gmail.com', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 05:30:17'),
(62, NULL, 'System Administrator', 'Administrator', 'Auth', 'Failed Login', 'Failed login attempt for account: damnyoustark77@gmail.com', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 05:30:35'),
(63, NULL, 'System Administrator', 'Administrator', 'Auth', 'Failed Login', 'Failed login attempt for account: damnyoustark77@gmail.com', '', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 05:30:45'),
(64, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 14:45:13'),
(65, 34, 'Jhonel Dela Cruz', 'TeamLead', 'Auth', 'Login', 'User Jhonel Dela Cruz logged in successfully.', '34', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 14:45:48'),
(66, 44, 'Andrew Dela Cruz Jr.', 'Employee', 'Attendance', 'Clock In', 'Attendance recorded: Clocked In at Gaisano - Bulua.', '3', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-17 15:01:20'),
(67, 1, 'System Administrator', 'Admin', 'Auth', 'Login', 'User System Administrator logged in successfully.', '1', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-18 01:42:29'),
(68, 3, 'Accounting User', 'Accounting', 'Auth', 'Login', 'User Accounting User logged in successfully.', '3', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-18 01:43:54'),
(69, 43, 'Aaron Dela Cruz X', 'Employee', 'Attendance', 'Clock In', 'Attendance recorded: Clocked In at coc.', '4', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-18 01:51:07'),
(70, 43, 'Aaron Dela Cruz X', 'Employee', 'Attendance', 'Clock Out', 'Attendance recorded: Clocked Out at coc.', '4', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-18 04:00:59'),
(71, 39, 'Drueew', 'TeamLead', 'Auth', 'Login', 'User Drueew logged in successfully.', '39', '::1', 'Safari', 'Windows 10/11', 'Desktop', '2026-08-19 02:31:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UsersID` int(11) NOT NULL,
  `RoleID` int(11) NOT NULL,
  `FullName` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `LastLogin` datetime DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UsersID`, `RoleID`, `FullName`, `Email`, `Password`, `IsActive`, `LastLogin`, `CreatedAt`) VALUES
(1, 1, 'System Administrator', 'admin@onazul.com', '$2y$10$2G0hui1LGIV1aYu7EoUYSeAiUazHiDFo2wAZqmwVZWB97NwbSrlu6', 1, NULL, '2026-02-20 21:05:17'),
(2, 2, 'HR User', 'hr@onazul.com', '$2y$10$2HbLnxRTwVg6OjLQZZJEt.Nnfkd7ab6quN0gONvzTnSlLz1SF0hPa', 1, NULL, '2026-02-20 21:05:28'),
(3, 3, 'Accounting User', 'accounting@onazul.com', '$2y$10$Va/bN9qqYvO02DUQXaTxMOa6i0B2vxhubtXSEtf5TPzvaoRFOimuW', 1, NULL, '2026-02-21 01:05:48'),
(4, 4, 'Team Lead User', 'teamlead@onazul.com', '$2y$10$hv6DAEioJnhv/fXcVn0gFeHCvFXe1qi3iDwJbyZZL68ePWVKd4qDS', 1, NULL, '2026-02-21 01:05:49'),
(5, 6, 'Applicant User', 'applicant@onazul.com', '$2y$10$QVyPBzP/ld/e3S0CuRygOeBGUdi84bYar8HZyKQeSfRSPyYYZYV/u', 1, NULL, '2026-02-21 01:05:49'),
(6, 6, 'Employee User', 'employee@onazul.com', '$2y$10$j0bxlE0mUZTXJvnnz4jv6ekuuCK0cp1jljK8sWqjUywnPXXD9k0mm', 1, NULL, '2026-02-21 01:05:49'),
(7, 6, 'Irina Gamurio', 'irinagamurio@gmail.com', '$2y$10$05e6w5ofA2zWMW9XD1Q4NezJ3re4cIgNdReRxBkmd/YiS.sBIRswu', 1, NULL, '2026-03-24 12:06:30'),
(8, 4, 'Merry Joy Ucat', 'MerryJoy@onazul.com', '$2y$10$/pDXasFRbl8WlNt6r94GqezPohSoAvJm.mtmgThq9SisttmHWwHDS', 1, NULL, '2026-03-24 15:18:12'),
(9, 5, 'Irishdwin Gamutan', 'irishdwin143@gmail.com', '$2y$10$66KwhGyBoVEyAuRcKz3MuejQAdYgbEuGwxVvtRk9cofB1U4DBI/AO', 1, NULL, '2026-03-26 14:20:49'),
(10, 5, 'Irishdwin Gamutan', 'gamutan.irishdwin04@gmail.com', '$2y$10$jPRNBQWcx8.vD9hkXI3ve.N5TzKi8g5iCzj4phW36PZNqqoXVyjKi', 1, NULL, '2026-03-29 13:27:38'),
(11, 5, 'Irishdwin Gamutan', 'tomntoms28@gmail.com', '$2y$10$rG/uF.ZATyDS1kK0r2HMJemvFm6yR9EGYi62ad.UbqwLrz4kPj4di', 1, NULL, '2026-03-29 15:32:10'),
(12, 4, 'Irishdwin', 'Irish@onazul.com', '$2y$10$AZ1MPno1KhXI0o5SfbPtVemNagDjbjFjUfHfeIDzP303d4e8Edj/2', 1, NULL, '2026-03-29 23:19:29'),
(13, 4, 'Juan Dela Cruz', 'juan@onazul.com', '$2y$10$pe/pl1DF1GiIu.aXOVbF4eTxRt0FNWuMCGNwVHbJedMsATjTFBJUq', 1, NULL, '2026-03-30 12:22:36'),
(14, 6, 'Selena Gomez', 'selenamain811@gmail.com', '$2y$10$W8YfniaWa1Ure/Vs0.NHWOJCRznwqEPE.5TqbrImI.nN.1.66/kFe', 1, NULL, '2026-03-30 12:34:40'),
(15, 6, 'Sherrish Mae', 'hayabusa.main.28@gmail.com', '$2y$10$m36QOd09nSXY4.XrGy9M.uaWREk3LvgYod7dpG4PAQjBWV8CHe.3C', 1, NULL, '2026-03-30 15:28:40'),
(16, 4, 'Dwin', 'Dwin@onazul.com', '$2y$10$DwUy8p/iT6.OBBgaWLH/i.XuQEs/BGy586LAzealnXn8cI8cBJAK2', 1, NULL, '2026-03-30 15:33:25'),
(17, 4, 'Irishdwin Gamutan', 'Irishdwin@onazul.com', '$2y$10$0p9tYVixlwIB6FcXz.SpYe./gfmhWqrkxPNSW.PDzQiR601NHb1TG', 1, NULL, '2026-03-30 16:44:26'),
(18, 4, 'Khulas', 'm76413283@gmail.com', '$2y$10$h6y7sFiM9lfxPVbvkqJ7Hug6uz1telV9C0NAFNSHHLToOvk51lQYC', 1, NULL, '2026-04-16 14:50:17'),
(19, 4, 'Khulas D. Buenas', 'adele.elle28@gmail.com', '$2y$10$Mke1XEl6Hns3UQ2vJWJMou48YOpiKPXvp8rgGB/fMPWexCedgWLFG', 1, NULL, '2026-04-25 11:16:37'),
(20, 5, 'Karina Sanchez', 'khulas812@gmail.com', '$2y$10$VzcpcKcyEdpLz/lIApRzuewJdFHmnS5Kn6WZaLZYews9iX6PrBXoq', 1, NULL, '2026-04-25 14:29:33'),
(21, 4, 'Juan Dela Cruz', 'j93011462@gmail.com', '$2y$10$Z09B2jBc1QWH7pEidgaVj.QxLtc2us4GNarhF6i5WE6OAlAhm4Ky2', 1, NULL, '2026-04-28 11:44:16'),
(22, 4, 'Dwight Jhonson', 's25043400@gmail.com', '$2y$10$S7ogbeuIsdIgQV89OTyXRe/TWXHVYlPIUZCfgIqnB566j1oBDRL8W', 0, NULL, '2026-05-18 22:44:55'),
(23, 4, 'Charmaine', 'v77336013@gmail.com', '$2y$10$YPWsikhFxi46180k/otqEu3/y81qNQmi80eEXTwniHgrnELAiS3he', 1, NULL, '2026-06-03 11:43:24'),
(24, 4, 'Iris Nota', 'irisaton25@gmail.com', '$2y$10$HVF8zkQZOzmoJV6f21s8zOONGY6PDv2g2bqOGGm7dxdr89LEJwubS', 1, NULL, '2026-06-09 20:33:41'),
(25, 5, 'Irishdwin Natumag', 'irishdwin28@gmail.com', '$2y$10$fuLiUMe05nFqroYf2YVube83E6TuZtr7EJ31mF1uvehELEq1y77t6', 1, NULL, '2026-06-10 21:58:59'),
(26, 5, 'Chevy Camaro', 'c4471892@gmail.com', '$2y$10$daJ1hP24CngBiFG.0flNg.GdDsSRl2UuPezgoFTftdrWJaVqPlPfS', 1, NULL, '2026-06-30 19:19:28'),
(27, 6, 'Nicolas Jhonson', 'gmailtest010203@gmail.com', '$2y$10$Q9e1UxdC6eg8yiR9vc4dWeY4KIpYZiNLFTq7t53vCGzWpDynB8zTG', 1, '2026-07-02 22:40:26', '2026-07-02 11:33:22'),
(28, 5, 'Jack Dawson', 'merryjoy010203@gmail.com', '$2y$10$V9I0fBzgvNRmOaJjuwIAuO2SNA2kGYlMSJBnHYcpgMTXDi6I9eqEi', 1, '2026-07-23 22:41:31', '2026-07-02 23:06:43'),
(29, 6, 'Toyota Supra', 'dumpnavs0@gmail.com', '$2y$10$WvwkAgeaa846vrC3W2p0yOT39nCcsIvpNJtB8mG0edYWqIKxdPIrm', 1, '2026-07-23 23:26:39', '2026-07-06 22:05:55'),
(30, 4, 'Trisia Navarro', 'undru6767@gmail.com', '$2y$10$ep9AUp7NzEbFR/OFBcq1newXCalThvlqSNC03/qoxurckiXJcxDtq', 1, '2026-07-23 23:43:22', '2026-07-08 16:48:34'),
(31, 6, 'Mariane Mildred', 'caelmax30@gmail.com', '$2y$10$bsfTXDB05JK1App3fZCI1uc6f0awtmB9h43FLYdkurUeOrFJEDLya', 1, '2026-07-30 21:26:23', '2026-07-08 16:56:06'),
(32, 6, 'Chevrolet Camarro', 'marcar8879@gmail.com', '$2y$10$b/lZXTAkokJcUrPDDuEn8u0znw2mz9MaMzUhJIeeCgaqs.bHoDRiO', 1, '2026-08-13 14:02:03', '2026-07-23 23:30:03'),
(33, 4, 'Marry James', 'mjucat3@gmail.com', '$2y$10$Ma2Po2I9/eyd2xZnRyPPRulXzeCez4ymdQio3homptXJJglK4CJda', 1, '2026-07-30 13:58:19', '2026-07-23 23:47:39'),
(34, 4, 'Jhonel Dela Cruz', 'druuw67@gmail.com', '$2y$10$AfgE/sJUE2MZAeQsktJYrOOBthxREC.cb7k0iRCPBRxZwQhPVGh8.', 1, '2026-07-31 01:20:33', '2026-07-30 20:36:06'),
(35, 6, 'Emmanuel Cruz', 'amille.5437@gmail.com', '$2y$10$.b3JXNpy5xceGUcMMXu.J.JGjUVJeggoBeVaIGlkLNTR2cAwDUvOS', 1, '2026-08-04 22:05:17', '2026-07-30 21:27:34'),
(36, 4, 'Merry Joy', 'ellimac618@gmail.com', '$2y$10$zY7wy9r0HwGQ3wpQAkPwROtGZdzgEjnS7V49Cd2Y9aIEfQjhj1nCi', 1, NULL, '2026-08-05 10:19:53'),
(37, 5, 'Style91998', 'Style91998@gmail.com', '$2y$10$pf0KrnuGLehFwH7A3qPtq.nVsYAY.qZ.Q4loCbzKU80i1F9pdbl2K', 1, NULL, '2026-08-05 11:01:43'),
(38, 6, 'Cammille Roxas', 'carsmar781@gmail.com', '$2y$10$UejLy522kOghFLluPOLqCeWaysby3RPt4Trl0ot0/g66u1xrRdzbC', 1, '2026-08-05 11:05:54', '2026-08-05 11:05:54'),
(39, 4, 'Drueew', 'mejoyu0@gmail.com', '$2y$10$YI.oOkxpOYsAaauaVcP2fO2qoRljCgQO6a8H.hRHHGDWMZRjuGFEW', 1, '2026-08-18 09:47:55', '2026-08-06 10:15:59'),
(40, 5, 'F04615934', 'f04615934@gmail.com', '$2y$10$W/gUtLDm3R/XXJWwPhnIaOS5v/GC0FiYo5w.wjSnSom9H2zyBlUsO', 1, NULL, '2026-08-06 11:01:22'),
(41, 6, 'Jerome Dela Cruz Jr.', 'july83767@gmail.com', '$2y$10$f1PP5tw4l4vmNGTiLnXDP.vpNG1Fjlp3ZoHmaQG.OmToRXrLVdEiK', 1, '2026-08-12 16:21:53', '2026-08-06 11:04:29'),
(42, 5, 'Jasleralchua67', 'jasleralchua67@gmail.com', '$2y$10$wKQlOHrNNuF6nSMYKd/zte.GpPtRjslz7EkgzIDYk8QdPvx9TXywa', 1, NULL, '2026-08-13 23:06:50'),
(43, 6, 'Aaron Dela Cruz X', 'unodostress0099@gmail.com', '$2y$10$dPNny/QdDX7kUhwcnSHqkujSs0TWapyA2XyurF9F5BQVhmEtNJSXu', 1, '2026-08-18 09:49:13', '2026-08-13 23:11:22'),
(44, 6, 'Andrew Dela Cruz Jr.', 'damnyoustark77@gmail.com', '$2y$10$Rg80Qi/ahnBQwPrqJR62auBWUAEL5BQqdN2XfOGjCrOue2RaznoHq', 1, '2026-08-17 22:46:43', '2026-08-14 14:31:18');

-- --------------------------------------------------------

--
-- Table structure for table `work_schedule_lookup`
--

CREATE TABLE `work_schedule_lookup` (
  `WorkScheduleID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `SortOrder` int(11) NOT NULL DEFAULT 0,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_schedule_lookup`
--

INSERT INTO `work_schedule_lookup` (`WorkScheduleID`, `Name`, `SortOrder`, `IsActive`, `CreatedAt`) VALUES
(1, 'Mon-Fri 8AM-5PM', 10, 1, '2026-04-25 02:52:46'),
(2, 'Mon-Sat 8AM-5PM', 20, 1, '2026-04-25 02:52:46'),
(3, 'Morning Shift (6AM-2PM)', 30, 1, '2026-04-25 02:52:46'),
(4, 'Mid Shift (2PM-10PM)', 40, 1, '2026-04-25 02:52:46'),
(5, 'Night Shift (10PM-6AM)', 50, 1, '2026-04-25 02:52:46'),
(6, 'Rotating', 60, 1, '2026-04-25 02:52:46'),
(7, 'Flexible', 70, 1, '2026-04-25 02:52:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `address_barangay_lookup`
--
ALTER TABLE `address_barangay_lookup`
  ADD PRIMARY KEY (`BarangayID`),
  ADD UNIQUE KEY `uniq_barangay_city` (`CityID`,`Name`),
  ADD KEY `idx_barangay_city` (`CityID`);

--
-- Indexes for table `address_city_lookup`
--
ALTER TABLE `address_city_lookup`
  ADD PRIMARY KEY (`CityID`),
  ADD UNIQUE KEY `uniq_city_province` (`ProvinceID`,`Name`),
  ADD KEY `idx_city_province` (`ProvinceID`);

--
-- Indexes for table `address_country_lookup`
--
ALTER TABLE `address_country_lookup`
  ADD PRIMARY KEY (`CountryID`),
  ADD UNIQUE KEY `uniq_country_name` (`Name`);

--
-- Indexes for table `address_province_lookup`
--
ALTER TABLE `address_province_lookup`
  ADD PRIMARY KEY (`ProvinceID`),
  ADD UNIQUE KEY `uniq_province_country` (`CountryID`,`Name`),
  ADD KEY `idx_province_country` (`CountryID`);

--
-- Indexes for table `applicantdocuments`
--
ALTER TABLE `applicantdocuments`
  ADD PRIMARY KEY (`DocumentID`),
  ADD KEY `fk_ad_employee` (`EmployeeID`),
  ADD KEY `fk_ad_verifiedby` (`VerifiedBy`),
  ADD KEY `fk_ad_status` (`StatusID`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`ApplicantID`),
  ADD KEY `fk_app_user` (`UserID`);

--
-- Indexes for table `applicant_profiles`
--
ALTER TABLE `applicant_profiles`
  ADD PRIMARY KEY (`UserID`);

--
-- Indexes for table `applicant_uploaded_docs`
--
ALTER TABLE `applicant_uploaded_docs`
  ADD PRIMARY KEY (`DocID`),
  ADD KEY `fk_aud_application` (`ApplicationID`),
  ADD KEY `fk_aud_applicant` (`ApplicantID`),
  ADD KEY `fk_aud_verifiedby` (`VerifiedBy`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`AttendanceID`),
  ADD KEY `fk_att_employee` (`EmployeeID`),
  ADD KEY `fk_att_status` (`StatusID`),
  ADD KEY `fk_att_dtr` (`DTRSubmissionID`),
  ADD KEY `fk_att_holiday` (`HolidayID`),
  ADD KEY `fk_att_approvedby` (`ApprovedBy`);

--
-- Indexes for table `bonus`
--
ALTER TABLE `bonus`
  ADD PRIMARY KEY (`BonusID`),
  ADD KEY `fk_bon_employee` (`EmployeeID`),
  ADD KEY `fk_bon_payroll` (`Payroll_ID`),
  ADD KEY `fk_bon_status` (`StatusID`),
  ADD KEY `fk_bon_approvedby` (`ApprovedBy`),
  ADD KEY `fk_bon_createdby` (`CreatedBy`);

--
-- Indexes for table `cashadvance`
--
ALTER TABLE `cashadvance`
  ADD PRIMARY KEY (`CashAdvanceID`),
  ADD KEY `fk_ca_employee` (`EmployeeID`),
  ADD KEY `fk_ca_approvedby` (`ApprovedBy`),
  ADD KEY `fk_ca_payroll` (`Payroll_ID`);

--
-- Indexes for table `cashadvancededuction`
--
ALTER TABLE `cashadvancededuction`
  ADD PRIMARY KEY (`DeductionID`),
  ADD KEY `fk_cad_cashadvance` (`CashAdvanceID`),
  ADD KEY `fk_cad_payroll` (`Payroll_ID`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`MessageID`),
  ADD KEY `idx_chat_messages_thread_time` (`ThreadID`,`CreatedAt`);

--
-- Indexes for table `chat_threads`
--
ALTER TABLE `chat_threads`
  ADD PRIMARY KEY (`ThreadID`),
  ADD KEY `idx_chat_threads_type_related` (`ThreadType`,`RelatedModule`,`RelatedRecord`);

--
-- Indexes for table `chat_thread_participants`
--
ALTER TABLE `chat_thread_participants`
  ADD PRIMARY KEY (`ThreadID`,`UserID`);

--
-- Indexes for table `chat_thread_reads`
--
ALTER TABLE `chat_thread_reads`
  ADD PRIMARY KEY (`ThreadID`,`UserID`);

--
-- Indexes for table `clearance_items`
--
ALTER TABLE `clearance_items`
  ADD PRIMARY KEY (`ClearanceItemID`),
  ADD KEY `fk_ci_resignation` (`ResignationID`),
  ADD KEY `fk_ci_clearedby` (`ClearedBy`);

--
-- Indexes for table `clientcompany`
--
ALTER TABLE `clientcompany`
  ADD PRIMARY KEY (`CompanyID`),
  ADD KEY `fk_company_user` (`UserID`);

--
-- Indexes for table `daily_time_records`
--
ALTER TABLE `daily_time_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dtr_employee_date` (`employee_id`,`date`),
  ADD KEY `idx_dtr_date_status` (`date`,`status`);

--
-- Indexes for table `deductions`
--
ALTER TABLE `deductions`
  ADD PRIMARY KEY (`DeductionsID`),
  ADD KEY `fk_ded_payroll` (`Payroll_ID`),
  ADD KEY `fk_ded_employee` (`EmployeeID`),
  ADD KEY `fk_ded_createdby` (`CreatedBy`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`DepartmentID`),
  ADD KEY `fk_dept_manager` (`ManagerID`);

--
-- Indexes for table `deployment`
--
ALTER TABLE `deployment`
  ADD PRIMARY KEY (`DeploymentID`),
  ADD KEY `fk_dep_employee` (`EmployeeID`),
  ADD KEY `fk_dep_company` (`CompanyID`),
  ADD KEY `fk_dep_status` (`StatusID`),
  ADD KEY `fk_dep_supervisor` (`SiteSuperVisorID`),
  ADD KEY `fk_dep_sa` (`ServiceAgreementID`);

--
-- Indexes for table `dtr_submissions`
--
ALTER TABLE `dtr_submissions`
  ADD PRIMARY KEY (`DTR_SubmissionID`),
  ADD KEY `fk_dtr_employee` (`EmployeeID`),
  ADD KEY `fk_dtr_deployment` (`DeploymentID`),
  ADD KEY `fk_dtr_supervisor` (`SiteSupervisorID`),
  ADD KEY `fk_dtr_submitted` (`SubmittedBy`),
  ADD KEY `fk_dtr_reviewed` (`ReviewedBy`),
  ADD KEY `fk_dtr_approved` (`ApprovedBy`),
  ADD KEY `fk_dtr_status` (`StatusID`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`EmployeeID`),
  ADD UNIQUE KEY `EmployeeNumber` (`EmployeeNumber`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD KEY `fk_emp_status` (`StatusID`),
  ADD KEY `fk_emp_department` (`DepartmentID`);

--
-- Indexes for table `employmentcontract`
--
ALTER TABLE `employmentcontract`
  ADD PRIMARY KEY (`EmploymentContractID`),
  ADD KEY `fk_ec_employee` (`EmployeeID`),
  ADD KEY `fk_ec_status` (`StatusID`),
  ADD KEY `fk_ec_createdby` (`CreatedBy`),
  ADD KEY `fk_ec_approvedby` (`ApprovedBy`);

--
-- Indexes for table `gov_percentage_rates`
--
ALTER TABLE `gov_percentage_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gu_tags`
--
ALTER TABLE `gu_tags`
  ADD PRIMARY KEY (`GUTagID`),
  ADD KEY `fk_gt_employee` (`EmployeeID`),
  ADD KEY `fk_gt_deployment` (`DeploymentID`),
  ADD KEY `fk_gt_attendance` (`AttendanceID`),
  ADD KEY `fk_gt_verifiedby` (`VerifiedBy`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`HolidayID`);

--
-- Indexes for table `industry_lookup`
--
ALTER TABLE `industry_lookup`
  ADD PRIMARY KEY (`IndustryID`),
  ADD UNIQUE KEY `uniq_industry_name` (`Name`);

--
-- Indexes for table `joborder`
--
ALTER TABLE `joborder`
  ADD PRIMARY KEY (`JobOrderID`),
  ADD KEY `fk_jo_company` (`CompanyID`),
  ADD KEY `fk_jo_sa` (`ServiceAgreementID`),
  ADD KEY `fk_jo_createdby` (`CreatedBy`),
  ADD KEY `fk_jo_approvedby` (`ApprovedBy`),
  ADD KEY `idx_joborder_category_id` (`category_id`);

--
-- Indexes for table `joborderpositions`
--
ALTER TABLE `joborderpositions`
  ADD PRIMARY KEY (`JobOrderPositionID`),
  ADD KEY `fk_jop_joborder` (`JobOrderID`);

--
-- Indexes for table `jobrequirements`
--
ALTER TABLE `jobrequirements`
  ADD PRIMARY KEY (`JobRequirementsID`),
  ADD KEY `fk_jr_joborder` (`JobOrderID`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`ApplicationID`),
  ADD KEY `fk_ja_applicant` (`ApplicantID`),
  ADD KEY `fk_ja_joborder` (`JobOrderID`),
  ADD KEY `fk_ja_status` (`StatusID`),
  ADD KEY `fk_ja_endorsed` (`EndorsedBy`),
  ADD KEY `fk_ja_hired` (`HiredBy`),
  ADD KEY `fk_app_target_detail` (`target_detail_id`);

--
-- Indexes for table `job_categories`
--
ALTER TABLE `job_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_job_categories_name` (`category_name`);

--
-- Indexes for table `job_order_details`
--
ALTER TABLE `job_order_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `idx_jod_request` (`RequestID`),
  ADD KEY `idx_jod_joborder` (`JobOrderID`);

--
-- Indexes for table `job_order_requests`
--
ALTER TABLE `job_order_requests`
  ADD PRIMARY KEY (`RequestID`),
  ADD KEY `fk_jor_requested` (`RequestedBy`),
  ADD KEY `fk_jor_company` (`CompanyID`),
  ADD KEY `fk_jor_processed` (`ProcessedBy`),
  ADD KEY `idx_jor_joborder` (`JobOrderID`),
  ADD KEY `idx_jor_category_id` (`category_id`);

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`LeaveID`),
  ADD KEY `fk_lv_employee` (`EmployeeID`),
  ADD KEY `fk_lv_approvedby` (`ApprovedBy`),
  ADD KEY `fk_lv_status` (`StatusID`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`NotificationID`),
  ADD KEY `fk_notif_user` (`UserID`);

--
-- Indexes for table `overtime`
--
ALTER TABLE `overtime`
  ADD PRIMARY KEY (`OvertimeID`),
  ADD KEY `fk_ot_employee` (`EmployeeID`),
  ADD KEY `fk_ot_attendance` (`AttendanceID`),
  ADD KEY `fk_ot_deployment` (`DeploymentID`),
  ADD KEY `fk_ot_requestedby` (`RequestedBy`),
  ADD KEY `fk_ot_approvedby` (`ApprovedBy`),
  ADD KEY `fk_ot_status` (`StatusID`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`Payroll_ID`),
  ADD KEY `fk_pay_employee` (`EmployeeID`),
  ADD KEY `fk_pay_processedby` (`ProcessedBy`),
  ADD KEY `fk_pay_holiday` (`HolidayID`),
  ADD KEY `fk_pay_salaryrate` (`SalaryRatesID`),
  ADD KEY `fk_pay_dtr` (`DTR_SubmissionID`),
  ADD KEY `fk_pay_status` (`StatusID`);

--
-- Indexes for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  ADD PRIMARY KEY (`AdjustmentID`),
  ADD KEY `fk_pa_payroll` (`Payroll_ID`),
  ADD KEY `fk_pa_employee` (`EmployeeID`),
  ADD KEY `fk_pa_approvedby` (`ApprovedBy`),
  ADD KEY `fk_pa_createdby` (`CreatedBy`);

--
-- Indexes for table `payroll_audit_log`
--
ALTER TABLE `payroll_audit_log`
  ADD PRIMARY KEY (`LogID`),
  ADD KEY `fk_pal_payroll` (`Payroll_ID`),
  ADD KEY `fk_pal_performedby` (`PerformedBy`);

--
-- Indexes for table `payroll_runs`
--
ALTER TABLE `payroll_runs`
  ADD PRIMARY KEY (`PayrollRunID`),
  ADD UNIQUE KEY `uk_payroll_runs_code` (`RunCode`),
  ADD KEY `idx_payroll_runs_company` (`CompanyID`),
  ADD KEY `idx_payroll_runs_period` (`PeriodStart`,`PeriodEnd`);

--
-- Indexes for table `payroll_status_history`
--
ALTER TABLE `payroll_status_history`
  ADD PRIMARY KEY (`HistoryID`),
  ADD KEY `idx_psh_run` (`PayrollRunID`);

--
-- Indexes for table `payslip`
--
ALTER TABLE `payslip`
  ADD PRIMARY KEY (`PayslipID`),
  ADD UNIQUE KEY `Payroll_ID` (`Payroll_ID`),
  ADD KEY `fk_ps_employee` (`EmployeeID`);

--
-- Indexes for table `performance`
--
ALTER TABLE `performance`
  ADD PRIMARY KEY (`PerformanceID`),
  ADD KEY `fk_perf_employee` (`EmployeeID`),
  ADD KEY `fk_perf_reviewer` (`ReviewerID`);

--
-- Indexes for table `resignationrequest`
--
ALTER TABLE `resignationrequest`
  ADD PRIMARY KEY (`ResignationID`),
  ADD KEY `fk_rr_employee` (`EmployeeID`),
  ADD KEY `fk_rr_processedby` (`ProcessedBy`),
  ADD KEY `fk_rr_approvedby` (`ApprovedBy`),
  ADD KEY `fk_rr_status` (`StatusID`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`RolesID`);

--
-- Indexes for table `salary_rates`
--
ALTER TABLE `salary_rates`
  ADD PRIMARY KEY (`SalaryRatesID`),
  ADD KEY `fk_sr_employee` (`EmployeeID`),
  ADD KEY `fk_sr_deployment` (`DeploymentID`),
  ADD KEY `fk_sr_company` (`CompanyID`),
  ADD KEY `fk_sr_createdby` (`CreatedBy`);

--
-- Indexes for table `serviceagreement`
--
ALTER TABLE `serviceagreement`
  ADD PRIMARY KEY (`ServiceAgreementID`),
  ADD KEY `fk_sa_company` (`ClientCompanyID`),
  ADD KEY `fk_sa_createdby` (`CreatedBy`),
  ADD KEY `fk_sa_approvedby` (`ApprovedBy`);

--
-- Indexes for table `sss_rates_config`
--
ALTER TABLE `sss_rates_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sss_salary_range` (`salary_min`,`salary_max`),
  ADD KEY `idx_sss_msc` (`monthly_salary_credit`);

--
-- Indexes for table `status`
--
ALTER TABLE `status`
  ADD PRIMARY KEY (`StatusID`);

--
-- Indexes for table `systemsettings`
--
ALTER TABLE `systemsettings`
  ADD PRIMARY KEY (`SettingID`),
  ADD UNIQUE KEY `SettingKey` (`SettingKey`),
  ADD KEY `fk_settings_user` (`UpdatedBy`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_module_action` (`module`,`action`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UsersID`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD KEY `fk_users_role` (`RoleID`);

--
-- Indexes for table `work_schedule_lookup`
--
ALTER TABLE `work_schedule_lookup`
  ADD PRIMARY KEY (`WorkScheduleID`),
  ADD UNIQUE KEY `uniq_work_schedule_name` (`Name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `address_barangay_lookup`
--
ALTER TABLE `address_barangay_lookup`
  MODIFY `BarangayID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=525;

--
-- AUTO_INCREMENT for table `address_city_lookup`
--
ALTER TABLE `address_city_lookup`
  MODIFY `CityID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `address_country_lookup`
--
ALTER TABLE `address_country_lookup`
  MODIFY `CountryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `address_province_lookup`
--
ALTER TABLE `address_province_lookup`
  MODIFY `ProvinceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `applicantdocuments`
--
ALTER TABLE `applicantdocuments`
  MODIFY `DocumentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `ApplicantID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `applicant_uploaded_docs`
--
ALTER TABLE `applicant_uploaded_docs`
  MODIFY `DocID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `AttendanceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `bonus`
--
ALTER TABLE `bonus`
  MODIFY `BonusID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashadvance`
--
ALTER TABLE `cashadvance`
  MODIFY `CashAdvanceID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashadvancededuction`
--
ALTER TABLE `cashadvancededuction`
  MODIFY `DeductionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `MessageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `chat_threads`
--
ALTER TABLE `chat_threads`
  MODIFY `ThreadID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `clearance_items`
--
ALTER TABLE `clearance_items`
  MODIFY `ClearanceItemID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clientcompany`
--
ALTER TABLE `clientcompany`
  MODIFY `CompanyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `daily_time_records`
--
ALTER TABLE `daily_time_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `deductions`
--
ALTER TABLE `deductions`
  MODIFY `DeductionsID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deployment`
--
ALTER TABLE `deployment`
  MODIFY `DeploymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `dtr_submissions`
--
ALTER TABLE `dtr_submissions`
  MODIFY `DTR_SubmissionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `employmentcontract`
--
ALTER TABLE `employmentcontract`
  MODIFY `EmploymentContractID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gu_tags`
--
ALTER TABLE `gu_tags`
  MODIFY `GUTagID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `HolidayID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `industry_lookup`
--
ALTER TABLE `industry_lookup`
  MODIFY `IndustryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1353;

--
-- AUTO_INCREMENT for table `joborder`
--
ALTER TABLE `joborder`
  MODIFY `JobOrderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `joborderpositions`
--
ALTER TABLE `joborderpositions`
  MODIFY `JobOrderPositionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobrequirements`
--
ALTER TABLE `jobrequirements`
  MODIFY `JobRequirementsID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `ApplicationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `job_categories`
--
ALTER TABLE `job_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=761289;

--
-- AUTO_INCREMENT for table `job_order_details`
--
ALTER TABLE `job_order_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `job_order_requests`
--
ALTER TABLE `job_order_requests`
  MODIFY `RequestID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `LeaveID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `NotificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=284;

--
-- AUTO_INCREMENT for table `overtime`
--
ALTER TABLE `overtime`
  MODIFY `OvertimeID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `Payroll_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  MODIFY `AdjustmentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_audit_log`
--
ALTER TABLE `payroll_audit_log`
  MODIFY `LogID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_runs`
--
ALTER TABLE `payroll_runs`
  MODIFY `PayrollRunID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=945;

--
-- AUTO_INCREMENT for table `payroll_status_history`
--
ALTER TABLE `payroll_status_history`
  MODIFY `HistoryID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payslip`
--
ALTER TABLE `payslip`
  MODIFY `PayslipID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance`
--
ALTER TABLE `performance`
  MODIFY `PerformanceID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resignationrequest`
--
ALTER TABLE `resignationrequest`
  MODIFY `ResignationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `RolesID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `salary_rates`
--
ALTER TABLE `salary_rates`
  MODIFY `SalaryRatesID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `serviceagreement`
--
ALTER TABLE `serviceagreement`
  MODIFY `ServiceAgreementID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `sss_rates_config`
--
ALTER TABLE `sss_rates_config`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `status`
--
ALTER TABLE `status`
  MODIFY `StatusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `systemsettings`
--
ALTER TABLE `systemsettings`
  MODIFY `SettingID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UsersID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `work_schedule_lookup`
--
ALTER TABLE `work_schedule_lookup`
  MODIFY `WorkScheduleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1002;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `address_barangay_lookup`
--
ALTER TABLE `address_barangay_lookup`
  ADD CONSTRAINT `fk_address_barangay_city` FOREIGN KEY (`CityID`) REFERENCES `address_city_lookup` (`CityID`);

--
-- Constraints for table `address_city_lookup`
--
ALTER TABLE `address_city_lookup`
  ADD CONSTRAINT `fk_address_city_province` FOREIGN KEY (`ProvinceID`) REFERENCES `address_province_lookup` (`ProvinceID`);

--
-- Constraints for table `address_province_lookup`
--
ALTER TABLE `address_province_lookup`
  ADD CONSTRAINT `fk_address_province_country` FOREIGN KEY (`CountryID`) REFERENCES `address_country_lookup` (`CountryID`);

--
-- Constraints for table `applicantdocuments`
--
ALTER TABLE `applicantdocuments`
  ADD CONSTRAINT `fk_ad_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_ad_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`),
  ADD CONSTRAINT `fk_ad_verifiedby` FOREIGN KEY (`VerifiedBy`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `fk_app_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `applicant_uploaded_docs`
--
ALTER TABLE `applicant_uploaded_docs`
  ADD CONSTRAINT `fk_aud_applicant` FOREIGN KEY (`ApplicantID`) REFERENCES `applicants` (`ApplicantID`),
  ADD CONSTRAINT `fk_aud_application` FOREIGN KEY (`ApplicationID`) REFERENCES `job_applications` (`ApplicationID`),
  ADD CONSTRAINT `fk_aud_verifiedby` FOREIGN KEY (`VerifiedBy`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_att_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_att_dtr` FOREIGN KEY (`DTRSubmissionID`) REFERENCES `dtr_submissions` (`DTR_SubmissionID`),
  ADD CONSTRAINT `fk_att_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_att_holiday` FOREIGN KEY (`HolidayID`) REFERENCES `holidays` (`HolidayID`),
  ADD CONSTRAINT `fk_att_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`);

--
-- Constraints for table `bonus`
--
ALTER TABLE `bonus`
  ADD CONSTRAINT `fk_bon_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_bon_createdby` FOREIGN KEY (`CreatedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_bon_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_bon_payroll` FOREIGN KEY (`Payroll_ID`) REFERENCES `payroll` (`Payroll_ID`),
  ADD CONSTRAINT `fk_bon_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`);

--
-- Constraints for table `cashadvance`
--
ALTER TABLE `cashadvance`
  ADD CONSTRAINT `fk_ca_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_ca_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_ca_payroll` FOREIGN KEY (`Payroll_ID`) REFERENCES `payroll` (`Payroll_ID`);

--
-- Constraints for table `cashadvancededuction`
--
ALTER TABLE `cashadvancededuction`
  ADD CONSTRAINT `fk_cad_cashadvance` FOREIGN KEY (`CashAdvanceID`) REFERENCES `cashadvance` (`CashAdvanceID`),
  ADD CONSTRAINT `fk_cad_payroll` FOREIGN KEY (`Payroll_ID`) REFERENCES `payroll` (`Payroll_ID`);

--
-- Constraints for table `clearance_items`
--
ALTER TABLE `clearance_items`
  ADD CONSTRAINT `fk_ci_clearedby` FOREIGN KEY (`ClearedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_ci_resignation` FOREIGN KEY (`ResignationID`) REFERENCES `resignationrequest` (`ResignationID`);

--
-- Constraints for table `clientcompany`
--
ALTER TABLE `clientcompany`
  ADD CONSTRAINT `fk_company_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `deductions`
--
ALTER TABLE `deductions`
  ADD CONSTRAINT `fk_ded_createdby` FOREIGN KEY (`CreatedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_ded_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_ded_payroll` FOREIGN KEY (`Payroll_ID`) REFERENCES `payroll` (`Payroll_ID`);

--
-- Constraints for table `department`
--
ALTER TABLE `department`
  ADD CONSTRAINT `fk_dept_manager` FOREIGN KEY (`ManagerID`) REFERENCES `employee` (`EmployeeID`);

--
-- Constraints for table `deployment`
--
ALTER TABLE `deployment`
  ADD CONSTRAINT `fk_dep_company` FOREIGN KEY (`CompanyID`) REFERENCES `clientcompany` (`CompanyID`),
  ADD CONSTRAINT `fk_dep_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_dep_sa` FOREIGN KEY (`ServiceAgreementID`) REFERENCES `serviceagreement` (`ServiceAgreementID`),
  ADD CONSTRAINT `fk_dep_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`),
  ADD CONSTRAINT `fk_dep_supervisor` FOREIGN KEY (`SiteSuperVisorID`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `dtr_submissions`
--
ALTER TABLE `dtr_submissions`
  ADD CONSTRAINT `fk_dtr_approved` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_dtr_deployment` FOREIGN KEY (`DeploymentID`) REFERENCES `deployment` (`DeploymentID`),
  ADD CONSTRAINT `fk_dtr_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_dtr_reviewed` FOREIGN KEY (`ReviewedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_dtr_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`),
  ADD CONSTRAINT `fk_dtr_submitted` FOREIGN KEY (`SubmittedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_dtr_supervisor` FOREIGN KEY (`SiteSupervisorID`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `employee`
--
ALTER TABLE `employee`
  ADD CONSTRAINT `fk_emp_department` FOREIGN KEY (`DepartmentID`) REFERENCES `department` (`DepartmentID`),
  ADD CONSTRAINT `fk_emp_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`);

--
-- Constraints for table `employmentcontract`
--
ALTER TABLE `employmentcontract`
  ADD CONSTRAINT `fk_ec_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_ec_createdby` FOREIGN KEY (`CreatedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_ec_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_ec_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`);

--
-- Constraints for table `gu_tags`
--
ALTER TABLE `gu_tags`
  ADD CONSTRAINT `fk_gt_attendance` FOREIGN KEY (`AttendanceID`) REFERENCES `attendance` (`AttendanceID`),
  ADD CONSTRAINT `fk_gt_deployment` FOREIGN KEY (`DeploymentID`) REFERENCES `deployment` (`DeploymentID`),
  ADD CONSTRAINT `fk_gt_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_gt_verifiedby` FOREIGN KEY (`VerifiedBy`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `joborder`
--
ALTER TABLE `joborder`
  ADD CONSTRAINT `fk_jo_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_jo_company` FOREIGN KEY (`CompanyID`) REFERENCES `clientcompany` (`CompanyID`),
  ADD CONSTRAINT `fk_jo_createdby` FOREIGN KEY (`CreatedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_jo_sa` FOREIGN KEY (`ServiceAgreementID`) REFERENCES `serviceagreement` (`ServiceAgreementID`);

--
-- Constraints for table `joborderpositions`
--
ALTER TABLE `joborderpositions`
  ADD CONSTRAINT `fk_jop_joborder` FOREIGN KEY (`JobOrderID`) REFERENCES `joborder` (`JobOrderID`);

--
-- Constraints for table `jobrequirements`
--
ALTER TABLE `jobrequirements`
  ADD CONSTRAINT `fk_jr_joborder` FOREIGN KEY (`JobOrderID`) REFERENCES `joborder` (`JobOrderID`);

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `fk_app_target_detail` FOREIGN KEY (`target_detail_id`) REFERENCES `job_order_details` (`detail_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ja_applicant` FOREIGN KEY (`ApplicantID`) REFERENCES `applicants` (`ApplicantID`),
  ADD CONSTRAINT `fk_ja_endorsed` FOREIGN KEY (`EndorsedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_ja_hired` FOREIGN KEY (`HiredBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_ja_joborder` FOREIGN KEY (`JobOrderID`) REFERENCES `joborder` (`JobOrderID`),
  ADD CONSTRAINT `fk_ja_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`);

--
-- Constraints for table `job_order_details`
--
ALTER TABLE `job_order_details`
  ADD CONSTRAINT `fk_jod_request` FOREIGN KEY (`RequestID`) REFERENCES `job_order_requests` (`RequestID`) ON DELETE CASCADE;

--
-- Constraints for table `job_order_requests`
--
ALTER TABLE `job_order_requests`
  ADD CONSTRAINT `fk_jor_company` FOREIGN KEY (`CompanyID`) REFERENCES `clientcompany` (`CompanyID`),
  ADD CONSTRAINT `fk_jor_processed` FOREIGN KEY (`ProcessedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_jor_requested` FOREIGN KEY (`RequestedBy`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `fk_lv_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_lv_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_lv_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `overtime`
--
ALTER TABLE `overtime`
  ADD CONSTRAINT `fk_ot_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_ot_attendance` FOREIGN KEY (`AttendanceID`) REFERENCES `attendance` (`AttendanceID`),
  ADD CONSTRAINT `fk_ot_deployment` FOREIGN KEY (`DeploymentID`) REFERENCES `deployment` (`DeploymentID`),
  ADD CONSTRAINT `fk_ot_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_ot_requestedby` FOREIGN KEY (`RequestedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_ot_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`);

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `fk_pay_dtr` FOREIGN KEY (`DTR_SubmissionID`) REFERENCES `dtr_submissions` (`DTR_SubmissionID`),
  ADD CONSTRAINT `fk_pay_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_pay_holiday` FOREIGN KEY (`HolidayID`) REFERENCES `holidays` (`HolidayID`),
  ADD CONSTRAINT `fk_pay_processedby` FOREIGN KEY (`ProcessedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_pay_salaryrate` FOREIGN KEY (`SalaryRatesID`) REFERENCES `salary_rates` (`SalaryRatesID`),
  ADD CONSTRAINT `fk_pay_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`);

--
-- Constraints for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  ADD CONSTRAINT `fk_pa_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_pa_createdby` FOREIGN KEY (`CreatedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_pa_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_pa_payroll` FOREIGN KEY (`Payroll_ID`) REFERENCES `payroll` (`Payroll_ID`);

--
-- Constraints for table `payroll_audit_log`
--
ALTER TABLE `payroll_audit_log`
  ADD CONSTRAINT `fk_pal_payroll` FOREIGN KEY (`Payroll_ID`) REFERENCES `payroll` (`Payroll_ID`),
  ADD CONSTRAINT `fk_pal_performedby` FOREIGN KEY (`PerformedBy`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `payslip`
--
ALTER TABLE `payslip`
  ADD CONSTRAINT `fk_ps_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_ps_payroll` FOREIGN KEY (`Payroll_ID`) REFERENCES `payroll` (`Payroll_ID`);

--
-- Constraints for table `performance`
--
ALTER TABLE `performance`
  ADD CONSTRAINT `fk_perf_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_perf_reviewer` FOREIGN KEY (`ReviewerID`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `resignationrequest`
--
ALTER TABLE `resignationrequest`
  ADD CONSTRAINT `fk_rr_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_rr_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `fk_rr_processedby` FOREIGN KEY (`ProcessedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_rr_status` FOREIGN KEY (`StatusID`) REFERENCES `status` (`StatusID`);

--
-- Constraints for table `salary_rates`
--
ALTER TABLE `salary_rates`
  ADD CONSTRAINT `fk_sr_company` FOREIGN KEY (`CompanyID`) REFERENCES `clientcompany` (`CompanyID`),
  ADD CONSTRAINT `fk_sr_createdby` FOREIGN KEY (`CreatedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_sr_deployment` FOREIGN KEY (`DeploymentID`) REFERENCES `deployment` (`DeploymentID`),
  ADD CONSTRAINT `fk_sr_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`);

--
-- Constraints for table `serviceagreement`
--
ALTER TABLE `serviceagreement`
  ADD CONSTRAINT `fk_sa_approvedby` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`UsersID`),
  ADD CONSTRAINT `fk_sa_company` FOREIGN KEY (`ClientCompanyID`) REFERENCES `clientcompany` (`CompanyID`),
  ADD CONSTRAINT `fk_sa_createdby` FOREIGN KEY (`CreatedBy`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `systemsettings`
--
ALTER TABLE `systemsettings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`UpdatedBy`) REFERENCES `users` (`UsersID`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`RoleID`) REFERENCES `roles` (`RolesID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
