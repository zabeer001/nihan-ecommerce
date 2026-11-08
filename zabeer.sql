-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql
-- Generation Time: Jul 08, 2026 at 11:49 AM
-- Server version: 8.4.9
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `zabeer`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `mobile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bin_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_currency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `footer_text` text COLLATE utf8mb4_unicode_ci,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_branch` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `swift_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `delivery_address` text COLLATE utf8mb4_unicode_ci,
  `mobile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bin_vat_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `address`, `delivery_address`, `mobile`, `email`, `bin_vat_number`, `contact_person`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'ABC Garments Ltd', 'House 12, Road 8, Dhaka', 'ABC Garments Factory, Gazipur', '+8801700000000', 'accounts@abc-garments.test', 'BIN-ABC-1001', 'Mr. Rahman', 'Demo customer for order history.', '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 'Duncan Duffy', 'Minus cupiditate sed', 'Aut odio autem quaer', 'Consequatur pariatur', 'pesu@mailinator.com', '310', 'Est sed suscipit inc', NULL, '2026-07-08 09:49:42', '2026-07-08 09:49:42');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_challans`
--

CREATE TABLE `delivery_challans` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `challan_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `challan_date` date DEFAULT NULL,
  `delivery_qty` decimal(15,2) DEFAULT '0.00',
  `delivery_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_person_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_info` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_weight` decimal(15,2) DEFAULT NULL,
  `net_weight` decimal(15,2) DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_challans`
--

INSERT INTO `delivery_challans` (`id`, `customer_id`, `order_id`, `challan_number`, `challan_date`, `delivery_qty`, `delivery_unit`, `delivery_person_name`, `package_info`, `gross_weight`, `net_weight`, `remarks`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'CH-001', '2026-07-10', 3000.00, NULL, 'Delivery Team', '3000 pcs', NULL, NULL, NULL, 'delivered', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 1, 1, 'CH-002', '2026-07-15', 4000.00, NULL, 'Delivery Team', '4000 pcs', NULL, NULL, NULL, 'delivered', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(3, 1, 1, 'CH-003', '2026-07-20', 3000.00, NULL, 'Delivery Team', '3000 pcs', NULL, NULL, NULL, 'delivered', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(4, 1, 2, 'CH-004', '2026-07-22', 5000.00, NULL, 'Delivery Team', '5000 pcs', NULL, NULL, NULL, 'delivered', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'invoice',
  `invoice_date` date DEFAULT NULL,
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'BDT',
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_rate` decimal(15,6) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT '0.00',
  `total_amount` decimal(15,2) DEFAULT '0.00',
  `payable_amount` decimal(15,2) DEFAULT '0.00',
  `amount_in_words` text COLLATE utf8mb4_unicode_ci,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `terms_text` text COLLATE utf8mb4_unicode_ci,
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'unpaid',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `customer_id`, `invoice_number`, `document_type`, `invoice_date`, `currency`, `payment_type`, `conversion_rate`, `subtotal`, `total_amount`, `payable_amount`, `amount_in_words`, `remarks`, `terms_text`, `payment_status`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'INV-001', 'invoice', '2026-07-21', 'USD', 'TT', NULL, 7000.00, 7000.00, 7000.00, 'Seven thousand dollars only', NULL, NULL, 'paid', 'issued', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` bigint UNSIGNED NOT NULL,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `order_item_id` bigint UNSIGNED DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `size_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(15,2) DEFAULT NULL,
  `unit_price` decimal(15,2) DEFAULT '0.00',
  `line_total` decimal(15,2) DEFAULT '0.00',
  `pp_qty` decimal(15,2) DEFAULT NULL,
  `yarn_qty` decimal(15,2) DEFAULT NULL,
  `material_amount` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `order_item_id`, `description`, `size_type`, `quantity`, `unit_price`, `line_total`, `pp_qty`, `yarn_qty`, `material_amount`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Nylon zipper', 'Standard', 10000.00, 0.50, 5000.00, NULL, NULL, NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 1, 2, 'Metal slider', 'Small', 5000.00, 0.20, 1000.00, NULL, NULL, NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(3, 1, 3, 'Locket', 'Regular', 2000.00, 0.10, 200.00, NULL, NULL, NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(4, 1, 4, 'Locket', 'Regular', 8000.00, 0.10, 800.00, NULL, NULL, NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_order`
--

CREATE TABLE `invoice_order` (
  `id` bigint UNSIGNED NOT NULL,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_02_25_000001_add_role_to_users_table', 1),
(5, '2026_07_05_110000_add_status_to_users_table', 1),
(6, '2026_07_05_110001_create_customers_table', 1),
(7, '2026_07_05_110003_create_orders_table', 1),
(8, '2026_07_05_110004_create_order_items_table', 1),
(9, '2026_07_05_110005_create_proforma_invoices_table', 1),
(10, '2026_07_05_110006_create_proforma_invoice_items_table', 1),
(11, '2026_07_05_110007_create_invoices_table', 1),
(12, '2026_07_05_110009_create_invoice_items_table', 1),
(13, '2026_07_05_110010_create_delivery_challans_table', 1),
(14, '2026_07_05_110012_create_payments_table', 1),
(15, '2026_07_05_110013_create_company_settings_table', 1),
(16, '2026_07_06_000001_create_invoice_order_table', 1),
(17, '2026_07_06_000002_add_payment_totals_to_proforma_invoices_table', 1),
(18, '2026_07_07_000001_create_suppliers_table', 1),
(19, '2026_07_07_000002_create_raw_materials_table', 1),
(20, '2026_07_07_000003_create_raw_material_purchases_table', 1),
(21, '2026_07_07_000005_create_raw_material_purchase_items_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `invoice_id` bigint UNSIGNED DEFAULT NULL,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_order_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `po_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_order_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'BDT',
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordered_qty` decimal(15,2) DEFAULT NULL,
  `pp_qty` decimal(15,2) DEFAULT NULL,
  `yarn_qty` decimal(15,2) DEFAULT NULL,
  `material_amount` decimal(15,2) DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `delivery_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `invoice_id`, `order_number`, `production_order_number`, `order_date`, `delivery_date`, `po_number`, `work_order_number`, `reference_number`, `file_reference`, `currency`, `payment_type`, `ordered_qty`, `pp_qty`, `yarn_qty`, `material_amount`, `status`, `delivery_status`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'ORD-101', 'PO-101', '2026-07-05', '2026-07-25', 'BUYER-PO-101', NULL, NULL, NULL, 'USD', 'TT', NULL, NULL, NULL, NULL, 'processing', 'delivered', 'Demo order with full operational history.', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 1, 1, 'ORD-102', 'PO-102', '2026-07-08', '2026-07-28', 'BUYER-PO-102', NULL, NULL, NULL, 'USD', 'TT', NULL, NULL, NULL, NULL, 'processing', 'partial', 'Second demo order linked to the same invoice.', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(3, 2, NULL, '154', '559', '2022-10-07', '2006-12-15', '765', '729', '779', 'Nam error ducimus q', 'BDT', 'Voluptas est ex con', 228.00, 571.00, 187.00, 89.00, 'cancelled', 'pending', 'Consectetur adipisi', 2, '2026-07-08 09:49:42', '2026-07-08 09:49:42');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `size_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(15,2) DEFAULT NULL,
  `unit_price` decimal(15,2) DEFAULT '0.00',
  `line_total` decimal(15,2) DEFAULT '0.00',
  `pp_qty` decimal(15,2) DEFAULT NULL,
  `yarn_qty` decimal(15,2) DEFAULT NULL,
  `material_amount` decimal(15,2) DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_name`, `unit`, `description`, `size_type`, `quantity`, `unit_price`, `line_total`, `pp_qty`, `yarn_qty`, `material_amount`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 'Nylon Zipper', NULL, 'Nylon zipper', 'Standard', 10000.00, 0.50, 5000.00, NULL, NULL, NULL, NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 1, 'Metal Slider', NULL, 'Metal slider', 'Small', 5000.00, 0.20, 1000.00, NULL, NULL, NULL, NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(3, 1, 'Locket', NULL, 'Locket', 'Regular', 2000.00, 0.10, 200.00, NULL, NULL, NULL, NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(4, 2, 'Locket', NULL, 'Locket', 'Regular', 8000.00, 0.10, 800.00, NULL, NULL, NULL, NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint UNSIGNED NOT NULL,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `payment_date` date DEFAULT NULL,
  `paid_amount` decimal(15,2) DEFAULT '0.00',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_note` text COLLATE utf8mb4_unicode_ci,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `invoice_id`, `payment_date`, `paid_amount`, `payment_method`, `payment_note`, `reference_number`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-07-22', 3000.00, 'bank_transfer', 'First partial payment.', 'PAY-001', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 1, '2026-07-24', 2000.00, 'bank_transfer', 'Second partial payment.', 'PAY-002', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(3, 1, '2026-07-26', 2000.00, 'bank_transfer', 'Final payment.', 'PAY-003', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `proforma_invoices`
--

CREATE TABLE `proforma_invoices` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `pi_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pi_date` date DEFAULT NULL,
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'BDT',
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_rate` decimal(15,6) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT '0.00',
  `bank_document_charge` decimal(15,2) DEFAULT '0.00',
  `freight_courier_charge` decimal(15,2) DEFAULT '0.00',
  `grand_total` decimal(15,2) DEFAULT '0.00',
  `paid_total` decimal(15,2) DEFAULT '0.00',
  `due_total` decimal(15,2) DEFAULT '0.00',
  `amount_in_words` text COLLATE utf8mb4_unicode_ci,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `terms_text` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `proforma_invoices`
--

INSERT INTO `proforma_invoices` (`id`, `order_id`, `customer_id`, `pi_number`, `pi_date`, `currency`, `payment_type`, `conversion_rate`, `subtotal`, `bank_document_charge`, `freight_courier_charge`, `grand_total`, `paid_total`, `due_total`, `amount_in_words`, `remarks`, `terms_text`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'PI-001', '2026-07-06', 'USD', 'TT', NULL, 6200.00, 0.00, 0.00, 6200.00, 0.00, 0.00, 'Six thousand two hundred dollars only', NULL, NULL, 'approved', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 2, 1, 'PI-002', '2026-07-09', 'USD', 'TT', NULL, 800.00, 0.00, 0.00, 800.00, 0.00, 0.00, 'Eight hundred dollars only', NULL, NULL, 'approved', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `proforma_invoice_items`
--

CREATE TABLE `proforma_invoice_items` (
  `id` bigint UNSIGNED NOT NULL,
  `proforma_invoice_id` bigint UNSIGNED NOT NULL,
  `order_item_id` bigint UNSIGNED DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `size_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(15,2) DEFAULT NULL,
  `unit_price` decimal(15,2) DEFAULT '0.00',
  `line_total` decimal(15,2) DEFAULT '0.00',
  `pp_qty` decimal(15,2) DEFAULT NULL,
  `yarn_qty` decimal(15,2) DEFAULT NULL,
  `material_amount` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `raw_materials`
--

CREATE TABLE `raw_materials` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `raw_materials`
--

INSERT INTO `raw_materials` (`id`, `name`, `description`, `image`, `created_at`, `updated_at`) VALUES
(1, 'Nylon Tape', 'Nylon zipper tape raw material.', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 'Metal Wire', 'Metal wire used for sliders and pullers.', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(3, 'Zinc Alloy', 'Zinc alloy for lockets and accessories.', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(4, 'Poly Bag', 'Packaging poly bag for finished goods.', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `raw_material_purchases`
--

CREATE TABLE `raw_material_purchases` (
  `id` bigint UNSIGNED NOT NULL,
  `supplier_id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED DEFAULT NULL,
  `purchase_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'local_purchase',
  `payment_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'cash',
  `lc_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'BDT',
  `exchange_rate` decimal(15,4) DEFAULT '1.0000',
  `subtotal` decimal(15,2) DEFAULT '0.00',
  `tax_amount` decimal(15,2) DEFAULT '0.00',
  `shipping_cost` decimal(15,2) DEFAULT '0.00',
  `customs_cost` decimal(15,2) DEFAULT '0.00',
  `other_cost` decimal(15,2) DEFAULT '0.00',
  `grand_total` decimal(15,2) DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `lc_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lc_bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lc_opening_date` date DEFAULT NULL,
  `lc_value` decimal(15,2) DEFAULT NULL,
  `lc_currency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lc_margin_amount` decimal(15,2) DEFAULT '0.00',
  `lc_charges` decimal(15,2) DEFAULT '0.00',
  `shipment_date` date DEFAULT NULL,
  `expected_arrival_date` date DEFAULT NULL,
  `import_document_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `last_shipment_date` date DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `raw_material_purchases`
--

INSERT INTO `raw_material_purchases` (`id`, `supplier_id`, `order_id`, `purchase_number`, `source_type`, `payment_mode`, `lc_type`, `purchase_date`, `currency`, `exchange_rate`, `subtotal`, `tax_amount`, `shipping_cost`, `customs_cost`, `other_cost`, `grand_total`, `status`, `remarks`, `lc_number`, `lc_bank_name`, `lc_opening_date`, `lc_value`, `lc_currency`, `lc_margin_amount`, `lc_charges`, `shipment_date`, `expected_arrival_date`, `import_document_number`, `expiry_date`, `last_shipment_date`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'RMP-LOCAL-001', 'local_purchase', 'cash', NULL, '2026-07-07', 'BDT', 1.0000, 125000.00, 0.00, 2500.00, 0.00, 500.00, 128000.00, 'received', 'Local demo purchase for ORD-101.', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 2, 2, 'RMP-IMPORT-001', 'import', 'lc', 'own_lc', '2026-07-09', 'USD', 120.0000, 2800.00, 0.00, 350.00, 420.00, 80.00, 3650.00, 'received', 'Import demo purchase for ORD-102.', 'LC-IMP-001', 'Demo Bank Ltd', '2026-07-10', 3650.00, 'USD', 500.00, 75.00, '2026-07-12', '2026-07-18', 'IMP-DOC-001', '2026-08-10', '2026-07-20', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `raw_material_purchase_items`
--

CREATE TABLE `raw_material_purchase_items` (
  `id` bigint UNSIGNED NOT NULL,
  `raw_material_purchase_id` bigint UNSIGNED NOT NULL,
  `raw_material_id` bigint UNSIGNED NOT NULL,
  `received_quantity` decimal(15,2) DEFAULT '0.00',
  `description` text COLLATE utf8mb4_unicode_ci,
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(15,2) DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `raw_material_purchase_items`
--

INSERT INTO `raw_material_purchase_items` (`id`, `raw_material_purchase_id`, `raw_material_id`, `received_quantity`, `description`, `unit`, `quantity`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 450.00, 'Tape for nylon zipper production.', 'kg', 450.00, 'Seeded demo raw material purchase item.', '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 1, 4, 12000.00, 'Packaging for finished goods.', 'pcs', 12000.00, 'Seeded demo raw material purchase item.', '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(3, 2, 2, 900.00, 'Imported metal wire for slider production.', 'kg', 900.00, 'Seeded demo raw material purchase item.', '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(4, 2, 3, 1.50, 'Imported zinc alloy for lockets.', 'tons', 1.50, 'Seeded demo raw material purchase item.', '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint UNSIGNED NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'local',
  `address` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `company_name`, `contact_person`, `mobile`, `email`, `country`, `supplier_type`, `address`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Dhaka Trims Supply', 'Mr. Kamal', '+8801711111111', 'sales@dhaka-trims.test', 'Bangladesh', 'local', 'Tejgaon Industrial Area, Dhaka', 'Local supplier for demo raw material purchases.', 1, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(2, 'Global Zipper Materials Co.', 'Ms. Chen', '+8613800000000', 'export@global-zipper.test', 'China', 'import', 'Guangzhou, China', 'Import supplier for LC demo purchase.', 1, '2026-07-08 09:46:49', '2026-07-08 09:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `status`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Test User', 'test@example.com', 'user', 'active', NULL, '$2y$12$t8VT/IUVPy4YItaBMUgFsu.UsMd36XwVnSxZyt4NNi28cPqpkaNA6', NULL, '2026-07-08 09:46:48', '2026-07-08 09:46:48'),
(2, 'Admin User', 'binzabirtareq@gmail.com', 'admin', 'active', NULL, '$2y$12$OC/3fa0ZTs/0RIgsYrYVm.XUxKlqqrTzUtLfP5CW/2FjAb5QFohXS', NULL, '2026-07-08 09:46:49', '2026-07-08 09:46:49'),
(3, 'Zabeer01', 'zabeer01@gmail.com', 'user', 'active', NULL, '$2y$12$yQln4qmvjrkk/fnhkGyN5uX65CtgtTC2dJY9LCqfNgFH5ipC15zke', NULL, '2026-07-08 11:11:17', '2026-07-08 11:13:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_settings_company_name_index` (`company_name`),
  ADD KEY `company_settings_mobile_index` (`mobile`),
  ADD KEY `company_settings_email_index` (`email`),
  ADD KEY `company_settings_bin_number_index` (`bin_number`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customers_name_index` (`name`),
  ADD KEY `customers_mobile_index` (`mobile`),
  ADD KEY `customers_email_index` (`email`),
  ADD KEY `customers_bin_vat_number_index` (`bin_vat_number`),
  ADD KEY `customers_contact_person_index` (`contact_person`);

--
-- Indexes for table `delivery_challans`
--
ALTER TABLE `delivery_challans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `delivery_challans_challan_number_unique` (`challan_number`),
  ADD KEY `delivery_challans_created_by_foreign` (`created_by`),
  ADD KEY `delivery_challans_customer_id_challan_date_index` (`customer_id`,`challan_date`),
  ADD KEY `delivery_challans_order_id_challan_date_index` (`order_id`,`challan_date`),
  ADD KEY `delivery_challans_status_challan_date_index` (`status`,`challan_date`),
  ADD KEY `delivery_challans_challan_date_index` (`challan_date`),
  ADD KEY `delivery_challans_delivery_unit_index` (`delivery_unit`),
  ADD KEY `delivery_challans_delivery_person_name_index` (`delivery_person_name`),
  ADD KEY `delivery_challans_package_info_index` (`package_info`),
  ADD KEY `delivery_challans_status_index` (`status`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  ADD KEY `invoices_created_by_foreign` (`created_by`),
  ADD KEY `invoices_customer_id_status_index` (`customer_id`,`status`),
  ADD KEY `invoices_customer_id_invoice_date_index` (`customer_id`,`invoice_date`),
  ADD KEY `invoices_payment_status_invoice_date_index` (`payment_status`,`invoice_date`),
  ADD KEY `invoices_document_type_index` (`document_type`),
  ADD KEY `invoices_invoice_date_index` (`invoice_date`),
  ADD KEY `invoices_payment_type_index` (`payment_type`),
  ADD KEY `invoices_payment_status_index` (`payment_status`),
  ADD KEY `invoices_status_index` (`status`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_items_order_item_id_foreign` (`order_item_id`),
  ADD KEY `invoice_items_invoice_id_order_item_id_index` (`invoice_id`,`order_item_id`),
  ADD KEY `invoice_items_size_type_index` (`size_type`);

--
-- Indexes for table `invoice_order`
--
ALTER TABLE `invoice_order`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_order_invoice_id_order_id_unique` (`invoice_id`,`order_id`),
  ADD KEY `invoice_order_order_id_invoice_id_index` (`order_id`,`invoice_id`);

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
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_order_number_unique` (`order_number`),
  ADD UNIQUE KEY `orders_production_order_number_unique` (`production_order_number`),
  ADD KEY `orders_created_by_foreign` (`created_by`),
  ADD KEY `orders_customer_id_status_index` (`customer_id`,`status`),
  ADD KEY `orders_customer_id_order_date_index` (`customer_id`,`order_date`),
  ADD KEY `orders_invoice_id_delivery_status_index` (`invoice_id`,`delivery_status`),
  ADD KEY `orders_invoice_id_index` (`invoice_id`),
  ADD KEY `orders_order_date_index` (`order_date`),
  ADD KEY `orders_delivery_date_index` (`delivery_date`),
  ADD KEY `orders_po_number_index` (`po_number`),
  ADD KEY `orders_work_order_number_index` (`work_order_number`),
  ADD KEY `orders_reference_number_index` (`reference_number`),
  ADD KEY `orders_file_reference_index` (`file_reference`),
  ADD KEY `orders_payment_type_index` (`payment_type`),
  ADD KEY `orders_status_index` (`status`),
  ADD KEY `orders_delivery_status_index` (`delivery_status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_order_id_item_name_index` (`order_id`,`item_name`),
  ADD KEY `order_items_item_name_index` (`item_name`),
  ADD KEY `order_items_unit_index` (`unit`),
  ADD KEY `order_items_size_type_index` (`size_type`);

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
  ADD KEY `payments_created_by_foreign` (`created_by`),
  ADD KEY `payments_invoice_id_payment_date_index` (`invoice_id`,`payment_date`),
  ADD KEY `payments_payment_date_index` (`payment_date`),
  ADD KEY `payments_payment_method_index` (`payment_method`),
  ADD KEY `payments_reference_number_index` (`reference_number`);

--
-- Indexes for table `proforma_invoices`
--
ALTER TABLE `proforma_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proforma_invoices_order_id_unique` (`order_id`),
  ADD UNIQUE KEY `proforma_invoices_pi_number_unique` (`pi_number`),
  ADD KEY `proforma_invoices_created_by_foreign` (`created_by`),
  ADD KEY `proforma_invoices_customer_id_status_index` (`customer_id`,`status`),
  ADD KEY `proforma_invoices_customer_id_pi_date_index` (`customer_id`,`pi_date`),
  ADD KEY `proforma_invoices_pi_date_index` (`pi_date`),
  ADD KEY `proforma_invoices_payment_type_index` (`payment_type`),
  ADD KEY `proforma_invoices_status_index` (`status`);

--
-- Indexes for table `proforma_invoice_items`
--
ALTER TABLE `proforma_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proforma_invoice_items_order_item_id_foreign` (`order_item_id`),
  ADD KEY `pi_items_invoice_order_item_index` (`proforma_invoice_id`,`order_item_id`),
  ADD KEY `proforma_invoice_items_size_type_index` (`size_type`);

--
-- Indexes for table `raw_materials`
--
ALTER TABLE `raw_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `raw_materials_name_index` (`name`);

--
-- Indexes for table `raw_material_purchases`
--
ALTER TABLE `raw_material_purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `raw_material_purchases_purchase_number_unique` (`purchase_number`),
  ADD KEY `raw_material_purchases_created_by_foreign` (`created_by`),
  ADD KEY `raw_material_purchases_supplier_id_purchase_date_index` (`supplier_id`,`purchase_date`),
  ADD KEY `raw_material_purchases_order_id_purchase_date_index` (`order_id`,`purchase_date`),
  ADD KEY `raw_material_purchases_source_type_status_index` (`source_type`,`status`),
  ADD KEY `raw_material_purchases_payment_mode_status_index` (`payment_mode`,`status`),
  ADD KEY `raw_material_purchases_source_type_index` (`source_type`),
  ADD KEY `raw_material_purchases_payment_mode_index` (`payment_mode`),
  ADD KEY `raw_material_purchases_lc_type_index` (`lc_type`),
  ADD KEY `raw_material_purchases_purchase_date_index` (`purchase_date`),
  ADD KEY `raw_material_purchases_status_index` (`status`),
  ADD KEY `raw_material_purchases_lc_number_index` (`lc_number`),
  ADD KEY `raw_material_purchases_import_document_number_index` (`import_document_number`);

--
-- Indexes for table `raw_material_purchase_items`
--
ALTER TABLE `raw_material_purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `raw_material_purchase_items_raw_material_id_foreign` (`raw_material_id`),
  ADD KEY `rm_purchase_item_material_idx` (`raw_material_purchase_id`,`raw_material_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `suppliers_company_name_index` (`company_name`),
  ADD KEY `suppliers_country_index` (`country`),
  ADD KEY `suppliers_supplier_type_index` (`supplier_type`),
  ADD KEY `suppliers_is_active_index` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_role_index` (`role`),
  ADD KEY `users_status_index` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `delivery_challans`
--
ALTER TABLE `delivery_challans`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `invoice_order`
--
ALTER TABLE `invoice_order`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `proforma_invoices`
--
ALTER TABLE `proforma_invoices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `proforma_invoice_items`
--
ALTER TABLE `proforma_invoice_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `raw_materials`
--
ALTER TABLE `raw_materials`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `raw_material_purchases`
--
ALTER TABLE `raw_material_purchases`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `raw_material_purchase_items`
--
ALTER TABLE `raw_material_purchase_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `delivery_challans`
--
ALTER TABLE `delivery_challans`
  ADD CONSTRAINT `delivery_challans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `delivery_challans_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_challans_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_items_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_order`
--
ALTER TABLE `invoice_order`
  ADD CONSTRAINT `invoice_order_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_order_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proforma_invoices`
--
ALTER TABLE `proforma_invoices`
  ADD CONSTRAINT `proforma_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proforma_invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proforma_invoices_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proforma_invoice_items`
--
ALTER TABLE `proforma_invoice_items`
  ADD CONSTRAINT `proforma_invoice_items_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proforma_invoice_items_proforma_invoice_id_foreign` FOREIGN KEY (`proforma_invoice_id`) REFERENCES `proforma_invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `raw_material_purchases`
--
ALTER TABLE `raw_material_purchases`
  ADD CONSTRAINT `raw_material_purchases_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `raw_material_purchases_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `raw_material_purchases_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `raw_material_purchase_items`
--
ALTER TABLE `raw_material_purchase_items`
  ADD CONSTRAINT `raw_material_purchase_items_raw_material_id_foreign` FOREIGN KEY (`raw_material_id`) REFERENCES `raw_materials` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `raw_material_purchase_items_raw_material_purchase_id_foreign` FOREIGN KEY (`raw_material_purchase_id`) REFERENCES `raw_material_purchases` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
