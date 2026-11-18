@if ($kontak['darurat'] !== '')
    <div class="mb-4 flex gap-3 rounded-2xl border border-aksen/30 bg-aksen-lembut p-4 text-sm" role="note">
        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-aksen/15 text-aksen"><x-ikon nama="hati" class="size-5" /></span>
        <span>
            <strong class="block text-teks">Dalam keadaan darurat</strong>
            <span class="text-teks/85">{!! nl2br(e($kontak['darurat'])) !!}</span>
        </span>
    </div>
@endif

@if ($kontak['email'] !== '' || $kontak['whatsapp'] !== '' || $kontak['jam_layanan'] !== '')
    <div class="rounded-2xl border border-garis bg-permukaan p-4 text-sm">
        <strong class="mb-2 block text-teks">Kontak SPK Rangkul</strong>
        <ul class="space-y-1.5 text-redup">
            @if ($kontak['email'] !== '')
                <li class="flex items-center gap-2"><x-ikon nama="surel" class="size-4" /> Surel: <a href="mailto:{{ $kontak['email'] }}">{{ $kontak['email'] }}</a></li>
            @endif
            @if ($kontak['whatsapp'] !== '')
                <li class="flex items-center gap-2"><x-ikon nama="telepon" class="size-4" /> WhatsApp:
                    @if ($kontak['whatsapp_tautan'])
                        <a href="{{ $kontak['whatsapp_tautan'] }}" rel="noreferrer noopener" target="_blank">{{ $kontak['whatsapp'] }}</a>
                    @else
                        {{ $kontak['whatsapp'] }}
                    @endif
                </li>
            @endif
            @if ($kontak['jam_layanan'] !== '')
                <li class="flex items-center gap-2"><x-ikon nama="jam" class="size-4" /> Jam layanan: {{ $kontak['jam_layanan'] }}</li>
            @endif
        </ul>
    </div>
@endif
