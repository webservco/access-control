<!--
This README is the template for every webservco component. Fill it in when you create a component from the skeleton.

The clone workflow already swaps `component-skeleton` for the real name. Replace the _italic_ placeholders by hand, drop any (optional) section that doesn't apply, then delete this comment.

Everything lives in this file, no docs folder. Around 100 lines is plenty, and when the reader needs more, link to the source file.

The reader is wiring the component into an app by hand, the way webservco/sandbox does it. Use real class names and put the interface in a comment above each object.

Leave the version out of the install command. Composer picks the current major, while a hardcoded constraint goes stale (http-client still says "^0").
-->

# webservco/component-skeleton

_What it does, in one sentence._

- Implements: _[PSR-N: Title](https://www.php-fig.org/psr/psr-N/)_ (optional)
- Provides: _`psr/xxx-implementation`_ (optional)
- Requires: _PHP extensions, external services, or setup (database table, config file)_ (optional)
- Related: _`webservco/xxx` (how it relates)_ (optional)

---

## Installation

```shell
composer require webservco/component-skeleton
```

---

## Usage

_Smallest working example for the common case. Usually that means getting an object from a factory and calling one method on it._

```php
use WebServCo\Component\Factory\ExampleFactory;

// \WebServCo\Component\Contract\ExampleInterface
$factory = new ExampleFactory();
$example = $factory->createExample();
$result = $example->doSomething($input);
```

_Another common case? Give it its own `###` heading. Two extra examples at most._

---

## Contracts

_Interfaces an app would type-hint against, and the classes that implement them. Leave out internal ones._

| Interface | Implementation | Purpose |
| --- | --- | --- |
| `Contract\ExampleInterface` | `Service\Example` | _What it does._ |
| `Contract\ExampleFactoryInterface` | `Factory\ExampleFactory` | _Creates `ExampleInterface`._ |

Namespaces are relative to `WebServCo\Component\`.

---

## Configuration (optional)

_Keys read from `config/.env.ini`, each with an example value._

```ini
[Component]

EXAMPLE_KEY = 'value'
```

---

## Notes (optional)

_Anything that surprised you while using it, such as limitations, gotchas or workarounds._

---

## Development

```shell
# Start project.
ddev start
# Install dependencies.
ddev c u
# Run all validations.
ddev c c
# Run unit tests.
ddev c test:dox
```

---

## License

[MIT](LICENSE.md)
