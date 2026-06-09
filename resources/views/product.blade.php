<!DOCTYPE html>
<html lang="en">

<head>
    <title>Products</title>
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
                        <i class="fa-solid fa-clipboard-list text-green-600 text-4xl"></i> Product Management
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Organize and manage your products</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('ingridient') }}"
                        class="px-6 py-3 text-base bg-yellow-500 text-white rounded-lg shadow-md hover:bg-yellow-600 transition font-bold flex items-center gap-2">
                        <i class="fa fa-wrench"></i> Set Ingridients
                    </a>
                    <x-button id="addBtn" size="lg" variant="green" icon="plus">Add</x-button>
                </div>
            </div>

            @if ($category->isEmpty())
                <!-- Empty State (no categories at all) -->
                <div class="w-full bg-white rounded-xl shadow-md border border-gray-100">
                    <div class="p-12 text-center">
                        <div class="flex flex-col items-center justify-center opacity-70">
                            <div
                                class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mb-4 border border-green-100">
                                <i class="fas fa-inbox text-4xl text-green-300"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900">No products available</h3>
                            <p class="text-sm text-gray-500 mt-1 mb-6">Add products to start managing your catalog.</p>
                            <button id="emptyAddBtn"
                                class="px-6 py-3 bg-green-500 text-white rounded-lg shadow-md hover:bg-green-600 hover:scale-105 transition font-bold flex items-center gap-2 text-base">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        </div>
                    </div>
                </div>
            @else
                @foreach ($category as $cat)
                    <!-- Category Card -->
                    <div class="w-full bg-white rounded-xl shadow-md border border-gray-100">
                        <!-- Card Header -->
                        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 rounded-t-xl">
                            <h2 class="font-bold text-gray-800 flex items-center gap-2 text-base">
                                <i class="fas fa-folder text-red-500"></i> {{ $cat->name }}
                                <span
                                    class="ml-1 bg-red-50 text-red-700 text-xs px-3 py-1 rounded-full font-bold border border-red-200">
                                    {{ $cat->menus->count() }} Items
                                </span>
                            </h2>
                        </div>

                        @if ($cat->menus->count() > 0)
                            <!-- Table -->
                            <div class="p-5 overflow-auto">
                                <table class="categoryTable w-full text-left" data-category-id="{{ $cat->id }}">
                                    <thead class="bg-gray-100 text-gray-600 text-sm leading-normal">
                                        <tr>
                                            <th class="p-4 font-bold rounded-tl-lg" width="5%">
                                                <div class="flex items-center justify-center">No</div>
                                            </th>
                                            <th class="p-4 font-bold">Name</th>
                                            <th class="p-4 font-bold">Price</th>
                                            <th class="p-4 font-bold rounded-tr-lg" width="15%">
                                                <div class="flex items-center justify-center">Action</div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-700 text-sm divide-y divide-gray-200">
                                        @php $no = 1; @endphp
                                        @foreach ($cat->menus as $menu)
                                            <tr class="hover:bg-gray-50 transition duration-150">
                                                <td class="p-4 font-medium text-center">{{ $no++ }}</td>

                                                <td class="p-4 space-y-2">
                                                    <div class="font-bold text-gray-900 text-base group-hover:text-cyan-600">
                                                        {{ $menu->name }}
                                                    </div>
                                                    <div class="text-xs text-gray-400">Created:
                                                        {{ $menu->created_at ? $menu->created_at->format('Y-m-d') : '-' }}
                                                    </div>
                                                    @if ($menu->has_variety && !empty($menu->varieties))
                                                        <span
                                                            class="inline-block mt-1 text-[10px] font-bold uppercase text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-200"
                                                            title="{{ implode(', ', array_map(fn($v) => ucwords(str_replace('_', ' ', $v)), $menu->varieties)) }}">
                                                            <i class="fas fa-layer-group mr-1"></i>
                                                            {{ count($menu->varieties) }} Variety
                                                        </span>
                                                    @endif
                                                </td>

                                                <td class="p-4">
                                                    <span class="font-mono font-bold text-gray-800">
                                                        Rp {{ number_format($menu->price, 0, ',', '.') }}
                                                    </span>
                                                </td>

                                                <td class="p-4">
                                                    <div class="flex justify-center items-center gap-2">
                                                        <button
                                                            class="editBtn w-10 h-10 flex items-center justify-center bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 hover:scale-105 transition"
                                                            data-id="{{ $menu->id }}" data-name="{{ $menu->name }}"
                                                            data-price="{{ (int) $menu->price }}"
                                                            data-category_id="{{ $menu->category_id }}"
                                                            data-station_id="{{ $menu->station_id }}"
                                                            data-desc="{{ $menu->description }}"
                                                            data-has_variety="{{ $menu->has_variety ? 1 : 0 }}"
                                                            data-varieties='@json($menu->varieties ?? [])' title="Edit">
                                                            <i class="fas fa-edit text-lg"></i>
                                                        </button>

                                                        <form method="post" action="{{ route('delproduct', ['id' => $menu->id]) }}"
                                                            class="inline deleteForm">
                                                            @csrf
                                                            @method('delete')
                                                            <button type="button"
                                                                class="delete-confirm w-10 h-10 flex items-center justify-center bg-red-500 text-white rounded-lg shadow hover:bg-red-600 hover:scale-105 transition"
                                                                title="Delete">
                                                                <i class="fas fa-trash text-lg"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <!-- Empty State per Category -->
                            <div class="p-8 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p class="text-sm">No menu in this category.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif

        </div>
    </main>

    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="//cdn.datatables.net/2.0.2/js/dataTables.min.js"></script>
    <script src="{{ asset('modal/prod.js') }}"></script>

    <!-- Modals -->
    @include('modal.addProd')
    @include('modal.editProd')

    @include('sweetalert::alert')
    @include('layout.loading')

</body>

</html>