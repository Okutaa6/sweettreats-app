# Rapor Adopsi Modul — prod, 7 Agustus 2026

> **Untuk apa dokumen ini**: bahan evaluasi internal untuk memutuskan prioritas —
> modul mana yang benar-benar dipakai orang, mana yang dibangun tapi mangkrak.
> **Bukan** untuk menilai kinerja siapa pun.
>
> **Cara ambil datanya**: murni `SELECT` ke `information_schema` + `COUNT(*)`
> pada tabel tertentu. **Tidak ada satu baris data pun yang dibuka** — semua
> angka di sini adalah jumlah baris dan tanggal, tanpa nama pasien, nomor
> telepon, atau nominal. Semua query berhasil, tidak ada yang gagal.
>
> Basis: 137 tabel (10 tabel sistem Laravel dikecualikan: migrations, sessions,
> cache, jobs, dst).

## 1. Ringkasan status

| Status | Jumlah |
|---|---|
| 1. NOL | 33 |
| 2. SEKARAT (<10 baris) | 28 |
| 3. BEKU (>30 hari tak ditulis) | 1 |
| 4. MELAMBAT (>7 hari) | 8 |
| 5. HIDUP | 67 |

⚠️ Angka HIDUP **terlalu optimistis** — 12 tabel salah kelas, lihat §5.
Yang benar-benar hidup sekitar **55**.

## 2. Per modul

| Modul | Hidup | Melambat | Beku | Sekarat | Nol | Vonis |
|---|---|---|---|---|---|---|
| `omnicare_*` | 10 | – | – | 1 | – | **paling hidup** |
| `raw_*` (CRM inti) | 16 | 5 | 1 | 4 | 5 | hidup, ada sampah |
| `att_*` (absensi/cuti) | 9 | – | – | 5 | 6 | hidup |
| `sys_*` (checklist/akses) | 6 | – | – | 6 | 6 | checklist hidup, AC mati |
| `ep_*` (editorial plan) | 3 | – | – | 1 | 4 | separuh mangkrak |
| `accurate_*` | 2 | 2 | – | 2 | 3 | pause, sesuai rencana |
| `petty_*` | 2 | – | – | – | – | hidup tipis |
| `rop_*` | – | – | – | 4 | – | baru lahir, belum diadopsi |
| `cash_deposit_*` | – | – | – | – | 2 | **mangkrak total** |
| `commission_*` | – | – | – | – | 2 | **mangkrak total** |
| `transport_cost_*` | – | – | – | 1 | 1 | mangkrak |
| `temp_*` & sampah | 6 | – | – | – | 1 | **sampah, bukan fitur** |

## 3. Daftar lengkap

### HIDUP — ditulis ≤7 hari (baris · terakhir ditulis · KB)

`omnicare_sales_detail` 133.383 · 6 Agu · 267.152 · `omnicare_commission` 80.911 · 6 Agu · 109.328 ·
`omnicare_patient_branch` 80.573 · 6 Agu · 7.200 · `omnicare_sales_payments` 58.148 · 6 Agu · 87.296 ·
`omnicare_medical_record` 44.945 · 6 Agu · 97.024 · `raw_chat` 27.139 · **7 Agu** · 37.024 ·
`raw_customer` 15.277 · **7 Agu** · 11.952 · `omnicare_patient` 14.126 · 6 Agu · 30.704 ·
`raw_contact` 13.190 · **7 Agu** · 4.128 · `customer_omnicare_link` 11.994 · 6 Agu · 8.256 ·
`raw_cus_reservation` 4.516 · **7 Agu** · 5.168 · `raw_cus_res_service` 4.423 · **7 Agu** · 3.328 ·
`raw_cus_journey` 4.224 · **7 Agu** · 2.416 · `reservation_omnicare_link` 3.564 · 6 Agu · 2.896 ·
`omnicare_sync_log` 2.700 · 6 Agu · 624 · `accurate_push_log` 2.617 · 6 Agu · 528 ·
`audit_log` 2.426 · **7 Agu** · 736 · `omnicare_product_sales_daily` 2.009 · 6 Agu · 480 ·
`accurate_staging_glaccount` 1.105 · 4 Agu · 1.616 · `group_access` 405 · 5 Agu · 224 ·
`sys_checklist_audit_log` 320 · **7 Agu** · 272 · `raw_doctor_service` 258 · **7 Agu** · 208 ·
`sys_checklist_task` 241 · **7 Agu** · 192 · `raw_doctor_branch_detail_duration` 223 · **7 Agu** · 192 ·
`omnicare_polyclinic` 205 · 6 Agu · 32 · `ep_konten_pillar_pivot` 162 · **7 Agu** · 128 ·
`ep_konten` 160 · **7 Agu** · 240 · `admin_notification` 160 · **7 Agu** · 144 ·
`sys_checklist_submission_item` 133 · **7 Agu** · 96 · `raw_doctor_schedule` 103 · **7 Agu** · 144 ·
`access_path` 86 · 5 Agu · 32 · `raw_doctor_branch_service_duration` 86 · **7 Agu** · 80 ·
`vendors` 75 · 4 Agu · 64 · `affiliate_content` 61 · 5 Agu · 80 ·
`att_hp_bukti` 53 · **7 Agu** · 96 · `att_scan` 53 · **7 Agu** · 96 ·
`raw_doctor_schedule_exception` 53 · **7 Agu** · 48 · `omnicare_doctor` 49 · 6 Agu · 96 ·
`sys_checklist_category` 37 · 6 Agu · 128 · `att_day` 33 · **7 Agu** · 80 ·
`user_device` 30 · **7 Agu** · 48 · `raw_doctor_leave` 28 · **7 Agu** · 48 ·
`att_work_rule_day` 28 · 6 Agu · 32 · `petty_cash_transactions` 27 · 6 Agu · 128 ·
`users` 26 · **7 Agu** · 48 · `att_user_rule` 23 · 6 Agu · 48 · `group_path` 19 · 6 Agu · 64 ·
`petty_cash_periods` 17 · 6 Agu · 64 · `sys_checklist_weekly_submission_item` 14 · 6 Agu · 96 ·
`att_branch_ip` 13 · **7 Agu** · 64 · `sys_checklist_submission` 13 · **7 Agu** · 144 ·
`att_approval_rule` 13 · 4 Agu · 48 · `marketing_promo` 12 · 31 Jul · 80 ·
`att_request` 10 · **7 Agu** · 64 · `att_flag` 10 · **7 Agu** · 80

### MELAMBAT (8)

`accurate_staging_sales_item` 3.559 · 14 Jul · `raw_cus_reservation_staging` 2.030 · 28 Jul ·
`raw_affiliate` 133 · 20 Jul · `accurate_item_account_map` 86 · 14 Jul ·
`marketing_promo_branch` 55 · 28 Jul · `raw_doctor` 35 · 12 Jul · `raw_promo` 32 · 11 Jul ·
`raw_doc_service` 12 · 10 Jul

### BEKU (1)

`raw_branch_target` — 72 baris, terakhir ditulis **7 Juli** (31 hari), 32 KB.

### SEKARAT — <10 baris (28)

`rop_cost_centre` 9 · `att_cuti_periode` 9 · `accurate_credentials` 7 · `accurate_sync_log` 7 ·
`omnicare_location` 6 · `att_work_rule_exception` 6 · `rop_status_log` 6 · `att_work_rule` 5 ·
`ep_konten_image` 5 · `raw_branch` 5 · `topup_requests` 5 · `sys_user_position` 4 ·
`att_request_approval` 4 · `sys_checklist_weekly_submission` 4 · `affiliate_reward_tier` 3 ·
`sys_checklist_division` 3 · `sys_complaint` 3 · `att_cuti_mutasi` 3 ·
`group_page_access_modes` 2 · `api_clients` 2 · `rop_requests` 2 ·
`raw_reservation_import_batch` 1 · `rop_counter` 1 · `transport_cost_periods` 1 ·
`raw_commission` 1 · `sys_checklist_weekly_day_submit` 1 · `sys_profile` 1 · `raw_cus_activity` 1

### NOL (33)

**Baru lahir — wajar masih nol**: `sys_checklist_reopen_request` (6 Agu, naik prod 7 Agu) ·
`att_request_hari` (6 Agu) · `transport_cost_transactions` (6 Agu) ·
`accurate_staging_sales_invoice` / `_purchase_invoice` / `_cashbank` (4 Agu, proyek pause)

**Benar-benar mangkrak**: `user_approval` (18 Feb — **paling tua**) ·
`affiliate_reward_claim` (19 Apr) · `commission_claim_batch` / `_item` (6 Mei) ·
`ep_campaign_framework` / `ep_campaign_week` (7 Mei) · `tableName` (12 Mei) ·
`raw_customer_dev` / `raw_contact_dev` / `raw_cus_reservation_dev` / `raw_cus_journey_dev` /
`raw_cus_res_service_dev` (19 Mei, sisa uji coba) · `fund_source_branch_accounts` (4 Jun) ·
`cash_deposit_periods` / `cash_deposit_transactions` (21 Jun) ·
`ep_breakdown` / `ep_breakdown_pillar_pivot` (21 Jun)

**Belum dipakai walau sudah live**: `sys_ac_unit` · `sys_ac_service_item` ·
`sys_ac_service_record` (Cuci AC, live 4 Agu — belum ada satu unit AC pun didaftarkan) ·
`att_period` · `att_device_user` · `att_rule_log` · `att_import_batch` · `att_approver` ·
`sys_setting` · `sys_checklist_weekly_day_excuse`

## 4. Verifikasi ulang dengan COUNT(*)

`TABLE_ROWS` pada InnoDB itu perkiraan, jadi 16 tabel yang angkanya penting
atau mencurigakan dihitung ulang. **Semua cocok** dengan perkiraannya:

| Tabel | COUNT(*) |
|---|---|
| `petty_cash_transactions` | **27** |
| `sys_checklist_submission` | 13 |
| `att_request` | 10 |
| `topup_requests` | 5 |
| `sys_checklist_weekly_submission` | 4 |
| `att_cuti_mutasi` | 3 |
| `rop_requests` | **2** |
| `rop_status_log` | 6 |
| `raw_cus_activity` | 1 |
| `att_period`, `sys_setting`, `sys_ac_service_record`, `sys_ac_unit`, `affiliate_reward_claim`, `cash_deposit_transactions`, `transport_cost_transactions` | **0** |

## 5. ⚠️ Cacat klasifikasi yang harus diketahui

Blok `CASE` pada query aslinya **tidak menangani `UPDATE_TIME IS NULL`**. Tabel
yang punya banyak baris tapi `UPDATE_TIME`-nya kosong jatuh ke `ELSE` dan dicap
**HIDUP** — padahal justru sebaliknya. Ada 12 tabel begitu, dan di situlah
kandidat mati terbesar bersembunyi:

| Tabel | COUNT(*) nyata | KB | Dibuat |
|---|---|---|---|
| `raw_customer1` | 8.224 | 5.680 | 12 Mei |
| `mytable` | 6.638 | 1.552 | 21 Mei |
| `temp` | 3.867 | 1.552 | 5 April |
| `temp_kalideres` | 2.165 | 1.552 | 12 Mei |
| `temp_cinere` | 1.260 | 320 | 19 April |
| `temp_pamulang` | 880 | 192 | 19 April |
| `tableName` | 0 | 16 | 12 Mei |

Total sekitar **10,8 MB** — jelas sisa impor/eksperimen, bukan fitur.
`raw_customer1` tampak salinan cadangan `raw_customer`.

**Catatan teknis jujur**: pada InnoDB, `UPDATE_TIME` **hilang setiap MySQL
restart** karena tidak dipersistenkan. Jadi NULL berarti "tak ada tulisan sejak
restart terakhir", **bukan** bukti mutlak "tak pernah dipakai". Karena mayoritas
tabel lain punya nilai, restart-nya sudah lama — sinyalnya kuat, tapi bukan
vonis. Ukuran yang lebih jujur untuk rapor berikutnya: `MAX(created_at)` per
tabel, yang tidak hilang saat restart.

Perbaikan untuk query berikutnya — tambahkan satu baris sebelum `ELSE`:

```sql
WHEN UPDATE_TIME IS NULL AND TABLE_ROWS >= 10 THEN '3b. TAK JELAS (UPDATE_TIME kosong)'
```

## 6. Kesimpulan

Aplikasi ini punya **inti yang sehat dan pinggiran yang menumpuk.**

Yang benar-benar hidup cuma empat modul: penjualan Omnicare, reservasi/pasien,
absensi, dan checklist. Keempatnya ditulis pada hari rapor ini dibuat, dan
menyumbang hampir seluruh volume data. Sisanya sebagian besar fitur yang
dibangun lalu tidak pernah menemukan pemakainya.

**Pola yang berulang: fitur keuangan paling sering mangkrak.** Setoran kas nol,
klaim komisi nol, biaya transport nol, petty cash cuma 27 transaksi dalam dua
bulan untuk lima cabang, ROP baru 2 pengajuan. Ini bukan soal kualitas kode —
kelihatannya proses kerja Finance yang belum berpindah ke sistem. Kalau harus
memilih satu hal untuk didorong bulan ini, **adopsi Finance** lebih berdampak
daripada membangun fitur keuangan baru.

**Pola kedua: beberapa fitur dipakai separuh.** Editorial Plan bagian kontennya
ramai (160 baris, ditulis hari ini) tapi kerangka kampanye dan breakdown-nya
kosong sama sekali. Itu sebenarnya sinyal sehat — yang berguna dipakai, yang
tidak ditinggalkan. Empat tabel kerangka kampanye itu lebih layak dihapus
daripada dirawat.

**Yang tidak perlu dikhawatirkan**: Accurate memang sengaja di-pause, dan tabel
nol yang lahir minggu ini (reopen request, tukar hari, transport) memang belum
waktunya terisi.

**Yang bisa dibereskan cepat tanpa risiko**: ~10,8 MB tabel sampah bernama
`temp*`, `mytable`, `tableName`, `raw_customer1` yang sudah berbulan-bulan tak
tersentuh. Murni kebersihan, bukan keputusan produk.

**Satu yang perlu ditanyakan ke tim**: Cuci AC sudah live sejak 4 Agustus tapi
belum ada satu unit AC pun didaftarkan. Perlu dicek apakah orangnya belum tahu,
belum sempat, atau alurnya menyulitkan.
