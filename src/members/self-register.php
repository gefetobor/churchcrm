<?php

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/Functions.php';

$sPageTitle = gettext('Self Registrations');
require_once __DIR__ . '/../Include/Header.php';

use ChurchCRM\dto\SystemURLs;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$selfRegisterUrl = SystemURLs::getURL() . '/external/register/';
$qrCode = new QrCode(data: $selfRegisterUrl, size: 240);
$writer = new PngWriter();
$qrDataUri = $writer->write($qrCode)->getDataUri();

?>

<div class="d-flex justify-content-end align-items-center mb-3">
    <button class="btn btn-outline-secondary" id="share-self-register">
        <i class="fa-solid fa-qrcode mr-1"></i><?= gettext('Share Form') ?>
    </button>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Families") ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table id="families" class="table table-striped table-bordered data-table">
                    <tbody></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("People") ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table id="people" class="table table-striped table-bordered data-table">
                    <tbody></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="self-register-share-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= gettext('Share Self Register Form') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted"><?= gettext('Share this link or scan the QR code') ?></p>
                <img src="<?= $qrDataUri ?>" alt="QR Code" class="mb-3" style="max-width: 240px;">
                <div class="input-group">
                    <input type="text" class="form-control" readonly value="<?= htmlspecialchars($selfRegisterUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" id="copy-self-register-link"><?= gettext('Copy') ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializeSelfRegister() {

        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/families/self-register",
                dataSrc: 'families'
            },
            autoWidth: false,
            columns: [
                {
                    width: '20%',
                    title: i18next.t('Family Id'),
                    data: 'Id',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href=' + window.CRM.root + '/v2/family/' + data + '>' + data + '</a>';
                    }
                },
                {
                    width: '50%',
                    title: i18next.t('Family'),
                    data: 'FamilyString',
                    searchable: true
                },
                {
                    width: '30%',
                    title: i18next.t('Date'),
                    data: 'DateEntered',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return moment(data).format("MM-DD-YY");
                    }
                }
            ],
            order: [[2, "desc"]]
        }

        $.extend(dataTableConfig, window.CRM.plugin.dataTable);

        $("#families").DataTable(dataTableConfig);

        dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/persons/self-register",
                dataSrc: 'people'
            },
            autoWidth: false,
            columns: [
                {
                    width: '15%',
                    title: i18next.t('Id'),
                    data: 'Id',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href=' + window.CRM.root + '/PersonView.php?PersonID=' + data + '>' + data + '</a>';
                    }
                },
                {
                    width: '30%',
                    title: i18next.t('First Name'),
                    data: 'FirstName',
                    searchable: true
                },
                {
                    width: '30%',
                    title: i18next.t('Last Name'),
                    data: 'LastName',
                    searchable: true
                },
                {
                    width: '25%',
                    title: i18next.t('Date'),
                    data: 'DateEntered',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return moment(data).format("MM-DD-YY");
                    }
                }
            ],
            order: [[3, "desc"]]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#people").DataTable(dataTableConfig);

        $('#share-self-register').on('click', function () {
            $('#self-register-share-modal').modal('show');
        });

        $('#copy-self-register-link').on('click', function () {
            const input = $(this).closest('.input-group').find('input')[0];
            input.select();
            document.execCommand('copy');
            window.CRM.notify(i18next.t('Link copied to clipboard'), {type: 'success', delay: 2000});
        });
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeSelfRegister);
    });
</script>
<?php
require_once __DIR__ . '/../Include/Footer.php';
