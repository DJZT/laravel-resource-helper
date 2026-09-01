<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Support;

use BackedEnum;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use UnitEnum;

/**
 * Форматирование значений для API-ответа.
 *
 * Вся логика вынесена сюда, чтобы её можно было использовать и вне ресурсов
 * (в контроллерах, джобах, экспортах) и покрывать тестами без Laravel-ресурса.
 *
 * Пустое значение (null / '') превращается в config('resource-helper.null_value')
 * у методов, отдающих строку, и в null — у методов, отдающих число или boolean.
 */
class Formatter
{
    /**
     * Форматы на случай, если конфиг не опубликован или ключ из него удалён.
     *
     * @var array<string, string>
     */
    protected const FALLBACK_FORMATS = [
        'date'     => 'Y-m-d',
        'datetime' => 'Y-m-d H:i:s',
        'time'     => 'H:i',
    ];

    public function __construct(protected ConfigRepository $config)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Даты
    |--------------------------------------------------------------------------
    */

    /**
     * Дата в формате из конфига (formats.date).
     *
     * @param  \DateTimeInterface|string|int|null  $value
     * @param  string|null  $format  пресет из конфига либо сырой PHP-формат
     */
    public function date(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatDate($value, $format, 'date', $timezone);
    }

    /**
     * Дата и время в формате из конфига (formats.datetime).
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function dateTime(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatDate($value, $format, 'datetime', $timezone);
    }

    /**
     * Время в формате из конфига (formats.time).
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function time(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatDate($value, $format, 'time', $timezone);
    }

    /**
     * ISO-8601 — универсальный формат для фронта: new Date(...) его понимает.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function isoDate(mixed $value, ?string $timezone = null): ?string
    {
        return $this->formatDate($value, 'iso', 'datetime', $timezone);
    }

    /**
     * Unix-время.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function timestamp(mixed $value): ?int
    {
        return $this->toCarbon($value)?->getTimestamp();
    }

    /**
     * «3 часа назад» — с учётом локали из конфига.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function diffForHumans(mixed $value, ?string $timezone = null): ?string
    {
        $date = $this->toCarbon($value, $timezone);

        if ($date === null) {
            return $this->nullString();
        }

        if ($locale = $this->locale()) {
            $date = $date->locale($locale);
        }

        return $date->diffForHumans();
    }

    /**
     * Одна дата во всех представлениях сразу; набор ключей задаётся в конфиге
     * (date_array). Удобно, когда фронту нужно и машиночитаемое значение,
     * и готовая к показу строка.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     * @return array<string, string|int|null>|null
     */
    public function dateArray(mixed $value, ?string $timezone = null): ?array
    {
        $date = $this->toCarbon($value, $timezone);

        if ($date === null) {
            return null;
        }

        $result = [];

        foreach ((array) $this->option('date_array', []) as $key => $format) {
            $result[$key] = match ($format) {
                'timestamp' => $date->getTimestamp(),
                'human'     => $this->diffForHumans($date),
                default     => $date->format($this->resolveFormat((string) $format, 'datetime')),
            };
        }

        return $result;
    }

    /**
     * Отформатировать сразу несколько дат.
     *
     *     $this->dates([
     *         'created_at' => $this->created_at,
     *         'paid_at'    => [$this->paid_at, 'human'],
     *     ]);
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string|null>
     */
    public function dates(array $values, ?string $format = null): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[$key] = is_array($value)
                ? $this->date($value[0] ?? null, $value[1] ?? $format, $value[2] ?? null)
                : $this->date($value, $format);
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Числа и деньги
    |--------------------------------------------------------------------------
    */

    /**
     * Сумма денег. Вид результата (строка / float / массив), количество знаков
     * и хранение в копейках настраиваются в конфиге, секция money.
     *
     * @return string|float|array<string, mixed>|null
     */
    public function money(mixed $value, ?string $currency = null, ?int $decimals = null): string|float|array|null
    {
        if ($this->isBlank($value)) {
            return $this->nullString();
        }

        if (! is_numeric($value)) {
            $this->invalid("Значение [{$this->describe($value)}] нельзя привести к сумме.");

            return $this->nullString();
        }

        $decimals = $decimals ?? (int) $this->option('money.decimals', 2);
        $amount = (float) $value;

        if ($this->option('money.minor_units', false)) {
            $amount /= (10 ** $decimals);
        }

        $amount = round($amount, $decimals);

        $formatted = number_format(
            $amount,
            $decimals,
            (string) $this->option('money.decimal_point', '.'),
            (string) $this->option('money.thousands_separator', ''),
        );

        return match ($this->option('money.output', 'string')) {
            'float' => $amount,
            'array' => [
                'amount'    => $amount,
                'formatted' => $formatted,
                'currency'  => $currency ?? $this->option('money.currency', 'USD'),
            ],
            default => $formatted,
        };
    }

    /**
     * Число с фиксированным количеством знаков: БД отдаёт decimal строкой
     * ("10.00"), и в JSON это выглядит как строка вместо числа.
     */
    public function number(mixed $value, ?int $decimals = null): ?float
    {
        if ($this->isBlank($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            $this->invalid("Значение [{$this->describe($value)}] нельзя привести к числу.");

            return null;
        }

        return round((float) $value, $decimals ?? (int) $this->option('number.decimals', 2));
    }

    /**
     * Целое число: BIGINT и COUNT(*) приходят из БД строкой.
     */
    public function integer(mixed $value): ?int
    {
        if ($this->isBlank($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            $this->invalid("Значение [{$this->describe($value)}] нельзя привести к целому.");

            return null;
        }

        return (int) $value;
    }

    /**
     * Настоящий boolean вместо 0 / 1 / "1" / "true".
     */
    public function boolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    /**
     * Проценты: 0.1234 -> 12.34. Если в БД уже проценты — $fromFraction = false.
     */
    public function percent(mixed $value, ?int $decimals = null, bool $fromFraction = true): ?float
    {
        $number = $this->number($value, 10);

        if ($number === null) {
            return null;
        }

        return round(
            $fromFraction ? $number * 100 : $number,
            $decimals ?? (int) $this->option('number.decimals', 2),
        );
    }

    /**
     * Человекочитаемый размер файла: 1536 -> "1.5 KB".
     */
    public function bytes(mixed $value, int $precision = 1): ?string
    {
        $size = $this->number($value, 0);

        if ($size === null) {
            return $this->nullString();
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = $size > 0 ? (int) floor(log($size, 1024)) : 0;
        $power = max(0, min($power, count($units) - 1));

        return round($size / (1024 ** $power), $precision).' '.$units[$power];
    }

    /*
    |--------------------------------------------------------------------------
    | Строки, enum-ы, файлы
    |--------------------------------------------------------------------------
    */

    /**
     * Строка с обрезкой пробелов и, опционально, длины.
     */
    public function string(mixed $value, ?int $limit = null, string $end = '...'): ?string
    {
        if ($value === null) {
            return $this->nullString();
        }

        if (is_array($value) || (is_object($value) && ! method_exists($value, '__toString'))) {
            $this->invalid("Значение [{$this->describe($value)}] нельзя привести к строке.");

            return $this->nullString();
        }

        $string = trim((string) $value);

        return $string === '' ? $this->nullString() : ($limit === null ? $string : Str::limit($string, $limit, $end));
    }

    /**
     * Значение enum-а: BackedEnum -> ->value, обычный enum -> ->name.
     * Массив или коллекция enum-ов маппится поэлементно.
     */
    public function enum(mixed $value): mixed
    {
        if ($value === null) {
            return $this->nullString();
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_iterable($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                $result[$key] = $this->enum($item);
            }

            return $result;
        }

        return $value;
    }

    /**
     * Абсолютная ссылка на файл в сторадже. Уже абсолютный путь
     * (http / https / протокол-относительный / data:) возвращается как есть.
     */
    public function url(mixed $path, ?string $disk = null): ?string
    {
        $path = $this->string($path);

        if ($path === null || $path === '') {
            return $this->nullString();
        }

        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
            return $path;
        }

        /** @var \Illuminate\Contracts\Filesystem\Factory $filesystem */
        $filesystem = app('filesystem');

        return $filesystem->disk($disk ?? $this->option('files.disk'))->url($path);
    }

    /**
     * Значение переводимого поля — json-колонки вида {"en": "...", "ru": "..."}.
     * Если перевода на текущую локаль нет, берётся fallback-локаль,
     * затем первый непустой вариант.
     */
    public function translate(mixed $value, ?string $locale = null): ?string
    {
        if ($value === null) {
            return $this->nullString();
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }

        if (! is_array($value)) {
            return $this->string($value);
        }

        $candidates = array_filter([
            $locale,
            $this->locale(),
            $this->config->get('app.fallback_locale'),
        ]);

        foreach ($candidates as $candidate) {
            if (filled($value[$candidate] ?? null)) {
                return $this->string($value[$candidate]);
            }
        }

        return $this->string(Arr::first($value, static fn ($item) => filled($item)));
    }

    /**
     * Скрыть часть строки: "79001234567" -> "*******4567".
     * У e-mail маскируется только локальная часть.
     */
    public function mask(mixed $value, ?int $keepStart = null, ?int $keepEnd = null, ?string $character = null): ?string
    {
        $string = $this->string($value);

        if ($string === null || $string === '') {
            return $this->nullString();
        }

        $character = $character ?? (string) $this->option('mask.character', '*');
        $keepStart = $keepStart ?? (int) $this->option('mask.keep_start', 0);
        $keepEnd = $keepEnd ?? (int) $this->option('mask.keep_end', 4);

        if (str_contains($string, '@')) {
            [$local, $domain] = explode('@', $string, 2);

            return $this->mask($local, max($keepStart, 1), 0, $character).'@'.$domain;
        }

        $length = mb_strlen($string);

        if ($length <= $keepStart + $keepEnd) {
            return $string;
        }

        return mb_substr($string, 0, $keepStart)
            .str_repeat($character, $length - $keepStart - $keepEnd)
            .mb_substr($string, $length - $keepEnd);
    }

    /*
    |--------------------------------------------------------------------------
    | Внутреннее
    |--------------------------------------------------------------------------
    */

    /**
     * Привести произвольное значение к Carbon. Строка из одних цифр
     * трактуется как unix-время.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function toCarbon(mixed $value, ?string $timezone = null): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            // instance() создаёт копию, поэтому исходный атрибут модели не мутируем.
            $date = Carbon::instance($value);
        } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $date = Carbon::createFromTimestamp((int) $value);
        } elseif (is_string($value)) {
            try {
                $date = Carbon::parse($value);
            } catch (Throwable $e) {
                $this->invalid("Значение [{$value}] не удалось разобрать как дату.", $e);

                return null;
            }
        } else {
            $this->invalid("Значение [{$this->describe($value)}] не является датой.");

            return null;
        }

        $timezone = $timezone ?? $this->timezone();

        return $timezone === null ? $date : $date->setTimezone(new DateTimeZone($timezone));
    }

    /**
     * @param  \DateTimeInterface|string|int|null  $value
     */
    protected function formatDate(mixed $value, ?string $format, string $fallbackKey, ?string $timezone): ?string
    {
        $date = $this->toCarbon($value, $timezone);

        if ($date === null) {
            return $this->nullString();
        }

        return $date->format($this->resolveFormat($format, $fallbackKey));
    }

    /**
     * Формат может быть: null (взять formats.{$fallbackKey}), именем пресета
     * из конфига либо сырым PHP-форматом.
     */
    protected function resolveFormat(?string $format, string $fallbackKey): string
    {
        $formats = (array) $this->option('formats', []);

        if ($format === null) {
            $format = $fallbackKey;

            return isset($formats[$format]) && is_string($formats[$format])
                ? $formats[$format]
                : (self::FALLBACK_FORMATS[$fallbackKey] ?? 'Y-m-d');
        }

        // Именованный пресет из конфига, иначе — сырой PHP-формат как есть.
        return isset($formats[$format]) && is_string($formats[$format])
            ? $formats[$format]
            : $format;
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->config->get('resource-helper.'.$key, $default);
    }

    /**
     * Заменитель пустого значения для методов, отдающих строку.
     */
    protected function nullString(): ?string
    {
        $value = $this->option('null_value');

        return $value === null ? null : (string) $value;
    }

    protected function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    protected function timezone(): ?string
    {
        $timezone = $this->option('timezone');

        if ($timezone instanceof \Closure) {
            $timezone = $timezone();
        }

        return filled($timezone) ? (string) $timezone : null;
    }

    protected function locale(): ?string
    {
        $locale = $this->option('locale') ?? $this->config->get('app.locale');

        return filled($locale) ? (string) $locale : null;
    }

    /**
     * В strict-режиме бросает исключение, иначе молча пропускает —
     * вызывающий метод сам вернёт «пустое» значение.
     */
    protected function invalid(string $message, ?Throwable $previous = null): void
    {
        if ($this->option('strict', false)) {
            throw new InvalidArgumentException($message, 0, $previous);
        }
    }

    protected function describe(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }
}
