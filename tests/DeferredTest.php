<?php

declare(strict_types=1);

namespace promise\Tests;

use PHPUnit\Framework\TestCase;
use promise\Deferred;
use promise\PromiseInterface;
use RuntimeException;

final class DeferredTest extends TestCase {
	public function testPromiseReturnsPromiseInterface(): void {
		$deferred = new Deferred();
		self::assertInstanceOf(PromiseInterface::class, $deferred->promise());
	}

	public function testResolveSettlesPromise(): void {
		$deferred = new Deferred();
		$got = null;
		$deferred->promise()->then(function ($v) use (&$got): void { $got = $v; });

		self::assertNull($got);
		$deferred->resolve('hello');
		self::assertSame('hello', $got);
	}

	public function testRejectSettlesPromise(): void {
		$deferred = new Deferred();
		$reason = null;
		$deferred->promise()->then(null, function ($e) use (&$reason): void { $reason = $e; });

		$err = new RuntimeException('fail');
		$deferred->reject($err);
		self::assertSame($err, $reason);
	}

	public function testCancellerIsCalledOnPromiseCancel(): void {
		$cancelled = false;
		$deferred = new Deferred(function () use (&$cancelled): void { $cancelled = true; });
		$deferred->promise()->cancel();
		self::assertTrue($cancelled);
	}

	public function testWithoutCanceller(): void {
		$deferred = new Deferred();
		$deferred->promise()->cancel();
		// no canceller, no value, no exception
		self::assertFalse($deferred->promise()->isResolved());
	}

	public function testResolveBeforeAttachmentStillDeliversToLaterThen(): void {
		$deferred = new Deferred();
		$deferred->resolve('early');

		$got = null;
		$deferred->promise()->then(function ($v) use (&$got): void { $got = $v; });
		self::assertSame('early', $got);
	}
}
