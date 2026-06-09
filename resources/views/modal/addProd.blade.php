<!-- ADD MODAL -->
<div id="addModal" role="dialog" aria-labelledby="addModalTitle" aria-hidden="true"
    class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-50 overflow-y-auto px-4 py-6">
    <div class="bg-white rounded-2xl p-8 w-full max-w-lg shadow-2xl relative transform transition-all scale-100">
        <button id="closeAddModal" aria-label="Close dialog"
            class="absolute top-5 right-5 text-gray-400 hover:text-gray-600 transition focus:outline-none focus:ring-2 focus:ring-red-500 rounded">
            <i class="fas fa-times text-xl"></i>
        </button>

        <h2 id="addModalTitle" class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-list text-green-600 text-4xl"></i> Add Product
        </h2>

        <form id="addForm" method="post" action="{{ route('postproduct') }}" enctype="multipart/form-data"
            class="space-y-5" novalidate>
            @csrf @method('post')

            <div>
                <label for="addName" class="block text-sm font-semibold text-gray-700 mb-2">
                    Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="addName" name="name"
                    class="form-input w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                    placeholder="Enter product name" required minlength="3" maxlength="100" aria-required="true">
                <div class="error-message hidden" id="addNameError"></div>
            </div>

            <div>
                <label for="addPrice" class="block text-sm font-semibold text-gray-700 mb-2">
                    Price <span class="text-red-500">*</span>
                </label>
                <input type="text" id="addPrice" name="price"
                    class="form-input w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                    placeholder="Rp 0" required aria-required="true">
                <div class="error-message hidden" id="addPriceError"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="addCategory" class="block text-sm font-semibold text-gray-700 mb-2">
                        Category <span class="text-red-500">*</span>
                    </label>
                    <select id="addCategory" name="category_id"
                        class="form-input w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                        required aria-required="true">
                        <option value="">Select Category</option>
                        @foreach ($category as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <div class="error-message hidden" id="addCategoryError"></div>
                </div>

                @include('modal.varietyToggle', ['prefix' => 'add', 'accent' => 'red'])
            </div>

            @include('modal.varietyChips', ['prefix' => 'add', 'accent' => 'red'])

            @if (isset($stations) && $stations->isNotEmpty())
                <div>
                    <label for="addStation" class="block text-sm font-semibold text-gray-700 mb-2">Station</label>
                    <select id="addStation" name="station_id"
                        class="form-input w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                        <option value="">No Station</option>
                        @foreach ($stations as $st)
                            <option value="{{ $st->id }}">{{ $st->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Where this item is prepared (for order checkers).</p>
                </div>
            @endif

            <div>
                <label for="addImage" class="block text-sm font-semibold text-gray-700 mb-2">
                    Image <span class="text-red-500">*</span>
                </label>
                <input type="file" id="addImage" name="img"
                    class="form-input w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                    required aria-required="true">
                <div class="error-message hidden" id="addImageError"></div>
            </div>

            <div>
                <label for="addDesc" class="block text-sm font-semibold text-gray-700 mb-2">
                    Description <span class="text-red-500">*</span>
                </label>
                <textarea id="addDesc" name="description"
                    class="form-input w-full rounded-lg border-gray-300 shadow-sm p-2.5 border focus:ring-2 focus:ring-red-500 focus:border-transparent transition resize-none"
                    placeholder="Enter product description" rows="4" required minlength="5" maxlength="500"
                    aria-required="true"></textarea>
                <div class="text-gray-500 text-xs mt-1">
                    <span id="addDescCount">0</span>/500 characters
                </div>
                <div class="error-message hidden" id="addDescError"></div>
            </div>

            <button type="submit"
                class="w-full py-3 bg-green-500 text-white font-bold rounded-lg shadow-md hover:bg-green-600 active:scale-95 transition flex justify-center items-center gap-2 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                <i class="fas fa-check"></i> Save Product
            </button>
        </form>
    </div>
</div>