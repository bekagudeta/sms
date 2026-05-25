# Deploy SMS Dashboard on Vercel

This project uses [vercel-php](https://github.com/vercel-community/php) with Laravel. All HTTP traffic goes through `api/index.php`; Vite assets are served from `public/build`.

## Prerequisites

- A [Vercel](https://vercel.com) account
- A **hosted database** (SQLite does not work on Vercel). Use MySQL or PostgreSQL (e.g. [PlanetScale](https://planetscale.com), [Neon](https://neon.tech), [Supabase](https://supabase.com), or Railway).
- Node.js 22.x (Vercel sets this automatically for vercel-php)

## 1. Push to GitHub

Connect the repository to Vercel (Import Project → select repo).

## 2. Vercel project settings

| Setting | Value |
|--------|--------|
| Framework Preset | **Other** |
| Root Directory | `.` (repository root) |
| Build Command | *(leave empty — vercel-php runs `composer install` and `composer run vercel`)* |
| Output Directory | *(leave empty)* |
| Install Command | *(leave empty)* |

## 3. Required environment variables

Set these in **Vercel → Project → Settings → Environment Variables** (Production, Preview, and Development):

| Variable | Example / notes |
|----------|-----------------|
| `APP_KEY` | Run `php artisan key:generate --show` locally and paste the value |
| `APP_URL` | `https://your-project.vercel.app` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_CONNECTION` | `mysql` or `pgsql` |
| `DB_HOST` | Your database host |
| `DB_PORT` | `3306` or `5432` |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database user |
| `DB_PASSWORD` | Database password |
| `SESSION_DRIVER` | `cookie` (recommended on Vercel) |
| `CACHE_STORE` | `array` or `database` if DB is configured |
| `LOG_CHANNEL` | `stderr` |

Optional (already set in `vercel.json` for serverless caches):

- `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`, `VIEW_COMPILED_PATH`, etc. → `/tmp/...`

## 4. Database migrations

Run migrations against your **production** database once (not on every deploy):

```bash
# Locally, with production DB URL in .env
php artisan migrate --force
```

Or use a one-off command from your machine with `DATABASE_URL` pointing at the hosted DB.

## 5. Deploy

**From Git:** push to `main` — Vercel redeploys automatically.

**From CLI:**

```bash
npm i -g vercel
vercel login
vercel --prod
```

## 6. Troubleshooting

| Issue | Fix |
|-------|-----|
| Build fails after `composer.json` changes | Redeploy with **Clear build cache** |
| `public/build` 404 | Ensure `composer run vercel` runs (`npm run build`); check routes in `vercel.json` |
| 500 / blank page | Set `APP_KEY` and database env vars in Vercel |
| Composer lock warning | Run `composer update --lock` locally and commit `composer.lock` |
| Login/session issues | Use `SESSION_DRIVER=cookie` and `SESSION_SECURE_COOKIE=true` on HTTPS |

## Files involved

- `api/index.php` — serverless entrypoint
- `vercel.json` — runtime, routes, default env
- `composer.json` — `vercel` and `vercel-build` scripts (npm build)
- `.vercelignore` — excludes `vendor` and `node_modules` (installed at build time)
