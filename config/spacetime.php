<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Memory Limit
    |--------------------------------------------------------------------------
    |
    | Maximum memory that SpaceTime operations can use. Can be specified
    | as a string (e.g., '256M', '1G') or number of bytes.
    |
    */
    'memory_limit' => env('SPACETIME_MEMORY_LIMIT', '256M'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Directory where SpaceTime will store temporary files for external
    | algorithms. Defaults to storage/spacetime.
    |
    */
    'storage_path' => env('SPACETIME_STORAGE_PATH', storage_path('spacetime')),

    /*
    |--------------------------------------------------------------------------
    | Chunk Strategy
    |--------------------------------------------------------------------------
    |
    | Strategy for determining chunk sizes:
    | - 'sqrt_n': Use √n of total items (recommended)
    | - 'memory_based': Based on available memory
    | - 'fixed': Fixed chunk size
    |
    */
    'chunk_strategy' => env('SPACETIME_CHUNK_STRATEGY', 'sqrt_n'),

    /*
    |--------------------------------------------------------------------------
    | Enable Checkpointing
    |--------------------------------------------------------------------------
    |
    | Whether to enable automatic checkpointing for long-running operations.
    | Checkpoints allow operations to be resumed after failures.
    |
    */
    'enable_checkpointing' => env('SPACETIME_CHECKPOINTING', true),

    /*
    |--------------------------------------------------------------------------
    | Checkpoint Storage
    |--------------------------------------------------------------------------
    |
    | Where to store checkpoints:
    | - 'file': Store in filesystem
    | - 'cache': Use Laravel's cache system
    | - 'database': Store in database
    |
    */
    'checkpoint_storage' => env('SPACETIME_CHECKPOINT_STORAGE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    |
    | Whether to compress data in external storage. Reduces disk usage
    | but adds CPU overhead.
    |
    */
    'compression' => env('SPACETIME_COMPRESSION', true),
    'compression_level' => env('SPACETIME_COMPRESSION_LEVEL', 6),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | Redis connection to use for distributed operations. Set to null
    | to disable distributed features.
    |
    */
    'redis_connection' => env('SPACETIME_REDIS_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Profiling
    |--------------------------------------------------------------------------
    |
    | Enable profiling to collect performance metrics. Useful for debugging
    | but adds overhead.
    |
    */
    'enable_profiling' => env('SPACETIME_PROFILING', false),

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    |
    | How to handle cleanup of temporary files:
    | - 'immediate': Clean up immediately after use
    | - 'delayed': Clean up after a delay
    | - 'manual': No automatic cleanup
    |
    */
    'cleanup_mode' => env('SPACETIME_CLEANUP_MODE', 'immediate'),
    'cleanup_delay' => env('SPACETIME_CLEANUP_DELAY', 3600), // 1 hour
];