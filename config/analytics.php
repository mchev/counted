<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the analytics aggregation and cleanup system.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Data Retention Periods
    |--------------------------------------------------------------------------
    |
    | How long to keep data at each aggregation level before cleanup.
    | Values are in days for all types.
    |
    */
    'retention' => [
        'page_views' => env('ANALYTICS_RETENTION_PAGE_VIEWS', 7), // days
        'events' => env('ANALYTICS_RETENTION_EVENTS', 7), // days
        'hourly' => env('ANALYTICS_RETENTION_HOURLY', 7),      // days
        'daily' => env('ANALYTICS_RETENTION_DAILY', 365),      // days
        'monthly' => env('ANALYTICS_RETENTION_MONTHLY', 1825), // days (5 years)
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic data cleanup after aggregation.
    |
    */
    'cleanup' => [
        'enabled' => env('ANALYTICS_CLEANUP_ENABLED', true),
        'after_aggregation' => env('ANALYTICS_CLEANUP_AFTER_AGGREGATION', true),
        'batch_size' => env('ANALYTICS_CLEANUP_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for analytics data caching.
    |
    */
    'cache' => [
        'enabled' => env('ANALYTICS_CACHE_ENABLED', true),
        'ttl' => [
            'hourly' => env('ANALYTICS_CACHE_TTL_HOURLY', 300),   // 5 minutes
            'daily' => env('ANALYTICS_CACHE_TTL_DAILY', 1800),    // 30 minutes
            'monthly' => env('ANALYTICS_CACHE_TTL_MONTHLY', 3600), // 1 hour
        ],
        'prefix' => env('ANALYTICS_CACHE_PREFIX', 'analytics'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for data aggregation process.
    |
    */
    'aggregation' => [
        'batch_size' => env('ANALYTICS_AGGREGATION_BATCH_SIZE', 1000),
        'chunk_size' => env('ANALYTICS_CHUNK_SIZE', 10000),
        'timeout' => env('ANALYTICS_AGGREGATION_TIMEOUT', 1800), // 30 minutes
        'memory_limit' => env('ANALYTICS_AGGREGATION_MEMORY_LIMIT', '1G'),
        'use_optimized_queries' => env('ANALYTICS_USE_OPTIMIZED_QUERIES', true),
        'use_chunking_for_large_datasets' => env('ANALYTICS_USE_CHUNKING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for performance optimizations.
    |
    */
    'performance' => [
        'use_queue' => env('ANALYTICS_USE_QUEUE', true),
        'queue_name' => env('ANALYTICS_QUEUE_NAME', 'analytics'),
        'max_execution_time' => env('ANALYTICS_MAX_EXECUTION_TIME', 1800),
        'database_connection' => env('ANALYTICS_DB_CONNECTION', 'mysql'),
        'enable_query_logging' => env('ANALYTICS_ENABLE_QUERY_LOGGING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring and alerting.
    |
    */
    'monitoring' => [
        'enabled' => env('ANALYTICS_MONITORING_ENABLED', true),
        'log_aggregation_times' => env('ANALYTICS_LOG_AGGREGATION_TIMES', true),
        'alert_on_failure' => env('ANALYTICS_ALERT_ON_FAILURE', true),
        'alert_threshold' => env('ANALYTICS_ALERT_THRESHOLD', 300), // seconds
        'log_slow_queries' => env('ANALYTICS_LOG_SLOW_QUERIES', true),
        'slow_query_threshold' => env('ANALYTICS_SLOW_QUERY_THRESHOLD', 5), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Scaling Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for handling large-scale analytics.
    |
    */
    'scaling' => [
        'large_site_threshold' => env('ANALYTICS_LARGE_SITE_THRESHOLD', 100000), // page views per day
        'enable_partitioning' => env('ANALYTICS_ENABLE_PARTITIONING', false),
        'partition_by_month' => env('ANALYTICS_PARTITION_BY_MONTH', true),
        'enable_sharding' => env('ANALYTICS_ENABLE_SHARDING', false),
        'shard_count' => env('ANALYTICS_SHARD_COUNT', 4),
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for data import and aggregation during import.
    |
    */
    'import' => [
        'use_aggregated_import' => env('ANALYTICS_USE_AGGREGATED_IMPORT', true),
        'aggregate_during_import' => env('ANALYTICS_AGGREGATE_DURING_IMPORT', true),
        'skip_raw_data_storage' => env('ANALYTICS_SKIP_RAW_DATA_STORAGE', true),
        'import_batch_size' => env('ANALYTICS_IMPORT_BATCH_SIZE', 1000),
        'import_chunk_size' => env('ANALYTICS_IMPORT_CHUNK_SIZE', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Optimization Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for database optimizations.
    |
    */
    'database' => [
        'optimize_indexes' => env('ANALYTICS_OPTIMIZE_INDEXES', true),
        'use_covering_indexes' => env('ANALYTICS_USE_COVERING_INDEXES', true),
        'enable_query_cache' => env('ANALYTICS_ENABLE_QUERY_CACHE', true),
        'query_cache_size' => env('ANALYTICS_QUERY_CACHE_SIZE', '256M'),
        'innodb_buffer_pool_size' => env('ANALYTICS_INNODB_BUFFER_POOL_SIZE', '70%'),
    ],
];
