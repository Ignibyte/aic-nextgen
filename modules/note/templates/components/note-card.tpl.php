<?php /** @var \App\Note\Component\NoteCard $noteCard */ ?>
<article id="note"><h2 id="note-title"><?= $noteCard->note->title ?></h2>
<p id="note-body"><?= $noteCard->note->body ?></p>
<p>Written <time><?= $noteCard->note->createdAt ?></time></p></article>
