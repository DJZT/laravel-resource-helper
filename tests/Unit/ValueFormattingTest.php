<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Tests\Unit;

use Djzt\ResourceHelper\Support\Formatter;
use Djzt\ResourceHelper\Tests\Fixtures\Status;
use Djzt\ResourceHelper\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ValueFormattingTest extends TestCase
{
    protected function formatter(): Formatter
    {
        return $this->app->make(Formatter::class);
    }

    #[Test]
    public function it_formats_money_as_a_string_by_default(): void
    {
        $this->assertSame('10.00', $this->formatter()->money('10'));
        $this->assertSame('1234.57', $this->formatter()->money(1234.5678));
        $this->assertNull($this->formatter()->money(null));
    }

    #[Test]
    public function it_divides_minor_units_when_configured(): void
    {
        config()->set('resource-helper.money.minor_units', true);

        $this->assertSame('12.34', $this->formatter()->money(1234));
    }

    #[Test]
    public function it_can_return_money_as_float_or_array(): void
    {
        config()->set('resource-helper.money.output', 'float');
        $this->assertSame(10.0, $this->formatter()->money('10'));

        config()->set('resource-helper.money.output', 'array');
        config()->set('resource-helper.money.thousands_separator', ' ');

        $this->assertSame([
            'amount'    => 1234.5,
            'formatted' => '1 234.50',
            'currency'  => 'EUR',
        ], $this->formatter()->money(1234.5, 'EUR'));
    }

    #[Test]
    public function it_casts_numbers_that_arrive_from_the_database_as_strings(): void
    {
        $this->assertSame(10.0, $this->formatter()->number('10.004'));
        $this->assertSame(10.5, $this->formatter()->number('10.5'));
        $this->assertSame(42, $this->formatter()->integer('42'));
        $this->assertNull($this->formatter()->number(null));
        $this->assertNull($this->formatter()->integer(''));
        $this->assertNull($this->formatter()->number('abc'));
    }

    #[Test]
    #[DataProvider('booleanProvider')]
    public function it_casts_booleans(mixed $input, ?bool $expected): void
    {
        $this->assertSame($expected, $this->formatter()->boolean($input));
    }

    /**
     * @return array<string, array{mixed, bool|null}>
     */
    public static function booleanProvider(): array
    {
        return [
            'int one'      => [1, true],
            'int zero'     => [0, false],
            'string one'   => ['1', true],
            'string zero'  => ['0', false],
            'string true'  => ['true', true],
            'string false' => ['false', false],
            'real bool'    => [true, true],
            'null'         => [null, null],
            'empty string' => ['', null],
        ];
    }

    #[Test]
    public function it_formats_percentages(): void
    {
        $this->assertSame(12.34, $this->formatter()->percent(0.1234));
        $this->assertSame(12.3, $this->formatter()->percent(0.1234, 1));
        $this->assertSame(12.34, $this->formatter()->percent(12.34, null, false));
        $this->assertNull($this->formatter()->percent(null));
    }

    #[Test]
    public function it_formats_byte_sizes(): void
    {
        $this->assertSame('0 B', $this->formatter()->bytes(0));
        $this->assertSame('512 B', $this->formatter()->bytes(512));
        $this->assertSame('1.5 KB', $this->formatter()->bytes(1536));
        $this->assertSame('1 MB', $this->formatter()->bytes(1048576));
        $this->assertNull($this->formatter()->bytes(null));
    }

    #[Test]
    public function it_trims_and_limits_strings(): void
    {
        $this->assertSame('hello', $this->formatter()->string('  hello  '));
        $this->assertSame('hel...', $this->formatter()->string('hello world', 3));
        $this->assertNull($this->formatter()->string('   '));
        $this->assertNull($this->formatter()->string(null));
        $this->assertSame('0', $this->formatter()->string('0'));
    }

    #[Test]
    public function it_unwraps_enums(): void
    {
        $this->assertSame('draft', $this->formatter()->enum(Status::Draft));
        $this->assertSame(['draft', 'published'], $this->formatter()->enum([Status::Draft, Status::Published]));
        $this->assertNull($this->formatter()->enum(null));
        $this->assertSame('plain', $this->formatter()->enum('plain'));
    }

    #[Test]
    public function it_picks_the_translation_for_the_current_locale(): void
    {
        $value = ['en' => 'Hello', 'uk' => 'Привіт'];

        $this->assertSame('Hello', $this->formatter()->translate($value));
        $this->assertSame('Привіт', $this->formatter()->translate($value, 'uk'));
        $this->assertSame('Hello', $this->formatter()->translate(json_encode($value)));

        // Neither the requested nor the fallback locale is present, so the first
        // non-empty variant wins.
        $this->assertSame('Bonjour', $this->formatter()->translate(['de' => '', 'fr' => 'Bonjour'], 'es'));
        $this->assertNull($this->formatter()->translate(null));
    }

    #[Test]
    public function it_masks_sensitive_strings(): void
    {
        $this->assertSame('*******4567', $this->formatter()->mask('79001234567'));
        $this->assertSame('79*****4567', $this->formatter()->mask('79001234567', 2));
        $this->assertSame('1234', $this->formatter()->mask('1234'));
        $this->assertSame('j******@example.com', $this->formatter()->mask('johndoe@example.com'));
        $this->assertNull($this->formatter()->mask(null));
    }

    #[Test]
    public function it_leaves_absolute_urls_untouched(): void
    {
        $this->assertSame('https://cdn.test/a.png', $this->formatter()->url('https://cdn.test/a.png'));
        $this->assertNull($this->formatter()->url(null));
    }

    #[Test]
    public function it_builds_urls_from_the_configured_disk(): void
    {
        config()->set('filesystems.disks.media', [
            'driver' => 'local',
            'root'   => sys_get_temp_dir(),
            'url'    => 'https://cdn.test/media',
        ]);
        config()->set('resource-helper.files.disk', 'media');

        $this->assertSame('https://cdn.test/media/avatars/1.png', $this->formatter()->url('avatars/1.png'));
    }
}
