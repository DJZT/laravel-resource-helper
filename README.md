# Laravel Resource Helper

**English** · **[Українська](README.uk.md)** · **[Русский](README.ru.md)**

Helper methods for Laravel API Resources. The main one is a single, config-driven
date format across your whole API, plus a set of methods for everything else you
otherwise end up writing by hand in every `toArray()`.

```php
// Before
'created_at' => $this->created_at?->format('Y-m-d'),
'price'      => number_format($this->price / 100, 2, '.', ''),
'is_active'  => (bool) $this->is_active,
'status'     => $this->status?->value,

// After
'created_at' => $this->date($this->created_at),
'price'      => $this->money($this->price),
'is_active'  => $this->bool($this->is_active),
'status'     => $this->enum($this->status),
```

## Installation

```bash
composer require djzt/laravel-resource-helper
```

The service provider is auto-discovered. Publish the config with:

```bash
php artisan vendor:publish --tag=resource-helper-config
```

Requires PHP 8.1+ and Laravel 10, 11 or 12.

## Setup

Either extend the base resource:

```php
use Djzt\ResourceHelper\HelperResource;

class PostResource extends HelperResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->string($this->title),
            'created_at' => $this->date($this->created_at),
        ];
    }
}
```

Or pull the trait into an existing resource — nothing else has to change:

```php
use Djzt\ResourceHelper\Concerns\HasResourceHelpers;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    use HasResourceHelpers;
}
```

Outside of resources the same methods are available through the facade:

```php
use Djzt\ResourceHelper\Facades\ResourceHelper;

ResourceHelper::date($order->created_at);
```

## Dates — the main feature

`$this->date()` takes its format from `config('resource-helper.formats.date')`,
so the date format across your entire API changes in one place.

```php
$this->date($this->created_at);            // 2026-09-01
$this->date($this->created_at, 'human');   // 1 Sep 2026     — preset from config
$this->date($this->created_at, 'd/m/Y');   // 01/09/2026     — raw PHP format
$this->date($this->created_at, null, 'Europe/Kyiv');
```

The second argument resolves like this: if the string is a key in the config's
`formats` array, it is a named preset; otherwise it is used verbatim as a PHP
`date()` format. Both styles work side by side.

Accepted input: `Carbon`, any `DateTimeInterface`, a string, or a unix timestamp
(including a digits-only string). An empty value (`null` or `''`) becomes
`config('resource-helper.null_value')`, which defaults to `null`.
The original date object is never mutated.

### Configuration

```php
// config/resource-helper.php

'formats' => [
    'date'     => 'Y-m-d',        // <- used by $this->date()
    'datetime' => 'Y-m-d H:i:s',  // <- used by $this->dateTime()
    'time'     => 'H:i',          // <- used by $this->time()

    // named presets
    'iso'            => \DateTimeInterface::ATOM,
    'human'          => 'j M Y',
    'human_datetime' => 'j M Y, H:i',
],

// Timezone to convert dates into before formatting.
// null — leave as is; a string — fixed for the whole API;
// a closure — e.g. the current user's timezone.
'timezone' => env('RESOURCE_HELPER_TIMEZONE'),

// What to return instead of an empty value (some frontends want '' over null).
'null_value' => null,

// true — throw on an unparsable value instead of silently returning null.
'strict' => false,
```

Per-user timezone:

```php
'timezone' => fn () => auth()->user()?->timezone,
```

### The other date methods

| Method | Result |
| --- | --- |
| `$this->date($v, $format = null, $tz = null)` | `"2026-09-01"` |
| `$this->dateTime($v, $format = null, $tz = null)` | `"2026-09-01 13:45:07"` |
| `$this->time($v, $format = null, $tz = null)` | `"13:45"` |
| `$this->isoDate($v)` | `"2026-09-01T13:45:07+00:00"` |
| `$this->timestamp($v)` | `1788270307` |
| `$this->diffForHumans($v)` | `"3 hours ago"` |
| `$this->dateArray($v)` | every representation at once |
| `$this->dates([...])` | several dates in one call |

`dateArray()` returns the date in all forms at once — handy when the frontend
needs both a machine-readable value for sorting and a ready-to-display string.
The set of keys is configured in `config('resource-helper.date_array')`:

```php
'published_at' => $this->dateArray($this->published_at),

// "published_at": {
//     "raw":       "2026-09-01T13:45:07+00:00",
//     "formatted": "2026-09-01 13:45:07",
//     "timestamp": 1788270307,
//     "human":     "3 hours ago"
// }
```

`dates()` is meant to be spread in, so you don't repeat `date()` per field:

```php
return [
    'id' => $this->id,
    ...$this->dates([
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        'paid_at'    => [$this->paid_at, 'human'],   // per-field format
    ]),
];
```

## Numbers

| Method | Why |
| --- | --- |
| `$this->money($v, $currency = null)` | `"1999.50"` — decimals, separators, minor units and currency from config |
| `$this->number($v, $decimals = null)` / `float()` | a `decimal` column arrives as the string `"10.00"`; JSON wants `10.0` |
| `$this->integer($v)` / `int()` | `BIGINT` and `COUNT(*)` also arrive as strings |
| `$this->boolean($v)` / `bool()` | `0` / `1` / `"1"` → a real `true` / `false` |
| `$this->percent($v)` | `0.1234` → `12.34` |
| `$this->bytes($v)` | `1536` → `"1.5 KB"` |

`money()` is driven by the `money` section of the config: number of decimals,
separators, `minor_units` (when the database stores cents), and the shape of the
result — a string, a `float`, or an array of `{amount, formatted, currency}`.

## Strings, enums, files

| Method | Why |
| --- | --- |
| `$this->string($v, $limit = null)` / `str()` | trim plus optional truncation for previews |
| `$this->enum($v)` | `BackedEnum` → `value`, pure enum → `name`, arrays element-wise |
| `$this->url($path, $disk = null)` | a relative path → an absolute file URL; an already absolute one is returned untouched |
| `$this->translate($v, $locale = null)` | a JSON column like `{"en": "...", "uk": "..."}` → the string for the current locale, with fallback |
| `$this->mask($v)` | `"380501234567"` → `"********4567"`; for an e-mail only the local part is masked |

## Conditional attributes

```php
// A nested resource, but only when the relation is loaded — otherwise N+1.
// Collection vs. single model is detected automatically.
'author'   => $this->whenLoadedResource('author', UserResource::class),
'comments' => $this->whenLoadedResource('comments', CommentResource::class),

// An attribute only for those the policy allows (Gate::allows($ability, $this->resource)).
'email' => $this->whenCan('viewEmail', fn () => $this->email),

// An attribute only for authenticated requests.
'balance' => $this->whenAuthenticated(fn () => $this->balance),
```

Laravel's own `when()`, `whenLoaded()`, `whenCounted()` and `whenNotNull()` are
untouched and keep working as usual.

## A full example

```php
class PostResource extends HelperResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->translate($this->title),
            'excerpt'     => $this->string($this->body, 160),
            'status'      => $this->enum($this->status),
            'is_featured' => $this->bool($this->is_featured),
            'views'       => $this->int($this->views),
            'price'       => $this->money($this->price),
            'cover'       => $this->url($this->cover_path),
            'attachment'  => $this->bytes($this->attachment_size),

            'published_at' => $this->dateArray($this->published_at),
            ...$this->dates([
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]),

            'author'   => $this->whenLoadedResource('author', UserResource::class),
            'comments' => $this->whenLoadedResource('comments', CommentResource::class),
        ];
    }
}
```

## Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT — see [LICENSE.md](LICENSE.md).
