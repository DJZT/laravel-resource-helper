<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper;

use Djzt\ResourceHelper\Concerns\HasResourceHelpers;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A base resource with the helpers already wired in.
 *
 * Extend this instead of JsonResource, or pull the
 * \Djzt\ResourceHelper\Concerns\HasResourceHelpers trait into an existing one.
 */
abstract class HelperResource extends JsonResource
{
    use HasResourceHelpers;
}
