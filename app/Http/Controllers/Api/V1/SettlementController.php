<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\SettlementResource;
use App\Models\Cart;
use App\Models\Settlement;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $storeId = $request->user()->store->id;

        $settlements = Settlement::where('store_id', $storeId)
            ->latest()
            ->get();

        return $this->ok(['settlements' => SettlementResource::collection($settlements)]);
    }

    public function active(Request $request)
    {
        $active = $request->user()->settlements()->active()->first();

        return $this->ok([
            'settlement' => $active ? new SettlementResource($active) : null,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $storeId = $request->user()->store->id;

        $settlement = Settlement::with('histories')
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->first();

        if (! $settlement) {
            return $this->error('settlement', 'Settlement tidak ditemukan.', 404);
        }

        return $this->ok(['settlement' => new SettlementResource($settlement)]);
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'start_amount' => 'nullable|numeric',
        ]);

        $user = $request->user();

        if ($user->settlements()->active()->first()) {
            return $this->error('shift', 'Shift sebelumnya belum ditutup.', 409);
        }

        $data['store_id'] = $user->store->id;
        $data['start_time'] = Carbon::now()->toDateTimeString();
        $data['expected'] = $data['start_amount'] ?? 0;

        $settlement = $user->settlements()->create($data);

        ActivityLogger::log(
            'Open Shift',
            'Opening shift with initial cash: Rp '.number_format($data['expected'] ?? 0, 0, ',', '.'),
            $settlement->store_id
        );

        return $this->created(['settlement' => new SettlementResource($settlement)]);
    }

    public function close(Request $request)
    {
        $data = $request->validate([
            'total_amount' => 'nullable|numeric',
        ]);

        $user = $request->user();
        $storeId = $user->store->id;

        $active = $user->settlements()->active()->first();
        if (! $active) {
            return $this->error('shift', 'Tidak ada shift aktif.', 409);
        }

        $openBillCount = Cart::openBills()->where('store_id', $storeId)->count();
        if ($openBillCount > 0) {
            return $this->error(
                'open_bills',
                "Tidak bisa tutup shift: masih ada {$openBillCount} open bill. Selesaikan atau cancel dulu.",
                409
            );
        }

        $data['end_time'] = Carbon::now()->toDateTimeString();
        $active->update($data);

        ActivityLogger::log(
            'Close Shift',
            'Closing shift with total cash: Rp '.number_format($data['total_amount'] ?? 0, 0, ',', '.'),
            $storeId
        );

        return $this->ok(['settlement' => new SettlementResource($active->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $storeId = $request->user()->store->id;

        $settlement = Settlement::where('store_id', $storeId)->where('id', $id)->first();
        if (! $settlement) {
            return $this->error('settlement', 'Settlement tidak ditemukan.', 404);
        }

        $settlement->delete();

        ActivityLogger::log('Delete Settlement', "Deleting settlement #{$id}", $storeId);

        return $this->ok(['message' => 'Settlement deleted.']);
    }
}
