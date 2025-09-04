# Counted Analytics (Work in progress)

A privacy-focused, self-hosted analytics platform built with Laravel 12 and Vue 3. Counted is an alternative to Google Analytics that respects user privacy while providing powerful insights into your website traffic.

## Why Counted?

- **Privacy First**: No cookies, no tracking scripts, no personal data collection
- **Self-Hosted**: Complete control over your data—your analytics never leave your servers
- **Handles Large Sites**: Efficient aggregation and optimized queries ensure fast loading, even with high-traffic or large-scale websites
- **Lightweight**: Minimal impact on page load times and user experience
- **GDPR Compliant**: Built with privacy regulations in mind from the ground up
- **Open Source**: Transparent codebase you can audit and customize

Think of Counted as a privacy-respecting alternative to Google Analytics, similar to Umami or Plausible, but built with the power and flexibility of Laravel and Vue.js.

## Feature Comparison

| Feature                      | Counted         | Plausible       | Umami           | Google Analytics   |
|------------------------------|-----------------|-----------------|-----------------|--------------------|
| **Privacy-First**            | ✅ No cookies, no personal data | ✅ No cookies, no personal data | ✅ No cookies, no personal data | ❌ Uses cookies, collects personal data |
| **Self-Hosted**              | ✅ Yes          | ✅ Yes          | ✅ Yes          | ❌ No               |
| **Open Source**              | ✅ Yes          | ✅ Yes          | ✅ Yes          | ❌ No               |
| **GDPR Compliant**           | ✅ Yes          | ✅ Yes          | ✅ Yes          | ⚠️ Requires configuration |
| **Real-Time Insights**       | ✅ Yes          | ✅ Yes          | ✅ Yes          | ✅ Yes              |
| **Multi-site Support**       | ✅ Yes          | ✅ Yes          | ✅ Yes          | ✅ Yes              |
| **Performance Optimized**    | ✅ Handles billions of page views, fast even on large sites | ✅ Yes, generally fast but may slow on very high traffic | ⚠️ Slows down on large sites (e.g., loading >7 days) | ✅ Yes, designed for scale but data is processed by Google |
| **Import System**            | ✅ Import from Umami | ❌ No           | ❌ No           | N/A                |
| **Modern UI / Dark Mode**    | ✅ Yes          | ✅ Yes          | ✅ Yes          | ✅ Yes              |
| **Data Ownership**           | ✅ 100% yours   | ✅ 100% yours   | ✅ 100% yours   | ❌ Google-owned     |
| **Pricing**                  | Free, open source | Paid (hosted) / Free (self-hosted) | Free, open source | Free (with data trade-off) |

## Installation on production

### With any web server

1. Clone the repository
2. Install dependencies: `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`
3. Copy `.env.example` to `.env` and configure your environment variables
4. Run migrations: `php artisan migrate`
5. Configure the Laravel scheduler to run every minute (e.g., using a cron job: `* * * * * cd /path/to/your/app && php artisan schedule:run >> /dev/null 2>&1`)
6. Start Laravel Horizon for queue processing (e.g., `php artisan horizon`)
7. (Optional) Set up Horizon as a daemon or service for automatic restarts

### With Laravel Forge

1. Connect your server to Forge and create a new site.
2. Link `https://github.com/mchev/counted.git` repository on `main` branch and deploy the code.
3. In the "Environment" tab, set your environment variables (database).
4. In the "Deploy Script", ensure you have:
    - `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`
    - `php artisan migrate --force`
5. In the "Application" tab, enable "Laravel Scheduler".
6. In the "Application" tab, enable "Laravel Horizon".
7. Deploy your site. Forge will handle the rest, including keeping Horizon running and the scheduler active.

## Aggregation System

The platform includes an aggregation system for handling billions of page views:

### Tables
- `analytics_hourly` - Hourly aggregations
- `analytics_daily` - Daily aggregations  
- `analytics_monthly` - Monthly aggregations

### Commands
```bash
# Run aggregation manually
php artisan analytics:aggregate

# Schedule aggregation (Laravel 12)
# Configured in routes/console.php
```

## Import System

> **Tip:** For large SQL dumps, it is recommended to upload your `.sql.gz` file directly to your server and use the "Import from FTP" function for better reliability and speed. For smaller dumps, you can use the HTTP upload option from your browser.

### Supported Formats
- SQL dumps (`.sql`)
- Compressed SQL dumps (`.gz`, `.sql.gz`)

### Features
- Automatic detection of compressed files
- Background processing for large imports
- Progress tracking
- Error handling with retry logic
- Import history

### Usage

**For large dumps:**
1. Upload your compressed SQL dump (`.sql.gz`) to your server via FTP in `storage/app/imports`.
2. Go to the Import page.
4. Choose "Import from FTP" and select your uploaded file.
5. Choose dry run or real import.
6. Monitor progress.

**For small dumps:**
1. Go to the Import page.
2. Select your site.
3. Upload your SQL dump directly via HTTP.
4. Choose dry run or real import.
5. Monitor progress.

## Development

```bash
# Start development server
php artisan serve

# Watch for changes
npm run dev

# Build for production
npm run build
```

## Testing

```bash
php artisan test
``` 
