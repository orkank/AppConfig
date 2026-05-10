var config = {
    map: {
        '*': {
            'IDangerous_AppConfig/js/json-editor': 'IDangerous_AppConfig/js/json-editor',
            'IDangerous_AppConfig/js/form/element/product-selector': 'IDangerous_AppConfig/js/form/element/product-selector',
            'IDangerous_AppConfig/js/form/element/cms-selector': 'IDangerous_AppConfig/js/form/element/cms-selector',
            'Magento_Backend/js/media-uploader': 'IDangerous_AppConfig/js/backend/media-uploader'
        }
    },
    config: {
        mixins: {
            'Magento_Ui/js/form/element/file-uploader': {
                'IDangerous_AppConfig/js/form/element/file-uploader-mixin': true
            }
        }
    }
};
