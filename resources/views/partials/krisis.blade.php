{{-- Kotak bantuan segera, tampil bila jawaban anggota menunjukkan kemungkinan risiko. --}}
<div class="mb-6 overflow-hidden rounded-2xl border border-bahaya-garis bg-bahaya-lembut" role="alert">
    <div class="flex gap-3 p-5">
        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-bahaya/15 text-bahaya"><x-ikon nama="hati" class="size-5" /></span>
        <div class="text-sm text-teks">
            <strong class="block text-base text-bahaya">Anda tidak sendirian, dan keselamatan Anda yang terpenting.</strong>
            <p class="mt-1.5 mb-2">
                Peer konselor SPK Rangkul bukan layanan gawat darurat. Bila Anda merasa tidak aman, berada dalam bahaya,
                atau terpikir untuk mengakhiri hidup, mohon segera hubungi bantuan langsung atau orang yang Anda percaya.
            </p>
            @if ($kontak['darurat'] !== '')
                <p class="mb-2 font-semibold">{!! nl2br(e($kontak['darurat'])) !!}</p>
            @endif
            @if ($kontak['whatsapp_tautan'])
                <p class="mb-2">Tim SPK Rangkul: <a href="{{ $kontak['whatsapp_tautan'] }}" rel="noreferrer noopener" target="_blank">WhatsApp {{ $kontak['whatsapp'] }}</a></p>
            @endif
            <p class="m-0 text-[13px] text-redup">Permintaan Anda akan ditandai mendesak sehingga tim komite segera menghubungi Anda setelah dikirim.</p>
        </div>
    </div>
</div>
