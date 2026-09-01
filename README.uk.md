# Laravel Resource Helper

**[English](README.md)** · **Українська**

Хелпери для Laravel API Resources. Головне — єдиний формат дат у всьому API,
який задається однією настройкою в конфізі, плюс набір методів на решту того,
що зазвичай доводиться писати руками в кожному `toArray()`.

```php
// Було
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

## Встановлення

```bash
composer require djzt/laravel-resource-helper
```

Провайдер підхоплюється автоматично. Конфіг публікується так:

```bash
php artisan vendor:publish --tag=resource-helper-config
```

Вимоги: PHP 8.2+, Laravel 12 / 13.

## Підключення

Або успадковуєтесь від базового ресурсу:

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

Або підключаєте трейт до наявного ресурсу — переписувати нічого не треба:

```php
use Djzt\ResourceHelper\Concerns\HasResourceHelpers;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    use HasResourceHelpers;
}
```

Поза ресурсами ті самі методи доступні через фасад:

```php
use Djzt\ResourceHelper\Facades\ResourceHelper;

ResourceHelper::date($order->created_at);
```

## Дати — основне

`$this->date()` бере формат із `config('resource-helper.formats.date')`,
тож формат дат у всьому API змінюється в одному місці.

```php
$this->date($this->created_at);            // 2026-09-01
$this->date($this->created_at, 'human');   // 1 Sep 2026     — пресет із конфіга
$this->date($this->created_at, 'd/m/Y');   // 01/09/2026     — сирий PHP-формат
$this->date($this->created_at, null, 'Europe/Kyiv');
```

Другий аргумент розв'язується так: якщо рядок є серед ключів `formats`
у конфізі — це іменований пресет; якщо ні — він використовується як
звичайний формат `date()`. Обидва стилі працюють одночасно.

На вхід приймається `Carbon`, будь-який `DateTimeInterface`, рядок або
unix-час (зокрема рядок із самих цифр). Порожнє значення (`null` або `''`)
перетворюється на `config('resource-helper.null_value')` — типово `null`.
Вихідний об'єкт дати не мутується.

### Конфіг

```php
// config/resource-helper.php

'formats' => [
    'date'     => 'Y-m-d',        // <- формат $this->date()
    'datetime' => 'Y-m-d H:i:s',  // <- формат $this->dateTime()
    'time'     => 'H:i',          // <- формат $this->time()

    // іменовані пресети
    'iso'            => \DateTimeInterface::ATOM,
    'human'          => 'j M Y',
    'human_datetime' => 'j M Y, H:i',
],

// У який пояс переводити дати перед виведенням.
// null — як є; рядок — жорстко; замикання — наприклад, пояс користувача.
'timezone' => env('RESOURCE_HELPER_TIMEZONE'),

// Чим замінювати порожнє значення (деяким фронтам потрібен '' замість null).
'null_value' => null,

// true — кидати виняток на значенні, яке не розбирається, замість тихого null.
'strict' => false,
```

Часовий пояс на користувача:

```php
'timezone' => fn () => auth()->user()?->timezone,
```

### Решта методів для дат

| Метод | Результат |
| --- | --- |
| `$this->date($v, $format = null, $tz = null)` | `"2026-09-01"` |
| `$this->dateTime($v, $format = null, $tz = null)` | `"2026-09-01 13:45:07"` |
| `$this->time($v, $format = null, $tz = null)` | `"13:45"` |
| `$this->isoDate($v)` | `"2026-09-01T13:45:07+00:00"` |
| `$this->timestamp($v)` | `1788270307` |
| `$this->diffForHumans($v)` | `"3 hours ago"` |
| `$this->dateArray($v)` | усі представлення одразу |
| `$this->dates([...])` | кілька дат за раз |

`dateArray()` віддає дату в усіх виглядах одночасно — зручно, коли фронту
потрібно і машиночитне значення для сортування, і готовий рядок для показу.
Набір ключів налаштовується в `config('resource-helper.date_array')`:

```php
'published_at' => $this->dateArray($this->published_at),

// "published_at": {
//     "raw":       "2026-09-01T13:45:07+00:00",
//     "formatted": "2026-09-01 13:45:07",
//     "timestamp": 1788270307,
//     "human":     "3 hours ago"
// }
```

`dates()` підмішується через spread, щоб не повторювати `date()` для кожного поля:

```php
return [
    'id' => $this->id,
    ...$this->dates([
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        'paid_at'    => [$this->paid_at, 'human'],   // свій формат для одного поля
    ]),
];
```

## Числа

| Метод | Навіщо |
| --- | --- |
| `$this->money($v, $currency = null)` | `"1999.50"` — знаки, роздільники, копійки та валюта з конфіга |
| `$this->number($v, $decimals = null)` / `float()` | `decimal` із БД приходить рядком `"10.00"` — у JSON потрібно `10.0` |
| `$this->integer($v)` / `int()` | `BIGINT` і `COUNT(*)` теж приходять рядком |
| `$this->boolean($v)` / `bool()` | `0` / `1` / `"1"` → справжні `true` / `false` |
| `$this->percent($v)` | `0.1234` → `12.34` |
| `$this->bytes($v)` | `1536` → `"1.5 KB"` |

`money()` налаштовується секцією `money` в конфізі: кількість знаків,
роздільники, `minor_units` (якщо в БД копійки) та вигляд результату —
рядок, `float` або масив `{amount, formatted, currency}`.

## Рядки, enum-и, файли

| Метод | Навіщо |
| --- | --- |
| `$this->string($v, $limit = null)` / `str()` | trim + обрізання довжини для прев'ю |
| `$this->enum($v)` | `BackedEnum` → `value`, звичайний enum → `name`, масиви поелементно |
| `$this->url($path, $disk = null)` | відносний шлях → абсолютне посилання на файл; уже абсолютне повертається як є |
| `$this->translate($v, $locale = null)` | json-поле `{"en": "...", "uk": "..."}` → рядок для поточної локалі з fallback |
| `$this->mask($v)` | `"380501234567"` → `"********4567"`, в e-mail маскується лише локальна частина |

## Умовні атрибути

```php
// Вкладений ресурс, але лише якщо зв'язок завантажено — інакше N+1.
// Колекція чи одна модель визначається автоматично.
'author'   => $this->whenLoadedResource('author', UserResource::class),
'comments' => $this->whenLoadedResource('comments', CommentResource::class),

// Атрибут лише для тих, кому дозволяє політика (Gate::allows($ability, $this->resource)).
'email' => $this->whenCan('viewEmail', fn () => $this->email),

// Атрибут лише для автентифікованих запитів.
'balance' => $this->whenAuthenticated(fn () => $this->balance),
```

Штатні `when()`, `whenLoaded()`, `whenCounted()`, `whenNotNull()` з Laravel
нікуди не зникають і працюють як зазвичай.

## Приклад цілком

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

## Тести

```bash
composer install
vendor/bin/phpunit
```

## Ліцензія

MIT — див. [LICENSE.md](LICENSE.md).
