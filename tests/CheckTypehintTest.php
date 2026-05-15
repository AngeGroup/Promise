<?php

declare(strict_types=1);

namespace promise\Tests;

use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;
use Throwable;

use function promise\_checkTypehint;

final class CheckTypehintTest extends TestCase {
	public function testNoParameterMatches(): void {
		self::assertTrue(_checkTypehint(function (): void {}, new RuntimeException()));
	}

	public function testUntypedParameterMatches(): void {
		self::assertTrue(_checkTypehint(function ($e): void {}, new RuntimeException()));
	}

	public function testNamedTypeMatches(): void {
		self::assertTrue(_checkTypehint(function (RuntimeException $e): void {}, new RuntimeException()));
	}

	public function testNamedTypeMatchesParentType(): void {
		self::assertTrue(_checkTypehint(function (Throwable $e): void {}, new RuntimeException()));
	}

	public function testNamedTypeDoesNotMatchSiblingType(): void {
		self::assertFalse(_checkTypehint(function (LogicException $e): void {}, new RuntimeException()));
	}

	public function testUnionTypeMatchesFirstMember(): void {
		$cb = function (RuntimeException|LogicException $e): void {};
		self::assertTrue(_checkTypehint($cb, new RuntimeException()));
	}

	public function testUnionTypeMatchesSecondMember(): void {
		$cb = function (RuntimeException|LogicException $e): void {};
		self::assertTrue(_checkTypehint($cb, new LogicException()));
	}

	public function testUnionTypeDoesNotMatchUnrelated(): void {
		$cb = function (RuntimeException|LogicException $e): void {};
		// Exception is parent of both — not a member of the union directly.
		self::assertFalse(_checkTypehint($cb, new Exception()));
	}

	public function testIntersectionTypeRequiresAllMembers(): void {
		$cb = function (Throwable&Stringable $e): void {};
		// Exception is both Throwable and Stringable (has __toString).
		self::assertTrue(_checkTypehint($cb, new RuntimeException()));
	}

	public function testIntersectionTypeFailsWhenOneIsMissing(): void {
		$nonThrowable = new class implements Stringable {
			public function __toString(): string {
				return 'x';
			}
		};
		$cb = function (Throwable&Stringable $e): void {};
		// $nonThrowable isn't a Throwable; we can't pass a non-Throwable directly,
		// so use a Throwable that does not satisfy a fictional intersection.
		// Easier: test the inverse — intersection of unrelated interfaces should fail.
		$cb2 = function (\Iterator&Throwable $e): void {};
		self::assertFalse(_checkTypehint($cb2, new RuntimeException()));
	}

	public function testCallableArrayWithMethodHandler(): void {
		$handler = new class {
			public function handle(RuntimeException $e): void {}
		};
		self::assertTrue(_checkTypehint([$handler, 'handle'], new RuntimeException()));
		self::assertFalse(_checkTypehint([$handler, 'handle'], new LogicException()));
	}

	public function testInvokableObject(): void {
		$invokable = new class {
			public function __invoke(LogicException $e): void {}
		};
		self::assertTrue(_checkTypehint($invokable, new LogicException()));
		self::assertFalse(_checkTypehint($invokable, new RuntimeException()));
	}
}
