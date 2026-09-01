# Laravel Resource Helper

Хелперы для Laravel API Resources. Главное — единый формат дат во всём API,
задаваемый одной настройкой в конфиге, плюс набор методов на всё остальное,
что обычно приходится писать в каждом `toArray()` руками.

```php
// Было
'created_at' => $this->created_at?->format('Y-m-d'),
'price'      => number_format($this->price / 100, 2, '.', ''),
'is_active'  => (bool) $this->is_active,
'status'     => $this->status?->value,

// Стало
'created_at' => $this->date($this->created_at),
'price'      => $this->money($this->price),
'is_active'  => $this->bool($this->is_active),
'status'     => $this->enum($this->status),
```

## Установка

```bash
composer require djzt/laravel-resource-helper
```

Провайдер подхватывается автоматически. Конфиг публикуется так:

```bash
php artisan vendor:publish --tag=resource-helper-config
```

Требования: PHP 8.1+, Laravel 10 / 11 / 12.

## Подключение

Либо наследуетесь от базового ресурса:

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

Либо подключаете трейт в существующий ресурс — ничего переписывать не нужно:

```php
use Djzt\ResourceHelper\Concerns\HasResourceHelpers;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    use HasResourceHelpers;
}
```

Вне ресурсов те же методы доступны через фасад:

```php
use Djzt\ResourceHelper\Facades\ResourceHelper;

ResourceHelper::date($order->created_at);
```

## Даты — основное

`$this->date()` берёт формат из `config('resource-helper.formats.date')`,
поэтому формат дат во всём API меняется в одном месте.

```php
$this->date($this->created_at);            // 2026-09-01
$this->date($this->created_at, 'human');   // 1 Sep 2026     — пресет из конфига
$this->date($this->created_at, 'd/m/Y');   // 01/09/2026     — сырой PHP-формат
$this->date($this->created_at, null, 'Europe/Moscow');
```

Второй аргумент разрешается так: если строка есть среди ключей
`formats` в конфиге — это именованный пресет; если нет — она используется
как обычный формат `date()`. Так что оба стиля работают одновременно.

На вход принимается `Carbon`, любой `DateTimeInterface`, строка или unix-время
(включая строку из одних цифр). Пустое значение (`null` или `''`) превращается
в `config('resource-helper.null_value')` — по умолчанию `null`.
Исходный объект даты не мутируется.

### Конфиг

```php
// config/resource-helper.php

'formats' => [
    'date'     => 'Y-m-d',        // <- формат $this->date()
    'datetime' => 'Y-m-d H:i:s',  // <- формат $this->dateTime()
    'time'     => 'H:i',          // <- формат $this->time()

    // именованные пресеты
    'iso'            => \DateTimeInterface::ATOM,
    'human'          => 'j M Y',
    'human_datetime' => 'j M Y, H:i',
],

// В какой пояс переводить даты перед выводом.
// null — как есть; строка — жёстко; замыкание — например, пояс пользователя.
'timezone' => env('RESOURCE_HELPER_TIMEZONE'),

// Чем заменять пустое значение (некоторым фронтам нужен '' вместо null).
'null_value' => null,

// true — бросать исключение на непарсящемся значении вместо тихого null.
'strict' => false,
```

Часовой пояс на пользователя:

```php
'timezone' => fn () => auth()->user()?->timezone,
```

### Остальные методы для дат

| Метод | Результат |
| --- | --- |
| `$this->date($v, $format = null, $tz = null)` | `"2026-09-01"` |
| `$this->dateTime($v, $format = null, $tz = null)` | `"2026-09-01 13:45:07"` |
| `$this->time($v, $format = null, $tz = null)` | `"13:45"` |
| `$this->isoDate($v)` | `"2026-09-01T13:45:07+00:00"` |
| `$this->timestamp($v)` | `1788270307` |
| `$this->diffForHumans($v)` | `"3 hours ago"` |
| `$this->dateArray($v)` | все представления сразу |
| `$this->dates([...])` | несколько дат за раз |

`dateArray()` отдаёт дату во всех видах одновременно — удобно, когда фронту
нужно и машиночитаемое значение для сортировки, и готовая строка для показа.
Набор ключей настраивается в `config('resource-helper.date_array')`:

```php
'published_at' => $this->dateArray($this->published_at),

// "published_at": {
//     "raw":       "2026-09-01T13:45:07+00:00",
//     "formatted": "2026-09-01 13:45:07",
//     "timestamp": 1788270307,
//     "human":     "3 hours ago"
// }
```

`dates()` подмешивается через spread, чтобы не повторять `date()` для каждого поля:

```php
return [
    'id' => $this->id,
    ...$this->dates([
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        'paid_at'    => [$this->paid_at, 'human'],   // свой формат для одного поля
    ]),
];
```

## Числа

| Метод | Зачем |
| --- | --- |
| `$this->money($v, $currency = null)` | `"1999.50"` — знаки, разделители, копейки, валюта из конфига |
| `$this->number($v, $decimals = null)` / `float()` | `decimal` из БД приходит строкой `"10.00"` — в JSON нужен `10.0` |
| `$this->integer($v)` / `int()` | `BIGINT` и `COUNT(*)` тоже приходят строкой |
| `$this->boolean($v)` / `bool()` | `0` / `1` / `"1"` → настоящий `true`/`false` |
| `$this->percent($v)` | `0.1234` → `12.34` |
| `$this->bytes($v)` | `1536` → `"1.5 KB"` |

`money()` настраивается секцией `money` в конфиге: количество знаков,
разделители, `minor_units` (если в БД копейки), и вид результата —
строка, `float` или массив `{amount, formatted, currency}`.

## Строки, enum-ы, файлы

| Метод | Зачем |
| --- | --- |
| `$this->string($v, $limit = null)` / `str()` | trim + обрезка длины для превью |
| `$this->enum($v)` | `BackedEnum` → `value`, обычный enum → `name`, массивы поэлементно |
| `$this->url($path, $disk = null)` | относительный путь → абсолютная ссылка на файл; уже абсолютная возвращается как есть |
| `$this->translate($v, $locale = null)` | json-поле `{"en": "...", "ru": "..."}` → строка для текущей локали с fallback |
| `$this->mask($v)` | `"79001234567"` → `"*******4567"`, у e-mail маскируется только локальная часть |

## Условные атрибуты

```php
// Вложенный ресурс, но только если связь загружена — иначе N+1.
// Коллекция или одна модель определяется автоматически.
'author'   => $this->whenLoadedResource('author', UserResource::class),
'comments' => $this->whenLoadedResource('comments', CommentResource::class),

// Атрибут только для тех, кому разрешает политика (Gate::allows($ability, $this->resource)).
'email' => $this->whenCan('viewEmail', fn () => $this->email),

// Атрибут только для аутентифицированных запросов.
'balance' => $this->whenAuthenticated(fn () => $this->balance),
```

Штатные `when()`, `whenLoaded()`, `whenCounted()`, `whenNotNull()` из Laravel
никуда не деваются и работают как обычно.

## Пример целиком

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

## Тесты

```bash
composer install
vendor/bin/phpunit
```

## Лицензия

MIT — см. [LICENSE.md](LICENSE.md).
