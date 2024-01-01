<?php

namespace ssigwart\SqsQueueProcessor;

use Psr\Log\LoggerInterface;
use ssigwart\AwsHighAvailabilitySqs\SqsMessage;
use Throwable;

/** SQS queue processor */
class SqsQueueProcessor
{
	/** @var SqsQueueProcessorConfiguration Configuration */
	private SqsQueueProcessorConfiguration $config;

	/** @var SqsQueueProcessorTimingInterface Timing interface */
	private SqsQueueProcessorTimingInterface $timingInterface;

	/** @var SqsQueueProcessorCleanupInterface Cleanup interface */
	private SqsQueueProcessorCleanupInterface $cleanupInterface;

	/** @var SqsQueueProcessorMessageProviderInterface Message provider */
	private SqsQueueProcessorMessageProviderInterface $msgProvider;

	/** @var SqsQueueProcessorMessageStatusInterface Message status interface */
	private SqsQueueProcessorMessageStatusInterface $msgStatusInterface;

	/** @var SqsQueueProcessorSingleMessageProcessorInterface Message processor */
	private SqsQueueProcessorSingleMessageProcessorInterface $msgProcessor;

	/** @var SqsQueueProcessorErrorReportingInterface Error reporting interface */
	private SqsQueueProcessorErrorReportingInterface $errorReporting;

	/** @var SqsQueueProcessorMessageIdPrefixedLogger Logger */
	private SqsQueueProcessorMessageIdPrefixedLogger $logger;

	/** @var SqsMessage|null Current SQS message */
	private ?SqsMessage $currentSqsMessage = null;

	/** @bar bool Was shutdown signal detected? */
	private bool $shutdownSignalDetected = false;

	/**
	 * Constructor
	 *
	 * @param SqsQueueProcessorConfiguration $config Configuration
	 * @param SqsQueueProcessorTimingInterface $timingInterface Timing interface
	 * @param SqsQueueProcessorCleanupInterface $cleanupInterface Cleanup interface
	 * @param SqsQueueProcessorMessageProviderInterface $msgProvider Message provider
	 * @param SqsQueueProcessorMessageStatusInterface $msgStatusInterface Message status interface
	 * @param SqsQueueProcessorSingleMessageProcessorInterface $msgProcessor Message processor
	 * @param SqsQueueProcessorErrorReportingInterface $errorReporting Error reporting interface
	 * @param LoggerInterface $logger Logger
	 */
	public function __construct(SqsQueueProcessorConfiguration $config, SqsQueueProcessorTimingInterface $timingInterface, SqsQueueProcessorCleanupInterface $cleanupInterface, SqsQueueProcessorMessageProviderInterface $msgProvider, SqsQueueProcessorMessageStatusInterface $msgStatusInterface, SqsQueueProcessorSingleMessageProcessorInterface $msgProcessor, SqsQueueProcessorErrorReportingInterface $errorReporting, LoggerInterface $logger)
	{
		$this->config = $config;
		$this->timingInterface = $timingInterface;
		$this->cleanupInterface = $cleanupInterface;
		$this->msgProvider = $msgProvider;
		$this->msgStatusInterface = $msgStatusInterface;
		$this->msgProcessor = $msgProcessor;
		$this->errorReporting = $errorReporting;
		$this->logger = new SqsQueueProcessorMessageIdPrefixedLogger($logger);

		$this->setUpSignalHandling();
	}

	/**
	 * Set up signal handling
	 */
	private function setUpSignalHandling(): void
	{
		$func = function(int $sigNo, mixed $sigInfo): void {
			$this->logger->info('Signal ' . $sigNo . ' detected. Will shut down.');
			$this->shutdownSignalDetected = true;
		};
		// SIGINT - Ctrl-C
		pcntl_signal(SIGINT, $func);
		// SIGTERM - kill
		pcntl_signal(SIGTERM, $func);
		// SIGHUP - Terminal closed
		pcntl_signal(SIGHUP, $func);
	}

	/**
	 * Process messages
	 */
	public function processMessages(): void
	{
		while (!$this->shutdownSignalDetected && !$this->timingInterface->shouldStopProcessingMessages())
		{
			$sqsMessages = $this->msgProvider->getSqsMessages($this->config->getMaxNumMessagesPerRequest(), $this->config->getVisibilityTimeout(), $this->config->getWaitTimeSec());
			foreach ($sqsMessages as $sqsMessage)
				$this->processSingleSqsMessage($sqsMessage);

			// Check signals
			pcntl_signal_dispatch();
		}
	}

	/**
	 * Process single SQS message
	 *
	 * @param SqsMessage $sqsMessage SQS message
	 */
	private function processSingleSqsMessage(SqsMessage $sqsMessage): void
	{
		$this->currentSqsMessage = $sqsMessage;
		$sqsMessageId = $this->currentSqsMessage->getMessageId();
		$this->logger->setPrefix('(' . $sqsMessageId . ') - ');
		$shouldReleaseInProgressLock = false;
		try
		{
			// Log
			if ($this->config->shouldLogMessageStart())
				$this->logger->info('Started processing message.');

			// Check if the message was already processed
			if ($this->msgStatusInterface->isMessageMarkedAsProcessed($sqsMessageId))
			{
				$this->logger->info('Message already processed.');
				$this->errorReporting->reportError(SqsQueueProcessorError::MSG_MARKED_AS_COMPLETED, $sqsMessage, null, $this->logger);
				$this->deleteSqsMessage($sqsMessage);
			}
			else
			{
				// Check if the message was already processed
				$wasMsgMarkedAsInProgress = $this->msgStatusInterface->markMessageAsInProgress($sqsMessageId);
				if (!$wasMsgMarkedAsInProgress)
				{
					$this->logger->info('Message already being processed.');
					$this->errorReporting->reportError(SqsQueueProcessorError::MSG_MARKED_AS_IN_PROGRESS, $sqsMessage, null, $this->logger);
					return;
				}
				$shouldReleaseInProgressLock = true;

				// Process message
				try {
					$processingResult = $this->msgProcessor->processMsg($sqsMessage);
				} catch (Throwable $e) {
					$this->logger->error('Exception thrown handling message.' . PHP_EOL . $e);
					$this->errorReporting->reportError(SqsQueueProcessorError::EXCEPTION_THROWN_HANDLING_MESSAGE, $sqsMessage, $e, $this->logger);
					$processingResult = SqsQueueProcessorSingleMessageProcessorResult::newFailureResult(null);
					$this->cleanupInterface->cleanUpAfterExceptionProcessingMessage();
				}

				// Report receipt handle if needed
				if (!$processingResult->wasSuccessful())
				{
					$receiptHandleMsg = 'Receipt handle: ' . $sqsMessage->getReceiptHandle();
					if ($processingResult->wasUnsuccessfulDueToError())
						$this->logger->error($receiptHandleMsg);
					else
						$this->logger->info($receiptHandleMsg);

					// Update new visibility timeout
					$newVisibilityTimeout = $processingResult->getNewVisibilityTimeout();
					if ($newVisibilityTimeout !== null)
						$this->msgProvider->updateSqsMessageVisibilityTimeout($sqsMessage, $newVisibilityTimeout);
				}
				// For success delete message and mark as processed
				else
				{
					// We don't need to release the lock because it has completed processing
					$shouldReleaseInProgressLock = false;

					// Mark message as processed
					if (!$this->msgStatusInterface->markMessageAsProcessed($sqsMessageId))
					{
						$this->logger->error('Failed to mark message as processed.');
						$this->errorReporting->reportError(SqsQueueProcessorError::FAILED_TO_MARK_AS_PROCESSED, $sqsMessage, null, $this->logger);
					}

					// Delete message
					$this->deleteSqsMessage($sqsMessage);
				}
			}
		} catch (Throwable $e) {
			$this->cleanupInterface->cleanUpAfterExceptionProcessingMessage();
			throw $e;
		} finally {
			// Release in-progress lock
			if ($shouldReleaseInProgressLock)
			{
				if (!$this->msgStatusInterface->clearMessageInProgressFlag($sqsMessageId))
				{
					$this->logger->error('Failed to clear message in-progress flag.');
					$this->errorReporting->reportError(SqsQueueProcessorError::FAILED_TO_CLEAR_MSG_IN_PROGRESS_FLAG, $sqsMessage, null, $this->logger);
				}
			}

			// Log
			if ($this->config->shouldLogMessageEnd())
				$this->logger->info('Done processing message.');

			// Clear current message
			$this->currentSqsMessage = null;
			$this->logger->setPrefix('');
		}
	}

	/**
	 * Delete SQS message
	 *
	 * @param SqsMessage $sqsMessage SQS message
	 */
	private function deleteSqsMessage(SqsMessage $sqsMessage): void
	{
		try {
			$this->msgProvider->deleteSqsMessage($sqsMessage);
		} catch (Throwable $e) {
			$this->logger->error('Failed to delete processed SQS message.');
			$this->errorReporting->reportError(SqsQueueProcessorError::FAILED_TO_DELETE_MSG, $sqsMessage, $e, $this->logger);
		}
	}
}
