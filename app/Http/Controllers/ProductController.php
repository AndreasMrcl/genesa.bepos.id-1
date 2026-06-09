<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CartMenu;
use App\Models\Category;
use App\Models\Discount;
use App\Models\InventMenu;
use App\Models\Menu;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{

    public function index()
    {
        $userStore = Auth::user()->store;

        $cacheKey = "menu_{$userStore->id}";

        $category = Category::query()->where('store_id', $userStore->id)->get();

        $stations = Station::where('is_active', true)->orderBy('name')->get();

        $menuAll = Cache::remember($cacheKey, 180, function () use ($userStore) {
            return Menu::query()
                ->where('store_id', $userStore->id)
                ->with(['category', 'station'])
                ->get();
        });

        return view('product', compact('category', 'menuAll', 'stations'));
    }

    public function store(Request $request)
    {
        $userStore = Auth::user()->store;

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'img' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'required|string|max:500',
            'category_id' => 'required|exists:categories,id,store_id,' . $userStore->id,
            'station_id' => 'nullable|exists:stations,id,store_id,' . $userStore->id,
        ] + $this->varietyRules());

        $hasVariety = $request->boolean('has_variety');
        $varieties = $this->resolveVarieties($request);

        $uploadedImage = $request->file('img');
        $imageName = $uploadedImage->getClientOriginalName();
        $uploadedImage->storeAs('img', $imageName, 'public');

        $menu = Menu::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'img' => 'img/' . $imageName,
            'description' => $data['description'],
            'category_id' => $data['category_id'],
            'station_id' => $data['station_id'] ?? null,
            'store_id' => $userStore->id,
            'has_variety' => $hasVariety,
            'varieties' => $varieties,
        ]);

        $this->logActivity(
            'Create Product',
            "Adding new product: {$menu->name} (Rp " . number_format($menu->price, 0, ',', '.') . ')'
                . ($hasVariety ? ' with varieties: ' . implode(', ', $varieties) : ''),
            $userStore->id
        );

        $this->clearCache($userStore->id, $menu->id);

        return redirect(route('product'))->with('success', 'Product successfully created!');
    }

    public function show($id)
    {
        $menu = Cache::remember("menu_detail_{$id}", now()->addMinutes(60), function () use ($id) {
            return Menu::find($id);
        });
        $discount = Cache::remember('discounts', now()->addMinutes(60), function () {
            return Discount::all();
        });

        return view('showproduct', compact('menu', 'discount'));
    }

    public function update(Request $request, $id)
    {
        $userStore = Auth::user()->store;

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'description' => 'required|string|max:500',
            'category_id' => 'required|exists:categories,id,store_id,' . $userStore->id,
            'station_id' => 'nullable|exists:stations,id,store_id,' . $userStore->id,
            'img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ] + $this->varietyRules());

        $menu = Menu::where('id', $id)
            ->where('store_id', $userStore->id)
            ->firstOrFail();

        $hasVariety = $request->boolean('has_variety');
        $newVarieties = $this->resolveVarieties($request);

        $oldVarieties = $menu->varieties ?? [];
        $oldHasVariety = (bool) $menu->has_variety;

        $payload = [
            'name' => $data['name'],
            'price' => $data['price'],
            'description' => $data['description'],
            'category_id' => $data['category_id'],
            'station_id' => $data['station_id'] ?? null,
            'has_variety' => $hasVariety,
            'varieties' => $newVarieties,
        ];

        if ($request->hasFile('img')) {
            if ($menu->img && Storage::disk('public')->exists($menu->img)) {
                Storage::disk('public')->delete($menu->img);
            }
            $uploadedImage = $request->file('img');
            $imageName = $uploadedImage->getClientOriginalName();
            $uploadedImage->storeAs('img', $imageName, 'public');
            $payload['img'] = 'img/' . $imageName;
        }

        // Variety cleanup: silent delete pivot untuk variety yang dihapus
        $removedVarieties = [];
        if ($oldHasVariety && ! $hasVariety) {
            $removedVarieties = $oldVarieties;
        } elseif ($oldHasVariety && $hasVariety) {
            $removedVarieties = array_values(array_diff($oldVarieties, $newVarieties));
        }

        $deletedRecipeRows = 0;
        if (! empty($removedVarieties)) {
            $deletedRecipeRows = InventMenu::where('menu_id', $menu->id)
                ->whereIn('variety', $removedVarieties)
                ->delete();
        }

        $diff = $this->diffPayload($menu, $payload);
        $menu->update($payload);

        if ($diff) {
            $this->logActivity('Update Product', "Update Product '{$menu->name}': " . implode(', ', $diff), $userStore->id);
        }

        $this->clearCache($userStore->id, $menu->id);

        $message = 'Product successfully updated!';
        if ($deletedRecipeRows > 0) {
            $list = implode(', ', array_map(fn($v) => Str::title(str_replace('_', ' ', $v)), $removedVarieties));
            $message .= " Resep untuk variety yang dihapus ({$list}) ikut terhapus.";
        }

        return redirect(route('product'))->with('success', $message);
    }

    private function varietyRules(): array
    {
        return [
            'has_variety' => 'sometimes|boolean',
            'varieties' => 'nullable|array|min:2',
            'varieties.*' => 'required|string|max:50|distinct',
        ];
    }

    private function resolveVarieties(Request $request): ?array
    {
        if (! $request->boolean('has_variety')) {
            return null;
        }

        $varieties = array_values(array_unique(array_map(
            fn($v) => Str::snake(trim($v)),
            $request->input('varieties', [])
        )));

        if (count($varieties) < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'varieties' => 'Minimal 2 variety jika opsi variety diaktifkan.',
            ]);
        }

        return $varieties;
    }

    private function diffPayload(Menu $menu, array $payload): array
    {
        $diff = [];
        foreach ($payload as $field => $value) {
            $old = $menu->getAttribute($field);
            $oldNorm = is_array($old) ? json_encode($old) : (string) $old;
            $newNorm = is_array($value) ? json_encode($value) : (string) $value;
            if ($oldNorm !== $newNorm) {
                $label = Str::headline($field);
                $diff[] = "{$label}: '{$oldNorm}' → '{$newNorm}'";
            }
        }
        return $diff;
    }

    public function destroy($id)
    {
        $userStore = Auth::user()->store;

        $menu = Menu::where('id', $id)
            ->where('store_id', $userStore->id)
            ->first();

        if (! $menu) {
            return redirect(route('product'))->withErrors(['msg' => 'Product not found.']);
        }

        // hapus relasi cart_menu
        CartMenu::where('menu_id', $id)->delete();

        // hapus file img dari storage
        if ($menu->img && Storage::disk('public')->exists($menu->img)) {
            Storage::disk('public')->delete($menu->img);
        }

        $name = $menu->name;

        $menu->delete();

        $this->logActivity(
            'Delete Product',
            "Deleting product: {$name}",
            $userStore->id
        );

        $this->clearCache($userStore->id, $menu->id);

        return redirect()->route('product')->with('success', 'Product successfully deleted!');
    }

    private function clearCache(int $storeId, ?int $menuId = null): void
    {
        Cache::forget("ingridient_{$storeId}");

        Cache::forget("menus_{$storeId}");
        Cache::forget("categories_with_menus_{$storeId}");

        if ($menuId !== null) {
            Cache::forget("menu_detail_{$menuId}");
        }
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
