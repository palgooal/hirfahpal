# VPM-UX-01E — Vendor product editor: design prototype

Static, non-production prototype of the vendor product editor (Add / Edit product) in the approved My Store design
language. It is not wired to Laravel: it sends no request, and Save and the media-library actions are simulated.

Open `product-editor.html` directly in a browser (it reads fonts, icons and sample images from `../../public`).
The bar at the top switches states; each state is also a URL parameter:

| Parameter | Values |
|---|---|
| `lang` | `ar` (RTL, default), `en` (LTR) |
| `mode` | `add` (default), `edit` |
| `moderation` | `none`, `suspended`, `archived` (Edit only) |
| `images` | `empty`, `some` (4), `full` (6) |
| `save` | `idle`, `dirty`, `saving`, `saved`, `error` |
| `errors` | `0`, `1` (field errors and the save-bar summary) |
| `stored` | `out_of_stock` (a product stored with the legacy status) |
| `chrome` | `0` hides the prototype bar |

Layout: one column below a content width of 880px (viewports under about 1224px, sidebar included), two columns
(about 2:1) above it. Copy comes from the approved translation seeders; strings marked `NEW` in the script are
proposals awaiting approval.
