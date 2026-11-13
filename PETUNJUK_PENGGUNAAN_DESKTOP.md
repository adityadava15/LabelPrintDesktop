# PCB Barcode Print - Desktop Application (.exe)

Aplikasi Desktop standalone untuk sistem cetak barcode PCB, EV Charger, Nameplate, dan Custom Label.

---

## 📦 Installer & Distribusi ke PC Lain (Hanya ~16 - 26 MB!)

Untuk mencoba atau memasang aplikasi di PC lain tanpa membawa file sampah/cache browser yang besar (sebelumnya mencapai 565 MB), gunakan file yang sudah siap di dalam folder **`dist/`**:

1. **`Setup_PCBBarcodePrint.exe` (~26 MB)** *(Sangat Direkomendasikan)*:
   - Installer grafis mandiri (Setup Wizard).
   - Memilih folder tujuan (default `C:\PCBBarcodePrint`).
   - Otomatis membuat shortcut di **Desktop** dan **Start Menu**.
   - Otomatis membuat script **Uninstall.bat**.
   - Opsi langsung menjalankan aplikasi setelah selesai instalasi.

2. **`Setup_PCBBarcodePrint_UltraCompact.exe` (~16 MB)**:
   - Installer kompresi ultra-tinggi berbasis SFX. Ukuran paling kecil (~16 MB) untuk dikirim lewat chat/email/flashdisk.

3. **`PCBBarcodePrint_v2.0_Portable.zip` (~25 MB)**:
   - Paket portable tanpa instalasi. Cukup ekstrak di folder mana saja di PC lain lalu langsung jalankan `PCBBarcodePrint.exe`.

### 🔄 Cara Membuat Ulang Installer:
Cukup double-click **`Buat Installer.bat`** di folder utama. Script akan otomatis mengemas ulang aplikasi bersih dan memperbarui file installer di dalam folder `dist/`.

---

## 🚀 Cara Menjalankan

1. **Jalankan Aplikasi:**
   - Double-click file **`PCBBarcodePrint.exe`** di folder aplikasi.
   - Atau double-click **`Buat Shortcut Desktop.bat`** terlebih dahulu untuk membuat icon shortcut di Desktop Windows Anda.

2. **Jendela Aplikasi:**
   - Aplikasi akan langsung terbuka dalam jendela desktop mandiri (tanpa address bar browser dan tanpa tab).
   - Server backend lokal otomatis berjalan di latar belakang secara terisolasi tanpa perlu membuka XAMPP secara manual.

3. **Keluar dari Aplikasi:**
   - Cukup tutup jendela desktop (tombol `X` di pojok kanan atas) atau klik kanan icon di **System Tray** (pojok kanan bawah dekat jam) lalu pilih **Keluar**.
   - Server latar belakang akan otomatis dihentikan secara bersih tanpa meninggalkan proses gantung.

---

## 🖨️ Fitur Cetak In-App & Pemilihan Printer Windows

- **In-App Print Studio**: Pratinjau label, zoom in/out/reset, dan cetak langsung tanpa membuka popup web browser baru.
- **Dropdown Printer Windows**: Otomatis mendeteksi daftar printer terpasang dari *Settings > Printers & scanners* Windows dengan indikator status 🟢 *Ready* dan ⚪ *Offline*.
- **Pintasan Keyboard**: Tekan **`Enter`** atau **`Ctrl+P`** untuk langsung mencetak, dan **`Esc`** untuk menutup pratinjau.

---

## 🗄️ Konfigurasi Koneksi Database

Koneksi database telah dipusatkan di file `db_config.json` dan dapat diatur langsung lewat antarmuka grafis:

1. **Halaman Pengaturan:**
   - Buka menu **Database** di sidebar aplikasi, atau
   - Buka lewat System Tray: Klik kanan icon tray -> **Pengaturan Database**, atau
   - Jika koneksi database terputus/server offline, aplikasi akan otomatis mengarahkan ke halaman konfigurasi.

2. **Preset Cepat:**
   - **🏢 Server Kantor / Pabrik:** `192.168.88.254:3307`
   - **💻 Localhost MySQL:** `127.0.0.1:3306`
   - **⚡ Localhost Port 3307:** `127.0.0.1:3307`

3. **Fitur Pengaturan:**
   - **🔍 Test Koneksi:** Menguji konektivitas tanpa mengubah konfigurasi.
   - **💾 Simpan Konfigurasi:** Menyimpan host, port, username, password, dan nama database.
   - **📥 Import test.sql:** Mengimpor struktur tabel dan data default jika menggunakan database lokal baru.

---

## 📂 Struktur File Penting

- **`dist/`**: Berisi installer `Setup_PCBBarcodePrint.exe` dan paket portable.
- **`Buat Installer.bat`**: Script 1-klik untuk membuat ulang file installer.
- **`Installer.cs`**: Source code installer C# WinForms.
- **`PCBBarcodePrint.exe`**: Executable desktop utama (C# WinExe berkecepatan tinggi, DPI-aware).
- **`Launcher.cs`**: Source code launcher desktop (dikompilasi lewat `build.bat`).
- **`build.bat`**: Script 1-klik untuk mengompilasi ulang `PCBBarcodePrint.exe`.
- **`Buat Shortcut Desktop.bat`**: Script untuk membuat shortcut di Desktop Windows secara otomatis.
- **`php/`**: Runtime portable PHP bawaan (modul `mysqli`, `pdo_mysql`, `mbstring`, `gd`, `openssl`).
- **`db_config.php` & `db_config.json`**: Pengaturan database terpusat dengan mekanisme timeout cepat (3 detik).
- **`db_setup.php`**: Antarmuka web untuk konfigurasi database & import skema.
- **`view.php` & `userview.php`**: Modul utama admin dan operator cetak label barcode.
- **`api_printers.php`**: Endpoint deteksi printer Windows real-time.
- **`test.sql`**: Dump database skema dan data acuan.
