<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Checkpoint;

use Illuminate\Support\Facades\DB;

/**
 * Database-based checkpoint storage for Laravel
 */
class DatabaseCheckpointStorage implements CheckpointStorage
{
    private string $table = 'spacetime_checkpoints';
    
    public function __construct()
    {
        $this->ensureTableExists();
    }
    
    public function save(string $id, array $data): void
    {
        DB::table($this->table)->updateOrInsert(
            ['checkpoint_id' => $id],
            [
                'checkpoint_id' => $id,
                'data' => serialize($data),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
    
    public function load(string $id): ?array
    {
        $checkpoint = DB::table($this->table)
            ->where('checkpoint_id', $id)
            ->first();
        
        if (!$checkpoint) {
            return null;
        }
        
        return unserialize($checkpoint->data);
    }
    
    public function exists(string $id): bool
    {
        return DB::table($this->table)
            ->where('checkpoint_id', $id)
            ->exists();
    }
    
    public function delete(string $id): void
    {
        DB::table($this->table)
            ->where('checkpoint_id', $id)
            ->delete();
    }
    
    public function list(): array
    {
        return DB::table($this->table)
            ->select('checkpoint_id as id', 'created_at as timestamp')
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'timestamp' => strtotime($row->timestamp),
            ])
            ->toArray();
    }
    
    public function cleanup(int $olderThanTimestamp): int
    {
        return DB::table($this->table)
            ->where('created_at', '<', date('Y-m-d H:i:s', $olderThanTimestamp))
            ->delete();
    }
    
    /**
     * Ensure checkpoints table exists
     */
    private function ensureTableExists(): void
    {
        if (!DB::getSchemaBuilder()->hasTable($this->table)) {
            DB::getSchemaBuilder()->create($this->table, function ($table) {
                $table->string('checkpoint_id')->primary();
                $table->longText('data');
                $table->timestamps();
                $table->index('created_at');
            });
        }
    }
}