# Run with Docker

This repository includes a Docker setup for PHP-Apache and MySQL.

## Prerequisites
- Docker and Docker Compose

## Quick start
1. Copy env sample (optional; compose already has defaults):
   
   cp .env.example .env

   You can customize JWT_SECRET and DB credentials if needed.

2. Build and start the stack:

   docker compose up -d --build

3. Access the API at:
   - http://localhost:8080/index.php?request=get-category

The MySQL container initializes with `pro1014_schema.sql` automatically.

## Services
- app: PHP 8.2 + Apache, exposes 8080
- db: MySQL 8.0, exposes 3307 (host) -> 3306 (container)

## Environment variables
- DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD used by PHP app (see config/Database.php)
- MYSQL_ROOT_PASSWORD used only by the MySQL container
- JWT_SECRET used for token signing

## Useful commands
- View logs:

  docker compose logs -f app
  docker compose logs -f db

- Stop and remove:

  docker compose down -v

- Recreate database (drops data):

  docker compose down -v && docker compose up -d --build
