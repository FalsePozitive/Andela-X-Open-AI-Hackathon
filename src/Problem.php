<?php
declare(strict_types=1);
namespace Passport;

final class Problem extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422, public readonly array $fields = [])
    {
        parent::__construct(Copy::text($message));
    }
}
