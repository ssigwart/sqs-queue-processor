<?php

declare(strict_types=1);

use PHPUnit\Framework\MockObject\MockObject;
use ssigwart\AwsHighAvailabilitySqs\SqsAvailableQueue;
use ssigwart\AwsHighAvailabilitySqs\SqsMessage;
use ssigwart\SqsQueueProcessor\AwsHighAvailabilitySqsMessageProvider;
use ssigwart\SqsQueueProcessor\SqsQueueProcessor;
use ssigwart\SqsQueueProcessor\SqsQueueProcessorCleanupInterface;
use ssigwart\SqsQueueProcessor\SqsQueueProcessorConfiguration;
use ssigwart\SqsQueueProcessor\SqsQueueProcessorError;
use ssigwart\SqsQueueProcessor\SqsQueueProcessorErrorReportingInterface;
use ssigwart\SqsQueueProcessor\SqsQueueProcessorMessageProviderInterface;
use ssigwart\SqsQueueProcessor\SqsQueueProcessorMessageStatusInterface;
use ssigwart\SqsQueueProcessor\SqsQueueProcessorSingleMessageProcessorInterface;
use ssigwart\SqsQueueProcessor\SqsQueueProcessorSingleMessageProcessorResult;
use TestAuxFiles\UnitTestCase;
use TestAuxFiles\UnitTestLogger;
use TestAuxFiles\UnitTestSqsQueueProcessorTiming;
use TestAuxFiles\UnitTestWithConsecutiveArgsAndReturnGenerator;

/**
 * SQS queue processor test
 */
class SqsQueueProcessorTest extends UnitTestCase
{
	/**
	 * Test processing queue successfully with mock AWS
	 */
	public function testProcessingQueueSuccessfullyWithMockAws(): void
	{
		// Set up cleanup
		/** @var MockObject|SqsQueueProcessorCleanupInterface $sqsQueueProcessorCleanup */
		$sqsQueueProcessorCleanup = $this->getMockBuilder(SqsQueueProcessorCleanupInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorCleanup->expects(self::exactly(0))->method('cleanUpAfterExceptionProcessingMessage');

		// Set up config
		$config = new SqsQueueProcessorConfiguration();
		$config->enableLogMessageStart();
		$config->enableLogMessageEnd();

		// Set up message provider
		$sqsMessage = new SqsMessage([
			'MessageId' => 'SQS-MSG-1',
			'ReceiptHandle' => 'ReceiptHandle1',
			'Body' => 'Body-1'
		]);
		/** @var MockObject|SqsQueueProcessorMessageProviderInterface $sqsQueueProcessorMessageProvider */
		$sqsQueueProcessorMessageProvider = $this->getMockBuilder(SqsQueueProcessorMessageProviderInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorMessageProvider->expects(self::exactly(1))->method('getSqsMessages')->with($config->getMaxNumMessagesPerRequest(), $config->getVisibilityTimeout(), $config->getWaitTimeSec())->willReturn([
			$sqsMessage
		]);
		$sqsQueueProcessorMessageProvider->expects(self::exactly(1))->method('deleteSqsMessage')->with($sqsMessage);

		// Set up message status interface
		/** @var MockObject|SqsQueueProcessorMessageStatusInterface $sqsQueueProcessorMessageStatus */
		$sqsQueueProcessorMessageStatus = $this->getMockBuilder(SqsQueueProcessorMessageStatusInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorMessageStatus->expects(self::exactly(1))->method('markMessageAsInProgress')->with($sqsMessage->getMessageId())->willReturn(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly(0))->method('clearMessageInProgressFlag');
		$sqsQueueProcessorMessageStatus->expects(self::exactly(1))->method('isMessageMarkedAsProcessed')->with($sqsMessage->getMessageId())->willReturn(false);
		$sqsQueueProcessorMessageStatus->expects(self::exactly(1))->method('markMessageAsProcessed')->with($sqsMessage->getMessageId())->willReturn(true);

		// Set up message processor
		/** @var MockObject|SqsQueueProcessorSingleMessageProcessorInterface $sqsQueueProcessorSingleMessageProcessor */
		$sqsQueueProcessorSingleMessageProcessor = $this->getMockBuilder(SqsQueueProcessorSingleMessageProcessorInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorSingleMessageProcessor->expects(self::exactly(1))->method('processMsg')->with($sqsMessage)->willReturn(SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult());

		// Error reporting
		/** @var MockObject|SqsQueueProcessorErrorReportingInterface $sqsQueueProcessorErrorReporting */
		$sqsQueueProcessorErrorReporting = $this->getMockBuilder(SqsQueueProcessorErrorReportingInterface::class)->disableAutoReturnValueGeneration()->getMock();

		// Set up logger
		$logger = new UnitTestLogger();

		$sqsQueueProcessor = new SqsQueueProcessor(
			$config,
			new UnitTestSqsQueueProcessorTiming(1),
			$sqsQueueProcessorCleanup,
			$sqsQueueProcessorMessageProvider,
			$sqsQueueProcessorMessageStatus,
			$sqsQueueProcessorSingleMessageProcessor,
			$sqsQueueProcessorErrorReporting,
			$logger
		);
		$sqsQueueProcessor->processMessages();

		// Check logged messages
		self::assertEquals([
			'INFO: (SQS-MSG-1) - Started processing message.',
			'INFO: (SQS-MSG-1) - Done processing message.'
		], $logger->getLoggedMessages());
	}

	/**
	 * Test processing queue successfully
	 */
	public function testProcessingQueueSuccessfully(): void
	{
		$awsSdk = $this->createNewAwsSdk();
		$queueUrl = $this->createRandomSqsQueue($awsSdk);

		// Set up cleanup
		/** @var MockObject|SqsQueueProcessorCleanupInterface $sqsQueueProcessorCleanup */
		$sqsQueueProcessorCleanup = $this->getMockBuilder(SqsQueueProcessorCleanupInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorCleanup->expects(self::exactly(0))->method('cleanUpAfterExceptionProcessingMessage');

		// Set up config
		$config = new SqsQueueProcessorConfiguration();
		$config->setMaxNumMessagesPerRequest(2);
		$config->enableLogMessageStart();
		$config->enableLogMessageEnd();

		// Send some messages
		$sqs = $awsSdk->createSqs();
		$sendMessageResult1 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 1'
		]);
		$messageId1 = $sendMessageResult1['MessageId'];
		$sendMessageResult2 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 2'
		]);
		$messageId2 = $sendMessageResult2['MessageId'];
		$sendMessageResult3 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 3'
		]);
		$messageId3 = $sendMessageResult3['MessageId'];

		// Set up message provider
		$sqsQueue = new SqsAvailableQueue('us-east-1', $queueUrl);
		$sqsQueueProcessorMessageProvider = new AwsHighAvailabilitySqsMessageProvider($awsSdk, $sqsQueue);

		// Set up message status interface
		/** @var MockObject|SqsQueueProcessorMessageStatusInterface $sqsQueueProcessorMessageStatus */
		$sqsQueueProcessorMessageStatus = $this->getMockBuilder(SqsQueueProcessorMessageStatusInterface::class)->disableAutoReturnValueGeneration()->getMock();

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId3])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly(3))->method('markMessageAsInProgress')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$sqsQueueProcessorMessageStatus->expects(self::exactly(0))->method('clearMessageInProgressFlag');

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId3])->addReturnValue(false);
		$sqsQueueProcessorMessageStatus->expects(self::exactly(3))->method('isMessageMarkedAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId3])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly(3))->method('markMessageAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		// Set up message processor
		/** @var MockObject|SqsQueueProcessorSingleMessageProcessorInterface $sqsQueueProcessorSingleMessageProcessor */
		$sqsQueueProcessorSingleMessageProcessor = $this->getMockBuilder(SqsQueueProcessorSingleMessageProcessorInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorSingleMessageProcessor->expects(self::exactly(3))->method('processMsg')->willReturn(SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult());

		// Error reporting
		/** @var MockObject|SqsQueueProcessorErrorReportingInterface $sqsQueueProcessorErrorReporting */
		$sqsQueueProcessorErrorReporting = $this->getMockBuilder(SqsQueueProcessorErrorReportingInterface::class)->disableAutoReturnValueGeneration()->getMock();

		// Set up logger
		$logger = new UnitTestLogger();

		$sqsQueueProcessor = new SqsQueueProcessor(
			$config,
			new UnitTestSqsQueueProcessorTiming(2),
			$sqsQueueProcessorCleanup,
			$sqsQueueProcessorMessageProvider,
			$sqsQueueProcessorMessageStatus,
			$sqsQueueProcessorSingleMessageProcessor,
			$sqsQueueProcessorErrorReporting,
			$logger
		);
		$sqsQueueProcessor->processMessages();

		// Check logged messages
		self::assertEquals([
			'INFO: (' . $messageId1 . ') - Started processing message.',
			'INFO: (' . $messageId1 . ') - Done processing message.',
			'INFO: (' . $messageId2 . ') - Started processing message.',
			'INFO: (' . $messageId2 . ') - Done processing message.',
			'INFO: (' . $messageId3 . ') - Started processing message.',
			'INFO: (' . $messageId3 . ') - Done processing message.'
		], $logger->getLoggedMessages());
	}

	/**
	 * Test processing queue exception
	 */
	public function testProcessingQueueException(): void
	{
		$awsSdk = $this->createNewAwsSdk();
		$queueUrl = $this->createRandomSqsQueue($awsSdk);

		// Set up cleanup
		/** @var MockObject|SqsQueueProcessorCleanupInterface $sqsQueueProcessorCleanup */
		$sqsQueueProcessorCleanup = $this->getMockBuilder(SqsQueueProcessorCleanupInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorCleanup->expects(self::exactly(1))->method('cleanUpAfterExceptionProcessingMessage');

		// Set up config
		$config = new SqsQueueProcessorConfiguration();
		$config->setMaxNumMessagesPerRequest(2);
		$config->enableLogMessageStart();
		$config->enableLogMessageEnd();

		// Send some messages
		$sqs = $awsSdk->createSqs();
		$sendMessageResult1 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 1'
		]);
		$messageId1 = $sendMessageResult1['MessageId'];
		$sendMessageResult2 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 2'
		]);
		$messageId2 = $sendMessageResult2['MessageId'];

		// Set up message provider
		$sqsQueue = new SqsAvailableQueue('us-east-1', $queueUrl);
		$sqsQueueProcessorMessageProvider = new AwsHighAvailabilitySqsMessageProvider($awsSdk, $sqsQueue);

		// Set up message status interface
		/** @var MockObject|SqsQueueProcessorMessageStatusInterface $sqsQueueProcessorMessageStatus */
		$sqsQueueProcessorMessageStatus = $this->getMockBuilder(SqsQueueProcessorMessageStatusInterface::class)->disableAutoReturnValueGeneration()->getMock();

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsInProgress')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('clearMessageInProgressFlag')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(false);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('isMessageMarkedAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		// Set up message processor
		/** @var MockObject|SqsQueueProcessorSingleMessageProcessorInterface $sqsQueueProcessorSingleMessageProcessor */
		$sqsQueueProcessorSingleMessageProcessor = $this->getMockBuilder(SqsQueueProcessorSingleMessageProcessorInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$invocationNum = 0;
		$sqsQueueProcessorSingleMessageProcessor->expects(self::exactly(2))->method('processMsg')->willReturnCallback(function() use (&$invocationNum) {
			$invocationNum++;
			if ($invocationNum === 1)
				throw new RuntimeException('Test exception.');
			return SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult();
		});

		// Error reporting
		/** @var MockObject|SqsQueueProcessorErrorReportingInterface $sqsQueueProcessorErrorReporting */
		$sqsQueueProcessorErrorReporting = $this->getMockBuilder(SqsQueueProcessorErrorReportingInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorErrorReporting->expects(self::exactly(1))->method('reportError')->with(SqsQueueProcessorError::EXCEPTION_THROWN_HANDLING_MESSAGE);

		// Set up logger
		$logger = new UnitTestLogger();

		$sqsQueueProcessor = new SqsQueueProcessor(
			$config,
			new UnitTestSqsQueueProcessorTiming(1),
			$sqsQueueProcessorCleanup,
			$sqsQueueProcessorMessageProvider,
			$sqsQueueProcessorMessageStatus,
			$sqsQueueProcessorSingleMessageProcessor,
			$sqsQueueProcessorErrorReporting,
			$logger
		);
		$sqsQueueProcessor->processMessages();

		// Clean up log messages
		$loggedMessages = array_map(function(string $msg): string {
			$msg = preg_replace('/Receipt handle: [A-Za-z0-9+\\/=]+/', 'Receipt handle: XXX', $msg);
			$msg = preg_replace("/(Exception thrown handling message.\n[^:]+:)(.|\n)*/mD", '$1', $msg);
			return $msg;
		}, $logger->getLoggedMessages());

		// Check logged messages
		self::assertEquals([
			'INFO: (' . $messageId1 . ') - Started processing message.',
			'ERROR: (' . $messageId1 . ') - Exception thrown handling message.' . PHP_EOL . 'RuntimeException:',
			'ERROR: (' . $messageId1 . ') - Receipt handle: XXX',
			'INFO: (' . $messageId1 . ') - Done processing message.',
			'INFO: (' . $messageId2 . ') - Started processing message.',
			'INFO: (' . $messageId2 . ') - Done processing message.'
		], $loggedMessages);
	}

	/**
	 * Test processing queue message in progress
	 */
	public function testProcessingQueueMessageInProgress(): void
	{
		$awsSdk = $this->createNewAwsSdk();
		$queueUrl = $this->createRandomSqsQueue($awsSdk);

		// Set up cleanup
		/** @var MockObject|SqsQueueProcessorCleanupInterface $sqsQueueProcessorCleanup */
		$sqsQueueProcessorCleanup = $this->getMockBuilder(SqsQueueProcessorCleanupInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorCleanup->expects(self::exactly(0))->method('cleanUpAfterExceptionProcessingMessage');

		// Set up config
		$config = new SqsQueueProcessorConfiguration();
		$config->setMaxNumMessagesPerRequest(2);
		$config->enableLogMessageStart();
		$config->enableLogMessageEnd();

		// Send some messages
		$sqs = $awsSdk->createSqs();
		$sendMessageResult1 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 1'
		]);
		$messageId1 = $sendMessageResult1['MessageId'];
		$sendMessageResult2 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 2'
		]);
		$messageId2 = $sendMessageResult2['MessageId'];

		// Set up message provider
		$sqsQueue = new SqsAvailableQueue('us-east-1', $queueUrl);
		$sqsQueueProcessorMessageProvider = new AwsHighAvailabilitySqsMessageProvider($awsSdk, $sqsQueue);

		// Set up message status interface
		/** @var MockObject|SqsQueueProcessorMessageStatusInterface $sqsQueueProcessorMessageStatus */
		$sqsQueueProcessorMessageStatus = $this->getMockBuilder(SqsQueueProcessorMessageStatusInterface::class)->disableAutoReturnValueGeneration()->getMock();

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsInProgress')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('clearMessageInProgressFlag')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(false);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('isMessageMarkedAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		// Set up message processor
		/** @var MockObject|SqsQueueProcessorSingleMessageProcessorInterface $sqsQueueProcessorSingleMessageProcessor */
		$sqsQueueProcessorSingleMessageProcessor = $this->getMockBuilder(SqsQueueProcessorSingleMessageProcessorInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorSingleMessageProcessor->expects(self::exactly(1))->method('processMsg')->willReturn(SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult());

		// Error reporting
		/** @var MockObject|SqsQueueProcessorErrorReportingInterface $sqsQueueProcessorErrorReporting */
		$sqsQueueProcessorErrorReporting = $this->getMockBuilder(SqsQueueProcessorErrorReportingInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorErrorReporting->expects(self::exactly(1))->method('reportError')->with(SqsQueueProcessorError::MSG_MARKED_AS_IN_PROGRESS);

		// Set up logger
		$logger = new UnitTestLogger();

		$sqsQueueProcessor = new SqsQueueProcessor(
			$config,
			new UnitTestSqsQueueProcessorTiming(1),
			$sqsQueueProcessorCleanup,
			$sqsQueueProcessorMessageProvider,
			$sqsQueueProcessorMessageStatus,
			$sqsQueueProcessorSingleMessageProcessor,
			$sqsQueueProcessorErrorReporting,
			$logger
		);
		$sqsQueueProcessor->processMessages();

		// Check logged messages
		self::assertEquals([
			'INFO: (' . $messageId1 . ') - Started processing message.',
			'INFO: (' . $messageId1 . ') - Message already being processed.',
			'INFO: (' . $messageId1 . ') - Done processing message.',
			'INFO: (' . $messageId2 . ') - Started processing message.',
			'INFO: (' . $messageId2 . ') - Done processing message.'
		], $logger->getLoggedMessages());
	}

	/**
	 * Test processing queue message already processed
	 */
	public function testProcessingQueueMessageAlreadyProcessed(): void
	{
		$awsSdk = $this->createNewAwsSdk();
		$queueUrl = $this->createRandomSqsQueue($awsSdk);

		// Set up cleanup
		/** @var MockObject|SqsQueueProcessorCleanupInterface $sqsQueueProcessorCleanup */
		$sqsQueueProcessorCleanup = $this->getMockBuilder(SqsQueueProcessorCleanupInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorCleanup->expects(self::exactly(0))->method('cleanUpAfterExceptionProcessingMessage');

		// Set up config
		$config = new SqsQueueProcessorConfiguration();
		$config->setMaxNumMessagesPerRequest(2);
		$config->enableLogMessageStart();
		$config->enableLogMessageEnd();

		// Send some messages
		$sqs = $awsSdk->createSqs();
		$sendMessageResult1 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 1'
		]);
		$messageId1 = $sendMessageResult1['MessageId'];
		$sendMessageResult2 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 2'
		]);
		$messageId2 = $sendMessageResult2['MessageId'];

		// Set up message provider
		$sqsQueue = new SqsAvailableQueue('us-east-1', $queueUrl);
		$sqsQueueProcessorMessageProvider = new AwsHighAvailabilitySqsMessageProvider($awsSdk, $sqsQueue);

		// Set up message status interface
		/** @var MockObject|SqsQueueProcessorMessageStatusInterface $sqsQueueProcessorMessageStatus */
		$sqsQueueProcessorMessageStatus = $this->getMockBuilder(SqsQueueProcessorMessageStatusInterface::class)->disableAutoReturnValueGeneration()->getMock();

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsInProgress')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('clearMessageInProgressFlag')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(false);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('isMessageMarkedAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		// Set up message processor
		/** @var MockObject|SqsQueueProcessorSingleMessageProcessorInterface $sqsQueueProcessorSingleMessageProcessor */
		$sqsQueueProcessorSingleMessageProcessor = $this->getMockBuilder(SqsQueueProcessorSingleMessageProcessorInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorSingleMessageProcessor->expects(self::exactly(1))->method('processMsg')->willReturn(SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult());

		// Error reporting
		/** @var MockObject|SqsQueueProcessorErrorReportingInterface $sqsQueueProcessorErrorReporting */
		$sqsQueueProcessorErrorReporting = $this->getMockBuilder(SqsQueueProcessorErrorReportingInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorErrorReporting->expects(self::exactly(1))->method('reportError')->with(SqsQueueProcessorError::MSG_MARKED_AS_COMPLETED);

		// Set up logger
		$logger = new UnitTestLogger();

		$sqsQueueProcessor = new SqsQueueProcessor(
			$config,
			new UnitTestSqsQueueProcessorTiming(1),
			$sqsQueueProcessorCleanup,
			$sqsQueueProcessorMessageProvider,
			$sqsQueueProcessorMessageStatus,
			$sqsQueueProcessorSingleMessageProcessor,
			$sqsQueueProcessorErrorReporting,
			$logger
		);
		$sqsQueueProcessor->processMessages();

		// Check logged messages
		self::assertEquals([
			'INFO: (' . $messageId1 . ') - Started processing message.',
			'INFO: (' . $messageId1 . ') - Message already processed.',
			'INFO: (' . $messageId1 . ') - Done processing message.',
			'INFO: (' . $messageId2 . ') - Started processing message.',
			'INFO: (' . $messageId2 . ') - Done processing message.'
		], $logger->getLoggedMessages());
	}

	/**
	 * Test processing queue message mark as processed failure
	 */
	public function testProcessingQueueMessageMarkAsProcessedFailure(): void
	{
		$awsSdk = $this->createNewAwsSdk();
		$queueUrl = $this->createRandomSqsQueue($awsSdk);

		// Set up cleanup
		/** @var MockObject|SqsQueueProcessorCleanupInterface $sqsQueueProcessorCleanup */
		$sqsQueueProcessorCleanup = $this->getMockBuilder(SqsQueueProcessorCleanupInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorCleanup->expects(self::exactly(0))->method('cleanUpAfterExceptionProcessingMessage');

		// Set up config
		$config = new SqsQueueProcessorConfiguration();
		$config->setMaxNumMessagesPerRequest(2);
		$config->enableLogMessageStart();
		$config->enableLogMessageEnd();

		// Send some messages
		$sqs = $awsSdk->createSqs();
		$sendMessageResult1 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 1'
		]);
		$messageId1 = $sendMessageResult1['MessageId'];
		$sendMessageResult2 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 2'
		]);
		$messageId2 = $sendMessageResult2['MessageId'];

		// Set up message provider
		$sqsQueue = new SqsAvailableQueue('us-east-1', $queueUrl);
		$sqsQueueProcessorMessageProvider = new AwsHighAvailabilitySqsMessageProvider($awsSdk, $sqsQueue);

		// Set up message status interface
		/** @var MockObject|SqsQueueProcessorMessageStatusInterface $sqsQueueProcessorMessageStatus */
		$sqsQueueProcessorMessageStatus = $this->getMockBuilder(SqsQueueProcessorMessageStatusInterface::class)->disableAutoReturnValueGeneration()->getMock();

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsInProgress')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('clearMessageInProgressFlag')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(false);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('isMessageMarkedAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		// Set up message processor
		/** @var MockObject|SqsQueueProcessorSingleMessageProcessorInterface $sqsQueueProcessorSingleMessageProcessor */
		$sqsQueueProcessorSingleMessageProcessor = $this->getMockBuilder(SqsQueueProcessorSingleMessageProcessorInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorSingleMessageProcessor->expects(self::exactly(2))->method('processMsg')->willReturn(SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult());

		// Error reporting
		/** @var MockObject|SqsQueueProcessorErrorReportingInterface $sqsQueueProcessorErrorReporting */
		$sqsQueueProcessorErrorReporting = $this->getMockBuilder(SqsQueueProcessorErrorReportingInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorErrorReporting->expects(self::exactly(1))->method('reportError')->with(SqsQueueProcessorError::FAILED_TO_MARK_AS_PROCESSED);

		// Set up logger
		$logger = new UnitTestLogger();

		$sqsQueueProcessor = new SqsQueueProcessor(
			$config,
			new UnitTestSqsQueueProcessorTiming(1),
			$sqsQueueProcessorCleanup,
			$sqsQueueProcessorMessageProvider,
			$sqsQueueProcessorMessageStatus,
			$sqsQueueProcessorSingleMessageProcessor,
			$sqsQueueProcessorErrorReporting,
			$logger
		);
		$sqsQueueProcessor->processMessages();

		// Check logged messages
		self::assertEquals([
			'INFO: (' . $messageId1 . ') - Started processing message.',
			'ERROR: (' . $messageId1 . ') - Failed to mark message as processed.',
			'INFO: (' . $messageId1 . ') - Done processing message.',
			'INFO: (' . $messageId2 . ') - Started processing message.',
			'INFO: (' . $messageId2 . ') - Done processing message.'
		], $logger->getLoggedMessages());
	}

	/**
	 * Test processing queue failure retry
	 */
	public function testProcessingQueueFailureRetry(): void
	{
		$awsSdk = $this->createNewAwsSdk();
		$queueUrl = $this->createRandomSqsQueue($awsSdk);

		// Set up cleanup
		/** @var MockObject|SqsQueueProcessorCleanupInterface $sqsQueueProcessorCleanup */
		$sqsQueueProcessorCleanup = $this->getMockBuilder(SqsQueueProcessorCleanupInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorCleanup->expects(self::exactly(0))->method('cleanUpAfterExceptionProcessingMessage');

		// Set up config
		$config = new SqsQueueProcessorConfiguration();
		$config->setMaxNumMessagesPerRequest(2);
		$config->enableLogMessageStart();
		$config->enableLogMessageEnd();

		// Send some messages
		$sqs = $awsSdk->createSqs();
		$sendMessageResult1 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 1'
		]);
		$messageId1 = $sendMessageResult1['MessageId'];
		$sendMessageResult2 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 2'
		]);
		$messageId2 = $sendMessageResult2['MessageId'];

		// Set up message provider
		$sqsQueue = new SqsAvailableQueue('us-east-1', $queueUrl);
		$sqsQueueProcessorMessageProvider = new AwsHighAvailabilitySqsMessageProvider($awsSdk, $sqsQueue);

		// Set up message status interface
		/** @var MockObject|SqsQueueProcessorMessageStatusInterface $sqsQueueProcessorMessageStatus */
		$sqsQueueProcessorMessageStatus = $this->getMockBuilder(SqsQueueProcessorMessageStatusInterface::class)->disableAutoReturnValueGeneration()->getMock();

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsInProgress')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('clearMessageInProgressFlag')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('isMessageMarkedAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		// Set up message processor
		/** @var MockObject|SqsQueueProcessorSingleMessageProcessorInterface $sqsQueueProcessorSingleMessageProcessor */
		$sqsQueueProcessorSingleMessageProcessor = $this->getMockBuilder(SqsQueueProcessorSingleMessageProcessorInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorSingleMessageProcessor->expects(self::exactly(3))->method('processMsg')->willReturnOnConsecutiveCalls(
			SqsQueueProcessorSingleMessageProcessorResult::newFailureResult(1),
			SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult(),
			SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult()
		);

		// Error reporting
		/** @var MockObject|SqsQueueProcessorErrorReportingInterface $sqsQueueProcessorErrorReporting */
		$sqsQueueProcessorErrorReporting = $this->getMockBuilder(SqsQueueProcessorErrorReportingInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorErrorReporting->expects(self::exactly(0))->method('reportError');

		// Set up logger
		$logger = new UnitTestLogger();

		$t1 = microtime(true);
		$sqsQueueProcessor = new SqsQueueProcessor(
			$config,
			new UnitTestSqsQueueProcessorTiming(2),
			$sqsQueueProcessorCleanup,
			$sqsQueueProcessorMessageProvider,
			$sqsQueueProcessorMessageStatus,
			$sqsQueueProcessorSingleMessageProcessor,
			$sqsQueueProcessorErrorReporting,
			$logger
		);
		$sqsQueueProcessor->processMessages();

		// Clean up log messages
		$loggedMessages = array_map(function(string $msg): string {
			return preg_replace('/Receipt handle: [A-Za-z0-9+\\/=]+/', 'Receipt handle: XXX', $msg);
		}, $logger->getLoggedMessages());

		// Check logged messages
		self::assertEquals([
			'INFO: (' . $messageId1 . ') - Started processing message.',
			'ERROR: (' . $messageId1 . ') - Receipt handle: XXX',
			'INFO: (' . $messageId1 . ') - Done processing message.',
			'INFO: (' . $messageId2 . ') - Started processing message.',
			'INFO: (' . $messageId2 . ') - Done processing message.',
			'INFO: (' . $messageId1 . ') - Started processing message.',
			'INFO: (' . $messageId1 . ') - Done processing message.'
		], $loggedMessages);

		// Check timing. Because of the failure, there should be at least 1 seconds. However, it shouldn't be too long to reprocess it
		$t2 = microtime(true);
		$time = $t2 - $t1;
		self::assertGreaterThanOrEqual(1, $time);
		self::assertLessThanOrEqual(2.5, $time);
	}

	/**
	 * Test processing queue delayed retry
	 */
	public function testProcessingQueueDelayedRetry(): void
	{
		$awsSdk = $this->createNewAwsSdk();
		$queueUrl = $this->createRandomSqsQueue($awsSdk);

		// Set up cleanup
		/** @var MockObject|SqsQueueProcessorCleanupInterface $sqsQueueProcessorCleanup */
		$sqsQueueProcessorCleanup = $this->getMockBuilder(SqsQueueProcessorCleanupInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorCleanup->expects(self::exactly(0))->method('cleanUpAfterExceptionProcessingMessage');

		// Set up config
		$config = new SqsQueueProcessorConfiguration();
		$config->setMaxNumMessagesPerRequest(2);
		$config->enableLogMessageStart();
		$config->enableLogMessageEnd();

		// Send some messages
		$sqs = $awsSdk->createSqs();
		$sendMessageResult1 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 1'
		]);
		$messageId1 = $sendMessageResult1['MessageId'];
		$sendMessageResult2 = $sqs->sendMessage([
			'QueueUrl' => $queueUrl,
			'MessageBody' => 'Message body 2'
		]);
		$messageId2 = $sendMessageResult2['MessageId'];

		// Set up message provider
		$sqsQueue = new SqsAvailableQueue('us-east-1', $queueUrl);
		$sqsQueueProcessorMessageProvider = new AwsHighAvailabilitySqsMessageProvider($awsSdk, $sqsQueue);

		// Set up message status interface
		/** @var MockObject|SqsQueueProcessorMessageStatusInterface $sqsQueueProcessorMessageStatus */
		$sqsQueueProcessorMessageStatus = $this->getMockBuilder(SqsQueueProcessorMessageStatusInterface::class)->disableAutoReturnValueGeneration()->getMock();

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsInProgress')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('clearMessageInProgressFlag')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(false);
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(false);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('isMessageMarkedAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		$consecutiveGenerator = new UnitTestWithConsecutiveArgsAndReturnGenerator();
		$consecutiveGenerator->addExpectedArguments([$messageId2])->addReturnValue(true);
		$consecutiveGenerator->addExpectedArguments([$messageId1])->addReturnValue(true);
		$sqsQueueProcessorMessageStatus->expects(self::exactly($consecutiveGenerator->getNumIterations()))->method('markMessageAsProcessed')->willReturnCallback($consecutiveGenerator->getWillReturnCallback());

		// Set up message processor
		/** @var MockObject|SqsQueueProcessorSingleMessageProcessorInterface $sqsQueueProcessorSingleMessageProcessor */
		$sqsQueueProcessorSingleMessageProcessor = $this->getMockBuilder(SqsQueueProcessorSingleMessageProcessorInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorSingleMessageProcessor->expects(self::exactly(3))->method('processMsg')->willReturnOnConsecutiveCalls(
			SqsQueueProcessorSingleMessageProcessorResult::newDelayedProcessingResult(1),
			SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult(),
			SqsQueueProcessorSingleMessageProcessorResult::newSuccessResult()
		);

		// Error reporting
		/** @var MockObject|SqsQueueProcessorErrorReportingInterface $sqsQueueProcessorErrorReporting */
		$sqsQueueProcessorErrorReporting = $this->getMockBuilder(SqsQueueProcessorErrorReportingInterface::class)->disableAutoReturnValueGeneration()->getMock();

		// Set up logger
		$logger = new UnitTestLogger();

		$t1 = microtime(true);
		$sqsQueueProcessor = new SqsQueueProcessor(
			$config,
			new UnitTestSqsQueueProcessorTiming(2),
			$sqsQueueProcessorCleanup,
			$sqsQueueProcessorMessageProvider,
			$sqsQueueProcessorMessageStatus,
			$sqsQueueProcessorSingleMessageProcessor,
			$sqsQueueProcessorErrorReporting,
			$logger
		);
		$sqsQueueProcessor->processMessages();

		// Clean up log messages
		$loggedMessages = array_map(function(string $msg): string {
			return preg_replace('/Receipt handle: [A-Za-z0-9+\\/=]+/', 'Receipt handle: XXX', $msg);
		}, $logger->getLoggedMessages());

		// Check logged messages
		self::assertEquals([
			'INFO: (' . $messageId1 . ') - Started processing message.',
			'INFO: (' . $messageId1 . ') - Receipt handle: XXX',
			'INFO: (' . $messageId1 . ') - Done processing message.',
			'INFO: (' . $messageId2 . ') - Started processing message.',
			'INFO: (' . $messageId2 . ') - Done processing message.',
			'INFO: (' . $messageId1 . ') - Started processing message.',
			'INFO: (' . $messageId1 . ') - Done processing message.'
		], $loggedMessages);

		// Check timing. Because of the failure, there should be at least 1 seconds. However, it shouldn't be too long to reprocess it
		$t2 = microtime(true);
		$time = $t2 - $t1;
		self::assertGreaterThanOrEqual(1, $time);
		self::assertLessThanOrEqual(2.5, $time);
	}

	/**
	 * Test processing queue SIGINT handling
	 *
	 * @group signals
	 */
	public function testProcessingQueueSigIntHandling(): void
	{
		$this->_testProcessingQueueSignalHandling(SIGINT);
	}

	/**
	 * Test processing queue SIGTERM handling
	 *
	 * @group signals
	 */
	public function testProcessingQueueSigTermHandling(): void
	{
		$this->_testProcessingQueueSignalHandling(SIGTERM);
	}

	/**
	 * Test processing queue SIGHUP handling
	 *
	 * @group signals
	 */
	public function testProcessingQueueSigHupHandling(): void
	{
		$this->_testProcessingQueueSignalHandling(SIGHUP);
	}

	/**
	 * Test processing queue SIGINT handling
	 *
	 * @param int $signal Signal
	 *
	 * @group signals
	 */
	private function _testProcessingQueueSignalHandling(int $signal): void
	{
		$awsSdk = $this->createNewAwsSdk();
		$queueUrl = $this->createRandomSqsQueue($awsSdk);

		// Set up cleanup
		/** @var MockObject|SqsQueueProcessorCleanupInterface $sqsQueueProcessorCleanup */
		$sqsQueueProcessorCleanup = $this->getMockBuilder(SqsQueueProcessorCleanupInterface::class)->disableAutoReturnValueGeneration()->getMock();
		$sqsQueueProcessorCleanup->expects(self::exactly(0))->method('cleanUpAfterExceptionProcessingMessage');

		// Set up config
		$config = new SqsQueueProcessorConfiguration();
		$config->setWaitTimeSec(5);
		$config->setMaxNumMessagesPerRequest(2);

		// Send some messages
		$sqs = $awsSdk->createSqs();

		// Set up message provider
		$sqsQueue = new SqsAvailableQueue('us-east-1', $queueUrl);
		$sqsQueueProcessorMessageProvider = new AwsHighAvailabilitySqsMessageProvider($awsSdk, $sqsQueue);

		// Set up message status interface
		/** @var MockObject|SqsQueueProcessorMessageStatusInterface $sqsQueueProcessorMessageStatus */
		$sqsQueueProcessorMessageStatus = $this->getMockBuilder(SqsQueueProcessorMessageStatusInterface::class)->disableAutoReturnValueGeneration()->getMock();

		// Set up message processor
		/** @var MockObject|SqsQueueProcessorSingleMessageProcessorInterface $sqsQueueProcessorSingleMessageProcessor */
		$sqsQueueProcessorSingleMessageProcessor = $this->getMockBuilder(SqsQueueProcessorSingleMessageProcessorInterface::class)->disableAutoReturnValueGeneration()->getMock();

		// Error reporting
		/** @var MockObject|SqsQueueProcessorErrorReportingInterface $sqsQueueProcessorErrorReporting */
		$sqsQueueProcessorErrorReporting = $this->getMockBuilder(SqsQueueProcessorErrorReportingInterface::class)->disableAutoReturnValueGeneration()->getMock();

		// Set up logger
		$logger = new UnitTestLogger();

		$t1 = microtime(true);
		$sqsQueueProcessor = new SqsQueueProcessor(
			$config,
			new UnitTestSqsQueueProcessorTiming(4), // 4 would be 20 seconds, but we'll kill it before
			$sqsQueueProcessorCleanup,
			$sqsQueueProcessorMessageProvider,
			$sqsQueueProcessorMessageStatus,
			$sqsQueueProcessorSingleMessageProcessor,
			$sqsQueueProcessorErrorReporting,
			$logger
		);

		// Send signal
		$pid = posix_getpid();
		$cmd = 'sleep 1 && kill -' . $signal . ' ' . $pid . ' 2>&1 > /dev/null &';
		exec($cmd);

		$sqsQueueProcessor->processMessages();

		// Clean up log messages
		$loggedMessages = array_map(function(string $msg): string {
			return preg_replace('/Receipt handle: [A-Za-z0-9+\\/=]+/', 'Receipt handle: XXX', $msg);
		}, $logger->getLoggedMessages());

		// Check logged messages
		self::assertEquals([
			'INFO: Signal ' . $signal . ' detected. Will shut down.'
		], $loggedMessages);

		// Check timing. Because of the failure, there should be at least 1 seconds. However, it shouldn't be too long to reprocess it
		$t2 = microtime(true);
		$time = $t2 - $t1;
		self::assertGreaterThanOrEqual(5, $time);
		self::assertLessThanOrEqual(9.5, $time);
	}
}
