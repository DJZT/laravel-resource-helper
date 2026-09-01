<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Tests\Feature;

use Djzt\ResourceHelper\Tests\Fixtures\Author;
use Djzt\ResourceHelper\Tests\Fixtures\AuthorResource;
use Djzt\ResourceHelper\Tests\Fixtures\Comment;
use Djzt\ResourceHelper\Tests\Fixtures\Post;
use Djzt\ResourceHelper\Tests\Fixtures\PostResource;
use Djzt\ResourceHelper\Tests\Fixtures\Status;
use Djzt\ResourceHelper\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class ResourceIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('authors', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('posts', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('author_id')->nullable();
            $table->string('title');
            $table->text('body');
            $table->string('status');
            $table->string('is_featured');
            $table->string('views');
            $table->string('rating');
            $table->string('price');
            $table->unsignedBigInteger('attachment_size');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->text('body');
            $table->timestamps();
        });
    }

    protected function makePost(): Post
    {
        $author = Author::create(['name' => '  Jane  ', 'email' => 'jane@example.com']);

        $post = Post::create([
            'author_id'       => $author->id,
            'title'           => 'Hello world',
            'body'            => 'A fairly long body that should be truncated',
            'status'          => Status::Published,
            'is_featured'     => '1',
            'views'           => '1024',
            'rating'          => '4.26',
            'price'           => '1999.5',
            'attachment_size' => 1536,
            'published_at'    => '2026-09-01 13:45:07',
            'created_at'      => '2026-09-01 13:45:07',
            'updated_at'      => '2026-09-01 13:45:07',
        ]);

        Comment::create(['post_id' => $post->id, 'body' => 'Nice one, thanks for sharing']);

        return $post;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolve(Post $post): array
    {
        return (new PostResource($post))->toArray(Request::create('/'));
    }

    #[Test]
    public function it_formats_every_attribute_through_the_helpers(): void
    {
        $result = $this->resolve($this->makePost());

        $this->assertSame('Hello world', $result['title']);
        $this->assertSame('A fairly l...', $result['excerpt']);
        $this->assertSame('published', $result['status']);
        $this->assertTrue($result['is_featured']);
        $this->assertSame(1024, $result['views']);
        $this->assertSame(4.3, $result['rating']);
        $this->assertSame('1999.50', $result['price']);
        $this->assertSame('1.5 KB', $result['attachment']);
        $this->assertSame('2026-09-01', $result['published_at']);
        $this->assertSame('2026-09-01T13:45:07+00:00', $result['published_iso']);
        $this->assertSame('2026-09-01', $result['created_at']);
        $this->assertSame('2026-09-01 13:45:07', $result['updated_at']);
    }

    #[Test]
    public function the_configured_format_drives_the_whole_api(): void
    {
        config()->set('resource-helper.formats.date', 'd.m.Y');

        $result = $this->resolve($this->makePost());

        $this->assertSame('01.09.2026', $result['published_at']);
        $this->assertSame('01.09.2026', $result['created_at']);
    }

    #[Test]
    public function the_configured_timezone_drives_the_whole_api(): void
    {
        config()->set('resource-helper.timezone', 'Europe/Moscow');
        config()->set('resource-helper.formats.date', 'Y-m-d H:i');

        $post = $this->makePost();
        $post->published_at = '2026-09-01 23:30:00';

        $this->assertSame('2026-09-02 02:30', $this->resolve($post)['published_at']);
    }

    #[Test]
    public function it_omits_relations_that_are_not_loaded(): void
    {
        $result = $this->resolve($this->makePost());

        $this->assertInstanceOf(MissingValue::class, $result['author']);
        $this->assertInstanceOf(MissingValue::class, $result['comments']);
    }

    #[Test]
    public function it_wraps_a_loaded_belongs_to_relation_in_its_resource(): void
    {
        $post = $this->makePost()->load('author');

        $author = $this->resolve($post)['author'];

        $this->assertInstanceOf(AuthorResource::class, $author);
        $this->assertSame('Jane', $author->toArray(Request::create('/'))['name']);
    }

    #[Test]
    public function it_wraps_a_loaded_has_many_relation_in_a_resource_collection(): void
    {
        $post = $this->makePost()->load('comments');

        $comments = $this->resolve($post)['comments'];

        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class, $comments);
        $this->assertCount(1, $comments->collection);
        $this->assertSame('Nice one,...', $comments->collection->first()->toArray(Request::create('/'))['body']);
    }

    #[Test]
    public function when_can_hides_the_attribute_unless_the_gate_allows_it(): void
    {
        $author = $this->makePost()->load('author')->author;

        Gate::define('viewEmail', static fn ($user = null) => false);

        $this->assertArrayNotHasKey(
            'email',
            (new AuthorResource($author))->resolve(Request::create('/')),
        );

        Gate::define('viewEmail', static fn ($user = null) => true);

        $this->assertSame(
            'jane@example.com',
            (new AuthorResource($author))->resolve(Request::create('/'))['email'],
        );
    }

    #[Test]
    public function it_serialises_to_json_through_the_full_response_pipeline(): void
    {
        config()->set('resource-helper.formats.date', 'd.m.Y');

        $json = (new PostResource($this->makePost()))
            ->response(Request::create('/'))
            ->getData(true);

        $this->assertSame('01.09.2026', $json['data']['published_at']);
        $this->assertArrayNotHasKey('author', $json['data']);
    }
}
