/**
 * PCB Barcode Print - Modern In-App Print Studio
 * Native In-App Direct Print with Sidebar Controls & Live Page Filtering
 * Bypasses browser print preview dialog & prints directly to selected Windows printer.
 */
(function (global) {
  "use strict";

  var currentConfig = null;
  var currentZoom = 1.0;
  var overlayEl = null;
  var pagesContainerEl = null;
  var zoomValEl = null;
  var hiddenIframe = null;
  var isPrinting = false;

  // Print Settings State
  var ROTATE_STORAGE_KEY = "app_print_rotate_180";
  function isEdgeBrowser() {
    return typeof navigator !== "undefined" && /\bEdg\//i.test(navigator.userAgent || "");
  }
  function getInitialRotate180() {
    try {
      var saved = localStorage.getItem(ROTATE_STORAGE_KEY);
      if (saved === "1") return true;
      if (saved === "0") return false;
    } catch (e) {}
    return isEdgeBrowser();
  }

  var APP_DEFAULT_PRINTER_KEY = "app_default_printer";
  var cachedPrinters = [];
  var selectedPrinter = null;
  var appDefaultPrinter = "";
  var windowsDefaultPrinter = "";
  var copies = 1;
  var layoutMode = "portrait";
  var rotate180 = getInitialRotate180();
  var pagesMode = "all"; // 'all' or 'custom'
  var pageRangeStr = "";
  var allSheets = [];
  var activeSheetIndices = [];

  function isSamePrinterName(a, b) {
    if (!a || !b) return false;
    return String(a).trim().toLowerCase() === String(b).trim().toLowerCase();
  }

  function escapeHtmlStr(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatPrinterOptionLabel(p) {
    var isReady = !!p.is_ready;
    var isAppDef = isSamePrinterName(p.name, appDefaultPrinter);
    var statusStr = isReady ? " (Ready)" : " [Offline]";
    return (isAppDef ? "★ " : "") + p.name + statusStr + (isAppDef ? " — Default Software" : "");
  }

  function refreshSelectOptionLabels() {
    var select = document.getElementById("appPrintPrinterSelect");
    if (!select) return;
    for (var i = 0; i < select.options.length; i++) {
      var opt = select.options[i];
      var found = cachedPrinters.find(function (p) { return p.name === opt.value; });
      if (found) {
        opt.textContent = formatPrinterOptionLabel(found);
      }
    }
  }

  function renderSettingsOverviewCard() {
    if (!pagesContainerEl || allSheets.length > 0) return;
    var selName = selectedPrinter ? selectedPrinter.name : "Memuat printer...";
    var selStatus = selectedPrinter ? ((selectedPrinter.status || (selectedPrinter.is_ready ? "Ready" : "Offline")) + " • Port: " + (selectedPrinter.port || "-") + " • Driver: " + (selectedPrinter.driver || "Windows")) : "";
    var appDefText = appDefaultPrinter ? ("★ " + appDefaultPrinter) : "Belum diatur (Klik 'Set as Default' di sebelah kiri)";
    var winDefText = windowsDefaultPrinter || "Mengikuti pengaturan Windows";

    pagesContainerEl.innerHTML = [
      '<div class="app-print-settings-card">',
      '  <h3 class="app-print-settings-card-title">🖨️ Pengaturan Default Printer Software</h3>',
      '  <p class="app-print-settings-card-sub">Pilih printer yang ingin digunakan lalu klik <strong>Set as Default</strong>. Pengaturan ini tersimpan permanen di software ini sehingga saat login kembali Anda tidak perlu memilih printer lagi, dan <strong>tidak akan mengubah Default Printer di Windows</strong>.</p>',
      '  <div class="app-print-settings-grid">',
      '    <div class="app-print-settings-item">',
      '      <div class="app-print-settings-item-label">Printer Dipilih Saat Ini</div>',
      '      <div class="app-print-settings-item-val">' + escapeHtmlStr(selName) + '</div>',
      '      <div class="app-print-settings-item-meta">' + escapeHtmlStr(selStatus) + '</div>',
      '    </div>',
      '    <div class="app-print-settings-item is-highlight">',
      '      <div class="app-print-settings-item-label">Default Printer Software Ini (Otomatis Saat Login)</div>',
      '      <div class="app-print-settings-item-val" style="color:#34d399;">' + escapeHtmlStr(appDefText) + '</div>',
      '      <div class="app-print-settings-item-meta">Tersimpan khusus untuk aplikasi PCB Barcode Print</div>',
      '    </div>',
      '    <div class="app-print-settings-item">',
      '      <div class="app-print-settings-item-label">Default Printer Windows (Tidak Diubah)</div>',
      '      <div class="app-print-settings-item-val" style="color:#94a3b8;">' + escapeHtmlStr(winDefText) + '</div>',
      '      <div class="app-print-settings-item-meta">Pengaturan printer bawaan Windows Anda tetap aman & tidak berubah</div>',
      '    </div>',
      '  </div>',
      '</div>'
    ].join("\n");
  }

  function updateDefaultPrinterUI(justSaved) {
    var currentName = selectedPrinter ? selectedPrinter.name : "";
    var isCurrentDefault = !!(currentName && appDefaultPrinter && isSamePrinterName(currentName, appDefaultPrinter));

    var badge = document.getElementById("appPrintDefaultBadge");
    if (badge) {
      badge.style.display = isCurrentDefault ? "inline-flex" : "none";
    }

    var btnSetDef = document.getElementById("appPrintBtnSetDefault");
    var iconEl = document.getElementById("appPrintSetDefaultIcon");
    var textEl = document.getElementById("appPrintSetDefaultText");
    if (btnSetDef && iconEl && textEl) {
      if (isCurrentDefault) {
        btnSetDef.classList.add("is-default-active");
        iconEl.textContent = "★";
        textEl.textContent = "Default Printer Software Ini (Aktif)";
      } else {
        btnSetDef.classList.remove("is-default-active");
        iconEl.textContent = "☆";
        textEl.textContent = "Set as Default (Khusus Software Ini)";
      }
    }

    var noteEl = document.getElementById("appPrintDefaultInfoNote");
    if (noteEl) {
      if (justSaved && isCurrentDefault) {
        noteEl.className = "app-print-default-note is-saved";
        noteEl.textContent = "✓ Tersimpan! Otomatis dipakai saat login lagi (Default Windows tidak berubah).";
      } else if (isCurrentDefault) {
        noteEl.className = "app-print-default-note is-saved";
        noteEl.textContent = "✓ Otomatis dipilih setiap login • Default Windows tetap: " + (windowsDefaultPrinter || "Tidak berubah");
      } else if (appDefaultPrinter) {
        noteEl.className = "app-print-default-note";
        noteEl.textContent = "Default software saat ini: " + appDefaultPrinter + " • Klik tombol di atas untuk mengganti";
      } else {
        noteEl.className = "app-print-default-note";
        noteEl.textContent = "Hanya untuk software ini • Tidak mengubah default printer di Windows";
      }
    }

    renderSettingsOverviewCard();
  }

  function createModalDOM() {
    if (document.getElementById("appPrintModalOverlay")) {
      overlayEl = document.getElementById("appPrintModalOverlay");
      pagesContainerEl = document.getElementById("appPrintPagesContainer");
      zoomValEl = document.getElementById("appPrintZoomVal");
      return;
    }

    if (!document.body) {
      return;
    }

    var div = document.createElement("div");
    div.id = "appPrintModalOverlay";
    div.setAttribute("role", "dialog");
    div.setAttribute("aria-modal", "true");

    div.innerHTML = [
      '<div class="app-print-studio-layout">',
      '  <!-- LEFT SIDEBAR: Print Controls matching Windows Print settings -->',
      '  <div class="app-print-sidebar">',
      '    <div>',
      '      <div class="app-print-sidebar-header">',
      '        <h2 class="app-print-main-title">Print</h2>',
      '        <p class="app-print-sheet-summary" id="appPrintTotalSheets">Total: 1 sheet of paper</p>',
      '      </div>',
      '',
      '      <!-- 1. Printer Selection -->',
      '      <div class="app-print-control-group">',
      '        <div class="app-print-label-row">',
      '          <label class="app-print-field-label" for="appPrintPrinterSelect" style="margin-bottom:0;">Printer</label>',
      '          <span id="appPrintDefaultBadge" class="app-print-default-badge" style="display:none;">★ Default Software</span>',
      '        </div>',
      '        <div class="app-print-select-wrap">',
      '          <select id="appPrintPrinterSelect" class="app-print-select">',
      '            <option value="">Memuat printer Windows...</option>',
      '          </select>',
      '          <svg class="app-print-select-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>',
      '        </div>',
      '        <div id="appPrintPrinterStatusDesc" class="app-print-status-hint">Mencari status printer...</div>',
      '        <div class="app-print-default-actions">',
      '          <button type="button" id="appPrintBtnSetDefault" class="app-print-btn-setdefault" title="Simpan sebagai Default Printer khusus untuk software ini (tidak mengubah Default Printer di Windows)">',
      '            <span id="appPrintSetDefaultIcon" class="app-print-setdefault-icon">☆</span>',
      '            <span id="appPrintSetDefaultText">Set as Default (Khusus Software Ini)</span>',
      '          </button>',
      '          <div id="appPrintDefaultInfoNote" class="app-print-default-note">Hanya untuk software ini • Tidak mengubah default printer di Windows</div>',
      '        </div>',
      '        <div class="app-print-safety-strip">',
      '          <div class="app-print-safety-strip-head">',
      '            <span><span class="app-print-safety-strip-dot"></span>🛡️ Safety Auto-Cancel Guard</span>',
      '            <span style="color:#34d399;font-size:10.5px;">AKTIF</span>',
      '          </div>',
      '          <div class="app-print-safety-strip-desc">Otomatis membatalkan job print jika tombol Start/Pause printer terpijit atau printer mati/terputus.</div>',
      '          <div class="app-print-safety-sim-row">',
      '            <button type="button" id="appPrintBtnSimPause" class="app-print-safety-sim-btn" title="Uji simulasi jika print terhenti karena tombol Start/Pause di printer terpijit">⏸️ Uji Tombol Pause</button>',
      '            <button type="button" id="appPrintBtnSimOffline" class="app-print-safety-sim-btn is-offline-sim" title="Uji simulasi jika printer mati / tidak terdeteksi">🔌 Uji Printer Mati</button>',
      '          </div>',
      '        </div>',
      '      </div>',
      '',
      '      <!-- 2. Copies -->',
      '      <div class="app-print-control-group">',
      '        <label class="app-print-field-label" for="appPrintCopiesInput">Copies</label>',
      '        <input type="number" id="appPrintCopiesInput" class="app-print-input app-print-input-number" min="1" max="999" value="1">',
      '      </div>',
      '',
      '      <!-- 3. Layout -->',
      '      <div class="app-print-control-group">',
      '        <span class="app-print-field-label">Layout</span>',
      '        <div class="app-print-radios">',
      '          <label class="app-print-radio-label">',
      '            <input type="radio" name="appPrintLayoutRadio" value="portrait" checked>',
      '            <span>Portrait</span>',
      '          </label>',
      '          <label class="app-print-radio-label">',
      '            <input type="radio" name="appPrintLayoutRadio" value="landscape">',
      '            <span>Landscape</span>',
      '          </label>',
      '        </div>',
      '      </div>',
      '',
      '      <!-- 3b. Orientasi Keluar (Kompensasi Edge / Printer Label) -->',
      '      <div class="app-print-control-group">',
      '        <label class="app-print-checkbox-label" for="appPrintRotate180Chk">',
      '          <input type="checkbox" id="appPrintRotate180Chk">',
      '          <span>Putar Cetak 180° (Luruskan Printer)</span>',
      '        </label>',
      '        <div class="app-print-range-help" style="margin-left: 25px;">Otomatis aktif di Edge; di Google Chrome biarkan tidak dicentang</div>',
      '      </div>',
      '',
      '      <!-- 4. Pages / Pilih Halaman -->',
      '      <div class="app-print-control-group">',
      '        <span class="app-print-field-label">Pages</span>',
      '        <div class="app-print-radios">',
      '          <label class="app-print-radio-label">',
      '            <input type="radio" name="appPrintPagesRadio" value="all" checked>',
      '            <span>All</span>',
      '          </label>',
      '          <label class="app-print-radio-label">',
      '            <input type="radio" name="appPrintPagesRadio" value="custom">',
      '            <span>Rentang Halaman:</span>',
      '          </label>',
      '        </div>',
      '        <div class="app-print-range-box">',
      '          <input type="text" id="appPrintPageRangeInput" class="app-print-input" placeholder="e.g. 1-5, 8, 11-13" disabled>',
      '          <div class="app-print-range-help" id="appPrintRangeHelp">Pilih rentang halaman spesifik (misal: 1, 3-5)</div>',
      '        </div>',
      '      </div>',
      '    </div>',
      '',
      '    <!-- Action Buttons -->',
      '    <div class="app-print-sidebar-actions">',
      '      <button type="button" id="appPrintBtnExecute" class="app-print-btn-execute" title="Cetak langsung ke printer (Enter)">',
      '        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">',
      '          <polyline points="6 9 6 2 18 2 18 9"></polyline>',
      '          <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>',
      '          <rect x="6" y="14" width="12" height="8"></rect>',
      '        </svg>',
      '        <span id="appPrintBtnLabel">Cetak Langsung</span>',
      '      </button>',
      '      <button type="button" id="appPrintBtnCancel" class="app-print-btn-cancel">Batal</button>',
      '      <div class="app-print-direct-badge">⚡ Cetak instan langsung tanpa jendela popup</div>',
      '    </div>',
      '  </div>',
      '',
      '  <!-- RIGHT PREVIEW CANVAS -->',
      '  <div class="app-print-preview-area">',
      '    <div class="app-print-preview-toolbar">',
      '      <div class="app-print-preview-title">',
      '        <span id="appPrintPreviewTitleText">Pratinjau Cetak</span>',
      '        <span class="app-print-preview-pill" id="appPrintPreviewPill">Standar</span>',
      '      </div>',
      '      <div class="app-print-zoom-controls">',
      '        <button type="button" class="app-print-zoom-btn" id="appPrintBtnZoomOut" title="Zoom Out (-)">-</button>',
      '        <span class="app-print-zoom-val" id="appPrintZoomVal">100%</span>',
      '        <button type="button" class="app-print-zoom-btn" id="appPrintBtnZoomIn" title="Zoom In (+)">+</button>',
      '        <button type="button" class="app-print-zoom-btn" id="appPrintBtnZoomReset" style="width:auto;padding:0 8px;font-size:11px;" title="Reset Zoom">100%</button>',
      '      </div>',
      '    </div>',
      '    <div class="app-print-canvas" id="appPrintCanvas">',
      '      <div class="app-print-pages-container" id="appPrintPagesContainer"></div>',
      '    </div>',
      '  </div>',
      '</div>'
    ].join("\n");

    document.body.appendChild(div);
    overlayEl = div;
    pagesContainerEl = document.getElementById("appPrintPagesContainer");
    zoomValEl = document.getElementById("appPrintZoomVal");

    // Bind UI Event Listeners
    setupEventListeners();
  }

  function setupEventListeners() {
    // 1. Printer Select Dropdown (Hanya pilih untuk sesi/dokumen ini, TIDAK mengubah default printer Windows)
    var printerSelect = document.getElementById("appPrintPrinterSelect");
    if (printerSelect) {
      printerSelect.addEventListener("change", function () {
        var printerName = printerSelect.value;
        var found = cachedPrinters.find(function (p) { return p.name === printerName; });
        if (found) {
          AppPrinter.selectPrinter(found, false);
        } else if (printerName) {
          AppPrinter.selectPrinter({ name: printerName, is_ready: true }, false);
        }
      });
    }

    // 1b. Tombol Set as Default (Khusus Software Ini)
    var btnSetDefault = document.getElementById("appPrintBtnSetDefault");
    if (btnSetDefault) {
      btnSetDefault.addEventListener("click", function () {
        var select = document.getElementById("appPrintPrinterSelect");
        var printerName = (select && select.value) ? select.value : (selectedPrinter ? selectedPrinter.name : "");
        if (printerName) {
          AppPrinter.setAsDefault(printerName);
        }
      });
    }

    // 1c. Tombol Uji Simulasi Safety Auto-Cancel
    var btnSimPause = document.getElementById("appPrintBtnSimPause");
    if (btnSimPause) {
      btnSimPause.addEventListener("click", function () {
        var select = document.getElementById("appPrintPrinterSelect");
        var pName = (select && select.value) ? select.value : (selectedPrinter ? selectedPrinter.name : "Printer Label");
        var pPort = (selectedPrinter && selectedPrinter.port) ? selectedPrinter.port : "USB001";
        AppPrinter.triggerSafetySimulation("PRINTER_PAUSED_OR_STOPPED", pName, pPort);
      });
    }

    var btnSimOffline = document.getElementById("appPrintBtnSimOffline");
    if (btnSimOffline) {
      btnSimOffline.addEventListener("click", function () {
        var select = document.getElementById("appPrintPrinterSelect");
        var pName = (select && select.value) ? select.value : (selectedPrinter ? selectedPrinter.name : "Printer Label");
        var pPort = (selectedPrinter && selectedPrinter.port) ? selectedPrinter.port : "USB001";
        AppPrinter.triggerSafetySimulation("PRINTER_OFFLINE", pName, pPort);
      });
    }

    // 2. Copies Input
    var copiesInput = document.getElementById("appPrintCopiesInput");
    if (copiesInput) {
      copiesInput.addEventListener("input", function () {
        var val = parseInt(copiesInput.value, 10);
        copies = (!isNaN(val) && val >= 1) ? val : 1;
        updatePagesFilterAndTotal();
      });
    }

    // 3. Layout Radios (Portrait / Landscape)
    var layoutRadios = document.querySelectorAll('input[name="appPrintLayoutRadio"]');
    layoutRadios.forEach(function (radio) {
      radio.addEventListener("change", function () {
        layoutMode = radio.value;
        updatePagesFilterAndTotal();
      });
    });

    // 3b. Rotate 180 Toggle
    var rotateChk = document.getElementById("appPrintRotate180Chk");
    if (rotateChk) {
      rotateChk.checked = rotate180;
      rotateChk.addEventListener("change", function () {
        rotate180 = !!rotateChk.checked;
        try {
          localStorage.setItem(ROTATE_STORAGE_KEY, rotate180 ? "1" : "0");
        } catch (e) {}
      });
    }

    // 4. Pages Radios (All / Custom Range)
    var pagesRadios = document.querySelectorAll('input[name="appPrintPagesRadio"]');
    var rangeInput = document.getElementById("appPrintPageRangeInput");
    pagesRadios.forEach(function (radio) {
      radio.addEventListener("change", function () {
        pagesMode = radio.value;
        if (rangeInput) {
          rangeInput.disabled = (pagesMode !== "custom");
          if (pagesMode === "custom") {
            rangeInput.focus();
          }
        }
        updatePagesFilterAndTotal();
      });
    });

    // 5. Page Range Input
    if (rangeInput) {
      rangeInput.addEventListener("input", function () {
        pageRangeStr = rangeInput.value;
        updatePagesFilterAndTotal();
      });
    }

    // 6. Action Buttons
    var btnExecute = document.getElementById("appPrintBtnExecute");
    if (btnExecute) {
      btnExecute.addEventListener("click", function () {
        if (allSheets.length === 0) {
          var select = document.getElementById("appPrintPrinterSelect");
          var printerName = (select && select.value) ? select.value : (selectedPrinter ? selectedPrinter.name : "");
          if (printerName) {
            AppPrinter.setAsDefault(printerName).then(function () {
              setTimeout(function () {
                AppPrinter.close();
              }, 350);
            });
          } else {
            AppPrinter.close();
          }
        } else {
          AppPrinter.print();
        }
      });
    }

    var btnCancel = document.getElementById("appPrintBtnCancel");
    if (btnCancel) {
      btnCancel.addEventListener("click", function () {
        AppPrinter.close();
      });
    }

    // 7. Zoom Buttons
    document.getElementById("appPrintBtnZoomIn").addEventListener("click", function () {
      AppPrinter.zoomIn();
    });
    document.getElementById("appPrintBtnZoomOut").addEventListener("click", function () {
      AppPrinter.zoomOut();
    });
    document.getElementById("appPrintBtnZoomReset").addEventListener("click", function () {
      AppPrinter.resetZoom();
    });

    // 8. Keyboard Shortcuts (Enter to print, Esc to close)
    window.addEventListener("keydown", function (e) {
      if (!overlayEl || !overlayEl.classList.contains("is-open")) return;
      if (e.key === "Escape") {
        AppPrinter.close();
      } else if (e.key === "Enter" && !e.target.matches("input[type='text'], textarea, select")) {
        if (allSheets.length === 0) {
          var select = document.getElementById("appPrintPrinterSelect");
          var printerName = (select && select.value) ? select.value : (selectedPrinter ? selectedPrinter.name : "");
          if (printerName) {
            AppPrinter.setAsDefault(printerName).then(function () {
              setTimeout(function () {
                AppPrinter.close();
              }, 350);
            });
          }
        } else {
          AppPrinter.print();
        }
      }
    });
  }

  /**
   * Mengurai string rentang halaman (misal: "1-3, 5, 8") menjadi array index halaman (0-based)
   */
  function parsePageRange(str, totalPages) {
    if (!str || !str.trim()) return [];
    var parts = str.split(",");
    var pagesMap = {};
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i].trim();
      if (!p) continue;
      if (p.indexOf("-") !== -1) {
        var dashParts = p.split("-");
        var start = parseInt(dashParts[0], 10);
        var end = parseInt(dashParts[1], 10);
        if (!isNaN(start) && !isNaN(end) && start > 0) {
          var minVal = Math.min(start, end);
          var maxVal = Math.max(start, end);
          for (var n = minVal; n <= Math.min(maxVal, totalPages); n++) {
            if (n >= 1) pagesMap[n] = true;
          }
        }
      } else {
        var num = parseInt(p, 10);
        if (!isNaN(num) && num >= 1 && num <= totalPages) {
          pagesMap[num] = true;
        }
      }
    }
    var result = Object.keys(pagesMap).map(function (k) { return parseInt(k, 10) - 1; });
    result.sort(function (a, b) { return a - b; });
    return result;
  }

  /**
   * Memfilter halaman yang ditampilkan di pratinjau dan memperbarui kalkulasi total lembar
   */
  function updatePagesFilterAndTotal() {
    var totalSheets = allSheets.length;
    if (totalSheets === 0) return;

    if (pagesMode === "all") {
      activeSheetIndices = [];
      for (var i = 0; i < totalSheets; i++) {
        activeSheetIndices.push(i);
      }
    } else {
      activeSheetIndices = parsePageRange(pageRangeStr, totalSheets);
      if (activeSheetIndices.length === 0 && pageRangeStr.trim() === "") {
        // Range kosong -> default semua halaman
        for (var j = 0; j < totalSheets; j++) {
          activeSheetIndices.push(j);
        }
      }
    }

    // Update Sheet Cards Visibility in Preview
    if (pagesContainerEl) {
      var cards = pagesContainerEl.querySelectorAll(".app-print-sheet-card");
      var visibleCount = 0;
      cards.forEach(function (card, idx) {
        if (activeSheetIndices.indexOf(idx) !== -1) {
          card.classList.remove("is-hidden");
          visibleCount++;
        } else {
          card.classList.add("is-hidden");
        }
      });
    }

    // Update Total Summary
    var totalToPrint = activeSheetIndices.length * copies;
    var summaryEl = document.getElementById("appPrintTotalSheets");
    if (summaryEl) {
      summaryEl.textContent = "Total: " + totalToPrint + " lembar kertas (" + activeSheetIndices.length + " hal × " + copies + " salinan)";
    }

    var rangeHelp = document.getElementById("appPrintRangeHelp");
    if (rangeHelp) {
      if (pagesMode === "custom") {
        rangeHelp.textContent = activeSheetIndices.length > 0 
          ? "Halaman dipilih: " + (activeSheetIndices.map(function(n){return n+1;}).join(", ")) 
          : "Format range tidak valid atau di luar halaman 1-" + totalSheets;
        rangeHelp.style.color = activeSheetIndices.length > 0 ? "#34d399" : "#f87171";
      } else {
        rangeHelp.textContent = "Pilih rentang halaman spesifik (misal: 1, 3-5)";
        rangeHelp.style.color = "#94a3b8";
      }
    }
  }

  function getHiddenIframe() {
    if (hiddenIframe && document.body.contains(hiddenIframe)) {
      return hiddenIframe;
    }
    hiddenIframe = document.createElement("iframe");
    hiddenIframe.id = "appPrintDirectSilentIframe";
    hiddenIframe.style.position = "fixed";
    hiddenIframe.style.right = "0";
    hiddenIframe.style.bottom = "0";
    hiddenIframe.style.width = "0";
    hiddenIframe.style.height = "0";
    hiddenIframe.style.border = "0";
    hiddenIframe.style.visibility = "hidden";
    document.body.appendChild(hiddenIframe);
    return hiddenIframe;
  }

  function countSheetLabels(paperEl) {
    if (!paperEl) return 0;
    var nodes = paperEl.querySelectorAll("td.label, .sticker, .ev-sn-sticker, .barcode-box, .np-card");
    var count = 0;
    for (var i = 0; i < nodes.length; i++) {
      var node = nodes[i];
      if (node.classList && node.classList.contains("ev-sn-sticker")) {
        count++;
      } else if (node.children.length > 0 || (node.textContent && node.textContent.trim() !== "")) {
        count++;
      }
    }
    return count;
  }

  var AppPrinter = {
    open: function (config) {
      createModalDOM();
      if (!overlayEl) return;

      currentConfig = config || {};
      allSheets = currentConfig.sheets || (currentConfig.html ? [currentConfig.html] : []);
      copies = 1;
      layoutMode = "portrait";
      pagesMode = "all";
      pageRangeStr = "";

      // Reset Form Controls
      var copiesInput = document.getElementById("appPrintCopiesInput");
      if (copiesInput) copiesInput.value = "1";

      var rangeInput = document.getElementById("appPrintPageRangeInput");
      if (rangeInput) {
        rangeInput.value = "";
        rangeInput.disabled = true;
      }

      var radioAll = document.querySelector('input[name="appPrintPagesRadio"][value="all"]');
      if (radioAll) radioAll.checked = true;

      var radioPortrait = document.querySelector('input[name="appPrintLayoutRadio"][value="portrait"]');
      if (radioPortrait) radioPortrait.checked = true;

      rotate180 = getInitialRotate180();
      var rotateChk = document.getElementById("appPrintRotate180Chk");
      if (rotateChk) rotateChk.checked = rotate180;

      // Titles
      var titleText = document.getElementById("appPrintPreviewTitleText");
      if (titleText) titleText.textContent = currentConfig.title || "Pratinjau Cetak Barcode";

      var pill = document.getElementById("appPrintPreviewPill");
      if (pill) pill.textContent = currentConfig.badge || (currentConfig.pageSize || "Standar");

      // Inject custom CSS
      var styleTag = document.getElementById("appPrintInjectedStyles");
      if (!styleTag) {
        styleTag = document.createElement("style");
        styleTag.id = "appPrintInjectedStyles";
        document.head.appendChild(styleTag);
      }
      styleTag.textContent = currentConfig.css || "";

      // Render Cards in Container
      pagesContainerEl.innerHTML = "";
      for (var i = 0; i < allSheets.length; i++) {
        var card = document.createElement("div");
        card.className = "app-print-sheet-card";

        var paper = document.createElement("div");
        paper.className = "app-print-sheet-paper";
        paper.innerHTML = allSheets[i];

        var labelCountOnSheet = countSheetLabels(paper);
        var metaText = currentConfig.badge
          ? String(currentConfig.badge)
          : (labelCountOnSheet > 0 ? (labelCountOnSheet + " label") : "");

        var header = document.createElement("div");
        header.className = "app-print-sheet-card-header";
        header.innerHTML =
          '<span class="app-print-sheet-page-badge">Halaman <span class="app-print-sheet-page-num">' + (i + 1) + '</span> dari ' + allSheets.length + '</span>' +
          (metaText ? '<span class="app-print-sheet-meta">' + escapeHtmlStr(metaText) + '</span>' : '');

        card.appendChild(header);
        card.appendChild(paper);
        pagesContainerEl.appendChild(card);
      }

      var btnLabel = document.getElementById("appPrintBtnLabel");
      var summaryEl = document.getElementById("appPrintTotalSheets");
      if (allSheets.length === 0) {
        if (btnLabel) btnLabel.textContent = "Simpan Default & Tutup";
        if (summaryEl) summaryEl.textContent = "Mode Pengaturan Default Printer Software";
        renderSettingsOverviewCard();
      } else {
        if (btnLabel) btnLabel.textContent = "Cetak Langsung";
      }

      // Reset zoom to 100% before onRender so OfflineTextRenderer measures true 1:1 dimensions
      this.resetZoom();

      // Execute onRender hook for Barcodes, QR, HD text
      if (typeof currentConfig.onRender === "function") {
        try {
          currentConfig.onRender(pagesContainerEl);
        } catch (err) {
          console.error("Error executing onRender in AppPrinter:", err);
        }
      }

      // Calculate initial pages
      updatePagesFilterAndTotal();

      // Load Windows Printers list
      this.loadPrinters();

      // Show Modal
      overlayEl.classList.add("is-open");
      document.body.style.overflow = "hidden";

      if (typeof global.hideAppLoadingScreen === "function") {
        requestAnimationFrame(function () {
          requestAnimationFrame(function () {
            global.hideAppLoadingScreen();
          });
        });
      }
    },

    openHtml: function (fullHtml, title) {
      createModalDOM();

      var parser = new DOMParser();
      var doc = parser.parseFromString(fullHtml, "text/html");

      var customStyles = "";
      doc.querySelectorAll("style").forEach(function (s) {
        customStyles += s.textContent + "\n";
      });

      var pageTitle = title || (doc.title || "Pratinjau Cetak Barcode");

      var sheets = [];
      var pageEls = doc.querySelectorAll(".page");
      if (pageEls.length === 0) {
        pageEls = doc.querySelectorAll(".print-page");
      }
      if (pageEls.length === 0) {
        pageEls = doc.querySelectorAll(".ev-sn-sticker");
      }
      if (pageEls.length > 0) {
        pageEls.forEach(function (p) {
          sheets.push(p.outerHTML);
        });
      } else if (doc.body && doc.body.children.length > 0) {
        sheets.push(doc.body.innerHTML);
      } else {
        sheets.push(fullHtml);
      }

      var scriptBodies = [];
      doc.querySelectorAll("script").forEach(function (sc) {
        var txt = sc.textContent || "";
        txt = txt.replace(/window\.print\s*\(\s*\)/g, "// window.print()");
        if (txt.trim()) {
          scriptBodies.push(txt);
        }
      });

      var labelCount = doc.querySelectorAll(".label, .barcode, .np-card, .ev-sn-card, .sticker").length;
      if (!labelCount) labelCount = sheets.length;

      var pageSizeMatch = customStyles.match(/@page\s*\{[^}]*?\bsize\s*:\s*([^;\}]+)/i);
      var detectedPageSize = pageSizeMatch ? pageSizeMatch[1].trim() : "";
      var bodyClass = (doc.body && doc.body.className) ? doc.body.className : "";

      AppPrinter.open({
        title: pageTitle,
        pageSize: detectedPageSize || undefined,
        bodyClass: bodyClass,
        css: customStyles,
        sheets: sheets,
        totalLabels: labelCount,
        totalPages: sheets.length,
        onRender: function (container) {
          scriptBodies.forEach(function (src) {
            try {
              var fn = new Function("container", "document", src);
              fn(container, document);
            } catch (err) {
              console.warn("Script execution inside print sheet:", err);
            }
          });
          if (window.OfflineTextRenderer && typeof window.OfflineTextRenderer.renderAll === "function") {
            window.OfflineTextRenderer.renderAll(container, ".hd-text[data-text]");
          }
          if (window.QRCodeRuntime && typeof window.QRCodeRuntime.renderAll === "function") {
            window.QRCodeRuntime.renderAll(container, ".np-qr[data-qr]");
          }
        }
      });
    },

    /**
     * Menyimpan printer sebagai Default KHUSUS untuk software ini
     * Tanpa mengubah setting Default Printer di Windows
     */
    setAsDefault: function (printerName) {
      if (!printerName) return Promise.resolve(false);
      appDefaultPrinter = printerName;
      try {
        localStorage.setItem(APP_DEFAULT_PRINTER_KEY, printerName);
        localStorage.setItem("preferred_printer", printerName);
      } catch (e) {}

      cachedPrinters.forEach(function (p) {
        p.is_app_default = isSamePrinterName(p.name, printerName);
      });

      refreshSelectOptionLabels();
      updateDefaultPrinterUI(true);

      var fd = new FormData();
      fd.append("action", "set_app_default");
      fd.append("name", printerName);
      return fetch("api_printers.php", { method: "POST", body: fd })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.windows_default_printer) {
            windowsDefaultPrinter = data.windows_default_printer;
          }
          updateDefaultPrinterUI(true);
          if (typeof global.updateStudioPrinterStatus === "function") {
            global.updateStudioPrinterStatus();
          }
          return !!(data && data.success);
        })
        .catch(function (err) {
          console.warn("Gagal menyimpan default printer aplikasi:", err);
          return false;
        });
    },

    /**
     * Mengarahkan spooler sementara sesaat sebelum silent print
     */
    preparePrintOnWindows: function (printerName) {
      if (!printerName) return Promise.resolve(false);
      var fd = new FormData();
      fd.append("action", "prepare_print");
      fd.append("name", printerName);
      return fetch("api_printers.php", { method: "POST", body: fd })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          return !!(data && data.success);
        })
        .catch(function (err) {
          console.warn("Gagal menyiapkan printer untuk cetak:", err);
          return false;
        });
    },

    /**
     * Mengembalikan Default Printer Windows ke setting asli pengguna segera setelah silent print
     */
    restoreWindowsDefaultPrinter: function () {
      var fd = new FormData();
      fd.append("action", "restore_windows_default");
      return fetch("api_printers.php", { method: "POST", body: fd })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.windows_default_printer) {
            windowsDefaultPrinter = data.windows_default_printer;
            updateDefaultPrinterUI(false);
          }
          return !!(data && data.success);
        })
        .catch(function () {
          return false;
        });
    },

    syncPrinterToWindows: function (printerName) {
      return AppPrinter.setAsDefault(printerName);
    },

    loadPrinters: function () {
      fetch("api_printers.php?action=list&t=" + Date.now())
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.success && Array.isArray(data.printers)) {
            cachedPrinters = data.printers;
            windowsDefaultPrinter = (data.windows_default_printer || "").trim();

            var serverAppDefault = (data.app_default_printer || "").trim();
            var localAppDefault = "";
            try {
              localAppDefault = (localStorage.getItem(APP_DEFAULT_PRINTER_KEY) || "").trim();
            } catch (e) {}

            if (serverAppDefault) {
              appDefaultPrinter = serverAppDefault;
              try {
                localStorage.setItem(APP_DEFAULT_PRINTER_KEY, serverAppDefault);
                localStorage.setItem("preferred_printer", serverAppDefault);
              } catch (e) {}
            } else if (localAppDefault) {
              appDefaultPrinter = localAppDefault;
              // Sinkronkan ke server config agar tetap tersimpan saat login lagi
              var fdSync = new FormData();
              fdSync.append("action", "set_app_default");
              fdSync.append("name", localAppDefault);
              fetch("api_printers.php", { method: "POST", body: fdSync }).catch(function () {});
            } else {
              appDefaultPrinter = "";
            }

            var select = document.getElementById("appPrintPrinterSelect");
            if (!select) return;

            select.innerHTML = "";
            var pref = "";
            try {
              pref = (localStorage.getItem("preferred_printer") || "").trim();
            } catch (e) {}

            var targetFound = null;

            // Prioritas 1: Default Printer Software yang sudah di-Set as Default
            if (appDefaultPrinter) {
              targetFound = cachedPrinters.find(function (p) {
                return isSamePrinterName(p.name, appDefaultPrinter);
              }) || null;
            }

            // Prioritas 2: Preferred printer terakhir jika belum ada default software
            if (!targetFound && pref) {
              targetFound = cachedPrinters.find(function (p) {
                return isSamePrinterName(p.name, pref);
              }) || null;
            }

            // Prioritas 3: Default Windows atau printer pertama
            if (!targetFound) {
              targetFound = cachedPrinters.find(function (p) {
                return !!p.is_windows_default || !!p.is_default;
              }) || cachedPrinters[0] || null;
            }

            cachedPrinters.forEach(function (p) {
              var opt = document.createElement("option");
              opt.value = p.name;
              opt.textContent = formatPrinterOptionLabel(p);
              if (targetFound && p.name === targetFound.name) {
                opt.selected = true;
              }
              select.appendChild(opt);
            });

            if (targetFound) {
              // false = JANGAN ubah default printer di Windows
              AppPrinter.selectPrinter(targetFound, false);
            } else {
              updateDefaultPrinterUI(false);
            }
          }
        })
        .catch(function (err) {
          console.warn("Gagal memuat list printer:", err);
        });
    },

    selectPrinter: function (printer, saveAsAppDefault) {
      if (!printer) return;
      selectedPrinter = printer;

      var select = document.getElementById("appPrintPrinterSelect");
      if (select && select.value !== printer.name) {
        select.value = printer.name;
      }

      var statusDesc = document.getElementById("appPrintPrinterStatusDesc");
      if (statusDesc) {
        var isAppDef = isSamePrinterName(printer.name, appDefaultPrinter);
        statusDesc.textContent = "Status: " + (printer.status || (printer.is_ready ? "Ready" : "Offline")) +
          " • Port: " + (printer.port || "USB") +
          (isAppDef ? " • ★ Default Software" : "");
        if (printer.is_ready) {
          statusDesc.className = "app-print-status-hint";
        } else {
          statusDesc.className = "app-print-status-hint offline";
        }
      }

      updateDefaultPrinterUI(false);

      if (saveAsAppDefault) {
        AppPrinter.setAsDefault(printer.name);
      }
    },

    /**
     * Mengirim perintah cetak langsung ke printer Windows via backend DirectPrint.exe
     * TANPA membuka jendela popup print browser (window.print) dan TANPA mengubah Default Printer Windows.
     */
    print: function () {
      if (!currentConfig || isPrinting || activeSheetIndices.length === 0) return;
      isPrinting = true;

      var btn = document.getElementById("appPrintBtnExecute");
      var btnLabel = document.getElementById("appPrintBtnLabel");
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = "0.75";
      }

      var select = document.getElementById("appPrintPrinterSelect");
      var targetPrinterName = (select && select.value) ? select.value : (selectedPrinter ? selectedPrinter.name : "");

      if (btnLabel) btnLabel.textContent = "Mengirim ke Printer...";

      var rawPageSize = (currentConfig.pageSize || "79.95mm 34.03mm").trim();
      var twoLenMatch = rawPageSize.match(/^([0-9.]+\s*[a-z%]+)\s+([0-9.]+\s*[a-z%]+)$/i);
      var finalPageSize = rawPageSize;
      if (twoLenMatch) {
        finalPageSize = (layoutMode === "landscape")
          ? (twoLenMatch[2] + " " + twoLenMatch[1])
          : (twoLenMatch[1] + " " + twoLenMatch[2]);
      } else if (layoutMode && rawPageSize.toLowerCase().indexOf(layoutMode) === -1) {
        finalPageSize = rawPageSize + " " + layoutMode;
      }
      var customCss = currentConfig.css || "";
      var bodyClassAttr = currentConfig.bodyClass
        ? ' class="' + String(currentConfig.bodyClass).replace(/"/g, "&quot;") + '"'
        : "";

      var htmlParts = [];
      htmlParts.push("<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>" + escapeHtmlStr(currentConfig.title || "Cetak Barcode") + "</title>");
      htmlParts.push("<style>");
      htmlParts.push("@page { size: " + finalPageSize + "; margin: 0 !important; }");
      htmlParts.push("* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }");
      htmlParts.push("html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; width: 100% !important; }");
      htmlParts.push(customCss);
      htmlParts.push("@page { size: " + finalPageSize + "; margin: 0 !important; }");
      htmlParts.push("@media print { .print-page, .page, .ev-sn-sticker { break-after: page; page-break-after: always; } .print-page:last-child, .page:last-child, .ev-sn-sticker:last-child { break-after: auto; page-break-after: auto; } }");
      if (rotate180) {
        htmlParts.push("@media print { .page, .print-page, .ev-sn-sticker { transform: rotate(180deg) !important; transform-origin: center center !important; } }");
      }
      htmlParts.push("</style></head><body" + bodyClassAttr + ">");

      var papers = pagesContainerEl.querySelectorAll(".app-print-sheet-paper");
      for (var c = 0; c < copies; c++) {
        for (var i = 0; i < activeSheetIndices.length; i++) {
          var sheetIdx = activeSheetIndices[i];
          if (papers && papers[sheetIdx]) {
            htmlParts.push(papers[sheetIdx].innerHTML);
          } else if (allSheets[sheetIdx]) {
            htmlParts.push(allSheets[sheetIdx]);
          }
        }
      }

      htmlParts.push("</body></html>");
      var fullPrintHtml = htmlParts.join("\n");

      var fd = new FormData();
      fd.append("action", "direct_print");
      fd.append("printer", targetPrinterName);
      fd.append("html", fullPrintHtml);

      fetch("api_printers.php", { method: "POST", body: fd })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.safety_triggered) {
            if (btnLabel) btnLabel.textContent = "⛔ Job Print Di-Cancel";
            var statusErr = document.getElementById("appPrintPrinterStatusDesc");
            if (statusErr) {
              statusErr.className = "app-print-status-hint offline";
              statusErr.textContent = "⛔ Safety Auto-Cancel: Job print telah dibatalkan otomatis.";
            }
            AppPrinter.showSafetyWarningModal(data);
            return;
          }

          if (data && data.success) {
            if (btnLabel) {
              btnLabel.textContent = "✓ Terkirim ke Printer (" + (data.pages || activeSheetIndices.length) + " hal)";
            }
            var statusDesc = document.getElementById("appPrintPrinterStatusDesc");
            if (statusDesc) {
              statusDesc.className = "app-print-status-hint";
              statusDesc.textContent = "✓ Perintah cetak dikirim ke: " + (data.printer || targetPrinterName) + " (Safety Guard Memantau...)";
            }
            // Pantau antrean selama proses cetak berlangsung: jika tombol Start/Pause dipijit atau printer mati di tengah jalan -> Auto-Cancel!
            AppPrinter.startActiveJobWatchdog(data.printer || targetPrinterName, 14000);
          } else {
            var errMsg = (data && data.error) ? data.error : "Gagal mengirim perintah cetak ke printer.";
            alert("Gagal Mencetak: " + errMsg);
            if (btnLabel) btnLabel.textContent = "Cetak Langsung";
          }
        })
        .catch(function (err) {
          console.error("Direct print error:", err);
          alert("Terjadi kesalahan saat mengirim perintah ke printer.");
          if (btnLabel) btnLabel.textContent = "Cetak Langsung";
        })
        .finally(function () {
          setTimeout(function () {
            isPrinting = false;
            if (btnLabel) btnLabel.textContent = "Cetak Langsung";
            if (btn) {
              btn.disabled = false;
              btn.style.opacity = "1";
            }
          }, 1100);
        });
    },

    /**
     * Memantau antrean spooler & status hardware printer secara aktif selama job berjalan
     */
    startActiveJobWatchdog: function (printerName, durationMs) {
      if (!printerName) return;
      if (this._safetyWatchTimer) {
        clearInterval(this._safetyWatchTimer);
        this._safetyWatchTimer = null;
      }

      var startTime = Date.now();
      var maxDuration = durationMs || 14000;
      var isChecking = false;

      this._safetyWatchTimer = setInterval(function () {
        if (isChecking) return;
        if (Date.now() - startTime > maxDuration) {
          clearInterval(AppPrinter._safetyWatchTimer);
          AppPrinter._safetyWatchTimer = null;
          return;
        }

        isChecking = true;
        var fd = new FormData();
        fd.append("action", "safety_watch");
        fd.append("printer", printerName);
        fd.append("active", "0");

        fetch("api_printers.php", { method: "POST", body: fd })
          .then(function (r) { return r.json(); })
          .then(function (wData) {
            isChecking = false;
            if (wData && wData.safety_triggered) {
              clearInterval(AppPrinter._safetyWatchTimer);
              AppPrinter._safetyWatchTimer = null;
              AppPrinter.showSafetyWarningModal(wData);
            } else if (wData && wData.active_jobs === 0 && (Date.now() - startTime > 3000)) {
              // Semua halaman sudah selesai tercetak dengan aman
              clearInterval(AppPrinter._safetyWatchTimer);
              AppPrinter._safetyWatchTimer = null;
            }
          })
          .catch(function () {
            isChecking = false;
          });
      }, 650);
    },

    /**
     * Membunyikan nada peringatan safety menggunakan Web Audio API
     */
    playSafetyWarningTone: function (isOffline) {
      try {
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        var ctx = new AudioCtx();
        var freqs = isOffline ? [980, 740, 980, 740] : [880, 1180, 880];
        var startAt = ctx.currentTime + 0.02;

        for (var i = 0; i < freqs.length; i++) {
          var osc = ctx.createOscillator();
          var gain = ctx.createGain();
          osc.type = "triangle";
          osc.frequency.value = freqs[i];

          var t0 = startAt + (i * 0.22);
          var t1 = t0 + 0.16;
          gain.gain.setValueAtTime(0.001, t0);
          gain.gain.exponentialRampToValueAtTime(0.28, t0 + 0.02);
          gain.gain.exponentialRampToValueAtTime(0.001, t1);

          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.start(t0);
          osc.stop(t1);
        }
      } catch (e) {}
    },

    /**
     * Membuat elemen DOM untuk Pop-up Modal Warning Safety Auto-Cancel
     */
    ensureSafetyModalDOM: function () {
      var existing = document.getElementById("appPrintSafetyModalOverlay");
      if (existing) return existing;
      if (!document.body) return null;

      var wrap = document.createElement("div");
      wrap.id = "appPrintSafetyModalOverlay";
      wrap.setAttribute("role", "alertdialog");
      wrap.setAttribute("aria-modal", "true");

      wrap.innerHTML = [
        '<div class="app-safety-modal-dialog" id="appSafetyModalDialog">',
        '  <div class="app-safety-top-bar">',
        '    <div class="app-safety-icon-orb" id="appSafetyIconOrb">⏸️</div>',
        '    <div class="app-safety-head-texts">',
        '      <div class="app-safety-tag-pill" id="appSafetyTagPill">🛡️ SISTEM SAFETY PROTEKSI CETAK</div>',
        '      <h3 class="app-safety-title" id="appSafetyTitle">JOB PRINT OTOMATIS DI-CANCEL!</h3>',
        '      <p class="app-safety-subtitle" id="appSafetySubtitle">Perintah cetak telah dihentikan dan dihapus dari antrean printer.</p>',
        '    </div>',
        '  </div>',
        '  <div class="app-safety-body">',
        '    <div class="app-safety-cancel-banner">',
        '      <div class="app-safety-cancel-left">',
        '        <div class="app-safety-cancel-check">✓</div>',
        '        <div>',
        '          <div class="app-safety-cancel-heading">Job yang Diperintahkan Ngeprint Sudah Di-Cancel Otomatis</div>',
        '          <div class="app-safety-cancel-sub">Antrean Windows Spooler telah dibersihkan agar tidak mencetak ulang / duplikasi nomor seri.</div>',
        '        </div>',
        '      </div>',
        '      <div class="app-safety-cancel-stamp">JOB CANCELLED</div>',
        '    </div>',
        '    <div class="app-safety-metrics-grid">',
        '      <div class="app-safety-metric-card">',
        '        <div class="app-safety-metric-label">Status Perintah Cetak</div>',
        '        <div class="app-safety-metric-value" style="color:#f87171;" id="appSafetyMetricStatus">DI-CANCEL OTOMATIS</div>',
        '      </div>',
        '      <div class="app-safety-metric-card">',
        '        <div class="app-safety-metric-label">Printer & Port</div>',
        '        <div class="app-safety-metric-value" id="appSafetyMetricPrinter">-</div>',
        '      </div>',
        '      <div class="app-safety-metric-card">',
        '        <div class="app-safety-metric-label">Waktu & Dokumen</div>',
        '        <div class="app-safety-metric-value" id="appSafetyMetricJob">-</div>',
        '      </div>',
        '    </div>',
        '    <div class="app-safety-details-dropdown" id="appSafetyDetailsDropdown">',
        '      <div class="app-safety-reason-box">',
        '        <div class="app-safety-section-title">⚠️ Penyebab Auto-Cancel Terdeteksi:</div>',
        '        <p class="app-safety-reason-text" id="appSafetyReasonText">-</p>',
        '      </div>',
        '      <div class="app-safety-reason-box">',
        '        <div class="app-safety-section-title">🛠️ Panduan Pemulihan sebelum Mencetak Kembali:</div>',
        '        <ol class="app-safety-steps-list" id="appSafetyStepsList"></ol>',
        '      </div>',
        '    </div>',
        '  </div>',
        '  <div class="app-safety-footer">',
        '    <div class="app-safety-footer-left">',
        '      <button type="button" class="app-safety-btn-secondary" id="appSafetyBtnPurge">🔄 Pastikan Antrean Kosong</button>',
        '    </div>',
        '    <div class="app-safety-footer-right">',
        '      <button type="button" class="app-safety-btn-detail" id="appSafetyBtnToggleDetail">Show Detail ▾</button>',
        '      <button type="button" class="app-safety-btn-primary" id="appSafetyBtnAck">✓ Mengerti, Tutup</button>',
        '    </div>',
        '  </div>',
        '</div>'
      ].join("\n");

      document.body.appendChild(wrap);

      var btnToggleDetail = document.getElementById("appSafetyBtnToggleDetail");
      var detailsDropdown = document.getElementById("appSafetyDetailsDropdown");
      if (btnToggleDetail && detailsDropdown) {
        btnToggleDetail.addEventListener("click", function () {
          var isExpanded = detailsDropdown.classList.toggle("is-expanded");
          btnToggleDetail.classList.toggle("is-active", isExpanded);
          btnToggleDetail.textContent = isExpanded ? "Hide Detail ▴" : "Show Detail ▾";
        });
      }

      var btnAck = document.getElementById("appSafetyBtnAck");
      if (btnAck) {
        btnAck.addEventListener("click", function () {
          AppPrinter.closeSafetyWarningModal();
        });
      }

      var btnPurge = document.getElementById("appSafetyBtnPurge");
      if (btnPurge) {
        btnPurge.addEventListener("click", function () {
          var pName = wrap.getAttribute("data-printer") || "";
          btnPurge.textContent = "Membersihkan Antrean...";
          var fd = new FormData();
          fd.append("action", "purge_jobs");
          fd.append("printer", pName);
          fetch("api_printers.php", { method: "POST", body: fd })
            .then(function () {
              btnPurge.textContent = "✓ Antrean Printer 100% Bersih";
              AppPrinter.loadPrinters();
              if (typeof global.updateStudioPrinterStatus === "function") {
                global.updateStudioPrinterStatus();
              }
            })
            .catch(function () {
              btnPurge.textContent = "🔄 Pastikan Antrean Kosong";
            });
        });
      }

      return wrap;
    },

    /**
     * Menampilkan Pop-up Modal Warning ketika Safety Auto-Cancel membatalkan job print
     */
    showSafetyWarningModal: function (safetyData) {
      var modal = this.ensureSafetyModalDOM();
      if (!modal) return;

      var data = safetyData || {};
      var code = String(data.safety_code || "PRINTER_PAUSED_OR_STOPPED");
      var isOffline = (code === "PRINTER_OFFLINE");
      var printerName = data.printer || (selectedPrinter ? selectedPrinter.name : "Printer Label");
      var portName = data.port || (selectedPrinter && selectedPrinter.port ? selectedPrinter.port : "USB");
      var cancelledCount = data.cancelled_jobs || 1;
      var timeStr = data.timestamp || new Date().toLocaleTimeString("id-ID");

      modal.setAttribute("data-printer", printerName);

      var dialog = document.getElementById("appSafetyModalDialog");
      var orb = document.getElementById("appSafetyIconOrb");
      var pill = document.getElementById("appSafetyTagPill");
      var title = document.getElementById("appSafetyTitle");
      var subtitle = document.getElementById("appSafetySubtitle");
      var metricStatus = document.getElementById("appSafetyMetricStatus");
      var metricPrinter = document.getElementById("appSafetyMetricPrinter");
      var metricJob = document.getElementById("appSafetyMetricJob");
      var reasonText = document.getElementById("appSafetyReasonText");
      var stepsList = document.getElementById("appSafetyStepsList");
      var btnPurge = document.getElementById("appSafetyBtnPurge");
      var detailsDropdown = document.getElementById("appSafetyDetailsDropdown");
      var btnToggleDetail = document.getElementById("appSafetyBtnToggleDetail");

      if (detailsDropdown) {
        detailsDropdown.classList.remove("is-expanded");
      }
      if (btnToggleDetail) {
        btnToggleDetail.classList.remove("is-active");
        btnToggleDetail.textContent = "Show Detail ▾";
      }

      if (btnPurge) {
        btnPurge.textContent = "🔄 Pastikan Antrean Kosong";
      }

      if (dialog) {
        if (isOffline) {
          dialog.classList.add("is-offline-mode");
        } else {
          dialog.classList.remove("is-offline-mode");
        }
      }

      if (isOffline) {
        if (orb) orb.textContent = "🔌";
        if (pill) pill.textContent = "🔌 SAFETY INTERLOCK: PRINTER MATI / TIDAK TERDETEKSI";
        if (title) title.textContent = "PRINTER MATI / TERPUTUS — JOB PRINT SUDAH DI-CANCEL!";
        if (subtitle) subtitle.textContent = "Printer tidak terdeteksi atau dalam kondisi mati saat perintah cetak dijalankan.";
      } else {
        if (orb) orb.textContent = "⏸️";
        if (pill) pill.textContent = "⏸️ SAFETY INTERLOCK: TOMBOL START / PAUSE PRINTER";
        if (title) title.textContent = "PRINT TERHENTI DI PRINTER — JOB PRINT SUDAH DI-CANCEL!";
        if (subtitle) subtitle.textContent = "Terdeteksi cetakan terhenti karena tombol Start / Pause pada printer terpijit atau intervensi perangkat.";
      }

      if (metricStatus) {
        metricStatus.textContent = "SUDAH DI-CANCEL (" + cancelledCount + " Job Dihapus)";
      }
      if (metricPrinter) {
        metricPrinter.textContent = printerName + (portName ? " (" + portName + ")" : "");
      }
      if (metricJob) {
        metricJob.textContent = timeStr + " • " + (data.cancelled_documents || "PCB Barcode Print");
      }
      if (reasonText) {
        reasonText.textContent = data.reason || (isOffline
          ? "Printer mati atau kabel koneksi tidak terdeteksi. Job print yang diperintahkan telah dibatalkan otomatis."
          : "Proses print terhenti karena tombol Start/Pause pada printer tertekan. Job print yang diperintahkan telah dibatalkan otomatis.");
      }

      if (stepsList) {
        if (isOffline) {
          stepsList.innerHTML = [
            "<li>Pastikan saklar power printer dalam posisi <strong>ON</strong> dan kabel USB/koneksi terpasang dengan benar.</li>",
            "<li>Tunggu hingga lampu indikator printer menyala <strong>Ready (Hijau)</strong>.</li>",
            "<li>Karena job print sebelumnya <strong>sudah di-cancel otomatis</strong>, printer aman dan tidak akan mencetak sendiri saat menyala.</li>",
            "<li>Periksa nomor barcode fisik terakhir yang sudah tercetak, lalu kirim perintah cetak ulang dari software.</li>"
          ].join("");
        } else {
          stepsList.innerHTML = [
            "<li>Periksa tombol <strong>Start / Pause / Feed</strong> pada fisik printer dan pastikan lampu indikator tidak berkedip (kembali <strong>Hijau Solid / Ready</strong>).</li>",
            "<li>Periksa posisi roll sticker label dan ribbon agar sensor tidak tertahan.</li>",
            "<li>Job print yang tadi diperintahkan <strong>sudah di-cancel otomatis</strong> sehingga tidak akan melanjutkan cetakan yang berisiko duplikat.</li>",
            "<li>Cek nomor seri barcode terakhir yang berhasil keluar, lalu lakukan print ulang mulai dari nomor berikutnya.</li>"
          ].join("");
        }
      }

      modal.classList.add("is-open");
      this.playSafetyWarningTone(isOffline);
    },

    closeSafetyWarningModal: function () {
      var modal = document.getElementById("appPrintSafetyModalOverlay");
      if (modal) {
        modal.classList.remove("is-open");
      }
      this.loadPrinters();
      if (typeof global.updateStudioPrinterStatus === "function") {
        global.updateStudioPrinterStatus();
      }
    },

    /**
     * Menjalankan simulasi uji Safety Auto-Cancel sekaligus membersihkan antrean spooler nyata jika ada
     */
    triggerSafetySimulation: function (safetyCode, printerName, portName) {
      var isOffline = (safetyCode === "PRINTER_OFFLINE");
      var fd = new FormData();
      fd.append("action", "purge_jobs");
      fd.append("printer", printerName);
      fetch("api_printers.php", { method: "POST", body: fd }).catch(function () {});

      this.showSafetyWarningModal({
        safety_triggered: true,
        safety_code: safetyCode,
        printer: printerName,
        port: portName,
        cancelled_jobs: 1,
        cancelled_documents: (currentConfig && currentConfig.title) ? currentConfig.title : "PCB Barcode Print",
        timestamp: new Date().toLocaleTimeString("id-ID"),
        reason: isOffline
          ? "Printer '" + printerName + "' terdeteksi MATI (Power OFF) atau tidak terdeteksi pada port " + portName + ". Job print yang diperintahkan telah di-cancel otomatis dari antrean spooler."
          : "Proses print pada '" + printerName + "' terhenti karena tombol Start / Pause di printer terpijit (Status: Printer Paused / Interrupted). Job print yang diperintahkan telah di-cancel otomatis dari antrean spooler."
      });
    },

    close: function () {
      if (overlayEl) {
        overlayEl.classList.remove("is-open");
        document.body.style.overflow = "";
      }
      var styleTag = document.getElementById("appPrintInjectedStyles");
      if (styleTag) {
        styleTag.textContent = "";
      }
    },

    setZoom: function (zoom) {
      currentZoom = Math.min(2.5, Math.max(0.4, Math.round(zoom * 100) / 100));
      this.updateZoomDisplay();
    },

    zoomIn: function () {
      this.setZoom(currentZoom + 0.15);
    },

    zoomOut: function () {
      this.setZoom(currentZoom - 0.15);
    },

    resetZoom: function () {
      this.setZoom(1.0);
    },

    updateZoomDisplay: function () {
      if (pagesContainerEl) {
        pagesContainerEl.style.transform = "scale(" + currentZoom + ")";
      }
      if (zoomValEl) {
        zoomValEl.textContent = Math.round(currentZoom * 100) + "%";
      }
    }
  };

  // Intercept window.open('', '_blank') seamlessly to render inside AppPrinter
  if (typeof window !== "undefined") {
    var originalWindowOpen = window.open;
    window.open = function (url, target, features) {
      if (!url || url === "" || url === "about:blank") {
        var buffer = "";
        return {
          closed: false,
          document: {
            write: function (s) { buffer += s; },
            writeln: function (s) { buffer += s + "\n"; },
            open: function () { buffer = ""; },
            close: function () {
              if (typeof global.showAppLoadingScreen === "function") {
                global.showAppLoadingScreen("Menyiapkan pratinjau cetak...");
              }
              setTimeout(function () {
                AppPrinter.openHtml(buffer);
              }, 35);
            }
          },
          focus: function () {},
          print: function () {},
          close: function () {}
        };
      }
      return originalWindowOpen ? originalWindowOpen.apply(window, arguments) : null;
    };
  }

  global.AppPrinter = AppPrinter;
})(window);
