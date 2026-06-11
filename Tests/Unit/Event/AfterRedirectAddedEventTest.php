<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Tests\Unit\Event;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Event\AfterRedirectAddedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AfterRedirectAddedEventTest extends TestCase
{
    #[Test]
    public function readonlyPropertiesAreSetCorrectly(): void
    {
        $config = new Configuration(targetStatusCode: 301);
        $event = new AfterRedirectAddedEvent('/source', 't3://page?uid=7', $config, 42);

        self::assertSame('/source', $event->sourceUrl);
        self::assertSame('t3://page?uid=7', $event->targetUrl);
        self::assertSame($config, $event->configuration);
        self::assertSame(42, $event->uid);
    }
}
