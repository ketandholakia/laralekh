<?php
declare(strict_types=1);
namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use OverflowException;
use Stringable;

final readonly class Money implements Stringable, JsonSerializable
{
    private function __construct(private string $amount) {}

    public static function of(mixed $value): self
    {
        if ((!is_string($value) && !is_int($value)) ||
            !preg_match('/^-?\d+(?:\.\d{1,2})?$/D', (string) $value)) {
            throw new InvalidArgumentException('Money requires a decimal string with at most two decimal places; floats are forbidden.');
        }
        $amount = bcadd((string) $value, '0', 2);
        if (bccomp($amount, '9999999999999.99', 2) > 0 || bccomp($amount, '-9999999999999.99', 2) < 0) {
            throw new OverflowException('Money exceeds decimal(15,2).');
        }
        return new self($amount);
    }

    public static function rounded(string $value): self
    {
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            throw new InvalidArgumentException('Invalid decimal.');
        }
        return self::of(bcadd($value, str_starts_with($value, '-') ? '-0.005' : '0.005', 2));
    }

    public function add(self $other): self { return self::of(bcadd($this->amount, $other->amount, 2)); }
    public function subtract(self $other): self { return self::of(bcsub($this->amount, $other->amount, 2)); }
    public function equals(self $other): bool { return bccomp($this->amount, $other->amount, 2) === 0; }
    public function isNegative(): bool { return bccomp($this->amount, '0', 2) < 0; }
    public function isZero(): bool { return $this->amount === '0.00'; }
    public function __toString(): string { return $this->amount; }
    public function jsonSerialize(): string { return $this->amount; }
}

