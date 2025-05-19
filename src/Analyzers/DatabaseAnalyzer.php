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
        // Get all tables using raw SQL query
        $driver = DB::connection($connection)->getDriverName();

        // Different SQL for different database drivers
        switch ($driver) {
            case 'mysql':
                $tables = DB::connection($connection)
                    ->select('SHOW TABLES');
                $tables = array_map(function ($table) {
                    return reset($table);
                }, $tables);
                break;
            case 'pgsql':
                $tables = DB::connection($connection)
                    ->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
                $tables = array_map(function ($table) {
                    return $table->table_name;
                }, $tables);
                break;
            case 'sqlite':
                $tables = DB::connection($connection)
                    ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $tables = array_map(function ($table) {
                    return $table->name;
                }, $tables);
                break;
            case 'sqlsrv':
                $tables = DB::connection($connection)
                    ->select("SELECT table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE'");
                $tables = array_map(function ($table) {
                    return $table->table_name;
                }, $tables);
                break;
            default:
                throw new \Exception("Unsupported database driver: {$driver}");
        }

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
            $driver = DB::connection($connection)->getDriverName();

            // Different SQL for different database drivers
            switch ($driver) {
                case 'mysql':
                    $result = DB::connection($connection)
                        ->select("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
                    if (!empty($result)) {
                        return $result[0]->Column_name;
                    }
                    break;
                case 'pgsql':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT a.attname
                            FROM pg_index i
                            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                            WHERE i.indrelid = '{$table}'::regclass
                            AND i.indisprimary
                        ");
                    if (!empty($result)) {
                        return $result[0]->attname;
                    }
                    break;
                case 'sqlite':
                    $result = DB::connection($connection)
                        ->select("PRAGMA table_info('{$table}')");
                    foreach ($result as $column) {
                        if ($column->pk) {
                            return $column->name;
                        }
                    }
                    break;
                case 'sqlsrv':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT column_name
                            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                            WHERE OBJECTPROPERTY(OBJECT_ID(constraint_name), 'IsPrimaryKey') = 1
                            AND table_name = '{$table}'
                        ");
                    if (!empty($result)) {
                        return $result[0]->column_name;
                    }
                    break;
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
            $driver = DB::connection($connection)->getDriverName();

            // Different SQL for different database drivers
            switch ($driver) {
                case 'mysql':
                    $foreignKeys = DB::connection($connection)
                        ->select("
                            SELECT
                                COLUMN_NAME as 'column',
                                REFERENCED_TABLE_NAME as 'foreign_table',
                                REFERENCED_COLUMN_NAME as 'foreign_column'
                            FROM
                                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                            WHERE
                                TABLE_SCHEMA = DATABASE()
                                AND TABLE_NAME = '{$table}'
                                AND REFERENCED_TABLE_NAME IS NOT NULL
                        ");

                    foreach ($foreignKeys as $fk) {
                        $result[$fk->column] = [
                            'localColumn' => $fk->column,
                            'foreignTable' => $fk->foreign_table,
                            'foreignColumn' => $fk->foreign_column,
                            'onDelete' => null, // Not easily available via SQL
                            'onUpdate' => null, // Not easily available via SQL
                        ];
                    }
                    break;

                case 'pgsql':
                    $foreignKeys = DB::connection($connection)
                        ->select("
                            SELECT
                                kcu.column_name as column,
                                ccu.table_name AS foreign_table,
                                ccu.column_name AS foreign_column
                            FROM
                                information_schema.table_constraints AS tc
                                JOIN information_schema.key_column_usage AS kcu
                                    ON tc.constraint_name = kcu.constraint_name
                                JOIN information_schema.constraint_column_usage AS ccu
                                    ON ccu.constraint_name = tc.constraint_name
                            WHERE tc.constraint_type = 'FOREIGN KEY'
                                AND tc.table_name = '{$table}'
                        ");

                    foreach ($foreignKeys as $fk) {
                        $result[$fk->column] = [
                            'localColumn' => $fk->column,
                            'foreignTable' => $fk->foreign_table,
                            'foreignColumn' => $fk->foreign_column,
                            'onDelete' => null, // Not easily available via SQL
                            'onUpdate' => null, // Not easily available via SQL
                        ];
                    }
                    break;

                case 'sqlite':
                    // SQLite requires parsing the table creation SQL
                    $createTableSql = DB::connection($connection)
                        ->select("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'");

                    if (!empty($createTableSql)) {
                        $sql = $createTableSql[0]->sql;
                        preg_match_all('/FOREIGN KEY\s*\(\s*([^\)]+)\s*\)\s*REFERENCES\s*([^\(]+)\s*\(\s*([^\)]+)\s*\)/i', $sql, $matches, PREG_SET_ORDER);

                        foreach ($matches as $match) {
                            $localColumn = trim($match[1]);
                            $foreignTable = trim($match[2]);
                            $foreignColumn = trim($match[3]);

                            $result[$localColumn] = [
                                'localColumn' => $localColumn,
                                'foreignTable' => $foreignTable,
                                'foreignColumn' => $foreignColumn,
                                'onDelete' => null, // Not easily available via SQL
                                'onUpdate' => null, // Not easily available via SQL
                            ];
                        }
                    }
                    break;

                case 'sqlsrv':
                    $foreignKeys = DB::connection($connection)
                        ->select("
                            SELECT
                                fk.name AS constraint_name,
                                OBJECT_NAME(fk.parent_object_id) AS table_name,
                                c1.name AS column_name,
                                OBJECT_NAME(fk.referenced_object_id) AS foreign_table_name,
                                c2.name AS foreign_column_name
                            FROM
                                sys.foreign_keys fk
                                INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
                                INNER JOIN sys.columns c1 ON fkc.parent_column_id = c1.column_id AND fkc.parent_object_id = c1.object_id
                                INNER JOIN sys.columns c2 ON fkc.referenced_column_id = c2.column_id AND fkc.referenced_object_id = c2.object_id
                            WHERE
                                OBJECT_NAME(fk.parent_object_id) = '{$table}'
                        ");

                    foreach ($foreignKeys as $fk) {
                        $result[$fk->column_name] = [
                            'localColumn' => $fk->column_name,
                            'foreignTable' => $fk->foreign_table_name,
                            'foreignColumn' => $fk->foreign_column_name,
                            'onDelete' => null, // Not easily available via SQL
                            'onUpdate' => null, // Not easily available via SQL
                        ];
                    }
                    break;
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
            $driver = DB::connection($connection)->getDriverName();

            // Different SQL for different database drivers
            switch ($driver) {
                case 'mysql':
                    $indexes = DB::connection($connection)
                        ->select("SHOW INDEX FROM `{$table}`");

                    $currentIndex = null;
                    foreach ($indexes as $index) {
                        $indexName = $index->Key_name;

                        if (!isset($result[$indexName])) {
                            $result[$indexName] = [
                                'name' => $indexName,
                                'columns' => [],
                                'unique' => $index->Non_unique == 0,
                                'primary' => $indexName === 'PRIMARY',
                            ];
                        }

                        $result[$indexName]['columns'][] = $index->Column_name;
                    }
                    break;

                case 'pgsql':
                    $indexes = DB::connection($connection)
                        ->select("
                            SELECT
                                i.relname as index_name,
                                a.attname as column_name,
                                ix.indisunique as is_unique,
                                ix.indisprimary as is_primary
                            FROM
                                pg_class t,
                                pg_class i,
                                pg_index ix,
                                pg_attribute a
                            WHERE
                                t.oid = ix.indrelid
                                AND i.oid = ix.indexrelid
                                AND a.attrelid = t.oid
                                AND a.attnum = ANY(ix.indkey)
                                AND t.relkind = 'r'
                                AND t.relname = '{$table}'
                            ORDER BY
                                i.relname, a.attnum
                        ");

                    foreach ($indexes as $index) {
                        $indexName = $index->index_name;

                        if (!isset($result[$indexName])) {
                            $result[$indexName] = [
                                'name' => $indexName,
                                'columns' => [],
                                'unique' => (bool)$index->is_unique,
                                'primary' => (bool)$index->is_primary,
                            ];
                        }

                        $result[$indexName]['columns'][] = $index->column_name;
                    }
                    break;

                case 'sqlite':
                    $indexes = DB::connection($connection)
                        ->select("PRAGMA index_list('{$table}')");

                    foreach ($indexes as $index) {
                        $indexName = $index->name;
                        $indexInfo = DB::connection($connection)
                            ->select("PRAGMA index_info('{$indexName}')");

                        $columns = [];
                        foreach ($indexInfo as $column) {
                            $columns[] = $column->name;
                        }

                        $result[$indexName] = [
                            'name' => $indexName,
                            'columns' => $columns,
                            'unique' => (bool)$index->unique,
                            'primary' => $indexName === 'sqlite_autoindex_' . $table . '_1', // SQLite primary key naming convention
                        ];
                    }
                    break;

                case 'sqlsrv':
                    $indexes = DB::connection($connection)
                        ->select("
                            SELECT
                                i.name AS index_name,
                                c.name AS column_name,
                                i.is_unique,
                                i.is_primary_key
                            FROM
                                sys.indexes i
                                INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                                INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
                                INNER JOIN sys.tables t ON i.object_id = t.object_id
                            WHERE
                                t.name = '{$table}'
                            ORDER BY
                                i.name, ic.key_ordinal
                        ");

                    foreach ($indexes as $index) {
                        $indexName = $index->index_name;

                        if (!isset($result[$indexName])) {
                            $result[$indexName] = [
                                'name' => $indexName,
                                'columns' => [],
                                'unique' => (bool)$index->is_unique,
                                'primary' => (bool)$index->is_primary_key,
                            ];
                        }

                        $result[$indexName]['columns'][] = $index->column_name;
                    }
                    break;
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
            $driver = DB::connection($connection)->getDriverName();

            // Different SQL for different database drivers
            switch ($driver) {
                case 'mysql':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT IS_NULLABLE
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = '{$table}'
                            AND COLUMN_NAME = '{$column}'
                        ");

                    if (!empty($result)) {
                        return strtoupper($result[0]->IS_NULLABLE) === 'YES';
                    }
                    break;

                case 'pgsql':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT is_nullable
                            FROM information_schema.columns
                            WHERE table_name = '{$table}'
                            AND column_name = '{$column}'
                        ");

                    if (!empty($result)) {
                        return strtoupper($result[0]->is_nullable) === 'YES';
                    }
                    break;

                case 'sqlite':
                    $result = DB::connection($connection)
                        ->select("PRAGMA table_info('{$table}')");

                    foreach ($result as $col) {
                        if ($col->name === $column) {
                            return $col->notnull == 0;
                        }
                    }
                    break;

                case 'sqlsrv':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT IS_NULLABLE
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_NAME = '{$table}'
                            AND COLUMN_NAME = '{$column}'
                        ");

                    if (!empty($result)) {
                        return strtoupper($result[0]->IS_NULLABLE) === 'YES';
                    }
                    break;
            }

            // Default fallback
            return false;
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
            $driver = DB::connection($connection)->getDriverName();

            // Different SQL for different database drivers
            switch ($driver) {
                case 'mysql':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT COLUMN_DEFAULT
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = '{$table}'
                            AND COLUMN_NAME = '{$column}'
                        ");

                    if (!empty($result)) {
                        return $result[0]->COLUMN_DEFAULT;
                    }
                    break;

                case 'pgsql':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT column_default
                            FROM information_schema.columns
                            WHERE table_name = '{$table}'
                            AND column_name = '{$column}'
                        ");

                    if (!empty($result)) {
                        return $result[0]->column_default;
                    }
                    break;

                case 'sqlite':
                    $result = DB::connection($connection)
                        ->select("PRAGMA table_info('{$table}')");

                    foreach ($result as $col) {
                        if ($col->name === $column) {
                            return $col->dflt_value;
                        }
                    }
                    break;

                case 'sqlsrv':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT COLUMN_DEFAULT
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_NAME = '{$table}'
                            AND COLUMN_NAME = '{$column}'
                        ");

                    if (!empty($result)) {
                        return $result[0]->COLUMN_DEFAULT;
                    }
                    break;
            }

            // Default fallback
            return null;
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
            $driver = DB::connection($connection)->getDriverName();

            // Different SQL for different database drivers
            switch ($driver) {
                case 'mysql':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT EXTRA
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = '{$table}'
                            AND COLUMN_NAME = '{$column}'
                        ");

                    if (!empty($result)) {
                        return stripos($result[0]->EXTRA, 'auto_increment') !== false;
                    }
                    break;

                case 'pgsql':
                    // In PostgreSQL, we need to check if the column is a serial or bigserial type
                    // or if it's associated with a sequence
                    $result = DB::connection($connection)
                        ->select("
                            SELECT pg_get_serial_sequence('{$table}', '{$column}') IS NOT NULL AS is_auto_increment
                        ");

                    if (!empty($result)) {
                        return (bool)$result[0]->is_auto_increment;
                    }
                    break;

                case 'sqlite':
                    // In SQLite, check if the column is the primary key and is of type INTEGER
                    $result = DB::connection($connection)
                        ->select("PRAGMA table_info('{$table}')");

                    foreach ($result as $col) {
                        if ($col->name === $column) {
                            // In SQLite, a column is auto-increment if it's the primary key and of type INTEGER
                            return $col->pk == 1 && strtoupper($col->type) === 'INTEGER';
                        }
                    }
                    break;

                case 'sqlsrv':
                    // In SQL Server, check if the column has an identity constraint
                    $result = DB::connection($connection)
                        ->select("
                            SELECT COLUMNPROPERTY(OBJECT_ID('{$table}'), '{$column}', 'IsIdentity') AS is_identity
                        ");

                    if (!empty($result)) {
                        return (bool)$result[0]->is_identity;
                    }
                    break;
            }

            // Default fallback
            return false;
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
            $driver = DB::connection($connection)->getDriverName();

            // Different SQL for different database drivers
            switch ($driver) {
                case 'mysql':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT COLUMN_TYPE
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = '{$table}'
                            AND COLUMN_NAME = '{$column}'
                        ");

                    if (!empty($result)) {
                        return stripos($result[0]->COLUMN_TYPE, 'unsigned') !== false;
                    }
                    break;

                case 'pgsql':
                    // PostgreSQL doesn't have unsigned types, but we can check if the column has a check constraint
                    // that ensures the value is >= 0
                    $result = DB::connection($connection)
                        ->select("
                            SELECT pg_get_constraintdef(c.oid) as constraint_def
                            FROM pg_constraint c
                            JOIN pg_namespace n ON n.oid = c.connamespace
                            JOIN pg_class t ON t.oid = c.conrelid
                            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(c.conkey)
                            WHERE n.nspname = current_schema()
                            AND t.relname = '{$table}'
                            AND a.attname = '{$column}'
                            AND c.contype = 'c'
                        ");

                    foreach ($result as $constraint) {
                        if (preg_match('/\(\(' . preg_quote($column) . ' >= 0\)\)/', $constraint->constraint_def)) {
                            return true;
                        }
                    }
                    return false;

                case 'sqlite':
                    // SQLite doesn't have unsigned types
                    return false;

                case 'sqlsrv':
                    // SQL Server doesn't have unsigned types
                    return false;
            }

            // Default fallback
            return false;
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
            $driver = DB::connection($connection)->getDriverName();

            // Different SQL for different database drivers
            switch ($driver) {
                case 'mysql':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT CHARACTER_MAXIMUM_LENGTH
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = '{$table}'
                            AND COLUMN_NAME = '{$column}'
                        ");

                    if (!empty($result) && $result[0]->CHARACTER_MAXIMUM_LENGTH !== null) {
                        return (int)$result[0]->CHARACTER_MAXIMUM_LENGTH;
                    }

                    // For numeric types, check NUMERIC_PRECISION
                    $result = DB::connection($connection)
                        ->select("
                            SELECT NUMERIC_PRECISION
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = '{$table}'
                            AND COLUMN_NAME = '{$column}'
                            AND DATA_TYPE IN ('int', 'bigint', 'mediumint', 'smallint', 'tinyint')
                        ");

                    if (!empty($result) && $result[0]->NUMERIC_PRECISION !== null) {
                        return (int)$result[0]->NUMERIC_PRECISION;
                    }
                    break;

                case 'pgsql':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT character_maximum_length
                            FROM information_schema.columns
                            WHERE table_name = '{$table}'
                            AND column_name = '{$column}'
                        ");

                    if (!empty($result) && $result[0]->character_maximum_length !== null) {
                        return (int)$result[0]->character_maximum_length;
                    }
                    break;

                case 'sqlite':
                    // SQLite doesn't store column length information in a standard way
                    // We need to parse the table creation SQL
                    $createTableSql = DB::connection($connection)
                        ->select("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'");

                    if (!empty($createTableSql)) {
                        $sql = $createTableSql[0]->sql;

                        // Try to extract the length from the column definition
                        if (preg_match('/[`"\[]?' . preg_quote($column) . '[`"\]]?\s+\w+\((\d+)\)/i', $sql, $matches)) {
                            return (int)$matches[1];
                        }
                    }
                    break;

                case 'sqlsrv':
                    $result = DB::connection($connection)
                        ->select("
                            SELECT CHARACTER_MAXIMUM_LENGTH
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_NAME = '{$table}'
                            AND COLUMN_NAME = '{$column}'
                        ");

                    if (!empty($result) && $result[0]->CHARACTER_MAXIMUM_LENGTH !== null) {
                        return (int)$result[0]->CHARACTER_MAXIMUM_LENGTH;
                    }
                    break;
            }

            // Default fallback
            return null;
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
