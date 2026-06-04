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
    /**
     * Matches both the bare marker {{attach_invoice}} and the explicit form
     * {{attach_invoice(<orderIncrement>)}}. Capture group 1 is the increment (empty
     * for the bare form).
     */
    public const MARKER_PATTERN = '/\{\{attach_invoice(?:\(([^)]*)\))?\}\}/';

    /**
     * Upper bound on distinct invoices attached to a single email. Markers come from
     * admin-authored templates, but this caps order loads + PDF renders so a malformed
     * template cannot turn one send into an unbounded amount of work.
     */
    public const MAX_ATTACHMENTS = 20;

    /**
     * Hooked on both email_template_send_before (synchronous sends) and
     * email_queue_send_before (queued transactional emails, e.g. the new-order email).
     * Attaches an invoice PDF for each marker, then strips the markers. Never blocks
     * the email: a failed PDF is logged and skipped.
     */
    public function attachOnEmailSend(Varien_Event_Observer $observer): void
    {
        $event = $observer->getEvent();
        $mail = $event->getData('mail');
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

        /** @var array<int, Mage_Sales_Model_Order> $orders keyed by id to de-dupe */
        $orders = [];
        foreach ($matches[1] as $rawIncrement) {
            if (count($orders) >= self::MAX_ATTACHMENTS) {
                Mage::log('attach_invoice: attachment cap reached (' . self::MAX_ATTACHMENTS . '), ignoring remaining markers', Mage::LOG_WARNING, 'mageaustralia_pdf.log');
                break;
            }
            $increment = trim((string) $rawIncrement);
            if ($increment !== '') {
                // Explicit: {{attach_invoice(700000004)}}
                $order = Mage::getModel('sales/order')->loadByIncrementId($increment);
                if (!$order->getId()) {
                    Mage::log('attach_invoice: order not found ' . $increment, Mage::LOG_WARNING, 'mageaustralia_pdf.log');
                    continue;
                }
            } else {
                // Bare: {{attach_invoice}} - resolve the order from the email context.
                $order = $this->_resolveOrder($event);
                if (!$order) {
                    Mage::log('attach_invoice: bare marker but no order in email context', Mage::LOG_WARNING, 'mageaustralia_pdf.log');
                    continue;
                }
            }
            $orders[(int) $order->getId()] = $order;
        }

        $helper = Mage::helper('mageaustralia_pdf');
        foreach ($orders as $order) {
            if (!$helper->isEnabled((int) $order->getStoreId())) {
                continue;
            }
            try {
                $pdf = Mage::helper('mageaustralia_pdf/pdf')->renderInvoice($order);
                if ($pdf !== '') {
                    $mail->attach($pdf, 'invoice_' . $order->getIncrementId() . '.pdf', 'application/pdf');
                }
            } catch (\Throwable $e) {
                Mage::log(
                    'attach_invoice render failed for ' . $order->getIncrementId() . ': ' . $e->getMessage(),
                    Mage::LOG_ERR,
                    'mageaustralia_pdf.log',
                );
            }
        }

        $clean = preg_replace(self::MARKER_PATTERN, '', $body);
        $mail->html((string) $clean);
    }

    /**
     * Resolve the order a bare {{attach_invoice}} marker refers to:
     *  - synchronous send: the template variables carry the order object;
     *  - queued send: the queue message carries the entity (order or invoice).
     */
    protected function _resolveOrder(Varien_Event $event): ?Mage_Sales_Model_Order
    {
        $variables = $event->getData('variables');
        if (is_array($variables) && isset($variables['order']) && $variables['order'] instanceof Mage_Sales_Model_Order) {
            return $variables['order'];
        }

        $message = $event->getData('message');
        if ($message) {
            $entityId = (int) $message->getEntityId();
            $entityType = (string) $message->getEntityType();
            if ($entityId > 0) {
                if ($entityType === 'order') {
                    $order = Mage::getModel('sales/order')->load($entityId);
                    return $order->getId() ? $order : null;
                }
                if ($entityType === 'invoice') {
                    $invoice = Mage::getModel('sales/order_invoice')->load($entityId);
                    return $invoice->getId() ? $invoice->getOrder() : null;
                }
            }
        }

        return null;
    }
}
