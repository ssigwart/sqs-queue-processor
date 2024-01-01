<?php

namespace ssigwart\SqsQueueProcessor;

use ssigwart\AwsHighAvailabilitySqs\AwsHighAvailabilitySqsReceiver;
use ssigwart\AwsHighAvailabilitySqs\SqsAvailableQueue;
use ssigwart\AwsHighAvailabilitySqs\SqsMessage;
use ssigwart\AwsHighAvailabilitySqs\SqsMessageReceivingMetadata;

/** AWS high availability SQS message provider */
class AwsHighAvailabilitySqsMessageProvider implements SqsQueueProcessorMessageProviderInterface
{
	/** @var SqsAvailableQueue SQS queue */
	private SqsAvailableQueue $sqsQueue;

	/** @var AwsHighAvailabilitySqsReceiver SQS receiver */
	private AwsHighAvailabilitySqsReceiver $sqsReceiver;

	/** @var \Aws\Sqs\SqsClient SQS client */
	private \Aws\Sqs\SqsClient $sqsClient;

	/**
	 * Constructor
	 *
	 * @param \Aws\Sdk $awsSdk AWS SDK
	 * @param SqsAvailableQueue $sqsQueue SQS queue
	 */
	public function __construct(\Aws\Sdk $awsSdk, SqsAvailableQueue $sqsQueue)
	{
		$this->sqsQueue = $sqsQueue;
		$this->sqsReceiver = new AwsHighAvailabilitySqsReceiver($awsSdk);
		$this->sqsClient = $awsSdk->createSqs();
	}

	/**
	 * Get SQS messages
	 *
	 * @param int $numMessages Number of messages to request
	 * @param int $visibilityTimeout Visibility timeout (seconds)
	 * @param int $waitTimeSec Wait time (seconds)
	 *
	 * @return SqsMessage[] SQS messages. This should be less than or equal to `$numMessages`
	 */
	public function getSqsMessages(int $numMessages, int $visibilityTimeout, int $waitTimeSec): array
	{
		$metadata = new SqsMessageReceivingMetadata();
		$metadata->setMaxNumMessages($numMessages)->setVisibilityTimeout($visibilityTimeout)->setWaitTime($waitTimeSec);
		$sqsResult = $this->sqsReceiver->receivedMessagesWithS3LargeMessageBacking($this->sqsQueue, $metadata);
		return $sqsResult->getSqsMessages();
	}

	/**
	 * Delete SQS message
	 *
	 * @param SqsMessage $sqsMessage SQS message
	 */
	public function deleteSqsMessage(SqsMessage $sqsMessage): void
	{
		$this->sqsReceiver->deleteMessage($this->sqsQueue, $sqsMessage);
	}

	/**
	 * Update SQS message visibility timeout
	 *
	 * @param SqsMessage $sqsMessage SQS message
	 * @param int $newVisibilityTimeout New Visibility timeout (in seconds)
	 */
	public function updateSqsMessageVisibilityTimeout(SqsMessage $sqsMessage, int $newVisibilityTimeout): void
	{
		$this->sqsClient->changeMessageVisibility([
			'QueueUrl' => $this->sqsQueue->getQueueUrl(),
			'ReceiptHandle' => $sqsMessage->getReceiptHandle(),
			'VisibilityTimeout' => $newVisibilityTimeout
		]);
	}
}
