<?php

declare(strict_types=1);

namespace promise;

use ReflectionException;
use Throwable;

class Deferred {
	private PromiseInterface $promise;

	/** @var callable(mixed):void */
	private $resolveCallback;

	/** @var callable(Throwable):void */
	private $rejectCallback;

	/**
	 * @throws ReflectionException
	 */
	public function __construct(?callable $canceller = null) {
		$this->promise = new Promise(function($resolve, $reject): void {
			$this->resolveCallback = $resolve;
			$this->rejectCallback = $reject;
		}, $canceller);
	}

	public function promise(): PromiseInterface {
		return $this->promise;
	}

	public function resolve(mixed $value): void {
		($this->resolveCallback)($value);
	}

	public function reject(Throwable $reason): void {
		($this->rejectCallback)($reason);
	}

}
