<?php /** @var \App\Site\Component\Greetings $greetings */ ?>
<ul id="greetings"><?php foreach ($greetings->greetings as $greeting): ?><li><?= $greeting ?></li><?php endforeach ?></ul>