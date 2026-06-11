<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Domain\Model\Dto;

readonly class Configuration
{
    public const ALLOWED_STATUS_CODES = [301, 302, 303, 307];

    public function __construct(
        public int $targetStatusCode = 307,
        public bool $keepQueryParameters = false,
        public bool $forceHttps = false,
        public bool $isRegexp = false,
        public bool $respectQueryParameters = false,
        public bool $disableHitCount = false,
    ) {}

    public static function statusCodeIsAllowed(int $statusCode): bool
    {
        return in_array($statusCode, self::ALLOWED_STATUS_CODES, true);
    }
}
