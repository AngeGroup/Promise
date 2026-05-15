<?php

declare(strict_types=1);

namespace promise\Tests\internal;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use promise\internal\FulfilledPromise;
use promise\internal\RejectedPromise;
use promise\PromiseInterface;
use RuntimeException;

final class FulfilledPromiseTest extends TestCase {
	public function testIsResolvedReturnsTrue(): void {
		self::assertTrue((new FulfilledPromise(1))->isResolved());
	}

	public function testThenInvokesOnFulfilledWithValue(): void {
		$got = null;
		(new FulfilledPromise(42))->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame(42, $got);
	}

	public function testThenWithoutOnFulfilledReturnsSelf(): void {
		$promise = new FulfilledPromise('x');
		self::assertSame($promise, $promise->then());
	}

	public function testThenReturningValueProducesFulfilledPromise(): void {
		$next = (new FulfilledPromise(1))->then(fn ($v) => $v + 1);
		$got = null;
		$next->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame(2, $got);
	}

	public function testThenThrowProducesRejectedPromise(): void {
		$next = (new FulfilledPromise(1))->then(function (): void {
			throw new RuntimeException('bad');
		});
		$reason = null;
		$next->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertInstanceOf(RuntimeException::class, $reason);
	}

	public function testCatchReturnsSelf(): void {
		$promise = new FulfilledPromise('x');
		self::assertSame($promise, $promise->catch(fn ($e) => null));
	}

	public function testFinallyRunsAndPreservesValue(): void {
		$ran = false;
		$got = null;
		(new FulfilledPromise('value'))
			->finally(function () use (&$ran): void { $ran = true; })
			->then(function ($v) use (&$got): void { $got = $v; });
		self::assertTrue($ran);
		self::assertSame('value', $got);
	}

	public function testCancelIsNoOp(): void {
		$promise = new FulfilledPromise(1);
		$promise->cancel();
		self::assertTrue($promise->isResolved());
	}

	public function testWaitIsNoOp(): void {
		$promise = new FulfilledPromise(1);
		$promise->wait();
		self::assertTrue($promise->isResolved());
	}

	public function testConstructorRejectsPromiseValue(): void {
		$this->expectException(InvalidArgumentException::class);
		new FulfilledPromise(new FulfilledPromise(1));
	}

	public function testNullValueIsAllowed(): void {
		$promise = new FulfilledPromise();
		$got = 'sentinel';
		$promise->then(function ($v) use (&$got): void { $got = $v; });
		self::assertNull($got);
	}

	public function testImplementsPromiseInterface(): void {
		self::assertInstanceOf(PromiseInterface::class, new FulfilledPromise(1));
	}

	public function testThenReturningRejectedPromiseStaysRejected(): void {
		$err = new RuntimeException('inner');
		$next = (new FulfilledPromise(1))->then(fn () => new RejectedPromise($err));
		$reason = null;
		$next->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertSame($err, $reason);
	}
}
