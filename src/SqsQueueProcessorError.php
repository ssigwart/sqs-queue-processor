<?php

namespace ssigwart\SqsQueueProcessor;

/** SQS queue processor error */
enum SqsQueueProcessorError: int
{
	/** Message ignored because it was marked as completed */
	case MSG_MARKED_AS_COMPLETED = 1;
	/** Message ignored because it was marked as in-progress */
	case MSG_MARKED_AS_IN_PROGRESS = 2;
	/** Exception thrown in processing of a single message */
	case EXCEPTION_THROWN_HANDLING_MESSAGE = 3;
	/** Failed to mark message as processed */
	case FAILED_TO_MARK_AS_PROCESSED = 4;
	/** Failed to deleted SQS message */
	case FAILED_TO_DELETE_MSG = 5;
	/** Failed to clear message in-progress flag */
	case FAILED_TO_CLEAR_MSG_IN_PROGRESS_FLAG = 6;
}
