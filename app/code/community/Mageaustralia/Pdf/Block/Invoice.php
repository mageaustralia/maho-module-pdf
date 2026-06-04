<?php

declare(strict_types=1);

/**
 * Mageaustralia_Pdf
 *
 * @copyright  Copyright (c) 2026 Mage Australia (https://mageaustralia.com.au)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mageaustralia_Pdf_Block_Invoice extends Mage_Core_Block_Template
{
    public function getOrder(): Mage_Sales_Model_Order
    {
        return $this->getData('order');
    }

    public function pdfHelper(): Mageaustralia_Pdf_Helper_Data
    {
        return Mage::helper('mageaustralia_pdf');
    }

    public function getStoreId(): int
    {
        return (int) $this->getOrder()->getStoreId();
    }

    /**
     * Visible, non-child order items.
     *
     * @return Mage_Sales_Model_Order_Item[]
     */
    public function getItems(): array
    {
        $items = [];
        foreach ($this->getOrder()->getAllVisibleItems() as $item) {
            $items[] = $item;
        }
        return $items;
    }

    public function formatPrice(float $value): string
    {
        return $this->getOrder()->formatPriceTxt($value);
    }

    /**
     * Data URI for the configured logo so Dompdf renders it without HTTP/path access.
     */
    public function getLogoDataUri(): string
    {
        $path = $this->pdfHelper()->getLogoPath($this->getStoreId());
        if ($path === '') {
            return '';
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            return '';
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /**
     * @return string[] one address line per element
     */
    public function getAddressLines(?Mage_Sales_Model_Order_Address $address): array
    {
        if (!$address) {
            return [];
        }
        $lines = [];
        if ($address->getCompany()) {
            $lines[] = (string) $address->getCompany();
        }
        $lines[] = trim($address->getFirstname() . ' ' . $address->getLastname());
        foreach ((array) $address->getStreet() as $street) {
            if (trim((string) $street) !== '') {
                $lines[] = (string) $street;
            }
        }
        $cityLine = trim($address->getCity() . ' ' . $address->getRegion() . ' ' . $address->getPostcode());
        if ($cityLine !== '') {
            $lines[] = $cityLine;
        }
        return $lines;
    }

    /**
     * Payment title (method label) + masked card last-4 when available.
     */
    public function getPaymentLabel(): string
    {
        $payment = $this->getOrder()->getPayment();
        if (!$payment) {
            return '';
        }
        try {
            $title = (string) $payment->getMethodInstance()->getTitle();
        } catch (\Throwable $e) {
            $title = (string) $payment->getMethod();
        }
        $last4 = $payment->getCcLast4();
        if ($last4) {
            $title .= ' (Last 4): ' . $last4;
        }
        return $title;
    }
}
