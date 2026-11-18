{{--
    Percakapan anggota–konselor. Pesan dimuat lewat JavaScript dan diperbarui
    tiap 10 detik; teks pesan selalu dipasang sebagai textContent (tidak
    pernah sebagai HTML).

    @param \App\Models\Permintaan $permintaan
    @param bool $bolehKirim
    @param string $keterangan
--}}
<section class="kartu" id="percakapan">
    <h2 class="flex items-center gap-2"><x-ikon nama="chat" class="size-5 text-utama" /> Percakapan</h2>
    @if ($keterangan ?? null)
        <p class="redup kecil">{{ $keterangan }}</p>
    @endif

    <div class="daftar-pesan" id="daftar-pesan" data-url="{{ route('percakapan.daftar', $permintaan) }}" aria-live="polite">
        <p class="redup kecil" data-kosong>Memuat percakapan…</p>
    </div>
    <noscript><p class="catatan">Percakapan membutuhkan JavaScript. Aktifkan JavaScript di peramban Anda.</p></noscript>

    @if ($bolehKirim)
        <form class="mt-2.5" method="POST" action="{{ route('percakapan.kirim', $permintaan) }}" id="formulir-pesan">
            @csrf
            <label for="isi-pesan" class="kecil">Tulis pesan</label>
            <textarea id="isi-pesan" name="isi" rows="3" maxlength="5000" required></textarea>
            <div class="baris-aksi mt-2 justify-between">
                <span class="redup kecil">Ctrl + Enter untuk mengirim</span>
                <button type="submit" class="tombol">Kirim</button>
            </div>
        </form>
    @endif

    <script>
        (() => {
            const wadah = document.getElementById('daftar-pesan');
            const formulir = document.getElementById('formulir-pesan');
            let terakhir = 0;
            let sedangMuat = false;

            function tambah(pesan) {
                wadah.querySelector('[data-kosong]')?.remove();
                const gelembung = document.createElement('div');
                gelembung.className = 'gelembung ' + (pesan.sistem ? 'sistem' : (pesan.milikSaya ? 'saya' : 'lawan'));
                const kepala = document.createElement('div');
                kepala.className = 'kepala-pesan';
                kepala.textContent = pesan.pengirim + ' · ' + pesan.waktu;
                const isi = document.createElement('div');
                isi.className = 'isi-pesan';
                isi.textContent = pesan.isi;
                gelembung.append(kepala, isi);
                wadah.append(gelembung);
            }

            async function muat() {
                if (sedangMuat) return;
                sedangMuat = true;
                try {
                    const jawaban = await fetch(wadah.dataset.url + '?setelah=' + terakhir, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (jawaban.status === 401 || jawaban.redirected) { window.location.reload(); return; }
                    if (!jawaban.ok) return;
                    const data = await jawaban.json();
                    const diBawah = wadah.scrollHeight - wadah.scrollTop - wadah.clientHeight < 60;
                    data.pesan.forEach((pesan) => { tambah(pesan); terakhir = Math.max(terakhir, pesan.id); });
                    if (terakhir === 0 && wadah.querySelector('[data-kosong]')) {
                        wadah.querySelector('[data-kosong]').textContent = 'Belum ada pesan.';
                    }
                    if (data.pesan.length && (diBawah || terakhir === data.pesan[data.pesan.length - 1].id)) {
                        wadah.scrollTop = wadah.scrollHeight;
                    }
                } catch (galat) {
                    // Jaringan putus sesaat: coba lagi pada putaran berikutnya.
                } finally {
                    sedangMuat = false;
                }
            }

            if (formulir) {
                const kotak = formulir.querySelector('textarea');
                kotak.addEventListener('keydown', (kejadian) => {
                    if (kejadian.key === 'Enter' && (kejadian.ctrlKey || kejadian.metaKey)) { formulir.requestSubmit(); }
                });
                formulir.addEventListener('submit', async (kejadian) => {
                    kejadian.preventDefault();
                    if (!kotak.value.trim()) return;
                    const tombol = formulir.querySelector('button');
                    tombol.disabled = true;
                    try {
                        const jawaban = await fetch(formulir.action, { method: 'POST', body: new FormData(formulir), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (jawaban.ok) { kotak.value = ''; await muat(); wadah.scrollTop = wadah.scrollHeight; }
                        else { formulir.submit(); }
                    } finally {
                        tombol.disabled = false;
                    }
                });
            }

            muat();
            setInterval(muat, 10000);
        })();
    </script>
</section>
