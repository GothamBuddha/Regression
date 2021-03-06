<?php

declare(strict_types=1);

namespace mcordingley\Regression\Linkings;

use InvalidArgumentException;
use mcordingley\Regression\Helpers;

final class Log extends Linking
{
    private $base;

    /**
     * __construct
     *
     * @param float|null $base Base of the logarithmic function. Defaults to M_E.
     */
    public function __construct(float $base = M_E)
    {
        $this->base = $base;
    }

    public function delinearize(float $value): float
    {
        return pow($this->base, $value);
    }

    public function linearize(float $value): float
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('Attempting to take the logarithm of a non-positive number. Double-check your regression model.');
        }

        return log($value, $this->base);
    }

    public function loss(array $coefficients, array $observations, float $outcome, int $index): float
    {
        $sumProduct = Helpers::sumProduct($coefficients, $observations);
        $hypothesis = $this->delinearize($sumProduct);

        return -2 * ($outcome - $hypothesis) * $hypothesis * log($this->base) * $observations[$index];
    }
}
