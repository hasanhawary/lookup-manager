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
     * Fetch model data according to the request definition.
     */
    public function getModels(array $request): array
    {
        $result = [];

        // Throw exception if 'tables' is missing or empty
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
                // Resolve model
                $model = $this->resolveModel($tableName, $table['module'] ?? null);
                if (!$model) {
                    Log::warning("Model not found for table: {$tableName}");
                    $result[$tableName] = [];
                    continue;
                }

                // Determine select fields
                $select = $this->determineSelectFields($table);

                // Apply scopes if any
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
                $model->excludeRoot();
            }
        }

        foreach (Arr::wrap($scopes) as $key => $scope) {
            try {
                $model = !isset($values[$key])
                    ? $model->$scope()
                    : $model->$scope(...Arr::wrap(@$values[$key]));
            } catch (Exception|Error $e) {
                Log::error("Error when applying scope {$scope}: " . $e->getMessage());
            }
        }
    }

    private function determineSelectFields(array $table): array
    {
        $select = Arr::wrap('id');

        if (!empty($table['extra'])) {
            $select = collect(array_merge($select, Arr::wrap($table['extra'])))->unique()->toArray();
        }

        $nameFields = ['display_name', 'title', 'label', 'name'];
        $columns = Schema::hasTable($table['name']) ? Schema::getColumnListing($table['name']) : [];

        foreach ($nameFields as $field) {
            if (in_array($field, $columns)) {
                $select[] = $field;
                break;
            }
        }

        return array_unique($select);
    }

    private function transformRecord($record, array $select): array
    {
        $data = Arr::only($record->toArray(), $select);

        $map = ['display_name', 'title', 'label', 'name'];
        foreach ($map as $field) {
            if (isset($data[$field])) {
                $data['name'] = $data[$field];
                break;
            }
        }

        return Arr::only($data, ['id', 'name', ...array_values(array_diff($select, ['id']))]);
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
