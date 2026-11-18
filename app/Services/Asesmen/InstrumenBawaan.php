<?php

namespace App\Services\Asesmen;

/**
 * Instrumen asesmen dasar bawaan SPK Rangkul.
 *
 * Kelimanya bebas dipakai tanpa lisensi berbayar. Teks butirnya terjemahan
 * kerja; komite sebaiknya menggantinya dengan versi bahasa Indonesia yang
 * sudah divalidasi bila tersedia, karena ambang skor bergantung pada
 * terjemahan yang setara.
 *
 * Semua instrumen adalah alat SKRINING, bukan diagnosis.
 */
final class InstrumenBawaan
{
    private const FREKUENSI_2_MINGGU = [
        ['label' => 'Tidak pernah sama sekali', 'nilai' => 0],
        ['label' => 'Beberapa hari', 'nilai' => 1],
        ['label' => 'Lebih dari separuh waktu', 'nilai' => 2],
        ['label' => 'Hampir setiap hari', 'nilai' => 3],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function semua(): array
    {
        return [self::phq9(), self::gad7(), self::cbi(), self::srq20(), self::dass21()];
    }

    /**
     * @param  list<string>  $teks
     * @param  array<int, array<string, mixed>>  $tambahan  data tambahan per nomor butir (mulai 1)
     * @return list<array<string, mixed>>
     */
    private static function butir(string $awalan, array $teks, array $tambahan = []): array
    {
        $hasil = [];

        foreach ($teks as $indeks => $kalimat) {
            $nomor = $indeks + 1;
            $hasil[] = array_merge([
                'kunci' => $awalan.$nomor,
                'teks' => $kalimat,
                'subskala' => '',
                'terbalik' => false,
                'kritis_min' => null,
                'pilihan' => null,
            ], $tambahan[$nomor] ?? []);
        }

        return $hasil;
    }

    /**
     * @return array<string, mixed>
     */
    private static function phq9(): array
    {
        return [
            'kunci' => 'phq9',
            'nama' => 'PHQ-9 — gejala depresi',
            'instruksi' => 'Selama dua minggu terakhir, seberapa sering Anda terganggu oleh hal-hal berikut?',
            'pilihan' => self::FREKUENSI_2_MINGGU,
            'metode' => 'jumlah',
            'pengali' => 1,
            'subskala' => [],
            'rentang' => [
                ['subskala' => '', 'min' => 0, 'max' => 4, 'label' => 'Minimal', 'saran' => 'Pemantauan biasa.'],
                ['subskala' => '', 'min' => 5, 'max' => 9, 'label' => 'Ringan', 'saran' => 'Dukungan dan pemantauan; ulangi skrining bila keluhan berlanjut.'],
                ['subskala' => '', 'min' => 10, 'max' => 14, 'label' => 'Sedang', 'saran' => 'Pendampingan; pertimbangkan rujukan ke psikolog.'],
                ['subskala' => '', 'min' => 15, 'max' => 19, 'label' => 'Cukup berat', 'saran' => 'Disarankan rujukan ke psikolog atau psikiater.'],
                ['subskala' => '', 'min' => 20, 'max' => 27, 'label' => 'Berat', 'saran' => 'Segera rujuk ke psikolog atau psikiater.'],
            ],
            'butir' => self::butir('phq', [
                'Kurang tertarik atau kurang bergairah dalam melakukan apa pun',
                'Merasa murung, sedih, atau putus asa',
                'Sulit tidur, mudah terbangun, atau terlalu banyak tidur',
                'Merasa lelah atau kurang bertenaga',
                'Kurang nafsu makan atau terlalu banyak makan',
                'Merasa buruk tentang diri sendiri — merasa gagal, atau telah mengecewakan diri sendiri atau keluarga',
                'Sulit berkonsentrasi, misalnya saat membaca atau menonton televisi',
                'Bergerak atau berbicara sangat lambat sehingga orang lain memperhatikannya; atau sebaliknya, sangat gelisah sehingga lebih sering bergerak dari biasanya',
                'Pikiran bahwa lebih baik Anda mati, atau ingin menyakiti diri sendiri dengan cara apa pun',
            ], [9 => ['kritis_min' => 1]]),
            'sumber' => 'PHQ-9 (Kroenke, Spitzer & Williams), dikembangkan dengan hibah Pfizer. Bebas digunakan tanpa izin. Terjemahan kerja.',
            'aktif' => true,
            'urutan' => 10,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function gad7(): array
    {
        return [
            'kunci' => 'gad7',
            'nama' => 'GAD-7 — gejala kecemasan',
            'instruksi' => 'Selama dua minggu terakhir, seberapa sering Anda terganggu oleh hal-hal berikut?',
            'pilihan' => self::FREKUENSI_2_MINGGU,
            'metode' => 'jumlah',
            'pengali' => 1,
            'subskala' => [],
            'rentang' => [
                ['subskala' => '', 'min' => 0, 'max' => 4, 'label' => 'Minimal', 'saran' => 'Pemantauan biasa.'],
                ['subskala' => '', 'min' => 5, 'max' => 9, 'label' => 'Ringan', 'saran' => 'Dukungan dan pemantauan.'],
                ['subskala' => '', 'min' => 10, 'max' => 14, 'label' => 'Sedang', 'saran' => 'Pendampingan; pertimbangkan rujukan ke psikolog.'],
                ['subskala' => '', 'min' => 15, 'max' => 21, 'label' => 'Berat', 'saran' => 'Disarankan rujukan ke psikolog atau psikiater.'],
            ],
            'butir' => self::butir('gad', [
                'Merasa gugup, cemas, atau tegang',
                'Tidak mampu menghentikan atau mengendalikan rasa khawatir',
                'Terlalu mengkhawatirkan berbagai hal',
                'Sulit untuk bersantai',
                'Sangat gelisah sehingga sulit untuk duduk diam',
                'Mudah kesal atau mudah tersinggung',
                'Merasa takut seolah-olah sesuatu yang buruk akan terjadi',
            ]),
            'sumber' => 'GAD-7 (Spitzer, Kroenke, Williams & Löwe), dikembangkan dengan hibah Pfizer. Bebas digunakan tanpa izin. Terjemahan kerja.',
            'aktif' => true,
            'urutan' => 20,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cbi(): array
    {
        $derajat = [
            ['label' => 'Sangat tinggi', 'nilai' => 100],
            ['label' => 'Tinggi', 'nilai' => 75],
            ['label' => 'Sedang', 'nilai' => 50],
            ['label' => 'Rendah', 'nilai' => 25],
            ['label' => 'Sangat rendah', 'nilai' => 0],
        ];

        return [
            'kunci' => 'cbi_kerja',
            'nama' => 'Burnout terkait pekerjaan (CBI)',
            'instruksi' => 'Pertanyaan berikut tentang pekerjaan Anda saat ini.',
            'pilihan' => [
                ['label' => 'Selalu', 'nilai' => 100],
                ['label' => 'Sering', 'nilai' => 75],
                ['label' => 'Kadang-kadang', 'nilai' => 50],
                ['label' => 'Jarang', 'nilai' => 25],
                ['label' => 'Tidak pernah / hampir tidak pernah', 'nilai' => 0],
            ],
            'metode' => 'rerata',
            'pengali' => 1,
            'subskala' => [],
            'rentang' => [
                ['subskala' => '', 'min' => 0, 'max' => 49.99, 'label' => 'Rendah', 'saran' => 'Pemantauan biasa.'],
                ['subskala' => '', 'min' => 50, 'max' => 74.99, 'label' => 'Sedang–tinggi', 'saran' => 'Bahas beban dan kondisi kerja; pertimbangkan dukungan serikat bila terkait kebijakan kerja.'],
                ['subskala' => '', 'min' => 75, 'max' => 100, 'label' => 'Sangat tinggi', 'saran' => 'Burnout berat; pendampingan intensif dan pertimbangkan rujukan.'],
            ],
            'butir' => self::butir('cbi', [
                'Apakah pekerjaan Anda menguras emosi?',
                'Apakah Anda merasa terbakar habis (burnout) karena pekerjaan Anda?',
                'Apakah pekerjaan Anda membuat Anda frustrasi?',
                'Apakah Anda merasa letih di akhir hari kerja?',
                'Apakah Anda sudah merasa lelah di pagi hari saat memikirkan hari kerja yang akan datang?',
                'Apakah Anda merasa setiap jam kerja melelahkan bagi Anda?',
                'Apakah Anda punya cukup tenaga untuk keluarga dan teman di waktu luang?',
            ], [
                1 => ['pilihan' => $derajat],
                2 => ['pilihan' => $derajat],
                3 => ['pilihan' => $derajat],
                7 => ['terbalik' => true],
            ]),
            'sumber' => 'Copenhagen Burnout Inventory (Kristensen dkk., 2005), subskala burnout terkait kerja. Bebas digunakan. Terjemahan kerja.',
            'aktif' => true,
            'urutan' => 30,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function srq20(): array
    {
        return [
            'kunci' => 'srq20',
            'nama' => 'SRQ-20 — masalah mental emosional',
            'instruksi' => 'Jawablah sesuai keadaan Anda dalam 30 hari terakhir.',
            'pilihan' => [
                ['label' => 'Tidak', 'nilai' => 0],
                ['label' => 'Ya', 'nilai' => 1],
            ],
            'metode' => 'jumlah',
            'pengali' => 1,
            'subskala' => [],
            'rentang' => [
                ['subskala' => '', 'min' => 0, 'max' => 5, 'label' => 'Di bawah ambang', 'saran' => 'Pemantauan biasa.'],
                ['subskala' => '', 'min' => 6, 'max' => 20, 'label' => 'Di atas ambang (≥ 6)', 'saran' => 'Kemungkinan ada masalah mental emosional; perlu pendalaman dan pertimbangkan rujukan.'],
            ],
            'butir' => self::butir('srq', [
                'Apakah Anda sering merasa sakit kepala?',
                'Apakah Anda kehilangan nafsu makan?',
                'Apakah tidur Anda tidak nyenyak?',
                'Apakah Anda mudah merasa takut?',
                'Apakah tangan Anda gemetar?',
                'Apakah Anda merasa cemas, tegang, atau khawatir?',
                'Apakah pencernaan Anda terganggu?',
                'Apakah Anda sulit berpikir jernih?',
                'Apakah Anda merasa tidak bahagia?',
                'Apakah Anda lebih sering menangis?',
                'Apakah Anda merasa sulit menikmati kegiatan sehari-hari?',
                'Apakah Anda sulit mengambil keputusan?',
                'Apakah pekerjaan sehari-hari Anda terganggu?',
                'Apakah Anda merasa tidak mampu berperan dalam kehidupan ini?',
                'Apakah Anda kehilangan minat terhadap berbagai hal?',
                'Apakah Anda merasa tidak berharga?',
                'Apakah Anda mempunyai pikiran untuk mengakhiri hidup?',
                'Apakah Anda merasa lelah sepanjang waktu?',
                'Apakah Anda merasa tidak enak di perut?',
                'Apakah Anda mudah lelah?',
            ], [17 => ['kritis_min' => 1]]),
            'sumber' => 'Self-Reporting Questionnaire (SRQ-20), WHO. Ambang ≥ 6 mengikuti praktik Kemenkes RI. Terjemahan kerja.',
            'aktif' => false,
            'urutan' => 40,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function dass21(): array
    {
        $subskala = [
            1 => 's', 2 => 'a', 3 => 'd', 4 => 'a', 5 => 'd', 6 => 's', 7 => 'a',
            8 => 's', 9 => 'a', 10 => 'd', 11 => 's', 12 => 's', 13 => 'd', 14 => 's',
            15 => 'a', 16 => 'd', 17 => 'd', 18 => 's', 19 => 'a', 20 => 'a', 21 => 'd',
        ];

        $rentang = static fn (string $kunci, array $batas): array => array_map(
            static fn (array $satu): array => ['subskala' => $kunci, 'min' => $satu[0], 'max' => $satu[1], 'label' => $satu[2], 'saran' => $satu[3]],
            $batas,
        );

        $tingkat = static fn (array $ambang): array => [
            [$ambang[0], $ambang[1], 'Normal', 'Pemantauan biasa.'],
            [$ambang[1] + 1, $ambang[2], 'Ringan', 'Dukungan dan pemantauan.'],
            [$ambang[2] + 1, $ambang[3], 'Sedang', 'Pendampingan; pertimbangkan rujukan ke psikolog.'],
            [$ambang[3] + 1, $ambang[4], 'Berat', 'Disarankan rujukan ke psikolog atau psikiater.'],
            [$ambang[4] + 1, 42, 'Sangat berat', 'Segera rujuk ke psikolog atau psikiater.'],
        ];

        return [
            'kunci' => 'dass21',
            'nama' => 'DASS-21 — depresi, kecemasan, dan stres',
            'instruksi' => 'Seberapa sesuai pernyataan berikut dengan keadaan Anda selama seminggu terakhir?',
            'pilihan' => [
                ['label' => 'Tidak sesuai sama sekali / tidak pernah', 'nilai' => 0],
                ['label' => 'Sesuai sampai tingkat tertentu / kadang-kadang', 'nilai' => 1],
                ['label' => 'Cukup sesuai / cukup sering', 'nilai' => 2],
                ['label' => 'Sangat sesuai / sering sekali', 'nilai' => 3],
            ],
            'metode' => 'jumlah',
            'pengali' => 2,
            'subskala' => ['d' => 'Depresi', 'a' => 'Kecemasan', 's' => 'Stres'],
            'rentang' => [
                ...$rentang('d', $tingkat([0, 9, 13, 20, 27])),
                ...$rentang('a', $tingkat([0, 7, 9, 14, 19])),
                ...$rentang('s', $tingkat([0, 14, 18, 25, 33])),
            ],
            'butir' => self::butir('dass', [
                'Saya merasa sulit untuk menenangkan diri',
                'Saya merasa mulut saya kering',
                'Saya sama sekali tidak dapat merasakan perasaan positif',
                'Saya mengalami kesulitan bernapas (misalnya napas cepat atau sesak padahal tidak sedang beraktivitas fisik)',
                'Saya merasa sulit berinisiatif untuk melakukan sesuatu',
                'Saya cenderung bereaksi berlebihan terhadap suatu situasi',
                'Saya merasa gemetar (misalnya pada tangan)',
                'Saya merasa menghabiskan banyak energi karena cemas',
                'Saya khawatir dengan situasi yang dapat membuat saya panik dan mempermalukan diri sendiri',
                'Saya merasa tidak ada hal yang dapat saya harapkan',
                'Saya merasa gelisah',
                'Saya merasa sulit untuk bersantai',
                'Saya merasa sedih dan tertekan',
                'Saya tidak dapat memaklumi hal apa pun yang menghalangi saya menyelesaikan pekerjaan',
                'Saya merasa hampir panik',
                'Saya tidak bisa merasa antusias terhadap hal apa pun',
                'Saya merasa tidak berharga sebagai seorang manusia',
                'Saya merasa mudah tersinggung',
                'Saya menyadari degup jantung saya walaupun tidak sedang beraktivitas fisik (misalnya berdebar-debar)',
                'Saya merasa takut tanpa alasan yang jelas',
                'Saya merasa hidup ini tidak bermakna',
            ], array_map(static fn (string $kunci): array => ['subskala' => $kunci], $subskala)),
            'sumber' => 'DASS-21 (Lovibond & Lovibond, 1995). Domain publik. Skor tiap subskala dikali 2. Terjemahan kerja.',
            'aktif' => false,
            'urutan' => 50,
        ];
    }
}
