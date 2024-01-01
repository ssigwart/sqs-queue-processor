<?php

namespace ssigwart\SqsQueueProcessor;

use Psr\Log\LoggerInterface;
use ssigwart\AwsHighAvailabilitySqs\SqsMessage;
use Throwable;

/** SQS queue processor error reporting interface */
interface SqsQueueProcessorErrorReportingInterface
{
	/**
	 * Report error
	 *
	 * @param SqsQueueProcessorError $error Error
	 * @param SqsMessage|null $sqsMessage SQS message if applicable
	 * @param Throwable|null $e Exception if applicable
	 * @param LoggerInterface $logger Logger
	 */
	public function reportError(SqsQueueProcessorError $error, ?SqsMessage $sqsMessage, ?Throwable $e, LoggerInterface $logger): void;
}
