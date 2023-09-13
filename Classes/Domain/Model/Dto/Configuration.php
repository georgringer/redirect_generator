<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Domain\Model\Dto;


class Configuration
{

    protected bool $respectQueryParameters = false;
    protected bool $keepQueryParameters = false;
    protected bool $forceHttps = false;
    protected bool $isRegexp = false;
    protected int $targetStatusCode = 307;
    protected bool $disableHitCount = false;

    private const ALLOWED_STATUS_CODES = [301, 302, 303, 307];

    public function getRespectQueryParameters(): bool
    {
        return $this->respectQueryParameters;
    }

    public function setRespectQueryParameters(bool $respectQueryParameters): Configuration
    {
        $this->respectQueryParameters = $respectQueryParameters;
        return $this;
    }

    public function getKeepQueryParameters(): bool
    {
        return $this->keepQueryParameters;
    }

    public function setKeepQueryParameters(bool $keepQueryParameters): Configuration
    {
        $this->keepQueryParameters = $keepQueryParameters;
        return $this;
    }

    public function getForceHttps(): bool
    {
        return $this->forceHttps;
    }

    public function setForceHttps(bool $forceHttps): Configuration
    {
        $this->forceHttps = $forceHttps;
        return $this;
    }

    public function getRegexp(): bool
    {
        return $this->isRegexp;
    }

    public function setIsRegexp(bool $isRegexp): Configuration
    {
        $this->isRegexp = $isRegexp;
        return $this;
    }

    public function getTargetStatusCode(): int
    {
        return $this->targetStatusCode;
    }

    public function setTargetStatusCode(int $targetStatusCode): Configuration
    {
        $this->targetStatusCode = $targetStatusCode;
        return $this;
    }

    public function getDisableHitCount(): bool
    {
        return $this->disableHitCount;
    }

    public function setDisableHitCount(bool $disableHitCount): Configuration
    {
        $this->disableHitCount = $disableHitCount;
        return $this;
    }

    public function statusCodeIsAllowed(int $statusCode): bool
    {
        return in_array($statusCode, self::ALLOWED_STATUS_CODES, true);
    }

}
