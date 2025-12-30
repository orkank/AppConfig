<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Source;

use IDangerous\AppConfig\Model\ResourceModel\Group\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class Group implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [['value' => '', 'label' => __('-- Please Select --')]];

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);

        foreach ($collection as $group) {
            $options[] = [
                'value' => $group->getId(),
                'label' => $group->getName() . ' (' . $group->getCode() . ')'
            ];
        }

        return $options;
    }
}


