<article id="content-page"><h2 id="content-title">{{ $contentArticle->contentPage->title }}</h2>
@foreach ($contentArticle->contentPage->paragraphs() as string $paragraph)
<p>{{ $paragraph }}</p>
@endforeach
</article>
