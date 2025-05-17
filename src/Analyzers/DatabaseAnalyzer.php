<?php

namespace Gsferro\GenerateTestsEasy\Analyzers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseAnalyzer
{
    /**
     * Analyze the database structure.
     *
     * @param array $options
     * @return array
     */
    public function analyze(array $options = []): array
    {
        // Get the connection
        $connection = $options['connection'] ?? config('database.default');
        
        // Get the tables to analyze
        $tables = $this->getTables($connection, $options);
        
        // Analyze each table
        $result = [
            'connection' => $connection,
            'tables' => [],
        ];
        
        foreach ($tables as $table) {
            $result['tables'][$table] = $this->analyzeTable($table, $connection);
        }
        
        return $result;
    }
    
    /**
     * Get the tables to analyze.
     *
     * @param string $connection
     * @param array $options
     * @return array
     */
    protected function getTables(string $connection, array $options): array
    {
        // Get all tables
        $allTables = Schema::connection($connection)->getAllTables();
        
        // Extract table names
        $tables = array_map(function ($table) {
            return $table->name;
        }, $allTables);
        
        // Filter tables if specified
        if (isset($options['tables']) && !empty($options['tables'])) {
            $tables = array_intersect($tables, $options['tables']);
        }
        
        // Exclude tables if specified
        if (isset($options['exclude']) && !empty($options['exclude'])) {
            $tables = array_diff($tables, $options['exclude']);
        }
        
        // Always exclude migrations table
        $tables = array_diff($tables, ['migrations']);
        
        return $tables;
    }
    
    /**
     * Analyze a table.
     *
     * @param string $table
     * @param string $connection
     * @return array
     */
    protected function analyzeTable(string $table, string $connection): array
    {
        // Get the columns
        $columns = Schema::connection($connection)->getColumnListing($table);
        
        // Get the primary key
        $primaryKey = $this->getPrimaryKey($table, $connection);
        
        // Get the foreign keys
        $foreignKeys = $this->getForeignKeys($table, $connection);
        
        // Get the indexes
        $indexes = $this->getIndexes($table, $connection);
        
        // Get the model name
        $modelName = $this->guessModelName($table);
        
        return [
            'name' => $table,
            'modelName' => $modelName,
            'primaryKey' => $primaryKey,
            'columns' => $this->getColumns($table, $columns, $connection),
            'foreignKeys' => $foreignKeys,
            'indexes' => $indexes,
            'hasTimestamps' => $this->hasTimestamps($columns),
            'hasSoftDeletes' => $this->hasSoftDeletes($columns),
        ];
    }
    
    /**
     * Get the columns of a table.
     *
     * @param string $table
     * @param array $columns
     * @param string $connection
     * @return array
     */
    protected function getColumns(string $table, array $columns, string $connection): array
    {
        $result = [];
        
        foreach ($columns as $column) {
            $type = Schema::connection($connection)->getColumnType($table, $column);
            
            $result[$column] = [
                'name' => $column,
                'type' => $type,
                'nullable' => $this->isNullable($table, $column, $connection),
                'default' => $this->getDefault($table, $column, $connection),
                'autoIncrement' => $this->isAutoIncrement($table, $column, $connection),
                'unsigned' => $this->isUnsigned($table, $column, $connection),
                'length' => $this->getLength($table, $column, $connection),
            ];
        }
        
        return $result;
    }
    
    /**
     * Get the primary key of a table.
     *
     * @param string $table
     * @param string $connection
     * @return string|null
     */
    protected function getPrimaryKey(string $table, string $connection): ?string
    {
        try {
            $sm = DB::connection($connection)->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails($table);
            
            if ($doctrineTable->hasPrimaryKey()) {
                $primaryKey = $doctrineTable->getPrimaryKey();
                $columns = $primaryKey->getColumns();
                
                if (count($columns) === 1) {
                    return $columns[0];
                }
            }
        } catch (\Exception $e) {
            // If we can't get the primary key, return null
        }
        
        return null;
    }
    
    /**
     * Get the foreign keys of a table.
     *
     * @param string $table
     * @param string $connection
     * @return array
     */
    protected function getForeignKeys(string $table, string $connection): array
    {
        $result = [];
        
        try {
            $sm = DB::connection($connection)->getDoctrineSchemaManager();
            $foreignKeys = $sm->listTableForeignKeys($table);
            
            foreach ($foreignKeys as $foreignKey) {
                $localColumns = $foreignKey->getLocalColumns();
                $foreignColumns = $foreignKey->getForeignColumns();
                
                if (count($localColumns) === 1 && count($foreignColumns) === 1) {
                    $result[$localColumns[0]] = [
                        'localColumn' => $localColumns[0],
                        'foreignTable' => $foreignKey->getForeignTableName(),
                        'foreignColumn' => $foreignColumns[0],
                        'onDelete' => $foreignKey->onDelete,
                        'onUpdate' => $foreignKey->onUpdate,
                    ];
                }
            }
        } catch (\Exception $e) {
            // If we can't get the foreign keys, return an empty array
        }
        
        return $result;
    }
    
    /**
     * Get the indexes of a table.
     *
     * @param string $table
     * @param string $connection
     * @return array
     */
    protected function getIndexes(string $table, string $connection): array
    {
        $result = [];
        
        try {
            $sm = DB::connection($connection)->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes($table);
            
            foreach ($indexes as $index) {
                $columns = $index->getColumns();
                
                $result[$index->getName()] = [
                    'name' => $index->getName(),
                    'columns' => $columns,
                    'unique' => $index->isUnique(),
                    'primary' => $index->isPrimary(),
                ];
            }
        } catch (\Exception $e) {
            // If we can't get the indexes, return an empty array
        }
        
        return $result;
    }
    
    /**
     * Check if a column is nullable.
     *
     * @param string $table
     * @param string $column
     * @param string $connection
     * @return bool
     */
    protected function isNullable(string $table, string $column, string $connection): bool
    {
        try {
            $sm = DB::connection($connection)->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails($table);
            $doctrineColumn = $doctrineTable->getColumn($column);
            
            return !$doctrineColumn->getNotnull();
        } catch (\Exception $e) {
            // If we can't get the column, assume it's not nullable
            return false;
        }
    }
    
    /**
     * Get the default value of a column.
     *
     * @param string $table
     * @param string $column
     * @param string $connection
     * @return mixed
     */
    protected function getDefault(string $table, string $column, string $connection)
    {
        try {
            $sm = DB::connection($connection)->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails($table);
            $doctrineColumn = $doctrineTable->getColumn($column);
            
            return $doctrineColumn->getDefault();
        } catch (\Exception $e) {
            // If we can't get the column, return null
            return null;
        }
    }
    
    /**
     * Check if a column is auto-incrementing.
     *
     * @param string $table
     * @param string $column
     * @param string $connection
     * @return bool
     */
    protected function isAutoIncrement(string $table, string $column, string $connection): bool
    {
        try {
            $sm = DB::connection($connection)->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails($table);
            $doctrineColumn = $doctrineTable->getColumn($column);
            
            return $doctrineColumn->getAutoincrement();
        } catch (\Exception $e) {
            // If we can't get the column, assume it's not auto-incrementing
            return false;
        }
    }
    
    /**
     * Check if a column is unsigned.
     *
     * @param string $table
     * @param string $column
     * @param string $connection
     * @return bool
     */
    protected function isUnsigned(string $table, string $column, string $connection): bool
    {
        try {
            $sm = DB::connection($connection)->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails($table);
            $doctrineColumn = $doctrineTable->getColumn($column);
            
            return $doctrineColumn->getUnsigned();
        } catch (\Exception $e) {
            // If we can't get the column, assume it's not unsigned
            return false;
        }
    }
    
    /**
     * Get the length of a column.
     *
     * @param string $table
     * @param string $column
     * @param string $connection
     * @return int|null
     */
    protected function getLength(string $table, string $column, string $connection): ?int
    {
        try {
            $sm = DB::connection($connection)->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails($table);
            $doctrineColumn = $doctrineTable->getColumn($column);
            
            return $doctrineColumn->getLength();
        } catch (\Exception $e) {
            // If we can't get the column, return null
            return null;
        }
    }
    
    /**
     * Check if a table has timestamps.
     *
     * @param array $columns
     * @return bool
     */
    protected function hasTimestamps(array $columns): bool
    {
        return in_array('created_at', $columns) && in_array('updated_at', $columns);
    }
    
    /**
     * Check if a table has soft deletes.
     *
     * @param array $columns
     * @return bool
     */
    protected function hasSoftDeletes(array $columns): bool
    {
        return in_array('deleted_at', $columns);
    }
    
    /**
     * Guess the model name from a table name.
     *
     * @param string $table
     * @return string
     */
    protected function guessModelName(string $table): string
    {
        return Str::studly(Str::singular($table));
    }
}