using System;
using System.IO;
using System.Text;
using System.Diagnostics;
using System.Threading;
using System.Collections.Generic;
using System.Runtime.InteropServices;
using System.Drawing;
using System.Drawing.Printing;
using System.Drawing.Drawing2D;
using System.Drawing.Imaging;
using Microsoft.Win32;
using Windows.Data.Pdf;
using Windows.Foundation;
using Windows.Storage;
using Windows.Storage.Streams;

namespace PCBBarcodePrint
{
    class DirectPrint
    {
        #region Win32 Spooler & PnP Configuration Manager Interop
        [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
        static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, ref PRINTER_DEFAULTS pDefault);

        [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
        static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);

        [DllImport("winspool.drv", SetLastError = true)]
        static extern bool ClosePrinter(IntPtr hPrinter);

        [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
        static extern bool GetPrinter(IntPtr hPrinter, uint Level, IntPtr pPrinter, uint cbBuf, out uint pcbNeeded);

        [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
        static extern bool SetPrinter(IntPtr hPrinter, uint Level, IntPtr pPrinter, uint Command);

        [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
        static extern bool EnumJobs(IntPtr hPrinter, uint FirstJob, uint NoJobs, uint Level, IntPtr pJob, uint cbBuf, out uint pcbNeeded, out uint pcReturned);

        [DllImport("winspool.drv", SetLastError = true)]
        static extern bool SetJob(IntPtr hPrinter, uint JobId, uint Level, IntPtr pJob, uint Command);

        [DllImport("cfgmgr32.dll", CharSet = CharSet.Unicode)]
        static extern int CM_Locate_DevNode(out uint pdnDevInst, string pDeviceID, uint ulFlags);

        [DllImport("cfgmgr32.dll")]
        static extern int CM_Get_DevNode_Status(out uint pulStatus, out uint pulProblemNumber, uint dnDevInst, uint ulFlags);

        [DllImport("kernel32.dll")]
        static extern bool Beep(uint dwFreq, uint dwDuration);

        [DllImport("gdi32.dll")]
        static extern int GetDeviceCaps(IntPtr hdc, int nIndex);

        [DllImport("gdi32.dll")]
        static extern int SetStretchBltMode(IntPtr hdc, int mode);

        [DllImport("gdi32.dll")]
        static extern int StretchDIBits(
            IntPtr hdc,
            int xDest,
            int yDest,
            int DestWidth,
            int DestHeight,
            int xSrc,
            int ySrc,
            int SrcWidth,
            int SrcHeight,
            byte[] lpBits,
            byte[] lpbmi,
            uint iUsage,
            uint rop);

        const int LOGPIXELSX = 88;
        const int LOGPIXELSY = 90;
        const int COLORONCOLOR = 3;
        const uint DIB_RGB_COLORS = 0;
        const uint SRCCOPY = 0x00CC0020;

        const uint CM_LOCATE_DEVNODE_NORMAL = 0x00000000;
        const int CR_SUCCESS = 0;

        const uint PRINTER_ACCESS_ADMINISTER = 0x00000004;
        const uint PRINTER_ACCESS_USE = 0x00000008;
        const uint PRINTER_ALL_ACCESS = 0x000F000C;

        const uint PRINTER_CONTROL_PAUSE = 1;
        const uint PRINTER_CONTROL_RESUME = 2;
        const uint PRINTER_CONTROL_PURGE = 3;

        const uint JOB_CONTROL_PAUSE = 1;
        const uint JOB_CONTROL_RESUME = 2;
        const uint JOB_CONTROL_CANCEL = 3;
        const uint JOB_CONTROL_RESTART = 4;
        const uint JOB_CONTROL_DELETE = 5;

        const uint PRINTER_ATTRIBUTE_WORK_OFFLINE = 0x00000400;

        const uint PRINTER_STATUS_PAUSED = 0x00000001;
        const uint PRINTER_STATUS_ERROR = 0x00000002;
        const uint PRINTER_STATUS_PENDING_DELETION = 0x00000004;
        const uint PRINTER_STATUS_PAPER_JAM = 0x00000008;
        const uint PRINTER_STATUS_PAPER_OUT = 0x00000010;
        const uint PRINTER_STATUS_MANUAL_FEED = 0x00000020;
        const uint PRINTER_STATUS_PAPER_PROBLEM = 0x00000040;
        const uint PRINTER_STATUS_OFFLINE = 0x00000080;
        const uint PRINTER_STATUS_NOT_AVAILABLE = 0x00001000;
        const uint PRINTER_STATUS_NO_TONER = 0x00040000;
        const uint PRINTER_STATUS_USER_INTERVENTION = 0x00100000;
        const uint PRINTER_STATUS_OUT_OF_MEMORY = 0x00200000;
        const uint PRINTER_STATUS_DOOR_OPEN = 0x00400000;
        const uint PRINTER_STATUS_SERVER_UNKNOWN = 0x00800000;

        const uint JOB_STATUS_PAUSED = 0x00000001;
        const uint JOB_STATUS_ERROR = 0x00000002;
        const uint JOB_STATUS_DELETING = 0x00000004;
        const uint JOB_STATUS_SPOOLING = 0x00000008;
        const uint JOB_STATUS_PRINTING = 0x00000010;
        const uint JOB_STATUS_OFFLINE = 0x00000020;
        const uint JOB_STATUS_PAPEROUT = 0x00000040;
        const uint JOB_STATUS_PRINTED = 0x00000080;
        const uint JOB_STATUS_DELETED = 0x00000100;
        const uint JOB_STATUS_BLOCKED_DEVQ = 0x00000200;
        const uint JOB_STATUS_USER_INTERVENTION = 0x00000400;
        const uint JOB_STATUS_RESTART = 0x00000800;

        [StructLayout(LayoutKind.Sequential)]
        struct PRINTER_DEFAULTS
        {
            public IntPtr pDatatype;
            public IntPtr pDevMode;
            public uint DesiredAccess;
        }

        [StructLayout(LayoutKind.Sequential)]
        struct SYSTEMTIME
        {
            public ushort wYear;
            public ushort wMonth;
            public ushort wDayOfWeek;
            public ushort wDay;
            public ushort wHour;
            public ushort wMinute;
            public ushort wSecond;
            public ushort wMilliseconds;
        }

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Auto)]
        struct PRINTER_INFO_2
        {
            [MarshalAs(UnmanagedType.LPTStr)] public string pServerName;
            [MarshalAs(UnmanagedType.LPTStr)] public string pPrinterName;
            [MarshalAs(UnmanagedType.LPTStr)] public string pShareName;
            [MarshalAs(UnmanagedType.LPTStr)] public string pPortName;
            [MarshalAs(UnmanagedType.LPTStr)] public string pDriverName;
            [MarshalAs(UnmanagedType.LPTStr)] public string pComment;
            [MarshalAs(UnmanagedType.LPTStr)] public string pLocation;
            public IntPtr pDevMode;
            [MarshalAs(UnmanagedType.LPTStr)] public string pSepFile;
            [MarshalAs(UnmanagedType.LPTStr)] public string pPrintProcessor;
            [MarshalAs(UnmanagedType.LPTStr)] public string pDatatype;
            [MarshalAs(UnmanagedType.LPTStr)] public string pParameters;
            public IntPtr pSecurityDescriptor;
            public uint Attributes;
            public uint Priority;
            public uint DefaultPriority;
            public uint StartTime;
            public uint UntilTime;
            public uint Status;
            public uint cJobs;
            public uint AveragePPM;
        }

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Auto)]
        struct JOB_INFO_1
        {
            public uint JobId;
            [MarshalAs(UnmanagedType.LPTStr)] public string pPrinterName;
            [MarshalAs(UnmanagedType.LPTStr)] public string pMachineName;
            [MarshalAs(UnmanagedType.LPTStr)] public string pUserName;
            [MarshalAs(UnmanagedType.LPTStr)] public string pDocument;
            [MarshalAs(UnmanagedType.LPTStr)] public string pDatatype;
            [MarshalAs(UnmanagedType.LPTStr)] public string pStatus;
            public uint Status;
            public uint Priority;
            public uint Position;
            public uint TotalPages;
            public uint PagesPrinted;
            public SYSTEMTIME Submitted;
        }
        #endregion

        private class PageMeta
        {
            public double WidthDip;
            public double HeightDip;
            public int Width100;
            public int Height100;
        }

        private class SafetyEvaluation
        {
            public bool Triggered;
            public string SafetyCode; // "PRINTER_PAUSED_OR_STOPPED" or "PRINTER_OFFLINE"
            public string SafetyTitle;
            public string Reason;
            public string PortName;
            public bool IsOnline;
            public bool IsPaused;
            public int ActiveJobs;
            public int CancelledJobs;
            public string CancelledJobNames;
        }

        private static bool OpenPrinterHandle(string printerName, out IntPtr hPrinter, out bool hasAdminAccess)
        {
            hPrinter = IntPtr.Zero;
            hasAdminAccess = false;
            PRINTER_DEFAULTS defs = new PRINTER_DEFAULTS
            {
                pDatatype = IntPtr.Zero,
                pDevMode = IntPtr.Zero,
                DesiredAccess = PRINTER_ALL_ACCESS
            };

            if (OpenPrinter(printerName, out hPrinter, ref defs))
            {
                hasAdminAccess = true;
                return true;
            }

            defs.DesiredAccess = PRINTER_ACCESS_USE | PRINTER_ACCESS_ADMINISTER;
            if (OpenPrinter(printerName, out hPrinter, ref defs))
            {
                hasAdminAccess = true;
                return true;
            }

            if (OpenPrinter(printerName, out hPrinter, IntPtr.Zero))
            {
                hasAdminAccess = false;
                return true;
            }

            return false;
        }

        /**
         * Cek secara instan (<1ms) apakah printer fisik pada port USB (misal USB001) atau WSD
         * benar-benar menyala dan terhubung ke komputer melalui Windows PnP Configuration Manager.
         */
        private static bool IsPhysicalPortConnected(string portName)
        {
            if (string.IsNullOrWhiteSpace(portName)) return true;
            string port = portName.Trim();

            // 1. Jika port USB001, USB002, dst. -> Cek GUID_DEVINTERFACE_USBPRINT di Registry + CM_Locate_DevNode
            if (port.StartsWith("USB", StringComparison.OrdinalIgnoreCase) && port.Length >= 4)
            {
                int usbPortNum;
                if (int.TryParse(port.Substring(3).Trim(), out usbPortNum))
                {
                    try
                    {
                        string regPath = @"SYSTEM\CurrentControlSet\Control\DeviceClasses\{28d78fad-5a12-11d1-ae5b-0000f803a8c2}";
                        using (RegistryKey baseKey = Registry.LocalMachine.OpenSubKey(regPath))
                        {
                            if (baseKey != null)
                            {
                                bool foundPortMatch = false;
                                foreach (string subName in baseKey.GetSubKeyNames())
                                {
                                    using (RegistryKey devKey = baseKey.OpenSubKey(subName))
                                    {
                                        if (devKey == null) continue;
                                        string devInstance = devKey.GetValue("DeviceInstance") as string;
                                        using (RegistryKey paramKey = devKey.OpenSubKey(@"#\Device Parameters"))
                                        {
                                            if (paramKey == null) continue;
                                            object pNumObj = paramKey.GetValue("Port Number");
                                            if (pNumObj != null && Convert.ToInt32(pNumObj) == usbPortNum)
                                            {
                                                foundPortMatch = true;
                                                if (!string.IsNullOrEmpty(devInstance))
                                                {
                                                    uint devInst;
                                                    int cr = CM_Locate_DevNode(out devInst, devInstance, CM_LOCATE_DEVNODE_NORMAL);
                                                    if (cr == CR_SUCCESS)
                                                    {
                                                        uint status, problem;
                                                        if (CM_Get_DevNode_Status(out status, out problem, devInst, 0) == CR_SUCCESS)
                                                        {
                                                            return true;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                if (foundPortMatch)
                                {
                                    // Port USB terdaftar tetapi device fisik mati / kabel USB dicabut!
                                    return false;
                                }
                            }
                        }
                    }
                    catch { }
                }
            }

            // 2. Jika port WSD-xxx -> Cek SWD\PRINTENUM\WSD-xxx
            if (port.StartsWith("WSD-", StringComparison.OrdinalIgnoreCase))
            {
                try
                {
                    string swdInstance = @"SWD\PRINTENUM\" + port;
                    uint devInst;
                    int cr = CM_Locate_DevNode(out devInst, swdInstance, CM_LOCATE_DEVNODE_NORMAL);
                    if (cr != CR_SUCCESS)
                    {
                        return false;
                    }
                }
                catch { }
            }

            return true;
        }

        /**
         * Memastikan job print di spooler benar-benar berasal dari software PCB Barcode Print ini,
         * sehingga job print dari aplikasi lain (Word, Excel, PDF, dll) TIDAK ikut terganggu atau di-cancel.
         */
        private static bool IsOurAppPrintJob(string docName)
        {
            if (string.IsNullOrEmpty(docName)) return false;
            return docName.IndexOf("PCB Barcode Print", StringComparison.OrdinalIgnoreCase) >= 0 ||
                   docName.IndexOf("PCBBarcodePrint", StringComparison.OrdinalIgnoreCase) >= 0;
        }

        private static List<JOB_INFO_1> GetSpoolerJobs(IntPtr hPrinter)
        {
            List<JOB_INFO_1> list = new List<JOB_INFO_1>();
            if (hPrinter == IntPtr.Zero) return list;

            uint needed = 0, returned = 0;
            EnumJobs(hPrinter, 0, 100, 1, IntPtr.Zero, 0, out needed, out returned);
            if (needed == 0) return list;

            IntPtr pBuf = Marshal.AllocHGlobal((int)needed);
            try
            {
                if (EnumJobs(hPrinter, 0, 100, 1, pBuf, needed, out needed, out returned))
                {
                    int sz = Marshal.SizeOf(typeof(JOB_INFO_1));
                    for (int i = 0; i < returned; i++)
                    {
                        IntPtr pCur = new IntPtr(pBuf.ToInt64() + (i * sz));
                        JOB_INFO_1 ji = (JOB_INFO_1)Marshal.PtrToStructure(pCur, typeof(JOB_INFO_1));
                        if ((ji.Status & (JOB_STATUS_DELETED | JOB_STATUS_DELETING)) == 0 && IsOurAppPrintJob(ji.pDocument))
                        {
                            list.Add(ji);
                        }
                    }
                }
            }
            finally
            {
                Marshal.FreeHGlobal(pBuf);
            }
            return list;
        }

        private static int PurgeAllJobs(IntPtr hPrinter, bool hasAdminAccess, out string cancelledJobNames)
        {
            cancelledJobNames = "";
            if (hPrinter == IntPtr.Zero) return 0;

            // Hanya ambil dan batalkan job milik software PCB Barcode Print ini saja
            List<JOB_INFO_1> jobs = GetSpoolerJobs(hPrinter);
            List<string> docNames = new List<string>();
            int cancelled = 0;

            foreach (JOB_INFO_1 ji in jobs)
            {
                bool ok = SetJob(hPrinter, ji.JobId, 0, IntPtr.Zero, JOB_CONTROL_CANCEL);
                if (!ok)
                {
                    ok = SetJob(hPrinter, ji.JobId, 0, IntPtr.Zero, JOB_CONTROL_DELETE);
                }
                else
                {
                    SetJob(hPrinter, ji.JobId, 0, IntPtr.Zero, JOB_CONTROL_DELETE);
                }
                cancelled++;
                string doc = string.IsNullOrEmpty(ji.pDocument) ? ("Job #" + ji.JobId) : (ji.pDocument + " (#" + ji.JobId + ")");
                docNames.Add(doc);
            }

            cancelledJobNames = string.Join(", ", docNames.ToArray());
            return cancelled;
        }

        /**
         * Mengevaluasi kondisi Safety Printer:
         * 1. Apakah print terhenti karena tombol Start/Pause pada printer terpijit (Paused / Blocked / Error / Intervention)?
         * 2. Apakah printer mati / tidak terdeteksi (Offline / Power Off / USB Terputus)?
         * Jika terdeteksi saat hendak mencetak atau saat ada job aktif, langsung AUTO-CANCEL seluruh job di Spooler.
         */
        private static SafetyEvaluation EvaluateAndEnforceSafety(string printerName, bool isPrintingOrWatchingActiveJob)
        {
            SafetyEvaluation eval = new SafetyEvaluation
            {
                Triggered = false,
                SafetyCode = "",
                SafetyTitle = "",
                Reason = "",
                PortName = "",
                IsOnline = true,
                IsPaused = false,
                ActiveJobs = 0,
                CancelledJobs = 0,
                CancelledJobNames = ""
            };

            IntPtr hPrinter;
            bool hasAdmin;
            if (!OpenPrinterHandle(printerName, out hPrinter, out hasAdmin))
            {
                eval.IsOnline = false;
                if (isPrintingOrWatchingActiveJob)
                {
                    eval.Triggered = true;
                    eval.SafetyCode = "PRINTER_OFFLINE";
                    eval.SafetyTitle = "PRINTER MATI / TIDAK TERDETEKSI";
                    eval.Reason = "Printer '" + printerName + "' tidak ditemukan atau mati (Power OFF / kabel terputus). Job print otomatis di-cancel.";
                    eval.CancelledJobs = 1;
                    eval.CancelledJobNames = "PCB Barcode Print";
                }
                return eval;
            }

            try
            {
                uint needed = 0;
                GetPrinter(hPrinter, 2, IntPtr.Zero, 0, out needed);
                PRINTER_INFO_2 pi = new PRINTER_INFO_2();
                if (needed > 0)
                {
                    IntPtr pBuf = Marshal.AllocHGlobal((int)needed);
                    try
                    {
                        if (GetPrinter(hPrinter, 2, pBuf, needed, out needed))
                        {
                            pi = (PRINTER_INFO_2)Marshal.PtrToStructure(pBuf, typeof(PRINTER_INFO_2));
                        }
                    }
                    finally
                    {
                        Marshal.FreeHGlobal(pBuf);
                    }
                }

                eval.PortName = pi.pPortName ?? "";
                List<JOB_INFO_1> jobs = GetSpoolerJobs(hPrinter);
                eval.ActiveJobs = jobs.Count;

                bool shouldEnforce = isPrintingOrWatchingActiveJob || jobs.Count > 0;

                // --- CEK 1: PRINTER MATI / TIDAK TERDETEKSI (OFFLINE / USB DISCONNECTED) ---
                bool spoolerOffline =
                    (pi.Status & (PRINTER_STATUS_OFFLINE | PRINTER_STATUS_NOT_AVAILABLE | PRINTER_STATUS_SERVER_UNKNOWN)) != 0 ||
                    (pi.Attributes & PRINTER_ATTRIBUTE_WORK_OFFLINE) != 0;

                bool jobOffline = false;
                foreach (JOB_INFO_1 j in jobs)
                {
                    if ((j.Status & JOB_STATUS_OFFLINE) != 0)
                    {
                        jobOffline = true;
                        break;
                    }
                }

                bool physicalConnected = IsPhysicalPortConnected(eval.PortName);
                if (spoolerOffline || jobOffline || !physicalConnected)
                {
                    eval.IsOnline = false;
                    if (shouldEnforce)
                    {
                        string jobNames;
                        int purged = PurgeAllJobs(hPrinter, hasAdmin, out jobNames);
                        eval.Triggered = true;
                        eval.SafetyCode = "PRINTER_OFFLINE";
                        eval.SafetyTitle = "PRINTER MATI / TIDAK TERDETEKSI — JOB PRINT DI-CANCEL OTOMATIS";
                        eval.Reason = "Printer '" + printerName + "' terdeteksi MATI (Power OFF) atau koneksi terputus pada port " + (string.IsNullOrEmpty(eval.PortName) ? "USB" : eval.PortName) + ". Seluruh perintah cetak telah dibatalkan otomatis dari antrean agar tidak mencetak ulang saat printer menyala kembali.";
                        eval.CancelledJobs = Math.Max(1, purged);
                        eval.CancelledJobNames = string.IsNullOrEmpty(jobNames) ? "PCB Barcode Print (Commanded Job)" : jobNames;
                        return eval;
                    }
                }

                // --- CEK 2: PRINT TERHENTI KARENA TOMBOL START/PAUSE DI PRINTER KEPIJIT ATAU HARDWARE STOP ---
                bool printerPausedFlag = (pi.Status & PRINTER_STATUS_PAUSED) != 0;
                bool printerStopFlag = (pi.Status & (
                    PRINTER_STATUS_USER_INTERVENTION |
                    PRINTER_STATUS_DOOR_OPEN |
                    PRINTER_STATUS_PAPER_OUT |
                    PRINTER_STATUS_PAPER_JAM |
                    PRINTER_STATUS_PAPER_PROBLEM |
                    PRINTER_STATUS_ERROR |
                    PRINTER_STATUS_NO_TONER
                )) != 0;

                bool jobPausedOrStopped = false;
                string specificStopDetail = "Tombol Start / Pause pada printer tertekan (status Printer Paused / Terhenti)";

                if (printerPausedFlag)
                {
                    specificStopDetail = "Tombol Start / Pause pada printer tertekan (Printer Paused)";
                }
                else if ((pi.Status & PRINTER_STATUS_DOOR_OPEN) != 0)
                {
                    specificStopDetail = "Penutup (Cover/Head) printer terbuka atau tombol fisik printer tertekan";
                }
                else if ((pi.Status & PRINTER_STATUS_PAPER_OUT) != 0)
                {
                    specificStopDetail = "Sensor label mendeteksi sticker habis / tertahan (Paper Out / Pause)";
                }
                else if ((pi.Status & PRINTER_STATUS_USER_INTERVENTION) != 0 || (pi.Status & PRINTER_STATUS_ERROR) != 0)
                {
                    specificStopDetail = "Print terhenti di printer (Tombol Start/Pause tertekan / Butuh Intervensi Operator)";
                }

                foreach (JOB_INFO_1 j in jobs)
                {
                    if ((j.Status & JOB_STATUS_PAUSED) != 0)
                    {
                        jobPausedOrStopped = true;
                        specificStopDetail = "Job print terhenti karena tombol Start / Pause printer tertekan (JOB_PAUSED)";
                        break;
                    }
                    if ((j.Status & JOB_STATUS_BLOCKED_DEVQ) != 0)
                    {
                        jobPausedOrStopped = true;
                        specificStopDetail = "Aliran cetak tertahan oleh tombol Start / Pause di printer (DEVICE_QUEUE_BLOCKED)";
                        break;
                    }
                    if ((j.Status & (JOB_STATUS_USER_INTERVENTION | JOB_STATUS_ERROR | JOB_STATUS_PAPEROUT | JOB_STATUS_RESTART)) != 0)
                    {
                        jobPausedOrStopped = true;
                        specificStopDetail = "Proses cetak terhenti di printer (Tombol Pause/Start tertekan atau error media)";
                        break;
                    }
                }

                if (printerPausedFlag || printerStopFlag || jobPausedOrStopped)
                {
                    eval.IsPaused = true;
                    if (shouldEnforce)
                    {
                        string jobNames;
                        int purged = PurgeAllJobs(hPrinter, hasAdmin, out jobNames);
                        ClearJobTrackerFile();
                        eval.Triggered = true;
                        eval.SafetyCode = "PRINTER_PAUSED_OR_STOPPED";
                        eval.SafetyTitle = "PRINT TERHENTI (TOMBOL START/PAUSE PRINTER) — JOB DI-CANCEL OTOMATIS";
                        eval.Reason = specificStopDetail + " pada printer '" + printerName + "'. Untuk mencegah duplikasi atau kerusakan urutan nomor seri barcode, job print telah di-cancel otomatis dari antrean cetak.";
                        eval.CancelledJobs = Math.Max(1, purged);
                        eval.CancelledJobNames = string.IsNullOrEmpty(jobNames) ? "PCB Barcode Print (Commanded Job)" : jobNames;
                        return eval;
                    }
                }

                // --- CEK 3: DETEKSI JOB TERTAHAN / STALLED KARENA HARDWARE INTERNAL PAUSE (TOMBOL START/PAUSE DI PRINTER) ---
                if (jobs.Count > 0)
                {
                    JOB_INFO_1 topJob = jobs[0];
                    if ((topJob.Status & JOB_STATUS_SPOOLING) == 0)
                    {
                        if (CheckIfJobStalledByHardwarePause(printerName, topJob.JobId, topJob.PagesPrinted))
                        {
                            string jobNames;
                            int purged = PurgeAllJobs(hPrinter, hasAdmin, out jobNames);
                            ClearJobTrackerFile();
                            eval.IsPaused = true;
                            eval.Triggered = true;
                            eval.SafetyCode = "PRINTER_PAUSED_OR_STOPPED";
                            eval.SafetyTitle = "PRINT TERHENTI (TOMBOL START/PAUSE PRINTER) — JOB DI-CANCEL OTOMATIS";
                            eval.Reason = "Aliran data cetak terhenti di printer '" + printerName + "' karena tombol Start / Pause pada printer tertekan (Internal Hardware Pause). Job print otomatis di-cancel dari antrean untuk mencegah duplikasi barcode.";
                            eval.CancelledJobs = Math.Max(1, purged);
                            eval.CancelledJobNames = string.IsNullOrEmpty(jobNames) ? "PCB Barcode Print (Commanded Job)" : jobNames;
                            return eval;
                        }
                    }
                }
                else
                {
                    ClearJobTrackerFile();
                }
            }
            finally
            {
                ClosePrinter(hPrinter);
            }

            return eval;
        }

        private static string GetJobTrackerFilePath()
        {
            string baseDir = AppDomain.CurrentDomain.BaseDirectory;
            string dataDir = Path.Combine(baseDir, "data");
            if (!Directory.Exists(dataDir))
            {
                try { Directory.CreateDirectory(dataDir); } catch { }
            }
            return Path.Combine(dataDir, "spooler_job_tracker.txt");
        }

        private static void ClearJobTrackerFile()
        {
            try
            {
                string f = GetJobTrackerFilePath();
                if (File.Exists(f)) File.Delete(f);
            }
            catch { }
        }

        private static bool CheckIfJobStalledByHardwarePause(string printerName, uint jobId, uint pagesPrinted)
        {
            try
            {
                string f = GetJobTrackerFilePath();
                long nowTicks = DateTime.UtcNow.Ticks;
                string key = (printerName ?? "").Trim().ToLowerInvariant() + "|" + jobId + "|" + pagesPrinted;

                if (File.Exists(f))
                {
                    string content = File.ReadAllText(f).Trim();
                    string[] parts = content.Split('^');
                    if (parts.Length == 2 && parts[0] == key)
                    {
                        long firstSeenTicks;
                        if (long.TryParse(parts[1], out firstSeenTicks))
                        {
                            double elapsedSec = TimeSpan.FromTicks(nowTicks - firstSeenTicks).TotalSeconds;
                            if (elapsedSec >= 4.2)
                            {
                                return true;
                            }
                            return false;
                        }
                    }
                }

                File.WriteAllText(f, key + "^" + nowTicks.ToString());
            }
            catch { }
            return false;
        }

        private static string FormatSafetyJson(string printerName, SafetyEvaluation eval)
        {
            return string.Format(
                "{{\"success\":false,\"safety_triggered\":true,\"safety_code\":\"{0}\",\"safety_title\":\"{1}\",\"reason\":\"{2}\",\"printer\":\"{3}\",\"port\":\"{4}\",\"cancelled_jobs\":{5},\"cancelled_documents\":\"{6}\",\"timestamp\":\"{7}\"}}",
                EscapeJson(eval.SafetyCode),
                EscapeJson(eval.SafetyTitle),
                EscapeJson(eval.Reason),
                EscapeJson(printerName),
                EscapeJson(eval.PortName),
                eval.CancelledJobs,
                EscapeJson(eval.CancelledJobNames),
                DateTime.Now.ToString("HH:mm:ss")
            );
        }

        private static T WaitAsync<T>(IAsyncOperation<T> op, int timeoutMs = 30000)
        {
            int waited = 0;
            while (op.Status == AsyncStatus.Started)
            {
                Thread.Sleep(5);
                waited += 5;
                if (waited > timeoutMs)
                {
                    try { op.Cancel(); } catch { }
                    throw new TimeoutException("WinRT operation timed out.");
                }
            }
            if (op.Status != AsyncStatus.Completed)
            {
                throw new Exception("WinRT operation failed with status: " + op.Status);
            }
            return op.GetResults();
        }

        private static void WaitAction(IAsyncAction act, int timeoutMs = 30000)
        {
            int waited = 0;
            while (act.Status == AsyncStatus.Started)
            {
                Thread.Sleep(5);
                waited += 5;
                if (waited > timeoutMs)
                {
                    try { act.Cancel(); } catch { }
                    throw new TimeoutException("WinRT action timed out.");
                }
            }
            if (act.Status != AsyncStatus.Completed)
            {
                throw new Exception("WinRT action failed with status: " + act.Status);
            }
            act.GetResults();
        }

        private static string FindBrowser()
        {
            string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            string[] candidates = new string[]
            {
                @"C:\Program Files\Google\Chrome\Application\chrome.exe",
                @"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
                Path.Combine(localAppData, @"Google\Chrome\Application\chrome.exe"),
                @"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
                @"C:\Program Files\Microsoft\Edge\Application\msedge.exe"
            };

            foreach (string p in candidates)
            {
                if (File.Exists(p)) return p;
            }
            return "";
        }

        private static string EscapeJson(string s)
        {
            if (s == null) return "";
            return s.Replace("\\", "\\\\").Replace("\"", "\\\"").Replace("\r", "\\r").Replace("\n", "\\n");
        }

        private static bool ConvertHtmlToPdf(string htmlPath, string pdfPath, out string errorMsg)
        {
            errorMsg = "";
            string browser = FindBrowser();
            if (string.IsNullOrEmpty(browser) || !File.Exists(browser))
            {
                errorMsg = "Browser Chrome/Edge tidak ditemukan untuk merender dokumen.";
                return false;
            }

            if (File.Exists(pdfPath))
            {
                try { File.Delete(pdfPath); } catch { }
            }

            string fileUri = new Uri(Path.GetFullPath(htmlPath)).AbsoluteUri;
            string tempProfile = Path.Combine(Path.GetDirectoryName(Path.GetFullPath(pdfPath)), "headless_print_profile");
            if (!Directory.Exists(tempProfile))
            {
                try { Directory.CreateDirectory(tempProfile); } catch { }
            }

            string args = string.Format(
                "--headless --disable-gpu --no-first-run --no-default-browser-check --disable-extensions --no-pdf-header-footer --user-data-dir=\"{0}\" --print-to-pdf=\"{1}\" \"{2}\"",
                tempProfile,
                Path.GetFullPath(pdfPath),
                fileUri
            );

            ProcessStartInfo psi = new ProcessStartInfo
            {
                FileName = browser,
                Arguments = args,
                CreateNoWindow = true,
                UseShellExecute = false,
                WindowStyle = ProcessWindowStyle.Hidden
            };

            try
            {
                using (Process proc = Process.Start(psi))
                {
                    if (proc != null)
                    {
                        if (!proc.WaitForExit(20000))
                        {
                            try { proc.Kill(); } catch { }
                            errorMsg = "Timeout saat merender dokumen cetak.";
                            return false;
                        }
                    }
                }
            }
            catch (Exception ex)
            {
                errorMsg = "Gagal menjalankan renderer: " + ex.Message;
                return false;
            }

            if (!File.Exists(pdfPath) || new FileInfo(pdfPath).Length == 0)
            {
                errorMsg = "File PDF hasil render tidak terbentuk.";
                return false;
            }

            return true;
        }

        private static PdfDocument LoadPdfDocument(string pdfPath, out List<PageMeta> metas)
        {
            metas = new List<PageMeta>();
            StorageFile file = WaitAsync(StorageFile.GetFileFromPathAsync(Path.GetFullPath(pdfPath)));
            PdfDocument pdfDoc = WaitAsync(PdfDocument.LoadFromFileAsync(file));

            uint count = pdfDoc.PageCount;
            for (uint i = 0; i < count; i++)
            {
                using (PdfPage page = pdfDoc.GetPage(i))
                {
                    double wDip = page.Size.Width;
                    double hDip = page.Size.Height;
                    metas.Add(new PageMeta
                    {
                        WidthDip = wDip,
                        HeightDip = hDip,
                        Width100 = (int)Math.Max(10, Math.Round(wDip * 100.0 / 96.0)),
                        Height100 = (int)Math.Max(10, Math.Round(hDip * 100.0 / 96.0))
                    });
                }
            }
            return pdfDoc;
        }

        private static Bitmap RenderPdfPageBitmap(PdfPage page, int pxW, int pxH)
        {
            PdfPageRenderOptions opts = new PdfPageRenderOptions();
            opts.DestinationWidth = (uint)Math.Max(1, pxW);
            opts.DestinationHeight = (uint)Math.Max(1, pxH);

            using (InMemoryRandomAccessStream stream = new InMemoryRandomAccessStream())
            {
                WaitAction(page.RenderToStreamAsync(stream, opts));
                stream.Seek(0);
                uint size = (uint)stream.Size;
                byte[] bytes = new byte[size];
                using (DataReader reader = new DataReader(stream))
                {
                    WaitAsync(reader.LoadAsync(size));
                    reader.ReadBytes(bytes);
                }

                using (MemoryStream ms = new MemoryStream(bytes))
                {
                    return new Bitmap(ms);
                }
            }
        }

        private static byte[] ConvertBitmapTo1bppDib(Bitmap src, int threshold, bool bottomUp, out int stride)
        {
            int w = src.Width;
            int h = src.Height;
            stride = ((w + 31) / 32) * 4;
            byte[] dib = new byte[stride * h];

            BitmapData srcData = src.LockBits(new Rectangle(0, 0, w, h), ImageLockMode.ReadOnly, PixelFormat.Format32bppArgb);
            try
            {
                int srcStride = Math.Abs(srcData.Stride);
                byte[] sBytes = new byte[srcStride * h];
                Marshal.Copy(srcData.Scan0, sBytes, 0, sBytes.Length);

                for (int y = 0; y < h; y++)
                {
                    int sRow = y * srcStride;
                    int dRow = (bottomUp ? (h - 1 - y) : y) * stride;
                    for (int x = 0; x < w; x++)
                    {
                        int idx = sRow + (x * 4);
                        byte b = sBytes[idx];
                        byte g = sBytes[idx + 1];
                        byte r = sBytes[idx + 2];
                        byte a = sBytes[idx + 3];
                        if (a < 255)
                        {
                            int inv = 255 - a;
                            r = (byte)((r * a + 255 * inv) / 255);
                            g = (byte)((g * a + 255 * inv) / 255);
                            b = (byte)((b * a + 255 * inv) / 255);
                        }
                        int lum = (r * 299 + g * 587 + b * 114) / 1000;
                        if (lum >= threshold)
                        {
                            dib[dRow + (x >> 3)] |= (byte)(0x80 >> (x & 7));
                        }
                    }
                }
            }
            finally
            {
                src.UnlockBits(srcData);
            }
            return dib;
        }

        private static byte[] Build1bppBitmapInfo(int w, int h, int stride)
        {
            byte[] bmi = new byte[48];
            BitConverter.GetBytes(40).CopyTo(bmi, 0);        // biSize
            BitConverter.GetBytes(w).CopyTo(bmi, 4);         // biWidth
            BitConverter.GetBytes(h).CopyTo(bmi, 8);         // biHeight (>0 = standard bottom-up DIB)
            BitConverter.GetBytes((short)1).CopyTo(bmi, 12); // biPlanes
            BitConverter.GetBytes((short)1).CopyTo(bmi, 14); // biBitCount = 1bpp monochrome
            BitConverter.GetBytes(0).CopyTo(bmi, 16);        // biCompression = BI_RGB
            BitConverter.GetBytes(stride * h).CopyTo(bmi, 20); // biSizeImage
            BitConverter.GetBytes(2).CopyTo(bmi, 32);        // biClrUsed = 2
            BitConverter.GetBytes(2).CopyTo(bmi, 36);        // biClrImportant = 2
            // Palette[0] = Pure Black (0,0,0), Palette[1] = Pure White (255,255,255)
            bmi[40] = 0; bmi[41] = 0; bmi[42] = 0; bmi[43] = 0;
            bmi[44] = 255; bmi[45] = 255; bmi[46] = 255; bmi[47] = 0;
            return bmi;
        }

        private static Bitmap Create1bppBitmap(Bitmap src, int threshold, float dpiX, float dpiY)
        {
            int w = src.Width;
            int h = src.Height;
            int dibStride;
            byte[] topDownBits = ConvertBitmapTo1bppDib(src, threshold, false, out dibStride);

            Bitmap dst = new Bitmap(w, h, PixelFormat.Format1bppIndexed);
            dst.SetResolution(dpiX, dpiY);
            ColorPalette pal = dst.Palette;
            pal.Entries[0] = Color.FromArgb(255, 0, 0, 0);
            pal.Entries[1] = Color.FromArgb(255, 255, 255, 255);
            dst.Palette = pal;

            BitmapData dstData = dst.LockBits(new Rectangle(0, 0, w, h), ImageLockMode.WriteOnly, PixelFormat.Format1bppIndexed);
            try
            {
                int dstStride = Math.Abs(dstData.Stride);
                int copyBytes = Math.Min(dibStride, dstStride);
                for (int y = 0; y < h; y++)
                {
                    IntPtr dstRow = new IntPtr(dstData.Scan0.ToInt64() + (y * dstStride));
                    Marshal.Copy(topDownBits, y * dibStride, dstRow, copyBytes);
                }
            }
            finally
            {
                dst.UnlockBits(dstData);
            }
            return dst;
        }

        [MTAThread]
        static int Main(string[] args)
        {
            if (args.Length >= 2 && args[0] == "--watch")
            {
                string watchPrinter = args[1];
                bool activeWatch = (args.Length >= 3 && args[2] == "1");
                SafetyEvaluation eval = EvaluateAndEnforceSafety(watchPrinter, activeWatch);
                if (eval.Triggered)
                {
                    Console.WriteLine(FormatSafetyJson(watchPrinter, eval));
                    return 0;
                }
                Console.WriteLine(string.Format(
                    "{{\"success\":true,\"safety_triggered\":false,\"printer\":\"{0}\",\"port\":\"{1}\",\"is_online\":{2},\"is_paused\":{3},\"active_jobs\":{4}}}",
                    EscapeJson(watchPrinter),
                    EscapeJson(eval.PortName),
                    eval.IsOnline ? "true" : "false",
                    eval.IsPaused ? "true" : "false",
                    eval.ActiveJobs
                ));
                return 0;
            }

            if (args.Length >= 2 && args[0] == "--purge")
            {
                string purgePrinter = args[1];
                IntPtr hP;
                bool hasAdm;
                int cancelled = 0;
                string names = "";
                if (OpenPrinterHandle(purgePrinter, out hP, out hasAdm))
                {
                    try
                    {
                        cancelled = PurgeAllJobs(hP, hasAdm, out names);
                    }
                    finally
                    {
                        ClosePrinter(hP);
                    }
                }
                Console.WriteLine(string.Format(
                    "{{\"success\":true,\"purged_jobs\":{0},\"cancelled_documents\":\"{1}\",\"printer\":\"{2}\"}}",
                    cancelled,
                    EscapeJson(names),
                    EscapeJson(purgePrinter)
                ));
                return 0;
            }

            if (args.Length < 2)
            {
                Console.WriteLine("{\"success\":false,\"error\":\"Usage: DirectPrint.exe <htmlOrPdfPath> <printerName> [dpi] [--dry-run]\"}");
                return 1;
            }

            string inputPath = args[0];
            string printerName = args[1];
            double dpi = 600.0;
            bool dryRun = false;

            for (int i = 2; i < args.Length; i++)
            {
                if (args[i] == "--dry-run")
                {
                    dryRun = true;
                }
                else
                {
                    double parsedDpi;
                    if (double.TryParse(args[i], out parsedDpi) && parsedDpi >= 150 && parsedDpi <= 1200)
                    {
                        dpi = parsedDpi;
                    }
                }
            }

            if (!File.Exists(inputPath))
            {
                Console.WriteLine("{\"success\":false,\"error\":\"File input cetak tidak ditemukan.\"}");
                return 1;
            }

            // PHASE 1: Pre-Print Safety Check (sebelum mengirim job ke printer)
            if (!dryRun)
            {
                SafetyEvaluation preCheck = EvaluateAndEnforceSafety(printerName, true);
                if (preCheck.Triggered)
                {
                    Console.WriteLine(FormatSafetyJson(printerName, preCheck));
                    return 0;
                }
            }

            string pdfPath = inputPath;
            bool tempPdfCreated = false;

            try
            {
                if (inputPath.EndsWith(".html", StringComparison.OrdinalIgnoreCase) ||
                    inputPath.EndsWith(".htm", StringComparison.OrdinalIgnoreCase))
                {
                    pdfPath = Path.ChangeExtension(inputPath, ".pdf");
                    tempPdfCreated = true;
                    string err;
                    if (!ConvertHtmlToPdf(inputPath, pdfPath, out err))
                    {
                        Console.WriteLine("{\"success\":false,\"error\":\"" + EscapeJson(err) + "\"}");
                        return 1;
                    }
                }

                List<PageMeta> pages;
                PdfDocument pdfDoc = LoadPdfDocument(pdfPath, out pages);
                if (pages.Count == 0)
                {
                    Console.WriteLine("{\"success\":false,\"error\":\"Tidak ada halaman untuk dicetak.\"}");
                    return 1;
                }

                if (dryRun)
                {
                    Console.WriteLine(string.Format(
                        "{{\"success\":true,\"dry_run\":true,\"pages\":{0},\"width_100\":{1},\"height_100\":{2},\"printer\":\"{3}\"}}",
                        pages.Count,
                        pages[0].Width100,
                        pages[0].Height100,
                        EscapeJson(printerName)
                    ));
                    return 0;
                }

                // Re-check tepat sebelum spooling dimulai
                SafetyEvaluation midCheck = EvaluateAndEnforceSafety(printerName, true);
                if (midCheck.Triggered)
                {
                    Console.WriteLine(FormatSafetyJson(printerName, midCheck));
                    return 0;
                }

                SafetyEvaluation midPrintAbort = null;

                using (PrintDocument printDoc = new PrintDocument())
                {
                    printDoc.PrinterSettings.PrinterName = printerName;
                    printDoc.PrinterSettings.Copies = 1;
                    if (!printDoc.PrinterSettings.IsValid)
                    {
                        Console.WriteLine("{\"success\":false,\"error\":\"Printer tidak ditemukan atau tidak valid: " + EscapeJson(printerName) + "\"}");
                        return 1;
                    }

                    printDoc.DocumentName = "PCB Barcode Print";
                    printDoc.PrintController = new StandardPrintController();
                    printDoc.OriginAtMargins = false;

                    int pageIdx = 0;

                    printDoc.QueryPageSettings += (sender, e) =>
                    {
                        PageMeta pm = pages[Math.Min(pageIdx, pages.Count - 1)];
                        e.PageSettings.Margins = new Margins(0, 0, 0, 0);
                        e.PageSettings.Landscape = false;

                        PaperSize matchedPaper = null;
                        foreach (PaperSize ps in printDoc.PrinterSettings.PaperSizes)
                        {
                            if (Math.Abs(ps.Width - pm.Width100) <= 12 && Math.Abs(ps.Height - pm.Height100) <= 12)
                            {
                                matchedPaper = ps;
                                break;
                            }
                        }

                        if (matchedPaper != null)
                        {
                            e.PageSettings.PaperSize = matchedPaper;
                        }
                        else
                        {
                            e.PageSettings.PaperSize = new PaperSize("CustomLabel", pm.Width100, pm.Height100);
                        }
                    };

                    printDoc.PrintPage += (sender, e) =>
                    {
                        // PHASE 2: Cek safety di sela-sela pengiriman halaman
                        SafetyEvaluation pageSafety = EvaluateAndEnforceSafety(printerName, true);
                        if (pageSafety.Triggered)
                        {
                            midPrintAbort = pageSafety;
                            e.Cancel = true;
                            e.HasMorePages = false;
                            return;
                        }

                        PageMeta pm = pages[pageIdx];
                        float gx = e.Graphics.DpiX;
                        float gy = e.Graphics.DpiY;
                        float hmX = e.PageSettings.HardMarginX;
                        float hmY = e.PageSettings.HardMarginY;

                        double devDpiX = (gx >= 72f && gx <= 4800f) ? gx : dpi;
                        double devDpiY = (gy >= 72f && gy <= 4800f) ? gy : dpi;

                        IntPtr probeHdc = e.Graphics.GetHdc();
                        try
                        {
                            int dcX = GetDeviceCaps(probeHdc, LOGPIXELSX);
                            int dcY = GetDeviceCaps(probeHdc, LOGPIXELSY);
                            if ((gx < 72f || gx > 4800f) && dcX >= 72 && dcX <= 4800) devDpiX = dcX;
                            if ((gy < 72f || gy > 4800f) && dcY >= 72 && dcY <= 4800) devDpiY = dcY;
                        }
                        finally
                        {
                            e.Graphics.ReleaseHdc(probeHdc);
                        }

                        int scaleDown = 1;
                        while ((devDpiX / scaleDown) > 1200.0 || (devDpiY / scaleDown) > 1200.0)
                        {
                            scaleDown++;
                        }

                        int devW = Math.Max(1, (int)Math.Round(pm.WidthDip * devDpiX / 96.0));
                        int devH = Math.Max(1, (int)Math.Round(pm.HeightDip * devDpiY / 96.0));
                        int srcW = (scaleDown == 1) ? devW : Math.Max(1, (int)Math.Round(pm.WidthDip * (devDpiX / scaleDown) / 96.0));
                        int srcH = (scaleDown == 1) ? devH : Math.Max(1, (int)Math.Round(pm.HeightDip * (devDpiY / scaleDown) / 96.0));

                        int offX = -(int)Math.Round(hmX * devDpiX / 100.0);
                        int offY = -(int)Math.Round(hmY * devDpiY / 100.0);

                        double effectiveDpi = Math.Min(devDpiX / scaleDown, devDpiY / scaleDown);
                        int threshold = (effectiveDpi <= 240.0) ? 118 : 128;

                        using (PdfPage pdfPage = pdfDoc.GetPage((uint)pageIdx))
                        using (Bitmap srcBmp = RenderPdfPageBitmap(pdfPage, srcW, srcH))
                        {
                            int dibStride;
                            byte[] dibBits = ConvertBitmapTo1bppDib(srcBmp, threshold, true, out dibStride);
                            byte[] bmi = Build1bppBitmapInfo(srcW, srcH, dibStride);

                            bool stretched = false;
                            IntPtr printHdc = e.Graphics.GetHdc();
                            try
                            {
                                SetStretchBltMode(printHdc, COLORONCOLOR);
                                int drawnLines = StretchDIBits(
                                    printHdc,
                                    offX, offY, devW, devH,
                                    0, 0, srcW, srcH,
                                    dibBits, bmi, DIB_RGB_COLORS, SRCCOPY
                                );
                                stretched = (drawnLines > 0);
                            }
                            finally
                            {
                                e.Graphics.ReleaseHdc(printHdc);
                            }

                            if (!stretched)
                            {
                                using (Bitmap monoBmp = Create1bppBitmap(srcBmp, threshold, (float)(devDpiX / scaleDown), (float)(devDpiY / scaleDown)))
                                {
                                    e.Graphics.InterpolationMode = InterpolationMode.NearestNeighbor;
                                    e.Graphics.PixelOffsetMode = PixelOffsetMode.Half;
                                    e.Graphics.SmoothingMode = SmoothingMode.None;
                                    float targetW = (float)(pm.WidthDip * 100.0 / 96.0);
                                    float targetH = (float)(pm.HeightDip * 100.0 / 96.0);
                                    e.Graphics.DrawImage(monoBmp, new RectangleF(-hmX, -hmY, targetW, targetH));
                                }
                            }
                        }

                        pageIdx++;
                        e.HasMorePages = (pageIdx < pages.Count);
                    };

                    printDoc.Print();
                }

                if (midPrintAbort != null && midPrintAbort.Triggered)
                {
                    Console.WriteLine(FormatSafetyJson(printerName, midPrintAbort));
                    return 0;
                }

                // PHASE 3: Post-Spool Immediate Watch (Pantau selama ~1 detik setelah masuk antrean Spooler)
                for (int w = 0; w < 5; w++)
                {
                    Thread.Sleep(180);
                    SafetyEvaluation postEval = EvaluateAndEnforceSafety(printerName, false);
                    if (postEval.Triggered)
                    {
                        Console.WriteLine(FormatSafetyJson(printerName, postEval));
                        return 0;
                    }
                    if (postEval.ActiveJobs == 0 && postEval.IsOnline && !postEval.IsPaused)
                    {
                        break;
                    }
                }

                Console.WriteLine(string.Format(
                    "{{\"success\":true,\"safety_triggered\":false,\"pages\":{0},\"printer\":\"{1}\"}}",
                    pages.Count,
                    EscapeJson(printerName)
                ));
                return 0;
            }
            catch (Exception ex)
            {
                // Jika exception terjadi karena printer mati/error saat spooling, pastikan antrean dibersihkan
                SafetyEvaluation errEval = EvaluateAndEnforceSafety(printerName, true);
                if (errEval.Triggered)
                {
                    Console.WriteLine(FormatSafetyJson(printerName, errEval));
                    return 0;
                }

                Console.WriteLine("{\"success\":false,\"error\":\"" + EscapeJson(ex.Message) + "\"}");
                return 1;
            }
            finally
            {
                if (tempPdfCreated && File.Exists(pdfPath))
                {
                    try { File.Delete(pdfPath); } catch { }
                }
            }
        }
    }
}
