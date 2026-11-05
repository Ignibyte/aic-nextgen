# ignibyte/aic-gates

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
