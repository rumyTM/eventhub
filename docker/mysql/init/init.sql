-- Creates the per-service databases on first MySQL boot.
-- The primary DB (eventhub_core) is created by MYSQL_DATABASE; create the others here.
CREATE DATABASE IF NOT EXISTS eventhub_payments CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS eventhub_notifications CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
