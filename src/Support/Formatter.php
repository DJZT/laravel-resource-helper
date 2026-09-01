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
 * Formats values for an API response.
 *
 * All of the logic lives here so it can be reused outside resources — in
 * controllers, jobs, exports — and tested without a Laravel resource.
 *
 * An empty value (null / '') becomes config('resource-helper.null_value') in
 * helpers that return a string, and null in those returning a number or a bool.
 */
class Formatter
{
    /**
     * Fallbacks for when the config is not published, or a key was removed.
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
    | Dates
    |--------------------------------------------------------------------------
    */

    /**
     * A date in the configured format (formats.date).
     *
     * @param  \DateTimeInterface|string|int|null  $value
     * @param  string|null  $format  a preset from the config, or a raw PHP format
     */
    public function date(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatDate($value, $format, 'date', $timezone);
    }

    /**
     * A date and time in the configured format (formats.datetime).
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function dateTime(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatDate($value, $format, 'datetime', $timezone);
    }

    /**
     * A time in the configured format (formats.time).
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function time(mixed $value, ?string $format = null, ?string $timezone = null): ?string
    {
        return $this->formatDate($value, $format, 'time', $timezone);
    }

    /**
     * ISO-8601 — the portable choice for a frontend: new Date(...) parses it.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function isoDate(mixed $value, ?string $timezone = null): ?string
    {
        return $this->formatDate($value, 'iso', 'datetime', $timezone);
    }

    /**
     * Unix time.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function timestamp(mixed $value): ?int
    {
        return $this->toCarbon($value)?->getTimestamp();
    }

    /**
     * "3 hours ago", in the locale from the config.
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
     * One date in every representation at once; the set of keys comes from the
     * config (date_array). Useful when the frontend needs both a machine-readable
     * value and a string that is ready to display.
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
     * Format several dates in one call.
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
    | Numbers and money
    |--------------------------------------------------------------------------
    */

    /**
     * A monetary amount. The shape of the result (string / float / array), the
     * number of decimals and whether cents are stored are set in the money
     * section of the config.
     *
     * @return string|float|array<string, mixed>|null
     */
    public function money(mixed $value, ?string $currency = null, ?int $decimals = null): string|float|array|null
    {
        if ($this->isBlank($value)) {
            return $this->nullString();
        }

        if (! is_numeric($value)) {
            $this->invalid("The value [{$this->describe($value)}] cannot be read as an amount.");

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
     * A number with a fixed number of decimals: the database hands back a
     * decimal as the string "10.00", which then shows up in JSON as a string.
     */
    public function number(mixed $value, ?int $decimals = null): ?float
    {
        if ($this->isBlank($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            $this->invalid("The value [{$this->describe($value)}] cannot be read as a number.");

            return null;
        }

        return round((float) $value, $decimals ?? (int) $this->option('number.decimals', 2));
    }

    /**
     * An integer: BIGINT and COUNT(*) also arrive from the database as strings.
     */
    public function integer(mixed $value): ?int
    {
        if ($this->isBlank($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            $this->invalid("The value [{$this->describe($value)}] cannot be read as an integer.");

            return null;
        }

        return (int) $value;
    }

    /**
     * A real boolean instead of 0 / 1 / "1" / "true".
     */
    public function boolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    /**
     * Percentages: 0.1234 -> 12.34. Pass $fromFraction = false when the column
     * already holds percents.
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
     * A human-readable file size: 1536 -> "1.5 KB".
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
    | Strings, enums, files
    |--------------------------------------------------------------------------
    */

    /**
     * A trimmed string, optionally truncated to a length.
     */
    public function string(mixed $value, ?int $limit = null, string $end = '...'): ?string
    {
        if ($value === null) {
            return $this->nullString();
        }

        if (is_array($value) || (is_object($value) && ! method_exists($value, '__toString'))) {
            $this->invalid("The value [{$this->describe($value)}] cannot be read as a string.");

            return $this->nullString();
        }

        $string = trim((string) $value);

        return $string === '' ? $this->nullString() : ($limit === null ? $string : Str::limit($string, $limit, $end));
    }

    /**
     * An enum's value: BackedEnum -> ->value, a pure enum -> ->name.
     * An array or collection of enums is mapped element-wise.
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
     * An absolute URL for a file on a disk. A path that is already absolute
     * (http / https / protocol-relative / data:) is returned untouched.
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
     * The value of a translatable field — a JSON column like
     * {"en": "...", "uk": "..."}. When there is no translation for the current
     * locale, the fallback locale is used, then the first non-empty variant.
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
     * Hide part of a string: "380501234567" -> "********4567".
     * For an e-mail only the local part is masked.
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
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Coerce an arbitrary value into Carbon. A digits-only string is read as
     * unix time.
     *
     * @param  \DateTimeInterface|string|int|null  $value
     */
    public function toCarbon(mixed $value, ?string $timezone = null): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            // instance() makes a copy, so the model's own attribute is never mutated.
            $date = Carbon::instance($value);
        } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $date = Carbon::createFromTimestamp((int) $value);
        } elseif (is_string($value)) {
            try {
                $date = Carbon::parse($value);
            } catch (Throwable $e) {
                $this->invalid("The value [{$value}] could not be parsed as a date.", $e);

                return null;
            }
        } else {
            $this->invalid("The value [{$this->describe($value)}] is not a date.");

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
     * The format may be null (use formats.{$fallbackKey}), the name of a preset
     * from the config, or a raw PHP format.
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

        // A named preset from the config, otherwise the raw PHP format as given.
        return isset($formats[$format]) && is_string($formats[$format])
            ? $formats[$format]
            : $format;
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->config->get('resource-helper.'.$key, $default);
    }

    /**
     * The stand-in for an empty value, for helpers that return a string.
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
     * Throws in strict mode; otherwise it stays quiet and the calling method
     * returns the empty value itself.
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
