# Availability Board

A one-tap availability board for WooCommerce: mark a menu item, service, or product unavailable the moment it runs out, no wp-admin required.

[Try Availability Board in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/availability-board/main/blueprint.json)

In wp-admin, marking a product out of stock means Products → find it → Edit → Inventory tab → toggle → Update: several clicks, one item at a time. Availability Board turns that into one screen: every product as a big switch, grouped by category, with a live search field. Flip it off when you run out, flip it back on when you're restocked.

- Reads and writes through WooCommerce's own product stock status; it keeps no data of its own.
- Requires the `manage_woocommerce` capability, so it's available to staff and managers, not customers.
- The demo blueprint seeds a small restaurant menu (appetizers, mains, desserts, drinks) with a couple of items already toggled off, so the board isn't empty on first load — the same idea works for a barber's service list or a dentist's procedure list.

Built on [WpApp](https://github.com/akirk/wp-app).
