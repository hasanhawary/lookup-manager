<?php

namespace HasanHawary\LookupManager;

use Error;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class ModelLookupManager
{
    /**
     * Preferred name-related fields in order of priority.
     */
    private const NAME_FIELDS = [
        'display_name',
        'title',
        'label',
        'name',
        'first_name',
        'last_name',
    ];

    private $ignoredColumns = [];

    /**
     * Fetch model data according to the request definition.
     */
    public function getModels(array $request): array
    {
        $result = [];

        $tables = $request['tables'] ?? null;
        if (empty($tables) || !is_array($tables)) {
            return $this->getAllModelsMetadata();
        }

        foreach ($tables as $table) {
            $tableName = $table['name'] ?? null;

            if (!$tableName) {
                Log::warning("Skipped a table without a 'name' key in request.");
                continue; // skip if the table name is missing
            }

            try {
                $model = $this->resolveModel($tableName, $table['module'] ?? null);
                if (!$model) {
                    Log::warning("Model not found for table: {$tableName}");
                    $result[$tableName] = [];
                    continue;
                }

                // TODO [Security]: Enforce permission check before accessing this model.
                //  - Some models may require explicit permissions (e.g., 'view-users', 'view-finance').
                //  - Implement a check like: $this->authorizeModelAccess($model, $user);
                //  - Deny access if user lacks required permissions or role.

                // Determine select fields
                $select = $this->determineSelectFields($table, $model);

                // Apply scopes
                $this->applyScopes(
                    $model,
                    Arr::wrap($table['scopes'] ?? []),
                    Arr::wrap($table['values'] ?? [])
                );

                // Apply search filters
                $this->applySearch(
                    $model,
                    $table['search'] ?? null,
                );

                // Fetch data
                $withFields = $table['with'] ?? [];
                $result[$tableName] = $model->select($select)->with($withFields);

                $isPaginated = $table['paginate'] ?? false;
                $perPage = $table['per_page'] ?? 15;
                $page = $table['page'] ?? 1;
                $query = $model;

                $transform = fn ($record) => $this->transformRecord($record, $select, $withFields);

                $result[$tableName] = $isPaginated
                    ? $query->paginate($perPage, page: $page)->through($transform)->toArray()
                    : $query->get()->map($transform)->toArray();


            } catch (Exception|Error $e) {
                Log::error("Error when getting model {$tableName}: " . $e->getMessage());
                $result[$tableName] = [];
            }
        }

        return $result;
    }

    private function applyScopes(&$model, $scopes = null, $values = null): void
    {
        // Exclude root (only once, safe)
        if (
            $model instanceof Model &&
            in_array(get_class($model), config('models.root_excluded_models', []), true) &&
            method_exists($model, 'excludeRoot')
        ) {
            $model = $model->excludeRoot();
        }

        // Always apply active scope if exists
        if ($model->hasNamedScope('active')) {
            $model = $model->active();
        }

        foreach (Arr::wrap($scopes) as $key => $scope) {
            try {
                if (!$model->hasNamedScope($scope)) {
                    Log::warning(
                        "Scope '{$scope}' not found on model " . get_class($model->getModel())
                    );
                    continue;
                }

                // Determine value (allow 0 / false)
                $value = $values[$scope] ?? $values[$key] ?? null;

                $model = $value !== null
                    ? $model->$scope(...Arr::wrap($value))
                    : $model->$scope();

            } catch (\Throwable $e) {
                Log::error(
                    "Error applying scope '{$scope}' on model " .
                    get_class($model->getModel()) . ': ' . $e->getMessage()
                );
            }
        }
    }

    private function applySearch(&$model, array|string|null $search): void
    {
        if(!$search) {
            return;
        }

        $isArray = is_array($search) && isset($search['term']) && !empty($search['term']);
        $isString = is_string($search) && !empty($search);

        if(!$isString && !$isArray) {
            return;
        }

        $table = $model->getModel()->getTable();

        // Pass the actual model instance to determineSelectFields when used as fallback
        $fields = ($isArray && !empty($search['fields']))
            ? Arr::wrap($search['fields'])
            : $this->determineSelectFields(['name' => $table], $model->getModel());

        // exclude 'id' from searchable fields
        $fields = array_values(array_filter(Arr::wrap($fields), fn($f) => $f !== 'id'));

        $term = $isString ? $search : $search['term'];

        if (empty($term) || empty($fields)) {
            return;
        }

        $model = $model->where(function ($query) use ($term, $fields, $model) {
            // Get translatable fields dynamically
            $jsonFields = property_exists($model, 'translatable') ? $model->translatable : [];

            foreach ($fields as $field) {
                if (in_array($field, $jsonFields)) {
                    // For spatie/laravel-translatable, you can search any locale like this
                    foreach (config('app.supported_languages', ['ar', 'en']) as $locale) {
                        $query->orWhere($field . '->' . $locale, 'LIKE', '%' . $term . '%');
                    }
                } else {
                    $query->orWhere($field, 'LIKE', '%' . $term . '%');
                }
            }
        });

    }

    private function determineSelectFields(array $table, $model): array
    {
        $select = ['id'];

        // Get table columns safely
        $tableName = Str::plural($table['name']);
        $columns = Schema::hasTable($tableName)
            ? Schema::getColumnListing($tableName)
            : [];

        // handle forgin keys of with relations
        if (!empty($table['with'])) {
            foreach ($table['with'] as $field) {
                $relation = $model->{$field}();
                $foreignKey = $relation->getForeignKeyName();
                $table['extra'][] = $foreignKey;
                $this->ignoredColumns[] = $foreignKey;
            }
        }

        // Include extra fields if provided
        if (!empty($table['extra'])) {
            // TODO [Security]: Before merging extra fields, validate that each field is allowed to be selected.
            //  - Prevent exposing locked or restricted columns (e.g., password, api_key, tokens, salary, etc.)
            //  - Consider checking against a whitelist or permission-based access control.
            //  - Example: $this->validateSelectableColumns($table['extra'], $userPermissions);
            $extra = Arr::wrap($table['extra']);

            // Ensure extra column in list of columns
            $select = array_merge($select, array_intersect($extra, $columns));
        }

        // Select helpModelName column if defined in the model
        if (property_exists($model, 'helpModelName') && $model->helpModelName) {
            $help = $model->helpModelName;

            foreach (Arr::wrap($help) as $field) {
                if (in_array($field, $columns)) {
                    $select[] = $field;
                }
            }
        }

        // Add default name fields
        foreach (self::NAME_FIELDS as $field) {
            if (in_array($field, $columns)) {
                $select[] = $field;
                break;
            }
        }

        return array_values(array_unique($select));
    }

    private function transformRecord($record, array $select, array $withFields = []): array
    {
        $data = [
            'id' => $record->id ?? null,
        ];

        // Determine name value from available name fields
        $nameModel = $this->resolveHelpModelName($record);

        $data['name'] = $nameModel;

        // Include valid extra fields
        $extraFields = array_diff($select, array_merge(['id'], self::NAME_FIELDS, $this->ignoredColumns));

        // if we used a helpModelName field as the name, exclude it from extras to avoid duplication
        if ($nameModel) {
            $helpFields = Arr::wrap($nameModel);
            $extraFields = array_values(array_filter($extraFields, fn($f) => !in_array($f, $helpFields)));
        }


        foreach ($extraFields as $field) {
            $data[$field] = $record->{$field};
        }

        foreach ($withFields as $field) {
            $data[Str::snake($field)] = $this->transformRelationValue($record->{$field});
        }

        return $data;
    }

    private function transformRelationValue($value): mixed
    {
        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            $result = [
                'id'   => $value->id ?? null,
                'name' => $this->resolveDefaultName($value),
            ];

            // Recursively handle any already-loaded relations
            foreach ($value->getRelations() as $relationName => $relationValue) {
                $result[Str::snake($relationName)] = $this->transformRelationValue($relationValue);
            }

            return $result;
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn($item) => $this->transformRelationValue($item))->values()->all();
        }

        return $value;
    }

    private function resolveHelpModelName(Model $record): ?string
    {
        $nameModel = null;

        // check if property exists on the model instance
        try {
            $recordClass = get_class($record);
            $nameModel = $recordClass::$helpModelName ?? null;
        } catch (\Throwable $e) {
            $nameModel = null;
        }

        // If nameModel is defined and refers to one or more columns, build name using those columns' values
        if ($nameModel) {
            if (is_array($nameModel)) {
                $values = array_map(fn($col) => $record->{$col} ?? '', $nameModel);
                $nameModel = $this->resolveModelName($values);
            } else {
                $nameModel = $this->resolveModelName($record->{$nameModel} ?? '');
            }
        } else {
            $nameModel = $this->resolveDefaultName($record);
        }

        return $nameModel;
    }

    private function resolveModel(string $name, ?string $module = null): ?object
    {
        $parts = explode('.', $name);
        $modelName = array_pop($parts);

        $model = Str::studly(Str::singular($modelName));

        $folders = collect($parts)
            ->map(fn ($part) => Str::studly($part))
            ->implode('\\');

        // Base namespace
        $baseNamespace = empty($module)
            ? 'App\\Models'
            : 'Modules\\' . Str::studly($module) . '\\App\\Models';

        // Build final class path
        $modelPath = $baseNamespace . ($folders ? "\\{$folders}" : '') . "\\{$model}";

        return class_exists($modelPath) ? app($modelPath) : null;
    }

    private function resolveDefaultName($record)
    {
        return $record->name
            ?? $record->title
            ?? $record->display_name
            ?? $record->full_name
            ?? $record->label
            ?? (
            isset($record->first_name, $record->last_name)
                ? trim("{$record->first_name} {$record->last_name}")
                : null
            );
    }

    private function resolveModelName(array|string $column)
    {
        return is_array($column)
            ? implode(' ', $column)
            : $column;
    }

    private function getAllModelsMetadata(): array
    {
        $result = [];
        $modelsPath = app_path('Models');

        if (! is_dir($modelsPath)) {
            Log::warning("Models directory not found: {$modelsPath}");

            return $result;
        }

        $files = scandir($modelsPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || ! str_ends_with($file, '.php')) {
                continue;
            }

            $modelName = str_replace('.php', '', $file);
            $modelClass = "App\\Models\\{$modelName}";

            if (! class_exists($modelClass)) {
                continue;
            }

            try {
                $model = new $modelClass;

                // Skip if not an Eloquent model
                if (! method_exists($model, 'getTable')) {
                    continue;
                }

                $tableName = $model->getTable();

                // Return only table name and model class without data
                $result[] = [
                    'name' => resolveTrans($tableName, 'lookup.models', snaked:false),
                    'model' => $modelName,
                    'table' => $tableName,
                ];

            } catch (Exception|Error $e) {
                Log::error("Error processing model {$modelName}: ".$e->getMessage());

                continue;
            }
        }

        return $result;
    }
}
