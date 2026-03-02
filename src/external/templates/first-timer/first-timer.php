<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('First Timer Registration');
require(SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php');

?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/family-register.min.css') ?>">
<style nonce="<?= SystemURLs::getCSPNonce() ?>">
    .register-box.register-box-600 {
        width: 90%;
        max-width: 600px;
        margin-top: 24px;
        margin-bottom: 96px;
    }

    .register-box.register-box-600 .register-logo {
        margin-top: 12px !important;
    }

    @media (max-width: 767.98px) {
        .register-box.register-box-600 {
            width: calc(100% - 24px);
            margin-top: 12px;
            margin-bottom: calc(104px + env(safe-area-inset-bottom));
        }

        .register-box.register-box-600 .card-body {
            padding: 1rem;
        }

        .register-box.register-box-600 .btn.btn-block {
            min-height: 44px;
        }
    }
</style>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = {
        root: "<?= SystemURLs::getRootPath() ?>",
        churchWebSite: "<?= SystemURLs::getRootPath() ?>/",
        phoneFormats: {
            home: "<?= SystemConfig::getValue('sPhoneFormat') ?>",
            cell: "<?= SystemConfig::getValue('sPhoneFormatCell') ?>",
            work: "<?= SystemConfig::getValue('sPhoneFormatWithExt') ?>"
        }
    };
</script>

<div class="register-box register-box-600">
    <div class="register-logo text-center mb-4">
        <a href="<?= SystemURLs::getRootPath() ?>/" class="h2"><?= ChurchMetaData::getChurchName() ?></a>
        <p class="text-muted mt-2"><?= gettext('First Time Visitor') ?></p>
    </div>

    <div class="card registration-card">
        <div class="card-body">
            <form id="first-timer-form" novalidate>
                <div class="form-group">
                    <label for="firstName"><?= gettext('First Name') ?> <span class="text-danger">*</span></label>
                    <input id="firstName" name="firstName" type="text" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="lastName"><?= gettext('Last Name') ?> <span class="text-danger">*</span></label>
                    <input id="lastName" name="lastName" type="text" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email"><?= gettext('Email') ?> <span class="text-danger">*</span></label>
                    <input id="email" name="email" type="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="phone"><?= gettext('Phone Number') ?> <span class="text-danger">*</span></label>
                    <input id="phone" name="phone" type="text" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="postcode"><?= gettext('Postal Code') ?> <span class="text-danger">*</span></label>
                    <input id="postcode" name="postcode" type="text" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="birthDate"><?= gettext('Date of Birth') ?></label>
                    <input id="birthDate" name="birthDate" type="date" class="form-control">
                </div>
                <div class="form-group">
                    <label for="address"><?= gettext('Address') ?> <span class="text-danger">*</span></label>
                    <input id="address" name="address" type="text" class="form-control" required>
                </div>
                <div id="form-message" class="alert d-none" role="alert"></div>
                <button type="submit" class="btn btn-primary btn-block">
                    <?= gettext('Submit') ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    const form = document.getElementById('first-timer-form');
    const message = document.getElementById('form-message');

    function showMessage(text, type) {
        message.textContent = text;
        message.classList.remove('d-none', 'alert-success', 'alert-danger');
        message.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = {
            firstName: document.getElementById('firstName').value.trim(),
            lastName: document.getElementById('lastName').value.trim(),
            email: document.getElementById('email').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            postcode: document.getElementById('postcode').value.trim(),
            birthDate: document.getElementById('birthDate').value,
            address: document.getElementById('address').value.trim()
        };

        if (!payload.firstName || !payload.lastName || !payload.email || !payload.phone || !payload.postcode || !payload.address) {
            showMessage("<?= gettext('Please fill the required fields.') ?>", 'error');
            return;
        }

        try {
            const response = await fetch(`${window.CRM.root}/api/public/first-timer`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (!response.ok) {
                showMessage(data.error || "<?= gettext('Unable to submit the form.') ?>", 'error');
                return;
            }

            form.reset();
            showMessage("<?= gettext('Thank you for registering. We will be in touch soon.') ?>", 'success');
        } catch (err) {
            showMessage("<?= gettext('Unable to submit the form right now.') ?>", 'error');
        }
    });
</script>
<?php
require(SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php');
?>
