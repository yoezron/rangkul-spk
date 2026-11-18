:root { --latar: #f6f4f1; --kartu: #ffffff; --teks: #2b2a28; --redup: #6b6862; --garis: #e4e0da; --utama: #1f6f6b; --utama-gelap: #175653; --peringatan-latar: #fdf3e2; --peringatan-garis: #e8c98c; --info-latar: #e9f4f3; --info-garis: #b5d8d5; --galat: #a8231b; --galat-latar: #fdecea; --galat-garis: #f0b8b3; }
@media (prefers-color-scheme: dark) {
    :root { --latar: #1b1c1c; --kartu: #252727; --teks: #ecebe8; --redup: #a5a29c; --garis: #3a3d3c; --utama: #4fb3ad; --utama-gelap: #6cc5c0; --peringatan-latar: #3a3020; --peringatan-garis: #7a6332; --info-latar: #203634; --info-garis: #2f5a56; --galat: #f2a19a; --galat-latar: #3d2220; --galat-garis: #7a3a35; }
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--latar); color: var(--teks); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
h1 { margin: 0 0 4px; font-size: 1.5rem; line-height: 1.3; }
h2 { margin: 0 0 12px; font-size: 1.125rem; line-height: 1.4; }
p { margin: 0 0 16px; }
a { color: var(--utama); }
.redup { color: var(--redup); font-size: .9375rem; }
.kecil { font-size: .875rem; }
.catatan { border: 1px solid var(--peringatan-garis); background: var(--peringatan-latar); border-radius: 10px; padding: 12px 14px; margin: 0 0 20px; }
.catatan.info { border-color: var(--info-garis); background: var(--info-latar); }
.catatan.galat { border-color: var(--galat-garis); background: var(--galat-latar); color: var(--galat); }
.catatan ul { margin: 0; padding-left: 20px; }
.tombol { display: inline-block; padding: 10px 16px; border: 0; border-radius: 10px; background: var(--utama); color: #fff; font: inherit; font-weight: 600; text-align: center; text-decoration: none; cursor: pointer; }
.tombol:hover, .tombol:focus-visible { background: var(--utama-gelap); }
.tombol.sekunder { background: transparent; color: var(--teks); border: 1px solid var(--garis); font-weight: 500; }
.tombol.sekunder:hover, .tombol.sekunder:focus-visible { background: var(--latar); }
.tombol.bahaya { background: transparent; color: var(--galat); border: 1px solid var(--galat-garis); font-weight: 500; }
.tombol.bahaya:hover, .tombol.bahaya:focus-visible { background: var(--galat-latar); }
.tombol.ringkas { padding: 5px 10px; font-size: .875rem; border-radius: 8px; }
.tombol.lebar { display: block; width: 100%; }
@media (prefers-color-scheme: dark) { .tombol { color: #10201f; } .tombol.sekunder { color: var(--teks); } .tombol.bahaya { color: var(--galat); } }
label { display: block; font-weight: 600; font-size: .9375rem; margin: 0 0 4px; }
input[type=text], input[type=email], input[type=number], input[type=password], input[type=search], select, textarea { width: 100%; padding: 9px 12px; border: 1px solid var(--garis); border-radius: 8px; background: var(--kartu); color: var(--teks); font: inherit; }
textarea { resize: vertical; }
.bidang { margin-bottom: 16px; }
.bantuan { font-size: .8125rem; color: var(--redup); margin: 4px 0 0; }
label.pilihan { display: flex; gap: 8px; align-items: flex-start; font-weight: 400; }
label.pilihan input { margin-top: 5px; }
label.pilihan.mati { color: var(--redup); }
fieldset { border: 1px solid var(--garis); border-radius: 12px; padding: 16px 18px 4px; margin: 0 0 20px; min-width: 0; }
legend { font-weight: 700; padding: 0 6px; }
table { width: 100%; border-collapse: collapse; font-size: .9375rem; }
th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--garis); vertical-align: top; }
th { font-size: .8125rem; color: var(--redup); font-weight: 600; }
.gulir { overflow-x: auto; }
.lencana { display: inline-block; padding: 1px 8px; border-radius: 999px; background: var(--info-latar); border: 1px solid var(--info-garis); font-size: .8125rem; margin: 0 4px 4px 0; white-space: nowrap; }
.lencana.peringatan { background: var(--peringatan-latar); border-color: var(--peringatan-garis); }
.baris-aksi { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.baris-aksi form { margin: 0; }
