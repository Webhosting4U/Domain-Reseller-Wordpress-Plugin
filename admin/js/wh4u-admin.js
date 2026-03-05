/**
 * WH4U Domains - Admin JavaScript
 *
 * @package WH4U_Domains
 */
(function($) {
    'use strict';

    var api = {
        baseUrl: wh4uAdmin.restUrl,
        nonce: wh4uAdmin.nonce,
        i18n: wh4uAdmin.i18n,

        request: function(method, endpoint, data) {
            var settings = {
                url: this.baseUrl + endpoint,
                method: method,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.nonce);
                },
                contentType: 'application/json',
                dataType: 'json'
            };

            if (data && method !== 'GET') {
                settings.data = JSON.stringify(data);
            } else if (data && method === 'GET') {
                settings.data = data;
            }

            return $.ajax(settings);
        }
    };

    /* ── Domain Search ─────────────────────────── */

    $(document).on('submit', '#wh4u-domain-search-form', function(e) {
        e.preventDefault();
        var searchTerm = $.trim($('#wh4u-search-term').val());
        if (!searchTerm) return;

        $('#wh4u-search-results').hide();
        $('#wh4u-search-error').hide();
        $('#wh4u-search-loading').show();
        $('#wh4u-search-btn').prop('disabled', true).text(api.i18n.searching);

        api.request('POST', 'domains/lookup', { searchTerm: searchTerm })
            .done(function(resp) {
                renderSearchResults(resp);
            })
            .fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || api.i18n.error;
                $('#wh4u-search-error p').text(msg);
                $('#wh4u-search-error').show();
            })
            .always(function() {
                $('#wh4u-search-loading').hide();
                $('#wh4u-search-btn').prop('disabled', false).text(
                    $('#wh4u-search-btn').data('original-text') || api.i18n.searchAvailability
                );
            });
    });

    $(document).on('click', '#wh4u-suggestions-btn', function() {
        var searchTerm = $.trim($('#wh4u-search-term').val());
        if (!searchTerm) return;

        $('#wh4u-search-results').hide();
        $('#wh4u-search-error').hide();
        $('#wh4u-search-loading').show();

        api.request('POST', 'domains/lookup/suggestions', { searchTerm: searchTerm })
            .done(function(resp) {
                renderSearchResults(resp);
            })
            .fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || api.i18n.error;
                $('#wh4u-search-error p').text(msg);
                $('#wh4u-search-error').show();
            })
            .always(function() {
                $('#wh4u-search-loading').hide();
            });
    });

    function renderSearchResults(data) {
        var $tbody = $('#wh4u-results-table tbody');
        $tbody.empty();

        if (!data || typeof data !== 'object') {
            $tbody.append('<tr><td colspan="3">' + escHtml(api.i18n.noResults) + '</td></tr>');
            $('#wh4u-search-results').show();
            return;
        }

        var results = Array.isArray(data) ? data : (data.result || data.results || [data]);

        if (!results.length) {
            $tbody.append('<tr><td colspan="3">' + escHtml(api.i18n.noResults) + '</td></tr>');
        } else {
            $.each(results, function(_, item) {
                var domain = item.domainName || item.domain || item.sld || '';
                var isAvailable = item.status === 'available' ||
                                  item.isAvailable === true ||
                                  item.available === true;

                var statusClass = isAvailable ? 'wh4u-available' : 'wh4u-unavailable';
                var statusText = isAvailable ? api.i18n.available : api.i18n.unavailable;
                var actionHtml = '';

                if (isAvailable) {
                    actionHtml = '<a href="' + adminPageUrl('wh4u-domains-register') +
                                 '&domain=' + encodeURIComponent(domain) +
                                 '" class="button button-small">' +
                                 escHtml(api.i18n.register) + '</a>';
                }

                $tbody.append(
                    '<tr>' +
                    '<td>' + escHtml(domain) + '</td>' +
                    '<td class="' + statusClass + '">' + statusText + '</td>' +
                    '<td>' + actionHtml + '</td>' +
                    '</tr>'
                );
            });
        }

        $('#wh4u-search-results').show();
    }

    /* ── Order Forms (Register / Transfer) ── */

    $(document).on('submit', '.wh4u-order-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var orderType = $form.find('input[name="order_type"]').val();
        var $btn = $form.find('.button-primary');
        var endpoint = 'orders/' + orderType;

        $btn.prop('disabled', true).text(api.i18n.processing);
        $('#wh4u-order-notice').hide();

        var formData = collectFormData($form);

        api.request('POST', endpoint, formData)
            .done(function(resp) {
                showNotice('success', resp.message || api.i18n.success);
            })
            .fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || api.i18n.error;
                showNotice('error', msg);
            })
            .always(function() {
                $btn.prop('disabled', false).text($btn.data('original-text') || $btn.text());
            });
    });

    function collectFormData($form) {
        var data = {};
        var nameservers = {};
        var contacts = {};
        var addons = {};

        $form.find(':input').each(function() {
            var name = this.name;
            if (!name || name.charAt(0) === '_') return;

            var val = $(this).val();

            if ($(this).is(':checkbox')) {
                val = this.checked ? 1 : 0;
            }

            if (name.indexOf('nameservers[') === 0) {
                var nsKey = name.replace('nameservers[', '').replace(']', '');
                if (val) nameservers[nsKey] = val;
            } else if (name.indexOf('contacts[') === 0) {
                var parts = name.replace('contacts[', '').replace(']', '').split('[');
                var cType = parts[0];
                var cField = parts[1] ? parts[1].replace(']', '') : '';
                if (!contacts[cType]) contacts[cType] = {};
                contacts[cType][cField] = val;
            } else if (name.indexOf('addons[') === 0) {
                var addonKey = name.replace('addons[', '').replace(']', '');
                addons[addonKey] = parseInt(val, 10) || 0;
            } else if (name === 'domain' || name === 'regperiod' || name === 'eppcode') {
                if (name === 'regperiod') {
                    data[name] = parseInt(val, 10);
                } else {
                    data[name] = val;
                }
            }
        });

        if (Object.keys(nameservers).length) data.nameservers = nameservers;
        if (Object.keys(contacts).length) data.contacts = contacts;
        if (Object.keys(addons).length) data.addons = addons;

        return data;
    }

    /* ── Copy Registrant Contact ─────────────── */

    $(document).on('change', '.wh4u-copy-registrant', function() {
        var target = $(this).data('target');
        var $targetTable = $('table[data-contact-type="' + target + '"]');
        var $regTable = $('table[data-contact-type="registrant"]');

        if (this.checked) {
            $regTable.find('.wh4u-contact-field').each(function() {
                var field = $(this).data('field');
                $targetTable.find('[data-field="' + field + '"]').val($(this).val());
            });
        }
    });

    /* ── Queue Retry ─────────────────────────── */

    $(document).on('click', '.wh4u-retry-queue', function() {
        var $btn = $(this);
        var queueId = $btn.data('queue-id');

        if (!confirm(api.i18n.confirm)) return;

        $btn.prop('disabled', true).text(api.i18n.retrying);

        api.request('POST', 'queue/' + queueId + '/retry')
            .done(function() {
                $btn.text(api.i18n.success);
                setTimeout(function() { location.reload(); }, 1000);
            })
            .fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || api.i18n.error;
                alert(msg);
                $btn.prop('disabled', false).text(api.i18n.retryNow);
            });
    });

    /* ── Process Now (notification-only orders) ── */

    $(document).on('click', '.wh4u-process-now', function() {
        var $btn = $(this);
        var orderId = $btn.data('order-id');

        if (!confirm(api.i18n.confirm)) return;

        $btn.prop('disabled', true).text(api.i18n.processing);

        api.request('POST', 'orders/process/' + orderId)
            .done(function() {
                $btn.text(api.i18n.success);
                setTimeout(function() { location.reload(); }, 1000);
            })
            .fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || api.i18n.error;
                alert(msg);
                $btn.prop('disabled', false).text(api.i18n.processNow);
            });
    });

    /* ── Public Order Approve / Reject ───────── */

    $(document).on('click', '.wh4u-public-order-action', function() {
        var $btn     = $(this);
        var orderId  = $btn.data('order-id');
        var action   = $btn.data('action');
        var newStatus = action === 'approve' ? 'wh4u-approved' : 'wh4u-rejected';
        var label     = action === 'approve' ? api.i18n.approve : api.i18n.reject;

        if (!confirm(api.i18n.confirmStatus.replace('%s', label))) return;

        var $row = $btn.closest('tr');
        $row.find('.wh4u-public-order-action').prop('disabled', true);
        $btn.text(api.i18n.processing);

        api.request('POST', 'orders/public/' + orderId + '/status', { status: newStatus })
            .done(function(resp) {
                $row.find('.wh4u-status')
                    .attr('class', 'wh4u-status wh4u-status-' + action)
                    .text(resp.label);

                var icon = action === 'approve'
                    ? '<span class="dashicons dashicons-yes-alt" style="color:#46b450;vertical-align:middle;"></span>'
                    : '<span class="dashicons dashicons-dismiss" style="color:#dc3232;vertical-align:middle;"></span>';
                $btn.closest('td').html(icon);

                if (resp.message) {
                    var noticeClass = 'notice-success';
                    if (resp.api_status === 'failed') {
                        noticeClass = 'notice-error';
                    } else if (resp.api_status === 'queued') {
                        noticeClass = 'notice-warning';
                    }
                    var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + escHtml(resp.message) + '</p></div>');
                    $row.closest('.wh4u-admin-wrap').find('h1').first().after($notice);
                }
            })
            .fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || api.i18n.error;
                alert(msg);
                $row.find('.wh4u-public-order-action').prop('disabled', false);
                $btn.text(label);
            });
    });

    /* ── Pricing Page ────────────────────────── */

    function loadPricing() {
        if (!$('#wh4u-pricing-table').length) return;

        $('#wh4u-pricing-loading').show();

        api.request('GET', 'tlds/pricing')
            .done(function(data) {
                var $tbody = $('#wh4u-pricing-table tbody');
                $tbody.empty();

                var items = Array.isArray(data) ? data : [];

                if (!items.length) {
                    $tbody.append('<tr><td colspan="3">' + escHtml(api.i18n.noPricing) + '</td></tr>');
                } else {
                    $.each(items, function(_, item) {
                        $tbody.append(
                            '<tr>' +
                            '<td>' + escHtml(item.tld || '') + '</td>' +
                            '<td>' + escHtml(item.register || '-') + '</td>' +
                            '<td>' + escHtml(item.transfer || '-') + '</td>' +
                            '</tr>'
                        );
                    });
                }

                $('#wh4u-pricing-container').show();
            })
            .fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || api.i18n.error;
                $('#wh4u-pricing-error p').text(msg);
                $('#wh4u-pricing-error').show();
            })
            .always(function() {
                $('#wh4u-pricing-loading').hide();
            });
    }

    /* ── Credits Page ────────────────────────── */

    function loadCredits() {
        var hasStandalone = $('#wh4u-credits-amount').length > 0;
        var hasDashboard  = $('#wh4u-dash-credits-amount').length > 0;

        if (!hasStandalone && !hasDashboard) return;

        if (hasStandalone) $('#wh4u-credits-loading').show();
        if (hasDashboard)  $('#wh4u-dash-credits-loading').show();

        api.request('GET', 'credits')
            .done(function(data) {
                var raw;
                if (typeof data === 'string' || typeof data === 'number') {
                    raw = data;
                } else {
                    raw = data.credits || data.balance || data.amount || data.credit || '--';
                }

                var num = parseFloat(raw);
                var display = isNaN(num) ? raw : '\u20AC' + num.toFixed(2);

                if (hasStandalone) {
                    $('#wh4u-credits-amount').text(display);
                    $('#wh4u-credits-container').show();
                }
                if (hasDashboard) {
                    $('#wh4u-dash-credits-amount').text(display).show();
                }
            })
            .fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || api.i18n.error;

                if (hasStandalone) {
                    $('#wh4u-credits-error p').text(msg);
                    $('#wh4u-credits-error').show();
                }
                if (hasDashboard) {
                    $('#wh4u-dash-credits-error').text(msg).show();
                }
            })
            .always(function() {
                if (hasStandalone) $('#wh4u-credits-loading').hide();
                if (hasDashboard)  $('#wh4u-dash-credits-loading').hide();
            });
    }

    /* ── Auto-fill domain from URL param ─────── */

    function autoFillDomain() {
        var params = new URLSearchParams(window.location.search);
        var domain = params.get('domain');
        if (domain) {
            $('#reg-domain, #transfer-domain').val(domain);
        }
    }

    /* ── Helpers ──────────────────────────────── */

    function showNotice(type, message) {
        var $notice = $('#wh4u-order-notice');
        $notice.attr('class', 'notice notice-' + type + ' is-dismissible')
               .html('<p>' + escHtml(message) + '</p>')
               .show();
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function adminPageUrl(page) {
        return window.location.pathname + '?page=' + page;
    }

    /* ── Init ─────────────────────────────────── */

    $(document).ready(function() {
        autoFillDomain();
        loadPricing();
        loadCredits();

        // Store original button text
        $('.wh4u-admin-wrap .button-primary, .wrap .button-primary').each(function() {
            $(this).data('original-text', $(this).text());
        });
    });

})(jQuery);
