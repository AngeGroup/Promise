<?php

declare(strict_types=1);

namespace promise\Tests;

use PHPUnit\Framework\TestCase;
use promise\internal\FulfilledPromise;
use promise\internal\RejectedPromise;
use promise\Promise;
use promise\PromiseInterface;
use RuntimeException;

use function promise\reject;
use function promise\resolve;

final class FunctionsTest extends TestCase {
	public function testResolveReturnsFulfilledPromiseForValue(): void {
		$promise = resolve('hello');
		self::assertInstanceOf(PromiseInterface::class, $promise);

		$got = null;
		$promise->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame('hello', $got);
	}

	public function testResolveReturnsSamePromiseForPromiseInput(): void {
		$inner = new FulfilledPromise(1);
		self::assertSame($inner, resolve($inner));
	}

	public function testResolveReturnsSamePromiseForOurOwnPromise(): void {
		$promise = new Promise(function ($r): void { $r(1); });
		self::assertSame($promise, resolve($promise));
	}

	public function testResolveAdaptsThenable(): void {
		$thenable = new class {
			public function then(callable $onFulfilled): void {
				$onFulfilled('from-thenable');
			}
		};

		$got = null;
		resolve($thenable)->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame('from-thenable', $got);
	}

	public function testResolveAdaptsCancellableThenable(): void {
		$cancelled = false;
		$thenable = new class($cancelled) {
			public function __construct(private bool &$flag) {}

			public function then(callable $onFulfilled): void {}

			public function cancel(): void {
				$this->flag = true;
			}
		};

		$promise = resolve($thenable);
		$promise->cancel();
		self::assertTrue($cancelled);
	}

	public function testResolveWithNullProducesFulfilledPromise(): void {
		$got = 'sentinel';
		resolve(null)->then(function ($v) use (&$got): void { $got = $v; });
		self::assertNull($got);
	}

	public function testRejectReturnsRejectedPromise(): void {
		$err = new RuntimeException('x');
		$promise = reject($err);
		self::assertInstanceOf(RejectedPromise::class, $promise);

		$reason = null;
		$promise->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertSame($err, $reason);
	}
}
