<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Tests\Fixtures;

use Djzt\ResourceHelper\HelperResource;
use Illuminate\Http\Request;

class CommentResource extends HelperResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'body'       => $this->string($this->body, 10),
            'created_at' => $this->dateTime($this->created_at),
        ];
    }
}
