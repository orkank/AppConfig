<?php
namespace IDangerous\AppConfig\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class TruncatedValue extends Column
{
    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['value'])) {
                    // Strip tags
                    $value = strip_tags($item['value']);
                    // Truncate
                    if (mb_strlen($value) > 50) {
                        $value = mb_substr($value, 0, 50) . '...';
                    }
                    $item['value'] = $value;
                }
            }
        }
        return $dataSource;
    }
}
