async function loadData() {
  try {
    // 🔹 Load tabel harian (per tanggal)
    const res = await axios.get("http://localhost/Projek_jualin/Backend/laporan/laba_rugi_harian.php");
    renderTable(res.data);

    // 🔹 Load ringkasan hari ini
    const ringkasanRes = await axios.get("http://localhost/Projek_jualin/Backend/laporan/ringkasan_laba_rugi_harian.php");
    updateRingkasan(ringkasanRes.data);
  } catch (err) {
    console.error("Error loadData:", err);
    document.getElementById("data").innerHTML =
      `<tr><td colspan="4" class="text-center text-danger">Gagal memuat data</td></tr>`;
    updateRingkasan({});
  }
}

function updateRingkasan(row) {
  let totalPenjualan = parseFloat(row.total_penjualan || 0);
  let totalModal = parseFloat(row.total_modal || 0);
  let totalLaba = parseFloat(row.laba || 0);

  document.getElementById("ringkasan-penjualan").innerText =
    "Rp " + totalPenjualan.toLocaleString("id-ID");
  document.getElementById("ringkasan-modal").innerText =
    "Rp " + totalModal.toLocaleString("id-ID");
  document.getElementById("ringkasan-laba").innerText =
    "Rp " + totalLaba.toLocaleString("id-ID");
}

function renderTable(data) {
  const tbody = document.getElementById("data");
  tbody.innerHTML = "";

  if (!data || !data.length) {
    tbody.innerHTML = `<tr><td colspan="4" class="text-center">Tidak ada data</td></tr>`;
    return;
  }

  data.forEach(row => {
    const totalPenjualan = parseInt(row.total_penjualan || 0);
    const totalModal = parseInt(row.total_modal || 0);
    const laba = parseInt(row.laba || 0);

    tbody.innerHTML += `
      <tr>
        <td>${row.tanggal}</td>
        <td>Rp ${totalPenjualan.toLocaleString("id-ID")}</td>
        <td>Rp ${totalModal.toLocaleString("id-ID")}</td>
        <td class="${laba < 0 ? 'text-danger' : 'text-success'}">
          Rp ${laba.toLocaleString("id-ID")}
        </td>
      </tr>`;
  });
}

async function filter() {
  const start = document.getElementById("start").value;
  const end = document.getElementById("end").value;
  let url = "http://localhost/Projek_jualin/Backend/laporan/laba_rugi_harian.php";
  
  if (start || end) {
    url += `?start=${start}&end=${end}`;
  }

  try {
    const res = await axios.get(url);
    renderTable(res.data);

    // 🔹 Ringkasan tetap ambil dari hari ini (bukan filter)
    const ringkasanRes = await axios.get("http://localhost/Projek_jualin/Backend/laporan/laba_rugi_harian.php");
    updateRingkasan(ringkasanRes.data);
  } catch (err) {
    console.error("Error filter:", err);
    document.getElementById("data").innerHTML =
      `<tr><td colspan="4" class="text-center text-danger">Gagal memuat data</td></tr>`;
    updateRingkasan({});
  }
}

// 🔹 load data pertama kali
document.addEventListener("DOMContentLoaded", loadData);
