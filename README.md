# SQS Queue Processor

This library provides an SQS queue processor with the following features:
- Duplicate message processing.
- Delayed retry capability.
- Error handling options.

## Installation
```sh
composer require ssigwart/sqs-queue-processor
```

## Usage

Use the following to set up and run a queue processor.
```php
$sqsQueueProcessor = new SqsQueueProcessor(/* Fill in parameters */);
$sqsQueueProcessor->processMessages();
```

### Parameter: `SqsQueueProcessorConfiguration`
- This parameter specifies the maximum number of messages to return on a single SQS `ReceiveMessage` call (default 10), the visibility timeout to use for received messages (default 300), and the number of seconds to wait for a message (default 20).
- You can also control what gets logged when processing messages.

### Parameter: `SqsQueueProcessorTimingInterface`
- This parameter will tell the queue processor when to stop processing messages using `shouldStopProcessingMessages`.
- To loop forever, have it always return false.
- You can also check the time and stop it after a given time.
- You can also have the same object implement this and `SqsQueueProcessorMessageProviderInterface` and have it stop after it has received an empty message list.

### Parameter: `SqsQueueProcessorCleanupInterface`
- This parameter includes cleanup functions such as `cleanUpAfterExceptionProcessingMessage`. If using a transactional database, you make want to call `ROLLBACK` on the database.

### Parameter: `SqsQueueProcessorMessageProviderInterface`
- This parameter is used to get messages, delete messages, and update the visibility timeout on messages.
- It is recommended to use `AwsHighAvailabilitySqsMessageProvider`, which implements this interface.

### Parameter: `SqsQueueProcessorMessageStatusInterface`
- This parameter provides functions to determine if a message is being processed or has completed processing.
- Suggested ways to implement this interface are with Memcached, Redis, or a database.

### Parameter: `SqsQueueProcessorSingleMessageProcessorInterface`
- This parameter is the core interface for processing the message.
- Your work to process the message should be in here.
- It should return a `SqsQueueProcessorSingleMessageProcessorResult`:
	- For a message that has completed processing successfully, return `SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult()`.
	- For a message that you want to delay processing on, return `SqsQueueProcessorSingleMessageProcessorResult::newDelayedProcessingResult(...)` with a new visibility timeout.
	- For a message that had an error while processing, return `SqsQueueProcessorSingleMessageProcessorResult::newFailureResult(...)`, possibly with a new visibility timeout.
- It is recommended to catch any `Throwable`, do cleanup, and return a failure, but uncaught exceptions will be treated as a failure.

### Parameter: `SqsQueueProcessorErrorReportingInterface`
- This parameter provides error handling capabilities.
- Below are the suggested ways to handle different error types:
	- `MSG_MARKED_AS_COMPLETED` - Store raw `SqsMessage` in S3 and send an alert or create a task to review the message to be sure it was successfully processed. The S3 data can be used to requeue the message if needed.
	- `MSG_MARKED_AS_IN_PROGRESS` - Send an alert or create a task to review the message if a message has been marked as in progress for a while. It possibly the in-progress flag failed to be cleared when message processing failed.
	- `EXCEPTION_THROWN_HANDLING_MESSAGE` - Send an alert or create a task to investigate error.
	- `FAILED_TO_MARK_AS_PROCESSED` - Send an alert. This often won't be much of an issue. It becomes an issue if the message is failed to be marked as processed and the SQS message is received a second time (e.g. if an SQS delete failed).
	- `FAILED_TO_DELETE_MSG` - Send an alert or create a task to delete the message.
	- `FAILED_TO_CLEAR_MSG_IN_PROGRESS_FLAG` - Send an alert or create a task to clear the flag so the message can be processed again later.

### Parameter: `LoggerInterface`
- This parameter is a `\Psr\Log\LoggerInterface`, so any implementation of that will do.
- All logged message will automatically have the SQS message ID appended to it to aid in distinguishing log messages for different SQS messages.
