# Mageaustralia_Pdf - Admin and Operator Manual

## Table of contents

1. [Overview](#1-overview)
2. [Installation](#2-installation)
3. [Configuration reference](#3-configuration-reference)
4. [Usage - adding the marker to an email template](#4-usage)
5. [Customising the PDF layout](#5-customising-the-pdf-layout)
6. [How the PDF is generated](#6-how-the-pdf-is-generated)
7. [Troubleshooting](#7-troubleshooting)
8. [FAQ](#8-faq)

---

## 1. Overview

`Mageaustralia_Pdf` attaches a branded tax invoice/receipt PDF to Maho
transactional emails. No extra admin screens or cron jobs are required. The
entire feature is driven by a single marker you place in the email template
body.

### Email event flow

```
Admin / cron triggers a transactional email (e.g. New Order)
  |
  v
Maho builds the email body from the template (marker survives filtering)
  |
  v
Maho dispatches  email_template_send_before  (sync send)
                 email_queue_send_before      (queued send)
  |
  v
Mageaustralia_Pdf_Model_Observer::attachOnEmailSend()
  - Scans the HTML body for {{attach_invoice}} / {{attach_invoice(<increment>)}}
  - Resolves the order (see below)
  - Checks mageaustralia_pdf/general/enabled for that store view
  - Renders the PDF (Helper/Pdf -> Dompdf)
  - Attaches invoice_<increment>.pdf to the Symfony Email object
  - Strips all markers from the body
  |
  v
Maho sends the email (with or without attachment - never blocked by a PDF failure)
```

### Bare marker vs explicit marker

| Marker form | When to use |
|---|---|
| `{{attach_invoice}}` | Recommended for all standard order/invoice emails. The observer resolves the order from the email's template variables (`$variables['order']`) or, for queued emails, from the queue message's `entity_id` / `entity_type` (supports both `order` and `invoice` entity types). |
| `{{attach_invoice(100000042)}}` | Use when the template context does not carry an order object (rare custom emails). The order is loaded by increment ID. |

Both forms may appear multiple times in the same template. Duplicate orders
(same entity resolved via both forms) are de-duplicated - each order produces
exactly one PDF attachment per email.

### Sync vs queued emails

The observer is registered on **both** events:

- `email_template_send_before` - fires for emails sent synchronously via
  `Mage_Core_Model_Email_Template::send()`.
- `email_queue_send_before` - fires when Maho's email queue processes a
  queued message (e.g. the New Order email sent during checkout).

You do not need to configure anything differently for queued emails.

### Enabled check

The `Enabled` config flag is evaluated per order's store view, not globally.
If the flag is off for the order's store, no PDF is attached and the marker
is still stripped from the body (so the raw text never reaches the customer).

---

## 2. Installation

### Composer (recommended)

```bash
composer require mageaustralia/maho-module-pdf
composer dump-autoload -o
./maho cache:flush
```

Composer installs the module files and its only dependency (`dompdf/dompdf`)
automatically.

### Verify the install

Check that the module is listed and active:

```bash
./maho module:status | grep Mageaustralia_Pdf
```

Clear the config cache after first install if System > Configuration does not
show the "Mage Australia" tab:

```bash
./maho cache:flush
```

---

## 3. Configuration reference

**Path in admin:** System > Configuration > Mage Australia > Invoice PDF

All fields live under the `mageaustralia_pdf/general/` config path and are
scoped to Default, Website, and Store View level.

| Field | Config key | Type | Default | Description |
|---|---|---|---|---|
| Enabled | `mageaustralia_pdf/general/enabled` | Yes/No | Yes | Master switch. When No, the observer no-ops (no PDF attached, marker still stripped). Evaluated per order's store view. |
| Logo | `mageaustralia_pdf/general/logo` | Image upload | (none) | PNG or JPG shown top-left of the invoice header. Uploaded to `media/mageaustralia/pdf/`. If empty, the Store Name text is shown instead. Embedded as a data-URI so Dompdf does not need HTTP access to render it. |
| Store Name | `mageaustralia_pdf/general/store_name` | Text | (none) | Displayed in the invoice header alongside the address. Also used as the logo fallback when no image is configured. |
| Store Address | `mageaustralia_pdf/general/store_address` | Textarea | (none) | Multi-line address text shown to the right of the logo in the header. Each newline becomes a line break on the PDF. Example: `123 Example Street\nSuburb VIC 3000`. |
| ABN | `mageaustralia_pdf/general/abn` | Text | (none) | Australian Business Number shown below the store address in the header. Example: `ABN 12 345 678 901`. |
| Bank Details (footer) | `mageaustralia_pdf/general/bank_details` | Textarea | (none) | Multi-line text shown in the PDF footer under the heading "Bank Account Details". Use this for BSB, account number, and account name. Each newline becomes a line break. |

### Where each field appears on the PDF

```
+---------------------------------------------------+
| [Logo or Store Name]       Store Address line 1   |
|                            Store Address line 2   |
|                            ABN xx xxx xxx xxx     |
+---------------------------------------------------+
| TAX INVOICE/RECEIPT         Order #xxx   Date     |
+---------------------------------------------------+
| BILLING ADDRESS  |  SHIPPING ADDRESS              |
| ...              |  ...                           |
+---------------------------------------------------+
| Shipping Type / Authority to Leave / Payment ...  |
+---------------------------------------------------+
| Qty | Items            | Price | GST   | Total    |
| ... | ...              | ...   | ...   | ...      |
+---------------------------------------------------+
                          Subtotal       xx.xx
                          Shipping       xx.xx
                          GST            xx.xx
                          Grand Total    xx.xx
+---------------------------------------------------+
| Bank Account Details:                             |
| [Bank Details lines]                              |
+---------------------------------------------------+
```

---

## 4. Usage

### Step 1 - open the email template

Go to **System > Transactional Emails**. Find the template you want to attach
the PDF to (e.g. "New Order" / `sales_email_order_template`). If you are using
the default template, copy it first ("Load default template" then save as a
custom template) so your changes are not overwritten by upgrades.

### Step 2 - add the marker

Place `{{attach_invoice}}` somewhere in the template **body** (not the subject
line). It can go on its own line at the top or bottom of the body - position
does not affect the attachment.

Example (add at the top of the order confirmation body):

```
{{attach_invoice}}

Dear {{var order.getCustomerFirstname()}},

Thank you for your order ...
```

The marker is removed before the email reaches the customer, so it does not
need to be hidden or styled.

### Step 3 - save and test

Save the template. Place a test order on the store view where the module is
enabled. The confirmation email should arrive with `invoice_<order-number>.pdf`
attached.

### Using the explicit form

If you need to attach an invoice for a specific order increment from a custom
email template that does not carry order context, use:

```
{{attach_invoice(100000042)}}
```

Replace `100000042` with the actual order increment ID. This form is rarely
needed for standard transactional emails.

---

## 5. Customising the PDF layout

The invoice layout is defined in:

```
app/design/frontend/base/default/template/mageaustralia/pdf/invoice.phtml
```

To override it without modifying the module, copy the file into your theme:

```
app/design/frontend/<package>/<theme>/template/mageaustralia/pdf/invoice.phtml
```

Maho's design fallback resolves the template from your theme first, then falls
back to `base/default`.

The template receives a `Mageaustralia_Pdf_Block_Invoice` block (`$this`) which
exposes:

| Method | Returns |
|---|---|
| `$this->getOrder()` | `Mage_Sales_Model_Order` |
| `$this->pdfHelper()` | `Mageaustralia_Pdf_Helper_Data` (config getters) |
| `$this->getStoreId()` | `int` - the order's store view ID |
| `$this->getItems()` | `Mage_Sales_Model_Order_Item[]` - visible, non-child items |
| `$this->formatPrice(float)` | Formatted currency string using the order's store locale |
| `$this->getLogoDataUri()` | `string` - base64 data-URI of the configured logo image (empty if none) |
| `$this->getAddressLines(?address)` | `string[]` - address fields as an array of lines |
| `$this->getPaymentLabel()` | `string` - payment method title, with masked card last-4 when present |
| `$this->getOrderDate()` | `string` - order date formatted long, no time component |
| `$this->hasShippingDetails()` | `bool` - whether authority-to-leave rows should be rendered |
| `$this->getAuthorityToLeave()` | `string` - "Yes" or "No" |
| `$this->getShippingNote()` | `string` - delivery instructions from Starshipit/shipnote when present |

The template is plain HTML with inline `<style>`. Dompdf does not support
JavaScript or external stylesheets fetched over HTTP - keep all CSS inline.

---

## 6. How the PDF is generated

1. The observer calls `Mageaustralia_Pdf_Helper_Pdf::renderInvoice($order)`.
2. `renderInvoice` calls `renderHtml($order)` which:
   - Temporarily switches the Maho design context to `frontend/base/default`
     (so the template lookup is deterministic and does not depend on the active
     store theme).
   - Creates a `Mageaustralia_Pdf_Block_Invoice` layout block.
   - Assigns the order and the `mageaustralia/pdf/invoice.phtml` template.
   - Calls `toHtml()` to render the PHP template to an HTML string.
   - Restores the previous design context in a `finally` block.
3. The HTML string is passed to Dompdf:
   - Paper: A4 portrait.
   - Default font: DejaVu Sans (bundled with Dompdf, supports most Latin and
     common Unicode glyphs).
   - Remote access disabled (`isRemoteEnabled: false`) - images must be
     embedded as data-URIs (the block's `getLogoDataUri()` handles this).
   - `chroot` set to Maho's `media/` directory.
4. Dompdf renders to PDF bytes which are returned and attached to the email.

### CSS support

Dompdf implements a CSS 2.1 subset. Supported features include: block/inline
layout, floats, borders, background colors, font-weight/style/size, and
percentage widths. Not supported: flexbox, grid, CSS variables, `calc()`,
multi-column layout.

---

## 7. Troubleshooting

### PDF is not attached to the email

Work through these checks in order:

1. **Module enabled?** Check System > Configuration > Mage Australia > Invoice PDF
   for the store view the order was placed in. "Enabled" must be "Yes".

2. **Marker present in the template?** Edit the transactional email template
   and confirm `{{attach_invoice}}` is in the body. If you recently edited the
   template, check that the marker was not accidentally deleted.

3. **Marker survived the template filter?** Maho runs template variables and
   directives through a filter before the observer fires. The `attach_invoice`
   token is not a registered directive, so it is treated as literal text and
   passed through unchanged. If a third-party extension rewrites the template
   filter it could strip unknown `{{...}}` blocks. In that case, temporarily
   send a test email and check whether the raw body (before sending) contains
   the marker using the module's debug logging.

4. **Email sent via a custom SMTP module?** Some legacy SMTP override modules
   replace Maho's native mailer entirely rather than using its hooks. If the
   module bypasses `email_template_send_before` and `email_queue_send_before`,
   the observer never fires. Check your installed extensions for anything that
   rewrites `Mage_Core_Model_Email_Template` or `Mage_Core_Model_Email_Queue`
   at the model level, or that overrides the transport layer without dispatching
   those events.

5. **Order not found (explicit marker)?** If using `{{attach_invoice(...)}}`,
   confirm the increment ID in the marker exactly matches the order's increment
   (including any prefix). Check `var/log/mageaustralia_pdf.log` for "order not
   found" entries.

### Errors are logged

All PDF and order-resolution failures are logged to:

```
var/log/mageaustralia_pdf.log
```

Tail this file when investigating attachment failures:

```bash
tail -f var/log/mageaustralia_pdf.log
```

Log entries include the order increment ID and the exception message when PDF
rendering throws.

### PDF attaches but is blank or has broken layout

- Dompdf only supports a CSS 2.1 subset. Remove any flexbox, grid, or CSS
  variable usage from your custom template.
- Dompdf uses DejaVu Sans by default. Characters outside its coverage (some
  extended Unicode, right-to-left scripts) will render as boxes or be dropped.
  Stick to ASCII/Latin characters in branding fields, or embed a custom font
  following the Dompdf font installation guide.
- Very long product names or addresses that do not wrap may overflow table
  cells. Add `word-wrap: break-word;` to affected `td` selectors.

### Logo does not appear on the PDF

- Confirm the image is uploaded under System > Configuration > Mage Australia >
  Invoice PDF > Logo.
- The image must be stored in `media/mageaustralia/pdf/` and be readable by the
  PHP process. Check file permissions.
- Dompdf cannot load images via HTTP when `isRemoteEnabled` is false (the
  default). The block encodes the logo as a base64 data-URI at render time. If
  `getLogoDataUri()` returns empty, the store-name text fallback is shown instead.
  Check that `mime_content_type()` is available on the server (it is a PHP
  standard function, but requires the `fileinfo` extension).

---

## 8. FAQ

**Q: Which transactional emails support the marker?**

A: Any Maho transactional email whose body passes through
`Mage_Core_Model_Email_Template::send()` or the email queue supports the
marker. This includes New Order, New Order for Guest, Invoice, Shipment, and
any custom template. The observer fires on both sync and queued sends.

**Q: Will a PDF failure cancel the email?**

A: No. Every render and attach call is wrapped in a try/catch. If the PDF
fails, the error is logged to `var/log/mageaustralia_pdf.log`, the marker is
still stripped from the body, and the email is sent without an attachment.

**Q: Can I attach PDFs for multiple orders in one email?**

A: Yes. Multiple markers in the same template body (including a mix of bare
and explicit forms) each produce one attachment. Duplicate order resolutions
(same order reached via different markers) are de-duplicated automatically.

**Q: Can I use different branding per store view?**

A: Yes. All configuration fields (logo, store name, address, ABN, bank
details, and the enabled flag) are scoped to store view. The PDF renderer
reads config for the order's store view, not the current admin scope.

**Q: I upgraded Dompdf and the layout changed slightly - is that expected?**

A: Yes. Dompdf is an active project and minor rendering changes between
versions are possible. The module requires `^2.0 || ^3.0`. If a Dompdf
upgrade causes layout regressions, override `invoice.phtml` in your theme and
adjust the CSS to suit the new version.

**Q: Where is the invoice template located for customisation?**

A: `app/design/frontend/base/default/template/mageaustralia/pdf/invoice.phtml`.
Copy it to the same relative path under your active frontend theme to override
it. See section 5 for details.

**Q: The "Authority to Leave" and "Shipping Note" rows do not appear - is something wrong?**

A: These rows are only rendered when the order has `atl_required` data set
(a field populated by the TW checkout / Shippit integration). On stores that
do not use that integration the rows are intentionally hidden. This is expected
behaviour.
