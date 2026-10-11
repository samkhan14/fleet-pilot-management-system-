# FleetPilot

FleetPilot is a dispatch desk for a small carrier. Harbor & Pine Cartage is the fictional demo company. The app records customers, shipments, drivers, vehicles, manual assignments, tracking events, exceptions, proof of delivery, and charges.

It does not track live GPS and it does not optimize routes.

## Stack

Laravel, Inertia.js, React, TypeScript, MySQL, and Tailwind CSS. Domain code lives under `app/Domain`. The UI is server-rendered through Inertia. See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for status rules, permissions, and metric definitions.

ThemeForest was researched and not installed. See [docs/THEMEFOREST.md](docs/THEMEFOREST.md).

## Local setup

Requirements used in this environment: PHP 8.3, Composer, Node 22, and MySQL 8.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create two databases and a user that can use them:

```sql
CREATE DATABASE fleetpilot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE fleetpilot_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fleetpilot'@'localhost' IDENTIFIED BY 'fleetpilot';
GRANT ALL ON fleetpilot.* TO 'fleetpilot'@'localhost';
GRANT ALL ON fleetpilot_test.* TO 'fleetpilot'@'localhost';
```

```bash
php artisan migrate --force
php artisan db:seed
npm run build
php artisan serve
```

`db:seed` loads Harbor & Pine only outside the test suite. `migrate:fresh --seed` reloads that demo from scratch.

## Demo

`FLEETPILOT_DEMO=true` lists these accounts on the login screen. Password for every account: `PilotDemo!2026`

| Role             | Email                            |
| ---------------- | -------------------------------- |
| Owner            | mara.ellison@harborpine.example  |
| Operations       | jules.okonkwo@harborpine.example |
| Dispatcher       | owen.park@harborpine.example     |
| Driver           | nia.rahman@harborpine.example    |
| Warehouse        | chris.dalton@harborpine.example  |
| Customer service | priya.shah@harborpine.example    |
| Finance          | helen.cho@harborpine.example     |
| Customer         | andrea.moss@harborpine.example   |

Public tracking for the in-transit paper-roll shipment, valid for 30 days from seeding:

`/track/hbpine-demo-public-track-7f3a9c2e`

The page shows cities and customer-safe messages. It does not show the street address, the internal note, or the driver's phone.

## Environment

| Variable                | Role                                                                        |
| ----------------------- | --------------------------------------------------------------------------- |
| `FLEETPILOT_DEMO`       | Show the fictional accounts on the login screen. Leave false in production. |
| `NOTIFICATION_PROVIDER` | `log` (default) or `mail`.                                                  |
| `SMS_WEBHOOK_URL`       | Optional. Without it, SMS jobs are skipped.                                 |
| `MAIL_*`                | Used only when the provider is `mail`.                                      |

Web requests send pending notices after the response. You can also run:

```bash
php artisan fleetpilot:notifications
```

A failed send does not undo the shipment update. Nothing in this repo deploys itself.

## Tests

```bash
php artisan test
```

Tests migrate `fleetpilot_test` and do not load the demo company.
