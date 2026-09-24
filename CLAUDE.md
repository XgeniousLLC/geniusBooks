# Laravel + Inertia Starter Scaffold

Self-hosted app scaffold built on the base Blade admin panel with a React (Inertia) customer portal. Use this as the starting point for a new project; it ships only setup, authentication, and a fresh database.

## What's Included

- **Admin panel (Blade + Alpine.js)**: admin login/logout, dashboard, profile + password management, admin CRUD, user CRUD, page management with SEO/meta analyzer.
- **Customer portal (React + Inertia)**: sign-up, login, forgot/reset password, authenticated dashboard, profile + password management.
- **Database**: migrations, factories, and seeders to stand up a fresh database.
- **Deploy**: `deploy/install.sh`, `deploy/nginx.conf`, `deploy/supervisor.conf`.

## Stack

- **Backend**: Laravel 12 (PHP 8.2+), Eloquent, Form Requests, Policies
- **Admin UI**: Laravel Blade + Alpine.js
- **Portal UI**: React 19 + TypeScript + Inertia + Vite + Tailwind CSS
- **Database**: MySQL (production) / SQLite (local default)
- **Queue**: Laravel Queue (database driver) + Supervisor
- **Auth**: Session guards — `admin` guard (Blade admin) and `web` guard (portal customers)

## Database Structure

1. **admins**: id, name, email, password, role, is_active, timestamps
2. **users**: id, name, email, password, is_active, timestamps
3. **pages**: id, title, slug, content, status, show_breadcrumb, created_by, updated_by, timestamps
4. **meta_information**: polymorphic SEO meta data
5. **site_settings**: key/value config
6. Laravel defaults: cache, jobs, sessions

## Key Relationships

- Admin hasMany Pages (created_by, updated_by)
- Page morphOne MetaInformation

## Key Files

```
app/Http/Controllers/Admin/   # AuthController, DashboardController, PageController, AdminController, UserController
app/Http/Controllers/Portal/  # AuthController, DashboardController, ProfileController
app/Http/Controllers/         # PageController (public frontend)
app/Http/Middleware/          # AdminAuth, RedirectIfNotCustomer, RedirectIfAuthenticated, HandleInertiaRequests
app/Models/                   # Admin, User, Page, MetaInformation, SiteSetting
app/Services/                 # SEOAnalyzerService
resources/views/admin/        # admin panel Blade views
resources/js/pages/           # portal Inertia React pages
routes/web.php                # all routes (admin + portal + frontend)
```

## Routes

- **Portal** (`/portal`): login, register, forgot/reset password, dashboard, profile
- **Admin** (`/admin`): login, dashboard, pages (+ analyze-seo), admins, users, profile
- **Frontend**: `GET /page/{page}`; `/` redirects to portal login

## Commands

```bash
# Setup
composer install
npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite

# Database
php artisan migrate
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=SiteSettingsSeeder

# Dev
php artisan serve
php artisan queue:work
npm run dev

# Test
./vendor/bin/pest
```

## Default Credentials

- **Admin**: `admin@example.com` / `password` at `/admin/login`

## Constraints

- Files stored outside public web root (MIME + size validated)
- Session auth for both admin and portal
- No emojis in UI, logs, or messages
