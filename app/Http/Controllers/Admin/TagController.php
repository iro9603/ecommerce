<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\AlertService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TagController extends Controller implements HasMiddleware
{
    public static function Middleware(): array
    {
        return [
            new Middleware('permission:Tags Management'),

        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $tags = Tag::paginate(20);

        return view('admin.tag.index', compact('tags'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.tag.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
            'status' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $tag = new Tag;
            $tag->name = $validated['name'];
            $tag->slug = Str::slug($validated['name']);
            $tag->is_active = $request->boolean('status');
            $tag->save();
        }, 3);

        AlertService::created();

        return redirect()->route('admin.tags.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tag $tag)
    {
        return view('admin.tag.edit', compact('tag'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tag $tag)
    {

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name,'.$tag->id],
            'status' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($tag, $request, $validated): void {
            $current = Tag::query()
                ->whereKey($tag->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $current->name = $validated['name'];
            $current->slug = Str::slug($validated['name']);
            $current->is_active = $request->boolean('status');
            $current->save();
        }, 3);

        AlertService::updated();

        return redirect()->route('admin.tags.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag)
    {
        DB::transaction(function () use ($tag): void {
            Tag::query()->whereKey($tag->getKey())->lockForUpdate()->firstOrFail()->delete();
        }, 3);
        AlertService::deleted();

        return response()->json(['status' => 'success', 'message' => 'Tag deleted successfully.']);
    }
}
