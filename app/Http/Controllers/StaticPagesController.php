<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaticPagesController extends Controller
{
    /**
     * Display list of static pages
     */
    public function index()
    {
        $pages = DB::table('static_pages')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('static-pages.index', compact('pages'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('static-pages.create');
    }

    /**
     * Store new static page
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:static_pages,slug',
            'content_en' => 'required|string',
            'content_ar' => 'required|string',
            'meta_description_en' => 'nullable|string',
            'meta_description_ar' => 'nullable|string',
            'is_published' => 'required|boolean',
        ]);

        DB::table('static_pages')->insert([
            'title_en' => $validated['title_en'],
            'title_ar' => $validated['title_ar'],
            'slug' => Str::slug($validated['slug']),
            'content_en' => $validated['content_en'],
            'content_ar' => $validated['content_ar'],
            'meta_description_en' => $validated['meta_description_en'] ?? null,
            'meta_description_ar' => $validated['meta_description_ar'] ?? null,
            'is_published' => $validated['is_published'],
            'last_updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('static-pages.index')
            ->with('success', 'Page created successfully.');
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $page = DB::table('static_pages')->find($id);

        if (!$page) {
            return redirect()->route('static-pages.index')
                ->with('error', 'Page not found.');
        }

        return view('static-pages.edit', compact('page'));
    }

    /**
     * Update static page
     */
    public function update(Request $request, $id)
    {
        $page = DB::table('static_pages')->find($id);

        if (!$page) {
            return redirect()->route('static-pages.index')
                ->with('error', 'Page not found.');
        }

        // Check if trying to modify system page slug
        $systemPages = ['terms-and-conditions', 'privacy-policy', 'about-us', 'faq'];
        if (in_array($page->slug, $systemPages) && $request->slug !== $page->slug) {
            return redirect()->back()
                ->with('error', 'Cannot modify system page slug.')
                ->withInput();
        }

        $validated = $request->validate([
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:static_pages,slug,' . $id,
            'content_en' => 'required|string',
            'content_ar' => 'required|string',
            'meta_description_en' => 'nullable|string',
            'meta_description_ar' => 'nullable|string',
            'is_published' => 'required|boolean',
        ]);

        DB::table('static_pages')->where('id', $id)->update([
            'title_en' => $validated['title_en'],
            'title_ar' => $validated['title_ar'],
            'slug' => Str::slug($validated['slug']),
            'content_en' => $validated['content_en'],
            'content_ar' => $validated['content_ar'],
            'meta_description_en' => $validated['meta_description_en'] ?? null,
            'meta_description_ar' => $validated['meta_description_ar'] ?? null,
            'is_published' => $validated['is_published'],
            'last_updated_by' => auth()->id(),
            'updated_at' => now(),
        ]);

        return redirect()->route('static-pages.index')
            ->with('success', 'Page updated successfully.');
    }

    /**
     * Toggle page status
     */
    public function toggleStatus($id)
    {
        $page = DB::table('static_pages')->find($id);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }

        $newStatus = !$page->is_published;

        DB::table('static_pages')->where('id', $id)->update([
            'is_published' => $newStatus,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'is_published' => $newStatus,
        ]);
    }

    /**
     * Delete static page
     */
    public function destroy($id)
    {
        $page = DB::table('static_pages')->find($id);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }

        // Prevent deletion of system pages
        $systemPages = ['terms-and-conditions', 'privacy-policy', 'about-us', 'faq'];
        if (in_array($page->slug, $systemPages)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system page',
            ], 403);
        }

        DB::table('static_pages')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Page deleted successfully',
        ]);
    }
}
