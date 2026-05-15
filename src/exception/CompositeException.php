<?php

declare(strict_types=1);

namespace promise\exception;

use Exception;
use Throwable;

class CompositeException extends Exception {
	/** @var list<Throwable> */
	private array $throwables;

	/**
	 * @param list<Throwable> $throwables
	 */
	public function __construct(array $throwables, string $message = '', int $code = 0, ?Throwable $previous = null) {
		parent::__construct($message, $code, $previous);

		$this->throwables = $throwables;
	}

	/**
	 * @return list<Throwable>
	 */
	public function getThrowables(): array {
		return $this->throwables;
	}

}
