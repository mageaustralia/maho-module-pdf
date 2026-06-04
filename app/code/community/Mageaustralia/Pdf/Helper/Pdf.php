<?php

declare(strict_types=1);

/**
 * Mageaustralia_Pdf
 *
 * @copyright  Copyright (c) 2026 Mage Australia (https://mageaustralia.com.au)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mageaustralia_Pdf_Helper_Pdf extends Mage_Core_Helper_Abstract
{
    /**
     * Render the invoice/receipt for an order as PDF bytes. Returns '' on failure.
     */
    public function renderInvoice(Mage_Sales_Model_Order $order): string
    {
        $html = $this->renderHtml($order);
        if ($html === '') {
            return '';
        }
        $dompdf = new \Dompdf\Dompdf([
            'defaultFont'    => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'chroot'         => Mage::getBaseDir('media'),
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return (string) $dompdf->output();
    }

    /**
     * Render the invoice phtml to HTML under the frontend/base/default design,
     * restoring the previous design afterwards so we never leak area state.
     */
    public function renderHtml(Mage_Sales_Model_Order $order): string
    {
        $design  = Mage::getDesign();
        $area    = $design->getArea();
        $package = $design->getPackageName();
        $theme   = $design->getTheme('template');

        try {
            $design->setArea('frontend')->setPackageName('base')->setTheme('default');
            /** @var Mageaustralia_Pdf_Block_Invoice $block */
            $block = Mage::app()->getLayout()->createBlock('mageaustralia_pdf/invoice');
            $block->setOrder($order);
            $block->setTemplate('mageaustralia/pdf/invoice.phtml');
            return (string) $block->toHtml();
        } finally {
            $design->setArea($area)->setPackageName($package)->setTheme($theme);
        }
    }
}
