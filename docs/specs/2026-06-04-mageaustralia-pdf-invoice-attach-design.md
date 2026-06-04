# Mageaustralia_Pdf - Invoice PDF email attachment (design)

**Status:** Approved (design)
**Date:** 2026-06-04
**Author:** brainstormed with Matthew Campbell

## Goal

Attach a TW-branded **tax invoice / receipt PDF** to order/invoice transactional
emails on Maho, triggered by a `{{attach_invoice(<orderIncrement>)}}` marker
placed in the email template body - replicating the behaviour of the legacy
Moogento Pickpack `attach_invoice` directive, but as a clean, self-contained
`Mageaustralia_Pdf` module (NOT a port of Moogento's coordinate-drawn element
engine).

Reference target: `invoice_pdf/invoice_298579.pdf` (legacy output). Fidelity goal
is **visually equivalent** - same content and general layout, rebuilt cleanly -
not pixel-identical.

## Decisions (locked)

| Decision | Choice | Rationale |
|---|---|---|
| Module | New `Mageaustralia_Pdf` | Clean, reusable; avoid Moogento's tangled code |
| PDF engine | **Dompdf** (HTML/CSS → PDF) | "Visually equivalent" becomes a styled web page; trivial to restyle/maintain; the opposite of coordinate drawing |
| Trigger | `{{attach_invoice(<increment>)}}` marker | Existing email templates work unchanged |
| Hook | Observer on `email_template_send_before` | Maho fires it with the `Symfony\Component\Mime\Email` object - attach with no email-class rewrite |
| Fidelity | Visually equivalent | Lower effort, maintainable |
| Branding | Config-driven (`system.xml`) | Reusable module; no TW values hardcoded |
| Scope v1 | `attach_invoice` only | YAGNI; `attach_packingsheet` is a clean future addition |

## Architecture & data flow

Maho `Mage_Core_Model_Email_Template::send()` builds a `Symfony\Component\Mime\Email`
and, immediately before `$mailer->send($email)`, dispatches:

```php
Mage::dispatchEvent('email_template_send_before', [
    'mail'      => $email,      // Symfony Email - supports ->attach($body, $name, $contentType)
    'template'  => $this,
    'variables' => $variables,
]);
```

Flow:

1. An order/invoice email is sent whose body contains `{{attach_invoice(700000004)}}`.
2. `Mageaustralia_Pdf_Model_Observer::attachOnEmailSend()` fires on
   `email_template_send_before`.
3. It scans the email's HTML body with `/\{\{attach_invoice\(([^)]+)\)\}\}/` and
   collects the order increment IDs.
4. For each: load the order, render the invoice PDF (see Helper/Pdf), and
   `$email->attach($pdfBytes, "invoice_{$increment}.pdf", "application/pdf")`.
5. Replace each marker in the body with an empty string and set the cleaned body
   back on the `Email` (`$email->html($cleanBody)`), so the marker never appears
   to the customer.
6. Errors are caught per-marker and logged (`mageaustralia_pdf.log`); a failed PDF
   must NEVER block the email from sending.

Note: the marker is treated as a plain string, not a registered Magento template
directive (this matches how the legacy Moogento implementation scanned for it).
The implementation must confirm the marker survives the email template filter to
`email_template_send_before`; if the filter strips unknown `{{...}}` tokens,
register a no-op `attachInvoiceDirective()` on the template filter that returns the
marker text verbatim so it reaches the observer.

## Components

| File | Responsibility | Depends on |
|---|---|---|
| `etc/config.xml` | module decl, `email_template_send_before` observer, Dompdf autoload, helper/model/block groups | - |
| `etc/system.xml` + `etc/adminhtml.xml` | config section `mageaustralia_pdf` (enable, logo, store name/ABN/address/contact, bank details) + ACL | - |
| `Helper/Data.php` | config getters (`isEnabled`, `getLogo`, `getAbn`, `getBankDetails`, ...) | - |
| `Helper/Pdf.php` | `renderInvoice(Mage_Sales_Model_Order $order): string` - build view block, render phtml to HTML, run Dompdf, return PDF bytes | Dompdf, Block, template |
| `Block/Invoice.php` | view-model for the template: exposes order, address, item, totals, and branding accessors | Helper/Data |
| `design/adminhtml/.../template/mageaustralia/pdf/invoice.phtml` | the HTML/CSS invoice/receipt layout | Block/Invoice |
| `Model/Observer.php` | `attachOnEmailSend(Observer)` - scan markers, attach PDFs, strip markers | Helper/Pdf |

Each unit is independently testable: `Helper/Pdf::renderInvoice()` is pure
(order → bytes), the observer is the only piece touching the Symfony Email, and
the phtml is the isolated visual layer.

## PDF template content (visually equivalent to the example)

- **Header:** logo (left) + store name/address/contact + ABN (config).
- **Title row:** `TAX INVOICE/RECEIPT` + `Order #<increment>` + order date.
- **Addresses:** Billing (left) and Shipping (right) blocks.
- **Meta block:** Shipping Type, Authority to Leave, Shipping Note, Payment
  (method + masked card where present) - pulled from order data, rendered only
  when present.
- **Line items table:** Qty · Items · Price · GST · Total.
- **Totals:** Subtotal · Shipping · GST · Grand Total.
- **Footer:** Bank account details (config).

All currency/tax via the order's store formatting. Branding strings come from
`Helper/Data` (config), never hardcoded - so the module is reusable across stores.

## Dependency

`composer require dompdf/dompdf` (MIT, pure-PHP, no system libs). Registered via
the module's composer `autoload` / Maho autoloader so it is available at runtime.

## Module location

Built and tested on dev under `app/code/community/Mageaustralia/Pdf/` (or
`app/code/local/`), then productized to a `maho-module-pdf` repo (matching the
other `mageaustralia/maho-module-*` modules) once verified.

## Error handling

- PDF render/attach wrapped in try/catch per marker; failures logged, email still
  sends without the attachment.
- Missing/inactive config (`isEnabled() == false`) → observer no-ops (markers
  still stripped so they don't leak into the email).
- Unknown/invalid order increment in a marker → logged + skipped.

## Testing

Dev harness (CLI, bootstrap `vendor/autoload.php`):

1. `renderInvoice($order)` for a known order → assert output begins with `%PDF`
   and is non-trivial in size; spot-check it contains the order increment.
2. Simulate `email_template_send_before` with a body containing
   `{{attach_invoice(<increment>)}}` → assert the resulting Symfony `Email` has
   exactly one `application/pdf` attachment named `invoice_<increment>.pdf` and the
   marker no longer appears in the body.
3. Disabled config → assert no attachment and marker stripped.
4. Invalid increment → assert email still sends, no attachment, error logged.

## Out of scope (v1)

- `attach_packingsheet` (future: same observer + a second renderer/template).
- Credit-memo / shipment PDFs.
- Multi-PDF combination, QR codes, barcodes, courier rules (Moogento extras).
