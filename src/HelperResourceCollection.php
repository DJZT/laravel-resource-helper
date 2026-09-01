<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper;

use Djzt\ResourceHelper\Concerns\HasResourceHelpers;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A base resource collection with the helpers wired in — useful for the
 * aggregates you put in meta, such as totals and date ranges.
 */
abstract class HelperResourceCollection extends ResourceCollection
{
    use HasResourceHelpers;
}
