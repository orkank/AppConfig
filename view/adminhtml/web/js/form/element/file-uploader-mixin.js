/**
 * Normalizes allowedExtensions before validate-file-type (whitespace / array quirks from UI meta).
 * Console: filter by [AppConfig file-uploader-mixin]
 */
define([
    'Magento_Ui/js/lib/validation/validator'
], function (validator) {
    'use strict';

    var LOG = '[AppConfig file-uploader-mixin]';
    var loggedMixinApplied = false;

    return function (target) {
        if (!loggedMixinApplied) {
            loggedMixinApplied = true;
            console.info(LOG, 'Mixin applied to Magento_Ui/js/form/element/file-uploader — if you do not see this, clear static content / browser cache.');
        }

        return target.extend({
            isExtensionAllowed: function (file) {
                var types = this.allowedExtensions;
                var fieldHint = {
                    name: this.name,
                    index: this.index,
                    ns: this.ns
                };

                console.groupCollapsed(LOG, 'isExtensionAllowed', file && file.name);
                console.log(LOG, 'field:', fieldHint);
                console.log(LOG, 'file.name / size:', file && file.name, file && file.size);
                console.log(LOG, 'allowedExtensions (raw):', types, '| typeof:', typeof types);

                if (types === false || types === undefined || types === '') {
                    console.log(LOG, '→ delegate _super (empty/false/undefined allowedExtensions)');
                    console.groupEnd();
                    return this._super(file);
                }

                if (typeof types === 'string') {
                    types = types.trim().split(/\s+/).filter(Boolean).join(' ');
                } else if (Array.isArray(types)) {
                    types = types.filter(Boolean).join(' ');
                } else {
                    console.warn(LOG, '→ delegate _super (unexpected type for allowedExtensions)');
                    console.groupEnd();
                    return this._super(file);
                }

                console.log(LOG, 'allowedExtensions (normalized string for validator):', types);

                var result = validator('validate-file-type', file.name, types);

                console.log(LOG, 'validate-file-type result:', result);
                if (!result || !result.passed) {
                    console.warn(LOG, 'BLOCKED before XHR —', result && result.message);
                }
                console.groupEnd();

                return result;
            }
        });
    };
});
