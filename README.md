# my-project

> _One-line description of what the app does — fill this in once the idea takes shape._

<!-- Add a screenshot here once there's something to show:
![Screenshot](docs/screenshot.png)
-->

## Tech stack

| Layer      | Technology                                   |
|------------|----------------------------------------------|
| Frontend   | React 19, TypeScript, Vite, Tailwind CSS     |
| Backend    | Laravel 13 (PHP 8.4), Laravel Sanctum        |
| Database   | MySQL 8.0                                    |
| Dev setup  | Docker Compose (everything runs in containers) |

## Getting started

**Requirements:** [Docker Desktop](https://www.docker.com/products/docker-desktop/) and Git. No local PHP, Composer, Node or MySQL needed.

```bash
# 1. Clone the repository
git clone https://github.com/LesterRmrz/my-project.git
cd my-project

# 2. Create the backend environment file
cp backend/.env.example backend/.env

# 3. Build the PHP image and install backend dependencies
docker compose build
docker compose run --rm php composer install
docker compose run --rm php php artisan key:generate

# 4. Start everything
docker compose up -d

# 5. Create the database tables
docker compose exec php php artisan migrate
```

Then open:

| URL                        | What it is                          |
|----------------------------|-------------------------------------|
| http://localhost:5173      | The app (React frontend)            |
| http://localhost:8000      | Laravel backend (API)               |

The frontend forwards every `/api/...` request to Laravel through Vite's proxy, so the app only ever talks to port 5173.

## Common commands

```bash
docker compose up -d            # start
docker compose stop             # pause (keeps containers)
docker compose down             # stop and remove containers (database is kept)
docker compose logs -f php      # follow backend logs

docker compose exec php php artisan <command>      # run Laravel commands
docker compose exec frontend npm install <package> # add a frontend package
```

> `docker compose down -v` also deletes the database volume — use it only for a fresh start.

## Project structure

```
my-project/
├── backend/             # Laravel API
├── frontend/            # React + TypeScript (Vite)
├── docker/php/          # Dockerfile for the PHP container
└── docker-compose.yml   # Defines the php, mysql and frontend services
```
