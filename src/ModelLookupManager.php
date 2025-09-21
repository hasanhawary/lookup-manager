<?php

namespace HasanHawary\LookupManager;

use App\Http\Requests\Global\Help\HelpModelRequest;
use Error;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModelLookupManager
{
    /**
     * Fetch model data according to the request definition.
     */
    public function getModels(HelpModelRequest $request): array
    {
        $result = [];

        foreach ($request->tables as $table) {
            try {
                when(!$model = $this->resolveModel($table['name'], @$table['module']), fn() => throw new \RuntimeException());

                $select = $this->determineSelectFields($table);

                $this->applyScopes($model, @$table['scopes'], @$table['values']);

                $result[$table['name']] = $model->select($select)
                    ->get()
                    ->transform(fn($record) => $this->transformRecord($record, $select))
                    ->toArray();

            } catch (Exception|Error $e) {
                Log::error("Error when Getting model");
                $result[$table['name']] = [];
            }
        }

        return $result;
    }

    private function applyScopes($model, $scopes, $values): void
    {
        if (empty($scopes)) return;

        collect($scopes)->each(function ($scope, $index) use ($model, $values) {
            if (method_exists($model, 'scope' . Str::studly($scope))) {
                $model->$scope(@$values[$index]);
            }
        });
    }

    private function determineSelectFields(array $table): array
    {
        $select = Arr::wrap('id');

        if (!empty($table['extra'])) {
            $select = collect(array_merge($select, Arr::wrap($table['extra'] ?? [])))->unique()->toArray();
        }

        $nameFields = ['display_name', 'title', 'label', 'name'];
        $columns = Schema::getColumnListing($table['name']);

        foreach ($nameFields as $field) {
            if (in_array($field, $columns)) {
                $select = array_merge($select, [$field]);
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
        $model = collect(explode('_', $name))->map(fn($i) => ucfirst(Str::camel($i)))->join('');

        $modelPath = !empty($module)
            ? "Modules\\" . ucfirst(Str::camel($module)) . "\\App\\Models\\{$model}"
            : "App\\Models\\{$model}";

        return class_exists($modelPath) ? new $modelPath() : null;
    }
}
