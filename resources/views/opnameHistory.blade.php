<!DOCTYPE html>
<html lang="en">

<head>
    <title>Opname History</title>
    @include('layout.head')
    <link href="//cdn.datatables.net/2.0.2/css/dataTables.dataTables.min.css" rel="stylesheet" />

    <style>
        .dataTables_wrapper .dataTables_length select {
            padding-right: 2rem;
            border-radius: 0.5rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            padding: 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
        }

        table.dataTable.no-footer {
            border-bottom: 1px solid #e5e7eb;
        }
    </style>
</head>

<body class="bg-gray-50 font-sans">
    @include('layout.sidebar')

    <main class="md:ml-64 xl:ml-72 2xl:ml-72">
        @include('layout.navbar')
        <div class="p-6 space-y-6">

            <!-- Header Section -->
            <div class="md:flex justify-between items-center bg-white p-5 rounded-xl shadow-sm border border-gray-100 space-y-2 md:space-y-0">
                <div>
                    <h1 class="font-bold text-2xl text-gray-800 flex items-center gap-2">
                        <i class="fas fa-history text-indigo-500 text-4xl"></i> Stock Opname History
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Audit trail of all stock opname sessions for comparison.</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('stock') }}"
                        class="px-6 py-3 bg-gray-200 text-base text-gray-800 rounded-lg shadow-sm hover:bg-gray-300 hover:scale-105 transition font-bold flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Back to Stock
                    </a>
                    <a href="{{ route('opname') }}"
                        class="px-6 py-3 bg-blue-500 text-base text-white rounded-lg shadow-md hover:bg-blue-600 hover:scale-105 transition font-bold flex items-center gap-2">
                        <i class="fas fa-clipboard-check"></i> New Opname
                    </a>
                </div>
            </div>

            @if ($sessions->isEmpty())
                <!-- Empty State -->
                <div class="w-full bg-white rounded-xl shadow-md border border-gray-100">
                    <div class="p-12 text-center">
                        <div class="flex flex-col items-center justify-center opacity-70">
                            <div
                                class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mb-4 border border-indigo-100">
                                <i class="fas fa-inbox text-4xl text-indigo-300"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900">No opname history yet</h3>
                            <p class="text-sm text-gray-500 mt-1 mb-6">Run your first stock opname to start building an
                                audit trail.</p>
                            <a href="{{ route('opname') }}"
                                class="px-6 py-3 bg-indigo-500 text-sm text-white rounded-lg shadow-md hover:bg-indigo-600 hover:scale-105 transition font-bold flex items-center gap-2">
                                <i class="fas fa-clipboard-check"></i> Start Opname
                            </a>
                        </div>
                    </div>
                </div>
            @else
            
                <div class="space-y-6">
                    @foreach ($sessions as $session)
                        @php
                            $sessionTime = $session['created_at'];
                        @endphp
                        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
                            <!-- Session header (click to expand/collapse) -->
                            <div class="session-toggle cursor-pointer select-none px-5 py-4 bg-indigo-50/50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="bg-indigo-100 text-indigo-700 rounded-lg p-2.5">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <div>
                                        <h2 class="font-bold text-gray-800">
                                            {{ $sessionTime->isoFormat('dddd, D MMM Y') }}
                                            <span class="text-gray-500 font-normal text-sm">
                                                · {{ $sessionTime->format('H:i') }}
                                            </span>
                                        </h2>
                                        <p class="text-xs text-gray-600 mt-0.5">
                                            <i class="fas fa-user mr-1 text-gray-400"></i>{{ $session['user_name'] }}
                                            <span class="mx-2 text-gray-300">|</span>
                                            <i class="fas fa-comment-dots mr-1 text-gray-400"></i>{{ $session['reason'] }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-2 text-xs items-center">
                                    <span class="bg-white border border-gray-200 text-gray-700 px-3 py-1.5 rounded-full font-bold">
                                        {{ $session['total_items'] }} item(s)
                                    </span>
                                    @if ($session['items_up'] > 0)
                                        <span class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-1.5 rounded-full font-bold">
                                            <i class="fas fa-arrow-up mr-0.5"></i>{{ $session['items_up'] }} naik
                                        </span>
                                    @endif
                                    @if ($session['items_down'] > 0)
                                        <span class="bg-red-50 border border-red-200 text-red-700 px-3 py-1.5 rounded-full font-bold">
                                            <i class="fas fa-arrow-down mr-0.5"></i>{{ $session['items_down'] }} turun
                                        </span>
                                    @endif
                                    <i class="fas fa-chevron-down session-chevron text-gray-400 transition-transform ml-1"></i>
                                </div>
                            </div>

                            <!-- Session items (collapsed by default) -->
                            <div class="session-body hidden border-t border-gray-100 p-5 overflow-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                        <tr>
                                            <th class="p-3 font-bold">Ingredient</th>
                                            <th class="p-3 font-bold text-center">Previous Opname</th>
                                            <th class="p-3 font-bold text-center">System (before)</th>
                                            <th class="p-3 font-bold text-center">Actual (after)</th>
                                            <th class="p-3 font-bold text-center">Δ vs System</th>
                                            <th class="p-3 font-bold text-center">Δ vs Previous</th>
                                            <th class="p-3 font-bold w-56">Item Note</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($session['items'] as $row)
                                            @php
                                                $stockBefore = (int) ($row->stock_before ?? 0);
                                                $stockAfter = $stockBefore + (int) $row->quantity;
                                                $unit = $row->invent->unit ?? '';
                                                $invName = $row->invent->name ?? 'Deleted ingredient';

                                                // Find previous opname for same invent before this one.
                                                $history = $previousOpnameByInvent[$row->invent_id] ?? [];
                                                $prevValue = null;
                                                foreach ($history as $h) {
                                                    if ($h['created_at']->lt($row->created_at)) {
                                                        $prevValue = $h['stock_after'];
                                                    } else {
                                                        break;
                                                    }
                                                }

                                                $deltaSystem = (int) $row->quantity;
                                                $deltaPrev = $prevValue !== null ? $stockAfter - $prevValue : null;
                                            @endphp
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="p-3 font-semibold text-gray-800">{{ $invName }}</td>
                                                <td class="p-3 text-center font-mono">
                                                    @if ($prevValue !== null)
                                                        <span class="text-gray-700">{{ $prevValue }} <span class="text-gray-400 text-xs">{{ $unit }}</span></span>
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>
                                                <td class="p-3 text-center font-mono text-gray-600">
                                                    {{ $stockBefore }} <span class="text-gray-400 text-xs">{{ $unit }}</span>
                                                </td>
                                                <td class="p-3 text-center font-mono font-bold text-gray-900">
                                                    {{ $stockAfter }} <span class="text-gray-400 text-xs">{{ $unit }}</span>
                                                </td>
                                                <td class="p-3 text-center font-mono font-bold">
                                                    @if ($deltaSystem > 0)
                                                        <span class="text-emerald-600">+{{ $deltaSystem }}</span>
                                                    @elseif ($deltaSystem < 0)
                                                        <span class="text-red-600">{{ $deltaSystem }}</span>
                                                    @else
                                                        <span class="text-gray-400">0</span>
                                                    @endif
                                                </td>
                                                <td class="p-3 text-center font-mono font-bold">
                                                    @if ($deltaPrev === null)
                                                        <span class="text-gray-300">—</span>
                                                    @elseif ($deltaPrev > 0)
                                                        <span class="text-emerald-600">+{{ $deltaPrev }}</span>
                                                    @elseif ($deltaPrev < 0)
                                                        <span class="text-red-600">{{ $deltaPrev }}</span>
                                                    @else
                                                        <span class="text-gray-400">0</span>
                                                    @endif
                                                </td>
                                                <td class="p-3 text-gray-600 align-top">
                                                    @if (! empty($row->item_notes))
                                                        <div class="flex items-start gap-1 w-56 max-w-56">
                                                            <i class="fas fa-comment-dots text-gray-400 mt-0.5 shrink-0"></i>
                                                            <span class="min-w-0 whitespace-normal break-words">{{ $row->item_notes }}</span>
                                                        </div>
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 pb-24">
                    {{ $sessions->links('vendor.pagination.datatables') }}
                </div>
            @endif
        </div>
    </main>

    <script>
        // Expand/collapse each opname session to save vertical space.
        document.querySelectorAll('.session-toggle').forEach(function (header) {
            header.addEventListener('click', function () {
                var card = header.parentElement;
                var body = card.querySelector('.session-body');
                var chevron = header.querySelector('.session-chevron');
                if (body) body.classList.toggle('hidden');
                if (chevron) chevron.classList.toggle('rotate-180');
            });
        });
    </script>

    @include('sweetalert::alert')
</body>

</html>