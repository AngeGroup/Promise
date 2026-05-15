# Promise

[![CI](https://github.com/AngeGroup/Promise/actions/workflows/ci.yml/badge.svg)](https://github.com/AngeGroup/Promise/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A Promise/A+ inspired implementation in PHP, with cooperative cancellation
support. Inspired by ReactPHP's promise library.

## Installation

```bash
composer require angegroup/promise
```

Requires PHP 8.2+.

## Quick start

```php
use function promise\resolve;

resolve(42)
    ->then(fn ($v) => $v * 2)
    ->then(function ($v) {
        echo "Got: $v\n"; // 84
    });
```

## API

### `Promise`

Construct a promise from a resolver callback. The resolver receives `$resolve`
and `$reject` and runs synchronously inside the constructor:

```php
use promise\Promise;

$promise = new Promise(function ($resolve, $reject) {
    $resolve('value');
    // or $reject(new \RuntimeException('error'));
});
```

Pass an optional second `$canceller` callable that runs when `$promise->cancel()`
is called by anyone holding a handle on the promise.

### `Deferred`

A handle to externally settle a promise — useful when the resolution comes from
an event, a timer, or any caller outside the promise constructor:

```php
use promise\Deferred;

$deferred = new Deferred();

// ... later, when something happens:
$deferred->resolve('result');
// or:
$deferred->reject(new \RuntimeException('failure'));

// Hand the promise to consumers:
$deferred->promise()->then(...);
```

### `PromiseInterface`

All promises implement:

| Method | Purpose |
|---|---|
| `then(?callable $onFulfilled, ?callable $onRejected)` | Register handlers; returns a derived promise |
| `catch(callable $onRejected)` | Shorthand for `then(null, $onRejected)`. Type-hint the parameter to filter by exception class |
| `finally(callable $onFulfilledOrRejected)` | Cleanup callback that runs on either outcome and preserves the original value/reason |
| `cancel()` | Cooperative cancellation; calls the canceller registered when the promise was created |
| `wait()` | No-op without an underlying event loop. Kept for interface compatibility |
| `isResolved()` | True once the promise has settled (either way) |

### Top-level functions

All in the `promise\` namespace.

```php
use function promise\{resolve, reject, all, race, any, allSettled};
```

#### `resolve(mixed $value): PromiseInterface`

- A `PromiseInterface` is returned as-is.
- A "thenable" object (any object with a `then` method) is adapted into a Promise.
- Anything else becomes a `FulfilledPromise` carrying the value.

#### `reject(Throwable $reason): PromiseInterface`

Always returns a rejected promise carrying the throwable.

#### `all(iterable $promisesOrValues): PromiseInterface`

Resolves with an array of all values when every input fulfills (preserving keys).
Rejects on the first rejection. Resolves with `[]` for an empty iterable.

```php
all([resolve(1), resolve(2), 3])->then(fn ($vs) => print_r($vs));
// [1, 2, 3]
```

#### `race(iterable $promisesOrValues): PromiseInterface`

Settles the same way as the first input that settles (fulfilled or rejected).
**Pending forever** when given an empty iterable.

#### `any(iterable $promisesOrValues): PromiseInterface`

Resolves with the value of the first input to fulfill. Only rejects if **all**
inputs reject — the rejection is a `CompositeException` carrying every reason.
Rejects with `LengthException` when given an empty iterable.

#### `allSettled(iterable $promisesOrValues): PromiseInterface`

Resolves once every input has settled. The resolution value is an array of
result entries (preserving keys):

```php
[
    ['status' => 'fulfilled', 'value' => mixed],
    ['status' => 'rejected',  'reason' => Throwable],
]
```

Never short-circuits. Resolves with `[]` for an empty iterable.

### Typed catch

`catch()` inspects the rejection handler's first parameter type. If the rejection
reason isn't an instance of that type, the handler is skipped and the rejection
propagates to the next handler:

```php
$promise
    ->catch(function (NotFoundException $e) {
        // only catches NotFoundException
    })
    ->catch(function (\Throwable $e) {
        // catches everything else
    });
```

Union and intersection types are supported.

## Exceptions

| Class | Thrown by |
|---|---|
| `promise\exception\CompositeException` | `any()` when every input rejects. `getThrowables()` returns the per-input reasons. |
| `promise\exception\LengthException` | `any()` when given an empty iterable. |
| `promise\exception\TimeoutException` | Reserved for `wait()` implementations backed by an event loop. |

## Development

```bash
composer install
composer test            # PHPUnit
composer test-coverage   # PHPUnit with coverage summary
composer cs-check        # php-cs-fixer dry-run
composer cs-fix          # apply formatting
composer phpstan         # static analysis
```

CI runs validate, syntax, php-cs-fixer (dry-run), PHPStan level 8 and PHPUnit
as five parallel jobs on every push and pull request.

## License

MIT — see [LICENSE](LICENSE).
