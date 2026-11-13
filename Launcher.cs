using System;
using System.IO;
using System.Diagnostics;
using System.Net;
using System.Net.Sockets;
using System.Threading;
using System.Windows.Forms;
using System.Drawing;
using System.Runtime.InteropServices;
using Microsoft.Win32;

namespace PCBBarcodePrint
{
    static class Program
    {
        private static Mutex appMutex;
        private static Process phpProcess;
        private static Process browserProcess;
        private static NotifyIcon trayIcon;
        private static IntPtr jobHandle = IntPtr.Zero;
        private static int serverPort = 0;
        private static string appDir = "";
        private static string browserPath = "";

        [STAThread]
        static void Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            appDir = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\', '/');

            // 1. Single Instance Check
            bool createdNew;
            appMutex = new Mutex(true, "PCBBarcodePrint_SingleInstance_Mutex_Adid", out createdNew);
            if (!createdNew)
            {
                // App already running: simply launch the browser window and bring to focus
                int runningPort = ReadPortMarker();
                if (runningPort > 0)
                {
                    browserPath = FindBrowser();
                    LaunchBrowserWindow("http://127.0.0.1:" + runningPort + "/index.php");
                    return;
                }

                MessageBox.Show(
                    "Aplikasi PCB Barcode Print sudah sedang berjalan.\nSilakan periksa System Tray di pojok kanan bawah desktop Anda.",
                    "PCB Barcode Print",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information
                );
                return;
            }

            // 2. Set up Job Object for automatic child process termination
            InitJobObject();

            // 3. Find an available free TCP port
            serverPort = FindFreeTcpPort(18080, 19000);
            if (serverPort == 0)
            {
                MessageBox.Show("Gagal menemukan port TCP yang tersedia untuk menjalankan server lokal.", "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return;
            }

            // Write port marker so subsequent launches know where the server is running
            WritePortMarker(serverPort);

            // 4. Locate PHP runtime
            string phpExe = Path.Combine(appDir, "php", "php.exe");
            if (!File.Exists(phpExe))
            {
                // Fallback to xampp
                if (File.Exists(@"C:\xampp 7.3.5\php\php.exe"))
                    phpExe = @"C:\xampp 7.3.5\php\php.exe";
                else if (File.Exists(@"C:\xampp\php\php.exe"))
                    phpExe = @"C:\xampp\php\php.exe";
            }

            if (!File.Exists(phpExe))
            {
                MessageBox.Show("PHP Runtime tidak ditemukan di folder aplikasi './php/php.exe'.", "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return;
            }

            // 5. Start PHP Built-in Server
            try
            {
                ProcessStartInfo phpPsi = new ProcessStartInfo
                {
                    FileName = phpExe,
                    Arguments = string.Format("-S 127.0.0.1:{0} -t \"{1}\"", serverPort, appDir),
                    WorkingDirectory = appDir,
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden
                };

                phpProcess = Process.Start(phpPsi);
                if (phpProcess != null && jobHandle != IntPtr.Zero)
                {
                    AssignProcessToJobObject(jobHandle, phpProcess.Handle);
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Gagal memulai server lokal PHP: " + ex.Message, "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return;
            }

            // 6. Enforce Kiosk Print Policies to always use Windows default printer
            ConfigurePrintPolicies();

            // 7. Locate Browser (Edge or Chrome)
            browserPath = FindBrowser();

            // 8. Initialize Tray Icon
            InitTrayIcon();

            // 9. Launch App Window in Background Thread
            ThreadPool.QueueUserWorkItem(state =>
            {
                // Wait briefly for server to respond (index.php returns immediate redirect, <50ms)
                WaitForServer("http://127.0.0.1:" + serverPort + "/index.php", 6000);
                LaunchBrowserWindow("http://127.0.0.1:" + serverPort + "/index.php");
            });

            // 9. Run Application Loop
            Application.Run();

            // 10. Clean exit
            Cleanup();
        }

        private static void InitTrayIcon()
        {
            trayIcon = new NotifyIcon();
            trayIcon.Text = "PCB Barcode Print";

            string iconPath = Path.Combine(appDir, "IMG", "favicon.ico");
            if (File.Exists(iconPath))
            {
                try { trayIcon.Icon = new Icon(iconPath); } catch { trayIcon.Icon = SystemIcons.Application; }
            }
            else
            {
                trayIcon.Icon = SystemIcons.Application;
            }

            ContextMenu contextMenu = new ContextMenu();
            contextMenu.MenuItems.Add("Buka PCB Barcode Print", (s, e) => LaunchBrowserWindow("http://127.0.0.1:" + serverPort + "/index.php"));
            contextMenu.MenuItems.Add("Pilih Mode (Database / Offline)", (s, e) => LaunchBrowserWindow("http://127.0.0.1:" + serverPort + "/mode_select.php"));
            contextMenu.MenuItems.Add("Pengaturan Database", (s, e) => LaunchBrowserWindow("http://127.0.0.1:" + serverPort + "/db_setup.php"));
            contextMenu.MenuItems.Add("Buka Folder Aplikasi", (s, e) => Process.Start("explorer.exe", appDir));
            contextMenu.MenuItems.Add("-");
            contextMenu.MenuItems.Add("Keluar", (s, e) => {
                trayIcon.Visible = false;
                Cleanup();
                Application.Exit();
            });

            trayIcon.ContextMenu = contextMenu;
            trayIcon.Visible = true;
            trayIcon.DoubleClick += (s, e) => LaunchBrowserWindow("http://127.0.0.1:" + serverPort + "/index.php");
        }

        private static void LaunchBrowserWindow(string url)
        {
            try
            {
                string profileDir = Path.Combine(appDir, "data", "profile");
                if (!Directory.Exists(profileDir))
                {
                    Directory.CreateDirectory(profileDir);
                }

                if (!string.IsNullOrEmpty(browserPath) && File.Exists(browserPath))
                {
                    string args = string.Format(
                        "--app=\"{0}\" --user-data-dir=\"{1}\" --window-size=1366,768 --disable-infobars --no-first-run --no-default-browser-check --new-window --kiosk-printing",
                        url,
                        profileDir
                    );

                    ProcessStartInfo psi = new ProcessStartInfo
                    {
                        FileName = browserPath,
                        Arguments = args,
                        UseShellExecute = false
                    };

                    DateTime launchTime = DateTime.UtcNow;
                    browserProcess = Process.Start(psi);
                    if (browserProcess != null)
                    {
                        // DO NOT assign browserProcess to JobObject to prevent nested Chromium sandbox failures
                        browserProcess.EnableRaisingEvents = true;
                        browserProcess.Exited += (s, e) =>
                        {
                            // If the process exited in under 3.5 seconds, it was an Edge broker handoff. DO NOT EXIT!
                            if ((DateTime.UtcNow - launchTime).TotalSeconds < 3.5)
                            {
                                return;
                            }

                            // Normal exit after session
                            Thread.Sleep(800);
                            Cleanup();
                            Application.Exit();
                        };
                    }
                }
                else
                {
                    // Fallback to default browser
                    Process.Start(url);
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Gagal membuka jendela aplikasi: " + ex.Message, "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private static string FindBrowser()
        {
            string[] candidates = new string[]
            {
                @"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
                @"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
                @"C:\Program Files\Google\Chrome\Application\chrome.exe",
                @"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"
            };

            foreach (string p in candidates)
            {
                if (File.Exists(p)) return p;
            }

            return "";
        }

        private static bool WaitForServer(string url, int timeoutMs)
        {
            int elapsed = 0;
            int step = 150;
            while (elapsed < timeoutMs)
            {
                try
                {
                    HttpWebRequest req = (HttpWebRequest)WebRequest.Create(url);
                    req.Timeout = 800;
                    req.AllowAutoRedirect = false;
                    using (HttpWebResponse resp = (HttpWebResponse)req.GetResponse())
                    {
                        return true;
                    }
                }
                catch (WebException wex)
                {
                    if (wex.Response != null) return true;
                }
                catch { }

                Thread.Sleep(step);
                elapsed += step;
            }
            return false;
        }

        private static int FindFreeTcpPort(int startPort, int endPort)
        {
            for (int port = startPort; port <= endPort; port++)
            {
                TcpListener listener = null;
                try
                {
                    listener = new TcpListener(IPAddress.Loopback, port);
                    listener.Start();
                    return port;
                }
                catch
                {
                    continue;
                }
                finally
                {
                    if (listener != null)
                    {
                        try { listener.Stop(); } catch { }
                    }
                }
            }
            return 0;
        }

        private static void WritePortMarker(int port)
        {
            try
            {
                string marker = Path.Combine(appDir, "data", "server.port");
                string dir = Path.GetDirectoryName(marker);
                if (!Directory.Exists(dir)) Directory.CreateDirectory(dir);
                File.WriteAllText(marker, port.ToString());
            }
            catch { }
        }

        private static int ReadPortMarker()
        {
            try
            {
                string marker = Path.Combine(appDir, "data", "server.port");
                if (File.Exists(marker))
                {
                    int p;
                    if (int.TryParse(File.ReadAllText(marker).Trim(), out p) && p > 0)
                        return p;
                }
            }
            catch { }
            return 0;
        }

        private static void Cleanup()
        {
            try
            {
                string marker = Path.Combine(appDir, "data", "server.port");
                if (File.Exists(marker))
                {
                    try { File.Delete(marker); } catch { }
                }

                if (trayIcon != null)
                {
                    trayIcon.Visible = false;
                    trayIcon.Dispose();
                    trayIcon = null;
                }

                if (phpProcess != null && !phpProcess.HasExited)
                {
                    phpProcess.Kill();
                    phpProcess.Dispose();
                    phpProcess = null;
                }

                if (jobHandle != IntPtr.Zero)
                {
                    CloseHandle(jobHandle);
                    jobHandle = IntPtr.Zero;
                }

                if (appMutex != null)
                {
                    appMutex.ReleaseMutex();
                    appMutex.Dispose();
                    appMutex = null;
                }
            }
            catch { }
        }

        #region Browser Print Policy Configuration
        private static void ConfigurePrintPolicies()
        {
            try
            {
                using (RegistryKey k = Registry.CurrentUser.CreateSubKey(@"Software\Policies\Microsoft\Edge"))
                {
                    if (k != null) k.SetValue("PrintPreviewUseSystemDefaultPrinter", 1, RegistryValueKind.DWord);
                }
            }
            catch { }

            try
            {
                using (RegistryKey k = Registry.CurrentUser.CreateSubKey(@"Software\Policies\Google\Chrome"))
                {
                    if (k != null) k.SetValue("PrintPreviewUseSystemDefaultPrinter", 1, RegistryValueKind.DWord);
                }
            }
            catch { }

            try
            {
                using (RegistryKey k = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows NT\CurrentVersion\Windows"))
                {
                    if (k != null) k.SetValue("LegacyDefaultPrinterMode", 1, RegistryValueKind.DWord);
                }
            }
            catch { }
        }
        #endregion

        #region Windows Job Object Interop
        [DllImport("kernel32.dll", CharSet = CharSet.Unicode)]
        static extern IntPtr CreateJobObject(IntPtr lpJobAttributes, string lpName);

        [DllImport("kernel32.dll")]
        static extern bool SetInformationJobObject(IntPtr hJob, int JobObjectInfoClass, IntPtr lpJobObjectInfo, uint cbJobObjectInfoLength);

        [DllImport("kernel32.dll", SetLastError = true)]
        static extern bool AssignProcessToJobObject(IntPtr hJob, IntPtr hProcess);

        [DllImport("kernel32.dll", SetLastError = true)]
        static extern bool CloseHandle(IntPtr hObject);

        [StructLayout(LayoutKind.Sequential)]
        struct IO_COUNTERS
        {
            public ulong ReadOperationCount;
            public ulong WriteOperationCount;
            public ulong OtherOperationCount;
            public ulong ReadTransferCount;
            public ulong WriteTransferCount;
            public ulong OtherTransferCount;
        }

        [StructLayout(LayoutKind.Sequential)]
        struct JOBOBJECT_BASIC_LIMIT_INFORMATION
        {
            public long PerProcessUserTimeLimit;
            public long PerJobUserTimeLimit;
            public uint LimitFlags;
            public UIntPtr MinimumWorkingSetSize;
            public UIntPtr MaximumWorkingSetSize;
            public uint ActiveProcessLimit;
            public UIntPtr Affinity;
            public uint PriorityClass;
            public uint SchedulingClass;
        }

        [StructLayout(LayoutKind.Sequential)]
        struct JOBOBJECT_EXTENDED_LIMIT_INFORMATION
        {
            public JOBOBJECT_BASIC_LIMIT_INFORMATION BasicLimitInformation;
            public IO_COUNTERS IoInfo;
            public UIntPtr ProcessMemoryLimit;
            public UIntPtr JobMemoryLimit;
            public UIntPtr PeakProcessMemoryLimit;
            public UIntPtr PeakJobMemoryLimit;
        }

        const int JobObjectExtendedLimitInformation = 9;
        const uint JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE = 0x00002000;
        const uint JOB_OBJECT_LIMIT_BREAKAWAY_OK = 0x00000800;
        const uint JOB_OBJECT_LIMIT_SILENT_BREAKAWAY_OK = 0x00001000;

        private static void InitJobObject()
        {
            try
            {
                jobHandle = CreateJobObject(IntPtr.Zero, null);
                JOBOBJECT_EXTENDED_LIMIT_INFORMATION info = new JOBOBJECT_EXTENDED_LIMIT_INFORMATION();
                info.BasicLimitInformation.LimitFlags = JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE | JOB_OBJECT_LIMIT_BREAKAWAY_OK | JOB_OBJECT_LIMIT_SILENT_BREAKAWAY_OK;

                int length = Marshal.SizeOf(typeof(JOBOBJECT_EXTENDED_LIMIT_INFORMATION));
                IntPtr extendedInfoPtr = Marshal.AllocHGlobal(length);
                try
                {
                    Marshal.StructureToPtr(info, extendedInfoPtr, false);
                    SetInformationJobObject(jobHandle, JobObjectExtendedLimitInformation, extendedInfoPtr, (uint)length);
                }
                finally
                {
                    Marshal.FreeHGlobal(extendedInfoPtr);
                }
            }
            catch { }
        }
        #endregion
    }
}
