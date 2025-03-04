<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $articles = $request->user()->articles()
            ->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('name', 'like', '%' . $request->search . '%');
            })
            ->when($request->filled('status') && $request->status !== 'all', function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->orderBy('name')
            ->paginate($request->per_page ?? 10);

        return response()->json($articles);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $article = $request->user()->articles()->create($request->all());

        return response()->json([
            'message' => 'Article created successfully',
            'article' => $article
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $article = $request->user()->articles()->findOrFail($id);

        return response()->json($article);
    }

    public function update(Request $request, $id)
    {
        $article = $request->user()->articles()->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $article->update($request->all());

        return response()->json([
            'message' => 'Article updated successfully',
            'article' => $article
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $article = $request->user()->articles()->findOrFail($id);

        // Check if article has related sale items
        if ($article->saleItems()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete article with related sales'
            ], 422);
        }

        $article->delete();

        return response()->json([
            'message' => 'Article deleted successfully'
        ]);
    }
}
