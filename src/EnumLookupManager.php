<?php

namespace HasanHawary\LookupManager;

use App\Http\Requests\Global\Help\HelpEnumRequest;
use Error;
use Illuminate\Support\Str;

class EnumLookupManager
{
    /**
     * Fetch enum lists. If request->enums is null, scan default locations.
     */
    public function getEnums(HelpEnumRequest $request): array
    {
        if (!$request->enums) {
            return array_merge($this->getDefaultEnums(), $this->getDefaultModuleEnums());
        }

        return collect($request->enums)->mapWithKeys(function ($enum) {
            $name = implode("\\", array_map(fn($i) => ucfirst(Str::camel($i)), explode('.', $enum['name'])));

            $enumPath = !empty($enum['module'])
                ? "Modules\\" . ucfirst(Str::camel($enum['module'])) . "\\App\\Enum\\{$name}Enum"
                : "App\\Enum\\{$name}Enum";

            try {
                return [$enum['name'] => $enumPath::getList()];
            } catch (\Exception|Error) {
                return [$enum['name'] => []];
            }
        })->toArray();
    }

    private function getDefaultEnums(): array
    {
        $result = [];
        $this->resolveFilesFromDir(app_path('Enum'), $result);

        collect($result)->each(function ($enum, $name) use (&$result) {
            $result[$name] = $enum::getList();
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
                        $result[$name] = $enum::getList();
                    });
                });
        }

        return $result;
    }

    private function resolveFilesFromDir(string $dir, array &$result): void
    {
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
