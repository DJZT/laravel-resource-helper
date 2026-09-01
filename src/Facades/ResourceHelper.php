<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * The same helpers outside resources — in controllers, exports, notifications.
 *
 * @method static string|null date(mixed $value, ?string $format = null, ?string $timezone = null)
 * @method static string|null dateTime(mixed $value, ?string $format = null, ?string $timezone = null)
 * @method static string|null time(mixed $value, ?string $format = null, ?string $timezone = null)
 * @method static string|null isoDate(mixed $value, ?string $timezone = null)
 * @method static int|null timestamp(mixed $value)
 * @method static string|null diffForHumans(mixed $value, ?string $timezone = null)
 * @method static array|null dateArray(mixed $value, ?string $timezone = null)
 * @method static array dates(array $values, ?string $format = null)
 * @method static string|float|array|null money(mixed $value, ?string $currency = null, ?int $decimals = null)
 * @method static float|null number(mixed $value, ?int $decimals = null)
 * @method static int|null integer(mixed $value)
 * @method static bool|null boolean(mixed $value)
 * @method static float|null percent(mixed $value, ?int $decimals = null, bool $fromFraction = true)
 * @method static string|null bytes(mixed $value, int $precision = 1)
 * @method static string|null string(mixed $value, ?int $limit = null, string $end = '...')
 * @method static mixed enum(mixed $value)
 * @method static string|null url(mixed $path, ?string $disk = null)
 * @method static string|null translate(mixed $value, ?string $locale = null)
 * @method static string|null mask(mixed $value, ?int $keepStart = null, ?int $keepEnd = null, ?string $character = null)
 * @method static \Carbon\CarbonInterface|null toCarbon(mixed $value, ?string $timezone = null)
 *
 * @see \Djzt\ResourceHelper\Support\Formatter
 */
class ResourceHelper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'resource-helper';
    }
}
