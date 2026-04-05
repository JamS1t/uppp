-- uppp database schema
-- Exported from live MariaDB 10.4.32 instance
-- Run in phpMyAdmin or: mysql -u root -p < sql/schema.sql

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Database
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `uppp`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `uppp`;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            int(11)              NOT NULL AUTO_INCREMENT,
  `username`      varchar(50)          NOT NULL,
  `email`         varchar(255)         NOT NULL,
  `password_hash` varchar(255)         NOT NULL,
  `role`          enum('user','admin') NOT NULL DEFAULT 'user',
  `is_banned`     tinyint(1)           NOT NULL DEFAULT 0,
  `bio`           text                 DEFAULT NULL,
  `avatar_path`   varchar(255)         DEFAULT NULL,
  `website_url`   varchar(255)         DEFAULT NULL,
  `created_at`    datetime             NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `name`        varchar(100) NOT NULL,
  `slug`        varchar(100) NOT NULL,
  `description` text         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: submissions
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submissions`;
CREATE TABLE `submissions` (
  `id`          int(11)                               NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)                               NOT NULL,
  `category_id` int(11)                               NOT NULL,
  `title`       varchar(255)                          NOT NULL,
  `url`         varchar(500)                          NOT NULL,
  `description` text                                  NOT NULL,
  `status`      enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`  datetime                              NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`user_id`)     REFERENCES `users`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: votes
-- --------------------------------------------------------

DROP TABLE IF EXISTS `votes`;
CREATE TABLE `votes` (
  `id`            int(11)    NOT NULL AUTO_INCREMENT,
  `user_id`       int(11)    NOT NULL,
  `submission_id` int(11)    NOT NULL,
  `vote_type`     tinyint(4) NOT NULL,
  `created_at`    datetime   NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_vote` (`user_id`, `submission_id`),
  KEY `submission_id` (`submission_id`),
  CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`user_id`)       REFERENCES `users`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: comments
-- --------------------------------------------------------

DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `id`            int(11)  NOT NULL AUTO_INCREMENT,
  `user_id`       int(11)  NOT NULL,
  `submission_id` int(11)  NOT NULL,
  `body`          text     NOT NULL,
  `created_at`    datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `submission_id` (`submission_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`user_id`)       REFERENCES `users`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: reports
-- --------------------------------------------------------

DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id`            int(11)                                NOT NULL AUTO_INCREMENT,
  `user_id`       int(11)                                NOT NULL,
  `submission_id` int(11)                                DEFAULT NULL,
  `comment_id`    int(11)                                DEFAULT NULL,
  `reason`        text                                   NOT NULL,
  `status`        enum('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `created_at`    datetime                               NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `submission_id` (`submission_id`),
  KEY `comment_id` (`comment_id`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`)       REFERENCES `users`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`comment_id`)    REFERENCES `comments`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_report_target` CHECK (
    (`submission_id` IS NOT NULL AND `comment_id` IS NULL) OR
    (`submission_id` IS NULL     AND `comment_id` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Seed data: categories (10 default categories)
-- --------------------------------------------------------

INSERT INTO `categories` (`id`, `name`, `slug`, `description`) VALUES
(1,  'Developer Tools',       'developer-tools',    'IDEs, code editors, debugging tools, and developer utilities'),
(2,  'Design',                'design',             'Design tools, UI kits, and creative resources'),
(3,  'Productivity',          'productivity',       'Task management, note-taking, and workflow tools'),
(4,  'AI & Machine Learning', 'ai-ml',              'AI tools, ML platforms, and data science resources'),
(5,  'Browser Extensions',    'browser-extensions', 'Useful browser add-ons and extensions'),
(6,  'Open Source',           'open-source',        'Notable open source projects and repositories'),
(7,  'APIs & Services',       'apis-services',      'Useful APIs, cloud services, and integrations'),
(8,  'Learning',              'learning',           'Tutorials, courses, documentation, and educational resources'),
(9,  'Marketing',             'marketing',          'SEO tools, analytics, social media, and marketing resources'),
(10, 'Other',                 'other',              'Everything else that does not fit in the above categories');

-- --------------------------------------------------------
-- Seed data: admin user
-- Default credentials: admin@uppp.local / password
-- --------------------------------------------------------

INSERT INTO `users` (`username`, `email`, `password_hash`, `role`) VALUES
('admin', 'admin@uppp.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
