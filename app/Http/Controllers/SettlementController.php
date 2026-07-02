<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Settlement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SettlementController extends Controller
{
    public function index()
    {
        $userStore = Auth::user()->store;

        $cacheKey = "settlement_{$userStore->id}";

        $settlements = Cache::remember($cacheKey, 180, function () use ($userStore) {
            return Settlement::query()
                ->where('store_id', $userStore->id)
                ->orderBy('created_at')
                ->get();
        });

        return view('settlement', compact('settlements'));
    }

    public function poststart(Request $request)
    {
        $userStore = Auth::user()->store;

        $data = $request->validate([
            'start_amount' => 'nullable|numeric',
        ]);

        $user = auth()->user();

        $activeShift = $user->settlements()->active()->first();

        if ($activeShift) {
            return redirect(route('settlement'))->with('error', "The previous shift hasn't closed yet. Please close it before opening a new shift.");
        }

        $data['store_id'] = $userStore->id;
        $data['start_time'] = Carbon::now()->toDateTimeString();
        $data['expected'] = $data['start_amount'] ?? 0;

        $user->settlements()->create($data);

        $this->logActivity(
            'Open Shift',
            'Opening shift with initial cash: Rp ' . number_format($data['expected'] ?? 0, 0, ',', '.'),
            $userStore->id
        );

        $this->clearCache($userStore->id);

        return redirect(route('settlement'))->with('success', 'New settlement created successfully!');
    }

    public function posttotal(Request $request)
    {
        $userStore = Auth::user()->store;

        $data = $request->validate([
            'total_amount' => 'nullable|numeric',
        ]);

        $user = auth()->user();
        $activeShift = $user->settlements()->active()->first();

        if (! $activeShift) {
            return redirect(route('settlement'))->with('error', 'There is no active shift that can be closed.');
        }

        $data['end_time'] = Carbon::now()->toDateTimeString();
        $activeShift->update($data);

        $this->logActivity(
            'Close Shift',
            'Closing shift with total cash: Rp ' . number_format($data['total_amount'] ?? 0, 0, ',', '.'),
            $userStore->id
        );

        $this->clearCache($userStore->id);

        Cache::forget("settlement_detail_{$activeShift->id}");

        return redirect(route('settlement'))->with('success', 'Shift ended successfully!');
    }

    public function show($id)
    {
        $settlement = Cache::remember(
            "settlement_detail_{$id}",
            now()->addMinutes(60),
            fn() => Settlement::with([
                'user',
                'histories' => fn($q) => $q->where('status', 'settlement')->orderBy('created_at'),
            ])->findOrFail($id)
        );

        $histories = $settlement->histories;

        $paymentBreakdown = $histories
            ->groupBy(fn($h) => $h->payment_type ?: 'unknown')
            ->map(fn($group) => [
                'count' => $group->count(),
                'total' => $group->sum('total_amount'),
            ])
            ->sortKeys();

        $grandTotal = $histories->sum('total_amount');

        return view('showsettlement', compact('settlement', 'paymentBreakdown', 'grandTotal'));
    }

    public function destroy($id)
    {
        $userStore = Auth::user()->store;

        $settlement = Settlement::findOrFail($id);
        $settlement->delete();

        $this->logActivity(
            'Delete Settlement',
            "Deleting settlement #{$id}",
            $userStore->id
        );

        $this->clearCache($userStore->id);
        Cache::forget("settlement_detail_{$id}");

        return redirect(route('settlement'))->with('success', 'Settlement deleted successfully!');
    }

    private function clearCache(int $storeId): void
    {
        Cache::forget("settlement_{$storeId}");
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
