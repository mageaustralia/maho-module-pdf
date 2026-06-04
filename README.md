# Mageaustralia_Pdf

Attaches a branded invoice/receipt PDF to Maho transactional emails.

## How it works

Put `{{attach_invoice(<orderIncrement>)}}` anywhere in a transactional email
template body (e.g. `{{attach_invoice(100000123)}}`). When the email is sent,
the module renders that order's invoice/receipt to PDF (Dompdf, from an
HTML/CSS template) and attaches it, then removes the marker from the body.

## Requirements

- Maho 26.5+, PHP 8.3+
- `dompdf/dompdf` (composer dependency)

## Install

    composer require mageaustralia/maho-module-pdf
    composer dump-autoload -o
    ./maho cache:flush

## Configure

**System > Configuration > Mage Australia > Invoice PDF**: enable, logo, store
name/address, ABN, and bank-details footer. Branding is config-driven, so the
module is store-agnostic.

## Customising the layout

Override `template/mageaustralia/pdf/invoice.phtml` in your theme. It is plain
HTML/CSS rendered by Dompdf.

## License

OSL-3.0
