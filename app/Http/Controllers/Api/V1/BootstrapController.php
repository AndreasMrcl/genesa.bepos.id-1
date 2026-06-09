<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ChairResource;
use App\Http\Resources\DiscountResource;
use App\Http\Resources\MenuResource;
use App\Http\Resources\ShowcaseResource;
use App\Http\Resources\StoreConfigResource;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Menu;
use App\Models\Showcase;
use App\Models\Station;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $store = $user->store;

        $menus = Menu::with('station')->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $stations = Station::where('store_id', $store->id)->orderBy('name')->get();
        $showcases = Showcase::orderBy('name')->get();
        $discounts = Discount::orderBy('name')->get();
        $chairs = $store->chairs()->orderBy('name')->get();
        $storeConfig = $store->storeConfig;

        return $this->ok([
            'menus'         => MenuResource::collection($menus),
            'categories'    => CategoryResource::collection($categories),
            'stations'      => $stations->map(fn ($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'is_active' => (bool) $s->is_active,
            ])->values(),
            'showcases'     => ShowcaseResource::collection($showcases),
            'discounts'     => DiscountResource::collection($discounts),
            'chairs'        => ChairResource::collection($chairs),
            'store_config'  => $storeConfig ? new StoreConfigResource($storeConfig) : null,
        ]);
    }
}
