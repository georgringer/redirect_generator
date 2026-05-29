<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Tests\Unit\Event;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Event\BeforeRedirectAddedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BeforeRedirectAddedEvent::class)]
final class BeforeRedirectAddedEventTest extends TestCase
{
    #[Test]
    public function gettersReturnInitialValues(): void
    {
        $config = new Configuration();
        $event = new BeforeRedirectAddedEvent('/old-path', 't3://page?uid=1', $config);

        self::assertSame('/old-path', $event->getSourceUrl());
        self::assertSame('t3://page?uid=1', $event->getTargetUrl());
        self::assertSame($config, $event->getConfiguration());
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $config = new Configuration();
        $event = new BeforeRedirectAddedEvent('/old-path', 't3://page?uid=1', $config);

        $newConfig = new Configuration(targetStatusCode: 301);
        $event->setSourceUrl('/new-source');
        $event->setTargetUrl('t3://page?uid=99');
        $event->setConfiguration($newConfig);

        self::assertSame('/new-source', $event->getSourceUrl());
        self::assertSame('t3://page?uid=99', $event->getTargetUrl());
        self::assertSame($newConfig, $event->getConfiguration());
    }
}
