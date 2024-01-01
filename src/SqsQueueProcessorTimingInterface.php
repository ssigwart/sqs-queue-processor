<?php

namespace ssigwart\SqsQueueProcessor;

/** SQS queue processor timing interface */
interface SqsQueueProcessorTimingInterface
{
	/**
	 * Should we stop processing messages?
	 *
	 * @return bool True if we should stop processing messages
	 */
	public function shouldStopProcessingMessages(): bool;
}
