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
            template: 'IDangerous_AppConfig/form/element/product-selector', // Reuse the same template as it handles list display
            selectedCategories: [],
            modalUrl: '',
            categoryFetchUrl: '',
            modalTitle: $t('Select Categories'),
            listens: {
                'value': 'onValueChange'
            }
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            var self = this;

            // Subscribe to observable changes to update value
            this.selectedCategories.subscribe(function (newValue) {
                self.updateValue();
            });

            // Initialize from current value
            this.onValueChange(this.value());

            // Initialize visibility and listeners
            setTimeout(function() {
                self.handleVisibility();
                self.setupFormSubmitHandler();
            }, 500);

            return this;
        },

        /**
         * Init observables
         */
        initObservable: function () {
            this.selectedCategories = ko.observableArray([]);
            this._super();
            return this;
        },

        /**
         * Setup robust form submit handler
         */
        setupFormSubmitHandler: function () {
            var self = this;

             // Try specific Magento admin form ID first
            var $form = $('#edit_form');
             if (!$form.length) {
                $form = $('form[data-form="edit-form"]');
            }
            if (!$form.length) {
                $form = $('form').has('[name="form_key"]').first();
            }

            // Hook into jQuery submit to ensure data is there right before send
            $form.on('submit', function() {
                self.updateHiddenInputs();
                return true;
            });

             // Also hook into the Save button click if possible
             $('[data-ui-id="page-actions-toolbar-save-button"]').on('click', function() {
                 self.updateHiddenInputs();
            });

            $('#save').on('click', function() {
                self.updateHiddenInputs();
            });

             // Periodically sync just in case
            setInterval(function() {
                if (self.getCurrentValueType() === 'category') {
                    self.updateHiddenInputs();
                }
            }, 2000);
        },

        /**
         * Update hidden inputs in the DOM
         */
        updateHiddenInputs: function() {
            var categories = this.selectedCategories();
            var jsonValue = JSON.stringify(categories);
            var $form = $('form[data-form-type="other"]');
            if (!$form.length) $form = $('form').first();

            // 1. Update/Create 'value' field (textarea or hidden)
            var $valueField = $form.find('[name="value"]');
            if ($valueField.length) {
                var currentType = this.getCurrentValueType();
                if (currentType === 'category') {
                    $valueField.val(jsonValue);
                }
            } else {
                 if (this.getCurrentValueType() === 'category') {
                    $form.append($('<input>').attr({type: 'hidden', name: 'value', value: jsonValue}));
                 }
            }

            // 2. Update/Create 'selected_categories' field
            var $selectedField = $form.find('[name="selected_categories"]');
            if ($selectedField.length) {
                $selectedField.val(jsonValue);
            } else {
                $form.append($('<input>').attr({type: 'hidden', name: 'selected_categories', value: jsonValue}));
            }
        },

         /**
         * Get current value type from the form
         */
        getCurrentValueType: function() {
            var type = $('[name="value_type"]').val();
             if (!type) {
                // Try UI registry
                var valueTypeComp = registry.get('appconfig_keyvalue_form.general.value_type');
                if (valueTypeComp) type = valueTypeComp.value();
            }
            return type;
        },

        /**
         * Handle visibility logic - all fields are always visible now
         */
        handleVisibility: function() {
            var self = this;
            // All fields are always visible - no need to hide/show based on value_type
            self.visible(true);
        },

        /**
         * Handle value change
         */
        onValueChange: function (value) {
            if (!value) {
                this.selectedCategories([]);
                return;
            }

            if (typeof value === 'string') {
                try {
                    var categories = JSON.parse(value);
                    if (Array.isArray(categories)) {
                        this.selectedCategories(categories);
                    }
                } catch (e) {
                    // console.warn('Invalid JSON for categories:', e);
                }
            }
        },

        /**
         * Update value based on selected categories
         */
        updateValue: function () {
            var categories = this.selectedCategories();
            var jsonValue = JSON.stringify(categories);
            this.value(jsonValue);
            this.updateHiddenInputs();
        },

        /**
         * Open category selection modal
         */
        openCategoryModal: function () {
            var self = this;
            var modalUrl = this.buildModalUrl();

            $.ajax({
                url: modalUrl,
                type: 'GET',
                dataType: 'html',
                showLoader: true
            }).done(function (data) {
                var modalHtml = $('<div class="category-selector-modal">' + data + '</div>');
                $('body').append(modalHtml);

                // Define the global object expected by the category tree widget
                // This fixes 'options.jsFormObject is undefined' error
                window.category_selector = {
                    updateElement: {
                        value: '',
                        valueName: ''
                    },
                    updateItem: function(id) {
                         // This is called by the tree when an item is checked
                         // We can use it if we want to track state, but probing the DOM/Tree is often enough
                    }
                };

                var modalOptions = {
                    type: 'slide',
                    modalClass: 'category-selector-modal-container',
                    title: self.modalTitle,
                    buttons: [{
                        text: $t('Cancel'),
                        class: 'action-secondary',
                        click: function () {
                            modalHtml.modal('closeModal');
                        }
                    }, {
                        text: $t('Add Selected Categories'),
                        class: 'action-primary',
                        click: function () {
                            self.addSelectedCategories(modalHtml);
                            modalHtml.modal('closeModal');
                        }
                    }]
                };

                modalHtml.modal(modalOptions);
                modalHtml.modal('openModal');

                // Initialize widgets/scripts in the loaded content
                modalHtml.trigger('contentUpdated');
            });
        },

        /**
         * Build modal URL
         */
        buildModalUrl: function () {
            return this.modalUrl || 'appconfig/keyvalue/categorygrid';
        },

        /**
         * Add selected categories from modal
         */
        addSelectedCategories: function (modalHtml) {
            var self = this;
            var selectedIds = [];
            var $modalContainer = $('.category-selector-modal-container');

            // Strategy 1: Check the global object updated by Magento's Tree (Most reliable for ExtJS tree)
            if (window.category_selector && window.category_selector.updateElement && window.category_selector.updateElement.value) {
                var val = window.category_selector.updateElement.value;
                if (typeof val === 'string') {
                    var parts = val.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
                    parts.forEach(function(p) { selectedIds.push(p); });
                }
            }

            // Strategy 2: Fallback to DOM input (legacy or different tree implementations)
            // Note: Magento's ExtJS checkboxes might not update the 'checked' property on the input element
            // correctly due to visual masking, but sometimes hidden input 'category_ids' is present.
            var hiddenInput = $modalContainer.find('input[name="category_ids"]');
            if (hiddenInput.length && hiddenInput.val()) {
                var parts = hiddenInput.val().split(',');
                parts.forEach(function(p) { if(p) selectedIds.push(p); });
            }

            // Strategy 3: Try to find checked inputs if Strategy 1 & 2 failed or as supplement
            // We check for the visual class checking if the input state is deceptive
            var treeCheckboxes = $modalContainer.find('.x-tree-node-el input.l-tcb');
            treeCheckboxes.each(function() {
                var $cb = $(this);
                // In some implementations, the input.checked IS updated, but jQuery.is(':checked') might act up
                // or the user interaction didn't bubble standardly.
                // However, the tree usually manages the state.
                if ($cb.is(':checked') || $cb.prop('checked')) {
                    if ($cb.val() && $cb.val() !== 'on') {
                         selectedIds.push($cb.val());
                    }
                }
            });

            // Deduplicate
            selectedIds = [...new Set(selectedIds)];

            if (selectedIds.length > 0) {
                this.loadCategoriesByIds(selectedIds);
            } else {
                alert($t('Please select at least one category.'));
            }
        },

        /**
         * Load category details by IDs
         */
        loadCategoriesByIds: function (categoryIds) {
            var self = this;

            $.ajax({
                url: this.categoryFetchUrl,
                type: 'POST',
                data: {
                    category_ids: categoryIds,
                    form_key: window.FORM_KEY
                },
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                if (response.success && response.categories) {
                    var currentCategories = self.selectedCategories();

                    // Merge
                    response.categories.forEach(function(newCat) {
                        var exists = currentCategories.some(function(c) { return c.id == newCat.id; });
                        if (!exists) {
                            currentCategories.push(newCat);
                        }
                    });

                    self.selectedCategories(currentCategories);
                    self.updateValue();
                } else {
                    alert(response.message || $t('Failed to load categories.'));
                }
            });
        },

        /**
         * Remove category from selection
         */
        removeCategory: function (category) {
            var categories = this.selectedCategories().filter(function (c) {
                return c.id !== category.id;
            });
            this.selectedCategories(categories);
            this.updateValue();
        }
    });
});
