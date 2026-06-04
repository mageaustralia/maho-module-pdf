<?php

declare(strict_types=1);

/**
 * Mageaustralia_Pdf
 *
 * @copyright  Copyright (c) 2026 Mage Australia (https://mageaustralia.com.au)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mageaustralia_Pdf_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) Mage::getStoreConfig('mageaustralia_pdf/general/enabled', $storeId);
    }
}
