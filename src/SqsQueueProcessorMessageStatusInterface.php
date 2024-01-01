<?php

namespace ssigwart\SqsQueueProcessor;

/** SQS queue processor message status interface */
interface SqsQueueProcessorMessageStatusInterface
{
	/**
	 * Mark message as in progress
	 *
	 * @param string $sqsMessageId SQS message ID
	 *
	 * @return bool True if successfully marked as in progress
	 */
	public function markMessageAsInProgress(string $sqsMessageId): bool;

	/**
	 * Clear message in progress flag
	 *
	 * @param string $sqsMessageId SQS message ID
	 *
	 * @return bool True if successfully marked as in progress
	 */
	public function clearMessageInProgressFlag(string $sqsMessageId): bool;

	/**
	 * Is message marked as processed
	 *
	 * @param string $sqsMessageId SQS message ID
	 *
	 * @return bool True if it's marked as processed
	 */
	public function isMessageMarkedAsProcessed(string $sqsMessageId): bool;

	/**
	 * Mark message as processed
	 *
	 * @param string $sqsMessageId SQS message ID
	 *
	 * @return bool True if successfully marked as processed
	 */
	public function markMessageAsProcessed(string $sqsMessageId): bool;
}
