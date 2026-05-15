<?php

declare(strict_types=1);

namespace promise\internal;

use function promise\_checkTypehint;
use promise\PromiseInterface;
use function promise\resolve;

use ReflectionException;
use Throwable;

final class RejectedPromise implements PromiseInterface {
	private Throwable $reason;

	public function __construct(Throwable $reason) {
		$this->reason = $reason;
	}

	public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface {
		if(null === $onRejected) {
			return $this;
		}
		try {
			return resolve($onRejected($this->reason));
		} catch (Throwable $exception) {
			return new RejectedPromise($exception);
		}
	}

	/**
	 * @phpstan-param callable(Throwable): mixed $onRejected
	 * @throws ReflectionException
	 */
	public function catch(callable $onRejected): PromiseInterface {
		if(!_checkTypehint($onRejected, $this->reason)) {
			return $this;
		}
		return $this->then(null, $onRejected);
	}

	/**
	 * @phpstan-param callable(): mixed $onFulfilledOrRejected
	 */
	public function finally(callable $onFulfilledOrRejected): PromiseInterface {
		return $this->then(null, function(Throwable $reason) use ($onFulfilledOrRejected): PromiseInterface {
			return resolve($onFulfilledOrRejected())->then(function() use ($reason): PromiseInterface {
				return new RejectedPromise($reason);
			});
		});
	}

	public function cancel(): void {}

	public function wait(): void {}

	public function isResolved(): bool {
		return true;
	}

}
