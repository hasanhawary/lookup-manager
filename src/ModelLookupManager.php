<?php

namespace HasanHawary\LookupManager;

use App\Models\Role;
use App\Models\User;
use Error;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

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

    /**
     * Fetch model data according to the request definition.
     */
    public function getModels(array $request): array
    {
        $result = [];

        $tables = $request['tables'] ?? null;
        if (empty($tables) || !is_array($tables)) {
            throw new RuntimeException("The 'tables' key is required and must be a non-empty array.");
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
                $select = $this->determineSelectFields($table);

                $this->applyScopes(
                    $model,
                    Arr::wrap($table['scopes'] ?? []),
                    Arr::wrap($table['values'] ?? [])
                );

                // Fetch data
                $result[$tableName] = $model->select($select)
                    ->get()
                    ->transform(fn($record) => $this->transformRecord($record, $select))
                    ->toArray();

            } catch (Exception|Error $e) {
                Log::error("Error when getting model {$tableName}: " . $e->getMessage());
                $result[$tableName] = [];
            }
        }

        return $result;
    }

    private function applyScopes(&$model, $scopes = null, $values = null): void
    {
        if ($model instanceof User || $model instanceof Role) {
            if (method_exists($model, 'scopeExcludeRoot')) {
                $model = $model->excludeRoot();
            }
        }

        foreach (Arr::wrap($scopes) as $key => $scope) {
            try {
                if (!method_exists($model, $scope)) {
                    Log::warning("Scope method '{$scope}' not found on model " . get_class($model));
                    continue;
                }

                // Determine matching value
                $value = $values[$scope] ?? $values[$key] ?? null;

                $model = !empty($value)
                    ? $model->$scope(...Arr::wrap($value))
                    : $model->$scope();

            } catch (Exception|Error $e) {
                Log::error("Error applying scope '{$scope}' on model " . get_class($model) . ": " . $e->getMessage());
                continue;
            }
        }
    }

    private function determineSelectFields(array $table): array
    {
        $select = ['id'];

        if (!empty($table['extra'])) {
            // TODO [Security]: Before merging extra fields, validate that each field is allowed to be selected.
            //  - Prevent exposing locked or restricted columns (e.g., password, api_key, tokens, salary, etc.)
            //  - Consider checking against a whitelist or permission-based access control.
            //  - Example: $this->validateSelectableColumns($table['extra'], $userPermissions);
            $select = array_merge($select, Arr::wrap($table['extra']));
        }

        $table = Str::plural($table['name']);

        $columns = Schema::hasTable($table['name'])
            ? Schema::getColumnListing($table['name'])
            : [];

        foreach (self::NAME_FIELDS as $field) {
            if (in_array($field, $columns)) {
                $select[] = $field;
                break;
            }
        }

        return array_values(array_unique($select));
    }

    private function transformRecord($record, array $select): array
    {
        $data = [
            'id' => $record->id ?? null,
        ];

        // Determine name value from available name fields
        $data['name'] = $record->name
            ?? $record->title
            ?? $record->display_name
            ?? $record->full_name
            ?? $record->label
            ?? ($record->first_name && $record->last_name
                ? trim("{$record->first_name} {$record->last_name}")
                : null);

        // Include valid extra fields
        $extraFields = array_diff($select, array_merge(['id'], self::NAME_FIELDS));
        foreach ($extraFields as $field) {
            if (isset($record->{$field})) {
                $data[$field] = $record->{$field};
            }
        }

        return $data;
    }

    private function resolveModel(string $name, $module): ?object
    {
        $model = collect(explode('_', $name))
            ->map(fn($i) => ucfirst(Str::camel(Str::singular($i))))->join('');

        $modelPath = !empty($module)
            ? "Modules\\" . ucfirst(Str::camel($module)) . "\\App\\Models\\{$model}"
            : "App\\Models\\{$model}";

        return class_exists($modelPath) ? new $modelPath() : null;
    }
}
