<?php /** @var \App\Site\Component\AppLayout $appLayout */ ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/assets/site/favicon.svg" type="image/svg+xml">
<title><?= $appLayout->name ?></title><meta name="tessera-layout" content="<?= \App\Site\Component\AppLayout::ID ?>">
<meta name="tessera-csrf-token" content="<?= $appLayout->csrfToken ?>"><meta name="tessera-csrf-header" content="<?= \AIC\Form\Contracts\FormToken::HEADER ?>">
<?= $appLayout->trackedAssets ?></head>
<body><h1><?= $appLayout->name ?></h1>
<?= $appLayout->content ?>

<?= $appLayout->nav ?>

</body></html>
