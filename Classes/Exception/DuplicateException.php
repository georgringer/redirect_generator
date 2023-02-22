<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Exception;

class DuplicateException extends \Exception
{
    protected bool $targetDifferent = false;

    public function __construct(
        string $message = "",
        int $code = 0,
        bool $targetDifferent = false,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->targetDifferent = $targetDifferent;
    }

    public function isTargetDifferent(): bool
    {
        return $this->targetDifferent;
    }

    public function setTargetDifferent(bool $targetDifferent): DuplicateException
    {
        $this->targetDifferent = $targetDifferent;

        return $this;
    }
}
