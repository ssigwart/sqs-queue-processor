<?php

declare(strict_types=1);

namespace TestAuxFiles;

/** Unit test with consecutive args and return generator */
class UnitTestWithConsecutiveArgsAndReturnGenerator
{
	/** @var mixed[] Expected arguments */
	private array $expectedArguments = [];

	/** @var mixed[] Return values */
	private array $returnValues = [];

	/**
	 * Add expected arguments
	 *
	 * @param mixed[] $expectedArguments Expected arguments
	 *
	 * @return self
	 */
	public function addExpectedArguments(array $expectedArguments): self
	{
		$this->expectedArguments[] = $expectedArguments;
		return $this;
	}

	/**
	 * Add return value
	 *
	 * @param mixed $returnValue Return value
	 *
	 * @return self
	 */
	public function addReturnValue(mixed $returnValue): self
	{
		$this->returnValues[] = $returnValue;
		return $this;
	}

	/**
	 * Get number of iterations
	 *
	 * @return int Number of iterations
	 */
	public function getNumIterations(): int
	{
		return count($this->returnValues);
	}

	/**
	 * Get will return callback
	 *
	 * @return callable Callback for willReturnCallback
	 */
	public function getWillReturnCallback(): callable
	{
		$callNum = 0;
		$expectedArguments = $this->expectedArguments;
		reset($expectedArguments);
		$returnValues = $this->returnValues;
		reset($returnValues);
		return function() use (&$expectedArguments, &$returnValues, &$callNum) {
			$callNum++;
			$invocationExpectedArgs = current($expectedArguments);
			next($expectedArguments);
			$rtn = current($returnValues);
			next($returnValues);

			// Check arguments
			\PHPUnit\Framework\Assert::assertEquals($invocationExpectedArgs, func_get_args(), 'Invalid arguments for call #' . $callNum . '.');

			return $rtn;
		};
	}
}
