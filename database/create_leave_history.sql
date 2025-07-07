-- Create leave history table
CREATE TABLE IF NOT EXISTS `leave_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `status_from` varchar(50) NOT NULL,
  `status_to` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `application_id` (`application_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `leave_history_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `leave_applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `leave_history_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 