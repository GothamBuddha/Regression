<?php

declare(strict_types=1);

namespace mcordingley\Regression;

use mcordingley\LinearAlgebra\Matrix;

/**
 * StatisticsGatherer
 *
 * Represents a regression analysis. Each instance maps to a particular
 * regression analysis.
 */
final class StatisticsGatherer
{
    // Great reference for most of these members:
    // http://facweb.cs.depaul.edu/sjost/csc423/documents/f-test-reg.htm

    /**
     * coefficients
     *
     * The calculated beta values that show what the contribution of each
     * explanatory variable is to the overall fitted curve.
     *
     * @var array
     */
    private $coefficients;

    /**
     * observations
     *
     * The data used to perform the regression.
     *
     * @var Observations
     */
    private $observations;

    /**
     * predictor
     *
     * Object used to derive predicted outcomes.
     *
     * @var Predictor
     */
    private $predictor;

    /**
     * predictedOutcomes
     *
     * What the observed outcomes would be if predicted by the model.
     *
     * @var array
     */
    private $predictedOutcomes;

    /**
     * SCoefficients
     *
     * This is an array of the standard errors for each calculated coefficient.
     *
     * @var array
     */
    private $SCoefficients;

    /**
     * sumSquaredError
     *
     * This is the sum of squared distances of observations from their
     * predicted values, a raw measure of the regression's overall error.
     *
     * @var float
     */
    private $sumSquaredError;

    /**
     * sumSquaredModel
     *
     * The sum of the squared distances of the predicted observations from the
     * mean of the true observations, a raw measure of the regression's overall
     * explanatory power.
     *
     * @var float
     */
    private $sumSquaredModel;

    /**
     * sumSquaredTotal
     *
     * The sum of the squared distances of the observations from their mean.
     * SST = SSE + SSM Useful measure to put the other two sum of squares measures
     * into context
     *
     * @var float
     */
    private $sumSquaredTotal;

    /**
     * tStatistics
     *
     * This is an array of the t statistics for each calculated coefficient.
     *
     * @var array
     */
    private $tStatistics;

    /**
     * __construct
     *
     * @param Observations $observations
     * @param array $coefficients
     * @param Predictor $predictor
     */
    public function __construct(Observations $observations, array $coefficients, Predictor $predictor)
    {
        $this->observations = $observations;
        $this->coefficients = $coefficients;
        $this->predictor = $predictor;
    }

    /**
     * getDegreesOfFreedomError
     *
     * Returns the degrees of freedom of the error for this regression.
     *
     * @return int
     */
    public function getDegreesOfFreedomError(): int
    {
        // Obervations minus explanatory variables
        return count($this->observations->getIndependents()) - count($this->observations->getIndependents()[0]);
    }

    /**
     * getDegreesOfFreedomModel
     *
     * Returns the degrees of freedom of the model for this regression.
     *
     * @return int
     */
    public function getDegreesOfFreedomModel(): int
    {
        // One less than the number of explanatory variables
        return count($this->observations->getIndependents()[0]) - 1;
    }

    /**
     * getDegreesOfFreedomTotal
     *
     * Returns the degrees of freedom for this regression.
     *
     * @return int
     */
    private function getDegreesOfFreedomTotal(): int
    {
        // One less than observations
        return count($this->observations->getIndependents()) - 1;
    }

    /**
     * getFStatistic
     *
     * Returns the F statistic, which is compared against the F distribution CDF
     * to determine if the regression is "significant" or not.
     *
     * @return float
     */
    public function getFStatistic(): float
    {
        return $this->getMeanSquaredModel() / $this->getMeanSquaredError();
    }

    /**
     * getPredictedOutcomes
     *
     * Lazily determines what the outcomes would be if predicted. i.e. the y-hat
     * values.
     *
     * @return array
     */
    private function getPredictedOutcomes(): array
    {
        if (!$this->predictedOutcomes) {
            $this->predictedOutcomes = [];

            foreach ($this->observations->getIndependents() as $observed) {
                $this->predictedOutcomes[] = $this->predictor->predict(array_slice($observed, 1));
            }
        }

        return $this->predictedOutcomes;
    }

    /**
     * getMeanSquaredError
     *
     * Returns the mean-squared error of the regression, which is effectively
     * the "average" of the corresponding sum of squares.
     *
     * @return float
     */
    private function getMeanSquaredError(): float
    {
        return $this->getSumSquaredError() / $this->getDegreesOfFreedomError();
    }

    /**
     * getMeanSquaredModel
     *
     * Returns the mean-squared model of the regression, which is effectively
     * the "average" of the corresponding sum of squares.
     *
     * @return float
     */
    private function getMeanSquaredModel(): float
    {
        return $this->getSumSquaredModel() / $this->getDegreesOfFreedomModel();
    }

    /**
     * getRSquared
     *
     * Calculates the coefficient of determination. i.e. how well the line of
     * best fit describes the data.
     *
     * @return float
     */
    public function getRSquared(): float
    {
        $sumSquaredTotal = $this->getSumSquaredTotal();

        if ($sumSquaredTotal === 0.0) {
            return 0.0;
        }

        return 1 - $this->getSumSquaredError() / $sumSquaredTotal;
    }

    /**
     * getStandardError
     *
     * Calculates the standard error of the regression. This is the average
     * distance of observed values from the regression line. It's conceptually
     * similar to the standard deviation.
     *
     * @return float
     */
    public function getStandardError(): float
    {
        return sqrt($this->getMeanSquaredError());
    }

    /**
     * getStandardErrorCoefficients
     *
     * Calculates the standard error of each of the regression coefficients.
     *
     * @return array
     */
    public function getStandardErrorCoefficients(): array
    {
        if (is_null($this->SCoefficients)) {
            $design = new Matrix($this->observations->getIndependents());

            $this->SCoefficients = $design->transpose()
                    ->multiply($design)
                    ->inverse()
                    ->diagonal()
                    ->multiply($this->getMeanSquaredError())
                    ->map(function ($element) {
                        return sqrt($element);
                    })
                    ->toArray()[0];
        }

        return $this->SCoefficients;
    }

    /**
     * getSumSquaredError
     *
     * Calculates the sum of the squares of the residuals, which are the
     * distances of the observations from their predicted values, a raw measure
     * of the overall error in the regression model.
     *
     * @return float
     */
    private function getSumSquaredError(): float
    {
        if (is_null($this->sumSquaredError)) {
            $this->sumSquaredError = array_sum(array_map(function ($predicted, $observed) {
                return pow($predicted - $observed, 2);
            }, $this->getPredictedOutcomes(), $this->observations->getDependents()));
        }

        return $this->sumSquaredError;
    }

    /**
     * getSumSquaredModel
     *
     * Calculates the sum-squared error of the regression. This is the sum
     * of the squared distances of predicted values from their average.
     *
     * @return float
     */
    private function getSumSquaredModel(): float
    {
        if (is_null($this->sumSquaredModel)) {
            $average = array_sum($this->observations->getDependents()) / count($this->observations->getDependents());
            $this->sumSquaredModel = static::sumSquaredDifference($this->getPredictedOutcomes(), $average);
        }

        return $this->sumSquaredModel;
    }

    /**
     * getSumSquaredTotal
     *
     * Calculates the sum-squared total of the regression. This is the sum
     * of the squared distances of observations from their average, a useful
     * measure to put the sum-squared error (SSE) and sum-squared model (SSM)
     * into context.
     *
     * @return float
     */
    private function getSumSquaredTotal(): float
    {
        if (is_null($this->sumSquaredTotal)) {
            $average = array_sum($this->observations->getDependents()) / count($this->observations->getDependents());
            $this->sumSquaredTotal = static::sumSquaredDifference($this->observations->getDependents(), $average);
        }

        return $this->sumSquaredTotal;
    }

    /**
     * getTStatistics
     *
     * Calculates the t test values of each of the regression coefficients.
     *
     * @return array
     */
    public function getTStatistics(): array
    {
        if (is_null($this->tStatistics)) {
            $this->tStatistics = array_map(function ($predictor, $SCoefficient) {
                return $predictor / $SCoefficient;
            }, $this->coefficients, $this->getStandardErrorCoefficients());
        }

        return $this->tStatistics;
    }

    /**
     * sumSquaredDifference
     *
     * @param array $series
     * @param float $baseline
     * @return float
     */
    private static function sumSquaredDifference(array $series, float $baseline): float
    {
        return array_sum(array_map(function ($element) use ($baseline) {
            return pow($element - $baseline, 2);
        }, $series));
    }
}
