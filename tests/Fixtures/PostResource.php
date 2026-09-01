<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Tests\Fixtures;

use Djzt\ResourceHelper\HelperResource;
use Illuminate\Http\Request;

class PostResource extends HelperResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->string($this->title),
            'excerpt'      => $this->string($this->body, 10),
            'status'       => $this->enum($this->status),
            'is_featured'  => $this->bool($this->is_featured),
            'views'        => $this->int($this->views),
            'rating'       => $this->float($this->rating, 1),
            'price'        => $this->money($this->price),
            'attachment'   => $this->bytes($this->attachment_size),
            'published_at' => $this->date($this->published_at),
            'published_iso' => $this->isoDate($this->published_at),
            'author'       => $this->whenLoadedResource('author', AuthorResource::class),
            'comments'     => $this->whenLoadedResource('comments', CommentResource::class),
            ...$this->dates([
                'created_at' => $this->created_at,
                'updated_at' => [$this->updated_at, 'datetime'],
            ]),
        ];
    }
}
