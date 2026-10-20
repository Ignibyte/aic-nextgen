@use AIC\Component\Domain\Badge
@use AIC\Component\Domain\Link
@use App\Note\Note
<h2>Notes @component(new Badge($noteList->size(), 'note-count'))</h2>
@if ($noteList->isEmpty())
<p id="no-notes">No notes yet.</p>
@else
<ul id="notes">
@foreach ($noteList->notes as Note $note)
<li>@component(new Link($note->title, '/notes/' . $note->id))</li>
@endforeach
</ul>
@endif
