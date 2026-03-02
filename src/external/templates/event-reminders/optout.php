<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

$sPageTitle = gettext('Event Reminder Preferences');
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>
<div class="register-logo text-center mb-4" style="margin-top: 72px;">
    <a href="<?= SystemURLs::getRootPath() ?>/" class="h2"><?= ChurchMetaData::getChurchName() ?></a>
</div>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-body">
        <h3 class="card-title"><?= $title ?></h3>
        <p class="card-text"><?= $message ?></p>
    </div>
</div>

<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
