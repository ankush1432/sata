<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-code-coverage.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace SebastianBergmann\CodeCoverage\Report;

final readonly class Thresholds
{
    private int $lowUpperBound;
    private int $highLowerBound;

    public static function default(): self
    {
        return self::from(50, 90);
    }

    public static function from(int $lowUpperBound, int $highLowerBound): self
    {
        return new self($lowUpperBound, $highLowerBound);
    }

    private function __construct(int $lowUpperBound, int $highLowerBound)
    {
        $this->lowUpperBound  = $lowUpperBound;
        $this->highLowerBound = $highLowerBound;
    }

    public function lowUpperBound(): int
    {
        return $this->lowUpperBound;
    }

    public function highLowerBound(): int
    {
        return $this->highLowerBound;
    }
}
