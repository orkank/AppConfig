<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Ui\DataProvider\KeyValue;

use IDangerous\AppConfig\Model\Headless\Origin;
use IDangerous\AppConfig\Model\ListOriginScope;
use IDangerous\AppConfig\Model\ResourceModel\KeyValue\CollectionFactory;
use Magento\Backend\Model\Session;
use Magento\Ui\DataProvider\AbstractDataProvider;

class ListingDataProvider extends AbstractDataProvider
{
    /** @var Session */
    protected $backendSession;

    /** @SuppressWarnings(PHPMD.ExcessiveParameterList) */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        Session $backendSession,
        array $meta = [],
        array $data = []
    ) {
        $this->backendSession = $backendSession;

        $this->collection = $collectionFactory->create();
        $this->collection->joinGroup();

        switch ($this->backendSession->getData(ListOriginScope::SESSION_KEY) ?: ListOriginScope::ADMIN_ONLY) {
            case ListOriginScope::HEADLESS_ONLY:
                $this->collection->addFieldToFilter('main_table.origin', ['eq' => Origin::HEADLESS]);
                break;
            case ListOriginScope::ALL:
                break;
            default:
                $this->collection->addFieldToFilter('main_table.origin', ['eq' => Origin::ADMIN]);
        }

        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }
}
