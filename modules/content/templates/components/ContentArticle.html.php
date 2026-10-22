<?php /** @var \App\Content\Component\ContentArticle $contentArticle */ ?>
<article id="content-page"><h2 id="content-title"><?= $contentArticle->title ?></h2>
<?php foreach ($contentArticle->paragraphs as $paragraph): ?>
<p><?= $paragraph ?></p>
<?php endforeach ?>
</article>
