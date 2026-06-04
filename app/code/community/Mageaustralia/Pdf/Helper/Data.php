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

    public function getStoreName(?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig('mageaustralia_pdf/general/store_name', $storeId);
    }

    public function getStoreAddress(?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig('mageaustralia_pdf/general/store_address', $storeId);
    }

    public function getAbn(?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig('mageaustralia_pdf/general/abn', $storeId);
    }

    public function getBankDetails(?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig('mageaustralia_pdf/general/bank_details', $storeId);
    }

    /**
     * Absolute filesystem path to the configured logo, or '' if none/unreadable.
     */
    public function getLogoPath(?int $storeId = null): string
    {
        $file = (string) Mage::getStoreConfig('mageaustralia_pdf/general/logo', $storeId);
        if ($file === '') {
            return '';
        }
        $path = Mage::getBaseDir('media') . DS . 'mageaustralia' . DS . 'pdf' . DS . $file;
        return is_readable($path) ? $path : '';
    }
}
