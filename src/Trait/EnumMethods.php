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
                array_column(self::cases(), 'value'),
                array_column(self::cases(), 'name')
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
        if (!is_callable($callback)) {
            [];
        }

        return collect($data)->map($callback)->values()->toArray();
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
        return method_exists(__CLASS__, 'keyName')
            ? self::keyName()
            : Str::of(class_basename(self::class))
                ->snake()
                ->replaceLast('_enum', '')
                ->toString();
    }

    private static function getExtraData(string $value, array|string|null $extra): array|string|null
    {
        if ($extra !== null) {
            return $extra;
        }

        return method_exists(__CLASS__, 'extra') ? (self::extra()[$value] ?? null) : null;
    }

    private static function format($key, $value, $extra = null): array
    {
        $labelKey = self::getLabelKey($key, $value);

        $keyName = self::getKeyName();

        $fullKeyPath = __("enums.$keyName.$labelKey");

        $labelValue = Str::startsWith($fullKeyPath, 'enums.') ? $labelKey : $fullKeyPath;

        return [
            'key' => $key,
            'value' => $value,
            'label' => $labelValue,
            'snake_key' => $labelKey,
            'extra' => self::getExtraData($value, $extra),
            'icon' => method_exists(__CLASS__, 'icon') ? (self::icons()[$value] ?? null) : null,
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

        if (!$object = collect(self::getList())->filter(fn($item) => $item['value'] == $value)->first()) {
            $key = true;
            if (!$object = collect(self::getList())->filter(fn($item) => $item['key'] == $value)->first()) {
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
        return Arr::pluck(self::cases(),'value');
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
}
