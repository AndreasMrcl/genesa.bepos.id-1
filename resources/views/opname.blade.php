<!DOCTYPE html>
<html lang="en">

<head>
    <title>Stock Opname</title>
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

        .filter-chip.active {
            background-color: #2563eb;
            color: #fff;
            border-color: #2563eb;
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
                        <i class="fas fa-clipboard-check text-blue-500 text-4xl"></i> Stock Opname
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Reconcile system stock with physical count</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('opnameHistory') }}"
                        class="px-6 py-3 bg-white text-base border border-gray-300 text-gray-800 rounded-lg shadow-sm hover:bg-gray-50 transition font-bold flex items-center gap-2">
                        <i class="fas fa-history"></i> History
                    </a>
                    <a href="{{ route('stock') }}"
                        class="px-6 py-3 bg-gray-200 text-base text-gray-800 rounded-lg shadow-sm hover:bg-gray-300 transition font-bold flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            @if ($invents->isEmpty())
                <!-- Empty State -->
                <div class="w-full bg-white rounded-xl shadow-md border border-gray-100">
                    <div class="p-12 text-center">
                        <div class="flex flex-col items-center justify-center opacity-70">
                            <div
                                class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mb-4 border border-blue-100">
                                <i class="fas fa-inbox text-4xl text-blue-300"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900">No ingredients yet</h3>
                            <p class="text-sm text-gray-500 mt-1 mb-6">Add an ingredient first via <strong>Master
                                    Ingredient</strong> before doing a stock opname.</p>
                            <a href="{{ route('invent') }}"
                                class="px-6 py-3 bg-blue-500 text-base text-white rounded-lg shadow-md hover:bg-blue-600 hover:scale-105 transition font-bold flex items-center gap-2">
                                <i class="fas fa-plus"></i> Add Ingredient
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <form id="opnameForm" method="post" action="{{ route('opnameinvent') }}" class="space-y-6">
                    @csrf

                    <!-- Reason & Filter Card -->
                    <div class="bg-white p-5 rounded-xl shadow-md border border-gray-100 space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Reason / Opname Notes</label>
                            <input type="text" name="reason" maxlength="255"
                                placeholder="e.g.: Monthly audit May 2026, beginning-of-shift correction, etc."
                                class="w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-blue-500"
                                required>
                            <p class="text-xs text-gray-500 mt-1">Applies to all ingredients you adjust in this session.
                            </p>
                        </div>

                        <!-- Filter Chips -->
                        <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                            <button type="button"
                                class="filter-chip active px-4 py-1.5 text-sm font-bold rounded-full border border-gray-300 text-gray-700 hover:bg-gray-100 transition"
                                data-filter="all">All</button>
                            <button type="button"
                                class="filter-chip px-4 py-1.5 text-sm font-bold rounded-full border border-gray-300 text-gray-700 hover:bg-gray-100 transition"
                                data-filter="empty">Empty</button>
                            <button type="button"
                                class="filter-chip px-4 py-1.5 text-sm font-bold rounded-full border border-gray-300 text-gray-700 hover:bg-gray-100 transition"
                                data-filter="filled">Filled</button>
                            <button type="button"
                                class="filter-chip px-4 py-1.5 text-sm font-bold rounded-full border border-gray-300 text-gray-700 hover:bg-gray-100 transition"
                                data-filter="delta">With Delta</button>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div class="w-full bg-white rounded-xl shadow-md border border-gray-100">
                        <div class="p-5 overflow-auto">
                            <table id="myTable" class="w-full text-left">
                                <thead class="bg-gray-100 text-gray-600 text-sm leading-normal">
                                    <tr>
                                        <th class="p-4 font-bold rounded-tl-lg" width="5%">
                                            <div class="flex items-center justify-center">No</div>
                                        </th>
                                        <th class="p-4 font-bold">Name</th>
                                        <th class="p-4 font-bold">
                                            <div class="flex items-center justify-center">System Stock</div>
                                        </th>
                                        <th class="p-4 font-bold" width="15%">
                                            <div class="flex items-center justify-center">Actual Stock</div>
                                        </th>
                                        <th class="p-4 font-bold" width="15%">
                                            <div class="flex items-center justify-center">Delta</div>
                                        </th>
                                        <th class="p-4 font-bold rounded-tr-lg" width="22%">
                                            <div class="flex items-center justify-center">Item Note</div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 text-sm divide-y divide-gray-200">
                                    @php $no = 1; @endphp
                                    @foreach ($invents as $idx => $item)
                                        <tr class="opname-row hover:bg-gray-50 transition duration-150 {{ $item->isLowStock() ? 'bg-yellow-50' : '' }}"
                                            data-stock="{{ $item->stock }}" data-unit="{{ $item->unit }}">
                                            <td class="p-4 font-medium text-center">{{ $no++ }}</td>

                                            <td class="p-4">
                                                <div class="font-bold text-gray-900 text-base">
                                                    {{ $item->name }}
                                                    @if ($item->isLowStock())
                                                        <i class="fas fa-exclamation-triangle text-yellow-500 ml-1"
                                                            title="Low stock"></i>
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="p-4 text-center">
                                                <span class="font-mono font-bold text-gray-800">
                                                    {{ $item->stock }}
                                                </span>
                                                <span class="text-gray-500 text-xs">{{ $item->unit }}</span>
                                            </td>

                                            <td class="p-4">
                                                <input type="hidden" name="items[{{ $idx }}][invent_id]"
                                                    value="{{ $item->id }}">
                                                <input type="number" name="items[{{ $idx }}][actual_stock]"
                                                    min="0" placeholder="leave empty to skip"
                                                    class="actual-stock w-full rounded-lg border-gray-300 shadow-sm p-2 border focus:ring-2 focus:ring-blue-500 text-center font-mono">
                                            </td>

                                            <td class="p-4 text-center">
                                                <span class="delta-preview text-gray-400 text-sm">—</span>
                                            </td>

                                            <td class="p-4">
                                                <input type="text" name="items[{{ $idx }}][item_notes]"
                                                    maxlength="255" placeholder="Reason for this item (optional)"
                                                    class="w-full rounded-lg border-gray-300 shadow-sm p-2 border focus:ring-2 focus:ring-blue-500 text-sm">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div
                        class="sticky bottom-4 bg-white p-4 rounded-xl shadow-lg border border-gray-200 flex flex-col md:flex-row justify-between items-center gap-3">
                        <p class="text-sm text-gray-600">
                            <span id="actionSummary">No changes yet.</span>
                        </p>
                        <div class="flex gap-2 w-full md:w-auto">
                            <a href="{{ route('stock') }}"
                                class="px-6 py-3 bg-gray-200 text-base text-gray-800 rounded-lg hover:bg-gray-300 transition font-bold flex-1 md:flex-none text-center">
                                Cancel
                            </a>
                            <button type="submit" id="submitBtn"
                                class="px-6 py-3 bg-blue-500 text-white rounded-lg shadow-md hover:bg-blue-600 hover:scale-105 transition font-bold flex items-center gap-2 justify-center flex-1 md:flex-none text-base disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                                disabled>
                                <i class="fas fa-check"></i> Save Opname
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </main>

    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="//cdn.datatables.net/2.0.2/js/dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            if (!document.getElementById('opnameForm')) return;
            const table = new DataTable('#myTable', {
                ordering: false,
                pageLength: 25,
            });

            let currentFilter = 'all';

            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'myTable') return true;
                const row = table.row(dataIndex).node();
                const $input = $(row).find('.actual-stock');
                const val = $input.val();
                const sysStock = parseInt($(row).data('stock'));

                if (currentFilter === 'all') return true;
                if (currentFilter === 'empty') return val === '' || val === null;
                if (currentFilter === 'filled') return val !== '' && val !== null;
                if (currentFilter === 'delta') {
                    if (val === '' || val === null) return false;
                    return parseInt(val) !== sysStock;
                }
                return true;
            });

            function recalcRow($row) {
                const sysStock = parseInt($row.data('stock'));
                const unit = $row.data('unit') || '';
                const $input = $row.find('.actual-stock');
                const $preview = $row.find('.delta-preview');
                const raw = $input.val();

                if (raw === '' || raw === null) {
                    $preview.html('<span class="text-gray-400">—</span>');
                    return {
                        filled: false,
                        delta: 0
                    };
                }

                const actual = parseInt(raw);
                if (isNaN(actual)) {
                    $preview.html('<span class="text-gray-400">—</span>');
                    return {
                        filled: false,
                        delta: 0
                    };
                }

                const delta = actual - sysStock;
                if (delta === 0) {
                    $preview.html('<span class="font-mono text-gray-500">Equal (0 ' + unit + ')</span>');
                } else if (delta > 0) {
                    $preview.html('<span class="font-mono font-bold text-emerald-600">+' + delta + ' ' + unit +
                        '</span>');
                } else {
                    $preview.html('<span class="font-mono font-bold text-red-600">' + delta + ' ' + unit +
                        '</span>');
                }
                return {
                    filled: true,
                    delta: delta
                };
            }

            function recalcSummary() {
                let changed = 0;
                $('.opname-row').each(function() {
                    const r = recalcRow($(this));
                    if (r.filled && r.delta !== 0) changed++;
                });

                if (changed === 0) {
                    $('#actionSummary').text('No changes yet.');
                    $('#submitBtn').prop('disabled', true);
                } else {
                    $('#actionSummary').html('<strong>' + changed + '</strong> ingredient(s) will be adjusted.');
                    $('#submitBtn').prop('disabled', false);
                }
            }

            $(document).on('input change', '.actual-stock', function() {
                recalcRow($(this).closest('.opname-row'));
                recalcSummary();
                if (currentFilter !== 'all') table.draw();
            });

            $('.filter-chip').on('click', function() {
                $('.filter-chip').removeClass('active');
                $(this).addClass('active');
                currentFilter = $(this).data('filter');
                table.draw();
            });

            $('#opnameForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;

                Swal.fire({
                    title: 'Save opname?',
                    text: 'Deltas will be permanently recorded in stock movements.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, Save',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) form.submit();
                });
            });
        });
    </script>

    @include('sweetalert::alert')
    @include('layout.loading')
</body>

</html>