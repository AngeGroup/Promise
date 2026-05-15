<?php

declare(strict_types=1);

namespace promise\Tests;

use PHPUnit\Framework\TestCase;
use promise\Deferred;
use RuntimeException;

use function promise\race;
use function promise\reject;
use function promise\resolve;

final class RaceTest extends TestCase {
	public function testFulfillsWithFirstSettledValue(): void {
		$got = null;
		race([resolve('first'), resolve('second')])
			->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame('first', $got);
	}

	public function testRejectsOnFirstRejection(): void {
		$err = new RuntimeException('first-fail');
		$reason = null;
		race([reject($err), resolve('late')])
			->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertSame($err, $reason);
	}

	public function testResolvesAsSoonAsAnyPendingPromiseSettles(): void {
		$d1 = new Deferred();
		$d2 = new Deferred();

		$got = null;
		race([$d1->promise(), $d2->promise()])
			->then(function ($v) use (&$got): void { $got = $v; });

		self::assertNull($got);
		$d2->resolve('winner');
		self::assertSame('winner', $got);

		// Settling the loser should not change the outcome.
		$d1->resolve('loser');
		self::assertSame('winner', $got);
	}

	public function testNonPromiseValueWinsImmediately(): void {
		$got = null;
		race(['plain', resolve('promise')])
			->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame('plain', $got);
	}
}
