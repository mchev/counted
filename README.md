# Counted Analytics

A privacy-focused, self-hosted analytics platform built with Laravel 12 and Vue 3. Counted is an alternative to Google Analytics that respects user privacy while providing powerful insights into your website traffic.

## Why Counted?

- **Privacy First**: No cookies, no tracking scripts, no personal data collection
- **Self-Hosted**: Complete control over your data - it never leaves your servers
- **Lightweight**: Minimal impact on page load times and user experience
- **GDPR Compliant**: Built with privacy regulations in mind from the ground up
- **Open Source**: Transparent codebase you can audit and customize

Think of Counted as a privacy-respecting alternative to Google Analytics, similar to Umami or Plausible, but built with the power and flexibility of Laravel and Vue.js.

## Features

- **Privacy-First Analytics**: No cookies, no personal data collection, GDPR compliant
- **Real-time Insights**: Live dashboard with instant data updates
- **Multi-site Support**: Track multiple websites from a single dashboard
- **Performance Optimized**: Data aggregation system handles billions of page views
- **Import System**: Migrate from Google Analytics, Umami, or other platforms
- **Modern UI**: Beautiful, responsive interface with dark mode support
- **Self-Hosted**: Complete control over your data and infrastructure

## Installation

1. Clone the repository
2. Install dependencies: `composer install && npm install`
3. Copy `.env.example` to `.env` and configure
4. Run migrations: `php artisan migrate`
5. Start the development server: `php artisan serve`

## Configuration for Large Files

### For 900MB+ SQL Dumps

If you need to import large SQL dumps (900MB+), configure your server:

#### PHP Configuration
Add to your `php.ini` or server configuration:
```ini
upload_max_filesize = 2G
post_max_size = 2G
max_execution_time = 3600
max_input_time = 3600
memory_limit = 1G
```

#### Nginx Configuration
Add to your nginx server block:
```nginx
client_max_body_size 2G;
proxy_read_timeout 3600s;
proxy_connect_timeout 3600s;
proxy_send_timeout 3600s;
```

#### Apache Configuration
Add to your `.htaccess` or server config:
```apache
php_value upload_max_filesize 2G
php_value post_max_size 2G
php_value max_execution_time 3600
php_value max_input_time 3600
php_value memory_limit 1G
```

#### Laravel Forge
1. Go to your site in Forge
2. Edit nginx configuration
3. Add: `client_max_body_size 2G;`
4. Restart the site

### Recommended Approach for Large Files

1. **Compress your dump**: `mysqldump -u user -p database | gzip > dump.sql.gz`
2. **Split large files**: `split -b 500M dump.sql.gz dump_part_`
3. **Use background processing**: Large files are automatically processed in the background

## Aggregation System

The platform includes an aggregation system for handling billions of page views:

### Tables
- `analytics_realtime` - Real-time data
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

### Laravel Forge Setup
1. Add a new Daemon in Forge
2. Command: `php /path/to/your/app/artisan queue:work --timeout=3600`
3. User: `forge`
4. Directory: `/path/to/your/app`
5. Auto-restart: Enabled

## Import System

### Supported Formats
- SQL dumps (.sql)
- Compressed SQL dumps (.gz, .sql.gz)
- Maximum file size: 2GB

### Features
- Automatic compression detection
- Background processing for large files
- Progress tracking
- Error handling and retry logic
- Import history

### Usage
1. Go to Import page
2. Select your site
3. Upload SQL dump
4. Choose dry run or real import
5. Monitor progress

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