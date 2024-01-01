<?php

namespace ssigwart\SqsQueueProcessor;

use ssigwart\AwsHighAvailabilitySqs\SqsMessage;

/** SQS queue processor message provider interface */
interface SqsQueueProcessorMessageProviderInterface
{
	/**
	 * Get SQS messages
	 *
	 * @param int $numMessages Number of messages to request
	 * @param int $visibilityTimeout Visibility timeout (seconds)
	 * @param int $waitTimeSec Wait time (seconds)
	 *
	 * @return SqsMessage[] SQS messages. This should be less than or equal to `$numMessages`
	 */
	public function getSqsMessages(int $numMessages, int $visibilityTimeout, int $waitTimeSec): array;

	/**
	 * Delete SQS message
	 *
	 * @param SqsMessage $sqsMessage SQS message
	 */
	public function deleteSqsMessage(SqsMessage $sqsMessage): void;

	/**
	 * Update SQS message visibility timeout
	 *
	 * @param SqsMessage $sqsMessage SQS message
	 * @param int $newVisibilityTimeout New Visibility timeout (in seconds)
	 */
	public function updateSqsMessageVisibilityTimeout(SqsMessage $sqsMessage, int $newVisibilityTimeout): void;
}
