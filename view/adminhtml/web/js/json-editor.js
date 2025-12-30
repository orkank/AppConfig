/**
 * JSON Editor Component for Key-Value Pairs with Nested Mode and File Picker
 */
define([
    'jquery',
    'mage/translate',
    'uiRegistry',
    'mage/adminhtml/browser'
], function ($, $t, registry) {
    'use strict';

    var initJsonEditor = function(config) {
        config = config || {};
        var mediaBrowserUrl = config.mediaBrowserUrl || 'cms/wysiwyg_images/index';
        var mediaBaseUrl = config.mediaBaseUrl || '';
        var currentMode = 'keyvalue'; // 'keyvalue', 'nested', 'raw'
        var filePickerCounter = 0;

        /**
         * Initialize JSON Editor
         */
        function init() {
            console.log('JSON Editor init called');

            // Function to find json_value textarea element
            var findJsonValueTextarea = function() {
                var selectors = [
                    'textarea[name*="json_value"]',
                    'textarea[data-bind*="json_value"]',
                    '.admin__field[data-index="json_value"] textarea',
                    '[name="data[json_value]"]',
                    'textarea[name*="[json_value]"]'
                ];

                for (var i = 0; i < selectors.length; i++) {
                    var $textarea = $(selectors[i]);
                    if ($textarea.length && !$textarea.closest('#json-editor-container').length) {
                        return $textarea;
                    }
                }
                return null;
            };

            // Move JSON editor template into form after json_value field
            var moveJsonEditor = function() {
                var $jsonValueField = findJsonValueTextarea();
                var $jsonEditorContainer = $('#json-editor-container');

                if (!$jsonEditorContainer.length) {
                    console.log('JSON editor container not found');
                    return;
                }

                if ($jsonValueField && $jsonValueField.length) {
                    $jsonValueField = $jsonValueField.closest('.admin__field');
                    if ($jsonValueField.length) {
                        $jsonEditorContainer.detach().insertAfter($jsonValueField);
                        console.log('JSON editor moved after json_value field');
                    }
                } else {
                    var $fieldset = $('.admin__fieldset[data-index="general"]');
                    if ($fieldset.length) {
                        $jsonEditorContainer.detach().appendTo($fieldset);
                        console.log('JSON editor added to fieldset');
                    }
                }
            };

            // Show JSON editor (always show for json_value field)
            var showJsonEditor = function() {
                var $jsonValueField = findJsonValueTextarea();
                var $jsonEditor = $('#json-editor-container');

                if ($jsonValueField && $jsonValueField.length && $jsonEditor.length) {
                    $jsonValueField = $jsonValueField.closest('.admin__field');
                    $jsonValueField.hide();
                    $jsonEditor.show();
                    console.log('JSON editor shown');
                } else {
                    console.log('Could not find json_value field or JSON editor', {
                        jsonValueField: $jsonValueField ? $jsonValueField.length : 0,
                        jsonEditor: $jsonEditor.length
                    });
                }
            };

            // Escape HTML
            var escapeHtml = function(text) {
                if (text === null || text === undefined) {
                    return '';
                }
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
            };

            // Convert directive URL to actual media URL
            var convertDirectiveToMediaUrl = function(directiveUrl) {
                if (!directiveUrl || typeof directiveUrl !== 'string') {
                    return directiveUrl;
                }

                // Check if it's a directive URL
                var directiveMatch = directiveUrl.match(/\/cms\/wysiwyg\/directive\/___directive\/([^\/]+)/);
                if (!directiveMatch) {
                    // Not a directive URL, return as is
                    return directiveUrl;
                }

                try {
                    // Decode the directive - Magento uses URL-safe Base64 encoding
                    var encodedDirective = directiveMatch[1];
                    // Replace URL-safe characters back to standard Base64
                    encodedDirective = encodedDirective.replace(/-/g, '+').replace(/_/g, '/').replace(/,/g, '=');

                    // Decode Base64 using browser's atob or fallback
                    var decodedDirective = '';
                    if (typeof window.atob === 'function') {
                        decodedDirective = decodeURIComponent(window.atob(encodedDirective));
                    } else {
                        // Fallback - try to decode manually if atob not available
                        decodedDirective = decodeURIComponent(window.atob(encodedDirective));
                    }

                    // Extract media path from directive: {{media url="path/to/file.jpg"}}
                    var mediaMatch = decodedDirective.match(/media\s+url=["']([^"']+)["']/);
                    if (mediaMatch && mediaMatch[1]) {
                        var mediaPath = mediaMatch[1];
                        // Ensure media path doesn't start with /
                        mediaPath = mediaPath.replace(/^\//, '');
                        // Combine with base media URL
                        var baseUrl = mediaBaseUrl.replace(/\/$/, '');
                        return baseUrl + '/' + mediaPath;
                    }
                } catch (e) {
                    console.error('Error converting directive URL:', e);
                    // If conversion fails, try to use Magento's Base64 utility if available
                    if (typeof window.Base64 !== 'undefined' && window.Base64.mageDecode) {
                        try {
                            var encodedDirective = directiveMatch[1];
                            var decodedDirective = window.Base64.mageDecode(decodeURIComponent(encodedDirective));
                            var mediaMatch = decodedDirective.match(/media\s+url=["']([^"']+)["']/);
                            if (mediaMatch && mediaMatch[1]) {
                                var mediaPath = mediaMatch[1].replace(/^\//, '');
                                var baseUrl = mediaBaseUrl.replace(/\/$/, '');
                                return baseUrl + '/' + mediaPath;
                            }
                        } catch (e2) {
                            console.error('Error with Base64.mageDecode:', e2);
                        }
                    }
                }

                // If conversion fails, return original
                return directiveUrl;
            };

            // Generate unique ID for file picker
            var generateFilePickerId = function() {
                filePickerCounter++;
                return 'json-editor-file-picker-' + filePickerCounter + '-' + Date.now();
            };

            // Open media browser for file selection
            var openMediaBrowser = function(targetInputId) {
                if (typeof window.MediabrowserUtility === 'undefined') {
                    require(['mage/adminhtml/browser'], function() {
                        openMediaBrowserDialog(targetInputId);
                    });
                } else {
                    openMediaBrowserDialog(targetInputId);
                }
            };

            var openMediaBrowserDialog = function(targetInputId) {
                var storeId = 0; // Default store ID
                var type = 'file'; // Allow all file types
                var $targetInput = $('#' + targetInputId);

                if (!$targetInput.length) {
                    console.error('Target input not found:', targetInputId);
                    return;
                }

                // Store the current value to detect changes
                var previousValue = $targetInput.val();

                var wUrl = mediaBrowserUrl +
                    '/target_element_id/' + targetInputId + '/' +
                    'store/' + storeId + '/' +
                    'type/' + type + '/?isAjax=true';

                window.MediabrowserUtility.openDialog(
                    wUrl,
                    null,
                    null,
                    $t('Select File'),
                    {
                        targetElementId: targetInputId,
                        closed: function() {
                            // Check if value changed after dialog closes
                            setTimeout(function() {
                                var newValue = $targetInput.val();
                                if (newValue !== previousValue && newValue) {
                                    // Convert directive URL to media URL if needed
                                    var mediaUrl = convertDirectiveToMediaUrl(newValue);
                                    $targetInput.val(mediaUrl).trigger('change');
                                    updateJsonValue();
                                }
                            }, 100);
                        }
                    }
                );
            };

            // Create file picker icon HTML
            var createFilePickerIcon = function(inputId) {
                return '<button type="button" ' +
                    'class="action-browse file-picker-btn" ' +
                    'data-target-id="' + escapeHtml(inputId) + '" ' +
                    'title="' + $t('Select File') + '" ' +
                    'style="margin-left: 5px; padding: 5px 8px; cursor: pointer;">' +
                    '<span class="admin__control-support-text" style="font-size: 16px;">📁</span>' +
                    '</button>';
            };

            // Add new key-value pair (simple mode)
            var addKeyValuePair = function() {
                var pairId = generateFilePickerId();
                var valueInputId = 'keyvalue-value-' + pairId;
                var pairHtml = '<div class="keyvalue-pair-row admin__field" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">' +
                    '<div style="flex: 1;">' +
                    '<input type="text" class="admin__control-text keyvalue-key" placeholder="' + $t('Key') + '" style="width: 100%;">' +
                    '</div>' +
                    '<div style="flex: 2; display: flex; align-items: center;">' +
                    '<input type="text" id="' + valueInputId + '" class="admin__control-text keyvalue-value" placeholder="' + $t('Value') + '" style="flex: 1;">' +
                    createFilePickerIcon(valueInputId) +
                    '</div>' +
                    '<div>' +
                    '<button type="button" class="action-delete remove-keyvalue-pair" title="' + $t('Remove') + '">' +
                    '<span>' + $t('Remove') + '</span>' +
                    '</button>' +
                    '</div>' +
                    '</div>';

                $('#keyvalue-pairs-container').append(pairHtml);
                updateJsonValue();
            };

            // Add nested row (array of objects mode)
            var addNestedRow = function() {
                var rowId = generateFilePickerId();
                var rowIndex = $('#nested-pairs-container .nested-row').length;
                var rowHtml = '<div class="nested-row admin__field" data-row-index="' + rowIndex + '" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;">' +
                    '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' +
                    '<strong style="color: #666;">' + $t('Row') + ' ' + (rowIndex + 1) + '</strong>' +
                    '<button type="button" class="action-delete remove-nested-row" title="' + $t('Remove Row') + '">' +
                    '<span>' + $t('Remove Row') + '</span>' +
                    '</button>' +
                    '</div>' +
                    '<div class="nested-row-keyvalue-container" style="margin-left: 10px;">' +
                    '</div>' +
                    '<button type="button" class="action-secondary add-keyvalue-to-row" data-row-id="' + rowId + '" style="margin-top: 5px;">' +
                    '<span>+ ' + $t('Add Key-Value') + '</span>' +
                    '</button>' +
                    '</div>';

                $('#nested-pairs-container').append(rowHtml);
                updateJsonValue();
            };

            // Add key-value pair to a nested row
            var addKeyValueToNestedRow = function($row) {
                var pairId = generateFilePickerId();
                var valueInputId = 'nested-value-' + pairId;
                var $container = $row.find('.nested-row-keyvalue-container');

                var pairHtml = '<div class="nested-row-kv-pair admin__field" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">' +
                    '<div style="flex: 1;">' +
                    '<input type="text" class="admin__control-text nested-row-key" placeholder="' + $t('Key') + '" style="width: 100%;">' +
                    '</div>' +
                    '<div style="flex: 2; display: flex; align-items: center;">' +
                    '<input type="text" id="' + valueInputId + '" class="admin__control-text nested-row-value" placeholder="' + $t('Value') + '" style="flex: 1;">' +
                    createFilePickerIcon(valueInputId) +
                    '</div>' +
                    '<div>' +
                    '<button type="button" class="action-delete remove-nested-row-kv" title="' + $t('Remove') + '">' +
                    '<span>' + $t('Remove') + '</span>' +
                    '</button>' +
                    '</div>' +
                    '</div>';

                $container.append(pairHtml);
                updateJsonValue();
            };

            // Convert key-value pairs to JSON (simple mode)
            var convertKeyValuePairsToJson = function() {
                var jsonObj = {};

                $('.keyvalue-pair-row').each(function() {
                    var key = $(this).find('.keyvalue-key').val();
                    var value = $(this).find('.keyvalue-value').val();

                    if (key && key.trim() !== '') {
                        try {
                            jsonObj[key] = JSON.parse(value);
                        } catch (e) {
                            jsonObj[key] = value;
                        }
                    }
                });

                return JSON.stringify(jsonObj, null, 2);
            };

            // Convert nested rows to JSON (array of objects)
            var convertNestedRowsToJson = function() {
                var jsonArray = [];

                $('#nested-pairs-container .nested-row').each(function() {
                    var $row = $(this);
                    var rowObj = {};

                    $row.find('.nested-row-kv-pair').each(function() {
                        var key = $(this).find('.nested-row-key').val();
                        var value = $(this).find('.nested-row-value').val();

                        if (key && key.trim() !== '') {
                            try {
                                rowObj[key] = JSON.parse(value);
                            } catch (e) {
                                rowObj[key] = value;
                            }
                        }
                    });

                    // Only add row if it has at least one key-value pair
                    if (Object.keys(rowObj).length > 0) {
                        jsonArray.push(rowObj);
                    }
                });

                return JSON.stringify(jsonArray, null, 2);
            };

            // Parse JSON to key-value pairs (simple mode)
            var parseJsonToKeyValuePairs = function(jsonString) {
                try {
                    var jsonObj = JSON.parse(jsonString);
                    var $container = $('#keyvalue-pairs-container');
                    $container.empty();

                    for (var key in jsonObj) {
                        if (jsonObj.hasOwnProperty(key)) {
                            var value = jsonObj[key];
                            if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                                value = JSON.stringify(value);
                            } else if (Array.isArray(value)) {
                                value = JSON.stringify(value);
                            }

                            var pairId = generateFilePickerId();
                            var valueInputId = 'keyvalue-value-' + pairId;
                            var pairHtml = '<div class="keyvalue-pair-row admin__field" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">' +
                                '<div style="flex: 1;">' +
                                '<input type="text" class="admin__control-text keyvalue-key" value="' + escapeHtml(key) + '" placeholder="' + $t('Key') + '" style="width: 100%;">' +
                                '</div>' +
                                '<div style="flex: 2; display: flex; align-items: center;">' +
                                '<input type="text" id="' + valueInputId + '" class="admin__control-text keyvalue-value" value="' + escapeHtml(String(value)) + '" placeholder="' + $t('Value') + '" style="flex: 1;">' +
                                createFilePickerIcon(valueInputId) +
                                '</div>' +
                                '<div>' +
                                '<button type="button" class="action-delete remove-keyvalue-pair" title="' + $t('Remove') + '">' +
                                '<span>' + $t('Remove') + '</span>' +
                                '</button>' +
                                '</div>' +
                                '</div>';

                            $container.append(pairHtml);
                        }
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    $('#keyvalue-pairs-container').empty();
                }
            };

            // Parse JSON array to nested rows
            var parseJsonToNestedRows = function(jsonString) {
                try {
                    var jsonArray = JSON.parse(jsonString);
                    if (!Array.isArray(jsonArray)) {
                        console.error('Nested mode expects an array');
                        $('#nested-pairs-container').empty();
                        return;
                    }

                    var $container = $('#nested-pairs-container');
                    $container.empty();

                    for (var i = 0; i < jsonArray.length; i++) {
                        var rowObj = jsonArray[i];
                        if (typeof rowObj !== 'object' || rowObj === null || Array.isArray(rowObj)) {
                            continue;
                        }

                        var rowId = generateFilePickerId();
                        var rowHtml = '<div class="nested-row admin__field" data-row-index="' + i + '" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;">' +
                            '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' +
                            '<strong style="color: #666;">' + $t('Row') + ' ' + (i + 1) + '</strong>' +
                            '<button type="button" class="action-delete remove-nested-row" title="' + $t('Remove Row') + '">' +
                            '<span>' + $t('Remove Row') + '</span>' +
                            '</button>' +
                            '</div>' +
                            '<div class="nested-row-keyvalue-container" style="margin-left: 10px;">' +
                            '</div>' +
                            '<button type="button" class="action-secondary add-keyvalue-to-row" data-row-id="' + rowId + '" style="margin-top: 5px;">' +
                            '<span>+ ' + $t('Add Key-Value') + '</span>' +
                            '</button>' +
                            '</div>';

                        $container.append(rowHtml);
                        var $row = $container.find('.nested-row').last();
                        var $kvContainer = $row.find('.nested-row-keyvalue-container');

                        // Add key-value pairs to this row
                        for (var key in rowObj) {
                            if (rowObj.hasOwnProperty(key)) {
                                var value = rowObj[key];
                                if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                                    value = JSON.stringify(value);
                                } else if (Array.isArray(value)) {
                                    value = JSON.stringify(value);
                                }

                                var pairId = generateFilePickerId();
                                var valueInputId = 'nested-value-' + pairId;
                                var pairHtml = '<div class="nested-row-kv-pair admin__field" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">' +
                                    '<div style="flex: 1;">' +
                                    '<input type="text" class="admin__control-text nested-row-key" value="' + escapeHtml(key) + '" placeholder="' + $t('Key') + '" style="width: 100%;">' +
                                    '</div>' +
                                    '<div style="flex: 2; display: flex; align-items: center;">' +
                                    '<input type="text" id="' + valueInputId + '" class="admin__control-text nested-row-value" value="' + escapeHtml(String(value)) + '" placeholder="' + $t('Value') + '" style="flex: 1;">' +
                                    createFilePickerIcon(valueInputId) +
                                    '</div>' +
                                    '<div>' +
                                    '<button type="button" class="action-delete remove-nested-row-kv" title="' + $t('Remove') + '">' +
                                    '<span>' + $t('Remove') + '</span>' +
                                    '</button>' +
                                    '</div>' +
                                    '</div>';

                                $kvContainer.append(pairHtml);
                            }
                        }
                    }

                    // Update row indices
                    $('#nested-pairs-container .nested-row').each(function(index) {
                        $(this).attr('data-row-index', index);
                        $(this).find('strong').text($t('Row') + ' ' + (index + 1));
                    });
                } catch (e) {
                    console.error('Error parsing JSON for nested mode:', e);
                    $('#nested-pairs-container').empty();
                }
            };

            // Update JSON value based on current mode
            var updateJsonValue = function() {
                var jsonValue;

                if (currentMode === 'raw') {
                    jsonValue = $('#raw-json-textarea').val();
                } else if (currentMode === 'nested') {
                    jsonValue = convertNestedRowsToJson();
                } else {
                    jsonValue = convertKeyValuePairsToJson();
                }

                // Update json_value textarea
                var $jsonValueTextarea = findJsonValueTextarea();
                if ($jsonValueTextarea && $jsonValueTextarea.length) {
                    $jsonValueTextarea.val(jsonValue);
                    $jsonValueTextarea.trigger('change');
                }
            };

            // Switch to nested mode
            var switchToNestedMode = function() {
                var jsonValue;

                // Get current JSON value
                if (currentMode === 'raw') {
                    jsonValue = $('#raw-json-textarea').val();
                } else if (currentMode === 'keyvalue') {
                    jsonValue = convertKeyValuePairsToJson();
                }

                // Hide other modes
                $('#keyvalue-mode').hide();
                $('#raw-json-mode').hide();
                $('#nested-mode').show();

                // Update button labels
                $('#toggle-nested-mode-btn span').text($t('Switch to Key-Value Mode'));
                $('#toggle-raw-mode-btn span').text($t('Switch to Raw JSON'));

                currentMode = 'nested';

                // Parse and display nested structure
                if (jsonValue && jsonValue.trim() !== '') {
                    try {
                        var parsed = JSON.parse(jsonValue);
                        // Check if it's an array (nested mode) or object (key-value mode)
                        if (Array.isArray(parsed)) {
                            parseJsonToNestedRows(jsonValue);
                        } else {
                            // Convert object to array with single object
                            parseJsonToNestedRows(JSON.stringify([parsed]));
                        }
                    } catch (e) {
                        console.error('Error parsing JSON for nested mode:', e);
                        $('#nested-pairs-container').empty();
                    }
                } else {
                    $('#nested-pairs-container').empty();
                }

                updateJsonValue();
            };

            // Switch to key-value mode
            var switchToKeyValueMode = function() {
                var jsonValue;

                // Get current JSON value
                if (currentMode === 'raw') {
                    jsonValue = $('#raw-json-textarea').val();
                } else if (currentMode === 'nested') {
                    jsonValue = convertNestedRowsToJson();
                }

                // Hide other modes
                $('#nested-mode').hide();
                $('#raw-json-mode').hide();
                $('#keyvalue-mode').show();

                // Update button labels
                $('#toggle-nested-mode-btn span').text($t('Switch to Nested Mode'));
                $('#toggle-raw-mode-btn span').text($t('Switch to Raw JSON'));

                currentMode = 'keyvalue';

                // Parse and display key-value pairs
                if (jsonValue && jsonValue.trim() !== '') {
                    try {
                        var parsed = JSON.parse(jsonValue);
                        // If it's an array, convert first object or empty object
                        if (Array.isArray(parsed) && parsed.length > 0) {
                            parseJsonToKeyValuePairs(JSON.stringify(parsed[0]));
                        } else if (Array.isArray(parsed)) {
                            parseJsonToKeyValuePairs('{}');
                        } else {
                            parseJsonToKeyValuePairs(jsonValue);
                        }
                    } catch (e) {
                        console.error('Error parsing JSON for key-value mode:', e);
                        $('#keyvalue-pairs-container').empty();
                    }
                } else {
                    $('#keyvalue-pairs-container').empty();
                }

                updateJsonValue();
            };

            // Toggle raw mode
            var toggleRawMode = function() {
                var jsonValue;

                // Get current JSON value
                if (currentMode === 'keyvalue') {
                    jsonValue = convertKeyValuePairsToJson();
                } else if (currentMode === 'nested') {
                    jsonValue = convertNestedRowsToJson();
                } else {
                    jsonValue = $('#raw-json-textarea').val();
                }

                if (currentMode === 'raw') {
                    // Switching from raw to key-value mode
                    switchToKeyValueMode();
                } else {
                    // Switching to raw mode
                    $('#keyvalue-mode').hide();
                    $('#nested-mode').hide();
                    $('#raw-json-mode').show();
                    $('#raw-json-textarea').val(jsonValue);

                    // Update button labels
                    $('#toggle-nested-mode-btn span').text($t('Switch to Nested Mode'));
                    $('#toggle-raw-mode-btn span').text($t('Switch to Key-Value Mode'));

                    currentMode = 'raw';
                    updateJsonValue();
                }
            };

            // Event listeners
            $(document).on('click', '#toggle-nested-mode-btn', function() {
                if (currentMode === 'nested') {
                    switchToKeyValueMode();
                } else {
                    switchToNestedMode();
                }
            });

            $(document).on('click', '#toggle-raw-mode-btn', function() {
                toggleRawMode();
            });

            $(document).on('click', '#add-keyvalue-pair-btn', function() {
                addKeyValuePair();
            });

            $(document).on('click', '#add-nested-pair-btn', function() {
                addNestedRow();
            });

            $(document).on('click', '.add-keyvalue-to-row', function() {
                var $row = $(this).closest('.nested-row');
                addKeyValueToNestedRow($row);
            });

            $(document).on('click', '.remove-keyvalue-pair', function() {
                $(this).closest('.keyvalue-pair-row').remove();
                updateJsonValue();
            });

            $(document).on('click', '.remove-nested-row', function() {
                $(this).closest('.nested-row').remove();
                // Update row indices
                $('#nested-pairs-container .nested-row').each(function(index) {
                    $(this).attr('data-row-index', index);
                    $(this).find('strong').text($t('Row') + ' ' + (index + 1));
                });
                updateJsonValue();
            });

            $(document).on('click', '.remove-nested-row-kv', function() {
                $(this).closest('.nested-row-kv-pair').remove();
                updateJsonValue();
            });

            $(document).on('click', '.file-picker-btn', function() {
                var targetId = $(this).data('target-id');
                openMediaBrowser(targetId);
            });

            $(document).on('input change', '.keyvalue-pair-row input, .nested-row input', function() {
                updateJsonValue();
            });

            $(document).on('input change', '#raw-json-textarea', function() {
                updateJsonValue();
            });

            // Move JSON editor and initialize - try multiple times
            var initAttempts = 0;
            var maxAttempts = 10;
            var initInterval = setInterval(function() {
                initAttempts++;

                moveJsonEditor();

                var $jsonValueTextarea = findJsonValueTextarea();
                var $jsonEditorContainer = $('#json-editor-container');

                if ($jsonValueTextarea && $jsonValueTextarea.length && $jsonEditorContainer.length) {
                    clearInterval(initInterval);

                    showJsonEditor();

                    // Load existing JSON data
                    var existingValue = $jsonValueTextarea.val();
                    if (existingValue && existingValue.trim() !== '') {
                        try {
                            var jsonObj = JSON.parse(existingValue);
                            // Check if it's an array (nested mode) or object (key-value mode)
                            if (Array.isArray(jsonObj)) {
                                currentMode = 'nested';
                                $('#keyvalue-mode').hide();
                                $('#raw-json-mode').hide();
                                $('#nested-mode').show();
                                $('#toggle-nested-mode-btn span').text($t('Switch to Key-Value Mode'));
                                parseJsonToNestedRows(existingValue);
                            } else {
                                parseJsonToKeyValuePairs(existingValue);
                            }
                        } catch (e) {
                            // If not valid JSON, show in raw mode
                            currentMode = 'raw';
                            $('#raw-json-textarea').val(existingValue);
                            $('#keyvalue-mode').hide();
                            $('#nested-mode').hide();
                            $('#raw-json-mode').show();
                            $('#toggle-raw-mode-btn span').text($t('Switch to Key-Value Mode'));
                        }
                    }
                } else if (initAttempts >= maxAttempts) {
                    clearInterval(initInterval);
                    console.error('Could not initialize JSON editor after', maxAttempts, 'attempts');
                }
            }, 200);
        };

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    };

    return initJsonEditor;
});
