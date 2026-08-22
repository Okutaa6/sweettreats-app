-- Sapu baris sys_user_position yang BERAKHIR SEBELUM DIMULAI.
--
-- §5 docs/rencana/rencana-libur-cabang-bertugas.md.
--
-- Panjangnya nol, jadi tak pernah terpilih activeOn() dan selama ini tak
-- berbahaya. Yang berubah 2026-08-21: penempatan ikut menentukan HARI LIBUR,
-- dan libur menentukan gaji. Rentang rusak yang lolos diam-diam jadi jauh
-- lebih mahal — dan sejak hari ini UserPosition::booted() menolaknya di titik
-- simpan, jadi baris lama seperti ini akan MELEDAK begitu ada kode yang
-- memuat lalu menyimpannya kembali.
--
-- Asalnya: rollingRemove/positionRemove menutup penempatan dengan
-- `effective_to = kemarin`. Untuk baris yang MULAI HARI ITU JUGA, "kemarin"
-- lebih awal daripada hari mulainya. Sudah ditutup di kode (akhiriSekarang()
-- menghapus, bukan menyimpan cacat) — ini membersihkan yang telanjur ada.
--
-- Prod per 2026-08-21: DUA baris —
--   a2821d77-… Verini,          rolling Cinere, 15 Agu → 14 Agu
--   a27346ed-… (user tak ada),  home Bintaro,   08 Agu → 07 Agu
-- Yang kedua lahir dari jalur HAPUS USER (UserManagement): user dihapus di hari
-- yang sama dengan lahirnya posisi, lalu ditutup "kemarin" — dan usernya ikut
-- hilang sehingga tak ada lagi yang bisa menjelaskan barisnya. Jalur itu sudah
-- ditutup di kode.
--
-- ⚠️ TABEL LAIN, di luar lingkup sapu ini: `att_user_rule` prod juga punya 2
-- baris terbalik (id 8 Irma, id 9 Anisa — FO-Asdent 5 Agu → 4 Agu, lahir saat
-- keduanya dipindah ke "Fleksibel Operasional" 6 Agu). Akibatnya nyata: 5 Agu
-- mereka tak punya aturan kerja sama sekali, sehingga scan Anisa hari itu tak
-- pernah jadi hari kerja. JANGAN disapu tanpa keputusan — menambalnya mengubah
-- siapa yang dinilai pada 5 Agu dan bisa memunculkan alpa baru.
--
-- Aman diulang.

-- Lihat dulu sebelum menghapus (jalankan terpisah kalau mau memeriksa):
--   SELECT p.id, u.nama, p.kind, p.raw_branch_id, p.effective_from, p.effective_to
--   FROM sys_user_position p JOIN users u ON u.id = p.user_id
--   WHERE p.effective_to IS NOT NULL AND p.effective_to < p.effective_from;

DELETE FROM sys_user_position
WHERE effective_to IS NOT NULL
  AND effective_to < effective_from;
