-- Runs once, the first time the MySQL volume is created.
-- A separate database for automated tests, so `php artisan test` never wipes your dev data.
CREATE DATABASE IF NOT EXISTS stockpulse_testing;
