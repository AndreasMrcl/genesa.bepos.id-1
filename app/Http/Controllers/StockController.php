<?php

namespace App\Http\Controllers;

use App\Models\Invent;
use App\Models\StockMovement;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index()
    {
        $invents = Invent::orderBy('name')->get();

        return view('stok', compact('invents'));
    }

    public function receive(Request $request)
    {
        $data = $request->validate([
            'invent_id' => 'required|exists:invents,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        $invent = Invent::findOrFail($data['invent_id']);

        DB::transaction(function () use ($invent, $data) {
            $stockBefore = (int) $invent->stock;
            $invent->increment('stock', $data['quantity']);

            StockMovement::create([
                'store_id' => $invent->store_id,
                'invent_id' => $invent->id,
                'user_id' => Auth::id(),
                'quantity' => $data['quantity'],
                'stock_before' => $stockBefore,
                'type' => 'receive',
                'notes' => $data['notes'] ?? "Penerimaan {$invent->name}",
            ]);
        });

        $this->logActivity(
            'Stock Receive',
            "Receiving stock {$invent->name}: +{$data['quantity']} {$invent->unit}",
            $invent->store_id
        );

        $this->clearCache($invent->store_id);

        return redirect(route('stock'))->with('success', "Receiving {$data['quantity']} {$invent->unit} {$invent->name} successful!");
    }

    public function opnameForm()
    {
        $invents = Invent::orderBy('name')->get();

        return view('opname', compact('invents'));
    }

    public function opnameHistory(Request $request)
    {
        $storeId = Auth::user()->store->id;

        $movements = StockMovement::where('store_id', $storeId)
            ->where('type', 'manual_adjust')
            ->with(['invent', 'user'])
            ->orderByDesc('created_at')
            ->get();

        // Group by session: same user, same notes, created within same second
        $sessions = $movements
            ->groupBy(fn ($m) => $m->created_at->format('Y-m-d H:i:s').'|'.($m->user_id ?? '0').'|'.($m->notes ?? ''))
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'created_at' => $first->created_at,
                    'user_name' => $first->user->name ?? '-',
                    'reason' => $first->notes ?? '-',
                    'items' => $rows->values(),
                    'total_items' => $rows->count(),
                    'items_up' => $rows->where('quantity', '>', 0)->count(),
                    'items_down' => $rows->where('quantity', '<', 0)->count(),
                ];
            })
            ->values();

        // Build "previous opname" map per invent for comparison.
        $previousOpnameByInvent = [];
        foreach ($movements->sortBy('created_at') as $m) {
            $stockAfter = (int) ($m->stock_before ?? 0) + (int) $m->quantity;
            $previousOpnameByInvent[$m->invent_id][] = [
                'created_at' => $m->created_at,
                'stock_after' => $stockAfter,
            ];
        }

        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $sessions = new LengthAwarePaginator(
            $sessions->forPage($page, $perPage)->values(),
            $sessions->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return view('opnameHistory', compact('sessions', 'previousOpnameByInvent'));
    }

    public function opname(Request $request)
    {
        $storeId = Auth::user()->store->id;

        $data = $request->validate([
            'reason' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.invent_id' => 'required|exists:invents,id',
            'items.*.actual_stock' => 'nullable|integer|min:0',
            'items.*.item_notes' => 'nullable|string|max:255',
        ]);

        $invents = Invent::whereIn('id', collect($data['items'])->pluck('invent_id'))
            ->get()
            ->keyBy('id');

        $changes = [];
        foreach ($data['items'] as $row) {
            if (! isset($row['actual_stock']) || $row['actual_stock'] === null || $row['actual_stock'] === '') {
                continue;
            }

            $invent = $invents->get($row['invent_id']);
            if (! $invent) {
                continue;
            }

            $delta = (int) $row['actual_stock'] - $invent->stock;
            if ($delta === 0) {
                continue;
            }

            $itemNotes = isset($row['item_notes']) ? trim($row['item_notes']) : '';

            $changes[] = [
                'invent' => $invent,
                'actual_stock' => (int) $row['actual_stock'],
                'delta' => $delta,
                'item_notes' => $itemNotes !== '' ? $itemNotes : null,
            ];
        }

        if (empty($changes)) {
            return redirect(route('stock'))->with('info', 'No stock changes detected, nothing to adjust.');
        }

        DB::transaction(function () use ($changes, $data, $storeId) {
            foreach ($changes as $change) {
                $stockBefore = (int) $change['invent']->stock;
                $change['invent']->update(['stock' => $change['actual_stock']]);

                StockMovement::create([
                    'store_id' => $storeId,
                    'invent_id' => $change['invent']->id,
                    'user_id' => Auth::id(),
                    'quantity' => $change['delta'],
                    'stock_before' => $stockBefore,
                    'type' => 'manual_adjust',
                    'notes' => $data['reason'],
                    'item_notes' => $change['item_notes'],
                ]);
            }
        });

        $totalUp = collect($changes)->where('delta', '>', 0)->sum('delta');
        $totalDown = collect($changes)->where('delta', '<', 0)->sum('delta');

        $this->logActivity(
            'Stock Opname',
            'Stock opname: '.count($changes)." item(s) adjusted (+{$totalUp} / {$totalDown}). Reason: {$data['reason']}",
            $storeId
        );

        $this->clearCache($storeId);

        return redirect(route('stock'))->with('success', 'Stock opname successful: '.count($changes).' item(s) adjusted.');
    }

    private function clearCache($storeId)
    {
        Cache::forget("stock_{$storeId}");
        Cache::forget("invents_{$storeId}");
    }

    private function logActivity($type, $description, $storeId)
    {
        ActivityLogger::log($type, $description, $storeId);
    }
}