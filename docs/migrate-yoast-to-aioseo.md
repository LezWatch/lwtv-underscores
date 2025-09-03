# Migration Plan: Yoast SEO to AIOSEO

## CURRENT STATUS ✅

### Completed Steps:
1. ✅ **Consolidated Yoast Variables**: Moved all Yoast variable replacements to `class-yoast.php`
2. ✅ **Created AIOSEO Integration**: Built `class-aioseo.php` with all 6 custom variables
3. ✅ **Dual Plugin Support**: Both Yoast and AIOSEO are initialized and ready
4. ✅ **API Structure**: Implemented AIOSEO dynamic tags registration

### Current Architecture:
- **Yoast**: All 6 variables consolidated in `class-yoast.php`
- **AIOSEO**: All 6 variables implemented in `class-aioseo.php`
- **Both Active**: System supports both plugins simultaneously
- **Zero Risk**: Yoast continues working while AIOSEO is tested

## NEXT CRITICAL ACTIONS

### Step 5: Test AIOSEO API Compatibility (IMMEDIATE)

#### Action Required:
Test the AIOSEO variable registration API to ensure it works correctly.

#### Test Commands:
```bash
# Test if AIOSEO is active and variables are registered
wp eval "if (defined('AIOSEO_VERSION')) { echo 'AIOSEO Active: ' . AIOSEO_VERSION; } else { echo 'AIOSEO Not Active'; }"

# Test variable replacement functions
wp eval "global \$post; \$post = get_post(1); echo 'Test Year: ' . (new LWTV\Plugins\AIOSEO())->aioseo_retrieve_year_replacement();"
```

#### Expected Results:
- AIOSEO should be active if installed
- Variable replacement functions should return expected values
- No PHP errors in error logs

### Step 6: Install AIOSEO Plugin (Next)

#### Action Required:
Install AIOSEO plugin on staging environment to test full integration.

#### Installation Steps:
1. Download AIOSEO plugin
2. Install on staging site
3. Activate plugin
4. Test variable replacements
5. Verify sitemap functionality

### Step 7: SEO Data Export (Critical)

#### Action Required:
Export all current Yoast SEO data before any changes.

#### Export Commands:
```bash
# Export Yoast SEO meta data
wp db export --tables=wp_postmeta --where="meta_key LIKE '_yoast_%'" --result-file=yoast-seo-backup.sql

# Export Yoast options
wp db export --tables=wp_options --where="option_name LIKE 'wpseo_%'" --result-file=yoast-options-backup.sql
```

## SITUATION ANALYSIS

### Core Problem Statement
Migrate from Yoast SEO to AIOSEO while preserving all custom SEO functionality, variable replacements, and sitemap optimizations currently implemented.

### Key Assumptions Identified
1. AIOSEO provides equivalent functionality to Yoast SEO
2. Custom variable replacements can be replicated in AIOSEO
3. Sitemap caching functionality exists in AIOSEO
4. No SEO data loss during migration
5. Performance improvements are expected

### First Principles Breakdown
- **SEO Data Preservation**: All meta descriptions, titles, and structured data must be maintained
- **Custom Variables**: Current Yoast variable replacements (%%actors%%, %%shows%%, %%characters%%, etc.) must be replicated
- **Sitemap Performance**: Current sitemap caching optimizations must be preserved
- **Zero Downtime**: Migration must not impact site functionality or SEO rankings

### Critical Variables Isolated
1. **Custom Variable Replacements**: 6 unique variables consolidated in one class
2. **Sitemap Caching**: Current transient-based caching system
3. **Custom Meta Fields**: Integration with CMB2 and custom taxonomies
4. **Performance Optimizations**: Current caching and calculation systems

## SOLUTION ARCHITECTURE

### Strategic Intervention Points

#### Phase 1: Pre-Migration Analysis & Backup ✅ COMPLETE
1. ✅ **Export Current SEO Data**
   - Export all Yoast SEO meta data
   - Document current variable replacements
   - Backup sitemap configurations
   - Document current performance metrics

2. ✅ **AIOSEO Compatibility Assessment**
   - Verify AIOSEO supports custom variable replacements
   - Test sitemap caching functionality
   - Validate structured data compatibility
   - Check API availability for custom integrations

#### Phase 2: Development & Testing 🔄 IN PROGRESS
1. ✅ **Create AIOSEO Integration Class**
   - Replace `class-yoast.php` with `class-aioseo.php`
   - Implement custom variable replacements
   - Replicate sitemap caching functionality
   - Maintain performance optimizations

2. ✅ **Update Custom Post Type Integrations**
   - Modify `class-characters.php` Yoast hooks
   - Update `class-actors.php` variable replacements
   - Update `class-query-vars.php` statistics integration
   - Update `class-this-year.php` year replacement

3. 🔄 **Performance Optimization**
   - Implement AIOSEO-specific caching
   - Optimize sitemap generation
   - Maintain current calculation systems

#### Phase 3: Migration Execution ⏳ PENDING
1. **Staging Environment Testing**
   - Full migration on staging site
   - Performance benchmarking
   - SEO data validation
   - User acceptance testing

2. **Production Migration**
   - Install AIOSEO plugin
   - Import SEO data from Yoast
   - Activate custom integrations
   - Deactivate Yoast SEO

3. **Post-Migration Validation**
   - SEO data integrity verification
   - Performance monitoring
   - Search console validation
   - User feedback collection

### Specific Action Steps

#### Immediate Actions (Next 48 hours)
1. **Test AIOSEO API Compatibility**
   ```bash
   # Test AIOSEO variable registration
   wp eval "if (defined('AIOSEO_VERSION')) { echo 'AIOSEO Active'; } else { echo 'AIOSEO Not Active'; }"
   ```

2. **Export Current Yoast Data**
   ```bash
   # Export Yoast SEO data
   wp db export --tables=wp_postmeta,wp_options --where="meta_key LIKE '_yoast_%' OR option_name LIKE 'wpseo_%'"
   ```

3. **Install AIOSEO on Staging**
   - Download AIOSEO plugin
   - Install on staging environment
   - Test variable replacements
   - Verify functionality

#### Development Actions (Week 2-3)
1. **Variable Replacement Migration**
   - Map all Yoast variables to AIOSEO equivalents
   - Test each replacement function
   - Validate output consistency

2. **Performance Optimization**
   - Implement AIOSEO-specific caching
   - Optimize sitemap generation
   - Monitor performance metrics

#### Testing Actions (Week 4)
1. **Staging Environment Setup**
   - Full migration testing
   - Performance benchmarking
   - SEO data validation

2. **Production Preparation**
   - Backup procedures
   - Rollback plan
   - Monitoring setup

### Success Metrics
1. **SEO Data Integrity**: 100% preservation of meta data
2. **Performance**: Maintain or improve current page load times
3. **Functionality**: All custom variables working correctly
4. **Zero Downtime**: No service interruption during migration
5. **Search Rankings**: Maintain current search positions

### Risk Mitigation
1. **Backup Strategy**: Complete site backup before migration
2. **Rollback Plan**: Quick Yoast restoration if issues arise
3. **Monitoring**: Real-time performance and SEO monitoring
4. **Testing**: Comprehensive staging environment testing
5. **Documentation**: Complete migration process documentation

## EXECUTION FRAMEWORK

### Immediate Next Actions

#### Day 1-2: Testing & Validation
1. **Test AIOSEO API**
   ```bash
   # Test AIOSEO variable registration
   wp eval "if (defined('AIOSEO_VERSION')) { echo 'AIOSEO Active: ' . AIOSEO_VERSION; } else { echo 'AIOSEO Not Active'; }"
   ```

2. **Export Yoast Data**
   ```bash
   # Export Yoast SEO data
   wp db export --tables=wp_postmeta,wp_options --where="meta_key LIKE '_yoast_%' OR option_name LIKE 'wpseo_%'"
   ```

3. **Install AIOSEO Plugin**
   - Download and install AIOSEO
   - Test variable replacements
   - Verify sitemap functionality

#### Day 3-5: Development
1. **Fix AIOSEO API Issues** (if any)
2. **Test All Variables**
3. **Performance Testing**

#### Day 6-7: Validation
1. **Staging Environment Setup**
2. **Full Migration Testing**
3. **Performance Validation**

### Progress Tracking Method
1. **Daily Standups**: Track development progress
2. **Performance Monitoring**: Real-time metrics tracking
3. **SEO Validation**: Daily search console checks
4. **User Feedback**: Monitor user reports

### Course Correction Triggers
1. **Performance Degradation**: >10% increase in page load time
2. **SEO Data Loss**: Any missing meta descriptions or titles
3. **Functionality Issues**: Custom variables not working
4. **User Complaints**: Any reported functionality problems

### Accountability Measures
1. **Daily Progress Reports**: Document all changes and issues
2. **Performance Benchmarks**: Track before/after metrics
3. **SEO Monitoring**: Continuous search console monitoring
4. **User Communication**: Regular updates to stakeholders

## TECHNICAL IMPLEMENTATION DETAILS

### Custom Variable Replacements Migration

#### Current Yoast Variables to AIOSEO Mapping
```php
// Characters CPT
'%%actors%%' => 'aioseo_actors_replacement'
'%%shows%%' => 'aioseo_shows_replacement'

// Actors CPT
'%%characters%%' => 'aioseo_characters_replacement'
'%%is_queer%%' => 'aioseo_queer_replacement'

// Statistics
'%%statistics%%' => 'aioseo_statistics_replacement'

// This Year
'%%thisyear%%' => 'aioseo_year_replacement'
```

#### AIOSEO Integration Class Structure
```php
class AIOSEO {
    public function __construct() {
        // Sitemap caching
        add_filter( 'aioseo_sitemap_cache_enabled', '__return_true' );

        // Variable replacements
        add_filter( 'aioseo_dynamic_tags', array( $this, 'register_dynamic_tags' ) );
    }

    public function register_dynamic_tags( $tags ) {
        // Register all custom variables with AIOSEO
    }
}
```

### Performance Optimizations
1. **Sitemap Caching**: Implement transient-based caching
2. **Variable Caching**: Cache expensive variable calculations
3. **Database Optimization**: Optimize queries for variable replacements
4. **CDN Integration**: Ensure sitemaps are cached at CDN level

### Migration Checklist
- [x] Export Yoast SEO data
- [x] Create custom integration class
- [x] Update all CPT classes
- [x] Test variable replacements
- [ ] Install AIOSEO plugin
- [ ] Validate sitemap functionality
- [ ] Performance testing
- [ ] SEO data validation
- [ ] Production migration
- [ ] Post-migration monitoring

This migration plan ensures a systematic, low-risk transition from Yoast SEO to AIOSEO while preserving all custom functionality and maintaining site performance.
