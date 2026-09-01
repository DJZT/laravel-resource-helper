<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper;

use Djzt\ResourceHelper\Concerns\HasResourceHelpers;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Базовый ресурс с подключёнными хелперами.
 *
 * Наследуйтесь от него вместо JsonResource — либо подключайте трейт
 * \Djzt\ResourceHelper\Concerns\HasResourceHelpers в существующий ресурс.
 */
abstract class HelperResource extends JsonResource
{
    use HasResourceHelpers;
}
