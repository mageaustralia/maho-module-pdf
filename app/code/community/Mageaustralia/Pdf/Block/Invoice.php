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
     * @param Mage_Sales_Model_Order_Address|false|null $address
     * @return string[] one address line per element
     *
     * Accepts false because that is what the order actually returns. Both
     * Mage_Sales_Model_Order::getShippingAddress() and getBillingAddress() walk the
     * address collection and "return false" when there is no match - they do not
     * return null. A virtual order has no shipping address, so passing that straight
     * in threw a TypeError against the previous ?Mage_Sales_Model_Order_Address hint.
     *
     * That mattered more than a log line: the invoice PDF is rendered by an observer
     * on email send, so the throw aborted the queued email for every virtual-only
     * order instead of just omitting an address block.
     */
    public function getAddressLines(Mage_Sales_Model_Order_Address|false|null $address): array
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

    /**
     * Whether to show the shipping-detail rows (Authority to Leave / Shipping Note).
     * atl_required is set by the TW checkout/Shippit on shippable orders; it is null on
     * installs/orders that do not use it, so those see no extra rows.
     */
    public function hasShippingDetails(): bool
    {
        return $this->getOrder()->getData('atl_required') !== null;
    }

    /**
     * Order date without time (matches the legacy invoice; avoids locale time glyphs
     * the PDF font cannot render).
     */
    public function getOrderDate(): string
    {
        return (string) Mage::helper('core')->formatDate(
            $this->getOrder()->getCreatedAt(),
            Mage_Core_Model_Locale::FORMAT_TYPE_LONG,
            false,
        );
    }

    /**
     * Authority to leave, from the Starshipit note that actually records the shopper's
     * choice at checkout.
     *
     * Three fields look like they hold this and only one does. sales_flat_order.atl_required
     * and shippit_authority_to_leave are both dead on this install — atl_required is 0 on
     * every order and shippit_authority_to_leave is NULL on every order — so reading either
     * printed "No" for everyone, including shoppers who had asked for authority to leave and
     * left delivery instructions saying so. The live value is shipnote_note.authority_to_leave,
     * which is also mutually exclusive with signature_required.
     *
     * The two order columns remain as a fallback for orders with no note record.
     */
    public function getAuthorityToLeave(): string
    {
        $note = $this->getShipNote();
        if ($note) {
            return $note->getAuthorityToLeave() ? 'Yes' : 'No';
        }

        $order = $this->getOrder();
        return ($order->getShippitAuthorityToLeave() ?? $order->getAtlRequired()) ? 'Yes' : 'No';
    }

    /**
     * The Starshipit note for this order, or null when the module or the record is absent.
     *
     * @return Rvtech_Starshipit_Model_Note|null
     */
    private function getShipNote()
    {
        $noteModel = Mage::getModel('shipnote/note');
        if (!$noteModel) {
            return null;
        }

        try {
            $note = $noteModel->loadByOrder($this->getOrder());
        } catch (\Throwable $e) {
            return null; // note module/data absent
        }

        return ($note && $note->getId()) ? $note : null;
    }

    /**
     * Delivery instructions from the Starshipit note record (shipnote/note) when present.
     */
    public function getShippingNote(): string
    {
        $note = $this->getShipNote();
        if ($note && $note->getDeliveryInstructions()) {
            return (string) $note->getDeliveryInstructions();
        }

        return (string) ($this->getOrder()->getShippitDeliveryInstructions() ?? '');
    }
}
