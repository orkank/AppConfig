<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Headless;

use Magento\Backend\App\Action;

abstract class AbstractHeadless extends Action
{
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::headless_integration';
}
