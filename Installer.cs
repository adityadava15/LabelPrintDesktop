using System;
using System.IO;
using System.Drawing;
using System.Reflection;
using System.Diagnostics;
using System.Windows.Forms;
using System.ComponentModel;
using System.IO.Compression;

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
        private Button btnInstall;
        private Button btnCancel;
        private BackgroundWorker worker;
        private bool isFinished = false;

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
                Text = @"C:\PCBBarcodePrint",
                Font = new Font("Segoe UI", 10),
                Location = new Point(24, 34),
                Size = new Size(415, 26),
                BorderStyle = BorderStyle.FixedSingle
            };

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
                    fbd.Description = "Pilih folder untuk instalasi PCB Barcode Print:";
                    fbd.SelectedPath = txtInstallPath.Text;
                    if (fbd.ShowDialog() == DialogResult.OK)
                    {
                        txtInstallPath.Text = fbd.SelectedPath;
                    }
                }
            };

            // Version info banner
            Panel infoCard = new Panel
            {
                Location = new Point(24, 68),
                Size = new Size(519, 44),
                BackColor = Color.FromArgb(240, 249, 255)
            };
            Label lblInfo = new Label
            {
                Text = "✨ Versi 2.4: Studio UI Modern, Mode Offline & Database, Windows Printer Sync, dan Layout Cetak Modem 2mm.",
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
                Location = new Point(330, 14),
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
                Location = new Point(435, 14),
                Size = new Size(130, 34),
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

            // Disable controls
            txtInstallPath.Enabled = false;
            btnBrowse.Enabled = false;
            chkDesktopShortcut.Enabled = false;
            chkStartMenuShortcut.Enabled = false;
            chkLaunchAfter.Enabled = false;
            btnInstall.Enabled = false;
            btnCancel.Enabled = false;

            lblStatus.Text = "Menyiapkan instalasi...";
            progressBar.Value = 5;

            worker.RunWorkerAsync(new InstallOptions
            {
                TargetDir = targetPath,
                CreateDesktopShortcut = chkDesktopShortcut.Checked,
                CreateStartMenuShortcut = chkStartMenuShortcut.Checked
            });
        }

        private class InstallOptions
        {
            public string TargetDir { get; set; }
            public bool CreateDesktopShortcut { get; set; }
            public bool CreateStartMenuShortcut { get; set; }
        }

        private void Worker_DoWork(object sender, DoWorkEventArgs e)
        {
            InstallOptions opts = (InstallOptions)e.Argument;
            string targetDir = opts.TargetDir;

            if (!Directory.Exists(targetDir))
            {
                Directory.CreateDirectory(targetDir);
            }

            // 1. Close any running instances of PCBBarcodePrint to prevent file locking
            worker.ReportProgress(4, "Menutup proses PCB Barcode Print yang sedang aktif...");
            try
            {
                foreach (var proc in Process.GetProcessesByName("PCBBarcodePrint"))
                {
                    try { proc.Kill(); proc.WaitForExit(1500); } catch { }
                }
            }
            catch { }

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
                        worker.ReportProgress(progress, "Mengekstrak (" + count + "/" + total + "): " + entry.Name);
                    }
                }
            }

            // 4. Create Shortcuts
            string exePath = Path.Combine(targetDir, "PCBBarcodePrint.exe");
            string iconPath = Path.Combine(targetDir, "favicon.ico");

            if (opts.CreateDesktopShortcut)
            {
                worker.ReportProgress(94, "Membuat shortcut di Desktop...");
                string desktopPath = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                string lnkPath = Path.Combine(desktopPath, "PCB Barcode Print.lnk");
                CreateShortcut(lnkPath, exePath, targetDir, iconPath, "Sistem Cetak Barcode PCB & Label v2.4");
            }

            if (opts.CreateStartMenuShortcut)
            {
                worker.ReportProgress(97, "Membuat shortcut di Start Menu...");
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
                    "taskkill /f /im php.exe 2>nul\r\n" +
                    "del /f /q \"%USERPROFILE%\\Desktop\\PCB Barcode Print.lnk\" 2>nul\r\n" +
                    "rmdir /s /q \"%APPDATA%\\Microsoft\\Windows\\Start Menu\\Programs\\PCB Barcode Print\" 2>nul\r\n" +
                    "echo Shortcut berhasil dihapus.\r\n" +
                    "echo Silakan hapus folder ini secara manual untuk menyelesaikan pembersihan.\r\n" +
                    "pause\r\n";
                File.WriteAllText(uninstallerBat, batContent);
            }
            catch { }

            worker.ReportProgress(100, "Instalasi PCB Barcode Print v2.4 selesai!");
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
                MessageBox.Show("Terjadi kesalahan saat instalasi: " + e.Error.Message, "Error Instalasi", MessageBoxButtons.OK, MessageBoxIcon.Error);
                btnInstall.Enabled = true;
                btnCancel.Enabled = true;
                lblStatus.Text = "Instalasi gagal: " + e.Error.Message;
                lblStatus.ForeColor = Color.Red;
                return;
            }

            isFinished = true;
            lblStatus.Text = "Instalasi PCB Barcode Print v2.4 berhasil! Aplikasi siap digunakan.";
            lblStatus.ForeColor = Color.FromArgb(22, 101, 52); // Dark Green
            progressBar.Value = 100;

            btnCancel.Visible = false;
            btnInstall.Text = "Selesai";
            btnInstall.Location = new Point(435, 14);
            btnInstall.Enabled = true;
            chkLaunchAfter.Enabled = true;
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
