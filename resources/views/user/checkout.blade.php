<!DOCTYPE html>
<html lang="en">

<head>
    <title>Checkout</title>
    @include('user.layout.head')
    @if (! empty($snapToken))
        <script type="text/javascript" src="https://app.midtrans.com/snap/snap.js"
            data-client-key="{{ config('midtrans.client_key') }}"></script>
    @endif
</head>

<body class="font-poppins bg-gray-50">
    <div class='w-full sm:max-w-sm mx-auto min-h-screen'>

        {{-- NAVBAR --}}
        <div class="fixed top-0 left-0 right-0 z-50 w-full sm:max-w-sm mx-auto">
            <div class="bg-white shadow-lg rounded-b-[22px] px-4 py-4 flex items-center gap-2">
                <a href="{{ route('user-payment') }}" class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center text-gray-700">
                    <span class="material-icons text-lg">arrow_back</span>
                </a>
                <div class="flex-1 text-center">
                    <h1 class="text-base font-semibold text-gray-900">Checkout</h1>
                </div>
                <a href="{{ route('user-home') }}" class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center text-gray-700">
                    <span class="material-icons text-lg">home</span>
                </a>
            </div>
        </div>

        <div class="h-16"></div>

        {{-- BODY --}}
        <div class="px-4 pt-3 pb-32 space-y-4">

            {{-- Order ID card --}}
            <div class="rounded-2xl border border-red-100 p-4 shadow-sm" style="background: linear-gradient(180deg, #FEF2F2, #FFFFFF);">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Order ID</p>
                        <p class="font-mono text-base font-bold text-gray-900 mt-1">{{ $order->no_order }}</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-50 text-red-800">
                        Menunggu Pembayaran
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-white border border-gray-100 flex items-center justify-center shrink-0">
                            <span class="material-icons text-gray-600 text-sm">person</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Nama</p>
                            <p class="text-xs font-semibold text-gray-900 truncate">{{ $order->atas_nama }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-white border border-gray-100 flex items-center justify-center shrink-0">
                            <span class="material-icons text-gray-600 text-sm">smartphone</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">HP</p>
                            <p class="text-xs font-semibold text-gray-900 truncate">{{ $order->no_telpon }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-white border border-gray-100 flex items-center justify-center shrink-0">
                            <span class="material-icons text-gray-600 text-sm">table_restaurant</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Meja</p>
                            <p class="text-xs font-semibold text-gray-900 truncate">{{ $order->alamat }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-white border border-gray-100 flex items-center justify-center shrink-0">
                            <span class="material-icons text-gray-600 text-sm">storefront</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Cabang</p>
                            <p class="text-xs font-semibold text-gray-900 truncate">{{ $order->cabang }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Order summary --}}
            <div>
                <h3 class="text-sm font-bold text-gray-900 mb-2">Pesanan</h3>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 space-y-1.5">
                    @foreach ($order->cart->cartMenus as $item)
                        <div class="flex justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-900 truncate">
                                    <span class="font-bold">{{ $item->quantity }}×</span> {{ $item->menu->name }}
                                    @if ($item->variety && $item->variety !== 'normal')
                                        <span class="text-gray-400"> · {{ ucwords(str_replace('_', ' ', $item->variety)) }}</span>
                                    @endif
                                </p>
                                @if ($item->notes)
                                    <p class="text-[10px] text-gray-500 italic truncate">"{{ $item->notes }}"</p>
                                @endif
                            </div>
                            <span class="text-xs font-semibold text-gray-900 shrink-0">
                                Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach

                    <div class="border-t border-gray-100 pt-2 mt-2 flex justify-between items-baseline">
                        <span class="text-sm font-bold text-gray-900">Total</span>
                        <span class="text-lg font-extrabold text-gray-900">
                            Rp {{ number_format($order->cart->total_amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- STICKY FOOTER --}}
        <div class="fixed bottom-0 left-0 right-0 w-full sm:max-w-sm mx-auto z-50 bg-white border-t border-gray-100 shadow-[0_-8px_18px_rgba(0,0,0,0.04)]">
            <div class="p-3">
                <button id="pay-button" type="button" {{ empty($snapToken) ? 'disabled' : '' }}
                    class="w-full h-12 rounded-xl bg-red-800 text-white font-bold flex items-center justify-center gap-2 active:bg-red-900 {{ empty($snapToken) ? 'opacity-50 cursor-not-allowed' : '' }}">
                    <span id="pay-button-text">Lanjut · Rp {{ number_format($order->cart->total_amount, 0, ',', '.') }}</span>
                    <span class="material-icons text-base">arrow_forward</span>
                </button>
                <p class="text-[10px] text-gray-400 text-center mt-2">Powered by Midtrans · Pembayaran aman</p>
            </div>
        </div>
    </div>

    @if (! empty($snapToken))
        <script type="text/javascript">
            window.history.pushState(null, null, window.location.href);
            window.onpopstate = function () { window.history.pushState(null, null, window.location.href); };
            window.addEventListener('beforeunload', function (event) {
                event.preventDefault();
                event.returnValue = '';
            });

            var payButton = document.getElementById('pay-button');
            var buttonText = document.getElementById('pay-button-text');

            payButton.addEventListener('click', function (event) {
                event.preventDefault();
                payButton.disabled = true;
                buttonText.textContent = 'Processing...';
                payButton.classList.add('animate-pulse');

                window.snap.pay('{{ $snapToken }}', {
                    onSuccess: function () { window.location.href = '{{ route('user-antrian') }}'; },
                    onPending: function () { window.location.href = '{{ route('user-antrian') }}'; },
                    onError:   function (result) { console.error('Payment failed', result); resetButton(); },
                    onClose:   function () { resetButton(); }
                });
            });

            function resetButton() {
                payButton.disabled = false;
                buttonText.textContent = 'Lanjut · Rp {{ number_format($order->cart->total_amount, 0, ',', '.') }}';
                payButton.classList.remove('animate-pulse');
            }
        </script>
    @endif
</body>

</html>
