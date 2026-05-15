<?php

declare(strict_types=1);

namespace promise\Tests\exception;

use Exception;
use PHPUnit\Framework\TestCase;
use promise\exception\CompositeException;
use RuntimeException;

final class CompositeExceptionTest extends TestCase {
	public function testStoresAndReturnsThrowables(): void {
		$err1 = new RuntimeException('one');
		$err2 = new Exception('two');

		$composite = new CompositeException([$err1, $err2], 'wrapper');
		self::assertSame([$err1, $err2], $composite->getThrowables());
		self::assertSame('wrapper', $composite->getMessage());
	}

	public function testEmptyThrowablesArrayIsPermitted(): void {
		$composite = new CompositeException([], 'empty');
		self::assertSame([], $composite->getThrowables());
	}

	public function testPropagatesCodeAndPrevious(): void {
		$prev = new RuntimeException('prev');
		$composite = new CompositeException([], 'msg', 42, $prev);
		self::assertSame(42, $composite->getCode());
		self::assertSame($prev, $composite->getPrevious());
	}
}
