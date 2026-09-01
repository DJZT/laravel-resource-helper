<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper;

use Djzt\ResourceHelper\Concerns\HasResourceHelpers;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Базовая коллекция ресурсов с подключёнными хелперами —
 * полезно для агрегатов в meta (суммы, диапазоны дат).
 */
abstract class HelperResourceCollection extends ResourceCollection
{
    use HasResourceHelpers;
}
