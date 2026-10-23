-- Runs once when the MySQL container is first created.
-- The main "excursio" database and user are created by the MYSQL_* settings in docker-compose.yml.
CREATE DATABASE IF NOT EXISTS excursio_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON excursio_test.* TO 'excursio'@'%';
FLUSH PRIVILEGES;
