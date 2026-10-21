// assets/js/app.js - JavaScript seperlunya
document.addEventListener('DOMContentLoaded', function () {

    // 1) Tombol menu untuk layar kecil
    var tombol = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    if (tombol && sidebar) {
        tombol.addEventListener('click', function () {
            sidebar.classList.toggle('terbuka');
        });
    }

    // 2) Konfirmasi sebelum submit (mis. hapus): <form data-confirm="Pesan">
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                ev.preventDefault();
            }
        });
    });

    // 3) Form sesi belajar: tampilkan pilihan tugas / skill sesuai jenis sesi
    var jenis = document.getElementById('jenis');
    if (jenis) {
        var barisTugas = document.getElementById('baris-tugas');
        var barisSkill = document.getElementById('baris-skill');
        var atur = function () {
            if (barisTugas) { barisTugas.style.display = jenis.value === 'Mengerjakan Tugas' ? '' : 'none'; }
            if (barisSkill) { barisSkill.style.display = jenis.value === 'Latihan Skill' ? '' : 'none'; }
        };
        jenis.addEventListener('change', atur);
        atur();
    }

    // 4) Form sesi belajar: jika waktu mulai diubah, waktu selesai otomatis +1 jam
    var mulai = document.getElementById('waktu_mulai');
    var selesai = document.getElementById('waktu_selesai');
    if (mulai && selesai) {
        mulai.addEventListener('change', function () {
            if (!mulai.value) { return; }
            var d = new Date(mulai.value);
            if (isNaN(d.getTime())) { return; }
            d.setHours(d.getHours() + 1);
            var p = function (n) { return String(n).padStart(2, '0'); };
            selesai.value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) +
                'T' + p(d.getHours()) + ':' + p(d.getMinutes());
        });
    }

    // 5) Tampilkan nilai slider progres skill
    var slider = document.getElementById('progres');
    var nilai = document.getElementById('progres-nilai');
    if (slider && nilai) {
        slider.addEventListener('input', function () { nilai.textContent = slider.value + '%'; });
    }
});
