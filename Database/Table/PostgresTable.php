<?php

/**
 * Copyright 2021 Jeremy Presutti <Jeremy@Presutti.us>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Feast\Database\Table;

use Feast\Database\Column\Postgres\Boolean;
use Feast\Database\Column\Postgres\Bytea;
use Feast\Database\Column\Postgres\BigInt;
use Feast\Database\Column\Column;
use Feast\Database\Column\Postgres\Integer;
use Feast\Database\Column\Postgres\SmallInt;
use Feast\Database\Column\Postgres\Text;
use Feast\Exception\InvalidArgumentException;
use Feast\Exception\ServerFailureException;

class PostgresTable extends Table
{

    /**
     * Drop specified column on the table.
     *
     * @param string $column
     */
    public function dropColumn(string $column): void
    {
        $this->connection->rawQuery('ALTER TABLE ' . $this->name . ' DROP COLUMN ' . $column);
    }

    /**
     * Drop table.
     */
    public function drop(): void
    {
        $this->connection->rawQuery('DROP TABLE IF EXISTS ' . $this->name);
    }

    /**
     * Get DDL object.
     *
     * @return Ddl
     */
    public function getDdl(): Ddl
    {
        $return = 'CREATE TABLE IF NOT EXISTS ' . $this->name . '(';
        $columns = [];
        $bindings = [];
        /** @var Column $column */
        foreach ($this->columns as $column) {
            $columns[] = $this->getColumnForDdl($column, $bindings);
        }
        if (isset($this->primaryKeyName)) {
            $columns[] = 'PRIMARY KEY (' . $this->primaryKeyName . ')';
        }

        /** @var array{name:string,columns:list<string>} $index */
        foreach ($this->indexes as $index) {
            $columns[] = 'index ' . $index['name'] . ' (' . implode(',', $index['columns']) . ')';
        }
        $return .= implode(',' . "\n", $columns) . ')';

        return new Ddl($return, $bindings);
    }

    protected function getColumnForDdl(Column $column, array &$bindings): string
    {
        $string = $column->getName() . ' ' . $column->getType();
        $string .= $column->getLength() !== null ? '(' . (string)$column->getLength() : '';
        $string .= $column->getLength() !== null ? (
        $column->getDecimal() !== null ?
            ',' . (string)$column->getDecimal() .
            ')' : ')') : '';
        $string .= $column->getUnsignedText();
        $string .= $column->isNullable() ? ' null' : ' not null';
        $string .= $this->getDefaultAsBindingOrText($column, $bindings);
        return $string;
    }

    protected function getDefaultAsBindingOrText(Column $column, array &$bindings): string
    {
        $default = $column->getDefault();
        if ($default !== null) {
            if (in_array(strtolower($column->getType()), ['datetime', 'timestamp']) && strtolower(
                    $default
                ) === 'current_timestamp') {
                return ' DEFAULT CURRENT_TIMESTAMP';
            }
            $return = ' DEFAULT ?';
            $bindings[] = $default;

            return $return;
        }
        return '';
    }

    /**
     * Add new Int column.
     *
     * @param string $name
     * @param bool $unsigned
     * @param bool $nullable - ignored for postgres
     * @param int|null $default
     * @param positive-int $length - ignored for postgres
     * @return static
     * @throws ServerFailureException
     */
    public function int(
        string $name,
        bool $unsigned = false,
        bool $nullable = false,
        ?int $default = null,
        int $length = 11
    ): static {
        if ($unsigned) {
            throw new InvalidArgumentException('Postgres does not support unsigned integers');
        }
        $this->columns[] = new Integer($name, $nullable, $default);

        return $this;
    }

    /**
     * Add new TinyInt column.
     *
     * @param string $name
     * @param bool $unsigned - ignored for postgres
     * @param positive-int $length - ignored for postgres
     * @param bool $nullable
     * @param int|null $default
     * @return static
     * @throws ServerFailureException
     */
    public function tinyInt(
        string $name,
        bool $unsigned = false,
        int $length = 4,
        bool $nullable = false,
        ?int $default = null
    ): static {
        return $this->smallInt($name, $unsigned, $length, $nullable, $default);
    }

    /**
     * Add new MediumInt column.
     *
     * @param string $name
     * @param bool $unsigned - ignored for postgres
     * @param positive-int $length - ignored for postgres
     * @param bool $nullable
     * @param int|null $default
     * @return static
     * @throws ServerFailureException
     */
    public function mediumInt(
        string $name,
        bool $unsigned = false,
        int $length = 4,
        bool $nullable = false,
        ?int $default = null
    ): static {
        return $this->bigInt($name, $unsigned, $length, $nullable, $default);
    }

    /**
     * Add new SmallInt column.
     *
     * @param string $name
     * @param bool $unsigned - ignored for postgres
     * @param positive-int $length - ignored for postgres
     * @param bool $nullable
     * @param int|null $default
     * @return static
     * @throws ServerFailureException
     */
    public function smallInt(
        string $name,
        bool $unsigned = false,
        int $length = 6,
        bool $nullable = false,
        ?int $default = null
    ): static {
        if ($unsigned) {
            throw new InvalidArgumentException('Postgres does not support unsigned integers');
        }
        $this->columns[] = new SmallInt($name, $nullable, $default);

        return $this;
    }

    /**
     * Add new BigInt column.
     *
     * @param string $name
     * @param bool $unsigned - ignored for postgres
     * @param positive-int $length - ignored for postgres
     * @param bool $nullable
     * @param int|null $default
     * @return static
     * @throws ServerFailureException
     */
    public function bigInt(
        string $name,
        bool $unsigned = false,
        int $length = 20,
        bool $nullable = false,
        ?int $default = null
    ): static {
        if ($unsigned) {
            throw new InvalidArgumentException('Postgres does not support unsigned integers');
        }
        $this->columns[] = new BigInt($name, $nullable, $default);

        return $this;
    }

    public function blob(string $name, int $length = 65535, bool $nullable = false): static
    {
        trigger_error('Using bytea with no length for blob', E_USER_NOTICE);
        return $this->bytea($name, $nullable);
    }

    public function mediumBlob(string $name, int $length = 65535, bool $nullable = false): static
    {
        trigger_error('Using bytea with no length for blob', E_USER_NOTICE);
        return $this->bytea($name, $nullable);
    }

    public function longBlob(string $name, int $length = 65535, bool $nullable = false): static
    {
        trigger_error('Using bytea with no length for blob', E_USER_NOTICE);
        return $this->bytea($name, $nullable);
    }

    public function tinyBlob(string $name, int $length = 65535, bool $nullable = false): static
    {
        trigger_error('Using bytea with no length for blob', E_USER_NOTICE);
        return $this->bytea($name, $nullable);
    }

    public function dateTime(string $name, ?string $default = null, bool $nullable = false): static
    {
        trigger_error('Using timestamp for datetime', E_USER_NOTICE);
        return $this->timestamp($name, $default,$nullable);
    }
    
    public function bytea(string $name, bool $nullable = false): static
    {
        $this->columns[] = new Bytea($name, $nullable);

        return $this;
    }

    /**
     * Add serial column and mark as primary key.
     *
     * @param string $column
     * @return static
     * @throws ServerFailureException
     */
    public function serial(string $column): static
    {
        $this->column($column, 'serial');
        $this->primary($column);
        $this->primaryKeyAutoIncrement = true;

        return $this;
    }

    public function autoIncrement(string $column, int $length = 11): static
    {
        return $this->serial($column);
    }

    public function tinyText(string $name, int $length = 255, bool $nullable = false): static
    {
        return $this->text($name, $length, $nullable);
    }

    public function mediumText(string $name, int $length = 255, bool $nullable = false): static
    {
        return $this->text($name, $length, $nullable);
    }

    public function longText(string $name, int $length = 255, bool $nullable = false): static
    {
        return $this->text($name, $length, $nullable);
    }
    
    public function boolean(string $name, ?bool $default = null, bool $nullable = false): static
    {
        $this->columns[] = new Boolean($name, $nullable, $default);
        
        return $this;
    }

    /**
     * Add new Text column.
     *
     * @param string $name
     * @param positive-int $length
     * @param bool $nullable
     * @return static
     * @throws ServerFailureException
     */
    public function text(string $name, int $length = 65535, bool $nullable = false): static
    {
        $this->columns[] = new Text($name, $nullable);

        return $this;
    }
}
