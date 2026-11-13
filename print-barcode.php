<?php
require_once __DIR__ . '/db_config.php';

// Ambil daftar batch
$batches = $koneksi->query("SELECT * FROM batch ORDER BY id ASC");

$selectedBatch = null;
$barcodes = [];

if (isset($_POST['batch_id'])) {
    $batch_id = (int) $_POST['batch_id'];
    $selectedBatch = $batch_id;

    // Ambil semua nomor dari batch terpilih
    $result = $koneksi->query("SELECT nomor FROM nomor_barcode WHERE batch_id=$batch_id ORDER BY id ASC");
    $nomorList = [];
    while ($row = $result->fetch_assoc()) {
        $nomorList[] = $row['nomor'];
    }

    // Ambil 9 awal & 9 akhir
    $awal = array_slice($nomorList, 0, 9);
    $akhir = array_slice($nomorList, -9);

    $barcodes = array_merge($awal, $akhir);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Barcode</title>
  <link rel="icon" type="image/png" sizes="32x32" href="IMG/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="IMG/favicon-16x16.png">
  <link rel="shortcut icon" href="IMG/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="IMG/apple-touch-icon.png">
  <link rel="stylesheet" href="CSS/app-print-modal.css?v=<?= file_exists(__DIR__ . '/CSS/app-print-modal.css') ? filemtime(__DIR__ . '/CSS/app-print-modal.css') : time() ?>">
  <script src="JS2/JsBarcode.all.min.js"></script>
  <script src="JS2/app-print-modal.js?v=<?= file_exists(__DIR__ . '/JS2/app-print-modal.js') ? filemtime(__DIR__ . '/JS2/app-print-modal.js') : time() ?>"></script>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif; padding: 24px; background: #0f172a; color: #f8fafc; }
    form { margin-bottom: 24px; }
    select, button { padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; }
    select { background: #1e293b; color: #f8fafc; border: 1px solid #334155; }
    button { background: #2563eb; color: #fff; border: none; cursor: pointer; }
    button:hover { background: #1d4ed8; }
    .card { border: 1px solid #334155; padding: 20px; border-radius: 12px; background: #1e293b; margin-bottom: 20px; }
    .barcode-container { display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0; }
    .barcode-box { background: #ffffff; color: #000; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; text-align: center; }
  </style>
</head>
<body>
    <h2>Pilih Batch untuk Cetak Barcode</h2>
    <form method="POST">
        <select name="batch_id" required>
            <option value="">-- Pilih Batch --</option>
            <?php while ($batch = $batches->fetch_assoc()): ?>
                <option value="<?= $batch['id'] ?>" <?= ($selectedBatch==$batch['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($batch['nama_batch']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Tampilkan</button>
    </form>

    <?php if (!empty($barcodes)): ?>
        <h3>Preview (9 Nomor Awal & 9 Nomor Akhir)</h3>
        <div class="barcode-container">
            <?php foreach ($barcodes as $i => $code): ?>
                <div class="barcode-box">
                    <svg id="barcode<?= $i ?>"></svg>
                    <p style="margin: 4px 0 0 0; font-size: 12px; font-weight: bold;"><?= htmlspecialchars($code) ?></p>
                    <script>
                        JsBarcode("#barcode<?= $i ?>", "<?= $code ?>", {
                            format: "CODE128",
                            displayValue: false,
                            width: 2,
                            height: 50
                        });
                    </script>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" onclick="openPrintStudio()">🖨 Pratinjau & Cetak Sekarang</button>

        <script>
        function openPrintStudio() {
          var containerHtml = document.querySelector('.barcode-container').outerHTML;
          window.AppPrinter.open({
            title: "Cetak Barcode Preview Batch",
            badge: "Format Standard",
            totalLabels: <?= count($barcodes) ?>,
            totalPages: 1,
            html: containerHtml,
            css: `
              .barcode-container { display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; }
              .barcode-box { border: 1px solid #ccc; padding: 8px; text-align: center; }
            `
          });
        }
        </script>
    <?php endif; ?>
</body>
</html>
