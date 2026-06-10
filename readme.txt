=== WC Generic Product Designer ===
Contributors: wc-generic-product-designer
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.54.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allow customers to add styled text layers on a product canvas and export production-ready SVG on order.

== Description ==

* Enable the designer per product under **Product data → Product Designer**.
* Customers design on the product page; SVG is stored on the order line item.
* Shop managers download production SVG from the order screen.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via zip.
2. For manual zip updates, download from GitHub **main** (same folder name WordPress expects after the first install):
   https://github.com/nickjdunn/wc-generic-product-designer/archive/refs/heads/main.zip
   The extracted folder must stay named `wc-generic-product-designer-main`.
2. Activate through the **Plugins** screen.
3. Requires WooCommerce.
4. Edit a product → **Product Designer** tab → enable and configure canvas + template image.

== GitHub updates (Git Updater) ==

1. Push this repo to GitHub (see `scripts/push-to-github.ps1`).
2. On the store, install the **Git Updater** plugin.
3. **Settings → Git Updater → Install Plugin** — use ONLY this slug (not a full URL):
   nickjdunn/wc-generic-product-designer
   If a branch field appears, set it to `main`.
4. For a private repo, add `define( 'GITHUB_TOKEN', '...' );` to wp-config.php (see `git-updater.wp-config.example.php`).
5. For each release: bump `Version` in the main plugin file, commit, push, then update from **Dashboard → Updates**.

== Changelog ==

= 1.54.0 =
* Admin order design editor: full layer access, save updates order preview, revert to customer design.
* Download production/proof from order editor with preset picker and layer toggles.
* Template document-part tags (backdrop, engraving, outline) control export contents; simplified image role UI.

= 1.53.1 =
* Fix design edit layer order and JSON round-trip for template text; includes debug logging for verification.

= 1.53.0 =
* Fix cart and order design edit loading; move production/proof downloads into the order design editor.

= 1.52.0 =
* Production: delete batches, Completed tab for finished jobs, hide cancelled/trashed orders from active queue.

= 1.2.0 =
* PoC fixes: add-to-cart submit loop, SVG sanitizer fallback, blank canvas without template.
* Auto-add starter text layer; hide product gallery when designer is active.
* Proof-of-concept checklist on Designer Debug page.

= 1.1.1 =
* Fix fatal error: autoloader now resolves class-* and interface-* filenames correctly.

= 1.1.0 =
* Core architecture: autoloader, service container, module interface.
* Debug framework: admin debug panel, structured logger, JS console debug.

= 1.0.0 =
* Initial release.
