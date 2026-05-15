<?php

declare(strict_types=1);

namespace promise\Tests\internal;

use PHPUnit\Framework\TestCase;
use promise\Deferred;
use promise\internal\CancellationQueue;
use promise\internal\FulfilledPromise;
use RuntimeException;
use stdClass;

final class CancellationQueueTest extends TestCase {
	public function testEnqueueAndInvokeCancelsCancellable(): void {
		$cancelled = false;
		$deferred = new Deferred(function () use (&$cancelled): void { $cancelled = true; });

		$queue = new CancellationQueue();
		$queue->enqueue($deferred->promise());
		($queue)();
		self::assertTrue($cancelled);
	}

	public function testEnqueueIgnoresNonObjects(): void {
		$queue = new CancellationQueue();
		$queue->enqueue('string');
		$queue->enqueue(42);
		$queue->enqueue(null);
		$queue->enqueue(['array']);
		($queue)();
		self::assertTrue(true); // no exception
	}

	public function testEnqueueIgnoresObjectMissingThen(): void {
		$queue = new CancellationQueue();
		$queue->enqueue(new stdClass());
		($queue)();
		self::assertTrue(true); // no exception
	}

	public function testEnqueueIgnoresObjectMissingCancel(): void {
		$obj = new class {
			public function then(): void {}
		};
		$queue = new CancellationQueue();
		$queue->enqueue($obj);
		($queue)();
		self::assertTrue(true); // no exception
	}

	public function testInvokeIsIdempotent(): void {
		$count = 0;
		$obj = new class($count) {
			public function __construct(private int &$count) {}

			public function then(): void {}

			public function cancel(): void {
				$this->count++;
			}
		};

		$queue = new CancellationQueue();
		$queue->enqueue($obj);
		($queue)();
		($queue)();
		self::assertSame(1, $count);
	}

	public function testEnqueueAfterStartedDrainsImmediately(): void {
		$cancelled = false;
		$obj = new class($cancelled) {
			public function __construct(private bool &$flag) {}

			public function then(): void {}

			public function cancel(): void {
				$this->flag = true;
			}
		};

		$queue = new CancellationQueue();
		($queue)(); // start with empty queue
		self::assertFalse($cancelled);

		$queue->enqueue($obj);
		self::assertTrue($cancelled);
	}

	public function testFulfilledPromiseCanBeQueued(): void {
		// FulfilledPromise has both then() and cancel(); cancel is a no-op.
		$queue = new CancellationQueue();
		$queue->enqueue(new FulfilledPromise(1));
		($queue)();
		self::assertTrue(true);
	}

	public function testCancelExceptionIsRethrown(): void {
		$obj = new class {
			public function then(): void {}

			public function cancel(): void {
				throw new RuntimeException('cancel-failed');
			}
		};

		$queue = new CancellationQueue();
		$queue->enqueue($obj);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('cancel-failed');
		($queue)();
	}
}
