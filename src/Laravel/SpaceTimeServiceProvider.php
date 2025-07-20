<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use SqrtSpace\SpaceTime\SpaceTimeConfig;
use SqrtSpace\SpaceTime\Algorithms\ExternalSort;
use SqrtSpace\SpaceTime\Algorithms\ExternalGroupBy;

/**
 * Laravel service provider for SpaceTime
 */
class SpaceTimeServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/spacetime.php', 'spacetime');
        
        // Configure SpaceTime with Laravel config
        $this->app->booted(function () {
            SpaceTimeConfig::configure([
                'memory_limit' => config('spacetime.memory_limit', '256M'),
                'external_storage_path' => config('spacetime.storage_path', storage_path('spacetime')),
                'chunk_strategy' => config('spacetime.chunk_strategy', 'sqrt_n'),
                'enable_checkpointing' => config('spacetime.enable_checkpointing', true),
                'compression' => config('spacetime.compression', true),
            ]);
        });
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/spacetime.php' => config_path('spacetime.php'),
        ], 'spacetime-config');

        // Register Collection macros
        $this->registerCollectionMacros();
        
        // Register Query Builder macros
        $this->registerQueryBuilderMacros();
    }

    /**
     * Register Collection macros
     */
    private function registerCollectionMacros(): void
    {
        // Sort using external memory
        Collection::macro('sortByExternal', function ($callback = null) {
            $items = $this->all();
            
            if ($callback) {
                $sorted = ExternalSort::sortBy($items, $callback);
            } else {
                $sorted = ExternalSort::sort($items);
            }
            
            return new static($sorted);
        });

        // Sort by key using external memory
        Collection::macro('sortByDescExternal', function ($callback) {
            $items = $this->all();
            $sorted = ExternalSort::sortBy($items, $callback, fn($a, $b) => $b <=> $a);
            return new static($sorted);
        });

        // Group by using external memory
        Collection::macro('groupByExternal', function ($groupBy) {
            $callback = $this->valueRetriever($groupBy);
            $grouped = ExternalGroupBy::groupBy($this->all(), $callback);
            
            return new static($grouped);
        });

        // Chunk by √n
        Collection::macro('chunkBySqrtN', function () {
            $size = SpaceTimeConfig::calculateSqrtN($this->count());
            return $this->chunk($size);
        });

        // Process in √n batches
        Collection::macro('eachBySqrtN', function ($callback) {
            $this->chunkBySqrtN()->each(function ($chunk) use ($callback) {
                $chunk->each($callback);
            });
        });

        // Map with checkpointing
        Collection::macro('mapWithCheckpoint', function ($callback, $checkpointKey = null) {
            $checkpointKey = $checkpointKey ?: 'collection_map_' . uniqid();
            $checkpoint = new \Ubiquity\SpaceTime\Checkpoint\CheckpointManager($checkpointKey);
            
            $result = [];
            $processed = 0;
            
            foreach ($this->all() as $key => $value) {
                $result[$key] = $callback($value, $key);
                $processed++;
                
                if ($checkpoint->shouldCheckpoint()) {
                    $checkpoint->save([
                        'processed' => $processed,
                        'result' => $result,
                    ]);
                }
            }
            
            return new static($result);
        });
    }

    /**
     * Register Query Builder macros
     */
    private function registerQueryBuilderMacros(): void
    {
        // Chunk by √n
        \Illuminate\Database\Query\Builder::macro('chunkBySqrtN', function ($callback) {
            $total = $this->count();
            $chunkSize = SpaceTimeConfig::calculateSqrtN($total);
            
            return $this->chunk($chunkSize, $callback);
        });

        // Order by external
        \Illuminate\Database\Query\Builder::macro('orderByExternal', function ($column, $direction = 'asc') {
            // This is a placeholder - in practice, you'd implement
            // external sorting at the query level
            return $this->orderBy($column, $direction);
        });

        // Get with √n memory usage
        \Illuminate\Database\Query\Builder::macro('getBySqrtN', function () {
            $results = collect();
            
            $this->chunkBySqrtN(function ($chunk) use ($results) {
                $results = $results->merge($chunk);
            });
            
            return $results;
        });
    }
}