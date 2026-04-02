-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 05, 2026 at 09:03 AM
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
-- Database: `criminal_detection_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `criminals`
--

CREATE TABLE `criminals` (
  `id` int(11) NOT NULL,
  `criminal_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `alias_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `crime_type` varchar(100) NOT NULL,
  `crime_description` text DEFAULT NULL,
  `danger_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('wanted','arrested','released','deceased') DEFAULT 'wanted',
  `arrest_count` int(11) DEFAULT 0,
  `last_seen_location` varchar(255) DEFAULT NULL,
  `last_seen_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `criminals`
--

INSERT INTO `criminals` (`id`, `criminal_code`, `first_name`, `last_name`, `alias_name`, `date_of_birth`, `gender`, `nationality`, `id_number`, `phone`, `address`, `city`, `state`, `crime_type`, `crime_description`, `danger_level`, `status`, `arrest_count`, `last_seen_location`, `last_seen_date`, `notes`, `added_by`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'CRM-000001', 'xxx', 'xxx', 'dd', '2026-02-21', 'male', 'usa', 'ee', 'ee', 'eeee', 'ee', 'ee', 'Robbery', 'df', 'medium', 'wanted', 0, 'fg', '2026-02-21', 'fdv', 1, 1, '2026-02-21 07:31:39', '2026-02-21 07:31:39'),
(2, 'CRM-000002', 'yyy', 'yyy', 'yyy', '2026-02-20', 'male', 'yyy', 'yy', 'yy', 'yy', 'yy', 'yy', 'Assault', 'yyyy', 'medium', 'wanted', 0, 'yy', NULL, 'yy', 1, 1, '2026-02-21 07:44:17', '2026-02-21 07:44:17');

-- --------------------------------------------------------

--
-- Table structure for table `criminal_photos`
--

CREATE TABLE `criminal_photos` (
  `id` int(11) NOT NULL,
  `criminal_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `face_encoding_stored` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `criminal_photos`
--

INSERT INTO `criminal_photos` (`id`, `criminal_id`, `photo_path`, `is_primary`, `face_encoding_stored`, `uploaded_at`) VALUES
(1, 1, 'uploads/criminals/1/photo_69995f5b13f2d.jpg', 1, 1, '2026-02-21 07:31:39'),
(2, 2, 'uploads/criminals/2/photo_699962519f051.jpg', 1, 1, '2026-02-21 07:44:17');

-- --------------------------------------------------------

--
-- Table structure for table `detection_alerts`
--

CREATE TABLE `detection_alerts` (
  `id` int(11) NOT NULL,
  `criminal_id` int(11) NOT NULL,
  `detected_by_user` int(11) NOT NULL,
  `confidence_score` decimal(5,2) NOT NULL,
  `detection_frame` longblob DEFAULT NULL,
  `detection_screenshot` varchar(255) DEFAULT NULL,
  `detection_location` varchar(255) DEFAULT NULL,
  `camera_source` varchar(100) DEFAULT 'webcam',
  `alert_status` enum('new','acknowledged','investigating','resolved','false_alarm') DEFAULT 'new',
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `detection_alerts`
--

INSERT INTO `detection_alerts` (`id`, `criminal_id`, `detected_by_user`, `confidence_score`, `detection_frame`, `detection_screenshot`, `detection_location`, `camera_source`, `alert_status`, `acknowledged_by`, `acknowledged_at`, `notes`, `detected_at`) VALUES
(1, 1, 1, 74.28, NULL, 'uploads/screenshots/detect_69996070c4c18.jpg', NULL, 'webcam', 'new', NULL, NULL, NULL, '2026-02-21 07:36:16'),
(2, 2, 1, 69.04, NULL, 'uploads/screenshots/detect_69996299383ea.jpg', NULL, 'webcam', 'new', NULL, NULL, NULL, '2026-02-21 07:45:29'),
(3, 2, 1, 66.67, NULL, 'uploads/screenshots/detect_6999629acdfab.jpg', NULL, 'webcam', 'new', NULL, NULL, NULL, '2026-02-21 07:45:30'),
(4, 2, 1, 61.00, NULL, 'uploads/screenshots/detect_6999629b42e40.jpg', NULL, 'webcam', 'acknowledged', 1, '2026-02-21 13:16:07', '\ngg', '2026-02-21 07:45:31');

-- --------------------------------------------------------

--
-- Table structure for table `detection_sessions`
--

CREATE TABLE `detection_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(100) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `frames_processed` int(11) DEFAULT 0,
  `faces_detected` int(11) DEFAULT 0,
  `matches_found` int(11) DEFAULT 0,
  `status` enum('active','ended','error') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `detection_sessions`
--

INSERT INTO `detection_sessions` (`id`, `user_id`, `session_token`, `start_time`, `end_time`, `frames_processed`, `faces_detected`, `matches_found`, `status`) VALUES
(1, 1, '473c19438ccc9059cec8474f5a23d94f17aaa1c974a88a20190f52c05eabeaa4', '2026-02-21 13:04:36', '2026-02-21 13:13:47', 538, 496, 1, 'ended'),
(2, 1, '743e2491288d1fd2ec68f81a173ab82ccb72790bae797ac6f77e606c92559386', '2026-02-21 13:12:48', NULL, 0, 0, 0, 'active'),
(3, 1, 'f4b3759b710cdd72382e469a89f4672ac5c92e39cd2310abf0e36861be12d1da', '2026-02-21 13:14:29', NULL, 0, 0, 0, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `face_encodings`
--

CREATE TABLE `face_encodings` (
  `id` int(11) NOT NULL,
  `criminal_id` int(11) NOT NULL,
  `photo_id` int(11) NOT NULL,
  `encoding_data` longblob NOT NULL,
  `encoding_hash` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `face_encodings`
--

INSERT INTO `face_encodings` (`id`, `criminal_id`, `photo_id`, `encoding_data`, `encoding_hash`, `created_at`) VALUES
(2, 1, 1, 0x000000e0f45dea3f000000401475b2bf000000e00cf3f1bf00000060902be13f000000c06af3f2bf0000006059a7d73f000000a04c2ce1bf0000004081d2f73f00000040eb98f03f00000000fe88f6bf000000402d1ff7bf00000020fcc0d7bf00000080dc90db3f00000080dff6dcbf000000604365da3f00000000ee72f1bf000000602aaed5bf000000c01841cb3f0000008066ecf43f000000a043b5f6bf00000060dbb1dfbf000000800366ef3f000000c0bf54e03f00000080c61df13f00000000b10ddc3f000000e054a0ef3f0000006077c88f3f000000603430ab3f000000a033ade33f00000060c980ee3f00000000040feebf000000a08c2ae63f000000e0f2e5a33f00000020d5abdabf0000004030bcd93f00000060bbc39dbf00000000268ad13f000000e08951fd3f000000409155a43f000000407f57edbf000000c0ffc8d4bf000000401613a63f000000a0382af3bf00000080fcfee9bf0000006092ecf2bf0000008055f6ecbf000000000d25c73f00000000e13fe43f0000000033c7e7bf000000602a47903f00000080511cfabf000000a0b964cf3f000000a04944ca3f0000008082e2d23f000000a063aaf13f000000203309ff3f00000040d836ff3f000000605a81d8bf00000040cd82f73f000000e03bf4f43f000000a01d5ffabf000000e03fa9c3bf000000a070ced73f00000000f0feadbf000000208ef3e13f000000a03800b83f000000206940e23f0000000025d2f13f000000a0de5ddf3f00000040ad3501c0000000c061fbebbf000000c0a37ddbbf000000005b9dd53f000000c04aafe03f00000060bfd8eb3f000000405922ebbf000000e05f37f4bf00000020f771dbbf000000a06c56edbf00000040e30bf3bf000000401805df3f000000607e07f5bf000000607130034000000080a689d43f000000402f0103c0000000a0c5bed5bf000000c00409ffbf000000e0546601c0000000802a34ca3f00000080d88cfabf000000406e06e73f0000000089e601400000004007dec03f000000c07b38cc3f000000600074dcbf000000c0258bed3f00000040c6fff5bf000000a0bb2b02c000000000673600c0000000007f43c8bf000000e08bcaf3bf000000002565f0bf000000e091cfc53f00000040df52bf3f000000404552dabf00000060ebf07ebf000000e030daecbf00000080fbeaf6bf000000c07146f0bf0000004064d0d4bf00000080b000e0bf000000a0898ff93f000000c04f6ae4bf00000000d98be73f000000a0d23cd23f000000c050a2de3f0000004082e9e03f000000601b8bf63f000000a0d1dce8bf00000020796ae0bf000000a0c177f0bf000000e05d00e83f0000006053a206400000008011c900c000000000d6e7e6bf000000a07580f13f000000c028c9eabf000000c0a62af3bf000000c03f9c80bf00000000bcb0ebbf000000a0c39cc6bf00000000f848e53f000000208423d8bf000000002ae786bf0000000010a4e8bf000000a0011df03f000000002e11f9bf000000605cbb833f0000000096deec3f000000e0ace7e6bf000000c04c13fdbf00000040828881bf000000809e1dde3f000000a0c878f7bf00000060fa19f43f000000e0184cf13f00000060ca21e3bf000000e011c4e2bf000000a02ec6d1bf000000c0842db4bf000000c0fa6cf63f000000a01b87ddbf00000020ef36cb3f0000002065fadbbf000000005805efbf00000060d4abf43f000000200c01f1bf000000404444e13f000000c051efd4bf00000060972ef1bf00000020a610e2bf000000605a7fed3f00000080be53e2bf000000600850f4bf000000802071debf00000000ea14e13f000000c0309ee4bf000000c06555a1bf000000a06550f4bf000000a09b86fe3f000000a0b85be03f00000060689cdb3f000000c07dd3f83f000000005f12e53f000000a0e381f53f0000004045b0f1bf000000607cefe73f000000807fd0cebf00000000181fc3bf000000c06ad8e6bf000000a0eea3e73f00000000c011f53f00000040b614e83f00000080c987f1bf000000406281f0bf000000209e4be1bf000000c044c1f83f000000e0c00bc03f000000a0cf9df03f000000e0f99af8bf00000020781ef43f000000609b1dd63f00000060fb55e93f000000606196e33f00000020b4a0f9bf000000a09354d53f000000c0b378c7bf000000a0fe60f73f0000000099e8f1bf00000020b35bdb3f000000c04db7e9bf000000e0da6d0040000000603da4f63f00000000578af13f000000204613e23f0000004058f0c23f00000020d20ee63f00000060923fe7bf000000c09402e33f000000802dbaa8bf00000080484cff3f000000402897dd3f000000a0139dfdbf0000004052460640000000402aa7e43f000000c0ac0ab93f000000c0b858e53f000000806305fcbf00000060005deebf000000408241e5bf00000040cf58ebbf000000e09c71eabf00000000a5eff5bf000000e0d3c2f9bf000000009e9af4bf00000060a217e23f000000805603d53f000000002671f43f000000a0338cd83f000000a0c3a5db3f0000002060dfbf3f00000060fc26d43f00000040c5d9c4bf00000060366ce6bf00000060f247ebbf000000e0e0dcd73f00000020b73de2bf000000405a73f43f000000a03850d1bf000000000a0cf9bf00000020a477f7bf00000000a576943f000000202aa5da3f00000020fd23ebbf000000a0978ae3bf000000a0f4a3fa3f0000004018f7d23f000000e0699ed4bf000000008df0ddbf0000004018cac33f000000c0d529d03f00000040b8eee9bf000000a0c422d0bf000000a090f5004000000060d7efdbbf000000a0898ce3bf0000008056f5adbf000000805218debf00000000de1ac53f000000e0cdcbd3bf000000203ebded3f000000c0340cf1bf000000200a61d93f000000a0bf55f1bf00000020d70b02c0000000204a7af4bf00000000545cd83f000000401824dc3f000000603500f7bf000000e0f5dcd53f000000e08c92d73f0000008045f6b5bf00000040f36fe83f0000008050e0f33f000000008f8ec8bf000000a05666b83f000000c06ebddfbf000000e0cb21e03f0000004099519ebf0000006010c3ee3f000000c0f287e5bf000000409a55d93f00000060ba7604c0000000e0a60ff0bf000000a04dcf03c000000060dbadf63f00000080a9a4af3f000000c00587f3bf000000602dc8d8bf000000e05619e53f000000c0a9a3e8bf00000080985deabf000000c0d512e0bf000000007184f53f00000020e7b6e9bf000000c00223f4bf000000801770e03f0000006090bef4bf0000004011f3eabf000000c049d5cd3f00000040c507ebbf000000202f63fbbf00000060ec79f63f00000080750ff23f000000c04ca3f4bf000000a0ab66e7bf000000207013c9bf00000020e6eee63f000000407803e13f000000408d76a6bf00000020fcf1e3bf00000000ee5dbdbf00000040622ee53f000000e0d4faf63f0000000084beecbf000000c0aab2fd3f000000006633c83f00000080815ab1bf0000006046f1f73f00000020990edd3f000000809f67bfbf000000c063cae9bf000000006ce9ec3f000000c0c727ef3f00000080e94ff03f00000060cad0e63f000000e01ea6e83f00000040e5edf3bf000000c01cf4e6bf000000c0190eb03f000000406158fa3f0000000066c9febf00000040349fd43f0000002086f6f2bf000000a0ffcef8bf000000608fb5e7bf000000002c34d13f000000002cc1de3f00000040949bf23f00000060cbf4dc3f00000000da3ce23f00000040afd2f2bf00000020d7fed5bf000000405951f7bf000000209da4ef3f0000002053e809c0000000408e62dcbf000000a01870044000000080f164efbf000000608f7eec3f000000e0787ed6bf000000e090cbf03f000000a07c86f8bf0000004031c3efbf00000060b654fbbf000000a02d7ce33f00000000d672e83f000000c05559dcbf00000080c0a6e4bf000000406fdac13f000000806c6ec0bf00000000dd4e763f0000008067dfe23f00000060aa3ae53f000000803741c9bf000000e08fccf63f000000c0266bcebf00000020e0d1c03f00000040af43e03f0000002029f0f63f000000c073e1b3bf000000203f68f73f00000000906bc9bf000000e0446403c0000000408d1eecbf000000a0c785d23f00000020019bf63f000000c0381000c0000000e0b11bf63f00000000b966f5bf00000020c37dfbbf00000000bf22d4bf000000c0f1cf0340000000204e94cbbf00000000d52ea43f0000002038dce3bf000000c0c9a8d6bf000000e04aa3f2bf000000e0b974dfbf000000a0302501c0000000a08ac7f33f000000407589cd3f000000206f5aea3f0000004040c9f6bf00000000ceabf13f00000080bc4ed73f00000020c536fb3f00000020faceee3f00000080387ee13f00000080b6b5dd3f000000005175ecbf00000060075aec3f00000000a6eff0bf00000080acb2024000000040d6aad73f00000000dc30fbbf000000c00af0d7bf00000080bf50c13f000000e0a683a33f000000a093e00940000000a0795feebf000000a0a886e23f000000e02439d5bf000000c0cddec1bf000000e005deb1bf000000e03abbe0bf00000060e1f7e33f000000806e3f01c0000000c0daa1f43f000000600a68eabf000000e09fcdf0bf00000040a054ed3f00000040e961c03f000000e06341b4bf000000c0051cf0bf000000606c90eebf000000000426bfbf000000801444f33f000000e083ebedbf000000c00fefc33f000000a08b32fe3f00000040698bc3bf000000809949f1bf000000407a53ce3f000000c0d734d4bf000000e0e322ca3f000000e052d4f83f000000a079e5ebbf000000209886fd3f000000e093cdd8bf0000006027d4f3bf000000a0d8f8d8bf000000201bc2d6bf000000e08d3bffbf000000e0ac2af73f000000c0d916fa3f000000807626fabf000000c00a10d33f000000207063f8bf000000a0247ebf3f000000c05944d7bf000000c015e9e83f000000606ed0e7bf000000a0b471b53f000000807cc0b33f000000407b46e1bf000000403307c53f000000404d4ba7bf0000004004d7f63f000000a099faf43f000000408d05debf000000e094d1c83f000000e077fb02c000000020e82bcfbf000000c0daffe0bf000000e0b8b0f2bf00000080cf3de1bf000000804d95f9bf000000a090eec23f00000040eed0a1bf000000c0a0e1d9bf000000c0080ff2bf000000808eabf23f000000207fb6ee3f000000605809f43f0000006016beac3f000000402474b5bf0000002048a5e33f00000000777cefbf00000080bffde63f0000008032c3fc3f0000008088efd3bf000000a00211f13f000000607238d2bf00000000f699f0bf00000000624a9abf000000c0bbdafdbf000000e081cae1bf00000020cf8f01c0000000c0269bfc3f000000803913f9bf00000020b1f5f7bf00000080fe29b2bf000000a05708febf000000601898f43f000000807efad63f00000040bbd0d3bf000000802beef43f000000407a33f0bf000000c03e60e9bf00000000adb2f23f00000020c144f8bf00000080a960f53f000000205993e63f000000a0cdf4f43f000000e02e3ffabf00000000ecd4f1bf000000c0b46cfb3f000000e0e1cffb3f00000040e463fe3f000000a0883af4bf000000801ad103c0, NULL, '2026-02-21 07:44:26'),
(3, 2, 2, 0x00000080199fd0bf00000040bd6ff73f00000060884ee33f000000208677ebbf0000008020e8c03f00000000fba4c63f000000c0bc83db3f000000c04657d73f000000c06f81fb3f000000809169e2bf00000080b77dd4bf000000402643c3bf000000006859d1bf000000e08972bb3f00000000bd2febbf00000020a214e5bf0000004029c6fdbf0000006013a3cf3f000000c0be49e5bf00000080e4b6dd3f000000e0c7a4593f000000801643d93f000000c05ff7e53f0000000025dcadbf00000000516ccebf00000020c941f03f00000020968bcbbf000000608404cdbf000000a07733ecbf000000a04d28e2bf000000600fdc0040000000e05638fc3f000000008b40dd3f000000e01e689c3f000000a0de3e094000000080b9e08b3f00000020b302e03f000000402f51e4bf000000e08a5bd4bf000000a0c48de83f000000e0cb25f83f000000e0d3a0f1bf000000a0ec5cc1bf000000404ae7ea3f000000e07710eabf000000c08b9502c0000000a01a8406c00000002056c7f63f000000e0b3fdf4bf000000808680e7bf00000080adf0f2bf00000020408befbf000000e0d9c2e0bf00000040ea00da3f000000605fc1e03f00000040300bfa3f00000040713cc5bf000000e0ea31ecbf000000a0bcafe03f00000040147dd83f00000040b9eaf53f00000080fa3dfa3f000000600517e43f00000020882ec0bf0000000075657a3f00000060e13acfbf000000606fb6d5bf000000a0ea94edbf00000060079bea3f000000e0eb53e0bf000000e0fe32c13f000000409212efbf000000a0ebbbf2bf000000c02f95d5bf000000a037d4ed3f00000040d9fa623f0000004005f2cdbf00000000e8fbc0bf000000a0f8bec73f00000020dad8dbbf000000804362c2bf000000c03d45d6bf000000605b92f8bf000000a03766d1bf00000080271e703f000000a06504f0bf000000a05564e53f000000c05b60c53f00000080bffdd7bf000000e098f9efbf000000a05f3cdf3f00000020e346debf000000c0b594fc3f00000000c9f2f6bf000000e05ccadf3f00000000665460bf00000060239ef0bf000000c0af3ef3bf000000400de1edbf000000600bb2f53f00000020965ef33f000000409172eb3f00000000275ea83f000000a0fbd6f43f000000c0f299c6bf00000000e436f63f0000008054adb83f00000060be34ec3f000000c0aba7c9bf00000000715ada3f000000e05aabe13f000000608109ea3f000000407072c9bf000000201800d03f00000060a06de1bf000000a02879f1bf000000600fb7ebbf000000605f45fa3f0000006025c3fbbf00000020aedaefbf00000060f57dedbf000000e0cc68d23f000000e0fbd2dabf000000c06513fc3f000000e002d4fcbf00000060f62acd3f000000400a2ee4bf00000040abc6f2bf000000c0111ce5bf000000a0b5e4f03f0000004004ecf9bf000000c04d21f7bf00000000a945f53f000000e0447503c0000000e01497e33f000000606150ec3f000000e0c08df13f000000c03c38f8bf000000609a90cbbf000000c01752e63f000000805dd9ed3f00000020a04af6bf000000803303e9bf000000002971dc3f000000a06ab3eebf000000401f6cc3bf000000409ccdf2bf000000e02900f53f000000e070b5e2bf000000808b68efbf000000803ca4a33f000000c0db06c33f000000a0fa3de1bf000000c09325f2bf000000c0bfb8e23f000000a0fef1d5bf000000e0adf5e5bf00000000f30ff73f000000a0c337e9bf0000004027c7c03f00000000ffc7a8bf000000a03609f23f00000040e9e1b7bf000000004b5ee33f000000c037bfe43f0000006087b5cdbf0000002083a1c13f000000a0ea39df3f0000006030fcd63f00000020d86fe43f000000e08320e3bf000000e035d4ca3f000000001139eebf000000e079b7be3f000000a07514dd3f000000006212f1bf00000080129ceebf000000008ff3d63f000000609c9c00c000000040be24b33f000000207357f3bf000000008292fe3f00000020acb1ca3f00000060e1d0e9bf000000e0e542d93f000000c02bc6d4bf000000204300c9bf000000c0d787e1bf00000000785215bf0000002038bcd8bf000000e00745f03f000000e01992e03f000000409371f43f0000008068c9f4bf00000000d517ecbf00000020a965dbbf000000a02de6d73f00000000cb61b43f000000205311fa3f00000000c3ccf2bf00000060ff09e43f00000060a736df3f000000004d3fe23f00000040a1cbdfbf00000000cffef43f000000006189e0bf000000c0656bebbf00000020ae9fe0bf00000020c46ed8bf00000020956bf73f00000000c6ba5ebf000000008a9af6bf00000000bd83bebf000000a0f589af3f000000c08a0ec7bf000000e064b2ca3f000000c0d548d03f000000c07a3be1bf00000060f3f6e0bf000000c02ff2e3bf00000000ef90ce3f00000000eb6ce13f000000c00248e23f000000e0f90af83f000000e00447c3bf000000008058d93f000000e0da67ff3f000000e05505f7bf00000040d7fdf8bf00000040d6adc83f00000000feead63f000000802339e43f000000603f4af1bf000000c05323f0bf000000806dc0e03f000000201164004000000080d1e0f4bf000000008b91f73f000000802e3ddebf00000080801ebcbf00000080fcf4f7bf00000040ddd6d43f000000005cf2e23f000000803077a53f000000403363c33f0000004049a2babf000000804f020440000000405abad83f000000601f4e83bf000000204d89e33f000000e0260ae3bf000000009919e4bf000000603503d73f00000060945ce83f000000c08560f03f000000a05195e4bf0000008075b9debf000000605b02f73f000000a06c18eabf000000005464004000000040b2ed0240000000a0ea85e33f00000040a4ecee3f000000c0ec93d3bf0000004009adc5bf000000a0b3e9f1bf00000060c1cef3bf00000080c60afc3f0000008025f5e8bf000000a0e06ef0bf000000402286e43f000000202711fabf00000080b300e0bf000000403e73e93f000000c0c61b0040000000e0d621e23f0000000036bee33f0000004010aff3bf0000002060f5d3bf000000800ac0ad3f0000006061c2bbbf000000200cc3e03f000000000b4af4bf000000c03832d4bf000000c02aa9f7bf00000020af03f8bf000000c08ee4ccbf000000e08ad5be3f000000c032edb4bf00000020b689f63f000000c09309e2bf000000a030cbd53f000000e05e879fbf000000c07a4de4bf000000800a7fed3f00000040db18eb3f00000080bc88bf3f00000040f33900c00000000049e0eebf000000409593e83f000000609162f7bf000000a0f848e43f000000e0188ad33f00000080e912e5bf000000a02925e3bf00000000315fd13f000000a08206e03f000000c0bda5a3bf000000203a6f9a3f00000000ea7fe0bf000000604018f1bf000000205ee3e3bf000000403d49f23f000000e098b2aebf000000c0dbaca2bf00000040ee28f43f000000800c28913f00000040e5f29b3f000000409a71d3bf000000c008ccffbf000000a04767edbf000000a0baddf8bf000000c05ebee4bf000000602421eb3f000000a08504e1bf00000000af4ce43f00000080e851cb3f0000004014dceb3f000000e0f9e9ebbf000000601e1ae63f00000000d2bbdc3f0000008046bfc7bf000000206271f8bf000000401ff3f33f000000a0daece83f000000e0d00de73f00000040af21df3f000000e0e91ff53f00000000dd0bd8bf000000a0d60aefbf000000a09419e5bf000000a0eff8fcbf000000a0dc82f83f000000205851e4bf000000a0c050e4bf000000402c17e7bf00000020e98aef3f000000600995f83f00000000ceee00c000000040ae3df13f000000c0c31003c00000004050f0eb3f000000204d99f33f00000060cdb9da3f000000c01238e0bf00000000cdc5d83f000000c0e1ebf73f00000000d934f1bf00000080db97fc3f000000805d9bdabf000000c0ee86e3bf000000e0e5420340000000008fb4d0bf000000206610fabf000000e0dea3ebbf000000804996ff3f0000008048fef2bf000000809e83024000000080bee1b33f000000c010c6b6bf000000207116eb3f0000000021946abf00000020fbb9df3f000000602b99fe3f000000e09246f9bf000000a0dcaad03f000000c04131ef3f000000204ef3e23f000000809165f43f00000080b194cdbf00000080a1addc3f000000a099e4f73f00000080af83f43f00000000e513edbf00000020e19adb3f000000e04165f1bf000000e03dddf43f000000001f03f1bf000000402d74e8bf00000040e97dca3f000000003033f43f000000808fb8d7bf000000e0d2daecbf000000c08e50cd3f000000c00228da3f000000603ac6f7bf00000020ecead8bf000000e0b8faf83f0000000073d4f2bf00000000f190dbbf000000605b4ad43f000000403129e5bf000000c0e8dbebbf000000009982de3f0000002021bcf0bf000000c08e21f23f00000020f7cdd9bf0000006034aafebf000000003b85fe3f000000e03d5eea3f000000e01221ecbf00000080c14cf73f00000080f9ebca3f00000000e04ff9bf000000e01adde2bf000000e03336e5bf000000e0f9acdebf00000060301000c00000004088edd1bf000000009383e4bf000000e053f5f93f000000a00caec83f000000805907f5bf0000002044acd73f00000080e8470040000000605dbfc7bf00000020d84cec3f000000805c54f1bf000000e002a3d23f000000a0d38bb1bf000000a0e6b0fbbf00000080e24fe1bf000000a0e0d0a3bf000000e0eac4ffbf000000a0f2fdd13f00000060bb7bd3bf000000007ec5e03f000000405716cb3f000000800b70f2bf000000a0d9f4b8bf000000809c73f1bf000000a0e6c0d33f00000080f6a3f6bf000000400ce191bf000000801cc6cc3f000000808ad7dfbf000000202bf0e7bf000000000bd1e6bf000000406468eabf000000009dc9e33f0000002046ace83f000000c0a4daf2bf000000e07e2ddbbf000000008e54bc3f000000408c54f7bf000000203643d2bf000000e04f80fdbf000000208c3fe93f000000609709ef3f000000c05b14c13f00000040e8b5d83f000000603816034000000040e408c6bf00000000d6d1c53f000000003f5adfbf00000040103cf43f000000a0a174ad3f000000e0ded0fdbf0000008028bde03f00000080c3ffedbf00000000fae1afbf000000e034afef3f000000c06298e6bf000000c0fa7ff8bf00000080adc8cc3f00000000c158ed3f00000020ecf1f3bf0000002033dff73f00000080ee9c97bf000000a063e2f5bf0000004079c8d03f000000005fed52bf000000c08f0cce3f0000002063680540000000206b75c93f000000007917b1bf000000e0d406c5bf000000009325eabf00000040e14cf1bf000000e0904701c000000080e9ab74bf00000060d15dd1bf00000040f462b93f000000602313f3bf00000060aa42f03f000000400a75c13f00000060a690fabf000000001beefabf000000c07f34f7bf000000a0d0ebf4bf000000403c96ecbf000000a03745d5bf000000c02060c0bf000000e0dd9bdfbf00000060d9e5c4bf000000808287ecbf00000040643ec23f000000807941d33f000000804356b63f000000002a97d5bf000000805e4ce3bf00000080f429e5bf, NULL, '2026-02-21 07:44:26');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `module`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'login_failed', 'auth', 'Failed login attempt for: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:21:55'),
(2, NULL, 'login_failed', 'auth', 'Failed login attempt for: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:22:05'),
(3, 1, 'login', 'auth', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:26:26'),
(4, 1, 'add_criminal', 'criminals', 'Added criminal: CRM-000001 - xxx xxx', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:31:39'),
(5, 1, 'train_model', 'detection', 'Trained with 1 encodings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:33:02'),
(6, 1, 'criminal_detected', 'detection', 'Criminal ID 1 detected with 74.28% confidence', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:36:16'),
(7, 1, 'add_criminal', 'criminals', 'Added criminal: CRM-000002 - yyy yyy', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:44:17'),
(8, 1, 'train_model', 'detection', 'Trained with 2 encodings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:44:26'),
(9, 1, 'criminal_detected', 'detection', 'Criminal ID 2 detected with 69.04% confidence', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:45:29'),
(10, 1, 'criminal_detected', 'detection', 'Criminal ID 2 detected with 66.67% confidence', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:45:30'),
(11, 1, 'criminal_detected', 'detection', 'Criminal ID 2 detected with 61% confidence', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:45:31'),
(12, 1, 'update_alert', 'alerts', 'Alert #4 status changed to acknowledged', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:46:07'),
(13, 1, 'logout', 'auth', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:49:40'),
(14, NULL, 'logout', 'auth', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 07:53:03');

-- --------------------------------------------------------

--
-- Table structure for table `training_history`
--

CREATE TABLE `training_history` (
  `id` int(11) NOT NULL,
  `trained_by` int(11) NOT NULL,
  `total_criminals` int(11) DEFAULT 0,
  `total_photos` int(11) DEFAULT 0,
  `total_encodings` int(11) DEFAULT 0,
  `training_duration` decimal(10,2) DEFAULT 0.00,
  `status` enum('started','completed','failed') DEFAULT 'started',
  `error_message` text DEFAULT NULL,
  `model_path` varchar(255) DEFAULT NULL,
  `trained_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `training_history`
--

INSERT INTO `training_history` (`id`, `trained_by`, `total_criminals`, `total_photos`, `total_encodings`, `training_duration`, `status`, `error_message`, `model_path`, `trained_at`) VALUES
(1, 1, 1, 1, 1, 1.31, 'completed', NULL, NULL, '2026-02-21 07:33:00'),
(2, 1, 2, 2, 2, 2.58, 'completed', NULL, NULL, '2026-02-21 07:44:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','officer','viewer') DEFAULT 'officer',
  `profile_pic` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `profile_pic`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@crimedetect.com', '$2y$10$sYhfOYNLmHDlcYXSLLIqlOHaH5r8WEXI//MYC/9h0/TBdHY5K9RTm', 'System Administrator', 'admin', NULL, 1, '2026-02-21 12:56:26', '2026-02-21 06:25:44', '2026-02-21 07:26:26');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `criminals`
--
ALTER TABLE `criminals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `criminal_code` (`criminal_code`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `idx_criminal_status` (`status`),
  ADD KEY `idx_criminal_danger` (`danger_level`),
  ADD KEY `idx_criminal_code` (`criminal_code`);

--
-- Indexes for table `criminal_photos`
--
ALTER TABLE `criminal_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `criminal_id` (`criminal_id`);

--
-- Indexes for table `detection_alerts`
--
ALTER TABLE `detection_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `criminal_id` (`criminal_id`),
  ADD KEY `detected_by_user` (`detected_by_user`),
  ADD KEY `acknowledged_by` (`acknowledged_by`),
  ADD KEY `idx_alert_status` (`alert_status`),
  ADD KEY `idx_alert_date` (`detected_at`);

--
-- Indexes for table `detection_sessions`
--
ALTER TABLE `detection_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `face_encodings`
--
ALTER TABLE `face_encodings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `photo_id` (`photo_id`),
  ADD KEY `idx_encoding_criminal` (`criminal_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `training_history`
--
ALTER TABLE `training_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trained_by` (`trained_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `criminals`
--
ALTER TABLE `criminals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `criminal_photos`
--
ALTER TABLE `criminal_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `detection_alerts`
--
ALTER TABLE `detection_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `detection_sessions`
--
ALTER TABLE `detection_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `face_encodings`
--
ALTER TABLE `face_encodings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_logs`
-- 
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `training_history`
--
ALTER TABLE `training_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `criminals`
--
ALTER TABLE `criminals`
  ADD CONSTRAINT `criminals_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `criminal_photos`
--
ALTER TABLE `criminal_photos`
  ADD CONSTRAINT `criminal_photos_ibfk_1` FOREIGN KEY (`criminal_id`) REFERENCES `criminals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `detection_alerts`
--
ALTER TABLE `detection_alerts`
  ADD CONSTRAINT `detection_alerts_ibfk_1` FOREIGN KEY (`criminal_id`) REFERENCES `criminals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detection_alerts_ibfk_2` FOREIGN KEY (`detected_by_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detection_alerts_ibfk_3` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `detection_sessions`
--
ALTER TABLE `detection_sessions`
  ADD CONSTRAINT `detection_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `face_encodings`
--
ALTER TABLE `face_encodings`
  ADD CONSTRAINT `face_encodings_ibfk_1` FOREIGN KEY (`criminal_id`) REFERENCES `criminals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `face_encodings_ibfk_2` FOREIGN KEY (`photo_id`) REFERENCES `criminal_photos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `training_history`
--
ALTER TABLE `training_history`
  ADD CONSTRAINT `training_history_ibfk_1` FOREIGN KEY (`trained_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
