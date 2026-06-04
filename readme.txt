=== WC Generic Product Designer ===
Contributors: wc-generic-product-designer
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allow customers to add styled text layers on a product canvas and export production-ready SVG on order.

== Description ==

* Enable the designer per product under **Product data → Product Designer**.
* Customers design on the product page; SVG is stored on the order line item.
* Shop managers download production SVG from the order screen.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via zip.
2. Activate through the **Plugins** screen.
3. Requires WooCommerce.
4. Edit a product → **Product Designer** tab → enable and configure canvas + template image.

== GitHub updates (Git Updater) ==

1. Push this repo to GitHub (see `scripts/push-to-github.ps1`).
2. On the store, install the **Git Updater** plugin.
3. For a private repo, add `define( 'GITHUB_TOKEN', '...' );` to wp-config.php (see `git-updater.wp-config.example.php`).
4. Install this plugin via Git Updater using your GitHub repo URL, or upload once — Git Updater detects it via the `GitHub Plugin URI` header.
5. For each release: bump `Version` in the main plugin file, commit, push, then update from **Dashboard → Updates**.

== Changelog ==

= 1.0.0 =
* Initial release.
