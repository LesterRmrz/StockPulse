# StockPulse

> A full-stack enterprise e-commerce dashboard and inventory manager built with React, TypeScript, Tailwind CSS, and Laravel. Features real-time stock analytics, relational database architecture, and containerized deployment with Docker.

See [docs/PLAN.md](docs/PLAN.md) for the roadmap and [docs/DATABASE.md](docs/DATABASE.md) for the schema.

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
(Optional: install Node and run `npm ci` in `frontend/` so your editor can find the TypeScript types. The app
itself uses the copy inside the container.)

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

# 5. Create the database tables and load the demo data (about a minute)
docker compose exec php php artisan migrate:fresh --seed
```

Then open http://localhost:5173 and sign in with `admin@stockpulse.test` / `password`, or click a demo button.
The seeded accounts are listed in [docs/M1-WALKTHROUGH.md](docs/M1-WALKTHROUGH.md#1-running-it).

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
docker compose exec php php artisan migrate:fresh --seed  # rebuild the database with fresh demo data
docker compose exec php php artisan test           # backend tests (uses the stockpulse_testing database)
docker compose exec frontend npm run lint          # frontend lint
docker compose exec frontend npm run build         # frontend type-check + production build
docker compose exec frontend npm install <package> # add a frontend package
```

> The test database `stockpulse_testing` is created automatically on a fresh MySQL volume. If your volume
> already existed before M1, create it once:
> `docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS stockpulse_testing"`

> `docker compose down -v` also deletes the database volume — use it only for a fresh start.

## Project structure

```
my-project/
├── backend/             # Laravel API
├── frontend/            # React + TypeScript (Vite)
├── docker/php/          # Dockerfile for the PHP container
├── docker/mysql/        # Creates the test database on first start
├── docs/                # Plan, database reference, milestone walkthroughs
└── docker-compose.yml   # Defines the php, mysql and frontend services
```
