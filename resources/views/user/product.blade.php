<!DOCTYPE html>
<html lang="en">

<head>
    <title>Menu</title>
    @include('user.layout.head')
    <style>
        .hscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
        .hscroll::-webkit-scrollbar { display: none; }
    </style>
</head>

<body class="font-poppins bg-gray-50">
    <div class='w-full sm:max-w-sm mx-auto min-h-screen'>

        {{-- NAVBAR --}}
        <div id="topNav" class="fixed top-0 left-0 right-0 z-50 w-full sm:max-w-sm mx-auto">
            <div class="bg-white shadow-lg rounded-b-[22px]">
                {{-- Header row --}}
                <div class="px-4 pt-4 pb-3 flex items-center gap-2">
                    <a href="{{ route('user-home') }}" class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center text-gray-700">
                        <span class="material-icons text-lg">arrow_back</span>
                    </a>
                    <div class="flex-1 text-center">
                        <h1 class="text-base font-semibold text-gray-900">Menu</h1>
                        <p class="text-[10px] text-gray-400">Meja {{ auth()->user()->name ?? '-' }}</p>
                    </div>
                    <a href="{{ route('user-cart') }}" class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center text-gray-700 relative">
                        <span class="material-icons text-lg">shopping_cart</span>
                        @if ($cart && $cart->cartMenus->count() > 0)
                            <span class="absolute -top-1 -right-1 bg-red-800 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center">{{ $cart->cartMenus->count() }}</span>
                        @endif
                    </a>
                </div>

                {{-- Step indicator --}}
                <div class="px-5 pb-3 flex items-center gap-2">
                    @php
                        $steps = [
                            ['n' => 1, 'label' => 'Menu',  'active' => true,  'done' => false],
                            ['n' => 2, 'label' => 'Cart',  'active' => false, 'done' => false],
                            ['n' => 3, 'label' => 'Bayar', 'active' => false, 'done' => false],
                        ];
                    @endphp
                    @foreach ($steps as $i => $s)
                        <div class="flex items-center gap-1.5 shrink-0">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold
                                {{ $s['active'] || $s['done'] ? 'bg-red-800 text-white' : 'bg-gray-200 text-gray-400' }}">
                                @if ($s['done'])
                                    <span class="material-icons text-sm">check</span>
                                @else
                                    {{ $s['n'] }}
                                @endif
                            </div>
                            <span class="text-xs {{ $s['active'] ? 'font-bold text-gray-900' : 'font-semibold text-gray-400' }}">{{ $s['label'] }}</span>
                        </div>
                        @if ($i < count($steps) - 1)
                            <div class="flex-1 h-0.5 rounded-full {{ $s['done'] ? 'bg-red-800' : 'bg-gray-200' }}"></div>
                        @endif
                    @endforeach
                </div>

                {{-- Search --}}
                <div class="px-4 pb-3">
                    <div class="h-11 rounded-xl bg-gray-50 border border-gray-200 flex items-center px-3 gap-2">
                        <span class="material-icons text-gray-400 text-lg">search</span>
                        <input id="menu-search" type="text" placeholder="Cari menu (matcha, croissant...)"
                            class="flex-1 bg-transparent outline-none text-sm text-gray-800">
                    </div>
                </div>

                {{-- Category chips --}}
                <div class="hscroll pb-3">
                    <div class="inline-flex gap-1.5 px-4">
                        <button data-category="all" class="categoryChip px-3.5 py-1.5 rounded-full border text-xs font-semibold whitespace-nowrap bg-gray-900 text-white border-gray-900">
                            Semua
                        </button>
                        @foreach ($category as $cat)
                            <button data-category="{{ $cat->id }}" class="categoryChip px-3.5 py-1.5 rounded-full border text-xs font-semibold whitespace-nowrap bg-white text-gray-700 border-gray-200">
                                {{ $cat->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div id="navSpacer" class="h-[180px]"></div>

        {{-- BODY --}}
        <div class="px-3 pb-32 space-y-5">
            @foreach ($category as $item)
                <div class="categorySection" data-category="{{ $item->id }}">
                    <h2 class="px-1 text-base font-bold text-gray-900 mb-2">{{ $item->name }}</h2>
                    <div class="grid grid-cols-2 gap-2.5">
                        @foreach ($item->menus as $menu)
                            <a href="{{ route('user-show', ['id' => $menu->id]) }}"
                               class="menuCard bg-white border border-gray-100 rounded-2xl p-2 shadow-sm active:scale-95 transition"
                               data-name="{{ strtolower($menu->name) }}">
                                <div class="aspect-square w-full rounded-xl bg-gray-50 overflow-hidden mb-2">
                                    <img src="{{ asset('storage/img/' . basename($menu->img)) }}" alt="{{ $menu->name }}" class="w-full h-full object-cover">
                                </div>
                                <div class="px-1 pb-1">
                                    <h3 class="text-sm font-semibold text-gray-900 line-clamp-1">{{ $menu->name }}</h3>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-sm font-bold text-red-800">Rp {{ number_format($menu->price, 0, ',', '.') }}</span>
                                        <span class="w-6 h-6 rounded-lg bg-red-800 text-white flex items-center justify-center">
                                            <span class="material-icons text-sm">add</span>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- STICKY CART FOOTER --}}
        <div class="fixed bottom-0 left-0 right-0 w-full sm:max-w-sm mx-auto z-50 bg-white border-t border-gray-100 shadow-[0_-8px_18px_rgba(0,0,0,0.04)]">
            <div class="p-3 flex items-center gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">
                        Keranjang · {{ $cart->cartMenus->count() ?? 0 }} item
                    </p>
                    <p class="text-lg font-bold text-gray-900">Rp {{ number_format($cart->total_amount ?? 0, 0, ',', '.') }}</p>
                </div>
                @if ($cart && $cart->total_amount > 0)
                    <a href="{{ route('user-cart') }}" class="px-5 py-3 bg-red-800 text-white font-bold rounded-xl flex items-center gap-1.5">
                        Lihat Cart
                        <span class="material-icons text-base">arrow_forward</span>
                    </a>
                @else
                    <div class="px-5 py-3 bg-gray-300 text-white font-bold rounded-xl flex items-center gap-1.5 cursor-not-allowed">
                        Lihat Cart
                        <span class="material-icons text-base">arrow_forward</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>

        (function () {
            var nav = document.getElementById('topNav');
            var spacer = document.getElementById('navSpacer');
            function syncSpacer() {
                if (nav && spacer) {
                    spacer.style.height = nav.offsetHeight + 'px';
                }
            }
            syncSpacer();
            window.addEventListener('load', syncSpacer);
            window.addEventListener('resize', syncSpacer);
        })();

        // Category filter
        document.querySelectorAll('.categoryChip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.categoryChip').forEach(function (b) {
                    b.classList.remove('bg-gray-900', 'text-white', 'border-gray-900');
                    b.classList.add('bg-white', 'text-gray-700', 'border-gray-200');
                });
                btn.classList.remove('bg-white', 'text-gray-700', 'border-gray-200');
                btn.classList.add('bg-gray-900', 'text-white', 'border-gray-900');

                var cat = btn.dataset.category;
                document.querySelectorAll('.categorySection').forEach(function (sec) {
                    sec.style.display = (cat === 'all' || sec.dataset.category === cat) ? '' : 'none';
                });
            });
        });

        // Search filter
        document.getElementById('menu-search').addEventListener('input', function (e) {
            var q = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.menuCard').forEach(function (card) {
                card.style.display = (!q || card.dataset.name.includes(q)) ? '' : 'none';
            });
        });
    </script>
</body>

</html>
