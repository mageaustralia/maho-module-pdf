<?php

declare(strict_types=1);

/**
 * Mageaustralia_Pdf
 *
 * @copyright  Copyright (c) 2026 Mage Australia (https://mageaustralia.com.au)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mageaustralia_Pdf_Model_Observer
{
    public const MARKER_PATTERN = '/\{\{attach_invoice\(([^)]+)\)\}\}/';

    /**
     * email_template_send_before: attach an invoice PDF for each {{attach_invoice(<inc>)}}
     * marker in the body, then strip the markers. Never blocks the email.
     */
    public function attachOnEmailSend(Varien_Event_Observer $observer): void
    {
        $mail = $observer->getEvent()->getData('mail');
        if (!$mail instanceof \Symfony\Component\Mime\Email) {
            return;
        }

        $body = (string) $mail->getHtmlBody();
        if (strpos($body, 'attach_invoice') === false) {
            return;
        }
        if (!preg_match_all(self::MARKER_PATTERN, $body, $matches)) {
            return;
        }

        $helper = Mage::helper('mageaustralia_pdf');
        foreach (array_unique($matches[1]) as $rawIncrement) {
            $increment = trim($rawIncrement);
            if (!$helper->isEnabled()) {
                continue;
            }
            try {
                $order = Mage::getModel('sales/order')->loadByIncrementId($increment);
                if (!$order->getId()) {
                    Mage::log('attach_invoice: order not found ' . $increment, Mage::LOG_WARNING, 'mageaustralia_pdf.log');
                    continue;
                }
                $pdf = Mage::helper('mageaustralia_pdf/pdf')->renderInvoice($order);
                if ($pdf !== '') {
                    $mail->attach($pdf, 'invoice_' . $increment . '.pdf', 'application/pdf');
                }
            } catch (\Throwable $e) {
                Mage::log('attach_invoice render failed for ' . $increment . ': ' . $e->getMessage(), Mage::LOG_ERR, 'mageaustralia_pdf.log');
            }
        }

        $clean = preg_replace(self::MARKER_PATTERN, '', $body);
        $mail->html((string) $clean);
    }
}
