<?php

declare(strict_types=1);

namespace promise\internal;

use function array_push;
use function is_object;
use function key;
use function method_exists;
use Throwable;

class CancellationQueue {
	private bool $started = false;
	/** @var list<object> */
	private array $queue = [];

	/**
	 * @throws Throwable
	 */
	public function __invoke(): void {
		if($this->started) {
			return;
		}
		$this->started = true;
		$this->drain();
	}

	public function enqueue(mixed $cancellable): void {
		if(!is_object($cancellable) || !method_exists($cancellable, 'then') || !method_exists($cancellable, 'cancel')) {
			return;
		}
		$length = array_push($this->queue, $cancellable);
		if($this->started && 1 === $length) {
			$this->drain();
		}
	}

	/**
	 * @throws Throwable
	 */
	private function drain(): void {
		if($this->queue === []) {
			return;
		}
		for ($i = key($this->queue); isset($this->queue[$i]); $i++) {
			$cancellable = $this->queue[$i];
			$exception = null;
			try {
				/** @phpstan-ignore method.notFound (enqueue() guarantees a cancel() method exists) */
				$cancellable->cancel();
			} catch (Throwable $exception) {
			}
			unset($this->queue[$i]);
			if($exception) {
				throw $exception;
			}
		}
		$this->queue = [];
	}

}
