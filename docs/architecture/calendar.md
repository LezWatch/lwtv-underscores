# Calendar

How the airdate calendar turns the TVMaze ICS feed into Eastern-time day groups, and the one timestamp trap to know about.

Code: [plugins/lwtv-plugin/php/calendar/](../../plugins/lwtv-plugin/php/calendar/) (the bundled `ICal/` parser excepted) and [assets/js/calendar-agenda.js](../../plugins/lwtv-plugin/assets/js/calendar-agenda.js).

## Pipeline

1. `wp lwtv generate tvmaze` (daily, see [cron-schedule.md](../operations/cron-schedule.md)) downloads the TVMaze ICS file.
2. `Calendar\ICS_Parser::generate_by_date()` returns the episodes in the requested range.
3. `Calendar\Generate_Calendar::make()` groups them by `Y-m-d` airdate and show. Episodes of one show with the same timestamp are one "binge" entry with several titles (`binge_it()`). A show that airs twice on one day at different times gets a second entry keyed `<name>.lwtv-<date>`.
4. `Calendar\Data_Processor::process_calendar_data()` resolves each TVMaze show name to a local show once (`Names::resolve()`) and adds the display fields. The result is cached for a day.
5. `Calendar\Build\Agenda` (pure, unit-testable) turns the processed array into ordered day groups and the week strip. `Display_Agenda` renders them.

The calendar's timezone is `LWTV_TIMEZONE` (`America/New_York`).

## Shifted timestamps

The `timestamp` that `Generate_Calendar` stores is **not the real instant the episode airs**. It takes the UTC time from TVMaze and adds the US/Eastern offset for that moment, so formatting the value *as UTC* gives the Eastern wall-clock time:

```php
$showtime = new \DateTime( $episode->dtstart, $tvmaze_tz ); // UTC
$offset   = $lwtv_tz->getOffset( $showtime );               // e.g. -14400
$showtime->add( \DateInterval::createFromDateString( $offset . 'seconds' ) );
```

That is convenient for display: `Display::get_showtime()` builds `new \DateTime( '@' . $timestamp )` and formats it directly. It also means the raw timestamp is several hours off the true moment.

**Rule:** anything that compares an airtime against "now", or hands it to the browser as an instant, must rebuild the real instant first. Use `Agenda::airtime()`.

### airtime()

`Agenda::airtime( $timestamp )` reads the stored value back as a UTC wall clock (`Y-m-d H:i:s`), then reinterprets that wall clock in the calendar's real timezone. The result is the correct instant **and** the correct offset for that date, so it stays right across DST changes.

`Agenda::episode()` uses it for `time_label` and `iso_airtime`. The sidebar widget's `lwtv_date` label is built separately in `Data_Processor::process_show_data()`.

### Aired state

Each episode has a `dot_state` (`aired`, `today`, `upcoming`) derived from the day alone. That is the no-JavaScript fallback. `calendar-agenda.js` refines it per episode against the visitor's clock using `iso_airtime`, which is the only way to know whether something airing later today has aired yet: the processed calendar is cached for a day, so the server cannot answer that reliably.

Days with nothing airing are left out of the agenda. The week strip in the header shows which days were quiet.

## Output escaping

In the processed show array, `show_name` is plain text and must be escaped at output. `show_link` is ready-to-print HTML (a link when there is a local show to point at) and must **not** be escaped again.

## Cache versioning

Processed calendar data is cached for `DAY_IN_SECONDS` under `Data_Processor::CACHE_PREFIX`, the date query, and the modification time of `uploads/tvmaze.ics` (`lwtv_processed_calendar_v4_<date>_<mtime>`).

- **The mtime** means the nightly `wp lwtv generate tvmaze` download is picked up on the next page load. The cron runs in CLI and can't delete a web-side cache entry (see [caching.md](caching.md#cli-and-web-cache-tiers)), so the key changes instead. Old keys expire on their TTL. `download_tvmaze()` also purges the `/calendar/` page cache, so the rebuilt page uses the new key.
- **The version suffix** (`v4`): bump it whenever the shape of the processed array changes, so payloads in the old shape are ignored rather than served to views that no longer understand them.

There is no bulk "clear calendar cache" call. Changing either part of the key is how the cache is invalidated.

`Names::resolve()` results are memoised on the shared `Names` instance from `Calendar_Object_Pool` for the request, because the same show recurs across the three weeks the calendar renders. `Calendar_Object_Pool::clear()` releases them.
