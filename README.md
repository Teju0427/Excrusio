# Excursio

**Stay somewhere special. Experience something extraordinary.**

Excursio is a full-stack booking platform for **stays**, **restaurant reservations** and **experiences** (skiing, trekking, diving, workshops) — built with Laravel 13, MySQL, Blade, Bootstrap 5 and vanilla JavaScript.

> Portfolio project. All listings are synthetic demo data and payments are simulated — no real money is taken and no card details are collected.

## Live demo

**🔗 Coming soon** — the public link will be added here after the first deployment to Render.

<!-- After deploying, replace the line above with:
**🔗 [excursio.onrender.com](https://excursio.onrender.com)** — hosted on Render's free tier, so the first visit after 15 idle minutes takes about a minute to wake up.
-->

Use the one-click **Continue as demo Guest / Host / Admin** buttons on the sign-in page — no sign-up needed.

## Tech stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.3+, Laravel 13 (MVC, Eloquent, Form Requests, Policies) |
| Database | MySQL 8 |
| Frontend | Blade, Bootstrap 5.3, Bootstrap Icons, vanilla JS, Chart.js |
| Maps / QR | Leaflet + OpenStreetMap, qrcode-generator, html5-qrcode |
| Build | **None** — front-end libraries are vendored in `public/vendor/` |
| Deploy | Docker → Render (free) + Aiven MySQL (free), or Azure App Service |

## Run it locally with Docker (recommended on Windows)

You only need **Docker Desktop** running. PHP, Composer and MySQL all run inside containers.

```powershell
cd path\to\excursio
Copy-Item .env.example .env
docker compose up -d --build
docker compose logs -f app        # wait for "Excursio running on http://localhost:8000", then press Ctrl+C
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
```

Open http://localhost:8000. Stop with `docker compose down` (data is kept).

<details><summary>Without Docker (Laragon / native PHP)</summary>

Requires PHP 8.3+, Composer and MySQL 8. Create databases `excursio` and `excursio_test`, then:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
</details>

### Demo accounts

All use the password set in `DEMO_PASSWORD` in your `.env`.

| Role | Email |
|---|---|
| Admin | admin@excursio.test |
| Host (Swiss Alps, Tuscany, Udaipur) | host.alpine@excursio.test |
| Host (Kyoto, Coorg, Manali) | host.kansai@excursio.test |
| Host (Bali, Iceland, Goa) | host.island@excursio.test |
| Guest | guest@excursio.test |

### Resetting the demo

Visitors share the demo accounts, so they can change things. To put everything back:

```bash
docker compose exec app php artisan excursio:demo-reset
```

On a deployed public demo, set `EXCURSIO_DEMO_RESET=true` (and optionally `DEMO_RESET_ON_BOOT=true` to reset on every restart). The command refuses to run in production without that opt-in.

### Reviews

Only guests who were **checked in** by the host (QR code or reference) can review, within 60 days of the visit. Every review is moderated before it appears, ratings are calculated from approved reviews only, and hosts can reply. Reviews on the seeded demo listings are sample data and are labelled as such on the site.

### Security

- Content-Security-Policy with no inline JavaScript, `X-Frame-Options: DENY`, `nosniff`, strict referrer and permissions policies, HSTS in production
- CSRF on every form, rate-limited login/sign-up/booking/contact, session regeneration on login
- Every booking, listing and review action is authorised server-side (others' records return 404), prices are always recalculated on the server, and all staff actions are audit-logged
- `composer audit`: no known vulnerable packages

### Photos

Listing photos are hand-picked from Wikimedia Commons (free licences) and stored in `public/images/listings/`, with author, licence and source for every file in `credits.json`. They are also listed on the site's **Image credits** page.

## Tests

```bash
php artisan test
```

Tests run against the `excursio_test` MySQL database (see `phpunit.xml`).

**Double-booking check with real parallel requests** (20 processes race for the last room):

```bash
docker compose exec app php scripts/concurrency-check.php
```

## Project docs

- [Architecture, booking rules and roadmap](docs/ARCHITECTURE.md)
- [Entity-relationship diagram](docs/er-diagram.mermaid)

## Licence

Code: MIT. Vendored libraries: see `public/vendor/LICENSES.md`. Photos: see `public/images/listings/credits.json`.
