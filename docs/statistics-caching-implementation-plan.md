# Statistics Caching Implementation Plan

## Executive Summary

This plan implements intelligent caching for the Statistics system, prioritizing pre-loaded data and template-level bulk operations to achieve 60-80% performance improvement with 3000 shows and 10k actors.

## Current State Analysis

### Existing Infrastructure ✅
- **Tiered Cache System**: 1hr/24hr/7day durations implemented
- **Cache Warming Framework**: Action Scheduler integration ready
- **Optimized Query Classes**: `Taxonomy_Optimized` class available
- **Template Optimization**: Some templates already optimized

### Critical Gaps Identified
- **Incomplete Cache Warming**: Placeholder methods not implemented
- **Template-Level Redundancy**: Multiple `generate_statistics()` calls per page
- **No Cache Monitoring**: Unknown hit/miss ratios
- **Missing Bulk Operations**: Individual queries instead of batch processing

## Priority Caching Strategy

### Tier 1: Critical Pre-loaded Data (1 Hour Cache)

**Target Statistics**:
```php
// From main.php - loaded on every statistics page
$characters = lwtv_plugin()->generate_total_counts( 'characters' );
$shows      = lwtv_plugin()->generate_total_counts( 'shows' );
$actors     = lwtv_plugin()->generate_total_counts( 'actors' );
$dead_chars = lwtv_plugin()->generate_total_dead( 'characters' );
```

**Implementation**:
- Pre-warm these 4 statistics via Action Scheduler
- Cache for 1 hour with immediate invalidation on content changes
- Background regeneration when cache expires

### Tier 2: Template-Level Bulk Data (24 Hour Cache)

**Target Templates**:

1. **stations.php**:
   ```php
   // Current: N+1 queries for each station
   $all_stations_data = $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_stations', true);
   $character_counts = $optimized_taxonomy->get_bulk_character_counts('lez_stations', array_keys($all_stations_data));
   $show_counts = $optimized_taxonomy->get_bulk_show_counts('lez_stations', array_keys($all_stations_data));
   ```

2. **nations.php**:
   ```php
   // Current: N+1 queries for each nation
   $all_nations_data = $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_country', true);
   $character_counts = $optimized_taxonomy->get_bulk_character_counts('lez_country', array_keys($all_nations_data));
   $show_counts = $optimized_taxonomy->get_bulk_show_counts('lez_country', array_keys($all_nations_data));
   ```

3. **characters.php**:
   ```php
   // Current: Multiple taxonomy queries
   $character_gender_data = $optimized_taxonomy->make_comprehensive(CPT_Characters::SLUG, 'lez_gender', false);
   $character_sexuality_data = $optimized_taxonomy->make_comprehensive(CPT_Characters::SLUG, 'lez_sexuality', false);
   $character_cliches_data = $optimized_taxonomy->make_comprehensive(CPT_Characters::SLUG, 'lez_cliches', false);
   ```

4. **shows.php**:
   ```php
   // Current: Multiple taxonomy queries for tropes and genres
   $tropes_data = $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_tropes', false);
   $genres_data = $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_genres', false);
   ```

5. **actors.php**:
   ```php
   // Current: Multiple taxonomy queries for actor demographics
   $actor_gender_data = $optimized_taxonomy->make_comprehensive(CPT_Actors::SLUG, 'lez_actor_gender', false);
   $actor_sexuality_data = $optimized_taxonomy->make_comprehensive(CPT_Actors::SLUG, 'lez_actor_sexuality', false);
   ```

6. **death.php**:
   ```php
   // Current: Multiple death statistics calls
   $deadchars_with_stats = lwtv_plugin()->generate_dead_statistics('characters', 'all', 'time');
   $dead_years_average = lwtv_plugin()->generate_dead_statistics('characters', 'years', 'average');

   // CRITICAL: generate_years_data() has NO CACHING - runs expensive query every time
   $years_data = $this->generate_years_data(); // Complex DB query + processing
   ```

**Implementation**:
- Cache bulk taxonomy data for 24 hours
- Background regeneration when content changes
- Template-level caching to eliminate redundant calls

### Tier 3: Stable Data (7 Day Cache)

**Target**: Taxonomy term lists, stable metadata
- Cache taxonomy term lists without counts
- Cache stable show metadata
- Preserve cache unless directly affected by content changes

## Implementation Steps

### Step 1: Complete Cache Warming System

**File**: `plugins/lwtv-plugin/php/schedulers/class-statistics-cache-warming.php`

Replace placeholder methods with actual implementations:

```php
/**
 * Warm count-related caches
 */
private function warm_count_caches(int $post_id): void {
    $post_type = get_post_type($post_id);

    // Warm the 4 critical statistics
    $this->warm_critical_counts();

    // Warm taxonomy counts for affected post type
    $this->warm_taxonomy_counts($post_type);
}

/**
 * Warm critical count statistics
 */
private function warm_critical_counts(): void {
    // Pre-load the 4 statistics from main.php
    lwtv_plugin()->generate_total_counts('characters');
    lwtv_plugin()->generate_total_counts('shows');
    lwtv_plugin()->generate_total_counts('actors');
    lwtv_plugin()->generate_total_dead('characters');

    // CRITICAL: Pre-load the expensive generate_years_data() cache
    $dead_build = new \LWTV\Statistics\Build\Dead();
    $dead_build->generate_years_data(); // This will cache the result
}

/**
 * Warm taxonomy count caches
 */
private function warm_taxonomy_counts(string $post_type): void {
    $optimized_taxonomy = new \LWTV\Statistics\Build\Taxonomy_Optimized();

    // Warm station data
    $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_stations', true);

    // Warm nation data
    $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_country', true);

    // Warm character taxonomy data
    if ($post_type === 'post_type_characters') {
        $optimized_taxonomy->make_comprehensive('post_type_characters', 'lez_gender', false);
        $optimized_taxonomy->make_comprehensive('post_type_characters', 'lez_sexuality', false);
        $optimized_taxonomy->make_comprehensive('post_type_characters', 'lez_cliches', false);
    }

    // Warm show taxonomy data
    if ($post_type === 'post_type_shows') {
        $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_tropes', false);
        $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_genres', false);
    }

    // Warm actor taxonomy data
    if ($post_type === 'post_type_actors') {
        $optimized_taxonomy->make_comprehensive('post_type_actors', 'lez_actor_gender', false);
        $optimized_taxonomy->make_comprehensive('post_type_actors', 'lez_actor_sexuality', false);
    }
}
```

### Step 2: Template-Level Caching

**File**: `plugins/lwtv-plugin/php/statistics/templates/main.php`

Add template-level caching:

```php
<?php
/**
 * The template for displaying the main stats page -- Cached Version
 */

// Cache key for all main statistics
$main_stats_cache_key = 'main_stats_' . $this->get_data_version_hash();
$main_stats = lwtv_plugin()->get_transient($main_stats_cache_key);

if (false === $main_stats) {
    // Generate all statistics at once
    $main_stats = array(
        'characters' => lwtv_plugin()->generate_total_counts('characters'),
        'shows'      => lwtv_plugin()->generate_total_counts('shows'),
        'actors'     => lwtv_plugin()->generate_total_counts('actors'),
        'dead_chars' => lwtv_plugin()->generate_total_dead('characters'),
    );

    // Cache for 1 hour
    lwtv_plugin()->set_transient($main_stats_cache_key, $main_stats, HOUR_IN_SECONDS);
}

// Extract variables
$characters = $main_stats['characters'];
$shows      = $main_stats['shows'];
$actors     = $main_stats['actors'];
$dead_chars = $main_stats['dead_chars'];
```

### Step 3: Bulk Data Caching

**File**: `plugins/lwtv-plugin/php/statistics/templates/stations.php`

Implement bulk caching:

```php
<?php
/**
 * The template for displaying station statistics -- Bulk Cached Version
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

// Cache key for all station data
$stations_cache_key = 'stations_bulk_data_' . $this->get_data_version_hash();
$stations_data = lwtv_plugin()->get_transient($stations_cache_key);

if (false === $stations_data) {
    $optimized_taxonomy = new Build_Taxonomy_Optimized();

    // Get all station data in bulk
    $all_stations_data = $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_stations', true);
    $character_counts = $optimized_taxonomy->get_bulk_character_counts('lez_stations', array_keys($all_stations_data));
    $show_counts = $optimized_taxonomy->get_bulk_show_counts('lez_stations', array_keys($all_stations_data));

    $stations_data = array(
        'stations' => $all_stations_data,
        'characters' => $character_counts,
        'shows' => $show_counts,
    );

    // Cache for 24 hours
    lwtv_plugin()->set_transient($stations_cache_key, $stations_data, DAY_IN_SECONDS);
}

// Extract variables
$all_stations_data = $stations_data['stations'];
$character_counts = $stations_data['characters'];
$show_counts = $stations_data['shows'];
```

**File**: `plugins/lwtv-plugin/php/statistics/templates/nations.php`

Implement bulk caching:

```php
<?php
/**
 * The template for displaying nation statistics -- Bulk Cached Version
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

// Cache key for all nation data
$nations_cache_key = 'nations_bulk_data_' . $this->get_data_version_hash();
$nations_data = lwtv_plugin()->get_transient($nations_cache_key);

if (false === $nations_data) {
    $optimized_taxonomy = new Build_Taxonomy_Optimized();

    // Get all nation data in bulk
    $all_nations_data = $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_country', true);
    $character_counts = $optimized_taxonomy->get_bulk_character_counts('lez_country', array_keys($all_nations_data));
    $show_counts = $optimized_taxonomy->get_bulk_show_counts('lez_country', array_keys($all_nations_data));

    $nations_data = array(
        'nations' => $all_nations_data,
        'characters' => $character_counts,
        'shows' => $show_counts,
    );

    // Cache for 24 hours
    lwtv_plugin()->set_transient($nations_cache_key, $nations_data, DAY_IN_SECONDS);
}

// Extract variables
$all_nations_data = $nations_data['nations'];
$character_counts = $nations_data['characters'];
$show_counts = $nations_data['shows'];
```

**File**: `plugins/lwtv-plugin/php/statistics/templates/shows.php`

Implement bulk caching:

```php
<?php
/**
 * The template for displaying shows statistics -- Bulk Cached Version
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

// Cache key for all shows data
$shows_cache_key = 'shows_bulk_data_' . $this->get_data_version_hash();
$shows_data = lwtv_plugin()->get_transient($shows_cache_key);

if (false === $shows_data) {
    $optimized_taxonomy = new Build_Taxonomy_Optimized();

    // Get all shows taxonomy data in bulk
    $tropes_data = $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_tropes', false);
    $genres_data = $optimized_taxonomy->make_comprehensive('post_type_shows', 'lez_genres', false);

    // Sort by count descending for top 10
    uasort($tropes_data, function($a, $b) { return $b['count'] <=> $a['count']; });
    uasort($genres_data, function($a, $b) { return $b['count'] <=> $a['count']; });

    $shows_data = array(
        'tropes' => $tropes_data,
        'genres' => $genres_data,
        'top_tropes' => array_slice($tropes_data, 0, 10, true),
        'top_genres' => array_slice($genres_data, 0, 10, true),
        'count_tropes' => count($tropes_data),
        'count_genres' => count($genres_data),
    );

    // Cache for 24 hours
    lwtv_plugin()->set_transient($shows_cache_key, $shows_data, DAY_IN_SECONDS);
}

// Extract variables
$tropes_data = $shows_data['tropes'];
$genres_data = $shows_data['genres'];
$top_tropes = $shows_data['top_tropes'];
$top_genres = $shows_data['top_genres'];
$count_tropes = $shows_data['count_tropes'];
$count_genres = $shows_data['count_genres'];
```

**File**: `plugins/lwtv-plugin/php/statistics/templates/actors.php`

Implement bulk caching:

```php
<?php
/**
 * The template for displaying actor statistics -- Bulk Cached Version
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;
use LWTV\CPTs\Actors as CPT_Actors;

// Cache key for all actor data
$actors_cache_key = 'actors_bulk_data_' . $this->get_data_version_hash();
$actors_data = lwtv_plugin()->get_transient($actors_cache_key);

if (false === $actors_data) {
    $optimized_taxonomy = new Build_Taxonomy_Optimized();

    // Get all actor taxonomy data in bulk
    $actor_gender_data = $optimized_taxonomy->make_comprehensive(CPT_Actors::SLUG, 'lez_actor_gender', false);
    $actor_sexuality_data = $optimized_taxonomy->make_comprehensive(CPT_Actors::SLUG, 'lez_actor_sexuality', false);

    // Sort by count descending for top 10
    uasort($actor_gender_data, function($a, $b) { return $b['count'] <=> $a['count']; });
    uasort($actor_sexuality_data, function($a, $b) { return $b['count'] <=> $a['count']; });

    $actors_data = array(
        'gender' => $actor_gender_data,
        'sexuality' => $actor_sexuality_data,
        'top_genders' => array_slice($actor_gender_data, 0, 10, true),
        'top_sexualities' => array_slice($actor_sexuality_data, 0, 10, true),
        'count_genders' => count($actor_gender_data),
        'count_sexualities' => count($actor_sexuality_data),
    );

    // Cache for 24 hours
    lwtv_plugin()->set_transient($actors_cache_key, $actors_data, DAY_IN_SECONDS);
}

// Extract variables
$actor_gender_data = $actors_data['gender'];
$actor_sexuality_data = $actors_data['sexuality'];
$top_genders = $actors_data['top_genders'];
$top_sexualities = $actors_data['top_sexualities'];
$count_genders = $actors_data['count_genders'];
$count_sexualities = $actors_data['count_sexualities'];
```

**File**: `plugins/lwtv-plugin/php/statistics/build/class-dead.php`

**CRITICAL FIX**: Add caching to `generate_years_data()` method:

```php
/**
 * Generate years data - CACHED VERSION
 *
 * A simple query to get the years and the count of characters that died in that year.
 *
 * @return array Years data
 */
public function generate_years_data() {
    global $wpdb;

    // Create cache key
    $cache_key = 'dead_years_data_' . $this->get_data_version_hash();
    $cached_data = lwtv_plugin()->get_transient($cache_key);

    if (false !== $cached_data) {
        lwtv_plugin()->error_log('dead-debug', 'Using cached years data');
        return $cached_data;
    }

    lwtv_plugin()->error_log('dead-debug', 'Cache miss, rebuilding years data');

    // Get all death year meta data (serialized arrays)
    $query = $wpdb->prepare(
        "SELECT post_id, meta_value
        FROM {$wpdb->postmeta}
        WHERE meta_key = %s
        AND meta_value IS NOT NULL
        AND meta_value != ''",
        'lezchars_death_year'
    );

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
    $results = $wpdb->get_results($query, ARRAY_A);

    $year_counts = array();

    foreach ($results as $row) {
        // Unserialize the meta value
        $death_dates = maybe_unserialize($row['meta_value']);

        // Ensure it's an array
        if (!is_array($death_dates)) {
            continue;
        }

        // Extract years from each death date
        foreach ($death_dates as $death_date) {
            // Extract year from Y-m-d format
            if (preg_match('/^(\d{4})-\d{2}-\d{2}$/', $death_date, $matches)) {
                $year = $matches[1];

                // Count this year
                if (!isset($year_counts[$year])) {
                    $year_counts[$year] = 0;
                }
                ++$year_counts[$year];
            }
        }
    }

    // Convert to the expected format
    $formatted_results = array();
    foreach ($year_counts as $year => $count) {
        $formatted_results[] = array(
            'death_year'  => $year,
            'death_count' => $count,
        );
    }

    // Sort by year
    usort(
        $formatted_results,
        function ($a, $b) {
            return $a['death_year'] <=> $b['death_year'];
        }
    );

    // Cache for 24 hours
    lwtv_plugin()->set_transient($cache_key, $formatted_results, DAY_IN_SECONDS);
    lwtv_plugin()->error_log('dead-debug', 'Cached ' . count($formatted_results) . ' years of death data');

    return $formatted_results;
}

/**
 * Get data version hash for cache invalidation
 *
 * @return string Hash based on last modification time
 */
private function get_data_version_hash() {
    $cache_key = 'dead_years_data_version_hash';
    $cached_hash = lwtv_plugin()->get_transient($cache_key);

    if (false !== $cached_hash) {
        return $cached_hash;
    }

    // Get the most recent modification time of any character with death data
    global $wpdb;
    $last_modified = $wpdb->get_var(
        "SELECT MAX(p.post_modified)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'post_type_characters'
        AND p.post_status = 'publish'
        AND pm.meta_key = 'lezchars_death_year'
        AND pm.meta_value IS NOT NULL
        AND pm.meta_value != ''"
    );

    $hash = md5($last_modified);
    lwtv_plugin()->set_transient($cache_key, $hash, HOUR_IN_SECONDS);

    return $hash;
}
```

### Step 4: Cache Monitoring

**File**: `plugins/lwtv-plugin/php/_components/class-transients.php`

Add cache monitoring:

```php
/**
 * Get cache statistics
 *
 * @return array Cache hit/miss statistics
 */
public function get_cache_statistics(): array {
    $cache_key = 'lwtv_cache_stats';
    $stats = lwtv_plugin()->get_transient($cache_key);

    if (false === $stats) {
        $stats = array(
            'hits' => 0,
            'misses' => 0,
            'hit_ratio' => 0,
            'last_reset' => time(),
        );
    }

    return $stats;
}

/**
 * Track cache hit
 */
public function track_cache_hit(string $cache_key): void {
    $stats = $this->get_cache_statistics();
    $stats['hits']++;
    $stats['hit_ratio'] = $stats['hits'] / ($stats['hits'] + $stats['misses']);

    lwtv_plugin()->set_transient('lwtv_cache_stats', $stats, WEEK_IN_SECONDS);
}

/**
 * Track cache miss
 */
public function track_cache_miss(string $cache_key): void {
    $stats = $this->get_cache_statistics();
    $stats['misses']++;
    $stats['hit_ratio'] = $stats['hits'] / ($stats['hits'] + $stats['misses']);

    lwtv_plugin()->set_transient('lwtv_cache_stats', $stats, WEEK_IN_SECONDS);
}
```

## Cache Invalidation Strategy

### Content-Aware Invalidation

```php
/**
 * Invalidate statistics cache based on content type
 */
public function invalidate_statistics_cache(string $content_type, int $post_id = 0): void {
    switch ($content_type) {
        case 'post_type_characters':
            // Clear character-related caches
            $this->clear_cache_pattern('main_stats_*');
            $this->clear_cache_pattern('characters_bulk_*');
            $this->clear_cache_pattern('stations_bulk_*'); // Characters affect station stats
            $this->clear_cache_pattern('nations_bulk_*'); // Characters affect nation stats

            // Schedule background warming
            $this->schedule_cache_warming('counts', $post_id);
            break;

        case 'post_type_shows':
            // Clear show-related caches
            $this->clear_cache_pattern('main_stats_*');
            $this->clear_cache_pattern('stations_bulk_*');
            $this->clear_cache_pattern('nations_bulk_*');
            $this->clear_cache_pattern('shows_bulk_*');

            // Schedule background warming
            $this->schedule_cache_warming('counts', $post_id);
            break;

        case 'post_type_actors':
            // Clear actor-related caches
            $this->clear_cache_pattern('main_stats_*');
            $this->clear_cache_pattern('actors_bulk_*');

            // Schedule background warming
            $this->schedule_cache_warming('counts', $post_id);
            break;
    }
}
```

## Performance Targets

### Expected Improvements
- **Query Count**: Reduce from 50-200+ to ~5 queries per page (90%+ reduction)
- **Response Time**: Reduce from 3-8 seconds to <2 seconds
- **Memory Usage**: Reduce from 128-512MB to <128MB
- **Cache Hit Ratio**: Achieve >90% for frequently accessed data
- **CRITICAL FIX**: `generate_years_data()` caching eliminates expensive DB query + processing on every death statistics call

### Success Metrics
- **Template Load Time**: <2 seconds for all statistics pages
- **Cache Hit Ratio**: >90% for main statistics
- **Background Processing**: Cache warming completes within 5 minutes
- **Memory Usage**: <128MB per request

## Implementation Timeline

### Week 1: Core Caching
- [ ] Complete cache warming implementation
- [ ] Implement template-level caching for main.php
- [ ] Add cache monitoring and statistics

### Week 2: Bulk Operations
- [ ] Implement bulk caching for stations.php
- [ ] Implement bulk caching for nations.php
- [ ] Implement bulk caching for characters.php
- [ ] Implement bulk caching for shows.php
- [ ] Implement bulk caching for actors.php
- [ ] Implement bulk caching for death.php

### Week 3: Optimization
- [ ] Add cache invalidation strategies
- [ ] Implement background cache warming
- [ ] Performance testing and optimization

### Week 4: Monitoring
- [ ] Add performance dashboards
- [ ] Implement alerting for cache misses
- [ ] Documentation and training

## Risk Mitigation

### Technical Risks
- **Cache Invalidation**: Implement reliable cache invalidation strategies
- **Memory Usage**: Monitor memory usage and implement limits
- **Background Processing**: Monitor queue health and implement retry logic

### Business Risks
- **Data Freshness**: Ensure cache invalidation maintains data accuracy
- **Performance**: Monitor user experience during implementation
- **Rollback**: Maintain ability to revert to previous system

## Conclusion

This caching strategy provides immediate performance improvements by focusing on the most critical data access patterns. The tiered approach ensures optimal cache utilization while maintaining data freshness through intelligent invalidation.

**Key Benefits**:
- 90%+ reduction in database queries
- Sub-2-second response times
- Intelligent cache invalidation
- Background processing prevents performance hits
- Comprehensive monitoring and alerting

The implementation leverages existing infrastructure while addressing the core performance bottlenecks identified in the Statistics Performance Improvement Plan.
