<?php

namespace ssigwart\SqsQueueProcessor;

/** SQS queue processor configuration */
class SqsQueueProcessorConfiguration
{
	/** @var int Max number of messages to request */
	private int $numMessages = 10;

	/** @var int Visibility timeout (seconds) */
	private int $visibilityTimeout = 300;

	/** @var int Wait time (seconds) */
	private int $waitTimeSec = 20;

	/** @var bool Should we log message start? */
	private bool $shouldLogMessageStart = false;

	/** @var bool Should we log message end? */
	private bool $shouldLogMessageEnd = false;

	/**
	 * Get max number of messages to request
	 *
	 * @return int Number of messages to request
	 */
	public function getMaxNumMessagesPerRequest(): int
	{
		return $this->numMessages;
	}

	/**
	 * Set max number of messages to request
	 *
	 * @param int $numMessages Max number of messages to request
	 *
	 * @return self
	 */
	public function setMaxNumMessagesPerRequest(int $numMessages): self
	{
		$this->numMessages = $numMessages;
		return $this;
	}

	/**
	 * Get visibility timeout (seconds)
	 *
	 * @return int Visibility timeout (seconds)
	 */
	public function getVisibilityTimeout(): int
	{
		return $this->visibilityTimeout;
	}

	/**
	 * Set visibility timeout (seconds)
	 *
	 * @param int $visibilityTimeout Visibility timeout (seconds)
	 *
	 * @return self
	 */
	public function setVisibilityTimeout(int $visibilityTimeout): self
	{
		$this->visibilityTimeout = $visibilityTimeout;
		return $this;
	}

	/**
	 * Get wait time (seconds)
	 *
	 * @return int Wait time (seconds)
	 */
	public function getWaitTimeSec(): int
	{
		return $this->waitTimeSec;
	}

	/**
	 * Set wait time (seconds)
	 *
	 * @param int $waitTimeSec Wait time (seconds)
	 *
	 * @return self
	 */
	public function setWaitTimeSec(int $waitTimeSec): self
	{
		$this->waitTimeSec = $waitTimeSec;
		return $this;
	}

	/**
	 * Should we log message start?
	 *
	 * @return bool True to log message start
	 */
	public function shouldLogMessageStart(): bool
	{
		return $this->shouldLogMessageStart;
	}

	/**
	 * Enable logging message start
	 *
	 * @return self
	 */
	public function enableLogMessageStart(): self
	{
		$this->shouldLogMessageStart = true;
		return $this;
	}

	/**
	 * Should we log message end?
	 *
	 * @return bool True to log message end
	 */
	public function shouldLogMessageEnd(): bool
	{
		return $this->shouldLogMessageEnd;
	}

	/**
	 * Enable logging message end
	 *
	 * @return self
	 */
	public function enableLogMessageEnd(): self
	{
		$this->shouldLogMessageEnd = true;
		return $this;
	}
}
