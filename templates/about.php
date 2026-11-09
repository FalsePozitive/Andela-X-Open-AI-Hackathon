<?php $content = \Passport\Copy::ABOUT; ?>
<article class="info-page">
<header class="info-heading"><p class="eyebrow"><?= $e($content['eyebrow']) ?></p><h1><?= $e($content['title']) ?><br><em><?= $e($content['accent']) ?></em></h1><p class="lead"><?= $e($content['intro']) ?></p></header>
<section class="about-opening"><div><h2><?= $e($content['whyTitle']) ?></h2><p><?= $e($content['why']) ?></p></div><aside class="about-emblem"><img src="/assets/okwethu-mark.svg" width="140" height="140" alt=""><p><?= $e($t('brandMeaning')) ?></p></aside></section>
<section class="about-purpose"><p class="eyebrow"><?= $e($t('ROOTED IN PEOPLE. SHARED ON OUR TERMS.')) ?></p><h2><?= $e($content['promiseTitle']) ?></h2><p><?= $e($content['promise']) ?></p></section>
<section class="about-pillars"><?php foreach ($content['pillars'] as $i => $pillar): ?><article class="card"><span class="principle-number" aria-hidden="true"><?= $e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?></span><h2><?= $e($pillar['title']) ?></h2><p><?= $e($pillar['body']) ?></p></article><?php endforeach ?></section>
<section class="about-explainer"><div><h2><?= $e($content['passportTitle']) ?></h2><p><?= $e($content['passport']) ?></p></div><div><h2><?= $e($content['careTitle']) ?></h2><p><?= $e($content['care']) ?></p></div></section>
<section class="notice"><h2><?= $e($content['limitsTitle']) ?></h2><p><?= $e($content['limits']) ?></p></section>
<section class="info-cta"><h2><?= $e($content['ctaTitle']) ?></h2><p><?= $e($content['cta']) ?></p><div class="actions"><a class="button" href="/"><?= $e($content['explore']) ?> ↗</a><a href="/faq"><?= $e($content['learn']) ?> →</a></div></section>
</article>
