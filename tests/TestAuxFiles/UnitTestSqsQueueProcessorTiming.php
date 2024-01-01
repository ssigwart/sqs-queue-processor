<?php

declare(strict_types=1);

namespace TestAuxFiles;

use ssigwart\SqsQueueProcessor\SqsQueueProcessorTimingInterface;

/** Unit test SQS queue processor timing */
class UnitTestSqsQueueProcessorTiming implements SqsQueueProcessorTimingInterface
{
	/** @var int Number of loops */
	private int $numLoops;

	/**
	 * Constructor
	 *
	 * @param int $numLoops Number of loops
	 */
	public function __construct(int $numLoops)
	{
		$this->numLoops = $numLoops;
	}

	/**
	 * Should we stop processing messages?
	 *
	 * @return bool True if we should stop processing messages
	 */
	public function shouldStopProcessingMessages(): bool
	{
		if ($this->numLoops === 0)
			return true;
		$this->numLoops--;
		return false;
	}
}
