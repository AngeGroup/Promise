<?php
declare(strict_types=1);

namespace promise\internal;

use promise\PromiseInterface;

use function promise\resolve;

use InvalidArgumentException;
use Throwable;

final class FulfilledPromise implements PromiseInterface {
	/** @var mixed|null */
	private mixed $value;

	public function __construct($value = null) {
		if($value instanceof PromiseInterface) {
			throw new InvalidArgumentException("You cannot create promise\\internal\\FulfilledPromise with a promise. Use promise\\resolve(\$promiseOrValue) instead.");
		}
		$this->value = $value;
	}

	public function then(callable $onFulfilled = null, callable $onRejected = null): PromiseInterface {
		if (null === $onFulfilled) {
			return $this;
		}

		try {
			return resolve($onFulfilled($this->value));
		} catch (Throwable $exception) {
			return new RejectedPromise($exception);
		}
	}

	public function catch(callable $onRejected): PromiseInterface {
		return $this;
	}

	/**
	 * @phpstan-param callable(): mixed $onFulfilledOrRejected
	 */
	public function finally(callable $onFulfilledOrRejected): PromiseInterface {
		return $this->then(function($value) use ($onFulfilledOrRejected): PromiseInterface {
			return resolve($onFulfilledOrRejected())->then(function() use ($value) {
				return $value;
			});
		});
	}

	public function cancel(): void {}


	public function wait(): void {
		// NOOP
	}

	public function isResolved(): bool {
		return true;
	}

}
