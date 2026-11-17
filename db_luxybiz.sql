-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 16, 2026 at 10:05 AM
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
-- Database: `db_luxybiz`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(20) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tour_id` bigint(20) UNSIGNED NOT NULL,
  `tour_departure_id` bigint(20) UNSIGNED DEFAULT NULL,
  `travelers_count` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `insurance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `payment_status` varchar(12) NOT NULL DEFAULT 'unpaid',
  `source` varchar(10) NOT NULL DEFAULT 'web',
  `insurance_selected` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_changes`
--

CREATE TABLE `booking_changes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_travelers`
--

CREATE TABLE `booking_travelers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `is_lead` tinyint(1) NOT NULL DEFAULT 0,
  `full_name` varchar(140) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `nationality` varchar(80) DEFAULT NULL,
  `passport_number` text DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `arrival_details` varchar(255) DEFAULT NULL,
  `arrival_date` date DEFAULT NULL,
  `arrival_flight` varchar(40) DEFAULT NULL,
  `arrival_time` time DEFAULT NULL,
  `health_notes` text DEFAULT NULL,
  `flag_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(191) NOT NULL,
  `value` mediumtext DEFAULT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(191) NOT NULL,
  `owner` varchar(191) DEFAULT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(191) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(191) NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `total_jobs` int(11) DEFAULT NULL,
  `pending_jobs` int(11) DEFAULT NULL,
  `failed_jobs` int(11) DEFAULT NULL,
  `failed_job_ids` longtext DEFAULT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(7, '0001_01_01_000000_create_users_table', 1),
(8, '0001_01_01_000001_create_cache_table', 1),
(9, '0001_01_01_000002_create_jobs_table', 1),
(10, '2026_10_14_000001_add_staff_fields_to_users_table', 1),
(11, '2026_10_14_000002_create_catalogue_tables', 1),
(12, '2026_10_14_000003_create_operations_tables', 1),
(13, '2026_10_15_000004_extend_operations_tables', 1),
(14, '2026_10_15_000005_create_settings_and_content_tables', 1),
(15, '2026_11_02_065108_create_catalogue_tables', 2),
(16, '2026_11_03_100000_create_bookings_table', 3),
(17, '2026_11_03_100100_create_booking_travelers_table', 3),
(18, '2026_11_03_100200_create_payments_table', 3),
(19, '2026_11_03_100300_create_booking_changes_table', 3),
(20, '2026_11_16_082114_create_tour_categories_table', 4);

-- --------------------------------------------------------

--
-- Table structure for table `objectives`
--

CREATE TABLE `objectives` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(80) DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'charge',
  `channel` varchar(14) NOT NULL DEFAULT 'offline',
  `gateway` varchar(30) DEFAULT NULL,
  `gateway_reference` varchar(120) DEFAULT NULL,
  `gateway_session_id` varchar(120) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `method` varchar(30) DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'pending',
  `failure_code` varchar(80) DEFAULT NULL,
  `card_brand` varchar(20) DEFAULT NULL,
  `card_last4` char(4) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('ithPtLi4sRhCUPQWH5a6kQWC8l2hA3057Dd90Trv', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiWm5rcVoxWWlMSDJWakNxZlNZYTV6YTVnNm9KcEhKdm9uSm1DZkZKRiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czoxMToiYWRtaW4ubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiNDBiMjBjMGE2YzQ1ODdjYTIxYzg0ZmE0ZjRlZGVjZDA0OWU3MzE3NjUyMzI0NDViNjA0ODczMGZkNTJkYTM1YyI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTt9', 1793261411),
('k7FgOKHNypgrTnkTzRlQMC29pnU1hsLls1ovKyle', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiN1pHa3VCV0ExUDBaV1huT21hU2FGdkVoUFE4RThaZU92NXI2b0hsdSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czoxMToiYWRtaW4ubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiI0MGIyMGMwYTZjNDU4N2NhMjFjODRmYTRmNGVkZWNkMDQ5ZTczMTc2NTIzMjQ0NWI2MDQ4NzMwZmQ1MmRhMzVjIjt9', 1793260073),
('jpjvlWJkXNuMOwoUvc80cda7gREI0aicxUDkOoYi', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiUEs0R2RDdTE1dlRyc0tIUU5rQTlaMllSTTFEQlJSZDBxd0xhNmNxZyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czoxMToiYWRtaW4ubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiI0MGIyMGMwYTZjNDU4N2NhMjFjODRmYTRmNGVkZWNkMDQ5ZTczMTc2NTIzMjQ0NWI2MDQ4NzMwZmQ1MmRhMzVjIjt9', 1793260445),
('LE8SPUn57rmf9FmgkRwEhlN9QKqxjQm5aWORt5tz', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiR0lXUzVOWGVSY2FGSGJBT0hSUm54b2tjUFM0N2tOUnNHeDlCek9CRSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czoxMToiYWRtaW4ubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiI0MGIyMGMwYTZjNDU4N2NhMjFjODRmYTRmNGVkZWNkMDQ5ZTczMTc2NTIzMjQ0NWI2MDQ4NzMwZmQ1MmRhMzVjIjt9', 1793261901),
('k1vY6fm65vOQImfzMEYuwTh0cfv27ItPwP2jIEVb', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiZzZxODBwVXVhOEpoNUhvYmR1UkF1d3U5QmE3eUhMWHp1R1VoTVZ5UCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czoxMToiYWRtaW4ubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiI0MGIyMGMwYTZjNDU4N2NhMjFjODRmYTRmNGVkZWNkMDQ5ZTczMTc2NTIzMjQ0NWI2MDQ4NzMwZmQ1MmRhMzVjIjt9', 1793262059),
('NusHm3Kfdehfg21s4O6KCMVT1ITsXspPjec1XF6N', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'YTo2OntzOjY6Il90b2tlbiI7czo0MDoieDBDaFNsdnM4NW05Q2pWaXo0ZDBzMWVua3VwQmx1TDZqbGo4dUQ3UCI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozNzoiaHR0cDovL2xvY2FsaG9zdDo4MDAwL2FkbWluL2Rhc2hib2FyZCI7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjM2OiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYWRtaW4vYXNzaXN0ZWQiO3M6NToicm91dGUiO3M6MjA6ImFkbWluLmFzc2lzdGVkLmluZGV4Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiNDBiMjBjMGE2YzQ1ODdjYTIxYzg0ZmE0ZjRlZGVjZDA0OWU3MzE3NjUyMzI0NDViNjA0ODczMGZkNTJkYTM1YyI7fQ==', 1793265475);

-- --------------------------------------------------------

--
-- Table structure for table `tours`
--

CREATE TABLE `tours` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(160) DEFAULT NULL,
  `slug` varchar(180) NOT NULL,
  `objective_id` bigint(20) UNSIGNED DEFAULT NULL,
  `destination` varchar(120) DEFAULT NULL,
  `duration_days` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `short_description` varchar(400) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `highlights` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `expert_name` varchar(120) DEFAULT NULL,
  `expert_role` varchar(120) DEFAULT NULL,
  `expert_bio` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `hero_image` varchar(191) DEFAULT NULL,
  `meta_title` varchar(70) DEFAULT NULL,
  `meta_description` varchar(170) DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_categories`
--

CREATE TABLE `tour_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tour_categories`
--

INSERT INTO `tour_categories` (`id`, `name`, `slug`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Highlands', 'highlands', 'active', 1, '2026-11-16 03:11:13', '2026-11-16 03:11:13'),
(2, 'Backwaters', 'backwaters', 'active', 2, '2026-11-16 03:11:55', '2026-11-16 03:11:55');

-- --------------------------------------------------------

--
-- Table structure for table `tour_days`
--

CREATE TABLE `tour_days` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tour_id` bigint(20) UNSIGNED NOT NULL,
  `day_number` smallint(5) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `activities` text DEFAULT NULL,
  `meals` varchar(120) DEFAULT NULL,
  `stay` varchar(160) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_departures`
--

CREATE TABLE `tour_departures` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tour_id` bigint(20) UNSIGNED NOT NULL,
  `departs_on` date NOT NULL,
  `capacity` smallint(5) UNSIGNED NOT NULL DEFAULT 12,
  `price_override` decimal(10,2) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'customer',
  `phone` bigint(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `phone`, `is_active`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'info@luxybiz.com', 'admin', NULL, 1, NULL, '$2y$12$3OVQr3zbCDOymZc0qM7xF.ox8NGUi3vqxSBvUe2sAfwmNYu5w.9Um', NULL, '2026-10-29 06:30:34', '2026-10-29 01:01:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bookings_reference_unique` (`reference`),
  ADD KEY `bookings_user_id_foreign` (`user_id`),
  ADD KEY `bookings_created_by_foreign` (`created_by`),
  ADD KEY `bookings_tour_id_index` (`tour_id`),
  ADD KEY `bookings_tour_departure_id_index` (`tour_departure_id`),
  ADD KEY `bookings_status_index` (`status`),
  ADD KEY `bookings_payment_status_index` (`payment_status`),
  ADD KEY `bookings_source_index` (`source`);

--
-- Indexes for table `booking_changes`
--
ALTER TABLE `booking_changes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_changes_changed_by_foreign` (`changed_by`),
  ADD KEY `booking_changes_booking_id_created_at_index` (`booking_id`,`created_at`);

--
-- Indexes for table `booking_travelers`
--
ALTER TABLE `booking_travelers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_travelers_booking_id_foreign` (`booking_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `objectives`
--
ALTER TABLE `objectives`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `objectives_slug_unique` (`slug`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payments_recorded_by_foreign` (`recorded_by`),
  ADD KEY `payments_booking_id_type_status_index` (`booking_id`,`type`,`status`),
  ADD KEY `payments_gateway_reference_index` (`gateway_reference`),
  ADD KEY `payments_gateway_session_id_index` (`gateway_session_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `tours`
--
ALTER TABLE `tours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tours_slug_unique` (`slug`),
  ADD KEY `tours_objective_id_foreign` (`objective_id`),
  ADD KEY `tours_status_index` (`status`);

--
-- Indexes for table `tour_categories`
--
ALTER TABLE `tour_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tour_categories_name_unique` (`name`),
  ADD UNIQUE KEY `tour_categories_slug_unique` (`slug`);

--
-- Indexes for table `tour_days`
--
ALTER TABLE `tour_days`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tour_days_tour_id_foreign` (`tour_id`);

--
-- Indexes for table `tour_departures`
--
ALTER TABLE `tour_departures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tour_departures_tour_id_foreign` (`tour_id`),
  ADD KEY `tour_departures_departs_on_index` (`departs_on`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_role_index` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_changes`
--
ALTER TABLE `booking_changes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_travelers`
--
ALTER TABLE `booking_travelers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `objectives`
--
ALTER TABLE `objectives`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tours`
--
ALTER TABLE `tours`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_categories`
--
ALTER TABLE `tour_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tour_days`
--
ALTER TABLE `tour_days`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_departures`
--
ALTER TABLE `tour_departures`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_changes`
--
ALTER TABLE `booking_changes`
  ADD CONSTRAINT `booking_changes_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_changes_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_travelers`
--
ALTER TABLE `booking_travelers`
  ADD CONSTRAINT `booking_travelers_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tours`
--
ALTER TABLE `tours`
  ADD CONSTRAINT `tours_objective_id_foreign` FOREIGN KEY (`objective_id`) REFERENCES `objectives` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tour_days`
--
ALTER TABLE `tour_days`
  ADD CONSTRAINT `tour_days_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tour_departures`
--
ALTER TABLE `tour_departures`
  ADD CONSTRAINT `tour_departures_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
