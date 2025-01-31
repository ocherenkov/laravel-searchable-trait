<?php

namespace App\Traits\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SearchTrait
{

    /**
     * Scope a query that matches the given search query against all searchable
     * columns and relations.
     *
     * @param Builder $query
     * @param string|null $searchTerm
     * @param bool $matchAllColumns
     * @return Builder
     */
    public function scopeSearch(Builder $query, ?string $searchTerm = null, bool $matchAllColumns = false): Builder
    {
        if (!$searchTerm) {
            return $query;
        }
        $uppercaseSearchTerm = mb_strtoupper($searchTerm);

        return $query->where(function (Builder $subQuery) use ($uppercaseSearchTerm, $matchAllColumns) {
            $this->applyColumnSearch($subQuery, $uppercaseSearchTerm, $matchAllColumns);
            $this->applyConcatSearch($subQuery, $uppercaseSearchTerm, $matchAllColumns);
            $this->applyRelationSearch($subQuery, $uppercaseSearchTerm, $matchAllColumns);
        });
    }

    /**
     * Applies the search condition to the columns of the model.
     *
     * @param Builder $query The query builder to apply the search condition to.
     * @param string $searchTerm The search query.
     * @param bool $matchAllColumns Whether to match the search query against all columns.
     * @return void
     */
    private function applyColumnSearch(Builder $query, string $searchTerm, bool $matchAllColumns): void
    {
        foreach (self::getSearchableColumns() as $column) {
            $expression = $this->buildExpression($column);
            $this->addSearchCondition($query, $expression, $searchTerm, $matchAllColumns);
        }
    }

    /**
     * Applies the search condition to the concatenated fields of the model.
     *
     * @param Builder $query The query builder to apply the search condition to.
     * @param string $searchTerm The search query.
     * @param bool $matchAllColumns Whether to match the search query against all columns.
     * @return void
     */
    private function applyConcatSearch(Builder $query, string $searchTerm, bool $matchAllColumns): void
    {
        foreach (self::getSearchableConcatenations() as $fields) {
            $expression = $this->buildExpression($fields);
            $this->addSearchCondition($query, $expression, $searchTerm, $matchAllColumns);
        }
    }

    /**
     * Applies the search condition to the relations of the model.
     *
     * @param Builder $query The query builder to apply the search condition to.
     * @param string $searchTerm The search query.
     * @param bool $matchAllColumns Whether to match the search query against all columns.
     * @return void
     */
    private function applyRelationSearch(Builder $query, string $searchTerm, bool $matchAllColumns): void
    {
        foreach (self::getSearchableRelations() as $relation => $fields) {
            $query->orWhereHas($relation, function (Builder $subQuery) use ($searchTerm, $fields, $matchAllColumns) {
                foreach ($fields as $index => $field) {
                    $expression = $this->buildExpression($field);
                    $this->addSearchCondition($subQuery, $expression, $searchTerm, $matchAllColumns || $index === 0);
                }
            });
        }
    }

    /**
     * Builds an expression to search for a string in a field(s).
     *
     * @param array|string $fields The field(s) to search in. If an array, the fields will be concatenated with a space in between.
     * @param string $function The function to use to transform the field(s). Defaults to 'UPPER'.
     * @return Expression The expression to add to the query.
     */
    private function buildExpression(array|string $fields, string $function = 'UPPER'): Expression
    {
        $fieldsString = is_array($fields)
            ? "CONCAT_WS(' ', " . implode(', ', $fields) . ")"
            : $fields;

        return DB::raw("{$function}($fieldsString)");
    }

    /**
     * Adds a condition to the query to search for a string in a column(s) defined by
     * the expression.
     *
     * @param Builder $query
     * @param string|Expression $expression
     * @param string $search
     * @param bool $matchAll
     */
    private function addSearchCondition(
        Builder $query,
        string|Expression $expression,
        string $search,
        bool $matchAll
    ): void {
        $matchAll
            ? $query->where($expression, 'LIKE', '%' . $search . '%')
            : $query->orWhere($expression, 'LIKE', '%' . $search . '%');
    }

    /**
     * Get all searchable columns for the model.
     *
     * @return array
     */
    private static function getSearchableColumns(): array
    {
        $model = self::createModelInstance();
        $cacheKey = 'searchable_columns_' . $model->getTable();

        return Cache::rememberForever($cacheKey, static function () use ($model) {
            $columns = $model->searchable ?? Schema::getColumnListing($model->getTable());
            $ignoredColumns = [
                $model->getKeyName(),
                $model->getUpdatedAtColumn(),
                $model->getCreatedAtColumn(),
            ];

            return array_diff($columns, $model->getHidden(), $ignoredColumns);
        });
    }

    /**
     * Get all searchable concatenations.
     *
     * @return array
     */
    private static function getSearchableConcatenations(): array
    {
        return self::createModelInstance()->searchableConcat ?? [];
    }

    /**
     * Get all searchable relations for the model.
     *
     * @return array
     */
    private static function getSearchableRelations(): array
    {
        return self::createModelInstance()->searchableRelations ?? [];
    }

    /**
     * Helper method to create a static model instance.
     *
     * @return static
     */
    private static function createModelInstance(): static
    {
        return new static();
    }
}
