 let currentFilter = 'all';

// Persistent selection set: stores selected PINs across searches
const persistentSelected = new Set();

function initPersistentSelection() {
    // Initialize from any existing checkboxes or hidden inputs on page
    document.querySelectorAll('input[name="selected_users[]"]').forEach(inp => {
        const val = inp.value;
        if (!val) return;
        // If it's a hidden input (server-rendered), treat as selected
        if (inp.type === 'hidden' || inp.checked) {
            persistentSelected.add(val);
        }
    });

    // Ensure visible checkboxes reflect the persistent set
    document.querySelectorAll('input[name="selected_users[]"]').forEach(inp => {
        if (inp.type !== 'hidden') {
            inp.checked = persistentSelected.has(inp.value);
        }
    });
    updateSelectedCount();
}

function syncSelectionsToForm(form) {
    // Remove any existing selected_users[] hidden inputs in the form
    form.querySelectorAll('input[name="selected_users[]"][type="hidden"]').forEach(i => i.remove());
    // Append hidden inputs for each selected value
    persistentSelected.forEach(val => {
        const h = document.createElement('input');
        h.type = 'hidden';
        h.name = 'selected_users[]';
        h.value = val;
        form.appendChild(h);
    });
}

        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:not(.hidden):not(:disabled)');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
                if (typeof persistentSelected !== 'undefined') {
                    if (source.checked) persistentSelected.add(checkbox.value);
                    else persistentSelected.delete(checkbox.value);
                }
            });
            updateSelectedCount();
        }

        function validateForm() {
            const selectedCount = (typeof persistentSelected !== 'undefined') ? persistentSelected.size : document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)').length;
            const tanggalDari = document.querySelector('input[name="tanggal_dari"]').value;
            const tanggalSampai = document.querySelector('input[name="tanggal_sampai"]').value;

            if (selectedCount === 0) {
                alert('⚠️ Pilih minimal satu user!');
                return false;
            }

            if (!tanggalDari || !tanggalSampai) {
                alert('⚠️ Tanggal dari dan sampai harus diisi!');
                return false;
            }

            if (tanggalDari > tanggalSampai) {
                alert('⚠️ Tanggal dari tidak boleh lebih besar dari tanggal sampai!');
                return false;
            }

            // Show loading spinner
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('loading');

            // Tampilkan loading tanpa mengganggu submit form
            const loadingIndicator = document.getElementById('loadingIndicator');
            if (loadingIndicator) {
                loadingIndicator.classList.add('show');
            }

            return true; // Ini penting! Harus return true agar form bisa submit
        }

        function validateAddUsers() {
            // Determine selected machine-only pins from persistentSelected when available
            const selectedPins = (typeof persistentSelected !== 'undefined') ? Array.from(persistentSelected) : Array.from(document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)')).map(cb => cb.value);
            const selectedMachineOnlyPins = selectedPins.filter(pin => {
                const row = Array.from(document.querySelectorAll('tbody tr')).find(r => {
                    const c = r.querySelector('.pin-col');
                    return c && c.textContent.trim() === String(pin).trim();
                });
                return row && row.classList.contains('machine-only');
            });

            if (selectedMachineOnlyPins.length === 0) {
                alert('⚠️ Pilih minimal satu user yang hanya ada di mesin (baris hijau) untuk ditambahkan ke database!');
                return false;
            }

            // Buat hidden inputs untuk data user yang dipilih
            const form = document.getElementById('absenForm');

            // Hapus hidden inputs yang lama jika ada
            const oldInputs = form.querySelectorAll('input[name^="user_data"]');
            oldInputs.forEach(input => input.remove());

            // Tambahkan data user yang dipilih
            selectedMachineOnlyPins.forEach((pin, index) => {
                const row = Array.from(document.querySelectorAll('tbody tr')).find(r => {
                    const c = r.querySelector('.pin-col');
                    return c && c.textContent.trim() === String(pin).trim();
                });
                const namaMesin = row ? row.cells[2].textContent.trim() : '';

                // Buat hidden inputs untuk pin dan nama
                const pinInput = document.createElement('input');
                pinInput.type = 'hidden';
                pinInput.name = `user_data[${index}][pin]`;
                pinInput.value = pin;
                form.appendChild(pinInput);

                const namaInput = document.createElement('input');
                namaInput.type = 'hidden';
                namaInput.name = `user_data[${index}][nama]`;
                namaInput.value = namaMesin;
                form.appendChild(namaInput);
            });
        }

        function showLoading() {
            const submitBtn = document.getElementById('submitBtn');
            const loadingText = document.getElementById('loadingText');

            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading-spinner show"></div> Memproses...';
            loadingText.classList.add('show');

            // Optional: Hide loading after some time if form doesn't submit
            setTimeout(() => {
                if (submitBtn.disabled) {
                    hideLoading();
                }
            }, 30000); // 30 seconds timeout
        }

        function hideLoading() {
            const submitBtn = document.getElementById('submitBtn');
            const loadingText = document.getElementById('loadingText');

            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Detail Absensi <span class="emoji">➡️</span>';
            loadingText.classList.remove('show');
        }

        // Tambahkan variabel global
        let currentBagian = 'all';

        // Update fungsi searchAndFilter
        function searchAndFilter() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            tableRows.forEach(row => {
                const bagianCell = row.cells[9].textContent.toLowerCase(); // Sesuaikan dengan index kolom bagian
                const matchBagian = currentBagian === 'all' || bagianCell === currentBagian.toLowerCase();

                // Existing search logic
                const matchSearch = Array.from(row.cells).some(cell =>
                    cell.textContent.toLowerCase().includes(searchInput)
                );

                // Check filter criteria
                const matchFilter = currentFilter === 'all' ||
                    (currentFilter === 'machine' && row.classList.contains('machine-only'));

                if (matchSearch && matchFilter && matchBagian) {
                    row.style.display = '';
                    row.classList.remove('hidden');
                    const checkbox = row.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.classList.remove('hidden');
                        // When row becomes visible, reflect persistent selection
                        checkbox.checked = persistentSelected.has(checkbox.value);
                    }
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    row.classList.add('hidden');
                    const checkbox = row.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.classList.add('hidden');
                        // Keep checkbox.checked as-is; persistentSelected still tracks it
                    }
                }
            });

            document.getElementById('userCount').textContent = visibleCount;
            // Recompute master checkbox state based on visible checkboxes
            const master = document.querySelector('input[onchange="toggleAll(this)"]');
            if (master) {
                const visibleBoxes = document.querySelectorAll('input[name="selected_users[]"]:not(.hidden):not(:disabled)');
                master.checked = visibleBoxes.length > 0 && Array.from(visibleBoxes).every(b => b.checked);
            }
            updateSelectedCount();
        }

        // Tambahkan fungsi filterByBagian
        function filterByBagian(bagian) {
            currentBagian = bagian;
            searchAndFilter();
        }
        function setFilter(filter) {
            currentFilter = filter;
            searchAndFilter();
        }

        function updateSelectedCount() {
            // Use persistentSelected size as the authoritative count
            const selectedCount = persistentSelected.size;
            const selectedCountElement = document.getElementById('selectedCount');
            if (selectedCountElement) {
                selectedCountElement.textContent = selectedCount;
            }

            // Update tombol tambah user berdasarkan selection
            updateAddUserButton();
        }

        function updateAddUserButton() {
            const addUserBtn = document.getElementById('addUserBtn');
            const machineOnlyCheckboxes = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)');
            const selectedMachineOnly = Array.from(machineOnlyCheckboxes).filter(checkbox => {
                return checkbox.closest('tr').classList.contains('machine-only');
            });

            if (selectedMachineOnly.length > 0) {
                addUserBtn.disabled = false;
                addUserBtn.innerHTML = `<span class="emoji">👤➕</span> Tambah ${selectedMachineOnly.length} User ke Database`;

                // Highlight selected machine-only rows
                document.querySelectorAll('.machine-only').forEach(row => {
                    const checkbox = row.querySelector('input[name="selected_users[]"]');
                    if (checkbox && checkbox.checked && !checkbox.classList.contains('hidden')) {
                        row.classList.add('machine-only-highlight');
                    } else {
                        row.classList.remove('machine-only-highlight');
                    }
                });
            } else {
                addUserBtn.disabled = true;
                addUserBtn.innerHTML = '<span class="emoji">👤➕</span> Tambah User ke Database';

                // Remove all highlights
                document.querySelectorAll('.machine-only-highlight').forEach(row => {
                    row.classList.remove('machine-only-highlight');
                });
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('searchInput').addEventListener('input', searchAndFilter);

            // Add change event to all checkboxes
            document.querySelectorAll('input[name="selected_users[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', function (e) {
                    const v = e.target.value;
                    if (e.target.checked) persistentSelected.add(v);
                    else persistentSelected.delete(v);
                    updateSelectedCount();
                });
            });

            // Initialize persistent selection from any server-rendered hidden fields or initial checkboxes
            initPersistentSelection();

            // Ensure forms submit the persistent selections by injecting hidden inputs before submit
            document.querySelectorAll('form').forEach(f => {
                f.addEventListener('submit', function (ev) {
                    syncSelectionsToForm(f);
                });
            });

            // Set default dates
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            document.querySelector('input[name="tanggal_dari"]').value = todayStr;
            document.querySelector('input[name="tanggal_sampai"]').value = todayStr;
        });

        function setToday() {
            const today = new Date();
            const todayStr = formatDate(today);

            document.getElementById('startDate').value = todayStr;
            document.getElementById('endDate').value = todayStr;
            updateDateButtons(this);
        }

        function setCurrentMonth() {
            const now = new Date();
            // Set first day of current month
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 2);
            // Set last day by getting day 0 of next month (which is last day of current month)
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 1);

            document.getElementById('startDate').value = formatDate(firstDay);
            document.getElementById('endDate').value = formatDate(lastDay);
            updateDateButtons(this);
        }

        function setPreviousMonth() {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);

            document.getElementById('startDate').value = formatDate(firstDay);
            document.getElementById('endDate').value = formatDate(lastDay);
            updateDateButtons(this);
        }

        function setCustomRange() {
            document.getElementById('startDate').focus();
            updateDateButtons(this);
        }

        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }

        function updateDateButtons(clickedBtn) {
            document.querySelectorAll('.date-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            clickedBtn.classList.add('active');
        }

        // Initialize with current month
        document.addEventListener('DOMContentLoaded', () => {
            setCurrentMonth();
        });