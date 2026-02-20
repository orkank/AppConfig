/**
 * CMS Page Selector Component
 */
define([
    'jquery',
    'uiRegistry',
    'Magento_Ui/js/form/element/abstract',
    'mage/translate',
    'Magento_Ui/js/modal/modal',
    'ko'
], function ($, registry, Abstract, $t, modal, ko) {
    'use strict';

    return Abstract.extend({
        defaults: {
            template: 'IDangerous_AppConfig/form/element/cms-selector',
            selectedPages: [],
            modalUrl: '',
            pageFetchUrl: '',
            modalTitle: $t('Select CMS Pages'),
            listens: {
                'value': 'onValueChange'
            }
        },

        initialize: function () {
            this._super();
            var self = this;

            this.selectedPages.subscribe(function () {
                self.updateValue();
            });

            this.onValueChange(this.value());

            setTimeout(function () {
                self.handleVisibility();
                self.setupFormSubmitHandler();
            }, 500);

            return this;
        },

        initObservable: function () {
            this.selectedPages = ko.observableArray([]);
            this._super();
            return this;
        },

        setupFormSubmitHandler: function () {
            var self = this;
            var $form = $('#edit_form');
            if (!$form.length) {
                $form = $('form[data-form="edit-form"]');
            }
            if (!$form.length) {
                $form = $('form').has('[name="form_key"]').first();
            }

            $form.on('submit', function () {
                self.updateHiddenInputs();
                return true;
            });

            $('[data-ui-id="page-actions-toolbar-save-button"]').on('click', function () {
                self.updateHiddenInputs();
            });

            $('#save').on('click', function () {
                self.updateHiddenInputs();
            });

            setInterval(function () {
                if (self.getCurrentValueType() === 'cms') {
                    self.updateHiddenInputs();
                }
            }, 2000);
        },

        updateHiddenInputs: function () {
            var pages = this.selectedPages();
            var jsonValue = JSON.stringify(pages);
            this.value(jsonValue);

            var $form = $('form[data-form-type="other"]');
            if (!$form.length) {
                $form = $('form').first();
            }

            var $selectedField = $form.find('[name="selected_cms_pages"]');
            if ($selectedField.length) {
                $selectedField.val(jsonValue);
            } else {
                $form.append($('<input>').attr({type: 'hidden', name: 'selected_cms_pages', value: jsonValue}));
            }
        },

        getCurrentValueType: function () {
            var type = $('[name="value_type"]').val();
            if (!type) {
                var valueTypeComp = registry.get('appconfig_keyvalue_form.general.value_type');
                if (valueTypeComp) {
                    type = valueTypeComp.value();
                }
            }
            return type;
        },

        handleVisibility: function () {
            this.visible(true);
        },

        onValueChange: function (value) {
            if (!value) {
                this.selectedPages([]);
                return;
            }

            if (typeof value === 'string') {
                try {
                    var pages = JSON.parse(value);
                    if (Array.isArray(pages)) {
                        this.selectedPages(pages);
                    }
                } catch (e) {
                    // Invalid JSON
                }
            }
        },

        updateValue: function () {
            var pages = this.selectedPages();
            var jsonValue = JSON.stringify(pages);
            this.value(jsonValue);
            this.updateHiddenInputs();
        },

        openCmsModal: function () {
            var self = this;
            var modalUrl = this.modalUrl || 'appconfig/keyvalue/cmsgrid';

            $.ajax({
                url: modalUrl,
                type: 'GET',
                dataType: 'html',
                showLoader: true
            }).done(function (data) {
                var modalHtml = $('<div class="cms-selector-modal">' + data + '</div>');
                $('body').append(modalHtml);

                var modalOptions = {
                    type: 'slide',
                    modalClass: 'cms-selector-modal-container',
                    title: self.modalTitle,
                    buttons: [{
                        text: $t('Cancel'),
                        class: 'action-secondary',
                        click: function () {
                            modalHtml.modal('closeModal');
                        }
                    }, {
                        text: $t('Add Selected Pages'),
                        class: 'action-primary',
                        click: function () {
                            self.addSelectedPages(modalHtml);
                            modalHtml.modal('closeModal');
                        }
                    }]
                };

                modalHtml.modal(modalOptions);
                modalHtml.modal('openModal');
                modalHtml.trigger('contentUpdated');
            });
        },

        addSelectedPages: function (modalHtml) {
            var self = this;
            var selectedIds = [];
            var $modalContainer = $('.cms-selector-modal-container');
            var checkboxes = $modalContainer.find('input[type="checkbox"][name="appconfig_selected_cms_pages"]');

            checkboxes.each(function () {
                var $checkbox = $(this);
                if ($checkbox.is(':checked')) {
                    var val = $checkbox.val();
                    if (val && !isNaN(parseInt(val))) {
                        selectedIds.push(val);
                    }
                }
            });

            selectedIds = [...new Set(selectedIds)];

            if (selectedIds.length > 0) {
                this.loadPagesByIds(selectedIds);
            } else {
                alert($t('Please select at least one CMS page.'));
            }
        },

        loadPagesByIds: function (pageIds) {
            var self = this;

            $.ajax({
                url: this.pageFetchUrl,
                type: 'POST',
                data: {
                    page_ids: pageIds,
                    form_key: window.FORM_KEY
                },
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                if (response.success && response.pages) {
                    var currentPages = self.selectedPages();
                    response.pages.forEach(function (newPage) {
                        var exists = currentPages.some(function (p) {
                            return p.id == newPage.id;
                        });
                        if (!exists) {
                            currentPages.push(newPage);
                        }
                    });
                    self.selectedPages(currentPages);
                    self.updateValue();
                } else {
                    alert(response.message || $t('Failed to load CMS pages.'));
                }
            });
        },

        removePage: function (page) {
            var pages = this.selectedPages().filter(function (p) {
                return p.id !== page.id;
            });
            this.selectedPages(pages);
            this.updateValue();
        }
    });
});
