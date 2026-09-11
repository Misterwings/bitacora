$(function () {
    $('.bit-initial-hidden').hide().removeClass('bit-initial-hidden');

    $('.bit-auto-submit').on('change', function () {
        this.form.submit();
    });

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function fieldAllowsSede($el, sede) {
        var sedes = String($el.attr('data-sede') || '').split(',').map(function (item) {
            return $.trim(item.toUpperCase());
        }).filter(Boolean);
        return sedes.length === 0 || sedes.indexOf(String(sede || '').toUpperCase()) !== -1;
    }

    function clearSedeControl($el) {
        if ($el.is(':radio, :checkbox')) {
            $el.prop('checked', false);
        } else if ($el.hasClass('select2-hidden-accessible')) {
            $el.val(null).trigger('change');
        } else if ($el.is('select')) {
            $el.prop('selectedIndex', 0);
        } else {
            $el.val('');
        }
    }

    function refreshConditionalDetails() {
        $('[data-toggle-detail]').trigger('change');
    }

    function refreshSedeFields() {
        var sede = $('#sede, #idSede').first().val() || '';
        $('[data-sede]').each(function () {
            var $el = $(this);
            var allowed = fieldAllowsSede($el, sede);
            var $container = $el.closest('.form-group, [class*="col-"], fieldset, .container, .row').first();
            if (!$container.length) {
                $container = $el;
            }

            $container.toggle(allowed);
            if ($el.is('input, select, textarea')) {
                if (allowed) {
                    $el.prop('disabled', false);
                    if ($el.data('was-required') === true) {
                        $el.prop('required', true);
                    }
                } else {
                    if ($el.prop('required')) {
                        $el.data('was-required', true);
                    }
                    $el.prop('required', false).prop('disabled', true);

                    clearSedeControl($el);
                }
            }
        });
        refreshConditionalDetails();
        bitUpdateSedeNotice();
        bitUpdateProgress();
    }

    function clearSedeFields(sede) {
        $('[data-sede]').each(function () {
            var $el = $(this);
            if (!fieldAllowsSede($el, sede)) return;

            var $container = $el.closest('.form-group, [class*="col-"], fieldset, .container, .row').first();
            if (!$container.length) {
                $container = $el;
            }

            $container.hide();
            if ($el.is('input, select, textarea')) {
                $el.prop('required', false).prop('disabled', true);
                clearSedeControl($el);
            }
        });
        refreshConditionalDetails();
        bitUpdateSedeNotice();
        bitUpdateProgress();
    }

    function conditionalDefaultSource($control) {
        var sourceName = String($control.attr('data-default-from') || '');
        if (!sourceName) return $();

        var sourceById = document.getElementById(sourceName);
        if (sourceById) return $(sourceById);

        return $('.form-bitacora [name]').filter(function () {
            return this.name === sourceName;
        }).first();
    }

    function applyConditionalDefault($control) {
        var $source = conditionalDefaultSource($control);
        if (!$source.length) return;

        var sourceValue = String($source.val() || '');
        var currentValue = String($control.val() || '');
        var previousDefault = String($control.attr('data-applied-default') || '');
        if (currentValue !== '' && (previousDefault === '' || currentValue !== previousDefault)) return;

        $control.val(sourceValue);
        if (sourceValue === '') {
            $control.removeAttr('data-applied-default');
        } else {
            $control.attr('data-applied-default', sourceValue);
        }
    }

    function bindDynamicYesNo() {
        $('[data-toggle-detail]').on('change', function () {
            var target = $(this).data('toggle-detail');
            var name = this.name;
            var show = $('input[name="' + name + '"]:checked').val() === 'Si';
            var $target = $(target);
            $target.toggle(show);
            $target.find('input, textarea, select').prop('required', show).prop('disabled', !show);
            if (show) {
                $target.find('[data-default-from]').each(function () {
                    applyConditionalDefault($(this));
                });
            }
            if (!show) {
                $target.find('input, textarea, select').val('').removeAttr('data-applied-default');
            }
        }).trigger('change');

        $(document).on('input change', '[data-default-from]', function () {
            $(this).removeAttr('data-applied-default');
        });

        $(document).on('change', '.form-bitacora [name]', function () {
            var sourceName = this.name;
            $('[data-default-from]').filter(function () {
                return String($(this).attr('data-default-from') || '') === sourceName && !this.disabled;
            }).each(function () {
                applyConditionalDefault($(this));
            });
        });
    }

    function bitVisitKey(value) {
        var text = String(value || '').trim();
        var hash = 0;
        for (var i = 0; i < text.length; i++) {
            hash = ((hash << 5) - hash) + text.charCodeAt(i);
            hash |= 0;
        }
        var slug = text.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').substring(0, 24) || 'visita';
        return 'v_' + slug + '_' + Math.abs(hash).toString(36);
    }

    function addVisitDetailControl($row, col, label, type, name, id) {
        var $wrap = $('<div>').addClass('form-group bit-field ' + col);
        var $label = $('<label>').addClass('bit-label').attr('for', id).text(label + ' ');
        $label.append($('<span>').addClass('text-danger').text('*'));
        $wrap.append($label);

        if (type === 'textarea') {
            $wrap.append($('<textarea>').addClass('form-control bit-input bit-visit-detail-control').attr({
                id: id,
                name: name,
                rows: 3,
                required: true,
                'data-dynamic-field': '1'
            }));
        } else {
            $wrap.append($('<input>').addClass('form-control bit-input bit-visit-detail-control').attr({
                id: id,
                name: name,
                type: type,
                required: true,
                'data-dynamic-field': '1'
            }));
        }

        $row.append($wrap);
    }

    function ensureVisitDetailItem($panel, detailName, visitor) {
        var key = bitVisitKey(visitor);
        var $item = $panel.find('.bit-visit-detail-item').filter(function () {
            return $(this).data('visit-key') === key;
        }).first();

        if ($item.length) {
            $item.find('.bit-repeat-title').text(visitor);
            $item.find('input[type="hidden"][data-visit-hidden="1"]').val(visitor);
            return key;
        }

        $item = $('<div>').addClass('bit-repeat-item bit-visit-detail-item').data('visit-key', key);
        $item.append($('<div>').addClass('bit-repeat-title').text(visitor));
        $item.append($('<input>').attr({
            type: 'hidden',
            name: detailName + '[' + key + '][visitante]',
            value: visitor,
            'data-dynamic-field': '1',
            'data-visit-hidden': '1'
        }));

        var $row = $('<div>').addClass('form-row');
        addVisitDetailControl($row, 'col-md-3', 'HORA INGRESO', 'time', detailName + '[' + key + '][hora_inicio]', detailName + '_' + key + '_hora_inicio');
        addVisitDetailControl($row, 'col-md-3', 'HORA SALIDA', 'time', detailName + '[' + key + '][hora_final]', detailName + '_' + key + '_hora_final');
        addVisitDetailControl($row, 'col-md-12', 'ACTIVIDADES REALIZADAS', 'textarea', detailName + '[' + key + '][actividades]', detailName + '_' + key + '_actividades');
        $item.append($row);
        $panel.append($item);

        return key;
    }

    var fixingVisitDetailSelect = false;

    function updateMultiselectDetailGroup($select) {
        if (fixingVisitDetailSelect) return;

        var $wrapper = $select.closest('.bit-field');
        var $panel = $wrapper.find('.bit-multiselect-detail-panel').first();
        var detailName = String($select.data('detail-name') || ($select.attr('name') || '').replace(/\[\]$/, '') + '_detalles');
        var noApply = String($select.data('no-apply') || 'No aplica visita');
        var values = ($select.val() || []).filter(function (value) {
            return String(value || '').trim() !== '';
        });

        if (values.indexOf(noApply) !== -1 && values.length > 1) {
            fixingVisitDetailSelect = true;
            $select.val([noApply]).trigger('change');
            fixingVisitDetailSelect = false;
            values = [noApply];
        }

        if (values.length === 0 || (values.length === 1 && values[0] === noApply)) {
            $panel.empty().hide();
            return;
        }

        var keepKeys = {};
        values.forEach(function (visitor) {
            if (visitor === noApply) return;
            keepKeys[ensureVisitDetailItem($panel, detailName, visitor)] = true;
        });

        $panel.find('.bit-visit-detail-item').each(function () {
            var key = $(this).data('visit-key');
            if (!keepKeys[key]) {
                $(this).remove();
            }
        });

        $panel.toggle($panel.find('.bit-visit-detail-item').length > 0);
    }

    function refreshMultiselectDetailGroups() {
        $('.bit-multiselect-detail-select').each(function () {
            updateMultiselectDetailGroup($(this));
        });
    }

    function bindPlantField() {
        var $plantControls = $('#mant5, #mant6, #mant7');
        var $plantTimes = $('#mant5, #mant6');

        $plantControls.each(function () {
            $(this).data('plant-required', this.required);
        });

        function calculatePlantMinutes() {
            var inicio = $('#mant5').val();
            var fin = $('#mant6').val();

            if (!inicio || !fin) {
                $('#mant7').val('');
                return;
            }

            var inicioParts = inicio.split(':').map(Number);
            var finParts = fin.split(':').map(Number);
            var minutosInicio = (inicioParts[0] * 60) + inicioParts[1];
            var minutosFin = (finParts[0] * 60) + finParts[1];
            var diff = minutosFin - minutosInicio;

            if (diff < 0) {
                diff += 24 * 60;
            }

            $('#mant7').val(diff);
        }

        $plantTimes.on('change input', calculatePlantMinutes);
        $('input[name="planta_elect"]').on('change', function () {
            var mostrar = $('input[name="planta_elect"]:checked').val() === 'Si';
            $('#plantaGroup').toggle(mostrar);
            $plantControls.each(function () {
                $(this).prop('required', mostrar && $(this).data('plant-required') === true);
            });
            if (mostrar) {
                calculatePlantMinutes();
            } else {
                $plantControls.val('');
            }
        }).trigger('change');
    }

    function bitSelectedWeekday() {
        var value = $('#fechab, #fechasup').first().val();
        if (!value) return null;

        var parts = value.split('-');
        if (parts.length !== 3) return null;

        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return isNaN(date.getTime()) ? null : date.getDay();
    }

    function clearHumanControl($control) {
        if ($control.is(':radio, :checkbox')) {
            $control.prop('checked', false);
            return;
        }
        if ($control.is('select')) {
            $control.val('');
            return;
        }
        $control.val('');
    }

    function setHumanControlState($control, active) {
        var required = $control.data('required') === 1 || $control.data('required') === true || String($control.data('required')) === '1';
        $control.prop('disabled', !active).prop('required', active && required);
        if (!active) {
            clearHumanControl($control);
        }
    }

    function bitHumanGroupAvailable($group) {
        var sede = $('#sede, #idSede').first().val() || '';
        if (!fieldAllowsSede($group, sede)) {
            return false;
        }

        var weekdayOnly = $group.data('weekday-only');
        if (weekdayOnly === undefined || weekdayOnly === null || weekdayOnly === '') {
            return true;
        }

        var weekday = bitSelectedWeekday();
        return weekday !== null && Number(weekdayOnly) === weekday;
    }

    function updateHumanQuantityGroup($group) {
        var groupName = $group.data('group-name');
        var groupRequired = $group.data('required') === 1 || $group.data('required') === true || String($group.data('required')) === '1';
        var available = bitHumanGroupAvailable($group);
        var $radios = $group.find('input[type="radio"][name="' + groupName + '"]');
        var $panel = $group.find('.bit-quantity-panel').first();
        var $quantity = $group.find('.bit-quantity-input').first();

        $group.toggle(available);
        $radios.prop('disabled', !available).prop('required', available && groupRequired);

        if (!available) {
            $radios.prop('checked', false);
            setHumanControlState($quantity, false);
            $panel.hide();
            $group.find('.bit-quantity-item').hide().find('.bit-human-dependent').each(function () {
                setHumanControlState($(this), false);
            });
            return;
        }

        var isYes = $radios.filter(':checked').val() === 'Si';
        $panel.toggle(isYes);
        setHumanControlState($quantity, isYes);

        var qty = parseInt($quantity.val(), 10);
        var min = parseInt($quantity.attr('min'), 10) || 1;
        var max = parseInt($quantity.attr('max'), 10) || 10;
        if (isYes && $quantity.val() !== '') {
            if (isNaN(qty) || qty < min) qty = min;
            if (qty > max) qty = max;
            $quantity.val(qty);
        }

        $group.find('.bit-quantity-item').each(function () {
            var $item = $(this);
            var index = parseInt($item.data('item-index'), 10);
            var showItem = isYes && !isNaN(qty) && index <= qty;

            $item.toggle(showItem);
            $item.find('.bit-human-dependent').each(function () {
                setHumanControlState($(this), showItem);
            });
        });
    }

    function updateHumanDetailGroup($group) {
        var groupName = $group.data('group-name');
        var groupRequired = $group.data('required') === 1 || $group.data('required') === true || String($group.data('required')) === '1';
        var $radios = $group.find('input[type="radio"][name="' + groupName + '"]');
        var $panel = $group.find('.bit-detail-group-panel').first();
        var isYes = $radios.filter(':checked').val() === 'Si';

        $radios.prop('disabled', false).prop('required', groupRequired);
        $panel.toggle(isYes);
        $panel.find('.bit-human-dependent').each(function () {
            setHumanControlState($(this), isYes);
        });
    }

    function updateHumanBranchGroup($group) {
        var groupName = $group.data('group-name');
        var groupRequired = $group.data('required') === 1 || $group.data('required') === true || String($group.data('required')) === '1';
        var $radios = $group.find('input[type="radio"][name="' + groupName + '"]');
        var answer = $radios.filter(':checked').val();
        var activeBranch = answer === 'Si' ? 'si' : (answer === 'No' ? 'no' : '');

        $radios.prop('disabled', false).prop('required', groupRequired);
        $group.find('.bit-branch-panel').each(function () {
            var $panel = $(this);
            var active = String($panel.data('branch') || '') === activeBranch;
            $panel.toggle(active);
            $panel.find('.bit-human-dependent').each(function () {
                setHumanControlState($(this), active);
            });
        });
    }

    function updateDirectQuantityGroup($group) {
        var available = bitHumanGroupAvailable($group);
        var $quantity = $group.find('.bit-direct-quantity-input').first();
        var max = parseInt($quantity.attr('max'), 10) || 10;

        $group.toggle(available);
        $quantity.prop('disabled', !available).prop('required', available);
        if (!available) {
            $quantity.val('');
        }

        var rawQuantity = String($quantity.val() || '');
        var quantity = /^\d+$/.test(rawQuantity) ? parseInt(rawQuantity, 10) : NaN;
        if (!isNaN(quantity) && quantity > max) {
            quantity = max;
            $quantity.val(quantity);
        }

        $group.find('.bit-quantity-item').each(function () {
            var $item = $(this);
            var index = parseInt($item.data('item-index'), 10);
            var showItem = available && !isNaN(quantity) && quantity > 0 && index <= quantity;
            $item.toggle(showItem);
            $item.find('.bit-human-dependent').each(function () {
                setHumanControlState($(this), showItem);
            });
        });
    }

    function refreshDirectQuantityGroups() {
        $('.bit-direct-quantity-group').each(function () {
            updateDirectQuantityGroup($(this));
        });
    }

    function refreshHumanGroups() {
        $('.bit-quantity-group').each(function () {
            updateHumanQuantityGroup($(this));
        });
        $('.bit-detail-group').each(function () {
            updateHumanDetailGroup($(this));
        });
        $('.bit-branch-group').each(function () {
            updateHumanBranchGroup($(this));
        });
    }

    function resetConditionalUi(form) {
        $(form).find('[data-toggle-detail]').trigger('change');
        $(form).find('input[name="planta_elect"]').trigger('change');
        $(form).trigger('bitacora:refreshConditional');
    }

    function bitHandleFormRequest(form, $button, action) {
        if (!form.checkValidity()) {
            bitReportInvalid(form);
            return;
        }

        var $buttons = $(form).find('.bit-actions button');
        var defaultText = $button.data('default-text') || $button.text();
        var isPdfOnly = action === 'generate_pdf';
        $buttons.prop('disabled', true);
        $button.text('Guardando borrador...');

        var draftFlush = window.bitacoraDraftStore
            ? window.bitacoraDraftStore.flush()
            : Promise.resolve();

        draftFlush.then(function () {
            var formData = new FormData(form);
            formData.append('bitacora_action', action);
            if (window.bitacoraDraftStore) {
                var metadata = window.bitacoraDraftStore.getSubmissionMetadata();
                if (metadata.token) formData.append('draft_token', metadata.token);
                if (metadata.version !== null) formData.append('draft_version', String(metadata.version));
            }

            $button.text(isPdfOnly ? 'Generando...' : 'Enviando...');
            Swal.fire({
                title: isPdfOnly ? 'Generando PDF' : 'Enviando bitácora',
                html: isPdfOnly ? 'Estamos preparando el PDF con la información diligenciada.' : 'Estamos validando la información y preparando el reporte.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                customClass: { popup: 'bit-swal-popup' },
                didOpen: function () {
                    Swal.showLoading();
                }
            });

            $.ajax({
            url: '../scripts/send_bitacora.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 120000,
            success: function (resp) {
                if (resp.pdfGenerado && resp.downloadUrl) {
                    var link = document.createElement('a');
                    link.href = resp.downloadUrl;
                    link.download = resp.pdfFileName || 'bitacora.pdf';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }

                var safeMessage = escapeHtml(resp.message || 'Proceso finalizado.');
                var pdfBadge = resp.pdfGenerado ? 'ok' : 'warn';
                var summaryHtml = '<div class="bit-swal-summary">'
                    + '<div class="bit-swal-row"><span>PDF</span><span class="bit-swal-badge ' + pdfBadge + '">' + (resp.pdfGenerado ? 'Generado' : 'No generado') + '</span></div>'
                    + '<div class="bit-swal-row"><span>Estado</span><span>' + safeMessage + '</span></div>'
                    + '</div>';

                if (!isPdfOnly) {
                    var correoBadge = resp.correoEnviado === false ? 'warn' : 'ok';
                    var correoEstado = resp.correoEncolado ? 'En cola' : (resp.correoEnviado === false ? 'No enviado' : 'Enviado');
                    summaryHtml = '<div class="bit-swal-summary">'
                        + '<div class="bit-swal-row"><span>Correo</span><span class="bit-swal-badge ' + correoBadge + '">' + correoEstado + '</span></div>'
                        + '<div class="bit-swal-row"><span>PDF</span><span class="bit-swal-badge ' + pdfBadge + '">' + (resp.pdfGenerado ? 'Generado' : 'No generado') + '</span></div>'
                        + '<div class="bit-swal-row"><span>Estado</span><span>' + safeMessage + '</span></div>'
                        + '</div>';
                    if (resp.draftFinalized === false) {
                        summaryHtml += '<p class="bit-swal-warning">El borrador se conservó porque existe una versión más reciente o no pudo finalizarse.</p>';
                    }
                }

                Swal.fire({
                    icon: resp.ok ? (resp.correoEnviado === false ? 'warning' : 'success') : 'error',
                    title: resp.ok ? (isPdfOnly ? 'PDF generado' : 'Proceso finalizado') : (isPdfOnly ? 'No se pudo generar' : 'No se pudo enviar'),
                    html: resp.ok ? summaryHtml : escapeHtml(resp.message || 'No fue posible completar el proceso.'),
                    confirmButtonText: 'Entendido',
                    customClass: { popup: 'bit-swal-popup' }
                });

                if (!isPdfOnly && resp.ok && resp.draftFinalized === true && window.bitacoraDraftStore) {
                    window.bitacoraDraftStore.clear();
                }

                if (!isPdfOnly && resp.ok && resp.correoEnviado !== false && resp.draftFinalized === true) {
                    form.reset();
                    $('.select2-field').val(null).trigger('change');
                    resetConditionalUi(form);
                    if (window.bitacoraSede) window.bitacoraSede.refresh();
                    bitSnapshot();
                    bitUpdateProgress();
                }
            },
            error: function (xhr, textStatus) {
                var message = 'No se pudo completar la solicitud.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (textStatus === 'timeout') {
                    message = 'El proceso tardó demasiado. Revisa tu conexión e intenta nuevamente.';
                } else if (xhr.status === 0) {
                    message = 'No se pudo contactar el servidor.';
                } else if (textStatus === 'parsererror') {
                    message = 'El servidor respondió con datos inválidos. Intenta nuevamente o revisa los logs.';
                }

                Swal.fire({
                    icon: 'error',
                    title: xhr.status === 0 ? 'Error de conexión' : 'Error del servidor',
                    text: message,
                    confirmButtonText: 'Reintentar',
                    customClass: { popup: 'bit-swal-popup' }
                });
            },
            complete: function () {
                $buttons.prop('disabled', false);
                $button.text(defaultText);
            }
            });
        }).catch(function (error) {
            $buttons.prop('disabled', false);
            $button.text(defaultText);
            Swal.fire({
                icon: error && error.conflict ? 'warning' : 'error',
                title: error && error.conflict ? 'Conflicto de borrador' : 'No se pudo guardar el borrador',
                text: error && error.conflict
                    ? 'Carga la versión del servidor o confirma que deseas sobrescribirla antes de enviar.'
                    : 'Los cambios no se enviaron. Revisa tu conexión e intenta nuevamente.',
                confirmButtonText: 'Entendido',
                customClass: { popup: 'bit-swal-popup' }
            });
        });
    }

    $('.select2-field').each(function () {
        $(this).select2({
            tags: true,
            tokenSeparators: [',', ';', '.'],
            placeholder: $(this).data('placeholder') || undefined
        });
    });

    bindDynamicYesNo();
    refreshSedeFields();
    $('#sede, #idSede').on('change', refreshSedeFields);

    bitPrefillDefaults();
    bitInitCharCounters();
    bitSnapshot();
    bitUpdateProgress();
    bitUpdateSedeNotice();

    var bitProgressTimer = null;
    $(document).on('input change', '.form-bitacora', function () {
        if (bitProgressTimer) clearTimeout(bitProgressTimer);
        bitProgressTimer = setTimeout(bitUpdateProgress, 250);
    });

    $(window).on('scroll', bitSyncActiveSection);

    $(document).on('input change', '.bit-input', function () {
        var $control = $(this);
        $control.removeClass('is-invalid');
        if ($control.hasClass('select2-hidden-accessible')) {
            $control.next('.select2-container').find('.select2-selection').removeClass('is-invalid');
        }
    });

    $(window).on('beforeunload', function () {
        if (!window.appSessionTimeoutRedirecting && window.bitacoraDraftStore && window.bitacoraDraftStore.hasUnsavedChanges()) {
            return 'Tienes cambios sin guardar en la bitácora.';
        }
    });

    window.bitacoraUi = {
        snapshot: bitSnapshot,
        updateProgress: bitUpdateProgress
    };

    if ($('body').data('bitacora-type') === 'operational') {
        bindPlantField();
        refreshHumanGroups();
        refreshMultiselectDetailGroups();
        bitUpdateProgress();

        $(document).on('change', '.bit-human-toggle', function () {
            var $quantityGroup = $(this).closest('.bit-quantity-group');
            if ($quantityGroup.length) {
                updateHumanQuantityGroup($quantityGroup);
                return;
            }

            var $detailGroup = $(this).closest('.bit-detail-group');
            if ($detailGroup.length) {
                updateHumanDetailGroup($detailGroup);
                return;
            }

            var $branchGroup = $(this).closest('.bit-branch-group');
            if ($branchGroup.length) {
                updateHumanBranchGroup($branchGroup);
            }
        });

        $(document).on('input change', '.bit-quantity-input', function () {
            updateHumanQuantityGroup($(this).closest('.bit-quantity-group'));
        });

        $(document).on('change', '.bit-multiselect-detail-select', function () {
            updateMultiselectDetailGroup($(this));
        });

        $('#fechab').on('change', refreshHumanGroups);
        $('#sede, #idSede').on('change', refreshHumanGroups);
        $('.form-bitacora').on('bitacora:refreshConditional', refreshHumanGroups);
        $('.form-bitacora').on('bitacora:refreshConditional', refreshMultiselectDetailGroups);

    }

    refreshDirectQuantityGroups();
    $(document).on('input change', '.bit-direct-quantity-input', function () {
        updateDirectQuantityGroup($(this).closest('.bit-direct-quantity-group'));
    });
    $('#fechab, #fechasup, #sede, #idSede').on('change', refreshDirectQuantityGroups);
    $('.form-bitacora').on('bitacora:refreshConditional', refreshDirectQuantityGroups);

    /* ===== Prellenado inteligente ===== */

    function bitPrefillDefaults() {
        var today = new Date();
        var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
        var iso = today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate());

        $('#fechab, #fechasup').each(function () {
            if (!$(this).val()) {
                $(this).val(iso);
            }
        });

        var nombre = $('body').data('bitacora-nombre') || '';
        if (nombre && $('#responsable').length && !$.trim($('#responsable').val())) {
            $('#responsable').val(nombre);
        }
    }

    /* ===== Secciones y progreso ===== */

    function bitBuildSections() {
        var sections = [];
        var current = null;

        $('.form-bitacora > .form-row').children().each(function () {
            var $el = $(this);
            var $heading = $el.find('.bit-section-heading').first();
            if ($heading.length) {
                current = { title: $.trim($heading.find('h3, h4').first().text()), fields: [], $el: $el };
                sections.push(current);
                return;
            }
            if (current && $el.hasClass('bit-field')) {
                current.fields.push($el);
            }
        });

        return sections;
    }

    function bitSectionStatus(section) {
        var total = 0;
        var complete = 0;
        var seen = {};

        $.each(section.fields, function (i, $field) {
            if (!$field.is(':visible')) return;

            $field.find('input, select, textarea').each(function () {
                var $c = $(this);
                if ($c.is(':disabled') || !$c.is(':visible')) return;

                var name = $c.attr('name') || '';

                if ($c.is('input[type="radio"]') || $c.is('input[type="checkbox"]')) {
                    if (!name || seen[name]) return;
                    seen[name] = true;
                    if (!bitControlRequired($c)) return;
                    total++;
                    if ($field.find('[name="' + name + '"]').filter(':checked').length > 0) {
                        complete++;
                    }
                    return;
                }

                if (!bitControlRequired($c)) return;
                total++;
                if ($c.is('select[multiple]')) {
                    if (($c.val() || []).length > 0) complete++;
                } else if ($.trim($c.val() || '') !== '') {
                    complete++;
                }
            });
        });

        return { total: total, complete: complete };
    }

    function bitControlRequired($c) {
        if ($c.prop('required')) return true;
        return Number($c.data('required')) === 1;
    }

    function bitVisibleSections() {
        return bitBuildSections().filter(function (section) {
            return section.$el.length > 0 && section.$el.is(':visible');
        });
    }

    function bitSyncActiveSection() {
        var sections = bitVisibleSections();
        if (!sections.length) return;

        var marker = $(window).scrollTop() + 150;
        var activeIndex = 0;
        $.each(sections, function (i, section) {
            if (section.$el.offset().top <= marker) activeIndex = i;
        });

        var $chips = $('#bitSectionNav .bit-section-chip');
        $chips.removeClass('is-current').removeAttr('aria-current');
        $chips.filter('[data-target="' + activeIndex + '"]').addClass('is-current').attr('aria-current', 'true');
    }

    function bitUpdateProgress() {
        var sections = bitVisibleSections();
        var total = 0;
        var complete = 0;
        var navHtml = '';

        $.each(sections, function (i, section) {
            var counts = bitSectionStatus(section);
            section.counts = counts;
            total += counts.total;
            complete += counts.complete;

            var cls = counts.total === 0
                ? 'none'
                : (counts.complete === counts.total ? 'complete' : (counts.complete > 0 ? 'partial' : 'pending'));
            var countLabel = counts.total > 0 ? ' <span class="bit-chip-count">' + counts.complete + '/' + counts.total + '</span>' : '';
            navHtml += '<button type="button" class="bit-section-chip ' + cls + '" data-target="' + i + '">'
                + '<span class="bit-chip-dot" aria-hidden="true"></span>'
                + escapeHtml(section.title) + countLabel + '</button>';
        });

        $('#bitSectionNav').html(navHtml);
        $('#bitSectionNav .bit-section-chip').on('click', function () {
            var idx = parseInt($(this).data('target'), 10);
            if (!isNaN(idx) && sections[idx]) {
                sections[idx].$el.find('.bit-section-heading')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
        bitSyncActiveSection();

        var pct = total === 0 ? 100 : Math.round(complete / total * 100);
        $('#bitProgressFill').css('width', pct + '%');
        $('#bitProgressText').text(total === 0 ? 'Sin campos obligatorios' : complete + ' de ' + total + ' campos obligatorios completados');
    }

    function bitUpdateSedeNotice() {
        var hidden = $('.form-bitacora .bit-field').filter(function () {
            var $f = $(this);
            if ($f.is(':visible')) return false;
            if ($f.is('[data-sede]')) return true;
            return $f.parents('[data-sede]').length > 0;
        }).length;

        var sede = String($('#sede, #idSede').first().val() || '').trim();
        if (hidden <= 0 || sede === '' || Swal.isVisible()) {
            return;
        }

        Swal.fire({
            icon: 'info',
            toast: true,
            position: 'bottom-start',
            title: 'La sede seleccionada oculta ' + hidden + ' campo(s). Los campos ocultos no se envían.',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            customClass: { popup: 'bit-swal-toast' }
        });
    }

    /* ===== Validación mejorada ===== */

    function bitInvalidControls(form) {
        return $(form).find(':invalid').filter(function () {
            var $control = $(this);
            if ($control.is(':disabled')) return false;
            if ($control.is(':visible')) return true;
            return $control.hasClass('select2-hidden-accessible')
                && $control.next('.select2-container').is(':visible');
        });
    }

    function bitInvalidLabels(form) {
        var labels = [];
        bitInvalidControls(form).each(function () {
            var $c = $(this);
            var $field = $c.closest('.bit-field');
            var label = $.trim($field.find('.bit-label').first().text());
            if (!label) label = $c.attr('name') || 'Campo obligatorio';
            if (labels.indexOf(label) === -1) labels.push(label);
        });
        return labels;
    }

    function bitReportInvalid(form) {
        var labels = bitInvalidLabels(form);
        var $invalid = bitInvalidControls(form);

        $(form).find('.is-invalid').removeClass('is-invalid');
        $invalid.each(function () {
            var $control = $(this);
            $control.addClass('is-invalid');
            if ($control.hasClass('select2-hidden-accessible')) {
                $control.next('.select2-container').find('.select2-selection').addClass('is-invalid');
            }
        });

        var $first = $invalid.first();
        if ($first.length) {
            var $focusTarget = $first.hasClass('select2-hidden-accessible')
                ? $first.next('.select2-container').find('.select2-selection').first()
                : $first;
            $focusTarget.focus();
            if ($focusTarget[0] && $focusTarget[0].scrollIntoView) {
                $focusTarget[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        var items = labels.slice(0, 8).map(function (label) {
            return '<li>' + escapeHtml(label) + '</li>';
        }).join('');
        if (labels.length > 8) {
            items += '<li>… y ' + (labels.length - 8) + ' más</li>';
        }

        Swal.fire({
            icon: 'warning',
            title: 'Faltan campos por diligenciar',
            html: 'Revisa los ' + labels.length + ' campo(s) pendiente(s):<ul class="bit-swal-invalid-list">' + items + '</ul>',
            confirmButtonText: 'Entendido',
            customClass: { popup: 'bit-swal-popup' }
        }).then(function () {
            if ($focusTarget && $focusTarget.length) $focusTarget.focus();
        });
    }

    /* ===== Revisión previa al envío ===== */

    function bitBuildReviewSummary(form) {
        var sections = bitVisibleSections();
        var html = '';

        $.each(sections, function (i, section) {
            var counts = bitSectionStatus(section);
            var badge;
            if (counts.total === 0) {
                badge = '<span class="bit-swal-badge ok">Sin obligatorios</span>';
            } else if (counts.complete === counts.total) {
                badge = '<span class="bit-swal-badge ok">Completa</span>';
            } else {
                badge = '<span class="bit-swal-badge warn">' + counts.complete + '/' + counts.total + '</span>';
            }
            html += '<div class="bit-swal-row"><span>' + escapeHtml(section.title) + '</span>' + badge + '</div>';
        });

        return '<div class="bit-swal-summary">' + html + '</div>';
    }

    function bitAskConfirmation(form, $button) {
        Swal.fire({
            icon: 'question',
            title: '¿Enviar bitácora?',
            html: bitBuildReviewSummary(form),
            showCancelButton: true,
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Revisar',
            confirmButtonColor: '#d71920',
            customClass: { popup: 'bit-swal-popup' }
        }).then(function (result) {
            if (result.value) {
                bitHandleFormRequest(form, $button, 'send');
            }
        });
    }

    /* ===== Alerta al salir ===== */

    var bitFormSnapshot = '';

    function bitSnapshot() {
        bitFormSnapshot = $('.form-bitacora').serialize();
    }

    /* ===== Contador de caracteres ===== */

    function bitInitCharCounters() {
        $('.form-bitacora textarea.bit-input').each(function () {
            var $ta = $(this);
            if ($ta.data('char-counter')) return;
            $ta.data('char-counter', true);

            var $counter = $('<small>').addClass('bit-char-counter form-text text-muted').text('0 caracteres');
            $ta.after($counter);

            var update = function () {
                $counter.text(($ta.val() || '').length + ' caracteres');
            };
            $ta.on('input', update);
            update();
        });
    }

    $('.form-bitacora').on('submit', function (event) {
        event.preventDefault();
        var form = this;
        var $button = $(this).find('button[type="submit"]');
        if (!form.checkValidity()) {
            bitReportInvalid(form);
            return;
        }
        bitAskConfirmation(form, $button);
    });

    $('#generar_pdf').on('click', function () {
        var form = $(this).closest('form')[0];
        bitHandleFormRequest(form, $(this), 'generate_pdf');
    });

    window.bitacoraSede = {
        refresh: refreshSedeFields,
        clear: clearSedeFields
    };
});
