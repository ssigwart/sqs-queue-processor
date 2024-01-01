<?php

namespace ssigwart\SqsQueueProcessor;

use ssigwart\AwsHighAvailabilitySqs\SqsMessage;

/** SQS queue processor single message processor interface */
interface SqsQueueProcessorSingleMessageProcessorInterface
{
	/**
	 * Process message
	 *
	 * @param SqsMessage $sqsMessage SQS message
	 *
	 * @return SqsQueueProcessorSingleMessageProcessorResult
	 */
	public function processMsg(SqsMessage $sqsMessage): SqsQueueProcessorSingleMessageProcessorResult;
}
