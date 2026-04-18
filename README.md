# Season Pass Group Purchase (SPGP)

A specialized Laravel application for coordinating group purchases of ski passes. This system manages seasons, pass types, and pass requests from users, ensuring everyone gets the group discount.

## Features

- **User Authentication**: Secure login and registration with invite code enforcement.
- **Season Management**: Admin control over pass seasons, deadlines, and pricing.
- **Season Q&A**: Users can ask questions about seasons in Markdown format. Admins can provide answers, and users can upvote helpful questions.
- **Pass Request Workflow**:
  - Users request passes for specific seasons.
  - Support for new requests and renewals.
  - Admin tracking of redemption codes and email notifications.
- **Promo Code Repository**: Admins import promo codes per season and country (USA / Canada). Auto-assign distributes available codes to matching pending pass requests oldest-first. Newly auto-assigned rows are automatically selected after the operation.
- **Dashboard Deadline Countdown**: Each active season card shows a live countdown (days / hours / minutes, updating every minute) to the final deadline. If the deadline is fewer than 3 days away, a warning is shown that promo codes will be fulfilled ASAP but may not arrive in time.
- **Admin Dashboard**: Comprehensive overview of all requests, with tools for bulk operations (assign codes, send emails, copy TSV, delete).
- **Email Logging**: Detailed history of all system-generated emails.

## Tech Stack

- **Backend**: Laravel 13 (PHP 8.2+)
- **Frontend**: React 19 with TypeScript
- **UI Components**: shadcn/ui + Radix UI primitives
- **Styling**: Tailwind CSS v4
- **Build**: Vite
- **Database**: MySQL/SQLite
- **Error Tracking**: Sentry (PHP + browser, enabled when `SENTRY_DSN` is set)

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

   To enable Sentry error tracking, set `SENTRY_DSN` in your `.env` file.
   When `SENTRY_DSN` is blank or not set, Sentry is disabled (PHP + JS).

4. **Run migrations**
   ```bash
   php artisan migrate --database=sqlite --no-interaction
   ```

   > **Note**: If your `.env` points to a production MySQL host, always pass `--database=sqlite` to avoid running against real data.

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
- `users`: User accounts with `is_admin`; linked to invite codes via `invite_code_user` pivot table.
- `seasons`: Definitions for different ski seasons and their deadlines.
- `season_pass_types`: Pricing and name definitions for passes within a season. Includes 6-tier pricing: (Group, Non-Group New, Non-Group Renewal) x (Early Spring, Late Spring/Summer).
- `questions`: User questions for seasons, with optional admin answers.
- `question_upvotes`: Tracks user upvotes on questions.
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

## Date Handling

All dates are stored as **UTC**. The `SerializesDatesAsLocal` trait serializes every model date as an ISO 8601 string with timezone offset so the browser can convert to local time automatically.

Frontend date display uses shared helpers in `resources/js/lib/dateHelpers.ts`:

| Helper | Use for |
|---|---|
| `formatDateOnly` | `DATE` columns (no time): birth dates, `assign_code_date`, promo code dates |
| `formatDateTime` | `DATETIME`/`TIMESTAMP` columns: deadlines, `created_at`, login times |
| `getCountdown` | Human-readable time-until for deadlines |

Never use `toLocaleDateString()` or `toLocaleString()` directly — always use the shared helpers.

## Testing

See [TESTING.md](TESTING.md) for detailed instructions on running tests and troubleshooting common environment issues.

### Running Tests

```bash
# Run all PHP tests
php artisan test

# Run TypeScript/JavaScript tests
pnpm test
```

### Linting

```bash
# Run ESLint linter
pnpm lint

# Run ESLint linter and auto-fix issues
pnpm lint:fix

# Run TypeScript type-check
pnpm type-check
```

## License

Private - All rights reserved