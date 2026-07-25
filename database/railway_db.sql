-- Database: railway_db
-- Tripura Railway Ticket Booking System
-- Open Source Web Application Project

CREATE DATABASE IF NOT EXISTS `railway_db`;
USE `railway_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `passengers`, `bookings`, `train_stops`, `trains`, `users`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(15) NOT NULL,
  `role` ENUM('user', 'admin') DEFAULT 'user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Trains Table (Stoppage-based routes, with travel class seat limits & operating days)
CREATE TABLE IF NOT EXISTS `trains` (
  `train_id` INT AUTO_INCREMENT PRIMARY KEY,
  `train_number` VARCHAR(10) NOT NULL UNIQUE,
  `train_name` VARCHAR(100) NOT NULL,
  `seats_gn` INT NOT NULL DEFAULT 50,
  `seats_2s` INT NOT NULL DEFAULT 40,
  `seats_sl` INT NOT NULL DEFAULT 60,
  `seats_cc` INT NOT NULL DEFAULT 30,
  `seats_3ac` INT NOT NULL DEFAULT 30,
  `running_days` VARCHAR(100) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
  `is_deleted` TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Train Intermediate Stops Table (with arrival, departure, day offsets, and segment-by-segment fares)
CREATE TABLE IF NOT EXISTS `train_stops` (
  `stop_id` INT AUTO_INCREMENT PRIMARY KEY,
  `train_id` INT NOT NULL,
  `station_name` VARCHAR(100) NOT NULL,
  `stop_order` INT NOT NULL,
  `arrival_time` TIME NULL,
  `departure_time` TIME NULL,
  `arrival_day` INT NOT NULL DEFAULT 0,
  `departure_day` INT NOT NULL DEFAULT 0,
  `segment_fare` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`train_id`) REFERENCES `trains`(`train_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Bookings Table (with updated ENUM options matching the 5 classes)
CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` INT AUTO_INCREMENT PRIMARY KEY,
  `pnr_number` VARCHAR(10) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `train_id` INT NOT NULL,
  `boarding_station` VARCHAR(100) NOT NULL,
  `deboarding_station` VARCHAR(100) NOT NULL,
  `travel_class` ENUM('GN', '2S', 'SL', 'CC', '3AC') DEFAULT 'GN',
  `booking_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `journey_date` DATE NOT NULL,
  `total_passengers` INT NOT NULL,
  `total_fare` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'Simulated UPI',
  `txn_id` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('Pending', 'Confirmed', 'Cancelled') DEFAULT 'Pending',
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`train_id`) REFERENCES `trains`(`train_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Passengers Table
CREATE TABLE IF NOT EXISTS `passengers` (
  `passenger_id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `passenger_name` VARCHAR(100) NOT NULL,
  `age` INT NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `seat_number` VARCHAR(10) DEFAULT 'S1-12',
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Default Admin and Demo User Accounts
INSERT INTO `users` (`full_name`, `email`, `password`, `phone`, `role`) VALUES
('System Admin', 'admin', '$2y$10$w09Z/sQvM89B0p5b3mX97.x9Qy6tN0T5.oJv5M1h4X8W2N6K9L0i6', '9800000000', 'admin'),
('Demo User 1', 'user1', '$2y$10$gGraGzdDn9cjHo5mu1u6eesnP1CB3kGmeCAp.yskCGo.WP4.KHgYi', '9800000001', 'user'),
('Demo User 2', 'user2', '$2y$10$gGraGzdDn9cjHo5mu1u6eesnP1CB3kGmeCAp.yskCGo.WP4.KHgYi', '9800000002', 'user'),
('Demo User 3', 'user3', '$2y$10$gGraGzdDn9cjHo5mu1u6eesnP1CB3kGmeCAp.yskCGo.WP4.KHgYi', '9800000003', 'user')
ON DUPLICATE KEY UPDATE `email`=`email`, `phone`=VALUES(`phone`);

-- Seed Top 7 Real Tripura Trains with Real Numbers, Seating Limits & Running Days
INSERT INTO `trains` (`train_id`, `train_number`, `train_name`, `seats_gn`, `seats_2s`, `seats_sl`, `seats_cc`, `seats_3ac`, `running_days`, `is_deleted`) VALUES
(1, '55676', 'Dharmanagar - Agartala Passenger (DMR-AGTL)', 100, 80, 0, 0, 0, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 0),
(2, '15664', 'Silchar - Agartala Express (SCL-AGTL)', 50, 40, 60, 30, 30, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 0),
(3, '13173', 'Kanchanjunga Express (SDAH-AGTL)', 40, 30, 80, 30, 40, 'Tue,Thu,Fri,Sun', 0),
(4, '55675', 'Agartala - Dharmanagar Passenger (AGTL-DMR)', 100, 80, 0, 0, 0, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 0),
(5, '15663', 'Agartala - Silchar Express (AGTL-SCL)', 50, 40, 60, 30, 30, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 0),
(6, '13174', 'Kanchanjunga Express (AGTL-SDAH)', 40, 30, 80, 30, 40, 'Tue,Thu,Sat,Sun', 0),
(7, '05644', 'Agartala - Sabroom Special (AGTL-SBRM)', 80, 60, 0, 0, 0, 'Mon,Wed,Fri', 0)
ON DUPLICATE KEY UPDATE `train_number`=`train_number`;

-- Seed Accurate Route Segment Stations, Stop Timings, Days and Segment Pricing
TRUNCATE TABLE `train_stops`;

-- Train 1: 55676 Dharmanagar to Agartala Passenger stops
INSERT INTO `train_stops` (`train_id`, `station_name`, `stop_order`, `arrival_time`, `departure_time`, `arrival_day`, `departure_day`, `segment_fare`) VALUES
(1, 'Dharmanagar (DMR)', 1, NULL, '05:40:00', 0, 0, 0.00),
(1, 'Panisagar (PAN)', 2, '05:52:00', '05:53:00', 0, 0, 10.00),
(1, 'Pencharthal (PEC)', 3, '06:09:00', '06:10:00', 0, 0, 5.00),
(1, 'Kumarghat (KUGT)', 4, '06:30:00', '06:32:00', 0, 0, 5.00),
(1, 'Manu (MANU)', 5, '07:05:00', '07:07:00', 0, 0, 5.00),
(1, 'Ambassa (ABSA)', 6, '08:10:00', '08:12:00', 0, 0, 5.00),
(1, 'Teliamura (TLMR)', 7, '08:50:00', '08:51:00', 0, 0, 5.00),
(1, 'Jirania (JRNA)', 8, '09:18:00', '09:20:00', 0, 0, 5.00),
(1, 'Agartala (AGTL)', 9, '10:30:00', NULL, 0, 0, 5.00);

-- Train 2: 15664 Silchar to Agartala Express stops
INSERT INTO `train_stops` (`train_id`, `station_name`, `stop_order`, `arrival_time`, `departure_time`, `arrival_day`, `departure_day`, `segment_fare`) VALUES
(2, 'Silchar (SCL)', 1, NULL, '08:30:00', 0, 0, 0.00),
(2, 'Katakhal (KTX)', 2, '08:58:00', '09:00:00', 0, 0, 20.00),
(2, 'Badarpur (BPB)', 3, '09:30:00', '09:35:00', 0, 0, 15.00),
(2, 'New Karimganj (NKMG)', 4, '10:08:00', '10:10:00', 0, 0, 10.00),
(2, 'Churaibari (CBZ)', 5, '10:55:00', '10:56:00', 0, 0, 15.00),
(2, 'Dharmanagar (DMR)', 6, '11:15:00', '11:20:00', 0, 0, 15.00),
(2, 'Kumarghat (KUGT)', 7, '12:02:00', '12:04:00', 0, 0, 10.00),
(2, 'Manu (MANU)', 8, '12:44:00', '12:46:00', 0, 0, 10.00),
(2, 'Ambassa (ABSA)', 9, '13:30:00', '13:32:00', 0, 0, 10.00),
(2, 'Teliamura (TLMR)', 10, '14:24:00', '14:26:00', 0, 0, 7.00),
(2, 'Agartala (AGTL)', 11, '16:10:00', NULL, 0, 0, 8.00);

-- Train 3: 13173 Sealdah to Agartala Kanchanjunga Express stops (spans multi-day)
INSERT INTO `train_stops` (`train_id`, `station_name`, `stop_order`, `arrival_time`, `departure_time`, `arrival_day`, `departure_day`, `segment_fare`) VALUES
(3, 'Sealdah (SDAH)', 1, NULL, '06:50:00', 0, 0, 0.00),
(3, 'Malda Town (MLDT)', 2, '13:30:00', '13:40:00', 0, 0, 120.00),
(3, 'New Jalpaiguri (NJP)', 3, '17:55:00', '18:05:00', 0, 0, 60.00),
(3, 'Guwahati (GHY)', 4, '02:40:00', '02:50:00', 1, 1, 80.00),
(3, 'Lumding Jn (LMG)', 5, '06:20:00', '06:35:00', 1, 1, 30.00),
(3, 'Badarpur Jn (BPB)', 6, '12:10:00', '12:20:00', 1, 1, 20.00),
(3, 'Dharmanagar (DMR)', 7, '13:55:00', '14:00:00', 1, 1, 20.00),
(3, 'Ambassa (ABSA)', 8, '15:20:00', '15:22:00', 1, 1, 15.00),
(3, 'Agartala (AGTL)', 9, '17:25:00', NULL, 1, 1, 15.00);

-- Train 4: 55675 Agartala to Dharmanagar Passenger stops
INSERT INTO `train_stops` (`train_id`, `station_name`, `stop_order`, `arrival_time`, `departure_time`, `arrival_day`, `departure_day`, `segment_fare`) VALUES
(4, 'Agartala (AGTL)', 1, NULL, '17:55:00', 0, 0, 0.00),
(4, 'Jirania (JRNA)', 2, '18:10:00', '18:12:00', 0, 0, 5.00),
(4, 'Teliamura (TLMR)', 3, '18:40:00', '18:41:00', 0, 0, 10.00),
(4, 'Ambassa (ABSA)', 4, '19:20:00', '19:22:00', 0, 0, 5.00),
(4, 'Manu (MANU)', 5, '20:18:00', '20:20:00', 0, 0, 5.00),
(4, 'Kumarghat (KUGT)', 6, '20:55:00', '20:57:00', 0, 0, 5.00),
(4, 'Pencharthal (PEC)', 7, '21:18:00', '21:19:00', 0, 0, 5.00),
(4, 'Panisagar (PAN)', 8, '21:35:00', '21:36:00', 0, 0, 5.00),
(4, 'Dharmanagar (DMR)', 9, '22:40:00', NULL, 0, 0, 10.00);

-- Train 5: 15663 Agartala to Silchar Express stops
INSERT INTO `train_stops` (`train_id`, `station_name`, `stop_order`, `arrival_time`, `departure_time`, `arrival_day`, `departure_day`, `segment_fare`) VALUES
(5, 'Agartala (AGTL)', 1, NULL, '11:15:00', 0, 0, 0.00),
(5, 'Teliamura (TLMR)', 2, '12:04:00', '12:06:00', 0, 0, 8.00),
(5, 'Ambassa (ABSA)', 3, '12:42:00', '12:44:00', 0, 0, 7.00),
(5, 'Manu (MANU)', 4, '13:13:00', '13:15:00', 0, 0, 10.00),
(5, 'Kumarghat (KUGT)', 5, '13:43:00', '13:45:00', 0, 0, 10.00),
(5, 'Dharmanagar (DMR)', 6, '14:30:00', '14:35:00', 0, 0, 10.00),
(5, 'Churaibari (CBZ)', 7, '14:55:00', '14:56:00', 0, 0, 15.00),
(5, 'New Karimganj (NKMG)', 8, '16:42:00', '16:44:00', 0, 0, 15.00),
(5, 'Badarpur Jn (BPB)', 9, '17:20:00', '17:25:00', 0, 0, 10.00),
(5, 'Katakhal (KTX)', 10, '17:47:00', '17:49:00', 0, 0, 15.00),
(5, 'Silchar (SCL)', 11, '19:45:00', NULL, 0, 0, 20.00);

-- Train 6: 13174 Agartala to Sealdah Kanchanjunga Express stops (spans multi-day)
INSERT INTO `train_stops` (`train_id`, `station_name`, `stop_order`, `arrival_time`, `departure_time`, `arrival_day`, `departure_day`, `segment_fare`) VALUES
(6, 'Agartala (AGTL)', 1, NULL, '08:05:00', 0, 0, 0.00),
(6, 'Ambassa (ABSA)', 2, '09:12:00', '09:14:00', 0, 0, 15.00),
(6, 'Dharmanagar (DMR)', 3, '11:00:00', '11:05:00', 0, 0, 15.00),
(6, 'Badarpur Jn (BPB)', 4, '12:50:00', '13:00:00', 0, 0, 20.00),
(6, 'Lumding Jn (LMG)', 5, '18:20:00', '18:35:00', 0, 0, 20.00),
(6, 'Guwahati (GHY)', 6, '22:25:00', '22:35:00', 0, 0, 30.00),
(6, 'New Jalpaiguri (NJP)', 7, '07:25:00', '07:35:00', 1, 1, 80.00),
(6, 'Malda Town (MLDT)', 8, '12:15:00', '12:25:00', 1, 1, 60.00),
(6, 'Sealdah (SDAH)', 9, '19:20:00', NULL, 1, 1, 120.00);

-- Train 7: 05644 Agartala to Sabroom Special stops
INSERT INTO `train_stops` (`train_id`, `station_name`, `stop_order`, `arrival_time`, `departure_time`, `arrival_day`, `departure_day`, `segment_fare`) VALUES
(7, 'Agartala (AGTL)', 1, NULL, '16:30:00', 0, 0, 0.00),
(7, 'Sekerkote (SKKE)', 2, '16:43:00', '16:44:00', 0, 0, 5.00),
(7, 'Bishramganj (BHRM)', 3, '17:01:00', '17:03:00', 0, 0, 5.00),
(7, 'Udaipur Tripura (UDPU)', 4, '17:28:00', '17:30:00', 0, 0, 8.00),
(7, 'Belonia (BOLA)', 5, '18:05:00', '18:07:00', 0, 0, 7.00),
(7, 'Sabroom (SBRM)', 6, '18:45:00', NULL, 0, 0, 5.00);

-- Seed Sample Bookings & Passengers
INSERT INTO `bookings` (`booking_id`, `pnr_number`, `user_id`, `train_id`, `boarding_station`, `deboarding_station`, `travel_class`, `booking_date`, `journey_date`, `total_passengers`, `total_fare`, `payment_method`, `txn_id`, `status`) VALUES
(1, 'PNR9861085', 2, 7, 'Agartala (AGTL)', 'Sabroom (SBRM)', 'GN', '2026-07-20 10:15:00', '2026-08-01', 2, 60.00, 'UPI', 'TXN986108577', 'Confirmed'),
(2, 'PNR7742190', 4, 5, 'Agartala (AGTL)', 'Teliamura (TLMR)', 'SL', '2026-07-15 14:20:00', '2026-07-20', 1, 90.00, 'Card', 'TXN774219088', 'Confirmed'),
(3, 'PNR5519302', 3, 2, 'Silchar (SCL)', 'Agartala (AGTL)', '2S', '2026-07-10 11:30:00', '2026-08-03', 1, 80.00, 'NetBanking', 'TXN551930299', 'Cancelled')
ON DUPLICATE KEY UPDATE `pnr_number`=`pnr_number`;

INSERT INTO `passengers` (`booking_id`, `passenger_name`, `age`, `gender`, `seat_number`) VALUES
(1, 'Demo Passenger 1', 24, 'Male', 'GN-01'),
(1, 'Demo Passenger 2', 23, 'Male', 'GN-02'),
(2, 'Demo Passenger 3', 22, 'Female', 'S1-14'),
(3, 'Demo Passenger 4', 23, 'Male', '2S-08')
ON DUPLICATE KEY UPDATE `passenger_name`=`passenger_name`;

SET FOREIGN_KEY_CHECKS = 1;
