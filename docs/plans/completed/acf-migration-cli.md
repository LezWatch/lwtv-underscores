# Post ACF migration steps

The ACF Migration has a lot of code that needs to be converted. In order to make that easier, we build out migration commands.

## 1. Deploy & build

Push the code to the desired branch.

## 2. Sync ACF field groups

```bash
wp acf json sync
```

## 3. Run migrations

```bash
wp lwtv migrate acf waystowatch
wp lwtv migrate acf shownames
wp lwtv migrate acf similarshows
wp lwtv migrate acf charactor
wp lwtv migrate acf chardeath
wp lwtv migrate acf charimages
wp lwtv migrate acf charshowgroup
wp lwtv migrate acf autoposting
wp lwtv migrate acf watchtermurls
wp lwtv migrate acf debuglogging
wp lwtv migrate acf charimages-to-gallery
```

You may also need to run this:

```bash
wp eval '
$field = acf_get_field("field_lwtv_lezchars_character_image_group");
$field["type"] = "gallery";
$field["return_format"] = "id";
$field["library"] = "all";
$field["min"] = 0;
$field["max"] = 0;
$field["insert"] = "append";
$field["preview_size"] = "medium";
unset($field["sub_fields"], $field["layout"], $field["pagination"], $field["collapsed"], $field["button_label"], $field["rows_per_page"]);
acf_update_field($field);
echo acf_get_field("field_lwtv_lezchars_character_image_group")["type"];
'
```

## 4. Re-index FacetWP

```bash
wp facetwp index
```

All the data migrations are idempotent — they skip records that already have ACF reference keys, so if any were partially run before, re-running them is safe. The ACF sync in step 2 needs to happen before the migrations so the field key references (`field_lwtv_*`) resolve correctly when the migration writes `_meta_key` reference rows.
