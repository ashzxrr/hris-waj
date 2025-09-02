 let currentFilter = 'all';

        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:not(.hidden):not(:disabled)');
            checkboxes.forEach(checkbox => checkbox.checked = source.checked);
            updateSelectedCount();
        }

        function validateForm() {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)');
            const tanggalDari = document.querySelector('input[name="tanggal_dari"]').value;
            const tanggalSampai = document.querySelector('input[name="tanggal_sampai"]').value;

            if (checkboxes.length === 0) {
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
            const machineOnlyCheckboxes = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)');
            const selectedMachineOnly = Array.from(machineOnlyCheckboxes).filter(checkbox => {
                return checkbox.closest('tr').classList.contains('machine-only');
            });

            if (selectedMachineOnly.length === 0) {
                alert('⚠️ Pilih minimal satu user yang hanya ada di mesin (baris hijau) untuk ditambahkan ke database!');
                return false;
            }

            // Buat hidden inputs untuk data user yang dipilih
            const form = document.getElementById('absenForm');

            // Hapus hidden inputs yang lama jika ada
            const oldInputs = form.querySelectorAll('input[name^="user_data"]');
            oldInputs.forEach(input => input.remove());

            // Tambahkan data user yang dipilih
            selectedMachineOnly.forEach((checkbox, index) => {
                const row = checkbox.closest('tr');
                const pin = checkbox.value;
                const namaMesin = row.cells[2].textContent.trim();

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
                    if (checkbox) checkbox.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    row.classList.add('hidden');
                    const checkbox = row.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.classList.add('hidden');
                        checkbox.checked = false;
                    }
                }
            });

            document.getElementById('userCount').textContent = visibleCount;
            document.querySelector('input[onchange="toggleAll(this)"]').checked = false;
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
            const selectedCount = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)').length;
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
                checkbox.addEventListener('change', updateSelectedCount);
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