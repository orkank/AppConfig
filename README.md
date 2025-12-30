# Magento2 AppConfig

**Magento2 AppConfig** is a robust Magento 2 module designed to manage dynamic configurations for mobile applications or external front-ends. It provides a flexible Key-Value store system directly within the Magento Admin Panel, allowing you to define, group, and expose configuration data (such as banners, featured products, category lists, or text settings) via a standardized REST API.

## Features

-   **Dynamic Key-Value Store**: Create and manage configuration keys without deploying code.
-   **Grouping**: Organize keys into logical groups (e.g., "Homepage", "Sidebar", "Checkout").
-   **Rich Data Types**: Support for various data types including:
    -   Text / String
    -   JSON Object with advanced editor:
        -   Key-Value Mode: Simple object editor
        -   Nested Mode: Array of objects editor for structured configurations
        -   Raw JSON Mode: Direct JSON editing
        -   Integrated file picker for media selection
    -   Product Selector (Select products from the catalog)
    -   Category Selector (Select categories from the tree)
    -   File Upload
-   **REST API**: Exposes configuration data via public endpoints for easy consumption by mobile apps (iOS/Android) or PWA.
-   **Import/Export**: Tools to migrate configuration between environments.

## Requirements

-   Magento Open Source / Adobe Commerce 2.4.x
-   PHP 7.4, 8.1, or 8.2

## Installation

### 1. Manual Installation
1.  Download the module source code.
2.  Copy the contents to `app/code/IDangerous/AppConfig` in your Magento root directory.
3.  Run the following commands:

```bash
bin/magento module:enable IDangerous_AppConfig
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```

### 2. Composer (if available via repository)
```bash
composer require idangerous/appconfig
bin/magento module:enable IDangerous_AppConfig
bin/magento setup:upgrade
```

## Usage

### Admin Configuration
Navigate to **Store > App Config** in the Admin Panel.

#### 1. Manage Groups
Create groups to organize your configuration keys. For example, you might create a group named **Homepage** to hold all homepage-related settings.

#### 2. Manage Key-Value Pairs
Create new configuration entries.
-   **Key**: The unique identifier for the setting (e.g., `homepage_banner_products`).
-   **Group**: Assign to a previously created group.
-   **Type**: Select the input type.
    -   *Text*: Simple string values.
    -   *JSON*: Advanced JSON editor with multiple modes:
        -   **Key-Value Mode**: Simple object format `{"key": "value"}` with visual editor
        -   **Nested Mode**: Array of objects format `[{"key": "value"}, {...}]` for structured data
        -   **Raw JSON Mode**: Direct JSON editing for advanced users
        -   **File Picker**: Each value field includes a file picker icon to select files from media gallery
    -   *Products*: Opens a product grid to select specific products. The API will return their IDs or basic data.
    -   *Category*: Opens a category tree to select categories.
    -   *File*: Upload generic files or images.

#### JSON Editor Features

The JSON editor provides three editing modes for different use cases:

1. **Key-Value Mode**: Simple object format `{"key1": "value1", "key2": "value2"}`
   - Visual editor with key-value pair inputs
   - Easy to add/remove pairs
   - Each value field includes a file picker icon (📁) to select files from media gallery

2. **Nested Mode**: Array of objects format `[{"key": "value"}, {"key": "value"}]`
   - Perfect for structured configurations like banners, lists, or repeated objects
   - Each row represents an object in the array
   - Rows are automatically numbered (Row 1, Row 2, etc.)
   - Each row can contain multiple key-value pairs
   - Useful for configurations like banner lists, product groups, etc.

3. **Raw JSON Mode**: Direct JSON editing
   - For advanced users who prefer manual JSON editing
   - Full JSON syntax support
   - Syntax validation

**File Picker Integration**: In both Key-Value and Nested modes, each value input field includes a file picker icon. Clicking the icon opens Magento's media browser, allowing you to select files. The selected file's URL is automatically inserted into the value field.

**Mode Switching**: You can switch between modes at any time. The editor preserves your data and converts it to the appropriate format when switching modes.

### API Reference

The module exposes the following REST API endpoints (accessible anonymously):

#### Get All Configurations
Return all active key-value pairs formatted for the application.

-   **URL**: `/V1/appconfig/config`
-   **Method**: `GET`
-   **Parameters**:
    -   `appVersion` (optional): App version filter (e.g., "1.0.5")
    -   `groupCode` (optional): Filter by group code

**Response Format:**

```json
{
  "DEFAULTS": {
    "key_name": {
      "key": "key_name",
      "text": "text value",
      "file": "https://example.com/media/file.jpg",
      "json": {...},
      "products": [
        {
          "id": 343079,
          "sku": "A.314066",
          "name": "Product Name",
          "final_price": 45.50,
          "regular_price": 50.00,
          "currency": "TRY",
          "is_in_stock": true,
          "qty": 10.0
        }
      ],
      "categories": [
        {
          "id": 32,
          "name": "Category Name"
        }
      ],
      "version": "1.0.0"
    }
  },
  "GROUPS": {
    "group_code": {
      "name": "Group Name",
      "description": "Group Description",
      "version": "1.0.0",
      "configs": {
        "key_name": {...}
      }
    }
  }
}
```

**Note:** The `products` field includes price and stock information loaded from Magento catalog. All price values are in the store's base currency.

#### Get Groups
Return a list of available configuration groups.

-   **URL**: `/V1/appconfig/groups`
-   **Method**: `GET`
-   **Parameters**:
    -   `appVersion` (optional): App version filter (e.g., "1.0.5")

### GraphQL Support

This module supports GraphQL for retrieving configurations with flexible filtering.

#### Query: `appConfig`

Retrieve configurations, optionally filtering by specific keys or group codes.

**Arguments:**

-   `keys` (optional): Array of Strings. Filter by configuration keys.
-   `groups` (optional): Array of Strings. Filter by group codes.
-   `app_version` (optional): String. Provide the client app version (e.g., "1.0.5") to filter out incompatible configurations based on version rules.

**Example Query: Fetch specific homepage config**

```graphql
query {
  appConfig(
    keys: ["homepage_banner", "homepage_title"],
    groups: ["homepage_main"]
  ) {
    key
    group
    type
    version
    text
    file
    json
    products {
      id
      sku
      name
      final_price
      regular_price
      currency
      is_in_stock
      qty
    }
    categories {
      id
      name
    }
  }
}
```

**Example Query: Fetch all configurations for an app version**

```graphql
query {
  appConfig(app_version: "2.1.0") {
    key
    group
    type
    text
    file
    json
    products {
      id
      sku
      name
      final_price
      regular_price
      currency
      is_in_stock
      qty
    }
    categories {
      id
      name
    }
  }
}
```

**Example Query: Fetch JSON configuration**

```graphql
query {
  appConfig(keys: ["BANNERS"]) {
    key
    group
    type
    json
  }
}
```

**Response Format:**

```json
{
  "data": {
    "appConfig": [
      {
        "key": "homepage_banner",
        "group": "homepage_main",
        "type": "file",
        "version": "1.0.0",
        "text": null,
        "file": "https://example.com/media/appconfig/files/banner.jpg",
        "json": null,
        "products": null,
        "categories": null
      },
      {
        "key": "BANNERS",
        "group": "homepage_main",
        "type": "json",
        "version": null,
        "text": null,
        "file": null,
        "json": "[{\"QUERY\":\"Athica Yayınları\",\"TYPE\":\"PUBLISHER\",\"BANNER\":\"https://example.com/media/674x220-athica_blok_ar25.jpg\"}]",
        "products": null,
        "categories": null
      },
      {
        "key": "WEEKLY_PROMO",
        "group": "HOMEPAGE",
        "type": "products",
        "version": null,
        "text": null,
        "file": null,
        "json": null,
        "products": [
          {
            "id": 343079,
            "sku": "A.314066",
            "name": "Unutulmuş Kuşlar Göğü - 1 (Ciltli)",
            "final_price": 45.50,
            "regular_price": 50.00,
            "currency": "TRY",
            "is_in_stock": true,
            "qty": 10.0
          }
        ],
        "categories": [
          {
            "id": 32,
            "name": "Bilim"
          }
        ]
      }
    ]
  }
}
```

**Note:**
- The response includes all value fields (`text`, `file`, `json`, `products`, `categories`), but only the field matching the `type` will have a value. All other fields will be `null`.
- The `json` field returns a JSON string that should be parsed on the client side using `JSON.parse()`.
- The `products` field returns an array of product objects with `id`, `sku`, `name`, `final_price`, `regular_price`, `currency`, `is_in_stock`, and `qty` fields. Price and stock information is loaded from Magento catalog.
- The `categories` field returns an array of category objects with `id` and `name` fields (same format as REST API).

You can access the configuration data programmatically within Magento (e.g., in Blocks, ViewModels, or other Models) by injecting the `IDangerous\AppConfig\Api\AppConfigInterface`.

#### 1. Retrieve a Single Value

Ideal for fetching a specific configuration setting.

```php
<?php
namespace Vendor\Module\ViewModel;

use IDangerous\AppConfig\Api\AppConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class MyViewModel implements ArgumentInterface
{
    private $appConfig;

    public function __construct(AppConfigInterface $appConfig)
    {
        $this->appConfig = $appConfig;
    }

    public function getBannerUrl()
    {
        // Get value by key
        $url = $this->appConfig->getValue('homepage_banner');

        // You can also specify group or app version for compatibility checks
        // $url = $this->appConfig->getValue('homepage_banner', 'homepage', '1.0.5');

        return $url;
    }
}
```

#### 2. Retrieve All Configurations

Useful if you need to build a complete configuration object.

```php
public function getAllConfigs()
{
    // Returns array ['DEFAULTS' => [...], 'GROUPS' => [...]]
    return $this->appConfig->getConfig('1.2.0');
}
```

## License

MIT License.

## Author

**Orkan K.**