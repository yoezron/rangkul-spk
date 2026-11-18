@if ($kontak['darurat'] !== '')
    <div class="catatan" role="note">
        <strong>Dalam keadaan darurat</strong><br>
        {!! nl2br(e($kontak['darurat'])) !!}
    </div>
@endif

@if ($kontak['email'] !== '' || $kontak['whatsapp'] !== '' || $kontak['jam_layanan'] !== '')
    <p class="redup kecil">
        <strong>Kontak SPK Rangkul</strong>
        @if ($kontak['email'] !== '')
            <br>Surel: <a href="mailto:{{ $kontak['email'] }}">{{ $kontak['email'] }}</a>
        @endif
        @if ($kontak['whatsapp'] !== '')
            <br>WhatsApp:
            @if ($kontak['whatsapp_tautan'])
                <a href="{{ $kontak['whatsapp_tautan'] }}" rel="noreferrer noopener" target="_blank">{{ $kontak['whatsapp'] }}</a>
            @else
                {{ $kontak['whatsapp'] }}
            @endif
        @endif
        @if ($kontak['jam_layanan'] !== '')
            <br>Jam layanan: {{ $kontak['jam_layanan'] }}
        @endif
    </p>
@endif
