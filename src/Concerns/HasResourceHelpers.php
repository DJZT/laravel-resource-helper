<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Concerns;

use Djzt\ResourceHelper\Support\Formatter;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Хелперы для JsonResource / ResourceCollection.
 *
 * Подключается либо напрямую в свой ресурс, либо через базовый класс
 * \Djzt\ResourceHelper\HelperResource.
 *
 * @mixin \Illuminate\Http\Resources\Json\JsonResource
 */
trait HasResourceHelpers
{
    /*
    |--------------------------------------------------------------------------
    | Даты
    |--------------------------------------------------------------------------
    */

    /**
     * Дата в формате из config('resource-helper.formats.date').
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
     * Дата и время в формате из config('resource-helper.formats.datetime').
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function dateTime(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatter()->dateTime($value, $format, $timezone);
    }

    /**
     * Время в формате из config('resource-helper.formats.time').
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function time(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatter()->time($value, $format, $timezone);
    }

    /**
     * Дата в ISO-8601.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function isoDate(mixed $value, ?string $timezone = null): ?string
    {
        return $this->formatter()->isoDate($value, $timezone);
    }

    /**
     * Unix-время.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function timestamp(mixed $value): ?int
    {
        return $this->formatter()->timestamp($value);
    }

    /**
     * «3 часа назад».
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function diffForHumans(mixed $value, ?string $timezone = null): ?string
    {
        return $this->formatter()->diffForHumans($value, $timezone);
    }

    /**
     * Дата во всех представлениях сразу (набор ключей — в конфиге date_array).
     *
     * @param  \DateTimeInterface|string|int|null  $value
     * @return array<string, string|int|null>|null
     */
    protected function dateArray(mixed $value, ?string $timezone = null): ?array
    {
        return $this->formatter()->dateArray($value, $timezone);
    }

    /**
     * Несколько дат разом — результат подмешивается через spread:
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
    | Числа
    |--------------------------------------------------------------------------
    */

    /**
     * Сумма денег (см. секцию money в конфиге).
     *
     * @return string|float|array<string, mixed>|null
     */
    protected function money(mixed $value, ?string $currency = null, ?int $decimals = null): string|float|array|null
    {
        return $this->formatter()->money($value, $currency, $decimals);
    }

    /**
     * Число с фиксированным количеством знаков после запятой.
     */
    protected function number(mixed $value, ?int $decimals = null): ?float
    {
        return $this->formatter()->number($value, $decimals);
    }

    /**
     * Алиас number().
     */
    protected function float(mixed $value, ?int $decimals = null): ?float
    {
        return $this->formatter()->number($value, $decimals);
    }

    /**
     * Целое число.
     */
    protected function integer(mixed $value): ?int
    {
        return $this->formatter()->integer($value);
    }

    /**
     * Алиас integer().
     */
    protected function int(mixed $value): ?int
    {
        return $this->formatter()->integer($value);
    }

    /**
     * Настоящий boolean вместо 0 / 1 / "1".
     */
    protected function boolean(mixed $value): ?bool
    {
        return $this->formatter()->boolean($value);
    }

    /**
     * Алиас boolean().
     */
    protected function bool(mixed $value): ?bool
    {
        return $this->formatter()->boolean($value);
    }

    /**
     * Проценты: 0.1234 -> 12.34.
     */
    protected function percent(mixed $value, ?int $decimals = null, bool $fromFraction = true): ?float
    {
        return $this->formatter()->percent($value, $decimals, $fromFraction);
    }

    /**
     * Человекочитаемый размер файла: 1536 -> "1.5 KB".
     */
    protected function bytes(mixed $value, int $precision = 1): ?string
    {
        return $this->formatter()->bytes($value, $precision);
    }

    /*
    |--------------------------------------------------------------------------
    | Строки, enum-ы, файлы
    |--------------------------------------------------------------------------
    */

    /**
     * Строка с trim и, опционально, обрезкой длины.
     */
    protected function string(mixed $value, ?int $limit = null, string $end = '...'): ?string
    {
        return $this->formatter()->string($value, $limit, $end);
    }

    /**
     * Алиас string().
     */
    protected function str(mixed $value, ?int $limit = null, string $end = '...'): ?string
    {
        return $this->formatter()->string($value, $limit, $end);
    }

    /**
     * Значение enum-а (BackedEnum -> value, обычный enum -> name).
     */
    protected function enum(mixed $value): mixed
    {
        return $this->formatter()->enum($value);
    }

    /**
     * Абсолютная ссылка на файл в сторадже.
     */
    protected function url(mixed $path, ?string $disk = null): ?string
    {
        return $this->formatter()->url($path, $disk);
    }

    /**
     * Значение переводимого json-поля для текущей локали.
     */
    protected function translate(mixed $value, ?string $locale = null): ?string
    {
        return $this->formatter()->translate($value, $locale);
    }

    /**
     * Скрыть часть строки: "79001234567" -> "*******4567".
     */
    protected function mask(mixed $value, ?int $keepStart = null, ?int $keepEnd = null, ?string $character = null): ?string
    {
        return $this->formatter()->mask($value, $keepStart, $keepEnd, $character);
    }

    /*
    |--------------------------------------------------------------------------
    | Условные атрибуты
    |--------------------------------------------------------------------------
    */

    /**
     * Вложенный ресурс, но только если связь уже загружена — иначе N+1.
     * Сам определяет, коллекция это или одна модель.
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
     * Атрибут только для тех, кому разрешает политика.
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
     * Атрибут только для аутентифицированных запросов.
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
    | Доступ к форматтеру
    |--------------------------------------------------------------------------
    */

    /**
     * Экземпляр форматтера — на случай, если нужен метод, не проксированный сюда.
     */
    protected function formatter(): Formatter
    {
        if (! app()->bound(Formatter::class)) {
            throw new LogicException(
                'Djzt\ResourceHelper\ResourceHelperServiceProvider не зарегистрирован.'
            );
        }

        return app(Formatter::class);
    }
}
