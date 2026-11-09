<?php use Passport\Copy; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="<?= $e($csrf) ?>"><title><?= $e($t('brand')) ?> · Community Ledger</title><link rel="stylesheet" href="/assets/app.css"><script src="/assets/app.js" defer></script></head>
<body class="bg-cream text-ink" data-ui="<?= $e(json_encode(Copy::UI + Copy::CLIENT, JSON_THROW_ON_ERROR)) ?>"><a class="skip" href="#main"><?= $e($t('Skip to content')) ?></a>
<header class="site-header"><a class="brand" href="/"><span class="brand-mark" aria-hidden="true">S<span>↗</span></span><span><?= $e($t('brand')) ?><small><?= $e($t('Community Ledger')) ?></small></span></a><nav aria-label="<?= $e($t('Main navigation')) ?>"><a href="/"><?= $e($t('Passports')) ?></a><?php if ($signedIn): ?><a href="/dashboard"><?= $e($t('dashboard')) ?></a><a href="/dashboard/requests"><?= $e($t('requests')) ?></a><a href="/ledger"><?= $e($t('ledger')) ?></a><form action="/logout" method="post"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-button"><?= $e($t('logout')) ?></button></form><?php else: ?><a class="button small" href="/login"><?= $e($t('login')) ?></a><?php endif ?></nav></header>
<main id="main" class="shell"><?php require __DIR__ . '/' . $view . '.php'; ?></main>
<footer class="shell footer"><strong><?= $e($t('demo')) ?></strong><p><?= $e(Copy::LIMITS) ?></p><p><?= $e($t('No real payments. No viewer analytics. People decide the terms.')) ?></p></footer>
</body></html>
