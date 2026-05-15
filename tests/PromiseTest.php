<?php

declare(strict_types=1);

namespace promise\Tests;

use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;
use promise\Promise;
use promise\PromiseInterface;
use RuntimeException;

use function promise\reject;
use function promise\resolve;

final class PromiseTest extends TestCase {
	public function testResolverReceivesValueViaThen(): void {
		$promise = new Promise(function ($resolve): void {
			$resolve(42);
		});

		$got = null;
		$promise->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame(42, $got);
	}

	public function testResolverWithZeroArgsIsCalled(): void {
		$called = false;
		new Promise(function () use (&$called): void { $called = true; });
		self::assertTrue($called);
	}

	public function testRejectionPropagates(): void {
		$err = new RuntimeException('boom');
		$promise = new Promise(function ($resolve, $reject) use ($err): void {
			$reject($err);
		});

		$reason = null;
		$promise->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertSame($err, $reason);
	}

	public function testThrowInResolverBecomesRejection(): void {
		$promise = new Promise(function (): void {
			throw new RuntimeException('explode');
		});

		$reason = null;
		$promise->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertInstanceOf(RuntimeException::class, $reason);
		self::assertSame('explode', $reason->getMessage());
	}

	public function testThenChainTransformsValue(): void {
		$got = null;
		(new Promise(function ($r): void { $r(5); }))
			->then(fn ($v) => $v * 2)
			->then(fn ($v) => $v + 1)
			->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame(11, $got);
	}

	public function testRejectionPropagatesThroughChainWithoutHandler(): void {
		$err = new RuntimeException('boom');
		$reason = null;
		(new Promise(function ($r, $rj) use ($err): void { $rj($err); }))
			->then(fn ($v) => $v + 1)
			->then(fn ($v) => $v + 1)
			->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertSame($err, $reason);
	}

	public function testCatchHandlesRejection(): void {
		$err = new RuntimeException('x');
		$reason = null;
		(new Promise(function ($r, $rj) use ($err): void { $rj($err); }))
			->catch(function (RuntimeException $e) use (&$reason): void { $reason = $e; });
		self::assertSame($err, $reason);
	}

	public function testCatchSkipsNonMatchingTypeHint(): void {
		$err = new RuntimeException('x');
		$called = false;
		$final = null;
		(new Promise(function ($r, $rj) use ($err): void { $rj($err); }))
			->catch(function (LogicException $e) use (&$called): void { $called = true; })
			->then(null, function ($e) use (&$final): void { $final = $e; });
		self::assertFalse($called);
		self::assertSame($err, $final);
	}

	public function testFinallyRunsOnFulfilled(): void {
		$ran = false;
		$value = null;
		(new Promise(function ($r): void { $r('ok'); }))
			->finally(function () use (&$ran): void { $ran = true; })
			->then(function ($v) use (&$value): void { $value = $v; });
		self::assertTrue($ran);
		self::assertSame('ok', $value);
	}

	public function testFinallyRunsOnRejected(): void {
		$err = new RuntimeException('nope');
		$ran = false;
		$reason = null;
		(new Promise(function ($r, $rj) use ($err): void { $rj($err); }))
			->finally(function () use (&$ran): void { $ran = true; })
			->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertTrue($ran);
		self::assertSame($err, $reason);
	}

	public function testFinallyThrowingTurnsFulfilledIntoRejection(): void {
		$reason = null;
		(new Promise(function ($r): void { $r('value'); }))
			->finally(function (): void { throw new RuntimeException('finalize-fail'); })
			->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertInstanceOf(RuntimeException::class, $reason);
		self::assertSame('finalize-fail', $reason->getMessage());
	}

	public function testIsResolvedReflectsState(): void {
		$promise = new Promise(function (): void {});
		self::assertFalse($promise->isResolved());

		$resolved = new Promise(function ($r): void { $r(1); });
		self::assertTrue($resolved->isResolved());

		$rejected = new Promise(function ($r, $rj): void { $rj(new RuntimeException()); });
		self::assertTrue($rejected->isResolved());
	}

	public function testCancelInvokesCanceller(): void {
		$cancelled = false;
		$promise = new Promise(
			function (): void {},
			function () use (&$cancelled): void { $cancelled = true; },
		);
		$promise->cancel();
		self::assertTrue($cancelled);
	}

	public function testCancelOnSettledPromiseDoesNothing(): void {
		$cancelled = false;
		$promise = new Promise(
			function ($r): void { $r(1); },
			function () use (&$cancelled): void { $cancelled = true; },
		);
		$promise->cancel();
		self::assertFalse($cancelled);
	}

	public function testCannotResolveWithSelf(): void {
		// Capture the resolve callback so we can settle the promise *after*
		// the local $promise variable holds the constructed instance.
		$resolveFn = null;
		$promise = new Promise(function ($r) use (&$resolveFn): void {
			$resolveFn = $r;
		});

		$reason = null;
		$promise->then(null, function ($e) use (&$reason): void { $reason = $e; });

		$resolveFn($promise);
		self::assertInstanceOf(LogicException::class, $reason);
	}

	public function testResolveWithPromiseFollowsIt(): void {
		$inner = resolve('inner-value');
		$outer = new Promise(function ($r) use ($inner): void { $r($inner); });

		$got = null;
		$outer->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame('inner-value', $got);
	}

	public function testThenReturnsPromiseInterface(): void {
		$promise = (new Promise(function ($r): void { $r(1); }))->then(null);
		self::assertInstanceOf(PromiseInterface::class, $promise);
	}

	public function testWaitIsNoOp(): void {
		$promise = new Promise(function (): void {});
		$promise->wait();
		self::assertFalse($promise->isResolved());
	}

	public function testHandlerRegisteredBeforeResolveStillRuns(): void {
		// Capture resolve callback to settle later
		$resolveFn = null;
		$promise = new Promise(function ($r) use (&$resolveFn): void {
			$resolveFn = $r;
		});

		$got = null;
		$promise->then(function ($v) use (&$got): void { $got = $v; });

		self::assertNull($got);
		$resolveFn('late');
		self::assertSame('late', $got);
	}

	public function testRejectAfterResolveIsIgnored(): void {
		$got = null;
		$reason = null;
		new Promise(function ($r, $rj): void {
			$r('first');
			$rj(new RuntimeException('second'));
		})->then(
			function ($v) use (&$got): void { $got = $v; },
			function ($e) use (&$reason): void { $reason = $e; },
		);
		self::assertSame('first', $got);
		self::assertNull($reason);
	}

	public function testResolveAfterRejectIsIgnored(): void {
		$got = null;
		$reason = null;
		new Promise(function ($r, $rj): void {
			$rj(new RuntimeException('first'));
			$r('second');
		})->then(
			function ($v) use (&$got): void { $got = $v; },
			function ($e) use (&$reason): void { $reason = $e; },
		);
		self::assertNull($got);
		self::assertSame('first', $reason->getMessage());
	}

	public function testRejectShortcutFunction(): void {
		$reason = null;
		reject(new Exception('rej'))->then(null, function ($e) use (&$reason): void { $reason = $e; });
		self::assertInstanceOf(Exception::class, $reason);
	}
}
