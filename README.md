# Mageaustralia_Pdf

Attach a branded tax invoice/receipt PDF to Maho transactional emails by placing an `{{attach_invoice}}` marker in any email template.

## Requirements

- Maho 26.5+
- PHP 8.3+
- `dompdf/dompdf` ^2.0 or ^3.0 (pulled in automatically via Composer)

## Install

```bash
composer require mageaustralia/maho-module-pdf
composer dump-autoload -o
./maho cache:flush
```

## How it works

Place `{{attach_invoice}}` anywhere in a transactional email template body
(e.g. the New Order email). When the email is sent, an observer intercepts
it, renders the order's invoice as HTML then PDF via Dompdf, attaches the
file as `invoice_<order-number>.pdf`, and removes the marker from the body.
The customer never sees the raw marker text.

An explicit form is also supported for edge cases where the order cannot be
inferred from email context: `{{attach_invoice(100000042)}}`.

PDF failures are caught and logged - they never block the email from sending.

## Configure

**System > Configuration > Mage Australia > Invoice PDF**

Set the store name, address, ABN, logo, and bank-details footer. All fields
are scoped to store view, so multi-store setups can have different branding
per store. See `docs/MANUAL.md` for a full field reference.

## Customising the layout

Override `template/mageaustralia/pdf/invoice.phtml` in your theme. It is
plain HTML/CSS rendered by Dompdf (CSS 2.1 subset). The block class
(`Mageaustralia_Pdf_Block_Invoice`) exposes the order, address lines,
formatted prices, and branding config as helpers.

## License

[OSL-3.0](https://opensource.org/licenses/osl-3.0.php)
