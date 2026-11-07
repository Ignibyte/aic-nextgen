# ignibyte/aic-gates

[![Latest version](https://img.shields.io/packagist/v/ignibyte/aic-gates)](https://packagist.org/packages/ignibyte/aic-gates)
[![PHP](https://img.shields.io/packagist/dependency-v/ignibyte/aic-gates/php)](https://packagist.org/packages/ignibyte/aic-gates)
[![License](https://img.shields.io/packagist/l/ignibyte/aic-gates)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/ignibyte/aic-gates)](https://packagist.org/packages/ignibyte/aic-gates/stats)

AIC's gate suite, for AIC and for an app built on it: every gate's code, the runner
(`vendor/bin/aic-gate`), the build (`vendor/bin/aic-build`) and each tool's base config. It is a
development dependency.

```sh
composer require --dev ignibyte/aic-gates
vendor/bin/aic-gate init
vendor/bin/aic-gate --list
```

This repository is a read-only split of `gates/` in
[Ignibyte/aic-nextgen](https://github.com/Ignibyte/aic-nextgen), made on each release tag.
Issues and pull requests go there. MIT licensed.
