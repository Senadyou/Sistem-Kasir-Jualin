// laporan_laba_rugi_bulanan_kasir.js

// Format YYYY-MM jadi "Maret 2026"
function formatBulan(str) {
  if (!str) return "-";

  const namaBulan = [
    "Januari","Februari","Maret","April","Mei","Juni",
    "Juli","Agustus","September","Oktober","November","Desember"
  ];

  const parts = str.split("-");
  const tahun = parts[0];
  const bulan = parts[1];

  if (!bulan) return str;

  return `${namaBulan[parseInt(bulan) - 1]} ${tahun}`;
}

// Format rupiah aman
function formatRupiah(angka) {
  return "Rp " + (parseInt(angka) || 0).toLocaleString("id-ID");
}

// Render tabel
function renderTable(data) {
  const tbody = document.getElementById("data");

  if (!Array.isArray(data) || data.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center">Tidak ada data</td>
      </tr>`;
    return;
  }

  let html = "";

  data.forEach(r => {
    const laba = parseInt(r.laba) || 0;

    html += `
      <tr>
        <td>${formatBulan(r.bulan)}</td>
        <td>${formatRupiah(r.total_penjualan)}</td>
        <td>${formatRupiah(r.total_modal)}</td>
        <td class="${laba < 0 ? 'text-danger fw-bold' : 'text-success fw-bold'}">
          ${formatRupiah(laba)}
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

// Tampilkan error
function showError(pesan) {
  document.getElementById("data").innerHTML = `
    <tr>
      <td colspan="4" class="text-center text-danger">${pesan}</td>
    </tr>`;
}

// Load data dari backend
function loadData() {
  const url = "http://localhost/Projek_jualin/Backend/laporan/laba_rugi_bulanan.php";

  axios.get(url)
    .then(response => {
      if (Array.isArray(response.data)) {
        renderTable(response.data);
      } else {
        showError("Format data tidak valid");
      }
    })
    .catch(error => {
      console.error(error);
      showError("Gagal memuat data");
    });
}

// Auto load saat halaman siap
document.addEventListener("DOMContentLoaded", () => {
  if (location.protocol === "file:") {
    console.warn("Jangan buka lewat file://, pakai http://localhost");
  }
  loadData();
});