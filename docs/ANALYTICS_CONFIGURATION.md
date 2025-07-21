# Analytics Configuration Guide

This document explains how to configure the analytics aggregation and cleanup system.

## Environment Variables

Add these variables to your `.env` file to customize the analytics behavior:

### Data Retention Periods

```env
# How long to keep source data before cleanup
ANALYTICS_RETENTION_PAGE_VIEWS=7   # Days to keep page_views data
ANALYTICS_RETENTION_EVENTS=7       # Days to keep events data

# How long to keep aggregated data
ANALYTICS_RETENTION_HOURLY=7       # Days to keep hourly data
ANALYTICS_RETENTION_DAILY=365      # Days to keep daily data
ANALYTICS_RETENTION_MONTHLY=1825   # Days to keep monthly data (5 years)
```

### Cleanup Settings

```env
# Enable/disable automatic cleanup
ANALYTICS_CLEANUP_ENABLED=true
ANALYTICS_CLEANUP_AFTER_AGGREGATION=true
ANALYTICS_CLEANUP_BATCH_SIZE=1000
```

### Cache Settings

```env
# Enable/disable analytics caching
ANALYTICS_CACHE_ENABLED=true

# Cache TTL in seconds
ANALYTICS_CACHE_TTL_HOURLY=300    # 5 minutes
ANALYTICS_CACHE_TTL_DAILY=1800    # 30 minutes
ANALYTICS_CACHE_TTL_MONTHLY=3600  # 1 hour

# Cache key prefix
ANALYTICS_CACHE_PREFIX=analytics
```

### Performance Settings

```env
# Use queue for aggregation (recommended for large datasets)
ANALYTICS_USE_QUEUE=false
ANALYTICS_QUEUE_NAME=analytics

# Aggregation settings
ANALYTICS_AGGREGATION_BATCH_SIZE=100
ANALYTICS_AGGREGATION_TIMEOUT=300
ANALYTICS_AGGREGATION_MEMORY_LIMIT=512M
ANALYTICS_MAX_EXECUTION_TIME=300
```

### Monitoring Settings

```env
# Enable monitoring and alerting
ANALYTICS_MONITORING_ENABLED=true
ANALYTICS_LOG_AGGREGATION_TIMES=true
ANALYTICS_ALERT_ON_FAILURE=true
ANALYTICS_ALERT_THRESHOLD=300
```

## Command Usage

### Basic Aggregation (Background Jobs)

```bash
# Run all aggregations (hourly, daily, monthly) - uses background jobs
php artisan analytics:aggregate

# Run specific aggregation - uses background jobs
php artisan analytics:aggregate hourly
php artisan analytics:aggregate daily
php artisan analytics:aggregate monthly
```

### Advanced Options

```bash
# Skip cleanup after aggregation
php artisan analytics:aggregate --no-cleanup

# Skip cache operations
php artisan analytics:aggregate --no-cache

# Only run cleanup, skip aggregation
php artisan analytics:aggregate --cleanup-only

# Run synchronously (not recommended for large datasets)
php artisan analytics:aggregate --sync

# Combine options
php artisan analytics:aggregate daily --no-cleanup --no-cache
```

### Job Management

```bash
# Check job status
php artisan analytics:jobs

# Check failed jobs
php artisan analytics:jobs --failed

# Process jobs manually
php artisan queue:work --queue=analytics

# Retry failed jobs
php artisan queue:retry <job_id>
```

## Data Flow

### Simple Aggregation System
```
📊 page_views (7 days max)
    ↓ Hourly Aggregation
📊 analytics_hourly (7 days max)
    ↓ Daily Aggregation
📊 analytics_daily (1 year max)
    ↓ Monthly Aggregation
📊 analytics_monthly (5 years max)
```

### Cleanup Strategy
- **Source data cleanup**: After aggregation, clean up `page_views` and `events`
- **Aggregated data cleanup**: Keep aggregated data for longer periods as specified
- **Automatic cleanup**: Runs after each aggregation cycle

## Performance Recommendations

### For Small Sites (< 10k page views/day)

```env
ANALYTICS_CLEANUP_ENABLED=true
ANALYTICS_CACHE_ENABLED=true
ANALYTICS_USE_QUEUE=false
ANALYTICS_AGGREGATION_BATCH_SIZE=100
```

### For Medium Sites (10k-100k page views/day)

```env
ANALYTICS_CLEANUP_ENABLED=true
ANALYTICS_CACHE_ENABLED=true
ANALYTICS_USE_QUEUE=true
ANALYTICS_AGGREGATION_BATCH_SIZE=500
ANALYTICS_AGGREGATION_MEMORY_LIMIT=1G
```

### For Large Sites (> 100k page views/day)

```env
ANALYTICS_CLEANUP_ENABLED=true
ANALYTICS_CACHE_ENABLED=true
ANALYTICS_USE_QUEUE=true
ANALYTICS_AGGREGATION_BATCH_SIZE=1000
ANALYTICS_AGGREGATION_MEMORY_LIMIT=2G
ANALYTICS_AGGREGATION_TIMEOUT=600
```

## Monitoring

The system automatically logs:

- Aggregation start/end times
- Number of records processed
- Cleanup statistics
- Error messages with context

### Log Examples

```
[2024-01-15 02:00:01] local.INFO: Analytics aggregation started for hourly
[2024-01-15 02:00:15] local.INFO: Cleanup realtime data for site 1: 1500 records deleted
[2024-01-15 02:00:20] local.INFO: Analytics aggregation completed in 19.5s
```

## Troubleshooting

### Common Issues

1. **Aggregation takes too long**
   - Increase `ANALYTICS_AGGREGATION_BATCH_SIZE`
   - Enable queue processing with `ANALYTICS_USE_QUEUE=true`
   - Increase memory limit with `ANALYTICS_AGGREGATION_MEMORY_LIMIT`

2. **High memory usage**
   - Decrease `ANALYTICS_AGGREGATION_BATCH_SIZE`
   - Increase `ANALYTICS_AGGREGATION_MEMORY_LIMIT`
   - Enable cleanup to reduce data volume

3. **Cache not working**
   - Check `ANALYTICS_CACHE_ENABLED=true`
   - Verify Redis/Memcached is configured
   - Check cache TTL settings

4. **Cleanup not running**
   - Check `ANALYTICS_CLEANUP_ENABLED=true`
   - Verify retention periods are reasonable
   - Check logs for error messages

### Debug Mode

To enable debug logging, add to your `.env`:

```env
LOG_LEVEL=debug
ANALYTICS_MONITORING_ENABLED=true
ANALYTICS_LOG_AGGREGATION_TIMES=true
```

This will provide detailed information about:
- SQL queries executed
- Cache hits/misses
- Memory usage
- Processing times per site 