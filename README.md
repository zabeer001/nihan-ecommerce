# Nihan Ecommerce Admin

A Laravel application for managing products, categories, inventory, and Cash on Delivery (COD) orders. The dashboard uses React and Inertia.js, with Tailwind CSS and DaisyUI for styling.

The current implementation focuses on the admin dashboard. The Next.js customer storefront and online payment integrations are not implemented.

## Technology

- Laravel 12 and JWT authentication
- React 18, Inertia.js 2, and Vite 7
- Tailwind CSS 4, DaisyUI 5, and React Select
- MySQL 8.4
- Laravel Sail / Docker Compose with PHP 8.5
- Pest for backend tests and Node.js tests for session refresh

## Features

### Products and categories

- Separate product creation and editing pages.
- Product information, variant rows, and image management in the same form.
- Flat categories managed through add/edit popups; no parent categories.
- Products can belong to multiple categories through the `category_product` pivot table.
- Searchable category selection with removable chips.
- One product can have multiple variants, each with a unique SKU, attributes, price, and stock balance.
- Products without extra variants receive a default variant automatically.
- Optional brand, model, color, unit, barcode, purchase price, dealer price, and corporate price fields.
- Featured images and gallery images, with primary-image selection and ordering.
- JPG, PNG, and WebP uploads, up to 5 MB per image.
- Product archiving through the API preserves variants and order history.

Brand, model, and unit are optional text fields, rather than separate management modules. Inventory currently supports one location and quantity tracking.

### Inventory and orders

- Stock adjustments with a reason, staff member, timestamp, and resulting balance.
- Add stock through Inventory after creating a product and its SKUs.
- Low-stock overview for active variants with five or fewer units.
- Staff-created COD orders with customer details, delivery address, and a manual delivery charge.
- BDT currency; prices and order totals are calculated on the server using integer minor units (`*_cents`).
- Stock is deducted transactionally when an order is created. Insufficient stock is rejected.
- A unique submission key prevents duplicate order creation on retries.
- Cancellation restores stock once.
- Product names, SKUs, attributes, and prices are snapshotted into order items.
- Printable invoices, order history, and delivered-sales totals.

Order status progression:

```text
Pending → Confirmed → Processing → Shipped → Delivered
```

Orders can be cancelled before delivery. Delivered and Cancelled are terminal states. Order items cannot be edited after creation; cancel and create a replacement order when corrections are needed.

### Authentication

- Admin APIs require an authenticated `admin` or `superadmin` account.
- Frozen accounts cannot use protected admin endpoints or refresh their session.
- The browser refreshes expired access tokens and retries the original request automatically.
- Concurrent requests share a refresh attempt; temporary network/server failures preserve the stored session.
- Default configuration: 60-minute access tokens and a 14-day refresh window. Invalid or unrefreshable tokens require signing in again.

## Local setup

Run commands from the project root. You need Docker with Compose, Composer, a compatible PHP installation for dependency installation (PHP 8.5 matches the container), and Node.js with npm. Node.js 24 is used for local development.

### 1. Environment and dependencies

For a new checkout only, create the environment file:

```bash
cp .env.example .env
```

Do not overwrite an existing `.env`. Configure the local environment before starting containers:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:97
APP_PORT=97

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=nihan_ecommerce
DB_USERNAME=sail
DB_PASSWORD=local-development-password

FORWARD_DB_PORT=3307
PHPMYADMIN_PORT=8086
WWWUSER=1000
WWWGROUP=1000

JWT_TTL=60
JWT_REFRESH_TTL=20160
```

Use your own database password. Match `WWWUSER` and `WWWGROUP` to your local user/group IDs (`id -u` and `id -g`), and choose unused host ports. `.env.example` contains different port defaults, so set `APP_PORT=97` explicitly if you want the URLs below.

```bash
composer install
npm ci
```

Composer dependencies must be installed before Compose builds the app, because its Dockerfile is under `vendor/laravel/sail`.

### 2. Start the application

```bash
docker compose up -d --build
```

Once MySQL is healthy, initialize a new installation:

```bash
docker compose exec -T laravel.test php artisan key:generate
docker compose exec -T laravel.test php artisan jwt:secret
docker compose exec -T laravel.test php artisan migrate
docker compose exec -T laravel.test php artisan storage:link
npm run build
```

Generate application and JWT keys only when initializing a new environment. Keep existing keys when restarting or updating an installation.

### 3. Create the admin account

Review `database/seeders/UserSeeder.php` and configure the intended admin account locally before running:

```bash
docker compose exec -T laravel.test php artisan db:seed --class=UserSeeder
```

The seeder uses `updateOrCreate`, so rerunning it can update the matching account's password and role. Do not publish credentials in documentation or use development credentials in production.

### 4. Open the dashboard

- Sign in: [http://localhost:97/sign-in](http://localhost:97/sign-in)
- Dashboard: [http://localhost:97/dashboard](http://localhost:97/dashboard)
- Categories: [http://localhost:97/dashboard/categories](http://localhost:97/dashboard/categories)
- Products: [http://localhost:97/dashboard/products](http://localhost:97/dashboard/products)
- Add product: [http://localhost:97/dashboard/products/create](http://localhost:97/dashboard/products/create)
- Inventory: [http://localhost:97/dashboard/inventory](http://localhost:97/dashboard/inventory)
- Orders: [http://localhost:97/dashboard/orders](http://localhost:97/dashboard/orders)
- phpMyAdmin: [http://localhost:8086](http://localhost:8086), when configured as above.

## Code structure

```text
app/Http/Controllers/Api/
├── Auth/
├── Category/
├── Dashboard/
├── Inventory/
│   └── Services/StockMovementService.php
├── Orders/
└── Product/
    ├── ProductController.php
    └── Services/
        ├── Concerns/HandlesProduct.php
        ├── ProductIndexService.php
        ├── ProductStoreService.php
        ├── ProductShowService.php
        ├── ProductUpdateService.php
        ├── ProductDeleteService.php
        └── …

resources/js/backend/pages/catalog/
├── ManagementPage.jsx
├── ProductFormPage.jsx
├── catalogUi.jsx
└── components/
```

Each module keeps its controller and services together. Controller methods contain one service delegation statement. Validation and business logic live in services; shared product persistence and validation live in `HandlesProduct`. Dependencies use Laravel's automatic constructor injection.

Routes are defined in `routes/api.php` and `routes/dashboard.php`. Models are under `app/Models`, and schema changes are recorded in `database/migrations`.

## API overview

Authenticated requests send `Authorization: Bearer <access_token>` and `Accept: application/json`. Admin endpoints use the `/api/admin` prefix.

| Module | Endpoints |
| --- | --- |
| Authentication | `POST /api/signin`, `POST /api/refresh`, `POST /api/signout`, `GET /api/profile` |
| Password recovery | `POST /api/forgot-password`, `POST /api/reset-password` |
| Dashboard | `GET /api/admin/summary` |
| Categories | `GET/POST /api/admin/categories`, `PUT/DELETE /api/admin/categories/{id}` |
| Products | `GET/POST /api/admin/products`, `GET/PUT/DELETE /api/admin/products/{product}` |
| Variants | `GET /api/admin/variants`, `POST /api/admin/products/{product}/variants`, `PUT /api/admin/products/{product}/variants/{id}` |
| Images | `POST /api/admin/products/{id}/images`, `PUT/DELETE /api/admin/images/{id}` |
| Inventory | `POST /api/admin/variants/{id}/adjust`, `GET /api/admin/variants/{id}/movements` |
| Orders | `GET/POST /api/admin/orders`, `GET /api/admin/orders/{id}`, `PATCH /api/admin/orders/{id}/status` |

Product CRUD services return the shared `ApiResponse` envelope (`status`, `message`, `data`). Some other module endpoints return models or paginators directly; the dashboard's `catalogUi` request helper handles both shapes. Validation failures return HTTP 422 with field errors.

## Development and verification

Build frontend assets after changing React or CSS files:

```bash
npm run build
```

For frontend development with the local Vite server:

```bash
npm run dev
```

Run the focused backend suite with an isolated in-memory SQLite database (PHP's SQLite extension is required):

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/CatalogTest.php tests/Feature/SessionRefreshTest.php
```

Run browser-session logic tests:

```bash
node tests/js/apiClient.test.mjs
```

These cover catalog relationships, variants, images, stock and order rules, access control, expired-token refresh, concurrent frontend refresh attempts, and session preservation. They do not replace a MySQL concurrency test or browser workflow verification. Older test files may target business modules that are no longer enabled.

Useful Docker commands:

```bash
docker compose ps
docker compose logs --tail=100 laravel.test
docker compose exec -T laravel.test php artisan migrate
docker compose exec -T laravel.test php artisan route:list
docker compose stop
```

## Troubleshooting

- **Vite manifest missing:** run `npm run build`, wait for it to finish, then reload. Loading a page during a build can briefly encounter a missing manifest.
- **Product images not visible:** run `php artisan storage:link` inside the app container and verify `APP_URL` matches the application URL.
- **Database connection refused:** wait for the MySQL health check and confirm `DB_HOST=mysql` inside Docker. Changing initialization credentials in `.env` does not reconfigure an existing MySQL volume.
- **Port already in use:** change the relevant `.env` port and run `docker compose up -d`.
- **Session already expired:** sign in again. New sessions automatically refresh within the configured refresh window.

Existing data should be updated with additive migrations. The category-pivot migration preserves existing assignments, but its rollback deliberately requires a database backup because reverting to a single category would lose relationships.

## Current scope

The customer storefront, customer self-service accounts, online payments, automatic delivery integrations, returns/refunds, multi-warehouse inventory, serialized tracking, and tax/discount calculations are outside the current implementation.
