<?php

namespace ssigwart\SqsQueueProcessor;

/** SQS queue processor single message processor result */
class SqsQueueProcessorSingleMessageProcessorResult
{
	/** @var bool Was message processed successfully? */
	private bool $wasSuccess;

	/** @var bool Was message non-successful processing due to error? */
	private bool $wasUnsuccessfulDueToError = false;

	/** @var int|null New visibility timeout for non-success */
	private ?int $newVisibilityTimeout = null;

	/**
	 * Constructor
	 *
	 * @param bool $wasSuccess Was message processed successfully?
	 */
	private function __construct(bool $wasSuccess)
	{
		$this->wasSuccess = $wasSuccess;
	}

	/**
	 * New success result
	 *
	 * @return SqsQueueProcessorSingleMessageProcessorResult
	 */
	public static function newSuccessResult(): SqsQueueProcessorSingleMessageProcessorResult
	{
		return new SqsQueueProcessorSingleMessageProcessorResult(true);
	}

	/**
	 * New failure result
	 *
	 * @param int|null $newVisibilityTimeout New visibility timeout if you want to change it
	 *
	 * @return SqsQueueProcessorSingleMessageProcessorResult
	 */
	public static function newFailureResult(?int $newVisibilityTimeout): SqsQueueProcessorSingleMessageProcessorResult
	{
		$rtn = new SqsQueueProcessorSingleMessageProcessorResult(false);
		$rtn->wasUnsuccessfulDueToError = true;
		$rtn->newVisibilityTimeout = $newVisibilityTimeout;
		return $rtn;
	}

	/**
	 * New delayed processing result. This will not be marked as a success, but will be treated as though everything is operating normally
	 *
	 * @param int $newVisibilityTimeout New visibility timeout if you want to change it
	 *
	 * @return SqsQueueProcessorSingleMessageProcessorResult
	 */
	public static function newDelayedProcessingResult(int $newVisibilityTimeout): SqsQueueProcessorSingleMessageProcessorResult
	{
		$rtn = new SqsQueueProcessorSingleMessageProcessorResult(false);
		$rtn->wasUnsuccessfulDueToError = false;
		$rtn->newVisibilityTimeout = $newVisibilityTimeout;
		return $rtn;
	}

	/**
	 * Was message successful
	 *
	 * @return bool True if successful
	 */
	public function wasSuccessful(): bool
	{
		return $this->wasSuccess;
	}

	/**
	 * Was message non-successful processing due to error?
	 *
	 * @return bool True if non-successful processing was due to error
	 */
	public function wasUnsuccessfulDueToError(): bool
	{
		return $this->wasUnsuccessfulDueToError;
	}

	/**
	 * Get new visibility timeout for non-success
	 *
	 * @return int|null New visibility timeout for non-success
	 */
	public function getNewVisibilityTimeout(): ?int
	{
		return $this->newVisibilityTimeout;
	}
}
