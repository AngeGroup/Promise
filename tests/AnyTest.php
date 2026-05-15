<?php

declare(strict_types=1);

namespace promise\Tests;

use PHPUnit\Framework\TestCase;
use promise\Deferred;
use promise\exception\CompositeException;
use promise\exception\LengthException;
use RuntimeException;

use function promise\any;
use function promise\reject;
use function promise\resolve;

final class AnyTest extends TestCase {
	public function testResolvesWithFirstFulfilled(): void {
		$got = null;
		any([reject(new RuntimeException()), resolve('winner'), resolve('late')])
			->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame('winner', $got);
	}

	public function testRejectsCompositeWhenAllReject(): void {
		$err1 = new RuntimeException('one');
		$err2 = new RuntimeException('two');
		$reason = null;
		any([reject($err1), reject($err2)])
			->then(null, function ($e) use (&$reason): void { $reason = $e; });

		self::assertInstanceOf(CompositeException::class, $reason);
		self::assertSame([$err1, $err2], $reason->getThrowables());
	}

	public function testRejectsLengthExceptionOnEmptyIterable(): void {
		$reason = null;
		any([])->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertInstanceOf(LengthException::class, $reason);
	}

	public function testNonPromiseValueIsTreatedAsFulfilled(): void {
		$got = null;
		any(['plain'])->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame('plain', $got);
	}

	public function testWaitsForPendingPromisesBeforeRejecting(): void {
		$d1 = new Deferred();
		$d2 = new Deferred();

		$reason = null;
		any([$d1->promise(), $d2->promise()])
			->then(null, function ($e) use (&$reason): void { $reason = $e; });

		self::assertNull($reason);
		$d1->reject(new RuntimeException('a'));
		self::assertNull($reason);
		$d2->reject(new RuntimeException('b'));
		self::assertInstanceOf(CompositeException::class, $reason);
	}

	public function testFirstFulfilledShortCircuitsLaterRejections(): void {
		$d1 = new Deferred();
		$d2 = new Deferred();

		$got = null;
		$reason = null;
		any([$d1->promise(), $d2->promise()])->then(
			function ($v) use (&$got): void { $got = $v; },
			function ($e) use (&$reason): void { $reason = $e; },
		);

		$d2->resolve('value');
		$d1->reject(new RuntimeException('after-the-fact'));

		self::assertSame('value', $got);
		self::assertNull($reason);
	}
}
