import Choices from "choices.js";
import "choices.js/public/assets/styles/choices.min.css";

const studentSelect = document.getElementById("student_id");
const classFilter = document.getElementById("class_filter");

if (studentSelect) {
    const CHOICES_CONFIG = {
        searchEnabled: true,
        searchPlaceholderValue: "Cari nama siswa...",
        shouldSort: false, // biar urutan kelas (X-A, X-B, ...) tidak diacak jadi alfabetis
        noResultsText: "Siswa tidak ditemukan",
        noChoicesText: "Tidak ada pilihan",
        itemSelectText: "",
        searchFields: ["label"], // label berisi "Nama (Kelas)", jadi nama kelas ikut bisa dicari
    };

    // Baca data siswa dari <optgroup> yang dirender server, dipakai untuk membangun ulang daftar saat kelas diganti
    const initialId = studentSelect.value;

    const classes = [...studentSelect.querySelectorAll("optgroup")].map(
        (group) => ({
            id: group.dataset.classId,
            name: group.label,
            students: [...group.querySelectorAll("option")].map((option) => ({
                id: option.value,
                label: option.textContent.trim(),
            })),
        }),
    );

    let choices = null;

    function build(classId, selectedId) {
        if (choices) {
            choices.destroy();
        }

        studentSelect.innerHTML = "";
        studentSelect.add(new Option("— Pilih siswa —", ""));

        classes
            .filter((kelas) => !classId || kelas.id === classId)
            .forEach((kelas) => {
                const group = document.createElement("optgroup");
                group.label = kelas.name;

                kelas.students.forEach((student) => {
                    const isSelected = student.id === selectedId;
                    group.appendChild(
                        new Option(
                            student.label,
                            student.id,
                            isSelected,
                            isSelected,
                        ),
                    );
                });

                studentSelect.appendChild(group);
            });

        choices = new Choices(studentSelect, CHOICES_CONFIG);
    }

    // Halaman edit (atau form yang dikembalikan karena error validasi):
    // dropdown kelas otomatis diisi kelas siswa yang sudah terpilih
    if (classFilter && initialId) {
        const kelasAwal = classes.find((kelas) =>
            kelas.students.some((student) => student.id === initialId),
        );

        if (kelasAwal) {
            classFilter.value = kelasAwal.id;
        }
    }

    build(classFilter ? classFilter.value : "", initialId);

    // Ganti kelas: daftar siswa menyempit, pilihan siswa direset jika tidak ada di kelas itu
    if (classFilter) {
        classFilter.addEventListener("change", () => {
            const classId = classFilter.value;
            const current = studentSelect.value;

            const stillValid = classes.some(
                (kelas) =>
                    (!classId || kelas.id === classId) &&
                    kelas.students.some((student) => student.id === current),
            );

            build(classId, stillValid ? current : "");
        });
    }
}
