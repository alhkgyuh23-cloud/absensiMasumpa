const INTERVAL_MS = 5000; // polling tiap 5 detik

const totalHadirEl = document.getElementById("total-hadir");
const totalSiswaEl = document.getElementById("total-siswa");

// Class ditulis utuh (bukan dirakit dari variabel) supaya terbaca oleh Tailwind
const BADGE_MASUK = "bg-emerald-50 text-emerald-700";
const BADGE_PULANG = "bg-amber-50 text-amber-700";

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
}

function badgeWaktu(waktu, warnaKelas) {
    if (!waktu) {
        return '<span class="text-slate-300">—</span>';
    }
    return `<span class="rounded-full px-2 py-0.5 text-xs ${warnaKelas}">${escapeHtml(waktu)}</span>`;
}

function badgeKeterangan(row) {
    const badges = [];

    if (row.jenis === "sakit") {
        badges.push(
            '<span class="rounded-full bg-orange-50 px-2 py-0.5 text-xs text-orange-700">Sakit</span>',
        );
    } else if (row.jenis === "izin") {
        badges.push(
            '<span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs text-sky-700">Izin</span>',
        );
    } else if (row.status === "telat") {
        badges.push(
            '<span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">Telat</span>',
        );
    } else if (row.status === "tepat_waktu") {
        badges.push(
            '<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Tepat waktu</span>',
        );
    }

    if (row.pulang) {
        badges.push(
            '<span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs text-sky-700">Pulang berhasil</span>',
        );
    }

    if (row.belum_pulang) {
        badges.push(
            '<span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Belum absen pulang</span>',
        );
    }

    if (!row.jenis && !row.status && !row.pulang && !row.belum_pulang) {
        badges.push('<span class="text-slate-300">—</span>');
    }

    return `<div class="flex flex-wrap gap-1">${badges.join("")}</div>`;
}

function renderBarisKelas(rows) {
    if (!rows || rows.length === 0) {
        return `
            <tr>
                <td colspan="5" class="px-4 py-6 text-center text-slate-400">Belum ada yang absen hari ini.</td>
            </tr>
        `;
    }

    // Kolom Waktu disembunyikan di HP (sama seperti di dashboard.blade.php)
    return rows
        .map(
            (row) => `
                <tr>
                    <td class="hidden px-4 py-3 sm:table-cell">${escapeHtml(row.tanggal)}</td>
                    <td class="px-4 py-3 font-medium text-slate-800">${escapeHtml(row.nama)}</td>
                    <td class="px-4 py-3">${badgeWaktu(row.masuk, BADGE_MASUK)}</td>
                    <td class="px-4 py-3">${badgeWaktu(row.pulang, BADGE_PULANG)}</td>
                    <td class="px-4 py-3">${badgeKeterangan(row)}</td>
                </tr>
            `,
        )
        .join("");
}

async function refreshDashboard() {
    try {
        const response = await fetch("/dashboard/data", {
            headers: { Accept: "application/json" },
        });

        if (!response.ok) return;

        const data = await response.json();

        if (totalHadirEl) totalHadirEl.textContent = data.totalHadirHariIni;
        if (totalSiswaEl) totalSiswaEl.textContent = data.totalSiswa;

        data.classes.forEach((kelas) => {
            const tbody = document.getElementById(`tbody-${kelas.id}`);
            const hadirCount = document.getElementById(
                `hadir-count-${kelas.id}`,
            );

            if (tbody) tbody.innerHTML = renderBarisKelas(kelas.rows);
            if (hadirCount)
                hadirCount.textContent = `${kelas.hadir} dari ${kelas.total} siswa hadir`;
        });
    } catch (error) {
        // diam saja kalau gagal sekali, nanti dicoba lagi di polling berikutnya
    }
}

setInterval(refreshDashboard, INTERVAL_MS);
