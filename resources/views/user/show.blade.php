<!DOCTYPE html>
<html lang="en">

<head>
    <title>Detail Menu</title>
    @include('user.layout.head')
</head>

<body class="font-poppins bg-gray-50">
    <div class='w-full sm:max-w-sm mx-auto min-h-screen'>
        <form action="{{ route('user-postcart') }}" method="post">
            @csrf

            {{-- NAVBAR --}}
            <div class="fixed top-0 left-0 right-0 z-50 w-full sm:max-w-sm mx-auto">
                <div class="bg-white shadow-lg rounded-b-[22px] px-4 py-4 flex items-center gap-2">
                    <a href="{{ route('user-product') }}" class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center text-gray-700">
                        <span class="material-icons text-lg">arrow_back</span>
                    </a>
                    <div class="flex-1 text-center">
                        <h1 class="text-base font-semibold text-gray-900">Detail Menu</h1>
                    </div>
                    <a href="{{ route('user-home') }}" class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center text-gray-700">
                        <span class="material-icons text-lg">home</span>
                    </a>
                </div>
            </div>

            <div class="h-16"></div>

            {{-- BODY --}}
            <div class="px-4 pt-2 pb-32">
                <input type="hidden" name="menu_id" value="{{ $menu->id }}">
                <input type="hidden" name="quantity" id="quantityInput" value="1">

                {{-- Hero image --}}
                <div class="mx-auto w-full max-w-[280px] aspect-square rounded-3xl bg-gradient-to-br from-red-50 to-red-100 border border-red-100 shadow-md overflow-hidden flex items-center justify-center">
                    <img src="{{ asset('storage/img/' . basename($menu->img)) }}" alt="{{ $menu->name }}"
                        class="w-3/4 h-3/4 object-contain" />
                </div>

                {{-- Title + price --}}
                <div class="mt-5">
                    <p class="text-[11px] uppercase tracking-widest font-bold text-gray-400">
                        {{ $menu->category->name ?? 'Menu' }}
                    </p>
                    <div class="flex items-end justify-between gap-3 mt-1">
                        <h1 class="text-2xl font-extrabold text-gray-900 leading-tight">{{ $menu->name }}</h1>
                        <h2 class="text-xl font-bold text-red-800 shrink-0">
                            Rp {{ number_format($menu->price, 0, ',', '.') }}
                        </h2>
                    </div>
                    @if ($menu->description)
                        <p class="text-sm text-gray-600 leading-relaxed mt-2">{{ $menu->description }}</p>
                    @endif
                </div>

                {{-- Variety --}}
                @if ($menu->has_variety && ! empty($menu->varieties))
                    <div class="mt-5 space-y-2">
                        <p class='text-xs font-bold text-gray-600'><span class="text-red-500">*</span> Varian</p>
                        <input type="hidden" name="variety" id="varietyInput" value="{{ $menu->varieties[0] }}">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($menu->varieties as $i => $v)
                                <button type="button" data-variety="{{ $v }}"
                                    class="varietyChip px-4 py-2 rounded-full border text-xs font-semibold transition
                                    {{ $i === 0 ? 'bg-red-50 text-red-800 border-red-800' : 'bg-white text-gray-700 border-gray-200' }}">
                                    {{ ucwords(str_replace('_', ' ', $v)) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Discount --}}
                <div class="mt-5 space-y-2">
                    <p class='text-xs font-bold text-gray-600'>Diskon <span class="text-gray-400 font-normal">· opsional</span></p>
                    <div class="flex flex-wrap gap-1.5">
                        <input type="hidden" name="discount_id" id="discountInput" value="">
                        <button type="button" data-discount=""
                            class="discountChip px-4 py-2 rounded-full border text-xs font-semibold bg-red-50 text-red-800 border-red-800">
                            Tanpa Diskon
                        </button>
                        @foreach ($discount as $d)
                            <button type="button" data-discount="{{ $d->id }}"
                                class="discountChip px-4 py-2 rounded-full border text-xs font-semibold bg-white text-gray-700 border-gray-200">
                                {{ $d->name }}
                                <span class="opacity-60">{{ $d->percentage }}%</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mt-5 space-y-2">
                    <p class="text-xs font-bold text-gray-600">Catatan <span class="text-gray-400 font-normal">· opsional</span></p>
                    <textarea class="w-full border border-gray-200 bg-white p-3 rounded-xl text-sm resize-none" name="notes" id="notes" rows="2"
                        placeholder="Mis: less ice, extra hot, no straw">{{ $menu->notes }}</textarea>
                </div>
            </div>

            {{-- STICKY FOOTER --}}
            <div class="fixed bottom-0 left-0 right-0 w-full sm:max-w-sm mx-auto z-50 bg-white border-t border-gray-100 shadow-[0_-8px_18px_rgba(0,0,0,0.04)]">
                <div class="p-3 flex items-center gap-3">
                    {{-- Qty stepper --}}
                    <div class="inline-flex items-center rounded-full border border-gray-200 bg-white h-12">
                        <button type="button" onclick="decrement()"
                            class="w-10 h-12 flex items-center justify-center text-gray-700">
                            <span class="material-icons text-lg">remove</span>
                        </button>
                        <div id="quantityDisplay" class="min-w-[28px] text-center font-bold text-base">1</div>
                        <button type="button" onclick="increment()"
                            class="w-10 h-12 flex items-center justify-center text-gray-700">
                            <span class="material-icons text-lg">add</span>
                        </button>
                    </div>
                    <button type="submit"
                        class="ml-auto h-12 rounded-xl bg-red-800 text-white font-bold flex items-center justify-center px-5 gap-2 active:bg-red-900">
                        <span class="material-icons text-base">add_shopping_cart</span>
                        <span id="totalLabel" class="font-bold">Rp {{ number_format($menu->price, 0, ',', '.') }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        var basePrice = {{ $menu->price }};
        var currentDiscount = 0;

        function fmt(n) {
            return 'Rp ' + Math.round(n).toLocaleString('id-ID');
        }

        function recalc() {
            var q = parseInt(document.getElementById('quantityInput').value) || 1;
            var subtotal = basePrice * q;
            if (currentDiscount > 0) subtotal = subtotal * (1 - currentDiscount / 100);
            document.getElementById('totalLabel').innerText = fmt(subtotal);
        }

        function increment() {
            var d = document.getElementById('quantityDisplay');
            var i = document.getElementById('quantityInput');
            var q = parseInt(d.innerText) + 1;
            d.innerText = q;
            i.value = q;
            recalc();
        }

        function decrement() {
            var d = document.getElementById('quantityDisplay');
            var i = document.getElementById('quantityInput');
            var q = parseInt(d.innerText);
            if (q > 1) { q--; d.innerText = q; i.value = q; recalc(); }
        }

        // Variety chips
        document.querySelectorAll('.varietyChip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.varietyChip').forEach(function (b) {
                    b.classList.remove('bg-red-50', 'text-red-800', 'border-red-800');
                    b.classList.add('bg-white', 'text-gray-700', 'border-gray-200');
                });
                btn.classList.remove('bg-white', 'text-gray-700', 'border-gray-200');
                btn.classList.add('bg-red-50', 'text-red-800', 'border-red-800');
                document.getElementById('varietyInput').value = btn.dataset.variety;
            });
        });

        // Discount chips
        var discountMap = {
            @foreach ($discount as $d)
                '{{ $d->id }}': {{ $d->percentage }},
            @endforeach
        };
        document.querySelectorAll('.discountChip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.discountChip').forEach(function (b) {
                    b.classList.remove('bg-red-50', 'text-red-800', 'border-red-800');
                    b.classList.add('bg-white', 'text-gray-700', 'border-gray-200');
                });
                btn.classList.remove('bg-white', 'text-gray-700', 'border-gray-200');
                btn.classList.add('bg-red-50', 'text-red-800', 'border-red-800');
                var d = btn.dataset.discount;
                document.getElementById('discountInput').value = d;
                currentDiscount = d ? (discountMap[d] || 0) : 0;
                recalc();
            });
        });
    </script>
</body>

</html>
