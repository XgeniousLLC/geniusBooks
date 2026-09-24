# Laravel + Inertia Starter Scaffold

Self-hosted app scaffold built on a base Blade admin panel with a React (Inertia) customer portal. Use this as the starting point for a new project; it ships only setup, authentication, and a fresh database.

## Documentation

HTML documentation lives in [`docs/`](docs/index.html):

- [User Manual](docs/user-manual.html) — how to use every module.
- [Developer Guide](docs/developer-guide.html) — architecture, setup, extending, testing.
- [Deployment Guide](docs/deployment-guide.html) — VPS, shared hosting, AWS and DigitalOcean.


## Features

### Authentication System
- **Dual Authentication**: Separate `admin` (Blade) and `web` (portal) guards
- **Admin Login**: Custom admin authentication with middleware protection
- **Customer Portal**: Sign-up, login, forgot/reset password
- **Profile Management**: Update profile, change password for both admin and customer
- **Security**: Active user validation, secure password hashing, rate-limited portal auth

### Admin Panel
- **Dashboard**: Admin landing page
- **Page Management**: CRUD with meta information
- **SEO & Meta**: Real-time SEO analysis, Open Graph / Twitter cards, meta previews
- **Admin & User management**: CRUD plus password changes

### UI
- **Admin UI**: Laravel Blade + Alpine.js
- **Portal UI**: React 19 + TypeScript + Inertia + Tailwind CSS

## Stack

- **Backend**: Laravel 12 (PHP 8.2+), Eloquent, Form Requests
- **Admin UI**: Laravel Blade + Alpine.js
- **Portal UI**: React 19 + TypeScript + Inertia + Vite + Tailwind CSS
- **Database**: MySQL (production) / SQLite (local default)
- **Queue**: Laravel Queue (database driver) + Supervisor
- **Auth**: Session guards — `admin` guard (Blade admin) and `web` guard (portal customers)

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # AuthController, DashboardController, PageController, AdminController, UserController
│   │   ├── Portal/         # AuthController, DashboardController, ProfileController
│   │   └── PageController.php
│   └── Middleware/         # AdminAuth, RedirectIfNotCustomer, RedirectIfAuthenticated, HandleInertiaRequests
├── Models/                 # Admin, User, Page, MetaInformation, SiteSetting
└── Services/               # SEOAnalyzerService
database/
├── factories/              # AdminFactory, UserFactory, PageFactory, MetaInformationFactory
├── migrations/
└── seeders/                # AdminSeeder, SiteSettingsSeeder, DatabaseSeeder
resources/
├── views/admin/            # admin panel Blade views
└── js/pages/               # portal Inertia React pages
routes/web.php              # all routes (admin + portal + frontend)
```

## Installation & Setup

### Prerequisites
- PHP 8.2 or higher
- Composer
- SQLite (default) or MySQL/PostgreSQL
- Node.js & npm (for frontend assets)

### Step 1: Install Dependencies
```bash
composer install
npm install
```

### Step 2: Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

### Step 3: Database Setup
```bash
# Create the SQLite database file (if not exists)
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed the default admin and site settings
# Seeding example data (includes the Section 31 demo business)
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=SiteSettingsSeeder
php artisan db:seed --class=DemoDataSeeder
```

### Step 4: Build Frontend Assets
```bash
npm run build
# or for development
npm run dev
```

### Step 5: Start Development Server
```bash
php artisan serve
php artisan queue:work
```

## Default Credentials

### Admin Login
- **URL**: `/admin/login`
- **Email**: `admin@example.com`
- **Password**: `password`

## Routes

- **Portal** (`/portal`): login, register, forgot/reset password, dashboard, profile
- **Admin** (`/admin`): login, dashboard, pages (+ analyze-seo), admins, users, profile
- **Frontend**: `GET /page/{page}`; `/` redirects to portal login

## Database Structure

1. **admins**: id, name, email, password, role, is_active, timestamps
2. **users**: id, name, email, password, is_active, timestamps
3. **pages**: id, title, slug, content, status, show_breadcrumb, created_by, updated_by, timestamps
4. **meta_information**: polymorphic SEO meta data
5. **site_settings**: key/value config
6. Laravel defaults: cache, jobs, sessions

## Testing

```bash
# Run all tests
./vendor/bin/pest

# Run a specific suite
./vendor/bin/pest tests/Feature/
./vendor/bin/pest tests/Unit/
```

> The full suite passes. The inherited scaffold tests (admin, pages, SEO, models) are
> green; the accounting features are covered by per-sprint feature tests.

## Deploy

```bash
bash deploy/install.sh
```

See `deploy/install.sh`, `deploy/nginx.conf`, and `deploy/supervisor.conf`.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
