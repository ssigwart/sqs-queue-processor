<?php

namespace ssigwart\SqsQueueProcessor;

/** SQS queue processor cleanup interface */
interface SqsQueueProcessorCleanupInterface
{
	/**
	 * Clean up after exception processing a single SQS message
	 */
	public function cleanUpAfterExceptionProcessingMessage(): void;
}
