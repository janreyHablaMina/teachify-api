# Teachify API

Laravel backend API for Teachify.

## Docker Quick Start
1. Create Docker env file:
```bash
cp .env.docker.example .env.docker
```
PowerShell alternative:
```powershell
Copy-Item .env.docker.example .env.docker
```
2. Set your real values in `.env.docker` (especially `AI_API_KEY`).
3. Build and run API + PostgreSQL:
```bash
docker compose up --build -d
```
4. Generate app key inside container (first run only):
```bash
docker compose exec teachify-api php artisan key:generate
```
5. Re-run migrations (optional, if needed):
```bash
docker compose exec teachify-api php artisan migrate --force
```

API should be available at `http://localhost:18000`.

Stop services:
```bash
docker compose down
```

## Useful Commands
```bash
docker compose logs -f teachify-api
docker compose exec teachify-api php artisan test
docker compose exec teachify-api php artisan tinker
```

## Notes
- The compose setup includes a local PostgreSQL service named `postgres`.
- Database data persists in Docker volume `teachify_pg_data`.
- For web container integration later, point frontend backend URL to `http://teachify-api:8000` inside shared compose network.
