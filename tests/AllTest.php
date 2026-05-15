<?php

declare(strict_types=1);

namespace promise\Tests;

use PHPUnit\Framework\TestCase;
use promise\Deferred;
use RuntimeException;

use function promise\all;
use function promise\reject;
use function promise\resolve;

final class AllTest extends TestCase {
	public function testFulfillsWithArrayOfValuesPreservingKeys(): void {
		$got = null;
		all([
			'a' => resolve(1),
			'b' => resolve(2),
			'c' => resolve(3),
		])->then(function ($v) use (&$got): void { $got = $v; });

		self::assertSame(['a' => 1, 'b' => 2, 'c' => 3], $got);
	}

	public function testAcceptsNonPromiseValues(): void {
		$got = null;
		all([1, 'two', null])->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame([1, 'two', null], $got);
	}

	public function testRejectsOnFirstRejection(): void {
		$err = new RuntimeException('boom');
		$reason = null;
		all([resolve(1), reject($err), resolve(3)])
			->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertSame($err, $reason);
	}

	public function testResolvesEmptyIterableImmediately(): void {
		$got = 'sentinel';
		all([])->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame([], $got);
	}

	public function testWaitsForAllPendingPromisesToFulfill(): void {
		$d1 = new Deferred();
		$d2 = new Deferred();

		$got = null;
		all([$d1->promise(), $d2->promise()])
			->then(function ($v) use (&$got): void { $got = $v; });

		self::assertNull($got);
		$d1->resolve('a');
		self::assertNull($got);
		$d2->resolve('b');
		self::assertSame(['a', 'b'], $got);
	}

	public function testAcceptsGenerator(): void {
		$gen = (function (): \Generator {
			yield resolve(1);
			yield 2;
			yield resolve(3);
		})();

		$got = null;
		all($gen)->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame([1, 2, 3], $got);
	}
}
