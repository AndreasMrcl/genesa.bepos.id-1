$(document).ready(function () {
    // ========== DataTable Initialization ==========
    $('.categoryTable').each(function () {
        new DataTable($(this), {});
    });

    // ========== Modal open/close ==========
    $('#addBtn, #emptyAddBtn').click(() => {
        resetVariety('add');
        $('#addModal').removeClass('hidden');
    });
    $('#closeAddModal').click(() => $('#addModal').addClass('hidden'));
    $('#closeModal').click(() => $('#editModal').addClass('hidden'));

    // ========== Open edit modal ==========
    $(document).on('click', '.editBtn', function () {
        const btn = $(this);
        $('#editName').val(btn.data('name'));
        $('#editPriceInput').val(formatRupiah(btn.data('price')));
        $('#editCategory').val(btn.data('category_id'));
        $('#editStation').val(btn.data('station_id') || '');
        $('#editDesc').val(btn.data('desc'));
        $('#editForm').attr('action', `/product/${btn.data('id')}/update`);

        const hasVariety = String(btn.data('has_variety')) === '1';
        const varieties = btn.data('varieties') || [];
        resetVariety('edit');
        $('#editHasVariety').prop('checked', hasVariety);
        toggleVarietySection('edit', hasVariety);
        if (hasVariety && Array.isArray(varieties)) {
            varieties.forEach(v => addChip('edit', labelize(v)));
        }

        $('#editModal').removeClass('hidden');
    });

    // ========== Delete confirmation ==========
    $(document).on('click', '.delete-confirm', function (e) {
        e.preventDefault();
        const form = $(this).closest('form');
        Swal.fire({
            title: 'Hapus?',
            text: 'Data akan dihapus permanen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(result => result.isConfirmed && form.submit());
    });

    // ========== Rupiah Formatting ==========
    function formatRupiah(value) {
        // Parse database value (10000.00) - remove decimal part only
        const number = parseInt(String(value).split('.')[0]) || 0;
        return number.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
    }

    function parseRupiah(value) {
        let cleaned = String(value);
        // Remove 'Rp' prefix and spaces
        cleaned = cleaned.replace(/Rp\s?/i, '');
        // Remove thousands separator (.) in Indonesian format
        cleaned = cleaned.replace(/\./g, '');
        // Remove decimal separator (,) and decimals in Indonesian format
        cleaned = cleaned.replace(/,\d+$/, '');
        // Remove any remaining non-numeric characters
        cleaned = cleaned.replace(/[^0-9]/g, '');
        return cleaned;
    }


    $('#addPrice, #editPriceInput').on('focus', function () {
        $(this).val(parseRupiah($(this).val()));
    });

    $('#addPrice, #editPriceInput').on('input', function () {
        const digits = parseRupiah($(this).val());
        $(this).val(digits ? Number(digits).toLocaleString('id-ID') : '');
    });

    $('#addPrice, #editPriceInput').on('blur', function () {
        const numericValue = parseRupiah($(this).val());
        $(this).val(formatRupiah(numericValue));
    });

    // ========== Variety chip-input ==========
    const labelize = (slug) => String(slug).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    const slugify = (label) => String(label).trim().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');

    function resetVariety(prefix) {
        $(`#${prefix}HasVariety`).prop('checked', false);
        $(`#${prefix}VarietyChips`).find('.varietyChip').remove();
        $(`#${prefix}VarietyInput`).val('');
        toggleVarietySection(prefix, false);
        hideError(`${prefix}VarietyError`);
    }

    function toggleVarietySection(prefix, show) {
        $(`#${prefix}VarietySection`).toggleClass('hidden', !show);
    }

    function getChipSlugs(prefix) {
        return $(`#${prefix}VarietyChips`).find('.varietyChip').map(function () {
            return $(this).data('slug');
        }).get();
    }

    function addChip(prefix, rawLabel) {
        const label = String(rawLabel).trim();
        if (!label) return false;
        const slug = slugify(label);
        if (!slug) return false;

        const existing = getChipSlugs(prefix);
        if (existing.includes(slug)) {
            flashInput(prefix);
            return false;
        }

        const $chip = $(`
            <span class="varietyChip inline-flex items-center gap-1 bg-red-50 text-red-700 text-sm font-semibold px-2.5 py-1 rounded-full" data-slug="${slug}">
                ${labelize(slug)}
                <button type="button" class="varietyChipRemove text-red-500 hover:text-red-700" aria-label="Hapus">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </span>
        `);
        $chip.insertBefore(`#${prefix}VarietyInput`);
        return true;
    }

    function flashInput(prefix) {
        const $i = $(`#${prefix}VarietyInput`);
        $i.addClass('text-red-500');
        setTimeout(() => $i.removeClass('text-red-500'), 400);
    }

    // toggle has_variety
    $('#addHasVariety, #editHasVariety').on('change', function () {
        const prefix = this.id === 'addHasVariety' ? 'add' : 'edit';
        toggleVarietySection(prefix, $(this).is(':checked'));
        if ($(this).is(':checked')) {
            $(`#${prefix}VarietyInput`).focus();
        }
    });

    // ketik chip
    $('#addVarietyInput, #editVarietyInput').on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const prefix = this.id === 'addVarietyInput' ? 'add' : 'edit';
            const val = $(this).val();
            if (addChip(prefix, val)) $(this).val('');
        } else if (e.key === 'Backspace' && !$(this).val()) {
            const prefix = this.id === 'addVarietyInput' ? 'add' : 'edit';
            $(`#${prefix}VarietyChips`).find('.varietyChip').last().remove();
        }
    });

    // hapus chip
    $(document).on('click', '.varietyChipRemove', function () {
        $(this).closest('.varietyChip').remove();
    });

    // klik area chip → fokus input
    $('#addVarietyChips, #editVarietyChips').on('click', function (e) {
        if ($(e.target).is(this) || $(e.target).is('.varietyChip')) {
            $(this).find('.varietyChipInput').focus();
        }
    });

    function showError(id, msg) {
        $(`#${id}`).removeClass('hidden').text(msg).addClass('text-red-500 text-xs mt-1');
    }
    function hideError(id) {
        $(`#${id}`).addClass('hidden').text('');
    }

    // ========== Form Submit ==========
    function injectVarietyInputs($form, prefix) {
        $form.find('.varietyHidden').remove();
        if (!$(`#${prefix}HasVariety`).is(':checked')) return true;

        const slugs = getChipSlugs(prefix);
        if (slugs.length < 2) {
            showError(`${prefix}VarietyError`, 'Minimal 2 variety. Tambahkan dulu sebelum submit.');
            return false;
        }
        hideError(`${prefix}VarietyError`);
        slugs.forEach(s => {
            $form.append(`<input type="hidden" class="varietyHidden" name="varieties[]" value="${s}">`);
        });
        return true;
    }

    $('#addForm').on('submit', function (e) {
        $('#addPrice').val(parseRupiah($('#addPrice').val()));
        if (!injectVarietyInputs($(this), 'add')) e.preventDefault();
    });

    $('#editForm').on('submit', function (e) {
        $('#editPriceInput').val(parseRupiah($('#editPriceInput').val()));
        if (!injectVarietyInputs($(this), 'edit')) e.preventDefault();
    });

    // Close modals on backdrop click
    $(window).click(e => {
        if (e.target.id === 'addModal') $('#addModal').addClass('hidden');
        if (e.target.id === 'editModal') $('#editModal').addClass('hidden');
    });

    // Close on Escape
    $(document).on('keydown', e => {
        if (e.key === 'Escape') {
            $('#addModal, #editModal').addClass('hidden');
        }
    });
});