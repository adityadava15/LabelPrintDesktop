using System;
using System.IO;
using System.Text;
using System.Diagnostics;
using System.Threading;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Printing;
using System.Drawing.Drawing2D;
using Windows.Data.Pdf;
using Windows.Foundation;
using Windows.Storage;
using Windows.Storage.Streams;

namespace PCBBarcodePrint
{
    class DirectPrint
    {
        private class RenderedPage
        {
            public Bitmap Image;
            public double WidthDip;
            public double HeightDip;
            public int Width100;
            public int Height100;
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

        private static List<RenderedPage> RenderPdfPages(string pdfPath, double dpi)
        {
            List<RenderedPage> list = new List<RenderedPage>();
            StorageFile file = WaitAsync(StorageFile.GetFileFromPathAsync(Path.GetFullPath(pdfPath)));
            PdfDocument pdfDoc = WaitAsync(PdfDocument.LoadFromFileAsync(file));

            uint count = pdfDoc.PageCount;
            for (uint i = 0; i < count; i++)
            {
                using (PdfPage page = pdfDoc.GetPage(i))
                {
                    double wDip = page.Size.Width;
                    double hDip = page.Size.Height;

                    uint pxW = (uint)Math.Max(1, Math.Round(wDip * dpi / 96.0));
                    uint pxH = (uint)Math.Max(1, Math.Round(hDip * dpi / 96.0));

                    PdfPageRenderOptions opts = new PdfPageRenderOptions();
                    opts.DestinationWidth = pxW;
                    opts.DestinationHeight = pxH;

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
                            Bitmap bmp = new Bitmap(ms);
                            list.Add(new RenderedPage
                            {
                                Image = bmp,
                                WidthDip = wDip,
                                HeightDip = hDip,
                                Width100 = (int)Math.Max(10, Math.Round(wDip * 100.0 / 96.0)),
                                Height100 = (int)Math.Max(10, Math.Round(hDip * 100.0 / 96.0))
                            });
                        }
                    }
                }
            }
            return list;
        }

        [MTAThread]
        static int Main(string[] args)
        {
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

                List<RenderedPage> pages = RenderPdfPages(pdfPath, dpi);
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
                    foreach (var p in pages) { if (p.Image != null) p.Image.Dispose(); }
                    return 0;
                }

                using (PrintDocument printDoc = new PrintDocument())
                {
                    printDoc.PrinterSettings.PrinterName = printerName;
                    printDoc.PrinterSettings.Copies = 1; // Copies sudah digandakan pada daftar sheet
                    if (!printDoc.PrinterSettings.IsValid)
                    {
                        foreach (var p in pages) { if (p.Image != null) p.Image.Dispose(); }
                        Console.WriteLine("{\"success\":false,\"error\":\"Printer tidak ditemukan atau tidak valid: " + EscapeJson(printerName) + "\"}");
                        return 1;
                    }

                    printDoc.DocumentName = "PCB Barcode Print";
                    // StandardPrintController memastikan TIDAK ada popup 'Printing...' sama sekali
                    printDoc.PrintController = new StandardPrintController();
                    printDoc.OriginAtMargins = false;

                    int pageIdx = 0;

                    printDoc.QueryPageSettings += (sender, e) =>
                    {
                        RenderedPage rp = pages[Math.Min(pageIdx, pages.Count - 1)];
                        e.PageSettings.Margins = new Margins(0, 0, 0, 0);
                        e.PageSettings.Landscape = false;

                        // Cari apakah driver printer sudah punya PaperSize yang ukurannya cocok (±3mm)
                        PaperSize matchedPaper = null;
                        foreach (PaperSize ps in printDoc.PrinterSettings.PaperSizes)
                        {
                            if (Math.Abs(ps.Width - rp.Width100) <= 12 && Math.Abs(ps.Height - rp.Height100) <= 12)
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
                            e.PageSettings.PaperSize = new PaperSize("CustomLabel", rp.Width100, rp.Height100);
                        }
                    };

                    printDoc.PrintPage += (sender, e) =>
                    {
                        RenderedPage rp = pages[pageIdx];
                        e.Graphics.InterpolationMode = InterpolationMode.HighQualityBicubic;
                        e.Graphics.PixelOffsetMode = PixelOffsetMode.HighQuality;
                        e.Graphics.SmoothingMode = SmoothingMode.HighQuality;

                        float targetW = (float)(rp.WidthDip * 100.0 / 96.0);
                        float targetH = (float)(rp.HeightDip * 100.0 / 96.0);
                        float offsetX = -e.PageSettings.HardMarginX;
                        float offsetY = -e.PageSettings.HardMarginY;

                        e.Graphics.DrawImage(rp.Image, new RectangleF(offsetX, offsetY, targetW, targetH));

                        pageIdx++;
                        e.HasMorePages = (pageIdx < pages.Count);
                    };

                    printDoc.Print();
                }

                foreach (var p in pages)
                {
                    if (p.Image != null) p.Image.Dispose();
                }

                Console.WriteLine(string.Format(
                    "{{\"success\":true,\"pages\":{0},\"printer\":\"{1}\"}}",
                    pages.Count,
                    EscapeJson(printerName)
                ));
                return 0;
            }
            catch (Exception ex)
            {
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
