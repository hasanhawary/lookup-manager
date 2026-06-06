<?php

namespace HasanHawary\LookupManager\Trait;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
trait EnumMethods
{
    public static function getList(): array
    {
        return self::buildList(
            array_combine(
                array_column(static::cases(), 'value'),
                array_column(static::cases(), 'name')
            ),
            fn ($key, $value) => self::format($key, $value)
        );
    }

    public static function getCustomList(array $data): array
    {
        return self::buildList(
            $data,
            fn($enum, $key) => self::format($enum->name, $enum->value)
        );
    }

    public static function getExtraList(array $data): array
    {
        return self::buildList(
            $data,
            fn($extra, $enumValue) => self::format((self::from($enumValue))->name, $enumValue, $extra)
        );
    }

    public static function buildList(array $data, callable $callback): array
    {
        return array_values(array_map($callback, $data, array_keys($data)));
    }

    private static function getLabelKey($key, $value): string
    {
        return is_numeric($value)
            ? (ctype_upper($key) || \count(explode('_', $key)) > 1
                ? strtolower($key)
                : Str::snake($key))
            : Str::snake($value);
    }

    private static function getKeyName(): string
    {
        return method_exists(static::class, 'keyName')
            ? static::keyName()
            : Str::of(class_basename(static::class))
                ->snake()
                ->replaceLast('_enum', '')
                ->toString();
    }

    private static function getExtraData(string $value, array|string|null $extra): array|string|null
    {
        if ($extra !== null) {
            return $extra;
        }

        return method_exists(static::class, 'extra') ? (static::extra()[$value] ?? null) : null;
    }

    private static function format($key, $value, $extra = null): array
    {
        $labelKey = self::getLabelKey($key, $value);

        $keyName = self::getKeyName();

        $fullKeyPath = self::translate("enums.$keyName.$labelKey");

        $labelValue = Str::startsWith($fullKeyPath, 'enums.') ? $labelKey : $fullKeyPath;

        return [
            'key' => $key,
            'value' => $value,
            'label' => $labelValue,
            'snake_key' => $labelKey,
            'extra' => self::getExtraData($value, $extra),
            'icon' => method_exists(static::class, 'icons') ? (static::icons()[$value] ?? null) : null,
        ];
    }


    /**
     * Matches a given value to its corresponding enumerated key or display value.
     *
     * @param mixed $value The value to be matched against the enumerated values or keys.
     * @param bool $trans Optional. Whether to return the translated display value. Default is true.
     *
     * @return mixed The matched display value if $trans is true, otherwise the matched key or value.
     *               If no match is found, the original $value is returned.
     */
    public static function resolve(mixed $value, ?bool $trans = true): mixed
    {
        $key = false;
        if (is_object($value)) {
            $value = $value->value; // Get the value from the Enum instance
        }

        if (!$object = self::firstWhere(self::getList(), 'value', $value)) {
            $key = true;
            if (!$object = self::firstWhere(self::getList(), 'key', $value)) {
                return $value;
            }
        }

        if ($trans) {
            return $object['label'];
        }

        return $key ? $object['value'] : $object['snake_key'];
    }

    /**
     * @return array
     */
    public static function values(): array
    {
        return Arr::pluck(static::cases(),'value');
    }

    /**
     * @return string
     */
    public static function commentFormat(): string
    {
        return implode(
            ', ',
            array_map(static fn($item) => $item['value'] . ' => ' . $item['label'], self::getList())
        );
    }

    private static function firstWhere(array $items, string $key, mixed $value): ?array
    {
        foreach ($items as $item) {
            if (($item[$key] ?? null) == $value) {
                return $item;
            }
        }

        return null;
    }

    private static function translate(string $key): string
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
}
