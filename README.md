# WooCommerce Print Orders Plugin

A powerful and user-friendly WordPress plugin that adds professional print functionality to WooCommerce orders directly from the WordPress admin dashboard. Print single orders or bulk print multiple orders with clean, well-formatted layouts.

## Features

- Print individual orders with a single click
- Bulk print multiple orders at once
- HPOS (High-Performance Order Storage) compatible
- Clean, professional print layout
- Responsive design for all devices
- Easy-to-use interface
- Multilingual ready (supports translations)
- Works with all major browsers
- No configuration required

## Requirements

- WordPress 5.0 or higher
- WooCommerce 6.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the `print-order` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The print functionality will be automatically available in your WooCommerce orders

## How to Use

### Printing a Single Order

#### Method 1: From Orders List
1. Go to WooCommerce → Orders
2. Hover over the order you want to print
3. Click on the "Print Order" link in the action menu

#### Method 2: From Individual Order Page
1. Open the order you want to print
2. Look for the "Print Order" button next to the "Refund" button
3. Click the button to open the print preview

### Bulk Printing Multiple Orders
1. Go to WooCommerce → Orders
2. Select the checkboxes next to the orders you want to print
3. From the "Bulk Actions" dropdown, select "Print Orders"
4. Click "Apply"
5. A new tab will open with all selected orders ready for printing

### Print Preview
- The print preview will open in a new tab
- Use your browser's print dialog (Ctrl+P or Cmd+P) to print or save as PDF
- The print layout is optimized for A4 paper size

## Styling

The plugin includes clean, professional styling for the printed output, including:
- Store logo (if set in WooCommerce)
- Order details (date, status, customer information)
- Billing and shipping addresses
- Order items with thumbnails
- Order totals and payment method
- Customer notes (if any)

## Customization

### Custom CSS
You can customize the print styles by adding custom CSS to your theme's `style.css` file or using a custom CSS plugin. Use the `.woocommerce-order-print` class to target print-specific styles.

### Translation
To translate the plugin into your language:
1. Copy the `.pot` file from the `languages` directory
2. Create a new `.po` file for your language (e.g., `wc-print-orders-fr_FR.po`)
3. Translate the strings and save
4. Compile to `.mo` file using a tool like Poedit
5. Upload both files to the `languages` directory

## Troubleshooting

### Print Button Not Appearing
- Make sure WooCommerce is activated
- Clear your browser cache
- Deactivate and reactivate the plugin
- Check for JavaScript errors in your browser console

### Print Layout Issues
- Try printing in a different browser
- Ensure you're using the latest version of the plugin
- Check for conflicts with other plugins by temporarily deactivating them

## Frequently Asked Questions

### Can I customize the print template?
Yes, you can override the default template by copying it to your theme directory at `your-theme/woocommerce/print-order/`.

### Does this work with custom order statuses?
Yes, the plugin works with all order statuses, including custom ones.

### Is this plugin compatible with my theme?
The plugin is designed to work with all standard WooCommerce-compatible themes. If you encounter any display issues, please contact support.

## Changelog

### 1.2.0
- Added HPOS (High-Performance Order Storage) compatibility
- Improved print layout and styling
- Added bulk print functionality
- Fixed various bugs and improved performance

### 1.0.0
- Initial release

## Support

For support, feature requests, or bug reports, please open an issue on the [GitHub repository](https://github.com/yourusername/woocommerce-print-orders).

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Sajid Khan](https://sajidkhan.me)
