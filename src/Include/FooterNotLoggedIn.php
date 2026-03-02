<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

?>
    <style nonce="<?= SystemURLs::getCSPNonce() ?>">
      body {
        padding-bottom: calc(52px + env(safe-area-inset-bottom));
      }
      .external-fixed-footer {
        background-color: #fff;
        padding-top: 5px;
        padding-bottom: 5px;
        position: fixed;
        bottom: 0;
        width: 100%;
        z-index: 1020;
      }
    </style>
    <div class="text-center external-fixed-footer">
      <strong><?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?> <a href="https://churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved')?>.
    </div>

  <script src="<?= SystemURLs::assetVersioned('/skin/external/select2/select2.full.min.js') ?>"></script>

  <!-- Bootstrap 3.3.5 -->
  <script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap/js/bootstrap.min.js') ?>"></script>

  <!-- AdminLTE App -->
  <script src="<?= SystemURLs::assetVersioned('/skin/external/adminlte/adminlte.min.js') ?>"></script>

  <!-- InputMask -->
  <script src="<?= SystemURLs::assetVersioned('/skin/external/inputmask/jquery.inputmask.min.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/external/inputmask/inputmask.binding.js') ?>"></script>

  <script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap-datepicker/bootstrap-datepicker.min.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/external/bootbox/bootbox.min.js') ?>"></script>

  <script src="<?= SystemURLs::assetVersioned('/skin/external/i18next/i18next.min.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/external/just-validate/just-validate.production.min.js') ?>"></script>

  <script src="<?= SystemURLs::assetVersioned('/skin/v2/locale-loader.min.js') ?>"></script>
  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
    // Load locale files dynamically
    (function() {
        const localeConfig = <?= json_encode(Bootstrapper::getCurrentLocale()->getLocaleConfigArray()) ?>;
        if (window.CRM && window.CRM.loadLocaleFiles) {
            window.CRM.loadLocaleFiles(localeConfig);
        }
    })();
  </script>
  <?php

    //If this is a first-run setup, do not include google analytics code.
    if ($_SERVER['SCRIPT_NAME'] != '/setup/index.php') {
        $analyticsPath = __DIR__ . '/analyticstracking.php';
        if (is_file($analyticsPath)) {
            include_once $analyticsPath;
        }
    }
    ?>
</body>
</html>
