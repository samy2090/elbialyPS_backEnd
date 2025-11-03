# elbialyPS_backEnd

A Laravel-based backend API for Elbialy PlayStation cafe management (users, devices, products, auth).

This repository provides REST API endpoints (using Laravel Sanctum for auth) and follows a service/repository pattern used across the project. It includes migrations, seeders, factories and basic CRUD for Users, Devices and Products with role-based permissions (Admin / Staff).

## Tech stack

- PHP (Laravel framework)
- MySQL (or compatible relational DB)
- Composer for PHP dependencies
- Laravel Sanctum for API authentication

## Repository structure (important files)

- `app/Models/` - Eloquent models (User, Device, Product)
- `app/Http/Controllers/` - API controllers (AuthController, UserController, DeviceController, ProductController)
- `app/Services/` - Business logic services (AuthService, ProductService)
- `app/Repositories/` - Repository interfaces & implementations
- `database/migrations/` - DB migrations (users, devices, products)
- `database/factories/` - Model factories
- `database/seeders/` - Seeders (UserSeeder, DeviceSeeder, ProductSeeder)
- `routes/api.php` - API routes and role-based route groups

## Quick setup (Windows / PowerShell)

1. Copy the environment file and set database credentials:

```powershell
cp .env.example .env
# then open .env and set DB_DATABASE, DB_USERNAME, DB_PASSWORD, and other settings
```

2. Install PHP dependencies:

```powershell
composer install
```

3. Generate application key:

```powershell
php artisan key:generate
```

4. Run migrations and seeders (creates tables and sample data including products):

```powershell
php artisan migrate
php artisan db:seed
# or seed a specific seeder:
php artisan db:seed --class=ProductSeeder
```

5. Start local server (or use Laragon's virtual host):

```powershell
php artisan serve
# Visit http://127.0.0.1:8000
```

## Running tests

Unit and feature tests (if present) can be run with:

```powershell
./vendor/bin/phpunit
```

## API overview

All API endpoints are prefixed with `/api` and protected by Sanctum where applicable. Endpoints below assume the `auth:sanctum` middleware is used and proper role-based middleware (`admin`, `admin_or_staff`) is in place.

Authentication
- POST `/api/register` — register a new user (if enabled)
- POST `/api/login` — login and receive a Sanctum token
- POST `/api/logout` — logout (requires auth)
- GET `/api/user` — get authenticated user info

Users (role-based)
- GET `/api/users` — list users (admin & staff; staff read-only)
- GET `/api/users/{user}` — get user details
- POST `/api/users` — create user (admin only)
- PUT/PATCH `/api/users/{user}` — update user (admin only)
- DELETE `/api/users/{user}` — soft delete (admin only)
- POST `/api/users/{id}/restore` — restore (admin only)
- DELETE `/api/users/{id}/force` — permanent delete (admin only)

Devices
- GET `/api/devices/available` — list available devices (all authenticated users)
- GET `/api/devices` — list devices (admin & staff)
- GET `/api/devices/{device}` — get device details
- PATCH `/api/devices/{device}/status` — update status (admin & staff)
- POST `/api/devices` — create (admin only)
- PUT/PATCH `/api/devices/{device}` — update (admin only)
- DELETE `/api/devices/{device}` — soft delete (admin only)
- POST `/api/devices/{id}/restore` — restore (admin only)
- DELETE `/api/devices/{id}/force` — permanent delete (admin only)

Products (new)
- GET `/api/products` — list products (admin & staff; staff read-only)
- GET `/api/products/{product}` — get product details
- POST `/api/products` — create product (admin only)
- PUT/PATCH `/api/products/{product}` — update product (admin only)
- DELETE `/api/products/{product}` — soft delete (admin only)
- POST `/api/products/{id}/restore` — restore soft deleted product (admin only)
- DELETE `/api/products/{id}/force` — permanently delete product (admin only)

Product fields (schema)
- `id`, `name`, `sku` (unique), `note`, `category` (enum: `drink`/`snack`),
- `price` (decimal), `cost` (decimal, nullable), `is_active` (boolean), `stock` (integer), `created_at`, `updated_at`, `deleted_at`

Example: list products (authenticated)

```powershell
# assuming you have a Bearer token in $token
curl -H "Authorization: Bearer $token" "http://127.0.0.1:8000/api/products"
```

## Developer notes

- The project uses a service/repository pattern. To add/change logic, update the service in `app/Services` and repository implementations in `app/Repositories`.
- New repository bindings are registered in `app/Providers/AppServiceProvider.php`.
- Factories and seeders live in `database/` and are useful for local development and tests.

## Quick tinker/snippets

```powershell
php artisan tinker
>>> App\\Models\\Product::count();
>>> App\\Models\\Product::first();
```

## Contributing

Open a PR with a clear description. Run tests and keep changes small and focused.

## License

This project doesn't include an explicit license file. Add one (e.g., `MIT`) if you plan to publish the code.

---

If you'd like, I can also add example Postman/Insomnia collections or expand the README with detailed request/response examples for each endpoint.
