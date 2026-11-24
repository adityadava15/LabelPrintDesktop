/**
 * Studio UI JavaScript
 * Manages Header Printer Status, Keyboard Shortcuts (Enter, F12),
 * and Studio Interactions.
 */
(function (global) {
  "use strict";

  var LOADING_STORAGE_KEY = "app_pending_swirl_loading";

  function showAppLoadingScreen(label, persistType) {
    if (persistType) {
      try {
        sessionStorage.setItem(LOADING_STORAGE_KEY, String(persistType));
      } catch (e) {}
    }
    var el = document.getElementById("appGlobalLoadingScreen");
    if (el) {
      if (label) {
        el.setAttribute("aria-label", String(label));
      }
      el.classList.remove("is-hidden");
    }
  }

  function hideAppLoadingScreen() {
    try {
      sessionStorage.removeItem(LOADING_STORAGE_KEY);
    } catch (e) {}
    var el = document.getElementById("appGlobalLoadingScreen");
    if (el) {
      el.classList.add("is-hidden");
    }
  }

  function isSamePrinter(a, b) {
    if (!a || !b) return false;
    return String(a).trim().toLowerCase() === String(b).trim().toLowerCase();
  }

  function updateStudioPrinterStatus() {
    fetch("api_printers.php?action=list&t=" + Date.now())
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.success && Array.isArray(data.printers)) {
          var serverAppDef = (data.app_default_printer || "").trim();
          var localAppDef = "";
          var pref = "";
          try {
            localAppDef = (localStorage.getItem("app_default_printer") || "").trim();
            pref = (localStorage.getItem("preferred_printer") || "").trim();
          } catch (e) {}

          var appDef = serverAppDef || localAppDef;
          if (serverAppDef) {
            try {
              localStorage.setItem("app_default_printer", serverAppDef);
              localStorage.setItem("preferred_printer", serverAppDef);
            } catch (e) {}
          } else if (localAppDef) {
            var fd = new FormData();
            fd.append("action", "set_app_default");
            fd.append("name", localAppDef);
            fetch("api_printers.php", { method: "POST", body: fd }).catch(function () {});
          }

          var activeP = null;
          if (appDef) {
            activeP = data.printers.find(function (p) { return isSamePrinter(p.name, appDef); });
          }
          if (!activeP && pref) {
            activeP = data.printers.find(function (p) { return isSamePrinter(p.name, pref); });
          }
          if (!activeP) {
            activeP = data.printers.find(function (p) { return p.is_default || p.is_windows_default; }) || data.printers[0];
          }
          if (activeP) {
            currentActivePrinterName = activeP.name;
            var isReady = activeP.is_ready;
            var port = activeP.port || "USB001";
            var statusStr = activeP.status || (isReady ? "Ready" : "Offline");
            var isAppDefaultActive = !!(appDef && isSamePrinter(activeP.name, appDef));
            var defaultBadge = isAppDefaultActive ? " ★ Default" : "";
            var txt = "Printer: " + activeP.name + " (" + statusStr + ")" + defaultBadge + " | Port: " + port;
            var fullTxt = "● Printer: " + activeP.name + defaultBadge + " | Status: " + statusStr + " | Port: " + port;

            var el1 = document.getElementById("headerPrinterStatusText");
            var el2 = document.getElementById("headerPrinterSubText");
            var dot1 = document.getElementById("headerPrinterDot");
            var dot2 = document.getElementById("headerPrinterSubDot");

            if (el1) el1.textContent = txt;
            if (el2) el2.textContent = fullTxt;
            if (dot1) dot1.className = "studio-printer-dot" + (isReady ? "" : " offline");
            if (dot2) dot2.className = "studio-printer-dot" + (isReady ? "" : " offline");
          }
        }
      })
      .catch(function (e) {
        console.warn("Studio printer status fetch error:", e);
      });
  }

  function initStudioEvents() {
    updateStudioPrinterStatus();

    // 1. Click on Header Printer Status opens Printer Settings Modal
    var box = document.getElementById("headerPrinterStatusBox");
    if (box) {
      box.addEventListener("click", function () {
        openPrinterStudioSettings();
      });
    }

    var subBar = document.querySelector(".studio-subbar");
    if (subBar) {
      subBar.addEventListener("click", function () {
        openPrinterStudioSettings();
      });
    }

    // 2. Button Pengaturan Printer [F12]
    var btnSettings = document.getElementById("btnStudioPrinterSettings");
    if (btnSettings) {
      btnSettings.addEventListener("click", function () {
        openPrinterStudioSettings();
      });
    }

    // 3. Button CETAK SEKARANG [Enter]
    var btnPrintNow = document.getElementById("btnStudioPrintNow");
    if (btnPrintNow) {
      btnPrintNow.addEventListener("click", function () {
        var form = document.getElementById("printForm");
        var legacyPrintBtn = document.getElementById("printBtn");
        if (legacyPrintBtn) {
          legacyPrintBtn.click();
        } else if (form) {
          form.submit();
        }
      });
    }

    // 4. Button Batal
    var btnCancel = document.getElementById("btnStudioCancel");
    if (btnCancel) {
      btnCancel.addEventListener("click", function () {
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete("batch_id");
        window.location.href = currentUrl.toString();
      });
    }

    // 5. Keyboard shortcuts: Enter to print, F12 for printer settings
    window.addEventListener("keydown", function (e) {
      // Don't intercept if an input or select is focused
      if (e.target.matches("input, textarea, select")) {
        return;
      }
      if (e.key === "Enter") {
        if (btnPrintNow && !btnPrintNow.disabled) {
          e.preventDefault();
          btnPrintNow.click();
        }
      } else if (e.key === "F12") {
        e.preventDefault();
        openPrinterStudioSettings();
      }
    });

    // 6. Theme Toggle Logic
    var themeBtn = document.getElementById("themeToggleBtn");
    var themeIcon = document.getElementById("themeToggleIcon");
    var themeText = document.getElementById("themeToggleText");
    var storageKey = "uiThemeMode";

    function applyStudioTheme(theme) {
      var isLight = theme === "light";
      document.body.classList.toggle("light-theme", isLight);
      if (themeIcon) themeIcon.innerHTML = isLight ? "&#127769;" : "&#9728;&#65039;";
      if (themeText) themeText.textContent = isLight ? "Dark Mode" : "Light Mode";
      if (themeBtn) themeBtn.setAttribute("aria-pressed", isLight ? "true" : "false");
    }

    var currentTheme = "dark";
    try {
      var saved = localStorage.getItem(storageKey);
      if (saved === "light" || saved === "dark") {
        currentTheme = saved;
      }
    } catch (e) {}

    applyStudioTheme(currentTheme);

    if (themeBtn) {
      themeBtn.addEventListener("click", function () {
        currentTheme = currentTheme === "light" ? "dark" : "light";
        applyStudioTheme(currentTheme);
        try {
          localStorage.setItem(storageKey, currentTheme);
        } catch (e) {}
      });
    }

    // 7. Work Order and Batch dropdown change listeners
    var woSelect = document.getElementById("studioWoSelect");
    if (woSelect) {
      woSelect.addEventListener("change", function () {
        var val = this.value;
        var currentUrl = new URL(window.location.href);
        if (val) {
          currentUrl.searchParams.set("wo", val);
          currentUrl.searchParams.delete("batch_id");
        } else {
          currentUrl.searchParams.delete("wo");
          currentUrl.searchParams.delete("batch_id");
        }
        window.location.href = currentUrl.toString();
      });
    }

    var batchSelect = document.getElementById("studioBatchSelect");
    if (batchSelect) {
      batchSelect.addEventListener("change", function () {
        var val = this.value;
        showAppLoadingScreen("Memuat data batch...", "batch");
        var currentUrl = new URL(window.location.href);
        if (val) {
          currentUrl.searchParams.set("batch_id", val);
        } else {
          currentUrl.searchParams.delete("batch_id");
        }
        window.location.href = currentUrl.toString();
      });
    }

    var npBatchSelect = document.querySelector('select[name="batch_id_np"]');
    if (npBatchSelect) {
      npBatchSelect.addEventListener("change", function () {
        showAppLoadingScreen("Memuat data batch nameplate...", "batch");
      });
    }
  }

  function openPrinterStudioSettings() {
    if (window.AppPrinter && typeof window.AppPrinter.open === "function") {
      window.AppPrinter.open({
        title: "Pengaturan Printer Studio",
        badge: "Standar",
        totalLabels: 1,
        totalPages: 1
      });
    }
  }

  global.showAppLoadingScreen = showAppLoadingScreen;
  global.hideAppLoadingScreen = hideAppLoadingScreen;
  global.updateStudioPrinterStatus = updateStudioPrinterStatus;
  global.openPrinterStudioSettings = openPrinterStudioSettings;

  window.addEventListener("load", function () {
    var el = document.getElementById("appGlobalLoadingScreen");
    if (!el) return;
    var isPendingPrint = el.getAttribute("data-pending-print") === "1";
    if (isPendingPrint) {
      setTimeout(function () {
        var modalOverlay = document.getElementById("appPrintModalOverlay");
        if (!modalOverlay || !modalOverlay.classList.contains("is-open")) {
          hideAppLoadingScreen();
        }
      }, 4000);
      return;
    }
    setTimeout(function () {
      requestAnimationFrame(hideAppLoadingScreen);
    }, 80);
  });

  window.addEventListener("pageshow", function (event) {
    if (event.persisted) {
      hideAppLoadingScreen();
    }
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStudioEvents);
  } else {
    initStudioEvents();
  }
})(window);
