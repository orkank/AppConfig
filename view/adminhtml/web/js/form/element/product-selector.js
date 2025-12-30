/**
 * Product Selector Component
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
            template: 'IDangerous_AppConfig/form/element/product-selector',
            selectedProducts: [],
            modalUrl: '',
            productFetchUrl: '',
            modalTitle: $t('Select Products'),
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
            this.selectedProducts.subscribe(function (newValue) {
                self.updateValue();
            });

            // Initialize from current value
            // We can rely on listens usually, but manual check is safe
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
            this.selectedProducts = ko.observableArray([]);
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
                // Fallback but be careful
                $form = $('form').has('[name="form_key"]').first();
            }

            // Hook into jQuery submit
            $form.on('submit', function() {
                self.updateHiddenInputs();
                return true;
            });

            // Also hook into the Save button click
            // Magento often uses data-ui-id for the save button
            $('[data-ui-id="page-actions-toolbar-save-button"]').on('click', function() {
                 self.updateHiddenInputs();
            });

            // Fallback for standard ID
            $('#save').on('click', function() {
                self.updateHiddenInputs();
            });

            // Periodically sync just in case
            setInterval(function() {
                if (self.getCurrentValueType() === 'products') {
                    self.updateHiddenInputs();
                }
            }, 2000);
        },

        /**
         * Update hidden inputs in the DOM to ensure payload is sent
         */
        updateHiddenInputs: function() {
            // This function is mostly a fallback now, as we rely on KO value binding.
            // But we ensure the value field used by Save.php is populated.

            var products = this.selectedProducts();
            var jsonValue = JSON.stringify(products);

            // Update UI component value (which updates the hidden input inside the template)
            this.value(jsonValue);

            // Ensure the main 'value' field (if it exists outside our component) is also updated or cleared
            // The controller looks at 'value' or 'selected_products'
            // In layout, we have 'value' as a textarea. We should update it if we are active.

            var $valueField = $('[name="value"]');
            if ($valueField.length && $valueField.is(':hidden')) {
                 // We are in product mode, so update the main value field to match
                 $valueField.val(jsonValue);
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
                this.selectedProducts([]);
                return;
            }

            if (typeof value === 'string') {
                try {
                    var products = JSON.parse(value);
                    if (Array.isArray(products)) {
                        this.selectedProducts(products);
                    }
                } catch (e) {
                    console.warn('Invalid JSON for products:', e);
                }
            } else if (Array.isArray(value)) {
                this.selectedProducts(value);
            }
        },

        /**
         * Update value based on selected products
         */
        updateValue: function () {
            var products = this.selectedProducts();
            var jsonValue = JSON.stringify(products);
            this.value(jsonValue);
            this.updateHiddenInputs(); // Sync immediately as well
        },

        /**
         * Open product selection modal
         */
        openProductModal: function () {
            var self = this;
            var modalUrl = this.buildModalUrl();

            $.ajax({
                url: modalUrl,
                type: 'GET',
                dataType: 'html',
                showLoader: true
            }).done(function (data) {
                var modalHtml = $('<div class="product-selector-modal">' + data + '</div>');
                $('body').append(modalHtml);

                var modalOptions = {
                    type: 'slide',
                    modalClass: 'product-selector-modal-container',
                    title: self.modalTitle,
                    buttons: [{
                        text: $t('Cancel'),
                        class: 'action-secondary',
                        click: function () {
                            modalHtml.modal('closeModal');
                        }
                    }, {
                        text: $t('Add Selected Products'),
                        class: 'action-primary',
                        click: function () {
                            // Delay slightly to ensure grids are updated
                            self.addSelectedProducts(modalHtml);
                            modalHtml.modal('closeModal');
                        }
                    }]
                };

                modalHtml.modal(modalOptions);
                modalHtml.modal('openModal');

                // Initialize grid initialization if needed
                modalHtml.trigger('contentUpdated');
            });
        },

        /**
         * Build modal URL
         */
        buildModalUrl: function () {
            return this.modalUrl || 'appconfig/keyvalue/productgrid';
        },

        /**
         * Add selected products from modal
         */
        addSelectedProducts: function (modalHtml) {
            var self = this;
            var selectedIds = [];

            // Query the document for the modal container and checked inputs
            // We use the modal class we assigned: .product-selector-modal-container
            var $modalContainer = $('.product-selector-modal-container');
            var checkboxes = $modalContainer.find('input[type="checkbox"][name="appconfig_selected_products"]');

            checkboxes.each(function () {
                var $checkbox = $(this);
                if ($checkbox.is(':checked')) {
                    var val = $checkbox.val();
                    if (val && !isNaN(parseInt(val))) {
                        selectedIds.push(val);
                    }
                }
            });

            // Remove duplicates
            selectedIds = [...new Set(selectedIds)];

            if (selectedIds.length > 0) {
                this.loadProductsByIds(selectedIds);
            } else {
                // Fallback: check if standard grid massaction was used
                // sometimes the grid keeps selection in a hidden input
                var hiddenMass = $modalContainer.find('input[name="massaction_prepare_key"]');
                // But honestly, the direct checkbox check is most reliable for a simple grid.

                alert($t('Please select at least one product.'));
            }
        },

        /**
         * Load product details by IDs
         */
        loadProductsByIds: function (productIds) {
            var self = this;

            $.ajax({
                url: this.productFetchUrl,
                type: 'POST',
                data: {
                    product_ids: productIds,
                    form_key: window.FORM_KEY
                },
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                if (response.success && response.products) {
                    var currentProducts = self.selectedProducts();

                    // Merge new products avoiding duplicates
                    response.products.forEach(function(newProd) {
                        var exists = currentProducts.some(function(p) { return p.id == newProd.id; });
                        if (!exists) {
                            currentProducts.push(newProd);
                        }
                    });

                    self.selectedProducts(currentProducts);
                    self.updateValue();
                } else {
                    alert(response.message || $t('Failed to load products.'));
                }
            });
        },

        /**
         * Remove product from selection
         */
        removeProduct: function (product) {
            var products = this.selectedProducts().filter(function (p) {
                return p.id !== product.id;
            });
            this.selectedProducts(products);
            this.updateValue();
        }
    });
});

