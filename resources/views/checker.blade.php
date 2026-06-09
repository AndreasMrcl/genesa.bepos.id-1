<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Checker {{ $order->no_order }}</title>
    @include('layout.head')
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
            }
            .checker {
                box-shadow: none !important;
                border: none !important;
            }
            /* Each station ticket prints on its own slip */
            .checker {
                page-break-after: always;
            }
            .checker:last-child {
                page-break-after: auto;
            }
        }

        .checker {
            font-family: 'Courier New', Courier, monospace;
            max-width: 320px;
            margin: 0 auto 1rem;
            background: white;
        }

        .dashed {
            border-top: 1px dashed #999;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen p-4">

    <div class="no-print max-w-md mx-auto mb-4 flex justify-between gap-2">
        <a href="{{ route('order') }}"
            class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg shadow-sm hover:bg-gray-300 hover:scale-105 transition font-bold flex items-center gap-2 text-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button onclick="window.print()"
            class="px-6 py-3 bg-blue-500 text-white rounded-lg shadow-md hover:bg-blue-600 hover:scale-105 transition font-bold flex items-center gap-2 text-sm">
            <i class="fas fa-print"></i> Print Checker
        </button>
    </div>

    @forelse ($groups as $stationName => $items)
        <div class="checker p-4 shadow-lg rounded-lg text-sm">
            <div class="text-center space-y-1 mb-3">
                <h1 class="font-bold text-lg uppercase">{{ $stationName }}</h1>
                <p class="text-xs text-gray-600">Preparation Ticket</p>
            </div>

            <div class="dashed pt-2 mb-2"></div>

            <div class="space-y-0.5 text-xs">
                <div class="flex justify-between">
                    <span>Order No:</span>
                    <span class="font-bold">{{ $order->no_order }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Table/Name:</span>
                    <span class="font-bold">
                        {{ $order->cart->customer_name ?? $order->cart->chair->name ?? '-' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span>Time:</span>
                    <span>{{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Service:</span>
                    <span>{{ $order->layanan ?? 'dine-in' }}</span>
                </div>
            </div>

            <div class="dashed pt-2 mb-2"></div>

            <div class="space-y-2">
                @foreach ($items as $cm)
                    <div class="flex gap-2">
                        <span class="font-bold text-base w-8 shrink-0">{{ $cm->quantity }}x</span>
                        <div class="flex-1">
                            <div class="font-bold text-base">{{ $cm->menu->name }}</div>
                            @if ($cm->variety && $cm->variety !== 'normal')
                                <div class="text-xs italic">{{ str_replace('_', ' ', $cm->variety) }}</div>
                            @endif
                            @if ($cm->notes)
                                <div class="text-xs italic">Note: {{ $cm->notes }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="dashed pt-2 mt-3 mb-2"></div>

            <div class="text-center text-[10px] text-gray-500">
                {{ \Carbon\Carbon::parse($order->created_at)->format('d M Y H:i:s') }}
            </div>
        </div>
    @empty
        <div class="checker p-4 text-center text-gray-500">No items to prepare.</div>
    @endforelse

</body>

</html>
