<!DOCTYPE html>
<html lang="en">

<head>
    <title>Detail Settlement</title>
    @include('layout.head')
    <link href="//cdn.datatables.net/2.0.2/css/dataTables.dataTables.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            <div
                class="md:flex justify-between items-center bg-white p-5 rounded-xl shadow-sm border border-gray-100 space-y-2 md:space-y-0">
                <div>
                    <h1 class="font-bold text-2xl text-gray-800 flex items-center gap-2">
                        <i class="fas fa-tags text-red-500"></i> Detail Settlement
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">View shift settlement details and transactions</p>
                </div>
                <a href="{{ route('settlement') }}"
                    class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg shadow-sm hover:bg-gray-300 hover:scale-105 transition font-bold flex items-center gap-2 text-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 border border-blue-100">
                            <i class="far fa-clock text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-bold">Time</p>
                            <p class="font-bold text-gray-800 text-sm mt-0.5">
                                {{ $settlement->start_time ? \Carbon\Carbon::parse($settlement->start_time)->format('d M H:i') : '-' }}
                                →
                                {{ $settlement->end_time ? \Carbon\Carbon::parse($settlement->end_time)->format('d M H:i') : '-' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 border border-gray-100">
                            <i class="fas fa-coins text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-bold">Start Amount</p>
                            <p class="font-mono font-bold text-gray-800 text-lg mt-0.5">
                                Rp {{ number_format($settlement->start_amount, 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-full bg-yellow-50 flex items-center justify-center text-yellow-600 border border-yellow-100">
                            <i class="fas fa-cash-register text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-bold">Total Amount</p>
                            <p class="font-mono font-bold text-gray-800 text-lg mt-0.5">
                                Rp {{ number_format($settlement->total_amount, 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 border border-emerald-100">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-bold">Expected</p>
                            <p class="font-mono font-bold text-emerald-700 text-lg mt-0.5">
                                Rp {{ number_format($settlement->expected, 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Method Breakdown -->
            <div class="w-full bg-white rounded-xl shadow-md border border-gray-100">
                <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 rounded-t-xl flex items-center justify-between">
                    <h2 class="font-bold text-gray-800 flex items-center gap-2 text-base">
                        <i class="fas fa-wallet text-gray-500"></i> Payment Breakdown
                    </h2>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 uppercase font-bold">Grand Total</p>
                        <p class="font-mono font-bold text-emerald-700 text-lg">
                            Rp {{ number_format($grandTotal, 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="p-5">
                    @if ($paymentBreakdown->isEmpty())
                        <p class="text-sm text-gray-500 text-center py-4">No completed orders in this shift.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($paymentBreakdown as $type => $data)
                                @php
                                    $pct = $grandTotal > 0 ? round(($data['total'] / $grandTotal) * 100) : 0;
                                @endphp
                                <div class="border border-gray-100 rounded-xl p-4 bg-gray-50">
                                    <div class="flex items-center justify-between mb-2">
                                        <span
                                            class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full font-bold border border-gray-200 uppercase">
                                            {{ $type }}
                                        </span>
                                        <span class="text-xs text-gray-500 font-bold">{{ $data['count'] }} order</span>
                                    </div>
                                    <p class="font-mono font-bold text-gray-800 text-lg">
                                        Rp {{ number_format($data['total'], 0, ',', '.') }}</p>
                                    <div class="mt-2 h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">{{ $pct }}% of total</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Table Section -->
            <div class="w-full bg-white rounded-xl shadow-md border border-gray-100">
                <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 rounded-t-xl">
                    <h2 class="font-bold text-gray-800 flex items-center gap-2 text-base">
                        <i class="fas fa-list text-gray-500"></i> Completed Orders
                        <span
                            class="ml-1 bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full font-bold border border-gray-200">
                            {{ $settlement->histories->count() }} Items
                        </span>
                    </h2>
                </div>

                <div class="p-5 overflow-auto">
                    <table id="myTable" class="w-full text-left">
                        <thead class="bg-gray-100 text-gray-600 text-sm leading-normal">
                            <tr>
                                <th class="p-4 font-bold rounded-tl-lg" width="5%">
                                    <div class="flex items-center justify-center">No</div>
                                </th>
                                <th class="p-4 font-bold">Name</th>
                                <th class="p-4 font-bold">Order ID</th>
                                <th class="p-4 font-bold">Order</th>
                                <th class="p-4 font-bold">Payment</th>
                                <th class="p-4 font-bold">Total Amount</th>
                                <th class="p-4 font-bold rounded-tr-lg">
                                    <div class="flex items-center justify-center">Status</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm divide-y divide-gray-200">
                            @php $no = 1; @endphp
                            @foreach ($settlement->histories as $item)
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="p-4 font-medium text-center">{{ $no++ }}</td>

                                    <td class="p-4">
                                        <div class="font-bold text-gray-900">{{ $item->name }}</div>
                                    </td>

                                    <td class="p-4 font-mono text-xs text-gray-700">{{ $item->no_order }}</td>

                                    <td class="p-4 text-xs text-gray-600">
                                        @php $orders = explode(' - ', $item->order); @endphp
                                        @foreach ($orders as $order)
                                            <div>{{ $order }}</div>
                                        @endforeach
                                    </td>

                                    <td class="p-4">
                                        <span
                                            class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full font-bold border border-gray-200 uppercase">
                                            {{ $item->payment_type }}
                                        </span>
                                    </td>

                                    <td class="p-4">
                                        <span class="font-mono font-bold text-gray-800">
                                            Rp {{ number_format($item->total_amount, 0, ',', '.') }}
                                        </span>
                                    </td>

                                    <td class="p-4 text-center">
                                        @php
                                            $statusColor = $item->status == 'settlement'
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                : 'bg-gray-100 text-gray-600 border-gray-200';
                                        @endphp
                                        <span
                                            class="{{ $statusColor }} px-3 py-1 rounded-full text-xs font-bold border uppercase shadow-sm">
                                            {{ $item->status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="//cdn.datatables.net/2.0.2/js/dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            if ($('#myTable').length) {
                new DataTable('#myTable', {});
            }
        });
    </script>

    @include('sweetalert::alert')
</body>

</html>
