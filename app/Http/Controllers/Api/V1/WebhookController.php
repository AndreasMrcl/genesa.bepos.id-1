<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    use ApiResponse;

    public function midtrans(Request $request)
    {
        $payload = $request->all();

        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $signatureKey = $payload['signature_key'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $transactionId = $payload['transaction_id'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;

        if (! $orderId || ! $statusCode || ! $grossAmount || ! $signatureKey) {
            return response()->json(['errors' => ['payload' => ['Incomplete payload.']]], 400);
        }

        $serverKey = config('midtrans.server_key');
        if (! $serverKey) {
            Log::warning('Midtrans webhook received but server_key not configured');

            return response()->json(['errors' => ['config' => ['Midtrans not configured.']]], 500);
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
        if (! hash_equals($expected, $signatureKey)) {
            Log::warning('Midtrans webhook signature mismatch', ['order_id' => $orderId]);

            return response()->json(['errors' => ['signature' => ['Invalid signature.']]], 401);
        }

        $order = Order::where('no_order', $orderId)->first();
        if (! $order) {
            Log::info('Midtrans webhook for unknown order', ['order_id' => $orderId]);

            return response()->json(['ok' => true]);
        }

        DB::transaction(function () use ($order, $transactionStatus, $fraudStatus, $transactionId) {
            $finalStatus = $transactionStatus;

            if ($transactionStatus === 'capture' && $fraudStatus === 'accept') {
                $finalStatus = 'settlement';
            }

            // A6: order yang sudah settle/capture bersifat final. Abaikan notifikasi non-final
            // yang datang belakangan (mis. 'expire' telat/duplikat) supaya order tidak terhapus
            // dan stok yang sudah dipotong tidak hilang.
            if (in_array($order->status, ['settlement', 'capture'], true)
                && ! in_array($finalStatus, ['settlement', 'capture'], true)) {
                return;
            }

            $order->update([
                'status'            => $finalStatus,
                'payment_reference' => $transactionId ?? $order->payment_reference,
            ]);

            if (in_array($finalStatus, ['settlement', 'capture'], true)) {
                app(InventoryService::class)->consumeForOrder($order);
            }

            if ($finalStatus === 'expire') {
                $order->delete();
            }
        });

        return response()->json(['ok' => true]);
    }
}
