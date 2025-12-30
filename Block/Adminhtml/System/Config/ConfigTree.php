<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Block\Adminhtml\System\Config;

use IDangerous\AppConfig\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use IDangerous\AppConfig\Model\ResourceModel\KeyValue\CollectionFactory as KeyValueCollectionFactory;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;

class ConfigTree extends Field
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var GroupCollectionFactory
     */
    protected $groupCollectionFactory;

    /**
     * @var KeyValueCollectionFactory
     */
    protected $keyValueCollectionFactory;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param UrlInterface $urlBuilder
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param KeyValueCollectionFactory $keyValueCollectionFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        UrlInterface $urlBuilder,
        GroupCollectionFactory $groupCollectionFactory,
        KeyValueCollectionFactory $keyValueCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->urlBuilder = $urlBuilder;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->keyValueCollectionFactory = $keyValueCollectionFactory;
    }

    /**
     * Render configuration tree
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $groups = $this->groupCollectionFactory->create()
            ->setOrder('name', 'ASC')
            ->getItems();

        $keyValues = $this->keyValueCollectionFactory->create()
            ->setOrder('key_name', 'ASC')
            ->getItems();

        // Group key-values by group_id
        $keyValuesByGroup = [];
        $keyValuesWithoutGroup = [];

        foreach ($keyValues as $keyValue) {
            $groupId = $keyValue->getGroupId();
            if ($groupId) {
                if (!isset($keyValuesByGroup[$groupId])) {
                    $keyValuesByGroup[$groupId] = [];
                }
                $keyValuesByGroup[$groupId][] = $keyValue;
            } else {
                $keyValuesWithoutGroup[] = $keyValue;
            }
        }

        $html = '<div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-top: 10px; max-height: 600px; overflow-y: auto;">';
        $html .= '<h3 style="margin-top: 0; margin-bottom: 15px;">Configuration Tree</h3>';

        // Render groups with their key-values
        foreach ($groups as $group) {
            $groupId = $group->getId();
            $isActive = $group->getIsActive() ? 'Yes' : 'No';
            $version = $group->getVersion() ?: 'None';

            $groupEditUrl = $this->urlBuilder->getUrl(
                'appconfig/group/edit',
                ['group_id' => $groupId]
            );

            $html .= '<div style="margin-bottom: 15px; border-left: 3px solid #007bff; padding-left: 10px;">';
            $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">';
            $html .= '<div>';
            $html .= '<strong style="font-size: 14px; color: #007bff;">📁 ' . $this->escapeHtml($group->getName()) . '</strong>';
            $html .= '<span style="color: #666; font-size: 12px; margin-left: 10px;">(' . $this->escapeHtml($group->getCode()) . ')</span>';
            $html .= '</div>';
            $html .= '<a href="' . $groupEditUrl . '" target="_blank" style="background: #007bff; color: #fff; padding: 5px 12px; text-decoration: none; border-radius: 3px; font-size: 12px;">Edit</a>';
            $html .= '</div>';

            $html .= '<div style="margin-left: 20px; color: #666; font-size: 12px; margin-bottom: 8px;">';
            $html .= '<span>Active: ' . $isActive . '</span>';
            $html .= '<span style="margin-left: 15px;">Version: ' . $this->escapeHtml($version) . '</span>';
            if ($group->getDescription()) {
                $html .= '<div style="margin-top: 5px; font-style: italic;">' . $this->escapeHtml($group->getDescription()) . '</div>';
            }
            $html .= '</div>';

            // Render key-values for this group
            if (isset($keyValuesByGroup[$groupId]) && count($keyValuesByGroup[$groupId]) > 0) {
                $html .= '<div style="margin-left: 20px; margin-top: 8px;">';
                foreach ($keyValuesByGroup[$groupId] as $keyValue) {
                    $keyValueEditUrl = $this->urlBuilder->getUrl(
                        'appconfig/keyvalue/edit',
                        ['keyvalue_id' => $keyValue->getId()]
                    );

                    $keyValueIsActive = $keyValue->getIsActive() ? 'Yes' : 'No';
                    $keyValueVersion = $keyValue->getVersion() ?: 'None';
                    $valueType = $keyValue->getValueType();
                    $valuePreview = $this->getValuePreview($keyValue);

                    $html .= '<div style="margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-left: 2px solid #28a745; border-radius: 3px;">';
                    $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">';
                    $html .= '<div>';
                    $html .= '<strong style="color: #28a745;">🔑 ' . $this->escapeHtml($keyValue->getKeyName()) . '</strong>';
                    if ($keyValue->getName()) {
                        $html .= '<span style="color: #666; font-size: 11px; margin-left: 8px; font-style: italic;">(' . $this->escapeHtml($keyValue->getName()) . ')</span>';
                    }
                    $html .= '<span style="color: #666; font-size: 11px; margin-left: 8px;">[' . $valueType . ']</span>';
                    $html .= '</div>';
                    $html .= '<a href="' . $keyValueEditUrl . '" target="_blank" style="background: #28a745; color: #fff; padding: 4px 10px; text-decoration: none; border-radius: 3px; font-size: 11px;">Edit</a>';
                    $html .= '</div>';

                    $html .= '<div style="margin-left: 10px; font-size: 11px; color: #666;">';
                    $html .= '<div>Value: <code style="background: #fff; padding: 2px 6px; border-radius: 2px;">' . $this->escapeHtml($valuePreview) . '</code></div>';
                    $html .= '<div style="margin-top: 3px;">';
                    $html .= '<span>Active: ' . $keyValueIsActive . '</span>';
                    $html .= '<span style="margin-left: 10px;">Version: ' . $this->escapeHtml($keyValueVersion) . '</span>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            } else {
                $html .= '<div style="margin-left: 20px; color: #999; font-size: 12px; font-style: italic;">No key-value pairs</div>';
            }

            $html .= '</div>';
        }

        // Render key-values without group
        if (count($keyValuesWithoutGroup) > 0) {
            $html .= '<div style="margin-top: 20px; margin-bottom: 15px; border-left: 3px solid #ffc107; padding-left: 10px;">';
            $html .= '<div style="margin-bottom: 8px;">';
            $html .= '<strong style="font-size: 14px; color: #ffc107;">📋 Key-Value Pairs (No Group)</strong>';
            $html .= '</div>';

            $html .= '<div style="margin-left: 20px; margin-top: 8px;">';
            foreach ($keyValuesWithoutGroup as $keyValue) {
                $keyValueEditUrl = $this->urlBuilder->getUrl(
                    'appconfig/keyvalue/edit',
                    ['keyvalue_id' => $keyValue->getId()]
                );

                $keyValueIsActive = $keyValue->getIsActive() ? 'Yes' : 'No';
                $keyValueVersion = $keyValue->getVersion() ?: 'None';
                $valueType = $keyValue->getValueType();
                $valuePreview = $this->getValuePreview($keyValue);

                $html .= '<div style="margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-left: 2px solid #ffc107; border-radius: 3px;">';
                $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">';
                $html .= '<div>';
                $html .= '<strong style="color: #ffc107;">🔑 ' . $this->escapeHtml($keyValue->getKeyName()) . '</strong>';
                if ($keyValue->getName()) {
                    $html .= '<span style="color: #666; font-size: 11px; margin-left: 8px; font-style: italic;">(' . $this->escapeHtml($keyValue->getName()) . ')</span>';
                }
                $html .= '<span style="color: #666; font-size: 11px; margin-left: 8px;">[' . $valueType . ']</span>';
                $html .= '</div>';
                $html .= '<a href="' . $keyValueEditUrl . '" target="_blank" style="background: #ffc107; color: #000; padding: 4px 10px; text-decoration: none; border-radius: 3px; font-size: 11px;">Edit</a>';
                $html .= '</div>';

                $html .= '<div style="margin-left: 10px; font-size: 11px; color: #666;">';
                $html .= '<div>Value: <code style="background: #fff; padding: 2px 6px; border-radius: 2px;">' . $this->escapeHtml($valuePreview) . '</code></div>';
                $html .= '<div style="margin-top: 3px;">';
                $html .= '<span>Active: ' . $keyValueIsActive . '</span>';
                $html .= '<span style="margin-left: 10px;">Version: ' . $this->escapeHtml($keyValueVersion) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        // Show message if no data
        if (count($groups) === 0 && count($keyValues) === 0) {
            $html .= '<div style="text-align: center; padding: 40px; color: #999;">';
            $html .= '<p>No configuration data found.</p>';
            $html .= '<p style="margin-top: 10px;">';
            $html .= '<a href="' . $this->urlBuilder->getUrl('appconfig/group/index') . '" style="color: #007bff;">Create a group</a> or ';
            $html .= '<a href="' . $this->urlBuilder->getUrl('appconfig/keyvalue/index') . '" style="color: #007bff;">add a key-value pair</a>';
            $html .= '</p>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get value preview for display
     *
     * @param \IDangerous\AppConfig\Model\KeyValue $keyValue
     * @return string
     */
    protected function getValuePreview($keyValue)
    {
        $previews = [];

        // Text value
        $textValue = $keyValue->getTextValue();
        if ($textValue) {
            $preview = mb_substr($textValue, 0, 50);
            if (mb_strlen($textValue) > 50) {
                $preview .= '...';
            }
            $previews[] = 'Text: ' . $preview;
        }

        // File value
        $filePath = $keyValue->getFilePath();
        if ($filePath) {
            $previews[] = 'File: ' . basename($filePath);
        }

        // JSON value
        $jsonValue = $keyValue->getJsonValue();
        if ($jsonValue) {
            $decoded = json_decode($jsonValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $jsonStr = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $preview = mb_substr($jsonStr, 0, 50);
                if (mb_strlen($jsonStr) > 50) {
                    $preview .= '...';
                }
                $previews[] = 'JSON: ' . $preview;
            } else {
                $preview = mb_substr($jsonValue, 0, 50);
                if (mb_strlen($jsonValue) > 50) {
                    $preview .= '...';
                }
                $previews[] = 'JSON: ' . $preview;
            }
        }

        // Products value
        $productsValue = $keyValue->getProductsValue();
        if ($productsValue) {
            $decoded = json_decode($productsValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $count = count($decoded);
                $previews[] = 'Products: ' . $count . ' product(s)';
            }
        }

        // Categories value
        $categoriesValue = $keyValue->getCategoriesValue();
        if ($categoriesValue) {
            $decoded = json_decode($categoriesValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $count = count($decoded);
                $previews[] = 'Categories: ' . $count . ' category(ies)';
            }
        }

        // Fallback to old value_type based logic for backward compatibility
        if (empty($previews)) {
            $valueType = $keyValue->getValueType();
            $value = $keyValue->getValue();

            switch ($valueType) {
                case 'file':
                    if ($filePath) {
                        return basename($filePath);
                    }
                    return 'No file';

                case 'json':
                    if ($value) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $jsonStr = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $preview = mb_substr($jsonStr, 0, 50);
                            if (mb_strlen($jsonStr) > 50) {
                                $preview .= '...';
                            }
                            return $preview;
                        }
                        return mb_substr($value, 0, 50) . (mb_strlen($value) > 50 ? '...' : '');
                    }
                    return 'Empty';

                case 'products':
                case 'category':
                    if ($value) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $count = count($decoded);
                            return $valueType === 'products' ? $count . ' product(s)' : $count . ' category(ies)';
                        }
                    }
                    return 'Empty';

                case 'text':
                default:
                    if ($value) {
                        $preview = mb_substr($value, 0, 100);
                        if (mb_strlen($value) > 100) {
                            $preview .= '...';
                        }
                        return $preview;
                    }
                    return 'Empty';
            }
        }

        return implode(' | ', $previews);
    }
}
