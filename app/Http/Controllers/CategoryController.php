<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index()
    {
        $userStore = Auth::user()->store;

        $cacheKey = "category_{$userStore->id}";

        $category = Cache::remember($cacheKey, 180, function () use ($userStore) {
            return Category::query()
                ->where('store_id', $userStore->id)
                ->get();
        });

        return view('category', compact('category'));
    }

    public function store(Request $request)
    {
        $userStore = Auth::user()->store;

        $data = $request->validate([
            'name' => 'required|string',
            'desc' => 'required|string',
        ]);

        $data['store_id'] = $userStore->id;

        $category = Category::create([
            'name' => $data['name'],
            'desc' => $data['desc'],
            'store_id' => $userStore->id,
        ]);

        $this->logActivity(
            'Create Category',
            "Adding new categories: {$category->name}",
            $userStore->id
        );

        $this->clearCache($userStore->id);

        return redirect(route('category'))->with('success', 'Category successfully created!');
    }

    public function update(Request $request, $id)
    {
        $userStore = Auth::user()->store;

        $data = $request->validate([
            'name' => 'required|string',
            'desc' => 'required|string',
        ]);

        $category = Category::where('id', $id)
            ->where('store_id', $userStore->id)
            ->firstOrFail();

        $old = [
            'name' => $category->name,
            'desc' => $category->desc,
        ];

        $category->update([
            'name' => $data['name'],
            'desc' => $data['desc'],
        ]);

        // Detect what changed
        $changes = [];
        foreach ($data as $field => $value) {
            if ($old[$field] != $value) {
                $label = ucfirst(str_replace('_', ' ', $field));
                $changes[] = "{$label} changed from '{$old[$field]}' to '{$value}'";
            }
        }

        if ($changes) {
            $desc = "Update Category '{$category->name}': " . implode(', ', $changes);
            $this->logActivity('Update Category', $desc, $userStore->id);
        }

        $this->clearCache($userStore->id);

        return redirect(route('category'))->with('success', 'Category successfully updated!');
    }

    public function destroy($id)
    {
        $userStore = Auth::user()->store;

        $category = Category::where('id', $id)
            ->where('store_id', $userStore->id)
            ->first();

        if (! $category) {
            return redirect(route('category'))->withErrors(['msg' => 'Category not found.']);
        }

        $name = $category->name;

        $category->delete();

        $this->logActivity(
            'Delete Category',
            "Deleting category: {$name}",
            $userStore->id
        );

        $this->clearCache($userStore->id);

        return redirect(route('category'))->with('success', 'Category successfully deleted!');
    }

    private function clearCache(int $storeId): void
    {
        Cache::forget("category_{$storeId}");
        Cache::forget("categories_with_menus_{$storeId}");
    }

    private function logActivity($type, $description, $storeId)
    {
        ActivityLog::create([
            'user_id'       => Auth::id(),
            'staff_id'      => Auth::id(),
            'store_id'      => $storeId,
            'activity_type' => $type,
            'description'   => $description,
            'created_at'    => now(),
        ]);

        Cache::forget("activities_{$storeId}");
    }
}
