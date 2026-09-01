<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Tests\Unit;

use Djzt\ResourceHelper\Support\Formatter;
use Djzt\ResourceHelper\Tests\TestCase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class DateFormattingTest extends TestCase
{
    protected function formatter(): Formatter
    {
        return $this->app->make(Formatter::class);
    }

    #[Test]
    public function it_uses_the_configured_default_date_format(): void
    {
        $date = Carbon::parse('2026-09-01 13:45:07', 'UTC');

        $this->assertSame('2026-09-01', $this->formatter()->date($date));

        config()->set('resource-helper.formats.date', 'd.m.Y');

        $this->assertSame('01.09.2026', $this->formatter()->date($date));
    }

    #[Test]
    public function it_uses_the_configured_datetime_and_time_formats(): void
    {
        $date = Carbon::parse('2026-09-01 13:45:07', 'UTC');

        $this->assertSame('2026-09-01 13:45:07', $this->formatter()->dateTime($date));
        $this->assertSame('13:45', $this->formatter()->time($date));

        config()->set('resource-helper.formats.datetime', 'd.m.Y H:i');
        config()->set('resource-helper.formats.time', 'H:i:s');

        $this->assertSame('01.09.2026 13:45', $this->formatter()->dateTime($date));
        $this->assertSame('13:45:07', $this->formatter()->time($date));
    }

    #[Test]
    public function it_resolves_a_named_preset_from_config(): void
    {
        $date = Carbon::parse('2026-09-01 13:45:07', 'UTC');

        $this->assertSame('1 Sep 2026', $this->formatter()->date($date, 'human'));
        $this->assertSame('2026-09-01T13:45:07+00:00', $this->formatter()->date($date, 'iso'));
    }

    #[Test]
    public function it_treats_an_unknown_format_string_as_a_raw_php_format(): void
    {
        $date = Carbon::parse('2026-09-01 13:45:07', 'UTC');

        $this->assertSame('01/09/2026', $this->formatter()->date($date, 'd/m/Y'));
        $this->assertSame('2026', $this->formatter()->date($date, 'Y'));
    }

    #[Test]
    public function it_accepts_strings_timestamps_and_datetime_objects(): void
    {
        $this->assertSame('2026-09-01', $this->formatter()->date('2026-09-01 13:45:07'));
        $this->assertSame('2026-09-01', $this->formatter()->date(new \DateTimeImmutable('2026-09-01 13:45:07', new \DateTimeZone('UTC'))));
        $this->assertSame('2026-09-01', $this->formatter()->date(1788270307));
        $this->assertSame('2026-09-01', $this->formatter()->date('1788270307'));
    }

    #[Test]
    public function it_returns_the_configured_null_value_for_empty_input(): void
    {
        $this->assertNull($this->formatter()->date(null));
        $this->assertNull($this->formatter()->date(''));

        config()->set('resource-helper.null_value', '');

        $this->assertSame('', $this->formatter()->date(null));
    }

    #[Test]
    public function it_converts_to_the_configured_timezone(): void
    {
        $date = Carbon::parse('2026-09-01 23:30:00', 'UTC');

        config()->set('resource-helper.timezone', 'Europe/Moscow');

        $this->assertSame('2026-09-02 02:30:00', $this->formatter()->dateTime($date));
        $this->assertSame('2026-09-01 23:30:00', $this->formatter()->dateTime($date, null, 'UTC'));
    }

    #[Test]
    public function it_accepts_a_closure_as_the_configured_timezone(): void
    {
        config()->set('resource-helper.timezone', static fn () => 'Europe/Moscow');

        $this->assertSame(
            '2026-09-02 02:30:00',
            $this->formatter()->dateTime(Carbon::parse('2026-09-01 23:30:00', 'UTC')),
        );
    }

    #[Test]
    public function it_does_not_mutate_the_original_date_instance(): void
    {
        config()->set('resource-helper.timezone', 'Europe/Moscow');

        $date = Carbon::parse('2026-09-01 23:30:00', 'UTC');
        $this->formatter()->dateTime($date);

        $this->assertSame('UTC', $date->timezone->getName());
        $this->assertSame('2026-09-01 23:30:00', $date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_returns_a_timestamp_and_an_iso_string(): void
    {
        $date = Carbon::parse('2026-09-01 13:45:07', 'UTC');

        $this->assertSame(1788270307, $this->formatter()->timestamp($date));
        $this->assertSame('2026-09-01T13:45:07+00:00', $this->formatter()->isoDate($date));
        $this->assertNull($this->formatter()->timestamp(null));
    }

    #[Test]
    public function it_builds_a_date_array_from_config(): void
    {
        $result = $this->formatter()->dateArray(Carbon::parse('2026-09-01 13:45:07', 'UTC'));

        $this->assertSame(
            ['raw', 'formatted', 'timestamp', 'human'],
            array_keys($result),
        );
        $this->assertSame('2026-09-01T13:45:07+00:00', $result['raw']);
        $this->assertSame('2026-09-01 13:45:07', $result['formatted']);
        $this->assertSame(1788270307, $result['timestamp']);
        $this->assertIsString($result['human']);

        $this->assertNull($this->formatter()->dateArray(null));
    }

    #[Test]
    public function it_formats_several_dates_at_once(): void
    {
        $result = $this->formatter()->dates([
            'created_at' => Carbon::parse('2026-09-01 13:45:07', 'UTC'),
            'paid_at'    => [Carbon::parse('2026-09-02 13:45:07', 'UTC'), 'human'],
            'closed_at'  => null,
        ]);

        $this->assertSame([
            'created_at' => '2026-09-01',
            'paid_at'    => '2 Sep 2026',
            'closed_at'  => null,
        ], $result);
    }

    #[Test]
    public function it_swallows_unparsable_dates_by_default(): void
    {
        $this->assertNull($this->formatter()->date('not a date at all'));
    }

    #[Test]
    public function it_throws_on_unparsable_dates_in_strict_mode(): void
    {
        config()->set('resource-helper.strict', true);

        $this->expectException(InvalidArgumentException::class);

        $this->formatter()->date('not a date at all');
    }

    #[Test]
    public function it_falls_back_when_a_format_key_is_missing_from_config(): void
    {
        config()->set('resource-helper.formats', []);

        $this->assertSame('2026-09-01', $this->formatter()->date(Carbon::parse('2026-09-01 13:45:07', 'UTC')));
    }
}
