<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Ui\DataProvider\KeyValue;

use IDangerous\AppConfig\Model\ResourceModel\KeyValue\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class ListingDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->collection->joinGroup();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }
}


