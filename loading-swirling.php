<?php
declare(strict_types=1);

/**
 * Reusable Swirling Loading Screen Overlay (PHP)
 *
 * Dapat dipanggil langsung sebagai halaman, atau di-include ke halaman PHP lain:
 *   <?php include 'loading-swirling.php'; ?>
 */
$isIncluded = (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__);
$loadingLabel = $loadingLabel ?? 'Memuat...';
$loadingId = $loadingId ?? 'loadingScreen';
$autoHideOnLoad = $autoHideOnLoad ?? false;
$loadingHidden = $loadingHidden ?? false;
$loadingDataPendingPrint = $loadingDataPendingPrint ?? false;
?>
<?php if (!$isIncluded): ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Loading — SMT Material Control</title>
  <link rel="stylesheet" href="loading-swirling.css">
</head>
<body>
<?php endif; ?>

  <div
    id="<?= htmlspecialchars($loadingId, ENT_QUOTES, 'UTF-8') ?>"
    class="loading-screen-overlay<?= $loadingHidden ? ' is-hidden' : '' ?>"
    role="status"
    aria-live="polite"
    aria-label="<?= htmlspecialchars($loadingLabel, ENT_QUOTES, 'UTF-8') ?>"<?= $loadingDataPendingPrint ? ' data-pending-print="1"' : '' ?>
  >
    <svg
      class="swirling-loader"
      viewBox="0 0 800 800"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <circle
        class="loading-ui-swirling-circle"
        cx="400"
        cy="400"
        r="200"
        fill="none"
        stroke="currentColor"
        stroke-linecap="round"
        stroke-width="50"
      />
    </svg>
  </div>

<?php if ($autoHideOnLoad): ?>
  <script>
    window.addEventListener('load', () => {
      const loader = document.getElementById(<?= json_encode($loadingId) ?>);
      if (loader) {
        loader.classList.add('is-hidden');
      }
    });
  </script>
<?php endif; ?>

<?php if (!$isIncluded): ?>
</body>
</html>
<?php endif; ?>
