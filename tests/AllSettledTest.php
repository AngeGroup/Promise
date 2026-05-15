<?php

declare(strict_types=1);

namespace promise\Tests;

use PHPUnit\Framework\TestCase;
use promise\Deferred;
use RuntimeException;

use function promise\allSettled;
use function promise\reject;
use function promise\resolve;

final class AllSettledTest extends TestCase {
	public function testResolvesWithMixedResultsPreservingKeys(): void {
		$err = new RuntimeException('boom');
		$got = null;
		allSettled([
			'a' => resolve(1),
			'b' => reject($err),
			'c' => resolve('three'),
		])->then(function ($v) use (&$got): void { $got = $v; });

		self::assertSame([
			'a' => ['status' => 'fulfilled', 'value' => 1],
			'b' => ['status' => 'rejected', 'reason' => $err],
			'c' => ['status' => 'fulfilled', 'value' => 'three'],
		], $got);
	}

	public function testEmptyResolvesEmpty(): void {
		$got = 'sentinel';
		allSettled([])->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame([], $got);
	}

	public function testNeverShortCircuitsOnRejection(): void {
		$err = new RuntimeException();
		$reason = null;
		$got = null;
		allSettled([reject($err), reject($err)])->then(
			function ($v) use (&$got): void { $got = $v; },
			function ($e) use (&$reason): void { $reason = $e; },
		);
		self::assertNull($reason);
		self::assertCount(2, $got);
	}

	public function testWaitsForAllPendingToSettle(): void {
		$d1 = new Deferred();
		$d2 = new Deferred();

		$got = null;
		allSettled([$d1->promise(), $d2->promise()])
			->then(function ($v) use (&$got): void { $got = $v; });

		self::assertNull($got);
		$d1->resolve('a');
		self::assertNull($got);
		$d2->reject(new RuntimeException('b-fail'));

		self::assertSame('fulfilled', $got[0]['status']);
		self::assertSame('a', $got[0]['value']);
		self::assertSame('rejected', $got[1]['status']);
		self::assertSame('b-fail', $got[1]['reason']->getMessage());
	}

	public function testTreatsNonPromiseValuesAsFulfilled(): void {
		$got = null;
		allSettled(['plain', 42])->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame([
			['status' => 'fulfilled', 'value' => 'plain'],
			['status' => 'fulfilled', 'value' => 42],
		], $got);
	}
}
