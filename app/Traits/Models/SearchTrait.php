<?php

namespace App\Traits\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SearchTrait
{
    /**
     * @param Builder $query
     * @param string|null $search
     * @param bool $matchAllColumns
     * @return Builder
     */
    public function scopeSearch(Builder $query, ?string $search = null, bool $matchAllColumns = false): Builder
    {
        if (!$search) {
            return $query;
        }
        $search = strtolower($search);
        return $query->where(function ($query) use ($search, $matchAllColumns) {
            // Handle local model searchable columns
            foreach (static::getSearchableColumns() as $column) {
                $lowerColumn = DB::raw("LOWER($column)");
                if ($matchAllColumns) {
                    $query->where($lowerColumn, 'LIKE', '%' . $search . '%');
                } else {
                    $query->orWhere($lowerColumn, 'LIKE', '%' . $search . '%');
                }
            }
            // Handle concatenated fields in the model
            foreach (static::getSearchableConcatenations() as $concatFields) {
                $concatExpression = DB::raw("LOWER(CONCAT_WS(' ', " . implode(', ', $concatFields) . "))");
                if ($matchAllColumns) {
                    $query->where($concatExpression, 'LIKE', '%' . $search . '%');
                } else {
                    $query->orWhere($concatExpression, 'LIKE', '%' . $search . '%');
                }
            }

            // Handle related models
            foreach (static::getSearchableRelations() as $relation => $fields) {
                $query->orWhereHas($relation, function (Builder $subQuery) use ($search, $fields, $matchAllColumns) {
                    $isFirstField = true;
                    foreach ($fields as $field) {
                        if (is_array($field)) {
                            // Handle concatenated fields in the related model
                            $concatExpression = DB::raw("LOWER(CONCAT_WS(' ', " . implode(', ', $field) . "))");
                            if ($matchAllColumns || $isFirstField) {
                                $subQuery->where($concatExpression, 'LIKE', '%' . $search . '%');
                                $isFirstField = false;
                            } else {
                                $subQuery->orWhere($concatExpression, 'LIKE', '%' . $search . '%');
                            }
                        } else {
                            // Handle individual fields in the related model
                            $lowerField = DB::raw("LOWER($field)");
                            if ($matchAllColumns || $isFirstField) {
                                $subQuery->where($lowerField, 'LIKE', '%' . $search . '%');
                                $isFirstField = false;
                            } else {
                                $subQuery->orWhere($lowerField, 'LIKE', '%' . $search . '%');
                            }
                        }
                    }
                });
            }
        });
    }

    /**
     * Get all searchable columns
     *
     * @return array
     */
    public static function getSearchableColumns(): array
    {
        $model = new static();
        $cacheKey = 'searchable_columns_' . $model->getTable();

        return Cache::rememberForever($cacheKey, static function () use ($model) {
            $columns = $model->searchable;

            if (empty($columns)) {
                $columns = Schema::getColumnListing($model->getTable());
                $ignoredColumns = [
                    $model->getKeyName(),
                    $model->getUpdatedAtColumn(),
                    $model->getCreatedAtColumn(),
                ];
                $columns = array_diff($columns, $model->getHidden(), $ignoredColumns);
            }

            return $columns;
        });
    }

    /**
     * Get all searchable concatenations
     *
     * @return array
     */
    public static function getSearchableConcatenations(): array
    {
        $model = new static();
        return $model->searchableConcat ?? [];
    }

    /**
     * Get all searchable relations for the model.
     *
     * @return array
     */
    public static function getSearchableRelations(): array
    {
        $model = new static();
        return $model->searchableRelations ?? [];
    }
}
