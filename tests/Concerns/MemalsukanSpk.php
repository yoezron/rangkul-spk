<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Tiruan sisi spk.or.id untuk pengujian: penerbit token masuk dan endpoint
 * data anggota. Token dibuat mengikuti rangkul_sso_token() di situs induk,
 * tanpa meminjam kode pemeriksa yang sedang diuji.
 */
trait MemalsukanSpk
{
    protected const RAHASIA = 'rahasia-uji-0123456789abcdef0123456789abcdef0123456789abcdef';

    protected const ALAMAT_SPK = 'https://spk.test/';

    protected function siapkanSpk(): void
    {
        config([
            'services.spk.url' => self::ALAMAT_SPK,
            'services.spk.sso_secret' => self::RAHASIA,
        ]);

        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $klaim  menimpa klaim bawaan
     */
    protected function tokenSso(array $klaim = [], string $rahasia = self::RAHASIA): string
    {
        $kini = now()->timestamp;

        $muatan = $this->base64Url(json_encode($klaim + [
            'v' => 1,
            'aud' => 'rangkul',
            'uid' => 18,
            'iat' => $kini,
            'exp' => $kini + 120,
            'nonce' => bin2hex(random_bytes(12)),
        ]));

        return $muatan.'.'.$this->base64Url(hash_hmac('sha256', 'sso.'.$muatan, $rahasia, true));
    }

    protected function alamatSso(string $token): string
    {
        return '/sso/spk?t='.rawurlencode($token);
    }

    /**
     * @param  array<string, mixed>  $anggota  menimpa data anggota bawaan
     * @return array<string, mixed>
     */
    protected function jawabanBerhak(array $anggota = [], int $sesiValidSejak = 0): array
    {
        return [
            'ok' => true,
            'waktu' => now()->timestamp,
            'sesi_valid_sejak' => $sesiValidSejak,
            'anggota' => $anggota + [
                'id' => 18,
                'berhak' => true,
                'alasan' => '',
                'nomor_anggota' => '1695605719',
                'nama' => 'Sri Wahyuni',
                'email' => 'sri@kampus.test',
                'telp' => '081200000018',
                'gender' => 'perempuan',
                'kampus' => 'Universitas Uji',
                'wilayah' => 'Jawa Barat',
                'peran' => 'Anggota',
                'foto' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function jawabanTidakBerhak(string $alasan, ?int $id = 18): array
    {
        return [
            'ok' => true,
            'waktu' => now()->timestamp,
            'sesi_valid_sejak' => 0,
            'anggota' => ['id' => $id, 'berhak' => false, 'alasan' => $alasan],
        ];
    }

    /**
     * @param  array<string, mixed>  $badan
     */
    protected function palsukanEndpoint(array $badan, int $status = 200): void
    {
        Http::fake(['spk.test/rangkul/anggota*' => Http::response($badan, $status)]);
    }

    protected function palsukanEndpointPutus(): void
    {
        Http::fake(['spk.test/rangkul/anggota*' => Http::failedConnection()]);
    }

    /**
     * Permintaan ke endpoint ditandatangani sesuai kontrak bagian 4.2.
     */
    protected function assertEndpointDitanyaTentang(int $uid): void
    {
        Http::assertSent(function (Request $permintaan) use ($uid): bool {
            $waktu = $permintaan->header('X-Rangkul-Waktu')[0] ?? '';

            return $permintaan->method() === 'GET'
                && $permintaan->url() === self::ALAMAT_SPK.'rangkul/anggota?uid='.$uid
                && $waktu === (string) now()->timestamp
                && ($permintaan->header('X-Rangkul-Tanda')[0] ?? '') === hash_hmac('sha256', "anggota\n{$uid}\n{$waktu}", self::RAHASIA);
        });
    }

    /**
     * Isi sesi yang ditinggalkan pintu SSO setelah masuk.
     *
     * @return array<string, int>
     */
    protected function sesiSso(?int $mulai = null, ?int $diperiksa = null): array
    {
        return [
            'sso.mulai' => $mulai ?? now()->timestamp,
            'sso.diperiksa_pada' => $diperiksa ?? now()->timestamp,
        ];
    }

    /**
     * Masuk sebagai pengguna ini dengan sesi yang baru saja diperiksa ke spk.or.id.
     */
    protected function masukSebagai(User $pengguna): static
    {
        return $this->actingAs($pengguna)->withSession($this->sesiSso());
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
