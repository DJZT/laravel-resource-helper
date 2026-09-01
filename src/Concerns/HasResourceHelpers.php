<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Concerns;

use Djzt\ResourceHelper\Support\Formatter;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Helpers for JsonResource / ResourceCollection.
 *
 * Use it either directly in your own resource, or through the base class
 * \Djzt\ResourceHelper\HelperResource.
 *
 * @mixin \Illuminate\Http\Resources\Json\JsonResource
 */
trait HasResourceHelpers
{
    /*
    |--------------------------------------------------------------------------
    | Dates
    |--------------------------------------------------------------------------
    */

    /**
     * A date in the format from config('resource-helper.formats.date').
     *
     *     $this->date($this->created_at)           // 2026-09-01
     *     $this->date($this->created_at, 'human')  // 1 Sep 2026
     *     $this->date($this->created_at, 'd/m/Y')  // 01/09/2026
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function date(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatter()->date($value, $format, $timezone);
    }

    /**
     * A date and time in the format from config('resource-helper.formats.datetime').
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function dateTime(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatter()->dateTime($value, $format, $timezone);
    }

    /**
     * A time in the format from config('resource-helper.formats.time').
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function time(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatter()->time($value, $format, $timezone);
    }

    /**
     * A date in ISO-8601.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function isoDate(mixed $value, ?string $timezone = null): ?string
    {
        return $this->formatter()->isoDate($value, $timezone);
    }

    /**
     * Unix time.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function timestamp(mixed $value): ?int
    {
        return $this->formatter()->timestamp($value);
    }

    /**
     * "3 hours ago".
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function diffForHumans(mixed $value, ?string $timezone = null): ?string
    {
        return $this->formatter()->diffForHumans($value, $timezone);
    }

    /**
     * A date in every representation at once (keys come from date_array in the config).
     *
     * @param  \DateTimeInterface|string|int|null  $value
     * @return array<string, string|int|null>|null
     */
    protected function dateArray(mixed $value, ?string $timezone = null): ?array
    {
        return $this->formatter()->dateArray($value, $timezone);
    }

    /**
     * Several dates at once; spread the result into your array:
     *
     *     return [
     *         'id' => $this->id,
     *         ...$this->dates(['created_at' => $this->created_at]),
     *     ];
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string|null>
     */
    protected function dates(array $values, ?string $format = null): array
    {
        return $this->formatter()->dates($values, $format);
    }

    /*
    |--------------------------------------------------------------------------
    | Numbers
    |--------------------------------------------------------------------------
    */

    /**
     * A monetary amount (see the money section of the config).
     *
     * @return string|float|array<string, mixed>|null
     */
    protected function money(mixed $value, ?string $currency = null, ?int $decimals = null): string|float|array|null
    {
        return $this->formatter()->money($value, $currency, $decimals);
    }

    /**
     * A number with a fixed number of decimals.
     */
    protected function number(mixed $value, ?int $decimals = null): ?float
    {
        return $this->formatter()->number($value, $decimals);
    }

    /**
     * Alias of number().
     */
    protected function float(mixed $value, ?int $decimals = null): ?float
    {
        return $this->formatter()->number($value, $decimals);
    }

    /**
     * An integer.
     */
    protected function integer(mixed $value): ?int
    {
        return $this->formatter()->integer($value);
    }

    /**
     * Alias of integer().
     */
    protected function int(mixed $value): ?int
    {
        return $this->formatter()->integer($value);
    }

    /**
     * A real boolean instead of 0 / 1 / "1".
     */
    protected function boolean(mixed $value): ?bool
    {
        return $this->formatter()->boolean($value);
    }

    /**
     * Alias of boolean().
     */
    protected function bool(mixed $value): ?bool
    {
        return $this->formatter()->boolean($value);
    }

    /**
     * Percentages: 0.1234 -> 12.34.
     */
    protected function percent(mixed $value, ?int $decimals = null, bool $fromFraction = true): ?float
    {
        return $this->formatter()->percent($value, $decimals, $fromFraction);
    }

    /**
     * A human-readable file size: 1536 -> "1.5 KB".
     */
    protected function bytes(mixed $value, int $precision = 1): ?string
    {
        return $this->formatter()->bytes($value, $precision);
    }

    /*
    |--------------------------------------------------------------------------
    | Strings, enums, files
    |--------------------------------------------------------------------------
    */

    /**
     * A trimmed string, optionally truncated to a length.
     */
    protected function string(mixed $value, ?int $limit = null, string $end = '...'): ?string
    {
        return $this->formatter()->string($value, $limit, $end);
    }

    /**
     * Alias of string().
     */
    protected function str(mixed $value, ?int $limit = null, string $end = '...'): ?string
    {
        return $this->formatter()->string($value, $limit, $end);
    }

    /**
     * An enum's value (BackedEnum -> value, a pure enum -> name).
     */
    protected function enum(mixed $value): mixed
    {
        return $this->formatter()->enum($value);
    }

    /**
     * An absolute URL for a file on a disk.
     */
    protected function url(mixed $path, ?string $disk = null): ?string
    {
        return $this->formatter()->url($path, $disk);
    }

    /**
     * The value of a translatable JSON field for the current locale.
     */
    protected function translate(mixed $value, ?string $locale = null): ?string
    {
        return $this->formatter()->translate($value, $locale);
    }

    /**
     * Hide part of a string: "380501234567" -> "********4567".
     */
    protected function mask(mixed $value, ?int $keepStart = null, ?int $keepEnd = null, ?string $character = null): ?string
    {
        return $this->formatter()->mask($value, $keepStart, $keepEnd, $character);
    }

    /*
    |--------------------------------------------------------------------------
    | Conditional attributes
    |--------------------------------------------------------------------------
    */

    /**
     * A nested resource, but only when the relation is already loaded —
     * otherwise you get N+1. Collection vs. single model is detected for you.
     *
     *     'author'  => $this->whenLoadedResource('author', UserResource::class),
     *     'comments' => $this->whenLoadedResource('comments', CommentResource::class),
     *
     * @param  class-string<\Illuminate\Http\Resources\Json\JsonResource>|null  $resourceClass
     */
    protected function whenLoadedResource(string $relation, ?string $resourceClass = null, mixed $default = null): mixed
    {
        $value = func_num_args() === 3
            ? $this->whenLoaded($relation, fn () => $this->resource->{$relation}, $default)
            : $this->whenLoaded($relation);

        if ($value instanceof MissingValue || $value === null || $resourceClass === null) {
            return $value;
        }

        return is_iterable($value)
            ? $resourceClass::collection($value)
            : new $resourceClass($value);
    }

    /**
     * An attribute only for those the policy allows.
     *
     *     'email' => $this->whenCan('viewEmail', $this->email),
     */
    protected function whenCan(string $ability, mixed $value, mixed $default = null): mixed
    {
        $allowed = Gate::allows($ability, $this->resource);

        return func_num_args() === 3
            ? $this->when($allowed, $value, $default)
            : $this->when($allowed, $value);
    }

    /**
     * An attribute only for authenticated requests.
     */
    protected function whenAuthenticated(mixed $value, mixed $default = null, ?string $guard = null): mixed
    {
        $check = auth()->guard($guard)->check();

        return func_num_args() >= 2
            ? $this->when($check, $value, $default)
            : $this->when($check, $value);
    }

    /*
    |--------------------------------------------------------------------------
    | Access to the formatter
    |--------------------------------------------------------------------------
    */

    /**
     * The formatter instance, for a method that is not proxied here.
     */
    protected function formatter(): Formatter
    {
        if (! app()->bound(Formatter::class)) {
            throw new LogicException(
                'Djzt\ResourceHelper\ResourceHelperServiceProvider is not registered.'
            );
        }

        return app(Formatter::class);
    }
}
