import { Html5Qrcode, Html5QrcodeSupportedFormats } from "html5-qrcode";

const statusText = document.getElementById("camera-status");
const cardInput = document.getElementById("card-input");
const scanLine = document.getElementById("scan-line");
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// Popup peringatan / error
const popupModal = document.getElementById("popup-modal");
const popupIconWrap = document.getElementById("popup-icon-wrap");
const popupIcon = document.getElementById("popup-icon");
const popupIconPath = document.getElementById("popup-icon-path");
const popupMessage = document.getElementById("popup-message");
const popupProgress = document.getElementById("popup-progress");

// Card sukses
const successModal = document.getElementById("success-modal");
const successIconWrap = document.getElementById("success-icon-wrap");
const successIcon = document.getElementById("success-icon");
const successIconPath = document.getElementById("success-icon-path");
const successTitle = document.getElementById("success-title");
const successName = document.getElementById("success-name");
const successJam = document.getElementById("success-jam");
const successMessage = document.getElementById("success-message");
const successProgress = document.getElementById("success-progress");

// Tombol & petunjuk suara
const soundToggle = document.getElementById("sound-toggle");
const soundIconOn = document.getElementById("sound-icon-on");
const soundIconOff = document.getElementById("sound-icon-off");
const soundHint = document.getElementById("sound-hint");

let html5QrCode = null;
let isSubmitting = false;
let feedbackTimer = null;

// Kamera: barcode yang sama diabaikan selama masih terlihat (atau baru saja hilang) dalam jeda ini
let lastCameraCode = null;
let lastCameraSeenAt = 0;
const SAME_CODE_GAP_MS = 6000;

// ─── Lama tampil tiap popup (milidetik), ubah di sini ──────────────
const DURATION_MS = {
    success: 6000, // card nama siswa
    warning: 5000, // peringatan: belum waktunya, batas lewat, libur, sudah lengkap
    error: 4000, // gangguan server / jaringan / format kartu salah
    duplicate: 5000, // "sudah tercatat"
    not_found: 5000, // kartu tidak dikenali
};

// ─── Pengaturan suara, ubah di sini ────────────────────────────────
const SPEECH_RATE = 1; // 1 = normal, lebih kecil = lebih pelan
const SPEECH_VOLUME = 1; // 0 - 1
const SPEECH_DELAY_MS = 350; // jeda setelah bunyi "ting" sebelum nama dibacakan
const SOUND_STORAGE_KEY = "scanSound";

// Koreksi pelafalan nama (kata dalam huruf kecil => ejaan yang dibaca).
// Tambahkan di sini jika ada nama yang terdengar aneh.
const NAME_FIXES = {
    muh: "Muhammad",
    moh: "Mohammad",
    mohd: "Mohammad",
    moch: "Mochammad",
    mhd: "Muhammad",
    mukh: "Mukhammad",
    st: "Siti",
    nur: "Nur",
};

// Path ikon (outline)
const ICON_CHECK = "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z";
const ICON_CLOCK = "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z";
const ICON_ALERT = "M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z";
const ICON_X =
    "M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z";

// Warna per kondisi. Class ditulis utuh supaya terbaca oleh Tailwind.
const SUCCESS_VARIANTS = {
    tepat_waktu: {
        icon: ICON_CHECK,
        wrap: "bg-emerald-100",
        svg: "text-emerald-600",
        pill: "bg-emerald-50 text-emerald-700",
        bar: "bg-emerald-400",
    },
    telat: {
        icon: ICON_CLOCK,
        wrap: "bg-amber-100",
        svg: "text-amber-600",
        pill: "bg-amber-50 text-amber-700",
        bar: "bg-amber-400",
    },
    pulang: {
        icon: ICON_CHECK,
        wrap: "bg-sky-100",
        svg: "text-sky-600",
        pill: "bg-sky-50 text-sky-700",
        bar: "bg-sky-400",
    },
};

const POPUP_VARIANTS = {
    warning: {
        icon: ICON_ALERT,
        wrap: "bg-amber-100",
        svg: "text-amber-600",
        bar: "bg-amber-400",
    },
    error: {
        icon: ICON_X,
        wrap: "bg-red-100",
        svg: "text-red-600",
        bar: "bg-red-400",
    },
};

// ═══════════════════════════════════════════════════════════════════
// SUARA: bunyi "ting" / "buzz" + ucapan nama (Web Speech API bawaan browser)
// ═══════════════════════════════════════════════════════════════════
const speechSupported = "speechSynthesis" in window;

let soundEnabled = true;
try {
    soundEnabled = localStorage.getItem(SOUND_STORAGE_KEY) !== "off";
} catch (e) {
    // localStorage diblokir: pakai default (aktif)
}

let audioCtx = null;
let speechTimer = null;
let currentUtterance = null; // disimpan agar tidak dibuang garbage collector (bug Chrome)
let indoVoice = null;

function updateSoundUi() {
    soundIconOn?.classList.toggle("hidden", !soundEnabled);
    soundIconOff?.classList.toggle("hidden", soundEnabled);
    soundToggle?.setAttribute(
        "aria-label",
        soundEnabled ? "Matikan suara" : "Nyalakan suara",
    );
    soundToggle?.setAttribute(
        "title",
        soundEnabled
            ? "Suara aktif (klik untuk mematikan)"
            : "Suara mati (klik untuk menyalakan)",
    );
}

// Browser baru mengizinkan suara setelah pengguna berinteraksi sekali dengan halaman
function userHasInteracted() {
    return navigator.userActivation?.hasBeenActive === true;
}

function updateHint() {
    if (!soundHint) return;
    const perluKetuk = soundEnabled && !userHasInteracted();
    soundHint.classList.toggle("hidden", !perluKetuk);
}

function pickIndonesianVoice() {
    if (!speechSupported) return null;

    const voices = window.speechSynthesis.getVoices();
    if (voices.length === 0) return null;

    indoVoice =
        voices.find(
            (v) => v.lang.toLowerCase().replace("_", "-") === "id-id",
        ) ??
        voices.find((v) => v.lang.toLowerCase().startsWith("id")) ??
        null;

    if (!indoVoice) {
        console.warn(
            "Suara bahasa Indonesia tidak ditemukan di perangkat ini. Ucapan memakai suara bawaan.",
        );
    }

    return indoVoice;
}

if (speechSupported) {
    pickIndonesianVoice();
    window.speechSynthesis.addEventListener(
        "voiceschanged",
        pickIndonesianVoice,
    );

    // Daftar suara kadang baru terisi beberapa saat setelah halaman dibuka: coba lagi sampai ada
    let percobaanSuara = 0;
    const pemantauSuara = setInterval(() => {
        percobaanSuara++;
        if (
            window.speechSynthesis.getVoices().length > 0 ||
            percobaanSuara >= 20
        ) {
            pickIndonesianVoice();
            clearInterval(pemantauSuara);
        }
    }, 500);
}

// Nada sangat rendah & sangat pelan yang berjalan terus, supaya speaker/driver laptop
// tidak masuk mode hemat daya (penyebab suara pertama telat atau hilang).
// Frekuensi 30 Hz berada di bawah jangkauan speaker laptop, jadi praktis tidak terdengar.
let keepAliveStarted = false;

function startKeepAlive(ctx) {
    if (keepAliveStarted) return;
    keepAliveStarted = true;

    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.frequency.value = 30;
    gain.gain.value = 0.002;
    osc.connect(gain).connect(ctx.destination);
    osc.start();
}

function getAudioCtx() {
    if (!audioCtx) {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        audioCtx = new Ctx();
        startKeepAlive(audioCtx);
    }
    if (audioCtx.state !== "running") audioCtx.resume().catch(() => {});
    return audioCtx;
}

function playTone(
    freq,
    startOffset,
    duration,
    type = "sine",
    gainValue = 0.18,
) {
    const ctx = getAudioCtx();
    if (!ctx) return;

    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    const t0 = ctx.currentTime + 0.05 + startOffset; // jeda 50 ms agar awal bunyi tidak terpotong

    osc.type = type;
    osc.frequency.value = freq;

    // naik-turun halus supaya tidak berbunyi "klik"
    gain.gain.setValueAtTime(0.0001, t0);
    gain.gain.exponentialRampToValueAtTime(gainValue, t0 + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);

    osc.connect(gain).connect(ctx.destination);
    osc.start(t0);
    osc.stop(t0 + duration + 0.05);
}

// kind: "success" (ting-ting naik), "warning" (dua nada sedang), "error" (buzz rendah)
function playBeep(kind) {
    if (!soundEnabled) return;

    try {
        if (kind === "success") {
            playTone(880, 0, 0.12);
            playTone(1320, 0.12, 0.18);
        } else if (kind === "warning") {
            playTone(520, 0, 0.12, "triangle");
            playTone(520, 0.17, 0.12, "triangle");
        } else {
            playTone(200, 0, 0.3, "square", 0.1);
        }
    } catch (e) {
        console.warn("Gagal memutar bunyi:", e);
    }
}

// "NOFAILAH, A'YUNIL" -> "Nofailah Ayunil" ; "MUH. ALI" -> "Muhammad Ali"
function normalizeNameForSpeech(nama) {
    return String(nama ?? "")
        .replace(/['’`]/g, "") // Rofi'ah -> Rofiah
        .replace(/[.,;:()\/\\-]+/g, " ")
        .replace(/\s+/g, " ")
        .trim()
        .split(" ")
        .map((kata) => {
            const kecil = kata.toLowerCase();
            if (NAME_FIXES[kecil]) return NAME_FIXES[kecil];
            return kecil.charAt(0).toUpperCase() + kecil.slice(1);
        })
        .join(" ");
}

// Bersihkan pesan server agar enak dibaca mesin suara
function cleanMessageForSpeech(message) {
    return String(message ?? "")
        .replace(/\(([^)]*)\)/g, ", $1") // "(telat)" -> ", telat"
        .replace(/(\d{1,2}):(\d{2})/g, "$1 $2") // "06:50" -> "06 50"
        .replace(/\s+/g, " ")
        .trim();
}

function stopSpeech() {
    clearTimeout(speechTimer);
    if (speechSupported) window.speechSynthesis.cancel();
}

// Bunyi + ucapan. Scan baru selalu memotong ucapan sebelumnya.
function announce(kind, text) {
    stopSpeech();

    if (!soundEnabled) return;

    playBeep(kind);

    if (!speechSupported || !text) return;

    speechTimer = setTimeout(() => speakNow(text, 0), SPEECH_DELAY_MS);
}

// Ucapkan sekarang. Jika mesin suara tidak mulai berbunyi dalam 1,5 detik
// (macet / belum siap), batalkan lalu coba lagi satu kali.
function speakNow(text, attempt) {
    const synth = window.speechSynthesis;

    // Mesin suara kadang macet dalam keadaan "paused" di Chrome
    if (synth.paused) synth.resume();

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = "id-ID";
    utterance.rate = SPEECH_RATE;
    utterance.volume = SPEECH_VOLUME;

    const voice = indoVoice ?? pickIndonesianVoice();
    if (voice) utterance.voice = voice;

    let started = false;
    let watchdog = null;

    utterance.onstart = () => {
        started = true;
        clearTimeout(watchdog);
    };
    utterance.onend = utterance.onerror = () => {
        clearTimeout(watchdog);
        if (currentUtterance === utterance) currentUtterance = null;
    };

    currentUtterance = utterance; // disimpan agar tidak dibuang garbage collector (bug Chrome)
    synth.speak(utterance);

    watchdog = setTimeout(() => {
        // Hanya ulangi jika ucapan ini masih yang terbaru dan belum mulai
        if (started || currentUtterance !== utterance || attempt >= 1) return;

        synth.cancel();
        speakNow(text, attempt + 1);
    }, 1500);
}

function toggleSound() {
    soundEnabled = !soundEnabled;

    try {
        localStorage.setItem(SOUND_STORAGE_KEY, soundEnabled ? "on" : "off");
    } catch (e) {
        // abaikan
    }

    updateSoundUi();
    updateHint();

    if (soundEnabled) {
        announce("success", "Suara diaktifkan.");
    } else {
        stopSpeech();
    }
}

soundToggle?.addEventListener("click", (event) => {
    event.stopPropagation();
    toggleSound();
    focusCardInput();
});

// Interaksi pertama (ketuk / tekan tombol / scan USB) membuka izin suara di browser
function unlockAudioOnFirstInteraction() {
    getAudioCtx();

    if (speechSupported) {
        // ucapan kosong: "membuka" fitur suara tanpa terdengar
        const kosong = new SpeechSynthesisUtterance("");
        kosong.volume = 0;
        window.speechSynthesis.speak(kosong);
    }

    updateHint();
}

["pointerdown", "keydown", "touchstart"].forEach((nama) =>
    window.addEventListener(nama, unlockAudioOnFirstInteraction, {
        once: true,
    }),
);

updateSoundUi();
updateHint();

// ─── Util: garis hitung mundur tipis di bawah card ─────────────────
function startCountdown(bar, durationMs, barColorClass) {
    bar.className = `countdown-bar h-1 rounded-full ${barColorClass}`;
    bar.style.animation = "none";
    void bar.offsetWidth; // paksa browser mengulang animasi dari awal
    bar.style.animation = `countdown ${durationMs}ms linear forwards`;
}

// ─── Tutup semua popup (dipakai timer, ketukan, dan scan baru) ─────
// Ucapan yang sedang berjalan sengaja tidak dipotong, supaya kalimat selesai dibacakan.
function hideFeedback() {
    clearTimeout(feedbackTimer);

    popupModal.classList.add("hidden");
    popupModal.classList.remove("flex");
    successModal.classList.add("hidden");
    successModal.classList.remove("flex");

    focusCardInput();
}

// Ketuk di mana saja pada popup untuk menutup lebih cepat (tidak wajib)
popupModal.addEventListener("click", hideFeedback);
successModal.addEventListener("click", hideFeedback);

// ─── Popup peringatan / error, hilang otomatis ─────────────────────
// speech: kalimat yang diucapkan (default: isi pesan popup)
function showPopup(
    message,
    level = "warning",
    durationMs = DURATION_MS.warning,
    speech = null,
) {
    hideFeedback(); // scan baru langsung menggantikan tampilan sebelumnya

    const variant = POPUP_VARIANTS[level] ?? POPUP_VARIANTS.warning;

    popupMessage.textContent = message;
    popupIconWrap.className = `mx-auto flex h-12 w-12 items-center justify-center rounded-full ${variant.wrap}`;
    popupIcon.setAttribute("class", `h-6 w-6 ${variant.svg}`);
    popupIconPath.setAttribute("d", variant.icon);

    popupModal.classList.remove("hidden");
    popupModal.classList.add("flex");

    startCountdown(popupProgress, durationMs, variant.bar);
    feedbackTimer = setTimeout(hideFeedback, durationMs);

    announce(level, speech ?? cleanMessageForSpeech(message));
}

// ─── Card sukses absensi, hilang otomatis ──────────────────────────
function showSuccessCard(data) {
    hideFeedback(); // scan baru langsung menggantikan tampilan sebelumnya

    const isMasuk = data.type === "masuk";
    const telat = isMasuk && data.status === "telat";
    const variant = !isMasuk
        ? SUCCESS_VARIANTS.pulang
        : telat
          ? SUCCESS_VARIANTS.telat
          : SUCCESS_VARIANTS.tepat_waktu;

    successTitle.textContent = isMasuk ? "Kehadiran" : "Kepulangan";
    successName.textContent = data.nama ?? "";
    successJam.textContent =
        data.jam ??
        new Date().toLocaleTimeString("en-GB", {
            hour: "2-digit",
            minute: "2-digit",
        });
    successMessage.textContent = data.message ?? "";

    successIconWrap.className = `mx-auto flex h-16 w-16 items-center justify-center rounded-full ${variant.wrap}`;
    successIcon.setAttribute("class", `h-8 w-8 ${variant.svg}`);
    successIconPath.setAttribute("d", variant.icon);
    successMessage.className = `mt-6 rounded-lg px-3 py-2 text-sm font-semibold ${variant.pill}`;

    successModal.classList.remove("hidden");
    successModal.classList.add("flex");

    startCountdown(successProgress, DURATION_MS.success, variant.bar);
    feedbackTimer = setTimeout(hideFeedback, DURATION_MS.success);

    // Kalimat suara: "Atas nama Nofailah, absen masuk berhasil. Terlambat."
    const nama = normalizeNameForSpeech(data.nama);
    const kalimat = !isMasuk
        ? `Atas nama ${nama}, absen pulang berhasil.`
        : telat
          ? `Atas nama ${nama}, absen masuk berhasil. Terlambat.`
          : `Atas nama ${nama}, absen masuk berhasil. Tepat waktu.`;

    announce("success", kalimat);
}

// ─── Tentukan tampilan dari respons server ─────────────────────────
function handleScanResponse(status, data) {
    if (data.success) {
        showSuccessCard(data);
        return;
    }

    // Token halaman kedaluwarsa (kiosk dibiarkan terbuka lama): muat ulang otomatis
    if (status === 419) {
        showPopup(
            "Sesi halaman habis. Halaman dimuat ulang, silakan scan kembali.",
            "warning",
            2500,
        );
        setTimeout(() => window.location.reload(), 2500);
        return;
    }

    if (status === 429) {
        showPopup(
            "Terlalu banyak scan. Tunggu sebentar lalu coba lagi.",
            "error",
            DURATION_MS.error,
        );
        return;
    }

    // Pesan error server (kode, nama kolom, dll.) tidak ditampilkan ke siswa
    if (status >= 500) {
        console.error("Server error:", status, data);
        showPopup(
            "Terjadi gangguan pada server. Coba lagi atau hubungi petugas.",
            "error",
            DURATION_MS.error,
        );
        return;
    }

    const reason = data.reason ?? null;
    const level =
        reason === "not_found" || status === 422 ? "error" : "warning";
    const duration = DURATION_MS[reason] ?? DURATION_MS[level];

    // "Sudah tercatat": sebut nama siswa jika dikirim server
    let speech = null;
    if (reason === "duplicate" && data.nama) {
        speech = `Atas nama ${normalizeNameForSpeech(data.nama)}, sudah tercatat.`;
    }

    showPopup(data.message ?? "Terjadi kesalahan.", level, duration, speech);
}

// ─── Kirim kode kartu ke server (dipakai kamera maupun alat scan) ──
async function submitCard(cardId) {
    if (isSubmitting || !cardId) return;
    isSubmitting = true;

    if (scanLine) scanLine.style.animationPlayState = "paused";

    try {
        const response = await fetch("/scan", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
                Accept: "application/json",
            },
            body: JSON.stringify({ card_id: cardId }),
        });

        const data = await response.json().catch(() => ({}));

        handleScanResponse(response.status, data);
    } catch (error) {
        showPopup(
            "Gagal terhubung ke server. Periksa jaringan lalu coba lagi.",
            "error",
            DURATION_MS.error,
        );
    } finally {
        isSubmitting = false;

        if (scanLine) scanLine.style.animationPlayState = "running";
    }
}

// ─── Sumber 1: Kamera (live scan) ──────────────────────────────────
function onCameraDetected(decodedText) {
    const code = decodedText.trim();
    const now = Date.now();

    // Kartu yang sama masih di depan kamera (atau baru saja diturunkan): jangan kirim ulang.
    // Waktu terakhir terlihat diperbarui terus, jadi kartu yang dipegang lama tidak memicu popup berulang.
    const kartuSama =
        code === lastCameraCode && now - lastCameraSeenAt < SAME_CODE_GAP_MS;

    lastCameraCode = code;
    lastCameraSeenAt = now;

    if (kartuSama) return;

    submitCard(code);
}

async function startCamera() {
    html5QrCode = new Html5Qrcode("reader");

    const config = {
        fps: 10,
        qrbox: { width: 300, height: 150 },
        formatsToSupport: [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
        ],
    };

    try {
        await html5QrCode.start(
            { facingMode: "environment" },
            config,
            onCameraDetected,
            () => {}, // error per-frame, normal terjadi terus, abaikan
        );
        statusText.textContent =
            "Kamera aktif — arahkan barcode atau tap ke alat scan.";
    } catch (envError) {
        try {
            const cameras = await Html5Qrcode.getCameras();

            if (!cameras || cameras.length === 0) {
                statusText.textContent =
                    "Kamera tidak ditemukan. Alat scan tetap bisa dipakai.";
                return;
            }

            await html5QrCode.start(
                cameras[0].id,
                config,
                onCameraDetected,
                () => {},
            );
            statusText.textContent =
                "Kamera aktif — arahkan barcode atau tap ke alat scan.";
        } catch (fallbackError) {
            console.error("Camera error:", fallbackError);
            statusText.textContent =
                "Kamera gagal diakses. Alat scan tetap bisa dipakai.";
        }
    }
}

// ─── Sumber 2: Alat scan fisik (USB keyboard wedge) ────────────────
function focusCardInput() {
    cardInput.focus();
}

cardInput.addEventListener("keypress", (event) => {
    if (event.key === "Enter") {
        event.preventDefault();
        const value = cardInput.value.trim();
        cardInput.value = "";
        submitCard(value);
    }
});

// Klik di mana pun pada halaman akan mengembalikan fokus ke input tersembunyi,
// supaya alat scan tetap bisa "mengetik" walau user sempat klik area kamera.
document.addEventListener("click", focusCardInput);
window.addEventListener("load", focusCardInput);

// ─── Mulai ──────────────────────────────────────────────────────────
startCamera();
