<?php

declare(strict_types=1);

namespace TestAuxFiles;

use PHPUnit\Framework\TestCase;

/** Unit test case */
class UnitTestCase extends TestCase
{
	/** @var callable[] Tear down functions */
	private array $tearDownFuncs = [];

	/**
	 * Tear down
	 */
	protected function tearDown(): void
	{
		parent::tearDown();
		foreach ($this->tearDownFuncs as $func)
			call_user_func($func);
		$this->tearDownFuncs = [];
	}

	/**
	 * Create new AWS sdk
	 *
	 * @return \Aws\Sdk AWS SDK
	 */
	protected function createNewAwsSdk(): \Aws\Sdk
	{
		$awsSdk = new \Aws\Sdk([
			'credentials' => [
				'key' => 'AKIAIOSFODNN7EXAMPLE',
				'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'
			],
			'Sqs' => [
				'endpoint' => 'http://sqs.us-east-1.localhost.localstack.cloud:4566'
			]
		]);
		return $awsSdk;
	}

	/**
	 * Create random SQS queue
	 *
	 * @param \Aws\Sdk $awsSdk AWS sdk
	 *
	 * @return string SQS queue URL
	 */
	public function createRandomSqsQueue(\Aws\Sdk $awsSdk): string
	{
		$queueName = 'unit-test-' . time() . '-' . preg_replace('/[^A-Za-z0-9]/', '', base64_encode(random_bytes(9)));
		$result = $awsSdk->createSqs()->createQueue([
			'QueueName' => $queueName
		]);
		$queueUrl = $result['QueueUrl'];

		$this->tearDownFuncs[] = function() use ($awsSdk, $queueUrl) {
			$awsSdk->createSqs()->deleteQueue([
				'QueueUrl' => $queueUrl
			]);
		};

		return $queueUrl;
	}
}
