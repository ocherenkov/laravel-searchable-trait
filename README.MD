# SearchTrait for Laravel

The `SearchTrait` is a reusable trait for **Laravel Eloquent models** that provides powerful and flexible search capabilities. With this trait, you can implement dynamic, case-insensitive, and multi-column searches with support for related models and concatenated fields out of the box.

---

## Features

- **Search in columns, concatenated fields, and relations:** Allows for flexible searches across model fields, combined fields, and associated models.
- **Case-insensitive matching:** Uses SQL's `UPPER()` function to ensure consistent matching.
- **Automatic column detection:** Searches all table fields except:
  - Primary key (`id` or other defined key).
  - Timestamp columns (`created_at`, `updated_at`).
  - Hidden fields in `$hidden` property.
- **Customizable search logic:** Choose between `OR` (default) or `AND` logic for condition grouping.
- **Performance optimization with caching:** Automatically caches detected searchable columns for better performance.

---

## Installation

1. Include the `SearchTrait.php` file into your project under an appropriate location such as `app/Traits/Models/`.
2. Add the trait to a model where you want search capabilities.

---

## Configuration

To enable the trait for a model, simply include it and define any optional configurations.

### 1. **Basic Model Example**
Here's an example of a model using the `SearchTrait`:
```php
<?php

namespace App\Models;

use App\Traits\Models\SearchTrait;
use Illuminate\Database\Eloquent\Model;

class Widget extends Model
{
    use SearchTrait;

    // Specify columns to search (optional)
    protected array $searchable = ['location', 'content'];

    // Define concatenated fields (optional)
    protected array $searchableConcat = [
        ['first_name', 'last_name'],
    ];

    // Define relationships for search (optional)
    protected array $searchableRelations = [
        'tags' => ['name'],
        'category' => ['title'],
    ];
}
```

---

### 2. **Automatic Column Detection**
If you don’t specify `$searchable` fields in your model, the trait will automatically determine searchable columns by fetching all table columns **except**:
- `id` (primary key).
- Timestamps (`created_at`, `updated_at`).
- Hidden fields (`$hidden` property in the model).

Example:
```php
class Widget extends Model
{
    use SearchTrait;

    // No $searchable defined; columns are auto-detected
}
```
---

## Additional Configuration

### Performance Considerations

Since `SearchTrait` uses database functions like `UPPER()` and `CONCAT_WS`, consider the following for better performance:
1. Create database indexes on fields most used in searches (e.g., `UPPER(location)` or combined indexes for concatenations).
2. Cache column detection using Laravel's configuration cache:
  - Use `artisan config:cache` to minimize repeat calculations for column listings.
3. Leverage Laravel's built-in query profiling for heavy queries.

To clear cached searchable columns:
```php
Cache::forget('searchable_columns_WIDGET_TABLE_NAME');
```
Replace `WIDGET_TABLE_NAME` with the actual table name.


---

## Usage

### Basic Search
Search strings across model fields using `search()`.

```php
use App\Models\Widget;

// Search across 'location', 'content', and other defined fields
$results = Widget::search('office')->get();
```

### `AND` vs `OR` Logic
Control how the search applies conditions with the `$matchAllColumns` flag:
- **OR (default)**: Match any column.
- **AND**: Match all columns.

```php
// Match in any column (default logic)
$results = Widget::search('office')->get();

// Match in all columns (AND logic)
$results = Widget::search('office', true)->get();
```

### Search in Related Models
Define related models in `$searchableRelations` to include their fields in the search.

```php
$results = Widget::search('tag_name')->get();
```

### Search Concatenated Fields
Search over combined fields by defining concatenations in `$searchableConcat`.

Example:
```php
protected array $searchableConcat = [
    ['first_name', 'last_name'],  // Combine first and last names for searching
];
```

---

## Advanced Examples

### Search with Relations
Suppose you have `Widget` with relationships like `tags` and `category`:
```php
protected array $searchableRelations = [
    'tags' => ['name'],        // Search `name` in `tags` relation
    'category' => ['title'],   // Search `title` in `category` relation
];
```
Perform a search:
```php
$results = Widget::search('example tag')->get();
```

### Concatenated Field Search
Combine multiple columns into a single searchable field:
```php
protected array $searchableConcat = [
    ['first_name', 'last_name'],  // Will search 'John Doe' as a combined string
];
```
Perform a search:
```php
$results = Widget::search('john doe')->get();
```

### Combine Search and Filters
You can also chain the `search` scope with other query features:
```php
$results = Widget::search('office')
    ->where('status', 'active') // Add additional query filters
    ->get();
```

---

## SQL Queries Generated

Here's an example of an SQL query generated by `search()`:

### Local Columns Search
Search fields such as `location` and `content`:
```sql
SELECT * FROM widgets
WHERE UPPER(location) LIKE '%OFFICE%'
   OR UPPER(content) LIKE '%OFFICE%';
```

### Concatenated Fields
Search combined first and last names:
```sql
SELECT * FROM widgets
WHERE UPPER(CONCAT_WS(' ', first_name, last_name)) LIKE '%JOHN DOE%';
```

### Related Models
Search across tags and category relations:
```sql
SELECT * FROM widgets
WHERE EXISTS (
    SELECT * FROM tags
    WHERE widgets.id = tags.widget_id
    AND UPPER(tags.name) LIKE '%EXAMPLE%')
OR EXISTS (
    SELECT * FROM categories
    WHERE widgets.category_id = categories.id
    AND UPPER(categories.title) LIKE '%EXAMPLE%');
```

---

## Improvement Suggestions

- **Tuning Performance**:
    - Enable query profiling and indexing for fields frequently used in searches.
    - Cache heavy relationship queries with `withCount()` or eager loading.
- **Adding Custom Strategies**:
  Extend this trait to allow custom search types for specific fields, such as `Exact Match`, `LIKE`, or `Full-Text`.

---

## Testing

It's critical to test your searches under expected scenarios. You can use Laravel's `PHPUnit` testing framework to write feature tests:
```php
namespace Tests\Feature;

use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function testBasicSearch()
    {
        Widget::create(['location' => 'Office', 'content' => 'Meeting Room']);
        Widget::create(['location' => 'Warehouse', 'content' => 'Storage']);

        $results = Widget::search('office')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Office', $results->first()->location);
    }

    public function testSearchWithRelations()
    {
        // Add test cases to cover related model searches
    }
}
```

---

## Contribution

If you'd like to contribute to this project, feel free to fork the repository, create a new branch, and submit a PR with your improvements. Contributions are welcome!

Steps to contribute:
1. Fork the repo.
2. Create a feature branch (`feature/my-new-feature`).
3. Submit a pull request with a descriptive explanation.

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## Author

- **Oleh Cherenkov**  
  Feel free to reach out or submit feedback via GitHub issues or pull requests.