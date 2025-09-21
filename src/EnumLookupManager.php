<?php

namespace HasanHawary\LookupManager;

use Error;
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

        return collect($request['enums'])->mapWithKeys(function ($enum) {
            $name = implode("\\", array_map(fn($i) => ucfirst(Str::camel($i)), explode('.', $enum['name'])));

            $enumPath = !empty($enum['module'])
                ? "Modules\\" . ucfirst(Str::camel($enum['module'])) . "\\App\\Enum\\{$name}Enum"
                : "App\\Enum\\{$name}Enum";

            // Throw exception if class does not exist
            if (!class_exists($enumPath)) {
                throw new RuntimeException("Enum class not found: {$enumPath}");
            }

            try {
                return [$enum['name'] => $enumPath::getList()];
            } catch (\Exception|Error $e) {
                // Log error but return empty array for this enum
                return [$enum['name'] => []];
            }
        })->toArray();
    }

    private function getDefaultEnums(): array
    {
        $result = [];
        $this->resolveFilesFromDir(app_path('Enum'), $result);

        collect($result)->each(function ($enum, $name) use (&$result) {
            if (class_exists($enum)) {
                $result[$name] = $enum::getList();
            } else {
                $result[$name] = [];
            }
        });

        return $result;
    }

    private function getDefaultModuleEnums(): array
    {
        $result = [];
        if (is_dir(base_path('Modules'))) {
            collect(glob(base_path('Modules') . '/*', GLOB_ONLYDIR))
                ->filter(fn($path) => is_dir("$path\\App\\Enum"))
                ->each(function ($path) use (&$result) {
                    $this->resolveFilesFromDir("$path\\App\\Enum", $result);

                    collect($result)->each(function ($enum, $name) use (&$result) {
                        $result[$name] = class_exists($enum) ? $enum::getList() : [];
                    });
                });
        }

        return $result;
    }

    private function resolveFilesFromDir(string $dir, array &$result): void
    {
        if (!is_dir($dir)) return;

        collect(scandir($dir))
            ->filter(fn($file) => !Str::startsWith($file, '.'))
            ->each(function ($file) use ($dir, &$result) {
                $filePath = "$dir\\$file";

                if (is_dir($filePath)) {
                    $this->resolveFilesFromDir($filePath, $result);
                } elseif (is_file($filePath)) {
                    $result[$this->resolveEnumKey($filePath)] = ucfirst(str_replace([base_path() . "\\", '.php'], ['', ''], $filePath));
                }
            });
    }

    private function resolveEnumKey(string $filePath): string
    {
        return Str::of($filePath)
            ->after('Enum\\')
            ->replace(['\\', '.php'], ['.', ''])
            ->snake()
            ->replaceLast('_enum', '')
            ->replace('._', '.')
            ->toString();
    }
}
