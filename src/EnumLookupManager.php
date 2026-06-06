<?php

namespace HasanHawary\LookupManager;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class EnumLookupManager
{
	/**
	 * Fetch enum lists. If request->enums is null, scan default locations.
	 *
	 * @throws \RuntimeException if enums request is invalid
	 */
	public function getEnums(array $request): array
	{
		// Throw exception if 'enums' key exists but is not an array
		if (isset($request['enums']) && !is_array($request['enums'])) {
			throw new RuntimeException("The 'enums' key must be an array.");
		}

		// Use defaults if 'enums' not provided
		if (!isset($request['enums']) || empty($request['enums'])) {
			return array_merge($this->getDefaultEnums(), $this->getDefaultModuleEnums());
		}

		$result = [];

		foreach ($request['enums'] as $enum) {
            try {
                $enumPath = $this->resolveEnumClassFromRequest($enum);

                // Throw exception if class does not exist
                if ($enumPath === null || ! $this->classExists($enumPath)) {
                    throw new RuntimeException("Enum class not found: {$enumPath}");
                }

			    $method = $this->resolveMethod($enum['method'] ?? null);

				// Call the static method dynamically
				$result[$enum['name']] = $enumPath::$method();
			} catch (\Throwable $e) {
				$this->log('error', "Failed to get enum [{$enum['name']}]: {$e->getMessage()}");

				$result[$enum['name']] = [];
			}
		}

		return $result;
	}

	private function getDefaultEnums(): array
	{
		$result = [];
		$final = [];
		foreach ($this->enumPaths() as $path) {
			$this->resolveFilesFromDir($path, $result);
		}

		foreach ($result as $name => $enum) {
			$className = Str::of($name)
				->replace('Enum', '')
				->snake()
				->toString();

				if ($this->classExists($enum)) {
				$final[] = ['label' => $this->translateEnumName($className), 'key' => $name];
			}
		}

		return array_values($final);
	}

	private function getDefaultModuleEnums(): array
	{
		$result = [];
		$moduleEnumNamespace = trim((string) $this->config('lookup.modules.enum_namespace', ''), '\\');
		$modulesPath = $this->modulesPath();

		if ($moduleEnumNamespace === '' || ! is_dir($modulesPath)) {
			return $result;
		}

		foreach (glob($modulesPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $path) {
			$enumPath = $path.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $moduleEnumNamespace);

			if (! is_dir($enumPath)) {
				continue;
			}

			$discovered = [];
			$this->resolveFilesFromDir($enumPath, $discovered);

			foreach ($discovered as $name => $enum) {
				$method = $this->resolveMethod(null);
				$result[$name] = $this->classExists($enum) && method_exists($enum, $method) ? $enum::$method() : [];
			}
		}

		return $result;
	}

	private function resolveFilesFromDir(string $dir, array &$result): void
	{
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $file) {
			if (Str::startsWith($file, '.')) {
				continue;
			}

			$filePath = $dir.DIRECTORY_SEPARATOR.$file;

			if (is_dir($filePath)) {
				$this->resolveFilesFromDir($filePath, $result);
			} elseif (is_file($filePath)) {
				$result[$this->resolveEnumKey($filePath)] = $this->resolveEnumClass($filePath);
			}
		}
	}

	private function resolveEnumKey(string $filePath): string
	{
		return Str::of($filePath)
			->after('Enum'.DIRECTORY_SEPARATOR)
			->replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''])
			->snake()
			->replaceLast('_enum', '')
			->replace('._', '.')
			->toString();
	}

	private function resolveEnumClass(string $filePath): string
	{
		return Str::of($filePath)
			->replaceFirst($this->basePath().DIRECTORY_SEPARATOR, '')
			->replace('.php', '')
			->replace(DIRECTORY_SEPARATOR, '\\')
			->ucfirst()
			->toString();
	}

	private function translateEnumName(string $name): string
	{
		$key = 'lookup.enums.'.$name;
		$translation = $this->translate($key);

		return $translation === $key ? Str::headline($name) : $translation;
	}

	private function resolveEnumClassFromRequest(array $enum): ?string
	{
		$requestedName = (string) ($enum['name'] ?? '');

		if ($requestedName !== '' && $this->classExists($requestedName)) {
			return $requestedName;
		}

		$name = implode('\\', array_map(fn($i) => ucfirst(Str::camel($i)), explode('.', $requestedName)));

		foreach ($this->enumNamespaces($enum['module'] ?? null) as $namespace) {
			$enumPath = trim($namespace, '\\')."\\{$name}Enum";

			if ($this->classExists($enumPath)) {
				return $enumPath;
			}
		}

		return null;
	}

	private function resolveMethod(?string $method): string
	{
		$method = $method !== null && $method !== ''
			? Str::camel($method)
			: (string) $this->config('lookup.enums.default_method', 'getList');

		$allowed = $this->config('lookup.enums.allowed_methods', ['getList']);
		$allowed = is_array($allowed) ? $allowed : [$allowed];

		if (! in_array($method, $allowed, true)) {
			throw new RuntimeException("Enum method [{$method}] is not allowed.");
		}

		return $method;
	}

	private function enumNamespaces(?string $module = null): array
	{
		if (! empty($module)) {
			$moduleNamespace = trim((string) $this->config('lookup.modules.namespace', ''), '\\');
			$enumNamespace = trim((string) $this->config('lookup.modules.enum_namespace', ''), '\\');

			if ($moduleNamespace === '' || $enumNamespace === '') {
				return [];
			}

			return [$moduleNamespace.'\\'.Str::studly($module).'\\'.$enumNamespace];
		}

		$namespaces = $this->config('lookup.enums.namespaces', []);
		$namespaces = is_array($namespaces) ? $namespaces : [$namespaces];

		if ($namespaces === []) {
			$appNamespace = $this->applicationNamespace();

			if ($appNamespace !== null) {
				$namespaces[] = trim($appNamespace, '\\').'\\Enum';
			}
		}

		return array_values(array_filter($namespaces));
	}

	private function enumPaths(): array
	{
		$paths = $this->config('lookup.enums.paths', []);
		$paths = is_array($paths) ? $paths : [$paths];

		if ($paths === [] && $this->appPath('Enum') !== null) {
			$paths[] = $this->appPath('Enum');
		}

		return array_values(array_filter($paths));
	}

	private function modulesPath(): string
	{
		$path = (string) $this->config('lookup.modules.path', 'Modules');

		if ($path === '') {
			return '';
		}

		if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
			return $path;
		}

		return $this->basePath().DIRECTORY_SEPARATOR.$path;
	}

	private function applicationNamespace(): ?string
	{
		try {
			if (function_exists('app') && method_exists(app(), 'getNamespace')) {
				return app()->getNamespace();
			}
		} catch (\Throwable) {
			return null;
		}

		return null;
	}

	private function basePath(): string
	{
		if (! function_exists('base_path')) {
			return getcwd();
		}

		try {
			return base_path();
		} catch (\Throwable) {
			return getcwd();
		}
	}

	private function appPath(string $path = ''): ?string
	{
		if (! function_exists('app_path')) {
			return null;
		}

		try {
			return app_path($path);
		} catch (\Throwable) {
			return null;
		}
	}

	private function config(string $key, mixed $default = null): mixed
	{
		if (! function_exists('config')) {
			return $default;
		}

		try {
			return config($key, $default);
		} catch (\Throwable) {
			return $default;
		}
	}

	private function translate(string $key): string
	{
		if (! function_exists('__')) {
			return $key;
		}

		try {
			return __($key);
		} catch (\Throwable) {
			return $key;
		}
	}

	private function log(string $level, string $message): void
	{
		try {
			if (class_exists(Log::class)) {
				Log::$level($message);
			}
		} catch (\Throwable) {
			//
		}
	}

	private function classExists(string $class): bool
	{
		return class_exists($class) || (function_exists('enum_exists') && enum_exists($class));
	}
}
