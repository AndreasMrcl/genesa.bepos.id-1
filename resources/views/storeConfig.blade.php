<!DOCTYPE html>
<html lang="en">

<head>
    <title>Store Configuration</title>
    @include('layout.head')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-50 font-sans">
    @include('layout.sidebar')

    <main class="md:ml-64 xl:ml-72 2xl:ml-72">
        @include('layout.navbar')
        <div class="p-6 space-y-6">

            <!-- Header Section -->
            <div class="flex justify-between items-center bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                <div>
                    <h1 class="font-bold text-2xl text-gray-800 flex items-center gap-2">
                        <i class="fas fa-cogs text-slate-600 text-4xl"></i> Store Configuration
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Global settings for store operations</p>
                </div>
            </div>

            @if (session('success'))
                <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Form Section -->
            <div class="w-full bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
                <div class="p-6">
                    <form action="{{ route('updatestoreConfig') }}" method="POST" class="space-y-8">
                        @csrf
                        @method('PUT')

                        <!-- TRANSACTION SETTINGS -->
                        <div>
                            <h3 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 border-b pb-2">
                                <i class="fas fa-money-bill-wave mr-1"></i> Transaction Settings
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Currency</label>
                                    <select name="currency"
                                        class="w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-indigo-500">
                                        <option value="IDR" {{ $config->currency == 'IDR' ? 'selected' : '' }}>IDR
                                            (Rupiah)</option>
                                        <option value="USD" {{ $config->currency == 'USD' ? 'selected' : '' }}>USD
                                            (Dollar)</option>
                                        <option value="MYR" {{ $config->currency == 'MYR' ? 'selected' : '' }}>MYR
                                            (Ringgit)</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Default currency for all transactions</p>
                                </div>
                            </div>
                        </div>

                        <!-- TAX & SERVICE -->
                        <div>
                            <h3 class="text-sm font-bold text-emerald-600 uppercase tracking-wider mb-4 border-b pb-2">
                                <i class="fas fa-percent mr-1"></i> Tax & Service Charge
                            </h3>

                            <!-- Master Switches -->
                            <div class="md:flex gap-6 mb-6 space-y-2 md:space-y-0">
                                <label
                                    class="inline-flex items-center cursor-pointer bg-gray-50 px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
                                    <input type="checkbox" name="tax_active" value="1"
                                        {{ $config->tax_active ? 'checked' : '' }}
                                        class="w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="ml-2 text-sm font-semibold text-gray-700">Enable Tax (VAT)</span>
                                </label>
                                <label
                                    class="inline-flex items-center cursor-pointer bg-gray-50 px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
                                    <input type="checkbox" name="service_active" value="1"
                                        {{ $config->service_active ? 'checked' : '' }}
                                        class="w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="ml-2 text-sm font-semibold text-gray-700">Enable Service Charge</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tax / VAT</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" name="tax_percent"
                                            value="{{ $config->tax_percent }}"
                                            class="w-full pr-10 rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-indigo-500"
                                            required>
                                        <span class="absolute right-3 top-2.5 text-gray-500 font-medium">%</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Tax percentage automatically applied to each order.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Service Charge</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" name="service_percent"
                                            value="{{ $config->service_percent }}"
                                            class="w-full pr-10 rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-indigo-500"
                                            required>
                                        <span class="absolute right-3 top-2.5 text-gray-500 font-medium">%</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Additional service fee per order.</p>
                                </div>
                            </div>
                        </div>

                        <!-- ORDER CHECKER -->
                        <div>
                            <h3 class="text-sm font-bold text-blue-600 uppercase tracking-wider mb-4 border-b pb-2">
                                <i class="fas fa-utensils mr-1"></i> Order Checker
                            </h3>
                            <div class="md:flex gap-6 items-center">
                                <label
                                    class="inline-flex items-center cursor-pointer bg-gray-50 px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
                                    <input type="checkbox" name="checker_active" value="1"
                                        {{ $config->checker_active ? 'checked' : '' }}
                                        class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm font-semibold text-gray-700">Enable Order Checker</span>
                                </label>
                                <p class="text-xs text-gray-500 mt-2 md:mt-0">
                                    Splits each order into separate station tickets (e.g. Bar &amp; Kitchen).
                                    Manage stations in the section below.
                                </p>
                            </div>
                        </div>

                        <!-- INVENTORY & ORDER -->
                        <div>
                            <h3 class="text-sm font-bold text-amber-600 uppercase tracking-wider mb-4 border-b pb-2">
                                <i class="fas fa-warehouse mr-1"></i> Inventory & Order
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Min. Stock Alert</label>
                                    <input type="number" name="min_stock_alert"
                                        value="{{ $config->min_stock_alert }}" min="0"
                                        class="w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-amber-500"
                                        required>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Notification appears if ingredient stock ≤ this number.
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Auto Archive Order
                                        (Days)</label>
                                    <input type="number" name="auto_archive_days"
                                        value="{{ $config->auto_archive_days }}" min="1"
                                        class="w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-amber-500"
                                        required>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Order will be automatically archived after X days.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- RECEIPT SETTINGS -->
                        <div>
                            <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-4 border-b pb-2">
                                <i class="fas fa-receipt mr-1"></i> Receipt Format
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Receipt Header</label>
                                    <input type="text" name="receipt_header"
                                        value="{{ $config->receipt_header }}"
                                        placeholder="Example: Welcome to our store"
                                        class="w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Text that appears at the top of the receipt.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Receipt Footer</label>
                                    <input type="text" name="receipt_footer"
                                        value="{{ $config->receipt_footer }}"
                                        placeholder="Example: Thank you for your visit"
                                        class="w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Text that appears at the bottom of the receipt.</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-end border-t border-gray-100">
                            <button type="submit"
                                class="px-8 py-3 bg-slate-800 text-white font-bold rounded-lg shadow-lg hover:bg-slate-900 transition transform hover:-translate-y-0.5 flex items-center gap-2">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- STATIONS MANAGEMENT -->
            <div class="w-full bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h3 class="text-sm font-bold text-blue-600 uppercase tracking-wider">
                            <i class="fas fa-utensils mr-1"></i> Stations
                        </h3>
                        <button type="button" id="addStationBtn"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow-sm hover:bg-blue-600 transition font-bold flex items-center gap-2 text-sm">
                            <i class="fas fa-plus"></i> Add Station
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mb-4">
                        Preparation stations (e.g. Bar, Kitchen) used to split order checkers. Assign a station to each
                        menu in the Product page.
                    </p>

                    @if ($stations->isEmpty())
                        <div class="text-center text-sm text-gray-400 py-8">
                            <i class="fas fa-inbox text-2xl mb-2"></i>
                            <p>No stations yet. Add one to start splitting checkers.</p>
                        </div>
                    @else
                        <div class="overflow-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                    <tr>
                                        <th class="p-3 font-bold" width="5%">No</th>
                                        <th class="p-3 font-bold">Name</th>
                                        <th class="p-3 font-bold text-center">Menus</th>
                                        <th class="p-3 font-bold text-center">Status</th>
                                        <th class="p-3 font-bold text-center" width="15%">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($stations as $i => $st)
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="p-3 text-center">{{ $i + 1 }}</td>
                                            <td class="p-3 font-semibold text-gray-800">{{ $st->name }}</td>
                                            <td class="p-3 text-center">
                                                <span class="bg-gray-100 text-gray-700 text-xs px-2.5 py-1 rounded-full font-bold">{{ $st->menus_count }}</span>
                                            </td>
                                            <td class="p-3 text-center">
                                                @if ($st->is_active)
                                                    <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs px-2.5 py-1 rounded-full font-bold">Active</span>
                                                @else
                                                    <span class="bg-gray-100 text-gray-500 border border-gray-200 text-xs px-2.5 py-1 rounded-full font-bold">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="p-3">
                                                <div class="flex justify-center items-center gap-2">
                                                    <button type="button"
                                                        class="editStationBtn w-9 h-9 flex items-center justify-center bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 transition"
                                                        data-id="{{ $st->id }}" data-name="{{ $st->name }}"
                                                        data-is_active="{{ $st->is_active ? 1 : 0 }}" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="post" action="{{ route('delstation', ['id' => $st->id]) }}"
                                                        class="inline stationDeleteForm">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="button"
                                                            class="stationDeleteBtn w-9 h-9 flex items-center justify-center bg-red-500 text-white rounded-lg shadow hover:bg-red-600 transition"
                                                            title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </main>

    <!-- ADD/EDIT STATION MODAL -->
    <div id="stationModal" role="dialog" aria-hidden="true"
        class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm items-center justify-center z-50 overflow-y-auto px-4 py-6">
        <div class="bg-white rounded-2xl p-8 w-full max-w-md shadow-2xl relative">
            <button type="button" id="closeStationModal" aria-label="Close"
                class="absolute top-5 right-5 text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>

            <h2 id="stationModalTitle" class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2">
                <i class="fas fa-utensils text-blue-500 text-3xl"></i> Add Station
            </h2>

            <form id="stationForm" method="post" action="{{ route('poststation') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="_method" id="stationMethod" value="post">

                <div>
                    <label for="stationName" class="block text-sm font-semibold text-gray-700 mb-2">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="stationName" name="name"
                        class="w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. Bar, Kitchen" required maxlength="50">
                </div>

                <label class="inline-flex items-center cursor-pointer bg-gray-50 px-4 py-2 rounded-lg border border-gray-200">
                    <input type="checkbox" id="stationActive" name="is_active" value="1" checked
                        class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm font-semibold text-gray-700">Active</span>
                </label>

                <button type="submit"
                    class="w-full py-3 bg-blue-500 text-white font-bold rounded-lg shadow-md hover:bg-blue-600 active:scale-95 transition flex justify-center items-center gap-2">
                    <i class="fas fa-check"></i> <span id="stationSubmitLabel">Save Station</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('stationModal');
            const form = document.getElementById('stationForm');
            const postAction = "{{ route('poststation') }}";

            function openModal() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
            function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); }

            document.getElementById('addStationBtn').addEventListener('click', function () {
                document.getElementById('stationModalTitle').innerHTML =
                    '<i class="fas fa-utensils text-blue-500 text-3xl"></i> Add Station';
                document.getElementById('stationSubmitLabel').innerText = 'Save Station';
                form.setAttribute('action', postAction);
                document.getElementById('stationMethod').value = 'post';
                document.getElementById('stationName').value = '';
                document.getElementById('stationActive').checked = true;
                openModal();
            });

            document.querySelectorAll('.editStationBtn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('stationModalTitle').innerHTML =
                        '<i class="fas fa-utensils text-blue-500 text-3xl"></i> Edit Station';
                    document.getElementById('stationSubmitLabel').innerText = 'Update Station';
                    form.setAttribute('action', `/station/${btn.dataset.id}/update`);
                    document.getElementById('stationMethod').value = 'put';
                    document.getElementById('stationName').value = btn.dataset.name;
                    document.getElementById('stationActive').checked = String(btn.dataset.is_active) === '1';
                    openModal();
                });
            });

            document.getElementById('closeStationModal').addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

            document.querySelectorAll('.stationDeleteBtn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const f = btn.closest('form');
                    Swal.fire({
                        title: 'Hapus station?',
                        text: 'Menu yang memakainya akan kehilangan station.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ef4444',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal'
                    }).then(r => { if (r.isConfirmed) f.submit(); });
                });
            });
        })();
    </script>

    @include('layout.loading')
</body>

</html>