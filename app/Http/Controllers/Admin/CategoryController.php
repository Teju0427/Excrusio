<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.categories', [
            'groups' => Category::withCount('listings')->orderBy('sort_order')->orderBy('name')->get()->groupBy(fn ($c) => $c->listing_type->value),
            'types' => ListingType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);
        $data['sort_order'] ??= 0;
        if (Category::where('listing_type', $data['listing_type'])->where('slug', $data['slug'])->exists()) {
            return back()->withErrors(['name' => 'That category already exists.'])->withInput();
        }
        $category = Category::create($data + ['is_active' => true]);
        $this->audit->log('category.created', $category, [], $data);

        return back()->with('status', "Category “{$category->name}” added.");
    }

    /** Rename, re-icon, reorder or hide. Categories in use are hidden rather than deleted. */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:60', 'regex:/^bi-[a-z0-9-]+$/'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ]) + ['is_active' => $request->boolean('is_active')];
        $data['sort_order'] ??= 0;
        $old = $category->only(array_keys($data));
        $category->update($data);
        $this->audit->log('category.updated', $category, $old, $data);

        return back()->with('status', "Category “{$category->name}” saved.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->listings()->exists()) {
            return back()->withErrors(['booking' => "“{$category->name}” is used by listings — switch it off instead of deleting it."]);
        }
        $this->audit->log('category.deleted', $category, $category->only(['name', 'listing_type']), []);
        $category->delete();

        return back()->with('status', 'Category deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'listing_type' => ['required', Rule::enum(ListingType::class)],
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:60', 'regex:/^bi-[a-z0-9-]+$/'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ]);
    }
}
