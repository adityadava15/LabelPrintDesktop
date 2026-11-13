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
  var cachedPrinters = [];
  var selectedPrinter = null;
  var copies = 1;
  var layoutMode = "portrait";
  var pagesMode = "all"; // 'all' or 'custom'
  var pageRangeStr = "";
  var allSheets = [];
  var activeSheetIndices = [];

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
      '        <label class="app-print-field-label" for="appPrintPrinterSelect">Printer</label>',
      '        <div class="app-print-select-wrap">',
      '          <select id="appPrintPrinterSelect" class="app-print-select">',
      '            <option value="">Memuat printer Windows...</option>',
      '          </select>',
      '          <svg class="app-print-select-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>',
      '        </div>',
      '        <div id="appPrintPrinterStatusDesc" class="app-print-status-hint">Mencari status printer...</div>',
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
    // 1. Printer Select Dropdown
    var printerSelect = document.getElementById("appPrintPrinterSelect");
    if (printerSelect) {
      printerSelect.addEventListener("change", function () {
        var printerName = printerSelect.value;
        var found = cachedPrinters.find(function (p) { return p.name === printerName; });
        if (found) {
          AppPrinter.selectPrinter(found, true);
        } else if (printerName) {
          AppPrinter.selectPrinter({ name: printerName, is_ready: true }, true);
        }
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
        AppPrinter.print();
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
      } else if (e.key === "Enter" && !e.target.matches("input[type='text'], textarea")) {
        AppPrinter.print();
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

        var header = document.createElement("div");
        header.className = "app-print-sheet-card-header";
        header.innerHTML = "<span>Halaman " + (i + 1) + " dari " + allSheets.length + "</span><span>" + (currentConfig.badge || "") + "</span>";

        var paper = document.createElement("div");
        paper.className = "app-print-sheet-paper";
        paper.innerHTML = allSheets[i];

        card.appendChild(header);
        card.appendChild(paper);
        pagesContainerEl.appendChild(card);
      }

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
      this.resetZoom();
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

      AppPrinter.open({
        title: pageTitle,
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

    syncPrinterToWindows: function (printerName) {
      if (!printerName) return Promise.resolve(false);
      var fd = new FormData();
      fd.append("action", "set_default");
      fd.append("name", printerName);
      return fetch("api_printers.php", { method: "POST", body: fd })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          return !!(data && data.success);
        })
        .catch(function (err) {
          console.warn("Gagal sinkronisasi printer ke Windows:", err);
          return false;
        });
    },

    loadPrinters: function () {
      fetch("api_printers.php?action=list&t=" + Date.now())
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.success && Array.isArray(data.printers)) {
            cachedPrinters = data.printers;
            var select = document.getElementById("appPrintPrinterSelect");
            if (!select) return;

            select.innerHTML = "";
            var pref = localStorage.getItem("preferred_printer");
            var targetFound = null;

            cachedPrinters.forEach(function (p) {
              var opt = document.createElement("option");
              opt.value = p.name;
              var isReady = p.is_ready;
              opt.textContent = p.name + (isReady ? " (Ready)" : " [Offline]");
              if (pref && p.name === pref) {
                opt.selected = true;
                targetFound = p;
              } else if (!pref && p.is_default) {
                opt.selected = true;
                targetFound = p;
              }
              select.appendChild(opt);
            });

            if (!targetFound && cachedPrinters.length > 0) {
              targetFound = cachedPrinters[0];
            }

            if (targetFound) {
              AppPrinter.selectPrinter(targetFound, true);
            }
          }
        })
        .catch(function (err) {
          console.warn("Gagal memuat list printer:", err);
        });
    },

    selectPrinter: function (printer, syncToWindows) {
      if (!printer) return;
      selectedPrinter = printer;
      localStorage.setItem("preferred_printer", printer.name);

      var select = document.getElementById("appPrintPrinterSelect");
      if (select && select.value !== printer.name) {
        select.value = printer.name;
      }

      var statusDesc = document.getElementById("appPrintPrinterStatusDesc");
      if (statusDesc) {
        statusDesc.textContent = "Status: " + (printer.status || (printer.is_ready ? "Ready" : "Offline")) + " • Driver: " + (printer.driver || "Windows");
        if (printer.is_ready) {
          statusDesc.className = "app-print-status-hint";
        } else {
          statusDesc.className = "app-print-status-hint offline";
        }
      }

      if (syncToWindows) {
        AppPrinter.syncPrinterToWindows(printer.name);
      }
    },

    /**
     * Mengirim dokumen ke printer secara langsung tanpa menampilkan popup Windows Print
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

      if (btnLabel) btnLabel.textContent = "Menyiapkan printer...";

      var doPrint = function () {
        if (btnLabel) btnLabel.textContent = "Mencetak Langsung...";

        var frame = getHiddenIframe();
        var frameDoc = frame.contentWindow || frame.contentDocument;
        if (frameDoc.document) frameDoc = frameDoc.document;

        var pageSize = currentConfig.pageSize || "79.95mm 34.03mm";
        var customCss = currentConfig.css || "";

        frameDoc.open();
        frameDoc.write("<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>" + (currentConfig.title || "Cetak Barcode") + "</title>");
        frameDoc.write("<style>");
        frameDoc.write("@page { size: " + pageSize + " " + layoutMode + "; margin: 0 !important; }");
        frameDoc.write("* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }");
        frameDoc.write("html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; width: 100% !important; }");
        frameDoc.write(customCss);
        frameDoc.write("</style></head><body>");

        // Render only the active filtered sheets, multiplied by copies
        var papers = pagesContainerEl.querySelectorAll(".app-print-sheet-paper");
        for (var c = 0; c < copies; c++) {
          for (var i = 0; i < activeSheetIndices.length; i++) {
            var sheetIdx = activeSheetIndices[i];
            if (papers && papers[sheetIdx]) {
              frameDoc.write(papers[sheetIdx].innerHTML);
            } else if (allSheets[sheetIdx]) {
              frameDoc.write(allSheets[sheetIdx]);
            }
          }
        }

        frameDoc.write("</body></html>");
        frameDoc.close();

        setTimeout(function () {
          try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
          } catch (err) {
            console.error("Gagal menjalankan silent print:", err);
          } finally {
            setTimeout(function () {
              isPrinting = false;
              if (btnLabel) btnLabel.textContent = "Cetak Langsung";
              if (btn) {
                btn.disabled = false;
                btn.style.opacity = "1";
              }
            }, 1200);
          }
        }, 300);
      };

      if (targetPrinterName) {
        AppPrinter.syncPrinterToWindows(targetPrinterName).then(function () {
          doPrint();
        });
      } else {
        doPrint();
      }
    },

    close: function () {
      if (overlayEl) {
        overlayEl.classList.remove("is-open");
        document.body.style.overflow = "";
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
              setTimeout(function () {
                AppPrinter.openHtml(buffer);
              }, 20);
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
