<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseArticle;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $staff = $request->user()->hasPermission('knowledge.manage');
        $articles = KnowledgeBaseArticle::with('author')->when(! $staff, fn ($q) => $q->where('is_published', true))->when($request->search, fn ($q, $s) => $q->where(fn ($b) => $b->where('title', 'like', "%$s%")->orWhere('body', 'like', "%$s%")->orWhere('category', 'like', "%$s%")))->orderByDesc('published_at')->paginate(15)->withQueryString();

        return view('knowledge.index', compact('articles', 'staff'));
    }

    public function show(Request $request, KnowledgeBaseArticle $article)
    {
        abort_unless($article->is_published || $request->user()->hasPermission('knowledge.manage'), 404);
        $article->increment('view_count');

        return view('knowledge.show', compact('article'));
    }

    public function create()
    {
        return view('knowledge.form', ['article' => new KnowledgeBaseArticle]);
    }

    public function store(Request $request, Audit $audit)
    {
        $data = $this->validated($request);
        $article = KnowledgeBaseArticle::create($this->prepare($data, $request));
        $audit->record('knowledge_article.created', $article);

        return redirect()->route('knowledge.show', $article)->with('success', 'Article created as a draft.');
    }

    public function update(Request $request, KnowledgeBaseArticle $article, Audit $audit)
    {
        $data = $this->validated($request, $article);
        $article->update($this->prepare($data, $request, $article));
        $audit->record('knowledge_article.updated', $article);

        return back()->with('success', 'Article updated.');
    }

    public function publish(KnowledgeBaseArticle $article, Audit $audit)
    {
        $article->update(['is_published' => ! $article->is_published, 'published_at' => $article->is_published ? null : now()]);
        $audit->record($article->is_published ? 'knowledge_article.published' : 'knowledge_article.unpublished', $article);

        return back()->with('success', $article->is_published ? 'Article published.' : 'Article unpublished.');
    }

    private function validated(Request $request, ?KnowledgeBaseArticle $article = null): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:180'], 'category' => ['required', 'string', 'max:100'], 'body' => ['required', 'string', 'max:30000']]);
    }

    private function prepare(array $data, Request $request, ?KnowledgeBaseArticle $article = null): array
    {
        $base = Str::slug($data['title']);
        $slug = $base;
        $i = 2;
        while (KnowledgeBaseArticle::where('slug', $slug)->when($article, fn ($q) => $q->where('id', '<>', $article->id))->exists()) {
            $slug = $base.'-'.$i++;
        }

return $data + ['author_id' => $article?->author_id ?? $request->user()->id, 'slug' => $slug];
    }
}
