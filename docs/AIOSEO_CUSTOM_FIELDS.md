# AIOSEO Custom Fields Usage

This document explains how to use the custom fields created for All in One SEO (AIOSEO) compatibility.

## Background

Since AIOSEO doesn't support custom replacement variables like Yoast SEO does, we've created custom post meta fields that can be used with AIOSEO's Custom Field smart tag feature.

## Available Custom Fields

### For Actors
- `lwtv_aioseo_characters` - Information about character count with proper pluralization (e.g., "3 characters", "1 character", "no characters")
- `lwtv_aioseo_is_queer` - Actor's queer status (e.g., "a queer actor" or "an actor")

### For Characters  
- `lwtv_aioseo_actors` - Comma-separated list of actor names who played the character
- `lwtv_aioseo_shows` - Comma-separated list of shows the character appeared on

### For Shows
- `lwtv_aioseo_formats` - Comma-separated list of show formats (TV show, mini series, etc.)
- `lwtv_aioseo_stations` - Comma-separated list of TV stations/networks
- `lwtv_aioseo_tropes` - Comma-separated list of tropes associated with the show

## Usage in AIOSEO

To use these custom fields in AIOSEO, use the Custom Field smart tag syntax:

```
#custom_field-[field_name]
```

### Examples

**For Actor Pages:**
```
#post_title is #custom_field-lwtv_aioseo_is_queer who has played at least #custom_field-lwtv_aioseo_characters who are queer on television, webseries, or TV Movies.
```

**For Character Pages:**
```
#post_title is a character played by #custom_field-lwtv_aioseo_actors on #custom_field-lwtv_aioseo_shows
```

**For Show Pages:**
```
#post_title is a #custom_field-lwtv_aioseo_formats found on #custom_field-lwtv_aioseo_stations
```

## Automatic Updates

These custom fields are automatically updated whenever:
- An actor post is saved/updated
- A character post is saved/updated  
- A show post is saved/updated

The fields will contain the same data that was previously available through Yoast SEO replacement variables.

## Migration from Yoast SEO

If you're migrating from Yoast SEO, here's the mapping:

| Yoast Variable | AIOSEO Custom Field |
|----------------|-------------------|
| `%%characters%%` | `#custom_field-lwtv_aioseo_characters` |
| `%%is_queer%%` | `#custom_field-lwtv_aioseo_is_queer` |
| `%%actors%%` | `#custom_field-lwtv_aioseo_actors` |
| `%%shows%%` | `#custom_field-lwtv_aioseo_shows` |

For shows, the taxonomy-based fields use AIOSEO's built-in taxonomy smart tags:
| Yoast Variable | AIOSEO Alternative |
|----------------|-------------------|
| `%%ct_lez_formats%%` | `#custom_field-lwtv_aioseo_formats` |
| `%%ct_lez_stations%%` | `#custom_field-lwtv_aioseo_stations` |
| `%%ct_lez_tropes%%` | `#custom_field-lwtv_aioseo_tropes` |