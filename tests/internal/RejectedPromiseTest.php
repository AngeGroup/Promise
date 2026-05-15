<?php

declare(strict_types=1);

namespace promise\Tests\internal;

use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;
use promise\internal\RejectedPromise;
use promise\PromiseInterface;
use RuntimeException;

final class RejectedPromiseTest extends TestCase {
	public function testIsResolvedReturnsTrue(): void {
		self::assertTrue((new RejectedPromise(new Exception()))->isResolved());
	}

	public function testThenWithoutOnRejectedReturnsSelf(): void {
		$promise = new RejectedPromise(new Exception());
		self::assertSame($promise, $promise->then(fn ($v) => $v));
	}

	public function testThenInvokesOnRejectedWithReason(): void {
		$err = new RuntimeException('x');
		$got = null;
		(new RejectedPromise($err))->then(null, function ($e) use (&$got): void { $got = $e; });
		self::assertSame($err, $got);
	}

	public function testThenReturningValueRecovers(): void {
		$next = (new RejectedPromise(new RuntimeException()))->then(null, fn () => 'recovered');
		$got = null;
		$next->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame('recovered', $got);
	}

	public function testThenThrowProducesNewRejection(): void {
		$next = (new RejectedPromise(new RuntimeException('first')))
			->then(null, function (): void { throw new RuntimeException('second'); });
		$reason = null;
		$next->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertSame('second', $reason->getMessage());
	}

	public function testCatchInvokesHandlerForMatchingType(): void {
		$err = new RuntimeException();
		$got = null;
		(new RejectedPromise($err))->catch(function (RuntimeException $e) use (&$got): void { $got = $e; });
		self::assertSame($err, $got);
	}

	public function testCatchSkipsHandlerForNonMatchingType(): void {
		$err = new RuntimeException();
		$called = false;
		$promise = (new RejectedPromise($err))
			->catch(function (LogicException $e) use (&$called): void { $called = true; });
		self::assertFalse($called);

		$reason = null;
		$promise->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertSame($err, $reason);
	}

	public function testCatchWithUntypedHandlerAlwaysMatches(): void {
		$got = null;
		(new RejectedPromise(new RuntimeException()))->catch(function ($e) use (&$got): void { $got = $e; });
		self::assertNotNull($got);
	}

	public function testFinallyRunsAndPreservesReason(): void {
		$err = new RuntimeException('keep');
		$ran = false;
		$reason = null;
		(new RejectedPromise($err))
			->finally(function () use (&$ran): void { $ran = true; })
			->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertTrue($ran);
		self::assertSame($err, $reason);
	}

	public function testCancelIsNoOp(): void {
		$promise = new RejectedPromise(new Exception());
		$promise->cancel();
		self::assertTrue($promise->isResolved());
	}

	public function testWaitIsNoOp(): void {
		$promise = new RejectedPromise(new Exception());
		$promise->wait();
		self::assertTrue($promise->isResolved());
	}

	public function testImplementsPromiseInterface(): void {
		self::assertInstanceOf(PromiseInterface::class, new RejectedPromise(new Exception()));
	}
}
