<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('First Timer Registration');
$displayChurchName = ChurchMetaData::getChurchName() ?: 'Spirit Embassy Leeds';
require(SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php');

?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/family-register.min.css') ?>">
<style nonce="<?= SystemURLs::getCSPNonce() ?>">
    body.login-page,
    body.register-page {
        display: block !important;
        min-height: 100vh !important;
        height: auto !important;
        padding-top: 18px !important;
        overflow-y: auto !important;
    }

    .register-box.register-box-600 {
        width: 92%;
        max-width: 680px;
        margin-top: 24px;
        margin-bottom: 96px;
    }

    .register-box.register-box-600 .register-logo {
        margin-top: 0 !important;
        margin-bottom: 20px !important;
    }

    .ftv-church-name {
        display: block;
        margin: 0 auto;
        font-size: clamp(1.8rem, 3.2vw, 3rem);
        font-weight: 700;
        color: #2d3748;
        line-height: 1.2;
        max-width: 100%;
        text-decoration: none;
    }

    .ftv-form-title {
        display: block;
        margin: 14px auto 0;
        padding: 10px 24px;
        border-radius: 999px;
        background: linear-gradient(135deg, #1e88e5 0%, #5e35b1 100%);
        color: #fff;
        font-size: clamp(1rem, 2.3vw, 1.35rem);
        font-weight: 700;
        letter-spacing: 0.01em;
        box-shadow: 0 10px 20px rgba(30, 136, 229, 0.22);
    }

    @media (max-width: 767.98px) {
        .register-box.register-box-600 {
            width: calc(100% - 24px);
            margin-top: 16px;
            margin-bottom: calc(104px + env(safe-area-inset-bottom));
        }

        .register-box.register-box-600 .card-body {
            padding: 1rem;
        }

        .register-box.register-box-600 .btn.btn-block {
            min-height: 44px;
        }

        .ftv-church-name {
            font-size: clamp(1.45rem, 6.4vw, 2rem);
        }

        .ftv-form-title {
            margin-top: 12px;
            padding: 9px 18px;
            font-size: 1rem;
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
    <div class="register-logo text-center">
        <a href="<?= SystemURLs::getRootPath() ?>/" class="ftv-church-name"><?= $displayChurchName ?></a>
        <div class="ftv-form-title"><?= gettext('First Timer Form') ?></div>
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
                    <label for="postcode"><?= gettext('Postcode') ?> <span class="text-danger">*</span></label>
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
