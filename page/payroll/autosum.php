<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Penjumlahan Otomatis (5 Angka)</title>
  <style>
    :root { color-scheme: light dark; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; }
    .wrap { max-width: 560px; margin: 6vh auto; padding: 24px; border-radius: 16px; box-shadow: 0 6px 24px rgba(0,0,0,.08); }
    h1 { font-size: 1.25rem; margin: 0 0 16px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    label { font-size: .9rem; opacity: .85; }
    input[type="text"], input[type="number"] { width: 100%; padding: 10px 12px; border-radius: 12px; border: 1px solid rgba(0,0,0,.2); outline: none; }
    input:focus { border-color: #4a90e2; box-shadow: 0 0 0 3px rgba(74,144,226,.2); }
    .hasil { margin-top: 16px; padding: 12px; border-radius: 12px; background: rgba(74,144,226,.08); font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; justify-content: space-between; }
    .sub { font-size: .85rem; opacity: .7; margin-top: 8px; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Penjumlahan Otomatis (tanpa tombol/refresh)</h1>

    <div class="grid">
      <div>
        <label>Angka 1</label>
        <input class="angka" type="text" inputmode="decimal" placeholder="0" />
      </div>
      <div>
        <label>Angka 2</label>
        <input class="angka" type="text" inputmode="decimal" placeholder="0" />
      </div>
      <div>
        <label>Angka 3</label>
        <input class="angka" type="text" inputmode="decimal" placeholder="0" />
      </div>
      <div>
        <label>Angka 4</label>
        <input class="angka" type="text" inputmode="decimal" placeholder="0" />
      </div>
      <div>
        <label>Angka 5</label>
        <input class="angka" type="text" inputmode="decimal" placeholder="0" />
      </div>
    </div>

    <div class="hasil">
      <span>Hasil</span>
      <span id="hasil">0</span>
    </div>
    <div class="sub">Tips: Kamu bisa pakai titik atau koma untuk desimal. Kolom kosong akan dihitung 0.</div>
  </div>

  <script>
    // Ambil semua input angka
    const inputs = document.querySelectorAll('.angka');
    const hasilEl = document.getElementById('hasil');

    // Helper: ubah teks ke angka, dukung koma & titik, kolom kosong jadi 0
    function toNumber(value) {
      if (value == null) return 0;
      const clean = String(value).trim().replace(',', '.');
      const n = parseFloat(clean);
      return Number.isNaN(n) ? 0 : n;
    }

    // Hitung total dan tampilkan
    function hitung() {
      let total = 0;
      inputs.forEach((el) => { total += toNumber(el.value); });

      // Pembulatan ringan agar 0.1+0.2 tidak jadi 0.3000000004
      total = Math.round((total + Number.EPSILON) * 1000000) / 1000000;
      hasilEl.textContent = total;
    }

    // Reaksi otomatis saat pengguna mengetik/ubah nilai
    inputs.forEach((el) => el.addEventListener('input', hitung));

    // Inisialisasi tampilan awal
    hitung();
  </script>
</body>
</html>
