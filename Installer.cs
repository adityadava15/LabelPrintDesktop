using System;
using System.IO;
using System.Drawing;
using System.Reflection;
using System.Diagnostics;
using System.Windows.Forms;
using System.ComponentModel;
using System.IO.Compression;
using System.Collections.Generic;
using Microsoft.Win32;

namespace PCBBarcodePrintInstaller
{
    public class InstallerForm : Form
    {
        private TextBox txtInstallPath;
        private Button btnBrowse;
        private CheckBox chkDesktopShortcut;
        private CheckBox chkStartMenuShortcut;
        private CheckBox chkLaunchAfter;
        private ProgressBar progressBar;
        private Label lblStatus;
        private Label lblInfo;
        private Panel infoCard;
        private Button btnInstall;
        private Button btnCancel;
        private BackgroundWorker worker;
        private bool isFinished = false;
        private bool lastActionWasUpdate = false;

        [STAThread]
        public static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new InstallerForm());
        }

        public InstallerForm()
        {
            InitializeUI();
        }

        private void InitializeUI()
        {
            this.Text = "PCB Barcode Print v2.4 - Setup";
            this.Size = new Size(580, 490);
            this.FormBorderStyle = FormBorderStyle.FixedDialog;
            this.MaximizeBox = false;
            this.StartPosition = FormStartPosition.CenterScreen;
            this.BackColor = Color.White;

            // Load Icon from embedded resource or file if available
            try
            {
                using (Stream iconStream = Assembly.GetExecutingAssembly().GetManifestResourceStream("favicon.ico"))
                {
                    if (iconStream != null)
                        this.Icon = new Icon(iconStream);
                    else if (File.Exists("favicon.ico"))
                        this.Icon = new Icon("favicon.ico");
                }
            }
            catch { }

            // 1. Header Banner Panel
            Panel headerPanel = new Panel
            {
                Dock = DockStyle.Top,
                Height = 90,
                BackColor = Color.FromArgb(15, 23, 42) // Dark Slate #0F172A
            };

            // App Logo Box
            PictureBox picLogo = new PictureBox
            {
                Location = new Point(20, 18),
                Size = new Size(54, 54),
                SizeMode = PictureBoxSizeMode.Zoom,
                BackColor = Color.Transparent
            };
            try
            {
                using (Stream s = Assembly.GetExecutingAssembly().GetManifestResourceStream("app_logo.png"))
                {
                    if (s != null)
                    {
                        picLogo.Image = Image.FromStream(s);
                    }
                    else if (this.Icon != null)
                    {
                        picLogo.Image = this.Icon.ToBitmap();
                    }
                }
            }
            catch
            {
                if (this.Icon != null) picLogo.Image = this.Icon.ToBitmap();
            }

            Label lblTitle = new Label
            {
                Text = "PCB Barcode Print",
                ForeColor = Color.White,
                Font = new Font("Segoe UI", 15, FontStyle.Bold),
                Location = new Point(86, 18),
                AutoSize = true
            };

            Label lblSubtitle = new Label
            {
                Text = "Sistem Cetak Barcode PCB, EV Charger, Nameplate & Custom Label",
                ForeColor = Color.FromArgb(56, 189, 248), // Sky Blue #38BDF8
                Font = new Font("Segoe UI", 9f, FontStyle.Regular),
                Location = new Point(88, 48),
                AutoSize = true
            };

            Label lblVersion = new Label
            {
                Text = "v2.4 Studio",
                ForeColor = Color.FromArgb(147, 197, 253),
                BackColor = Color.FromArgb(30, 58, 138),
                Font = new Font("Segoe UI", 8.5f, FontStyle.Bold),
                Location = new Point(460, 20),
                AutoSize = true,
                Padding = new Padding(6, 3, 6, 3)
            };

            headerPanel.Controls.Add(picLogo);
            headerPanel.Controls.Add(lblTitle);
            headerPanel.Controls.Add(lblSubtitle);
            headerPanel.Controls.Add(lblVersion);
            this.Controls.Add(headerPanel);

            // 2. Main Content Container
            Panel contentPanel = new Panel
            {
                Location = new Point(0, 90),
                Size = new Size(580, 335),
                BackColor = Color.White,
                Padding = new Padding(24, 12, 24, 10)
            };

            Label lblPathDesc = new Label
            {
                Text = "Folder Lokasi Instalasi:",
                Font = new Font("Segoe UI", 9.5f, FontStyle.Bold),
                ForeColor = Color.FromArgb(30, 41, 59),
                Location = new Point(24, 12),
                AutoSize = true
            };

            txtInstallPath = new TextBox
            {
                Text = DetectExistingInstallPath(),
                Font = new Font("Segoe UI", 10),
                Location = new Point(24, 34),
                Size = new Size(415, 26),
                BorderStyle = BorderStyle.FixedSingle
            };
            txtInstallPath.TextChanged += (s, e) => UpdateInstallModeUI();

            btnBrowse = new Button
            {
                Text = "Pilih...",
                Font = new Font("Segoe UI", 9),
                Location = new Point(448, 33),
                Size = new Size(95, 28),
                BackColor = Color.FromArgb(241, 245, 249),
                FlatStyle = FlatStyle.Flat
            };
            btnBrowse.FlatAppearance.BorderColor = Color.FromArgb(203, 213, 225);
            btnBrowse.Click += (s, e) =>
            {
                using (FolderBrowserDialog fbd = new FolderBrowserDialog())
                {
                    fbd.Description = "Pilih folder untuk instalasi / update PCB Barcode Print:";
                    fbd.SelectedPath = txtInstallPath.Text;
                    if (fbd.ShowDialog() == DialogResult.OK)
                    {
                        txtInstallPath.Text = fbd.SelectedPath;
                    }
                }
            };

            // Version info banner
            infoCard = new Panel
            {
                Location = new Point(24, 68),
                Size = new Size(519, 44),
                BackColor = Color.FromArgb(240, 249, 255)
            };
            lblInfo = new Label
            {
                Text = "✨ Versi 2.4: Studio UI Modern, Mode Offline & Database, Direct Print Tanpa Popup, dan Default Printer Software.",
                Font = new Font("Segoe UI", 8.5f, FontStyle.Regular),
                ForeColor = Color.FromArgb(3, 105, 161),
                Dock = DockStyle.Fill,
                Padding = new Padding(8, 6, 8, 6)
            };
            infoCard.Controls.Add(lblInfo);

            // Option Checkboxes
            chkDesktopShortcut = new CheckBox
            {
                Text = "Buat Shortcut di Desktop",
                Checked = true,
                Font = new Font("Segoe UI", 9.5f),
                ForeColor = Color.FromArgb(51, 65, 85),
                Location = new Point(24, 122),
                AutoSize = true
            };

            chkStartMenuShortcut = new CheckBox
            {
                Text = "Buat Shortcut di Start Menu Programs",
                Checked = true,
                Font = new Font("Segoe UI", 9.5f),
                ForeColor = Color.FromArgb(51, 65, 85),
                Location = new Point(24, 148),
                AutoSize = true
            };

            chkLaunchAfter = new CheckBox
            {
                Text = "Jalankan PCB Barcode Print setelah instalasi selesai",
                Checked = true,
                Font = new Font("Segoe UI", 9.5f),
                ForeColor = Color.FromArgb(51, 65, 85),
                Location = new Point(24, 174),
                AutoSize = true
            };

            // Status and Progress
            lblStatus = new Label
            {
                Text = "Aplikasi siap diinstal. Klik 'Instal Sekarang' untuk memulai proses.",
                Font = new Font("Segoe UI", 9f),
                ForeColor = Color.FromArgb(100, 116, 139),
                Location = new Point(24, 210),
                Size = new Size(519, 20)
            };

            progressBar = new ProgressBar
            {
                Location = new Point(24, 234),
                Size = new Size(519, 22),
                Minimum = 0,
                Maximum = 100,
                Value = 0
            };

            contentPanel.Controls.Add(lblPathDesc);
            contentPanel.Controls.Add(txtInstallPath);
            contentPanel.Controls.Add(btnBrowse);
            contentPanel.Controls.Add(infoCard);
            contentPanel.Controls.Add(chkDesktopShortcut);
            contentPanel.Controls.Add(chkStartMenuShortcut);
            contentPanel.Controls.Add(chkLaunchAfter);
            contentPanel.Controls.Add(lblStatus);
            contentPanel.Controls.Add(progressBar);
            this.Controls.Add(contentPanel);

            // 3. Bottom Action Bar
            Panel bottomPanel = new Panel
            {
                Dock = DockStyle.Bottom,
                Height = 60,
                BackColor = Color.FromArgb(248, 250, 252)
            };

            Panel borderLine = new Panel
            {
                Dock = DockStyle.Top,
                Height = 1,
                BackColor = Color.FromArgb(226, 232, 240)
            };
            bottomPanel.Controls.Add(borderLine);

            btnCancel = new Button
            {
                Text = "Batal",
                Font = new Font("Segoe UI", 9.5f),
                Location = new Point(320, 14),
                Size = new Size(95, 34),
                BackColor = Color.White,
                FlatStyle = FlatStyle.Flat
            };
            btnCancel.FlatAppearance.BorderColor = Color.FromArgb(203, 213, 225);
            btnCancel.Click += (s, e) => this.Close();

            btnInstall = new Button
            {
                Text = "Instal Sekarang",
                Font = new Font("Segoe UI", 10f, FontStyle.Bold),
                ForeColor = Color.White,
                Location = new Point(425, 14),
                Size = new Size(140, 34),
                BackColor = Color.FromArgb(37, 99, 235), // Blue #2563EB
                FlatStyle = FlatStyle.Flat,
                Cursor = Cursors.Hand
            };
            btnInstall.FlatAppearance.BorderSize = 0;
            btnInstall.Click += BtnInstall_Click;

            bottomPanel.Controls.Add(btnCancel);
            bottomPanel.Controls.Add(btnInstall);
            this.Controls.Add(bottomPanel);

            // 4. Background Worker for non-blocking install
            worker = new BackgroundWorker
            {
                WorkerReportsProgress = true
            };
            worker.DoWork += Worker_DoWork;
            worker.ProgressChanged += Worker_ProgressChanged;
            worker.RunWorkerCompleted += Worker_RunWorkerCompleted;

            // Cek status instalasi saat form pertama kali dibuat
            UpdateInstallModeUI();
        }

        private static bool IsExistingInstallation(string dir)
        {
            if (string.IsNullOrWhiteSpace(dir)) return false;
            try
            {
                if (!Directory.Exists(dir)) return false;
                return File.Exists(Path.Combine(dir, "PCBBarcodePrint.exe")) ||
                       File.Exists(Path.Combine(dir, "view.php")) ||
                       File.Exists(Path.Combine(dir, "index.php"));
            }
            catch
            {
                return false;
            }
        }

        private static string DetectExistingInstallPath()
        {
            // 1. Cek Registry lokasi instalasi sebelumnya
            try
            {
                using (RegistryKey key = Registry.CurrentUser.OpenSubKey(@"Software\PCBBarcodePrint"))
                {
                    if (key != null)
                    {
                        string regPath = key.GetValue("InstallLocation") as string;
                        if (!string.IsNullOrWhiteSpace(regPath) && IsExistingInstallation(regPath))
                        {
                            return regPath;
                        }
                    }
                }
            }
            catch { }

            // 2. Cek folder default C:\PCBBarcodePrint
            string defaultDir = @"C:\PCBBarcodePrint";
            if (IsExistingInstallation(defaultDir))
            {
                return defaultDir;
            }

            // 3. Cek dari shortcut Desktop jika sudah pernah dibuat sebelumnya
            try
            {
                string desktopPath = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                string lnkPath = Path.Combine(desktopPath, "PCB Barcode Print.lnk");
                if (File.Exists(lnkPath))
                {
                    Type shellType = Type.GetTypeFromProgID("WScript.Shell");
                    if (shellType != null)
                    {
                        dynamic shell = Activator.CreateInstance(shellType);
                        dynamic shortcut = shell.CreateShortcut(lnkPath);
                        string workDir = shortcut.WorkingDirectory as string;
                        if (!string.IsNullOrWhiteSpace(workDir) && IsExistingInstallation(workDir))
                        {
                            return workDir;
                        }
                    }
                }
            }
            catch { }

            return defaultDir;
        }

        private void UpdateInstallModeUI()
        {
            if (isFinished || txtInstallPath == null || btnInstall == null || lblStatus == null) return;

            string currentPath = txtInstallPath.Text.Trim();
            bool exists = IsExistingInstallation(currentPath);

            if (exists)
            {
                btnInstall.Text = "Update Software";
                btnInstall.BackColor = Color.FromArgb(5, 150, 105); // Emerald Green #059669
                lblStatus.Text = "Instalasi lama terdeteksi. Klik 'Update Software' untuk memperbarui aplikasi.";
                lblStatus.ForeColor = Color.FromArgb(180, 83, 9); // Amber #B45309
                if (infoCard != null) infoCard.BackColor = Color.FromArgb(254, 243, 199); // Warm Amber tint
                if (lblInfo != null)
                {
                    lblInfo.ForeColor = Color.FromArgb(146, 64, 14);
                    lblInfo.Text = "🔄 Terdeteksi PCB Barcode Print sudah terinstal di folder ini. Proses instalasi akan melakukan UPDATE (pengaturan database & default printer tetap aman).";
                }
                if (chkLaunchAfter != null)
                {
                    chkLaunchAfter.Text = "Jalankan PCB Barcode Print setelah update selesai";
                }
            }
            else
            {
                btnInstall.Text = "Instal Sekarang";
                btnInstall.BackColor = Color.FromArgb(37, 99, 235); // Blue #2563EB
                lblStatus.Text = "Aplikasi siap diinstal. Klik 'Instal Sekarang' untuk memulai proses.";
                lblStatus.ForeColor = Color.FromArgb(100, 116, 139);
                if (infoCard != null) infoCard.BackColor = Color.FromArgb(240, 249, 255);
                if (lblInfo != null)
                {
                    lblInfo.ForeColor = Color.FromArgb(3, 105, 161);
                    lblInfo.Text = "✨ Versi 2.4: Studio UI Modern, Mode Offline & Database, Direct Print Tanpa Popup, dan Default Printer Software.";
                }
                if (chkLaunchAfter != null)
                {
                    chkLaunchAfter.Text = "Jalankan PCB Barcode Print setelah instalasi selesai";
                }
            }
        }

        private void BtnInstall_Click(object sender, EventArgs e)
        {
            if (isFinished)
            {
                // Launch app if checked
                if (chkLaunchAfter.Checked)
                {
                    string targetDir = txtInstallPath.Text.Trim();
                    string exePath = Path.Combine(targetDir, "PCBBarcodePrint.exe");
                    if (File.Exists(exePath))
                    {
                        ProcessStartInfo psi = new ProcessStartInfo
                        {
                            FileName = exePath,
                            WorkingDirectory = targetDir
                        };
                        Process.Start(psi);
                    }
                }
                this.Close();
                return;
            }

            string targetPath = txtInstallPath.Text.Trim();
            if (string.IsNullOrEmpty(targetPath))
            {
                MessageBox.Show("Silakan tentukan folder lokasi instalasi terlebih dahulu.", "Perhatian", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            // Cek apakah software sudah terinstal sebelumnya di lokasi tujuan
            bool isUpdateMode = IsExistingInstallation(targetPath);
            if (isUpdateMode)
            {
                DialogResult confirm = MessageBox.Show(
                    "Aplikasi PCB Barcode Print terdeteksi sudah terinstal sebelumnya di:\n" +
                    targetPath + "\n\n" +
                    "Apakah Anda ingin melakukan UPDATE software (menimpa instalasi lama dengan versi terbaru)?\n\n" +
                    "• Pengaturan koneksi database dan default printer software Anda akan tetap dipertahankan.",
                    "Konfirmasi Update Software",
                    MessageBoxButtons.YesNo,
                    MessageBoxIcon.Question
                );

                if (confirm != DialogResult.Yes)
                {
                    return;
                }
            }

            lastActionWasUpdate = isUpdateMode;

            // Disable controls
            txtInstallPath.Enabled = false;
            btnBrowse.Enabled = false;
            chkDesktopShortcut.Enabled = false;
            chkStartMenuShortcut.Enabled = false;
            chkLaunchAfter.Enabled = false;
            btnInstall.Enabled = false;
            btnCancel.Enabled = false;

            lblStatus.Text = isUpdateMode ? "Menyiapkan proses update software..." : "Menyiapkan instalasi...";
            lblStatus.ForeColor = Color.FromArgb(30, 64, 175);
            progressBar.Value = 5;

            worker.RunWorkerAsync(new InstallOptions
            {
                TargetDir = targetPath,
                IsUpdate = isUpdateMode,
                CreateDesktopShortcut = chkDesktopShortcut.Checked,
                CreateStartMenuShortcut = chkStartMenuShortcut.Checked
            });
        }

        private class InstallOptions
        {
            public string TargetDir { get; set; }
            public bool IsUpdate { get; set; }
            public bool CreateDesktopShortcut { get; set; }
            public bool CreateStartMenuShortcut { get; set; }
        }

        private void Worker_DoWork(object sender, DoWorkEventArgs e)
        {
            InstallOptions opts = (InstallOptions)e.Argument;
            string targetDir = opts.TargetDir;
            bool isUpdate = opts.IsUpdate;

            if (!Directory.Exists(targetDir))
            {
                Directory.CreateDirectory(targetDir);
            }

            // 1. Close any running instances of PCBBarcodePrint / DirectPrint / portable php to prevent file locking
            worker.ReportProgress(4, "Menutup proses PCB Barcode Print yang sedang aktif...");
            try
            {
                string[] procNames = new string[] { "PCBBarcodePrint", "DirectPrint" };
                foreach (string pName in procNames)
                {
                    foreach (var proc in Process.GetProcessesByName(pName))
                    {
                        try { proc.Kill(); proc.WaitForExit(1500); } catch { }
                    }
                }

                // Tutup juga proses php.exe yang berjalan dari folder instalasi target
                string fullTargetNorm = Path.GetFullPath(targetDir).TrimEnd('\\', '/');
                foreach (var proc in Process.GetProcessesByName("php"))
                {
                    try
                    {
                        string modPath = proc.MainModule.FileName;
                        if (!string.IsNullOrEmpty(modPath) && modPath.StartsWith(fullTargetNorm, StringComparison.OrdinalIgnoreCase))
                        {
                            proc.Kill();
                            proc.WaitForExit(1500);
                        }
                    }
                    catch { }
                }
            }
            catch { }

            // 1b. Backup file konfigurasi pengguna saat mode Update agar setting database & printer tidak hilang
            Dictionary<string, byte[]> preservedConfigs = new Dictionary<string, byte[]>(StringComparer.OrdinalIgnoreCase);
            if (isUpdate)
            {
                string[] configRelPaths = new string[]
                {
                    "db_config.json",
                    @"data\printer_config.json",
                    "type_meter_options.json",
                    "ev_charger_type_options.json"
                };

                foreach (string rel in configRelPaths)
                {
                    try
                    {
                        string cfgFull = Path.Combine(targetDir, rel);
                        if (File.Exists(cfgFull))
                        {
                            preservedConfigs[rel] = File.ReadAllBytes(cfgFull);
                        }
                    }
                    catch { }
                }
            }

            // 2. Clean old Chromium caches in target directory so user never sees stale UI
            worker.ReportProgress(8, "Membersihkan cache versi lama...");
            try
            {
                string[] profiles = new string[] { "profile", "profile_chrome" };
                string[] cacheFolders = new string[] { "Cache", "Code Cache", "GPUCache" };
                foreach (string prof in profiles)
                {
                    foreach (string cf in cacheFolders)
                    {
                        string dir = Path.Combine(targetDir, "data", prof, "Default", cf);
                        if (Directory.Exists(dir)) Directory.Delete(dir, true);
                    }
                }
            }
            catch { }

            // 3. Extract embedded payload
            Assembly asm = Assembly.GetExecutingAssembly();
            using (Stream resStream = asm.GetManifestResourceStream("payload.zip"))
            {
                if (resStream == null)
                {
                    throw new Exception("Resource 'payload.zip' tidak ditemukan di dalam paket installer.");
                }

                using (ZipArchive archive = new ZipArchive(resStream, ZipArchiveMode.Read))
                {
                    int total = archive.Entries.Count;
                    int count = 0;
                    string actionVerb = isUpdate ? "Memperbarui" : "Mengekstrak";

                    foreach (ZipArchiveEntry entry in archive.Entries)
                    {
                        string safeRelPath = entry.FullName.Replace('/', Path.DirectorySeparatorChar);
                        string destPath = Path.Combine(targetDir, safeRelPath);

                        if (entry.FullName.EndsWith("/") || entry.FullName.EndsWith("\\") || string.IsNullOrEmpty(entry.Name))
                        {
                            // Directory
                            Directory.CreateDirectory(destPath);
                        }
                        else
                        {
                            // File
                            string parentDir = Path.GetDirectoryName(destPath);
                            if (!string.IsNullOrEmpty(parentDir) && !Directory.Exists(parentDir))
                            {
                                Directory.CreateDirectory(parentDir);
                            }

                            entry.ExtractToFile(destPath, true);
                        }

                        count++;
                        int progress = (int)((count / (float)total) * 78) + 12;
                        worker.ReportProgress(progress, actionVerb + " (" + count + "/" + total + "): " + entry.Name);
                    }
                }
            }

            // 3b. Restore file konfigurasi pengguna yang di-backup saat mode Update
            if (isUpdate && preservedConfigs.Count > 0)
            {
                foreach (var kvp in preservedConfigs)
                {
                    try
                    {
                        string cfgFull = Path.Combine(targetDir, kvp.Key);
                        string parentDir = Path.GetDirectoryName(cfgFull);
                        if (!string.IsNullOrEmpty(parentDir) && !Directory.Exists(parentDir))
                        {
                            Directory.CreateDirectory(parentDir);
                        }
                        File.WriteAllBytes(cfgFull, kvp.Value);
                    }
                    catch { }
                }
            }

            // 3c. Simpan lokasi instalasi di Registry untuk deteksi update berikutnya
            try
            {
                using (RegistryKey key = Registry.CurrentUser.CreateSubKey(@"Software\PCBBarcodePrint"))
                {
                    if (key != null)
                    {
                        key.SetValue("InstallLocation", targetDir, RegistryValueKind.String);
                        key.SetValue("Version", "2.4", RegistryValueKind.String);
                        key.SetValue("UpdatedAt", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"), RegistryValueKind.String);
                    }
                }
            }
            catch { }

            // 4. Create Shortcuts
            string exePath = Path.Combine(targetDir, "PCBBarcodePrint.exe");
            string iconPath = Path.Combine(targetDir, "favicon.ico");

            if (opts.CreateDesktopShortcut)
            {
                worker.ReportProgress(94, "Memperbarui shortcut di Desktop...");
                string desktopPath = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                string lnkPath = Path.Combine(desktopPath, "PCB Barcode Print.lnk");
                CreateShortcut(lnkPath, exePath, targetDir, iconPath, "Sistem Cetak Barcode PCB & Label v2.4");
            }

            if (opts.CreateStartMenuShortcut)
            {
                worker.ReportProgress(97, "Memperbarui shortcut di Start Menu...");
                string startMenuPath = Environment.GetFolderPath(Environment.SpecialFolder.Programs);
                string folderPath = Path.Combine(startMenuPath, "PCB Barcode Print");
                if (!Directory.Exists(folderPath)) Directory.CreateDirectory(folderPath);

                string lnkPath = Path.Combine(folderPath, "PCB Barcode Print.lnk");
                CreateShortcut(lnkPath, exePath, targetDir, iconPath, "Sistem Cetak Barcode PCB & Label v2.4");
            }

            // 5. Create clean Uninstaller script
            try
            {
                string uninstallerBat = Path.Combine(targetDir, "Uninstall.bat");
                string batContent = "@echo off\r\n" +
                    "title Hapus PCB Barcode Print\r\n" +
                    "echo ==========================================================\r\n" +
                    "echo           UNINSTALLER PCB BARCODE PRINT v2.4               \r\n" +
                    "echo ==========================================================\r\n" +
                    "echo.\r\n" +
                    "set /p CONFIRM=Apakah Anda yakin ingin menghapus aplikasi ini? (Y/N): \r\n" +
                    "if /i \"%CONFIRM%\" neq \"Y\" exit /b\r\n" +
                    "echo Menutup proses aplikasi jika sedang aktif...\r\n" +
                    "taskkill /f /im PCBBarcodePrint.exe 2>nul\r\n" +
                    "taskkill /f /im DirectPrint.exe 2>nul\r\n" +
                    "taskkill /f /im php.exe 2>nul\r\n" +
                    "del /f /q \"%USERPROFILE%\\Desktop\\PCB Barcode Print.lnk\" 2>nul\r\n" +
                    "rmdir /s /q \"%APPDATA%\\Microsoft\\Windows\\Start Menu\\Programs\\PCB Barcode Print\" 2>nul\r\n" +
                    "reg delete \"HKCU\\Software\\PCBBarcodePrint\" /f 2>nul\r\n" +
                    "echo Shortcut berhasil dihapus.\r\n" +
                    "echo Silakan hapus folder ini secara manual untuk menyelesaikan pembersihan.\r\n" +
                    "pause\r\n";
                File.WriteAllText(uninstallerBat, batContent);
            }
            catch { }

            worker.ReportProgress(100, isUpdate ? "Update PCB Barcode Print v2.4 selesai!" : "Instalasi PCB Barcode Print v2.4 selesai!");
        }

        private void Worker_ProgressChanged(object sender, ProgressChangedEventArgs e)
        {
            progressBar.Value = Math.Min(100, Math.Max(0, e.ProgressPercentage));
            if (e.UserState != null)
            {
                lblStatus.Text = e.UserState.ToString();
            }
        }

        private void Worker_RunWorkerCompleted(object sender, RunWorkerCompletedEventArgs e)
        {
            if (e.Error != null)
            {
                string failTitle = lastActionWasUpdate ? "Error Update" : "Error Instalasi";
                MessageBox.Show("Terjadi kesalahan: " + e.Error.Message, failTitle, MessageBoxButtons.OK, MessageBoxIcon.Error);
                txtInstallPath.Enabled = true;
                btnBrowse.Enabled = true;
                chkDesktopShortcut.Enabled = true;
                chkStartMenuShortcut.Enabled = true;
                chkLaunchAfter.Enabled = true;
                btnInstall.Enabled = true;
                btnCancel.Enabled = true;
                lblStatus.Text = (lastActionWasUpdate ? "Update gagal: " : "Instalasi gagal: ") + e.Error.Message;
                lblStatus.ForeColor = Color.Red;
                return;
            }

            isFinished = true;
            lblStatus.Text = lastActionWasUpdate
                ? "✓ Update PCB Barcode Print v2.4 berhasil! Software telah diperbarui."
                : "✓ Instalasi PCB Barcode Print v2.4 berhasil! Aplikasi siap digunakan.";
            lblStatus.ForeColor = Color.FromArgb(22, 101, 52); // Dark Green
            progressBar.Value = 100;

            btnCancel.Visible = false;
            btnInstall.Text = "Selesai";
            btnInstall.BackColor = Color.FromArgb(37, 99, 235);
            btnInstall.Location = new Point(425, 14);
            btnInstall.Enabled = true;
            chkLaunchAfter.Enabled = true;

            if (lastActionWasUpdate)
            {
                MessageBox.Show(
                    "Software PCB Barcode Print berhasil di-update ke versi terbaru!\n\nKlik 'Selesai' untuk membuka aplikasi.",
                    "Update Software Berhasil",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information
                );
            }
        }

        private static void CreateShortcut(string shortcutPath, string targetPath, string workingDir, string iconLocation, string description)
        {
            try
            {
                Type shellType = Type.GetTypeFromProgID("WScript.Shell");
                if (shellType == null) return;

                dynamic shell = Activator.CreateInstance(shellType);
                dynamic shortcut = shell.CreateShortcut(shortcutPath);
                shortcut.TargetPath = targetPath;
                shortcut.WorkingDirectory = workingDir;
                shortcut.Description = description;

                if (File.Exists(iconLocation))
                {
                    shortcut.IconLocation = iconLocation + ",0";
                }

                shortcut.Save();
            }
            catch (Exception ex)
            {
                Debug.WriteLine("Shortcut error: " + ex.Message);
            }
        }
    }
}
