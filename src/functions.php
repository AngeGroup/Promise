<?php

declare(strict_types=1);

namespace promise;

use Closure;
use function gettype;
use function is_array;
use function is_object;
use LogicException;

use function method_exists;
use promise\exception\CompositeException;
use promise\exception\LengthException;

use promise\internal\FulfilledPromise;

use promise\internal\RejectedPromise;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;

function resolve(mixed $promiseOrValue): PromiseInterface {
	if($promiseOrValue instanceof PromiseInterface) {
		return $promiseOrValue;
	}
	if(is_object($promiseOrValue) && method_exists($promiseOrValue, 'then')) {
		$canceller = null;
		if(method_exists($promiseOrValue, 'cancel')) {
			$canceller = [$promiseOrValue, 'cancel'];
		}
		return new Promise(function($resolve, $reject) use ($promiseOrValue): void {
			$promiseOrValue->then($resolve, $reject);
		}, $canceller);
	}
	return new FulfilledPromise($promiseOrValue);
}

function reject(Throwable $reason): PromiseInterface {
	return new RejectedPromise($reason);
}

/**
 * @param iterable<mixed> $promisesOrValues
 */
function all(iterable $promisesOrValues): PromiseInterface {
	$cancellationQueue = new internal\CancellationQueue();

	return new Promise(function($resolve, $reject) use ($promisesOrValues, $cancellationQueue): void {
		$toResolve = 0;
		$continue = true;
		$values = [];

		foreach ($promisesOrValues as $i => $promiseOrValue) {
			$cancellationQueue->enqueue($promiseOrValue);
			$values[$i] = null;
			$toResolve++;

			resolve($promiseOrValue)->then(
				function($value) use ($i, &$values, &$toResolve, &$continue, $resolve): void {
					$values[$i] = $value;

					if (0 === --$toResolve && !$continue) {
						$resolve($values);
					}
				},
				function(Throwable $reason) use (&$continue, $reject): void {
					$continue = false;
					$reject($reason);
				}
			);

			if ( !$continue) {
				break;
			}
		}

		$continue = false;
		if ($toResolve === 0) {
			$resolve($values);
		}
	}, $cancellationQueue);
}

/**
 * Initiates a competitive race that allows one winner. Returns a promise which is
 * resolved in the same way the first settled promise resolves.
 *
 * The returned promise will become **infinitely pending** if  `$promisesOrValues`
 * contains 0 items.
 *
 * @param iterable<mixed> $promisesOrValues
 */
function race(iterable $promisesOrValues): PromiseInterface {
	$cancellationQueue = new internal\CancellationQueue();

	return new Promise(function($resolve, $reject) use ($promisesOrValues, $cancellationQueue): void {
		$continue = true;

		foreach ($promisesOrValues as $promiseOrValue) {
			$cancellationQueue->enqueue($promiseOrValue);

			resolve($promiseOrValue)->then($resolve, $reject)->finally(function() use (&$continue): void {
				$continue = false;
			});

			if ( !$continue) {
				break;
			}
		}
	}, $cancellationQueue);
}

/**
 * Returns a promise that will resolve when any one of the items in
 * `$promisesOrValues` resolves. The resolution value of the returned promise
 * will be the resolution value of the triggering item.
 *
 * The returned promise will only reject if *all* items in `$promisesOrValues` are
 * rejected. The rejection value will be a `CompositeException` whose throwables
 * carry every individual rejection reason.
 *
 * The returned promise will reject with a `promise\exception\LengthException`
 * if `$promisesOrValues` contains 0 items.
 *
 * @param iterable<mixed> $promisesOrValues
 */
function any(iterable $promisesOrValues): PromiseInterface {
	$cancellationQueue = new internal\CancellationQueue();

	return new Promise(function($resolve, $reject) use ($promisesOrValues, $cancellationQueue): void {
		$toReject = 0;
		$continue = true;
		$reasons = [];

		foreach ($promisesOrValues as $i => $promiseOrValue) {
			$cancellationQueue->enqueue($promiseOrValue);
			$toReject++;

			resolve($promiseOrValue)->then(
				function($value) use ($resolve, &$continue): void {
					$continue = false;
					$resolve($value);
				},
				function(Throwable $reason) use ($i, &$reasons, &$toReject, $reject, &$continue): void {
					$reasons[$i] = $reason;

					if (0 === --$toReject && !$continue) {
						$reject(new CompositeException(
							$reasons,
							'All promises rejected.'
						));
					}
				}
			);

			if ( !$continue) {
				break;
			}
		}

		$continue = false;
		if ($toReject === 0 && !$reasons) {
			$reject(new LengthException(
				'Must contain at least 1 item but contains only 0 items.'
			));
		} elseif ($toReject === 0) {
			$reject(new CompositeException(
				$reasons,
				'All promises rejected.'
			));
		}
	}, $cancellationQueue);
}

/**
 * Returns a promise that resolves once every input has settled (fulfilled or
 * rejected). The resolution value is an array of result entries in input order:
 *
 *   - `['status' => 'fulfilled', 'value' => mixed]`
 *   - `['status' => 'rejected', 'reason' => Throwable]`
 *
 * Unlike {@see all()}, `allSettled()` never short-circuits on rejection.
 * Resolves with `[]` when given an empty iterable.
 *
 * @param iterable<mixed> $promisesOrValues
 */
function allSettled(iterable $promisesOrValues): PromiseInterface {
	$cancellationQueue = new internal\CancellationQueue();

	return new Promise(function($resolve) use ($promisesOrValues, $cancellationQueue): void {
		$toSettle = 0;
		$continue = true;
		$results = [];

		foreach ($promisesOrValues as $i => $promiseOrValue) {
			$cancellationQueue->enqueue($promiseOrValue);
			$results[$i] = null;
			$toSettle++;

			resolve($promiseOrValue)->then(
				function($value) use ($i, &$results, &$toSettle, &$continue, $resolve): void {
					$results[$i] = ['status' => 'fulfilled', 'value' => $value];
					if (0 === --$toSettle && !$continue) {
						$resolve($results);
					}
				},
				function(Throwable $reason) use ($i, &$results, &$toSettle, &$continue, $resolve): void {
					$results[$i] = ['status' => 'rejected', 'reason' => $reason];
					if (0 === --$toSettle && !$continue) {
						$resolve($results);
					}
				}
			);
		}

		$continue = false;
		if ($toSettle === 0) {
			$resolve($results);
		}
	}, $cancellationQueue);
}

/**
 * @throws ReflectionException
 * @internal
 */
function _checkTypehint(callable $callback, Throwable $reason): bool {
	if (is_array($callback)) {
		$callbackReflection = new ReflectionMethod($callback[0], $callback[1]);
	} elseif (is_object($callback) && !$callback instanceof Closure) {
		$callbackReflection = new ReflectionMethod($callback, '__invoke');
	} else {
		/** @var Closure|string $callback */
		$callbackReflection = new ReflectionFunction($callback);
	}

	$parameters = $callbackReflection->getParameters();

	if ( !isset($parameters[0])) {
		return true;
	}

	$expectedException = $parameters[0];

	// Extract the type of the argument and handle different possibilities
	$type = $expectedException->getType();

	$isTypeUnion = true;
	$types = [];

	switch (true) {
		case $type === null:
			break;
		case $type instanceof ReflectionNamedType:
			$types = [$type];
			break;
		case $type instanceof ReflectionIntersectionType:
			$isTypeUnion = false;
			// no break
		case $type instanceof ReflectionUnionType:
			$types = $type->getTypes();
			break;
		default:
			throw new LogicException('Unexpected return value of ReflectionParameter::getType');
	}

	// If there is no type restriction, it matches
	if (empty($types)) {
		return true;
	}

	foreach ($types as $type) {
		$matches = false;

		if ($type instanceof ReflectionIntersectionType) {
			foreach ($type->getTypes() as $typeToMatch) {
				assert($typeToMatch instanceof ReflectionNamedType);
				$matches = ($typeToMatch->isBuiltin() && gettype($reason) === $typeToMatch->getName())
					|| (new ReflectionClass($typeToMatch->getName()))->isInstance($reason);
				if ( !$matches) {
					break;
				}
			}
		} else {
			assert($type instanceof ReflectionNamedType);
			$matches = ($type->isBuiltin() && gettype($reason) === $type->getName())
				|| (new ReflectionClass($type->getName()))->isInstance($reason);
		}

		// If we look for a single match (union), we can return early on match
		// If we look for a full match (intersection), we can return early on mismatch
		if ($matches) {
			if ($isTypeUnion) {
				return true;
			}
		} else {
			if ( !$isTypeUnion) {
				return false;
			}
		}
	}

	// If we look for a single match (union) and did not return early, we matched no type and are false
	// If we look for a full match (intersection) and did not return early, we matched all types and are true
	return !$isTypeUnion;
}
