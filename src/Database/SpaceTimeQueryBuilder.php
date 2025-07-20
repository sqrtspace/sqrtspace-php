<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Database;

use SqrtSpace\SpaceTime\SpaceTimeConfig;
use SqrtSpace\SpaceTime\Streams\SpaceTimeStream;
use SqrtSpace\SpaceTime\Algorithms\ExternalSort;
use SqrtSpace\SpaceTime\Algorithms\ExternalGroupBy;

/**
 * SpaceTime-aware query builder for large datasets
 */
class SpaceTimeQueryBuilder
{
    private \PDO $connection;
    private string $table;
    private array $wheres = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $columns = ['*'];
    
    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Set table
     */
    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }
    
    /**
     * Select columns
     */
    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }
    
    /**
     * Add where clause
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = compact('column', 'operator', 'value');
        return $this;
    }
    
    /**
     * Add order by
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderBy[] = compact('column', 'direction');
        return $this;
    }
    
    /**
     * Set limit
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Set offset
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * Get results as stream
     */
    public function stream(): SpaceTimeStream
    {
        $sql = $this->buildSql();
        $statement = $this->connection->prepare($sql);
        $this->bindValues($statement);
        
        $generator = function() use ($statement) {
            $statement->execute();
            
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                yield $row;
            }
        };
        
        return SpaceTimeStream::from($generator());
    }
    
    /**
     * Process in √n chunks
     */
    public function chunkBySqrtN(callable $callback): void
    {
        $total = $this->count();
        $chunkSize = SpaceTimeConfig::calculateSqrtN($total);
        
        $this->chunk($chunkSize, $callback);
    }
    
    /**
     * Process in chunks
     */
    public function chunk(int $size, callable $callback): void
    {
        $offset = 0;
        
        do {
            $results = $this->offset($offset)->limit($size)->get();
            
            if (empty($results)) {
                break;
            }
            
            if ($callback($results) === false) {
                break;
            }
            
            $offset += $size;
        } while (count($results) === $size);
    }
    
    /**
     * Get all results
     */
    public function get(): array
    {
        $sql = $this->buildSql();
        $statement = $this->connection->prepare($sql);
        $this->bindValues($statement);
        $statement->execute();
        
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Count results
     */
    public function count(): int
    {
        $sql = $this->buildCountSql();
        $statement = $this->connection->prepare($sql);
        $this->bindValues($statement);
        $statement->execute();
        
        return (int) $statement->fetchColumn();
    }
    
    /**
     * Order by using external sort
     */
    public function orderByExternal(string $column, string $direction = 'asc'): array
    {
        $results = $this->get();
        
        $comparator = $direction === 'asc' 
            ? fn($a, $b) => $a <=> $b
            : fn($a, $b) => $b <=> $a;
        
        return ExternalSort::sortBy($results, fn($row) => $row[$column], $comparator);
    }
    
    /**
     * Group by using external grouping
     */
    public function groupByExternal(string $column): array
    {
        $results = $this->get();
        
        return ExternalGroupBy::groupBy($results, fn($row) => $row[$column]);
    }
    
    /**
     * Aggregate with grouping
     */
    public function groupByAggregate(string $groupColumn, string $aggregateColumn, string $function = 'sum'): array
    {
        $results = $this->get();
        
        return match($function) {
            'sum' => ExternalGroupBy::groupBySum(
                $results,
                fn($row) => $row[$groupColumn],
                fn($row) => $row[$aggregateColumn]
            ),
            'count' => ExternalGroupBy::groupByCount(
                $results,
                fn($row) => $row[$groupColumn]
            ),
            'avg' => $this->groupByAverage($results, $groupColumn, $aggregateColumn),
            default => throw new \InvalidArgumentException("Unknown aggregate function: $function"),
        };
    }
    
    /**
     * Build SQL query
     */
    private function buildSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table;
        
        if (!empty($this->wheres)) {
            $conditions = [];
            foreach ($this->wheres as $where) {
                $conditions[] = "{$where['column']} {$where['operator']} ?";
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        if (!empty($this->orderBy)) {
            $orders = [];
            foreach ($this->orderBy as $order) {
                $orders[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }
        
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        
        return $sql;
    }
    
    /**
     * Build count SQL
     */
    private function buildCountSql(): string
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->table;
        
        if (!empty($this->wheres)) {
            $conditions = [];
            foreach ($this->wheres as $where) {
                $conditions[] = "{$where['column']} {$where['operator']} ?";
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        return $sql;
    }
    
    /**
     * Bind values to statement
     */
    private function bindValues(\PDOStatement $statement): void
    {
        $index = 1;
        foreach ($this->wheres as $where) {
            $statement->bindValue($index++, $where['value']);
        }
    }
    
    /**
     * Group by average helper
     */
    private function groupByAverage(array $data, string $groupColumn, string $aggregateColumn): array
    {
        $groups = ExternalGroupBy::groupBy($data, fn($row) => $row[$groupColumn]);
        $result = [];
        
        foreach ($groups as $key => $items) {
            $sum = array_sum(array_column($items, $aggregateColumn));
            $count = count($items);
            $result[$key] = $count > 0 ? $sum / $count : 0;
        }
        
        return $result;
    }
}