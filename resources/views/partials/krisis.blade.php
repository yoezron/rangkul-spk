{{-- Kotak bantuan segera, tampil bila jawaban anggota menunjukkan kemungkinan risiko. --}}
<div class="catatan galat" role="alert" style="color: inherit">
    <strong>Anda tidak sendirian, dan keselamatan Anda yang terpenting.</strong>
    <p style="margin: 8px 0">
        Peer konselor SPK Rangkul bukan layanan gawat darurat. Bila Anda merasa tidak aman, berada dalam bahaya,
        atau terpikir untuk mengakhiri hidup, mohon segera hubungi bantuan langsung atau orang yang Anda percaya.
    </p>
    @if ($kontak['darurat'] !== '')
        <p style="margin: 0 0 8px">{!! nl2br(e($kontak['darurat'])) !!}</p>
    @endif
    @if ($kontak['whatsapp_tautan'])
        <p style="margin: 0">Tim SPK Rangkul: <a href="{{ $kontak['whatsapp_tautan'] }}" rel="noreferrer noopener" target="_blank">WhatsApp {{ $kontak['whatsapp'] }}</a></p>
    @endif
    <p style="margin: 8px 0 0" class="kecil">Permintaan Anda akan ditandai mendesak sehingga tim komite segera menghubungi Anda setelah dikirim.</p>
</div>
