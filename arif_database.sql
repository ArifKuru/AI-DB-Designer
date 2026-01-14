-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Anamakine: localhost
-- Üretim Zamanı: 15 Oca 2026, 00:00:15
-- Sunucu sürümü: 10.6.19-MariaDB
-- PHP Sürümü: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `arif_database`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','rules_extracted','components_ready','tables_created','audit_passed','normalized','diagram_generated','completed') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `project_columns`
--

CREATE TABLE `project_columns` (
  `id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `data_type` varchar(50) DEFAULT NULL,
  `is_primary_key` tinyint(1) DEFAULT 0,
  `is_foreign_key` tinyint(1) DEFAULT 0,
  `is_nullable` tinyint(1) DEFAULT 1,
  `is_unique` tinyint(1) DEFAULT 0,
  `check_constraint` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `project_exports`
--

CREATE TABLE `project_exports` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `sql_content` longtext NOT NULL,
  `version_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `project_missing_rules`
--

CREATE TABLE `project_missing_rules` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `missing_rule` text DEFAULT NULL,
  `related_br` varchar(50) DEFAULT NULL,
  `solution` text DEFAULT NULL,
  `status` enum('pending','resolved','ignored') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `project_normalization_logs`
--

CREATE TABLE `project_normalization_logs` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `stage` enum('1NF','2NF','3NF') NOT NULL,
  `log_type` enum('INFO','WARNING','CHANGE') DEFAULT 'CHANGE',
  `table_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `project_relationships`
--

CREATE TABLE `project_relationships` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `parent_table_id` int(11) NOT NULL,
  `child_table_id` int(11) NOT NULL,
  `cardinality` enum('1:1','1:N','M:N') NOT NULL DEFAULT '1:N',
  `label` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `project_rules`
--

CREATE TABLE `project_rules` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `rule_id` varchar(20) DEFAULT NULL,
  `rule_type` char(1) DEFAULT NULL,
  `rule_statement` text NOT NULL,
  `rule_rationale` text DEFAULT NULL,
  `implementation_type` varchar(50) DEFAULT 'Constraint',
  `entity_component` char(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `project_tables`
--

CREATE TABLE `project_tables` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `normalization_level` varchar(10) DEFAULT '0NF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `gemini_api_key` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `project_columns`
--
ALTER TABLE `project_columns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `table_id` (`table_id`);

--
-- Tablo için indeksler `project_exports`
--
ALTER TABLE `project_exports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Tablo için indeksler `project_missing_rules`
--
ALTER TABLE `project_missing_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Tablo için indeksler `project_normalization_logs`
--
ALTER TABLE `project_normalization_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Tablo için indeksler `project_relationships`
--
ALTER TABLE `project_relationships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_rel_project` (`project_id`),
  ADD KEY `idx_project_rel_parent` (`parent_table_id`),
  ADD KEY `idx_project_rel_child` (`child_table_id`);

--
-- Tablo için indeksler `project_rules`
--
ALTER TABLE `project_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Tablo için indeksler `project_tables`
--
ALTER TABLE `project_tables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Tablo için indeksler `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_settings_user` (`user_id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `project_columns`
--
ALTER TABLE `project_columns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `project_exports`
--
ALTER TABLE `project_exports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `project_missing_rules`
--
ALTER TABLE `project_missing_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `project_normalization_logs`
--
ALTER TABLE `project_normalization_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Tablo için AUTO_INCREMENT değeri `project_relationships`
--
ALTER TABLE `project_relationships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- Tablo için AUTO_INCREMENT değeri `project_rules`
--
ALTER TABLE `project_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Tablo için AUTO_INCREMENT değeri `project_tables`
--
ALTER TABLE `project_tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- Tablo için AUTO_INCREMENT değeri `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `project_columns`
--
ALTER TABLE `project_columns`
  ADD CONSTRAINT `project_columns_ibfk_1` FOREIGN KEY (`table_id`) REFERENCES `project_tables` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `project_exports`
--
ALTER TABLE `project_exports`
  ADD CONSTRAINT `project_exports_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `project_missing_rules`
--
ALTER TABLE `project_missing_rules`
  ADD CONSTRAINT `project_missing_rules_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `project_normalization_logs`
--
ALTER TABLE `project_normalization_logs`
  ADD CONSTRAINT `project_normalization_logs_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `project_relationships`
--
ALTER TABLE `project_relationships`
  ADD CONSTRAINT `fk_rel_child` FOREIGN KEY (`child_table_id`) REFERENCES `project_tables` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rel_parent` FOREIGN KEY (`parent_table_id`) REFERENCES `project_tables` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rel_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `project_rules`
--
ALTER TABLE `project_rules`
  ADD CONSTRAINT `project_rules_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `project_tables`
--
ALTER TABLE `project_tables`
  ADD CONSTRAINT `project_tables_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `settings`
--
ALTER TABLE `settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
