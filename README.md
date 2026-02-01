# Season Pass Group Purchase (SPGP)

A specialized Laravel application for coordinating group purchases of ski passes (e.g., Ikon Pass). This system manages seasons, pass types, and pass requests from users, ensuring everyone gets the group discount.

## Features

- **User Authentication**: Secure login and registration with invite code enforcement.
- **Season Management**: Admin control over pass seasons, deadlines, and pricing.
- **Pass Request Workflow**: 
  - Users request passes for specific seasons.
  - Support for new requests and renewals.
  - Admin tracking of redemption codes and email notifications.
- **Admin Dashboard**: Comprehensive overview of all requests, with tools for bulk operations.
- **Email Logging**: Detailed history of all system-generated emails.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: React 19 with TypeScript
- **UI Components**: shadcn/ui + Radix UI primitives
- **Styling**: Tailwind CSS v4
- **Build**: Vite
- **Database**: MySQL/SQLite

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 18+ and pnpm
- MySQL or SQLite

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd spgp/laravel
   ```

2. **Install dependencies**
   ```bash
   composer install
   pnpm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   
   Update `.env` with your database credentials.

4. **Run migrations**
   ```bash
   php artisan migrate
   ```

5. **Build assets**
   ```bash
   pnpm run build
   ```

### Development

Run the development server:
```bash
# Start Laravel server and Vite dev server concurrently
pnpm run dev
php artisan serve
```

Or use multiple terminals:
- Terminal 1: `php artisan serve`
- Terminal 2: `pnpm run dev`

## CLI Commands

### Create User

Create a new user with a confirmed email address and optional admin status. This bypasses the invite code requirement.

```bash
php artisan user:create "Name" "email@example.com" "password" [--admin]
```

## Database Schema

The system uses several core tables:
- `users`: User accounts with `is_admin` and `invite_code_id`.
- `seasons`: Definitions for different ski seasons and their deadlines.
- `season_pass_types`: Pricing and name definitions for passes within a season. Includes 4-tier pricing: (Group, Non-Group) x (Early Spring, Late Spring/Summer).
- `pass_requests`: Individual requests for passes, tracking passholder details and status.
- `invite_codes`: Codes required for public registration.
- `email_logs`: History of emails sent to users.
- `user_logins`: Audit log of user login activity.

## Deployment

### Deployment Instructions

1. **Upload Project Files**
   - Upload all project files to your server, excluding `node_modules/`, `vendor/`, and `.env`
   - Place files in a directory outside of `public_html`, e.g., `~/spgp/`

2. **Install Dependencies**
   ```bash
   cd ~/spgp
   composer install --no-dev --optimize-autoloader
   pnpm install
   pnpm run build
   ```

3. **Configure Environment**
   - Create `.env` and configure database, `APP_KEY`, and `APP_URL`

4. **Set Up Public Directory**
   - Copy `~/spgp/public/` contents to `~/public_html/`
   - Update `index.php` paths as needed

5. **Database Setup**
   ```bash
   php artisan migrate --force
   ```

6. **Set Permissions**
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

7. **Cache Configuration**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

## Testing

### Running Tests

```bash
# Run all PHP tests
composer test

# Or directly with artisan
php artisan test
```

### Database Safety

**Important:** Tests are configured to ALWAYS use SQLite in-memory database, never MySQL.

This is enforced at multiple levels:
1. `phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`
2. `Tests\SafeTestCase` verifies SQLite is active and throws an error if not.

## License

Private - All rights reserved