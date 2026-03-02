<?php

require_once __DIR__ . '/Include/LoadConfigs.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
    RedirectUtils::securityRedirect('Admin');
}

$sPageTitle = gettext('First Timers');
require_once __DIR__ . '/Include/Header.php';

$firstTimerUrl = SystemURLs::getURL() . '/external/first-timer/';
$qrCode = new QrCode(data: $firstTimerUrl, size: 240);
$writer = new PngWriter();
$qrDataUri = $writer->write($qrCode)->getDataUri();

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <button class="btn btn-primary" id="add-first-timer">
            <i class="fa-solid fa-user-plus mr-1"></i><?= gettext('Add First Timer') ?>
        </button>
        <button class="btn btn-outline-secondary ml-2" id="share-first-timer">
            <i class="fa-solid fa-qrcode mr-1"></i><?= gettext('Share Form') ?>
        </button>
    </div>
    <button class="btn btn-outline-primary" id="email-first-timers">
        <i class="fa-solid fa-envelope mr-1"></i><?= gettext('Email First Timers') ?>
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-2">
                <label for="first-timer-filter-from" class="mb-1"><?= gettext('From') ?></label>
                <input type="date" id="first-timer-filter-from" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="first-timer-filter-to" class="mb-1"><?= gettext('To') ?></label>
                <input type="date" id="first-timer-filter-to" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="first-timer-filter-all-dates">
                    <label class="form-check-label" for="first-timer-filter-all-dates"><?= gettext('All Dates') ?></label>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="first-timer-filter-include-promoted">
                    <label class="form-check-label" for="first-timer-filter-include-promoted"><?= gettext('Include promoted first timers') ?></label>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end justify-content-end">
                <button class="btn btn-outline-primary btn-sm mr-2" id="first-timer-filter-today"><?= gettext('Today') ?></button>
                <button class="btn btn-primary btn-sm" id="first-timer-filter-apply"><?= gettext('Apply') ?></button>
            </div>
        </div>
        <div class="table-responsive">
            <table id="first-timers" class="table table-striped table-bordered data-table">
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="first-timer-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="first-timer-modal-title"><?= gettext('Add First Timer') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="first-timer-form">
                    <input type="hidden" id="first-timer-id">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="first-timer-first-name"><?= gettext('First Name') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first-timer-first-name" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="first-timer-last-name"><?= gettext('Last Name') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first-timer-last-name" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="first-timer-email"><?= gettext('Email') ?> <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="first-timer-email" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="first-timer-phone"><?= gettext('Phone') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first-timer-phone" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="first-timer-postcode"><?= gettext('Postal Code') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first-timer-postcode" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="first-timer-birth-date"><?= gettext('Date of Birth') ?></label>
                            <input type="date" class="form-control" id="first-timer-birth-date">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="first-timer-address"><?= gettext('Address') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first-timer-address" required>
                        </div>
                    </div>
                </form>
                <div id="first-timer-modal-message" class="alert d-none" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-primary" id="save-first-timer"><?= gettext('Save') ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="first-timer-share-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= gettext('Share First Timer Form') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted"><?= gettext('Share this link or scan the QR code') ?></p>
                <img src="<?= $qrDataUri ?>" alt="QR Code" class="mb-3" style="max-width: 240px;">
                <div class="input-group">
                    <input type="text" class="form-control" readonly value="<?= htmlspecialchars($firstTimerUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" id="copy-first-timer-link"><?= gettext('Copy') ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="first-timer-email-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= gettext('Email First Timers') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="first-timer-email-form">
                    <div class="form-group">
                        <label for="first-timer-email-subject"><?= gettext('Subject') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first-timer-email-subject" required>
                    </div>
                    <div class="form-group">
                        <label for="first-timer-email-body"><?= gettext('Message') ?> <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="first-timer-email-body" rows="6" required></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="first-timer-email-copy-to-sender" checked>
                            <label class="custom-control-label" for="first-timer-email-copy-to-sender"><?= gettext('Send a copy to my email address') ?></label>
                        </div>
                    </div>
                </form>
                <div id="first-timer-email-message" class="alert d-none" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-primary" id="send-first-timer-email"><?= gettext('Send Email') ?></button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    let firstTimerMap = {};
    const firstTimerFilters = {
        createdFrom: '',
        createdTo: '',
        allDates: false,
        includePromoted: false
    };

    function getTodayISODate() {
        return moment().format('YYYY-MM-DD');
    }

    function resetFiltersToToday() {
        const today = getTodayISODate();
        firstTimerFilters.createdFrom = today;
        firstTimerFilters.createdTo = today;
        firstTimerFilters.allDates = false;
        firstTimerFilters.includePromoted = false;
        $('#first-timer-filter-from').val(today);
        $('#first-timer-filter-to').val(today);
        $('#first-timer-filter-all-dates').prop('checked', false);
        $('#first-timer-filter-include-promoted').prop('checked', false);
    }

    function syncFiltersFromInputs() {
        firstTimerFilters.createdFrom = $('#first-timer-filter-from').val();
        firstTimerFilters.createdTo = $('#first-timer-filter-to').val();
        firstTimerFilters.allDates = $('#first-timer-filter-all-dates').is(':checked');
        firstTimerFilters.includePromoted = $('#first-timer-filter-include-promoted').is(':checked');
    }

    function showModalMessage(elementId, message, type) {
        const el = document.getElementById(elementId);
        el.textContent = message;
        el.classList.remove('d-none', 'alert-success', 'alert-danger');
        el.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
    }

    function clearModalMessage(elementId) {
        const el = document.getElementById(elementId);
        el.classList.add('d-none');
        el.textContent = '';
    }

    function resetFirstTimerForm() {
        document.getElementById('first-timer-id').value = '';
        document.getElementById('first-timer-first-name').value = '';
        document.getElementById('first-timer-last-name').value = '';
        document.getElementById('first-timer-email').value = '';
        document.getElementById('first-timer-phone').value = '';
        document.getElementById('first-timer-address').value = '';
        document.getElementById('first-timer-postcode').value = '';
        document.getElementById('first-timer-birth-date').value = '';
    }

    function openFirstTimerModal(title, data) {
        document.getElementById('first-timer-modal-title').textContent = title;
        if (data) {
            document.getElementById('first-timer-id').value = data.Id;
            document.getElementById('first-timer-first-name').value = data.FirstName || '';
            document.getElementById('first-timer-last-name').value = data.LastName || '';
            document.getElementById('first-timer-email').value = data.Email || '';
            document.getElementById('first-timer-phone').value = data.Phone || '';
            document.getElementById('first-timer-address').value = data.Address || '';
            document.getElementById('first-timer-postcode').value = data.Postcode || '';
            document.getElementById('first-timer-birth-date').value = data.BirthDate ? data.BirthDate.split(' ')[0] : '';
        } else {
            resetFirstTimerForm();
        }
        clearModalMessage('first-timer-modal-message');
        $('#first-timer-modal').modal('show');
    }

    function getFirstTimerPayload() {
        return {
            firstName: document.getElementById('first-timer-first-name').value.trim(),
            lastName: document.getElementById('first-timer-last-name').value.trim(),
            email: document.getElementById('first-timer-email').value.trim(),
            phone: document.getElementById('first-timer-phone').value.trim(),
            address: document.getElementById('first-timer-address').value.trim(),
            postcode: document.getElementById('first-timer-postcode').value.trim(),
            birthDate: document.getElementById('first-timer-birth-date').value
        };
    }

    function initializeFirstTimers() {
        resetFiltersToToday();
        const dataTableConfig = {
            ajax: {
                url: window.CRM.root + '/api/first-timers',
                data: function (d) {
                    syncFiltersFromInputs();
                    d.includePromoted = firstTimerFilters.includePromoted ? 1 : 0;
                    d.allDates = firstTimerFilters.allDates ? 1 : 0;
                    if (!firstTimerFilters.allDates) {
                        if (firstTimerFilters.createdFrom) {
                            d.createdFrom = firstTimerFilters.createdFrom;
                        }
                        if (firstTimerFilters.createdTo) {
                            d.createdTo = firstTimerFilters.createdTo;
                        }
                    }
                },
                dataSrc: function (json) {
                    firstTimerMap = {};
                    json.firstTimers.forEach((item) => {
                        firstTimerMap[item.Id] = item;
                    });
                    return json.firstTimers;
                }
            },
            autoWidth: false,
            columns: [
                {
                    title: i18next.t('Name'),
                    data: null,
                    render: function (data) {
                        return `${data.FirstName || ''} ${data.LastName || ''}`.trim();
                    }
                },
                {
                    title: i18next.t('Email'),
                    data: 'Email',
                    render: function (data) {
                        if (!data) {
                            return '<span class="text-muted">' + i18next.t('No email') + '</span>';
                        }
                        return '<a href="mailto:' + data + '">' + data + '</a>';
                    }
                },
                {
                    title: i18next.t('Phone'),
                    data: 'Phone',
                    defaultContent: ''
                },
                {
                    title: i18next.t('Created'),
                    data: 'CreatedAt',
                    render: function (data) {
                        return data ? moment(data).format('MM-DD-YY') : '';
                    }
                },
                {
                    title: i18next.t('Promoted'),
                    data: null,
                    render: function (data) {
                        if (data.PromotedPersonId) {
                            return '<a href="' + window.CRM.root + '/PersonView.php?PersonID=' + data.PromotedPersonId + '">' + i18next.t('Yes') + '</a>';
                        }
                        return '<span class="text-muted">' + i18next.t('No') + '</span>';
                    }
                },
                {
                    title: i18next.t('Actions'),
                    data: null,
                    orderable: false,
                    render: function (data) {
                        const promoteDisabled = data.PromotedPersonId ? 'disabled' : '';
                        return `
                            <button class="btn btn-sm btn-outline-primary mr-1 edit-first-timer" data-id="${data.Id}"><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-sm btn-outline-success mr-1 promote-first-timer" data-id="${data.Id}" ${promoteDisabled}><i class="fa-solid fa-user-check"></i></button>
                            <button class="btn btn-sm btn-outline-danger delete-first-timer" data-id="${data.Id}"><i class="fa-solid fa-trash"></i></button>
                        `;
                    }
                }
            ],
            order: [[3, 'desc']]
        };

        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        const table = $('#first-timers').DataTable(dataTableConfig);

        $('#first-timer-filter-all-dates').on('change', function () {
            const disabled = $(this).is(':checked');
            $('#first-timer-filter-from, #first-timer-filter-to').prop('disabled', disabled);
        });

        $('#first-timer-filter-apply').on('click', function () {
            syncFiltersFromInputs();
            table.ajax.reload(null, false);
        });

        $('#first-timer-filter-today').on('click', function () {
            resetFiltersToToday();
            $('#first-timer-filter-from, #first-timer-filter-to').prop('disabled', false);
            table.ajax.reload(null, false);
        });

        $('#add-first-timer').on('click', function () {
            openFirstTimerModal(i18next.t('Add First Timer'));
        });

        $('#share-first-timer').on('click', function () {
            $('#first-timer-share-modal').modal('show');
        });

        $('#email-first-timers').on('click', function () {
            clearModalMessage('first-timer-email-message');
            $('#first-timer-email-form')[0].reset();
            $('#first-timer-email-modal').modal('show');
        });

        $('#copy-first-timer-link').on('click', function () {
            const input = $(this).closest('.input-group').find('input')[0];
            input.select();
            document.execCommand('copy');
            window.CRM.notify(i18next.t('Link copied to clipboard'), {type: 'success', delay: 2000});
        });

        $('#first-timers').on('click', '.edit-first-timer', function () {
            const id = $(this).data('id');
            openFirstTimerModal(i18next.t('Edit First Timer'), firstTimerMap[id]);
        });

        $('#first-timers').on('click', '.promote-first-timer', function () {
            const id = $(this).data('id');
            if (!confirm(i18next.t('Promote this first timer to a member?'))) {
                return;
            }
            fetch(`${window.CRM.root}/api/first-timers/${id}/promote`, {
                method: 'POST'
            }).then((res) => res.json().then((data) => ({ok: res.ok, data})))
            .then(({ok, data}) => {
                if (!ok) {
                    window.CRM.notify(data.error || i18next.t('Unable to promote'), {type: 'danger'});
                    return;
                }
                window.CRM.notify(i18next.t('First timer promoted'), {type: 'success'});
                table.ajax.reload(null, false);
            });
        });

        $('#first-timers').on('click', '.delete-first-timer', function () {
            const id = $(this).data('id');
            if (!confirm(i18next.t('Delete this first timer?'))) {
                return;
            }
            fetch(`${window.CRM.root}/api/first-timers/${id}`, {
                method: 'DELETE'
            }).then((res) => res.json().then((data) => ({ok: res.ok, data})))
            .then(({ok, data}) => {
                if (!ok) {
                    window.CRM.notify(data.error || i18next.t('Unable to delete'), {type: 'danger'});
                    return;
                }
                window.CRM.notify(i18next.t('First timer deleted'), {type: 'success'});
                table.ajax.reload(null, false);
            });
        });

        $('#save-first-timer').on('click', function () {
            const payload = getFirstTimerPayload();
            if (!payload.firstName || !payload.lastName || !payload.email || !payload.phone || !payload.address || !payload.postcode) {
                showModalMessage('first-timer-modal-message', i18next.t('Please fill the required fields'), 'error');
                return;
            }

            const id = document.getElementById('first-timer-id').value;
            const url = id ? `${window.CRM.root}/api/first-timers/${id}` : `${window.CRM.root}/api/first-timers`;
            const method = id ? 'PATCH' : 'POST';

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            }).then((res) => res.json().then((data) => ({ok: res.ok, data})))
            .then(({ok, data}) => {
                if (!ok) {
                    showModalMessage('first-timer-modal-message', data.error || i18next.t('Unable to save'), 'error');
                    return;
                }
                $('#first-timer-modal').modal('hide');
                table.ajax.reload(null, false);
                window.CRM.notify(i18next.t('Saved'), {type: 'success'});
            });
        });

        $('#send-first-timer-email').on('click', function () {
            const subject = document.getElementById('first-timer-email-subject').value.trim();
            const body = document.getElementById('first-timer-email-body').value.trim();
            const copyToSender = document.getElementById('first-timer-email-copy-to-sender').checked;
            syncFiltersFromInputs();

            if (!subject || !body) {
                showModalMessage('first-timer-email-message', i18next.t('Subject and message are required'), 'error');
                return;
            }

            fetch(`${window.CRM.root}/api/first-timers/email`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subject,
                    body,
                    copyToSender: copyToSender ? 1 : 0,
                    includePromoted: firstTimerFilters.includePromoted ? 1 : 0,
                    allDates: firstTimerFilters.allDates ? 1 : 0,
                    createdFrom: firstTimerFilters.createdFrom || null,
                    createdTo: firstTimerFilters.createdTo || null
                })
            }).then((res) => res.json().then((data) => ({ok: res.ok, data})))
            .then(({ok, data}) => {
                if (!ok) {
                    showModalMessage('first-timer-email-message', data.error || i18next.t('Unable to send email'), 'error');
                    return;
                }
                const recipients = Array.isArray(data.recipients) ? data.recipients : [];
                const recipientsLabel = recipients.length > 0
                    ? ` ${i18next.t('Recipients')}: ${recipients.slice(0, 5).join(', ')}${recipients.length > 5 ? ' ...' : ''}`
                    : '';
                const copyInfo = copyToSender
                    ? (data.copySent
                        ? ` ${i18next.t('Copy sent to')}: ${data.copyRecipient || i18next.t('your email')}.`
                        : ` ${i18next.t('Sender copy failed')}: ${(data.copyError || i18next.t('Unable to send email'))}.`)
                    : '';
                if ((data.failed || 0) > 0) {
                    const details = data.errors && data.errors.length ? ` ${data.errors[0]}` : '';
                    showModalMessage('first-timer-email-message', i18next.t('Some emails were accepted by the mail server, but some failed') + ` (${data.sent}/${data.total}).${details}${recipientsLabel}${copyInfo}`, 'error');
                    return;
                }
                showModalMessage('first-timer-email-message', i18next.t('Emails accepted by the mail server') + ` (${data.sent}/${data.total}).${recipientsLabel}${copyInfo}`, 'success');
            }).catch(() => {
                showModalMessage('first-timer-email-message', i18next.t('Unable to send email'), 'error');
            });
        });
    }

    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeFirstTimers);
    });
</script>

<?php
require_once __DIR__ . '/Include/Footer.php';
?>
