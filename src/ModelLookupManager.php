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
				$result[$tableName] = $model->select($select);

				$isPaginated = $table['paginate'] ?? false;
				$perPage = $table['per_page'] ?? 15;
				$page = $table['page'] ?? 1;
				$query = $model;

				$transform = fn ($record) => $this->transformRecord($record, $select);

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
		if ($model instanceof User || $model instanceof Role) {
			if (method_exists($model, 'scopeExcludeRoot')) {
				$model = $model->excludeRoot();
			}
		}

		foreach (Arr::wrap($scopes) as $key => $scope) {
			try {
				$method = Str::camel($scope);
				if (!method_exists($model, "scope{$method}")) {
					Log::warning("Scope method '{$scope}' not found on model " . get_class($model));
					continue;
				}

				// Determine matching value
				$value = $values[$scope] ?? $values[$key] ?? null;

				// allow 0/"0"/false as valid values — only treat null as absent
				$model = $value !== null
					? $model->$scope(...Arr::wrap($value))
					: $model->$scope();

			} catch (Exception|Error $e) {
				Log::error("Error applying scope '{$scope}' on model " . get_class($model) . ": " . $e->getMessage());
				continue;
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

	private function transformRecord($record, array $select): array
	{
		$data = [
			'id' => $record->id ?? null,
		];

		// Determine name value from available name fields
		$nameModel = null;

		// check if property exists on the model instance
		if (property_exists($record, 'helpModelName')) {
			$nameModel = $record->helpModelName;
		} else {
			$recordClass = get_class($record);

			if (property_exists($recordClass, 'helpModelName')) {
				try {
					$nameModel = $recordClass::$helpModelName ?? null;
				} catch (\Throwable $e) {
					$nameModel = null;
				}
			}
		}


		// If nameModel is defined and refers to one or more columns, build name using those columns' values
		if ($nameModel) {
			if (is_array($nameModel)) {
				$values = array_map(fn($col) => $record->{$col} ?? '', $nameModel);
				$data['name'] = $this->resolveModelName($values);
			} else {
				$data['name'] = $this->resolveModelName($record->{$nameModel} ?? '');
			}
		} else {
			$data['name'] = $this->resolveDefaultName($record);
		}

		// Include valid extra fields
		$extraFields = array_diff($select, array_merge(['id'], self::NAME_FIELDS));

		// if we used a helpModelName field as the name, exclude it from extras to avoid duplication
		if ($nameModel) {
			$helpFields = Arr::wrap($nameModel);
			$extraFields = array_values(array_filter($extraFields, fn($f) => !in_array($f, $helpFields)));
		}

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
					'name' => __('lookup.models.'.Str::snake($tableName)),
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