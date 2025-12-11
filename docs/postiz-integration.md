# Postiz Integration Configuration

## Overview

The Postiz integration allows LezWatch.TV to automatically post "Of The Day" content to configured social media channels through the Postiz API.

## Configuration

Add the following constants to your `wp-config.php` file (recommended) or define them in your theme/plugin:

```php
/**
 * Postiz API Configuration
 */

// API Key from Postiz settings
define( 'POSTIZ_API_KEY', 'your-api-key-here' );

// Channel IDs (can be a single ID or an array for multiple channels)
// Single channel:
define( 'POSTIZ_CHANNEL_IDS', 'asdfsad23rwdfasfsddc' );

// Multiple channels:
define( 'POSTIZ_CHANNEL_IDS', array(
    'channel-id-1',
    'channel-id-2',
    'channel-id-3',
) );

// Optional: Custom API URL (defaults to https://postiz.ipstenu.com/api/public/v1)
define( 'POSTIZ_API_URL', 'https://postiz.ipstenu.com/api/public/v1' );
```

## Alternative: WordPress Options

If you prefer not to use constants, you can also store the configuration in WordPress options:

```php
// Set API Key
update_option( 'lwtv_postiz_api_key', 'your-api-key-here' );

// Set Channel IDs (single or array)
update_option( 'lwtv_postiz_channel_ids', array( 'channel-id-1', 'channel-id-2' ) );
```

## How It Works

1. When the daily cron job runs (see `cron/otd.sh`), it calls `Of_The_Day::set_of_the_day()`
2. This creates the OTD entry and adds it to the database
3. After adding to the database, the `lwtv_otd_added` action hook is fired
4. The Postiz integration listens for this hook and automatically posts to configured channels

## Features

### Multiple Channels
Yes, you can use more than one channel! Simply define `POSTIZ_CHANNEL_IDS` as an array with multiple channel IDs. The integration will create a separate post for each channel in a single API request.

### Groups
The integration automatically creates a unique group ID for each OTD post using the format:
- `otd_character_YYYY-MM-DD` for character OTDs
- `otd_show_YYYY-MM-DD` for show OTDs

This allows related posts across multiple channels to be grouped together in Postiz.

### Featured Images
If the character or show has a featured image set in WordPress, it will be automatically included in the Postiz post.

## Manual Testing

You can manually trigger an OTD post using WP-CLI:

```bash
# Trigger character of the day
wp eval "lwtv_plugin()->set_of_the_day('character');"

# Trigger show of the day
wp eval "lwtv_plugin()->set_of_the_day('show');"
```

## Debugging

The integration logs all API requests and responses. Check the error logs for entries with type `postiz`:

- Successful posts will log the content and response
- Failed posts will log the error message

## API Endpoint Reference

The integration uses the Postiz Public API:

**Endpoint:** `POST https://postiz.ipstenu.com/api/public/v1/posts`

**Headers:**
- `Authorization: {your-api-key}`
- `Content-Type: application/json`

**Payload Structure:**
```json
{
  "type": "schedule",
  "date": "2024-12-14T08:18:54.274Z",
  "posts": [
    {
      "integration": {
        "id": "channel-id"
      },
      "value": [
        {
          "content": "The LezWatch.TV character of the day is...",
          "image": [
            {
              "id": "123",
              "path": "https://example.com/image.jpg"
            }
          ]
        }
      ],
      "group": "otd_character_2024-12-14",
      "settings": {}
    }
  ]
}
```

## Files Modified/Created

- **New:** `/plugins/lwtv-plugin/php/plugins/class-postiz.php` - Main Postiz integration class
- **Modified:** `/plugins/lwtv-plugin/php/_components/class-of-the-day.php` - Added `lwtv_otd_added` action hook
- **Modified:** `/plugins/lwtv-plugin/php/_components/class-plugins.php` - Registered Postiz plugin

## Disabling the Integration

To disable Postiz integration:

1. Remove or comment out `POSTIZ_API_KEY` and `POSTIZ_CHANNEL_IDS` constants
2. Or delete the options:
   ```php
   delete_option( 'lwtv_postiz_api_key' );
   delete_option( 'lwtv_postiz_channel_ids' );
   ```

The integration will automatically disable itself when no API key or channel IDs are configured.
