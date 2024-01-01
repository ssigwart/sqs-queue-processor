<?php

declare(strict_types=1);

namespace TestAuxFiles;

use Psr\Log\AbstractLogger;

/** Unit test logger */
class UnitTestLogger extends AbstractLogger
{
	/** @var string[] Logged messages */
	private array $loggedMessages = [];

	/**
	 * Get logged messages
	 *
	 * @return string[] Logged messages
	 */
	public function getLoggedMessages(): array
	{
		return $this->loggedMessages;
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
		$this->loggedMessages[] = strtoupper($level) . ': ' . $message;
	}
}
