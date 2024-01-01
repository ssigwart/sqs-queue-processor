<?php

namespace ssigwart\SqsQueueProcessor;

use Psr\Log\LoggerInterface;

/** SQS queue processor message ID prefixed logger */
class SqsQueueProcessorMessageIdPrefixedLogger extends \Psr\Log\AbstractLogger
{
	/** @var LoggerInterface Logger */
	private LoggerInterface $logger;

	/** @var string Prefix */
	private string $prefix = '';

	/**
	 * Constructor
	 *
	 * @param LoggerInterface $logger Logger
	 */
	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	/**
	 * Get prefix
	 *
	 * @return string Prefix
	 */
	public function getPrefix(): string
	{
		return $this->prefix;
	}

	/**
	 * Set prefix
	 *
	 * @param string $prefix Prefix
	 *
	 * @return self
	 */
	public function setPrefix(string $prefix): self
	{
		$this->prefix = $prefix;
		return $this;
	}

	/**
	 * Logs with an arbitrary level
	 *
	 * @param mixed $level
	 * @param string|\Stringable $message
	 * @param array $context
	 *
	 * @return void
	 *
	 * @throws \Psr\Log\InvalidArgumentException
	 */
	public function log($level, string|\Stringable $message, array $context = []): void
	{
		$this->logger->log($level, $this->prefix . $message, $context);
	}
}
