<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Tests\Fixtures;

use Djzt\ResourceHelper\HelperResource;
use Illuminate\Http\Request;

class AuthorResource extends HelperResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->string($this->name),
            'email' => $this->whenCan('viewEmail', fn () => $this->email),
        ];
    }
}
