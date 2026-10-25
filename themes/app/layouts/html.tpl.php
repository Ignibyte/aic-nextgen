<?php /** @var \AIC\Component\Domain\ThemeLayout $themeLayout */ ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/assets/site/favicon.svg" type="image/svg+xml">
<title><?= $themeLayout->name ?></title><meta name="tessera-layout" content="<?= $themeLayout->identity ?>">
<meta name="tessera-csrf-token" content="<?= $themeLayout->token ?>"><meta name="tessera-csrf-header" content="<?= \AIC\Form\Contracts\FormToken::HEADER ?>">
<?= $themeLayout->trackedAssets ?></head>
<body><h1><?= $themeLayout->name ?></h1>
<?= $themeLayout->page ?>
</body></html>
