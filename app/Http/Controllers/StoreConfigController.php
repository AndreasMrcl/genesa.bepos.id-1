<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Station;
use App\Models\StoreConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class StoreConfigController extends Controller
{
    public function index()
    {
        if (! Auth::check()) {
            return redirect('/');
        }

        $userStore = Auth::user()->store;

        if (! $userStore) {
            return redirect()->route('addstore');
        }

        $cacheKey = "store_config_{$userStore->id}";

        $config = Cache::remember($cacheKey, 180, function () use ($userStore) {
            return $userStore->storeConfig;
        });

        if (! $config) {
            $config = new StoreConfig;
            $config->currency = 'IDR';
            $config->tax_percent = 0;
            $config->service_percent = 0;
            $config->tax_active = false;
            $config->service_active = false;
            $config->checker_active = false;
            $config->min_stock_alert = 5;
            $config->auto_archive_days = 30;
            $config->receipt_header = null;
            $config->receipt_footer = null;
        }

        $stations = Station::withCount('menus')->orderBy('name')->get();

        return view('storeConfig', compact('config', 'stations'));
    }

    public function update(Request $request)
    {
        $userStore = Auth::user()->store;

        $request->validate([
            'currency' => 'required|string|max:10',
            'tax_percent' => 'required|numeric|min:0|max:100',
            'service_percent' => 'required|numeric|min:0|max:100',
            'min_stock_alert' => 'required|integer|min:0',
            'auto_archive_days' => 'required|integer|min:1',
            'receipt_header' => 'nullable|string|max:255',
            'receipt_footer' => 'nullable|string|max:255',
        ]);

        $userStore->storeConfig()->updateOrCreate(
            ['store_id' => $userStore->id],
            [
                'currency' => $request->currency,
                'tax_percent' => $request->tax_percent,
                'service_percent' => $request->service_percent,
                'tax_active' => $request->has('tax_active'),
                'service_active' => $request->has('service_active'),
                'checker_active' => $request->has('checker_active'),
                'min_stock_alert' => $request->min_stock_alert,
                'auto_archive_days' => $request->auto_archive_days,
                'receipt_header' => $request->receipt_header,
                'receipt_footer' => $request->receipt_footer,
            ]
        );

        $this->logActivity(
            'Update Config',
            'Updating store configuration',
            $userStore->id
        );

        $this->clearCache($userStore->id);

        return redirect()->back()->with('success', 'Store configuration updated successfully!');
    }

    private function clearCache($storeId)
    {
        Cache::forget("store_config_{$storeId}");
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