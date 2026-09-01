<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Date formats
    |--------------------------------------------------------------------------
    |
    | The format dates are rendered with across your API. The date / datetime /
    | time keys are the defaults used by $this->date(), $this->dateTime()
    | and $this->time().
    |
    | Every other key in this array is a named preset you can pass as the
    | format argument:
    |
    |     $this->date($this->created_at)            // 2026-09-01
    |     $this->date($this->created_at, 'human')   // 1 Sep 2026
    |     $this->date($this->created_at, 'd/m/Y')   // 01/09/2026  (raw format)
    |
    | A string that is not one of the presets is used verbatim as a PHP
    | date() format.
    |
    */

    'formats' => [
        'date'           => 'Y-m-d',
        'datetime'       => 'Y-m-d H:i:s',
        'time'           => 'H:i',

        // Named presets
        'iso'            => \DateTimeInterface::ATOM,
        'rfc'            => \DateTimeInterface::RFC7231,
        'human'          => 'j M Y',
        'human_datetime' => 'j M Y, H:i',
        'day_month'      => 'd.m',
        'month_year'     => 'm.Y',
    ],

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone dates are converted into before they are formatted.
    | null leaves them as they are, i.e. in app.timezone, which is what
    | Eloquent hands you for Carbon attributes.
    |
    | A string ('Europe/Kyiv', 'UTC') fixes one timezone for the whole API.
    |
    | A closure lets you resolve it per request, e.g. from the current user:
    |
    |     'timezone' => fn () => auth()->user()?->timezone,
    |
    */

    'timezone' => env('RESOURCE_HELPER_TIMEZONE'),

    /*
    |--------------------------------------------------------------------------
    | Locale for humanized output
    |--------------------------------------------------------------------------
    |
    | The locale used by diffForHumans() and Carbon's localized formats.
    | null falls back to the application locale, app()->getLocale().
    |
    */

    'locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Null value
    |--------------------------------------------------------------------------
    |
    | What to return instead of a formatted value when the input is empty
    | (null or ''). Usually null, though some frontends prefer ''.
    |
    | This applies to helpers that return a string. Helpers returning a number
    | or a boolean always return null, so their types stay honest.
    |
    */

    'null_value' => null,

    /*
    |--------------------------------------------------------------------------
    | Strict mode
    |--------------------------------------------------------------------------
    |
    | false — a value that cannot be parsed silently becomes null_value.
    | true  — an InvalidArgumentException is thrown, which is handy in tests.
    |
    */

    'strict' => false,

    /*
    |--------------------------------------------------------------------------
    | Date array
    |--------------------------------------------------------------------------
    |
    | The keys returned by $this->dateArray(). Each value is a preset or a raw
    | format; two values are special:
    |   'timestamp' — unix time as an int
    |   'human'     — diffForHumans(), e.g. "3 hours ago"
    |
    */

    'date_array' => [
        'raw'       => 'iso',
        'formatted' => 'datetime',
        'timestamp' => 'timestamp',
        'human'     => 'human',
    ],

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    |
    | output:        string | float | array
    | decimals:      digits after the decimal point
    | minor_units:   true when the database stores cents as an integer
    | currency:      the default currency
    | decimal_point / thousands_separator: separators used when output is string
    |
    */

    'money' => [
        'output'               => 'string',
        'decimals'             => 2,
        'minor_units'          => false,
        'currency'             => 'USD',
        'decimal_point'        => '.',
        'thousands_separator'  => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Numbers
    |--------------------------------------------------------------------------
    */

    'number' => [
        'decimals'            => 2,
        'decimal_point'       => '.',
        'thousands_separator' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    |
    | The disk $this->url() builds absolute file URLs from.
    | null uses the default disk from config/filesystems.php.
    |
    */

    'files' => [
        'disk' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mask
    |--------------------------------------------------------------------------
    |
    | Defaults for $this->mask(), which hides part of a string — phone numbers,
    | card numbers, e-mails — in public output.
    |
    */

    'mask' => [
        'character' => '*',
        'keep_start' => 0,
        'keep_end'   => 4,
    ],

];
