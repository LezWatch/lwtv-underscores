# Action Scheduler Implementation

This document describes the Action Scheduler integration and migration work for the LezWatch.TV plugin.

## Overview

The Action Scheduler implementation replaces less reliable WordPress cron-based systems with WooCommerce's Action Scheduler library, providing:

- **Reliable Background Processing**: Tasks run reliably regardless of site traffic
- **Automatic Retry Logic**: Failed tasks are automatically retried
- **Better Monitoring**: Complete visibility into task execution and status
- **Scalability**: Can handle high volumes of background operations
- **Performance**: Non-blocking operations that don't slow down page loads
- **Memory Protection**: Built-in failsafes to prevent memory exhaustion during critical operations

## Memory Protection & Failsafes

### Critical Operation Detection
The Action Scheduler implementation includes comprehensive failsafes to prevent memory exhaustion during critical WordPress operations:

- **Plugin Activation/Deactivation**: Skips initialization during plugin management
- **WordPress Installation/Upgrade**: Avoids processing during core updates
- **Database Upgrades**: Prevents interference with schema changes
- **Memory Threshold Monitoring**: Stops processing when memory usage exceeds 80-90% of limit

### Implementation Details
- **Lazy Loading**: Task handlers are only initialized when Action Scheduler is available
- **Memory Checks**: Continuous monitoring during batch processing
- **Error Handling**: Comprehensive try-catch blocks with detailed logging
- **Graceful Degradation**: Falls back to WordPress cron when Action Scheduler is unavailable

## Migration Status

### 1. Missed Schedule Checks
- **File**: `php/features/class-missed-schedule.php`
- **Status**: ✅ **COMPLETE**
- **Changes**:
  - Added Action Scheduler recurring action for hourly checks
  - Maintained backward compatibility with WP-CLI commands
  - Integrated health checks for monitoring
  - Added comprehensive status reporting

### 2. TMDB API Calls
- **File**: `php/schedulers/class-tmdb-batch-task.php`
- **Status**: ✅ **COMPLETE**
- **Changes**:
  - Implemented batch processing to respect API rate limits
  - Added exponential backoff for rate-limited requests
  - Queue-based system for efficient processing
  - Comprehensive monitoring and status tracking

### 3. Cache Queue Processing
- **File**: `php/schedulers/class-cache-batch-task.php`
- **Status**: ✅ **COMPLETE**
- **Changes**:
  - Replaced shutdown-based processing with Action Scheduler
  - Implemented batch processing for URL clearing
  - Added rate limiting between batches
  - Maintained backward compatibility
  - **NEW**: Implemented dependency-aware priority system
  - **NEW**: Added priority-based processing (CRITICAL → HIGH → MEDIUM → LOW → CASCADE)
  - **NEW**: Post type priority mapping (Shows=HIGH, Characters=MEDIUM, Actors=LOW)
  - **NEW**: Advanced batch operations including URL pattern batching, time-based windows, and performance optimization

### 4. WP-CLI Command Reorganization
- **File**: `php/wp-cli/cli-scheduler.php`
- **Status**: ✅ **COMPLETE**
- **Changes**:
  - Created dedicated `wp lwtv scheduler` command
  - Organized subcommands: `missed`, `tmdb`, `cache`, `status`
  - Added comprehensive status reporting
  - Improved command structure and usability

### 5. Transient Management Enhancement
- **Status**: ✅ **COMPLETE** (Simplified)
- **Changes**:
  - ~~Added Action Scheduler-based transient cleanup (`Transient_Cleanup_Task`)~~ **REMOVED**
  - ~~Implemented transient health monitoring (`Transient_Health_Monitor`)~~ **REMOVED**
  - ~~Created batch transient operations with configurable batch sizes~~ **REMOVED**
  - ~~Added WP-CLI integration for transient management~~ **REMOVED**
  - ~~Integrated with health monitoring system~~ **REMOVED**
  - ~~Added daily scheduled cleanup to prevent accumulation~~ **REMOVED**
  - **NEW**: Simplified approach - WordPress handles transient cleanup automatically
  - **NEW**: Removed unnecessary Action Scheduler complexity for transient management

### 6. Dependency-Aware Priority System
- **Status**: ✅ **COMPLETE**
- **Implementation**:
  - Priority levels: CRITICAL, HIGH, MEDIUM, LOW, CASCADE
  - Post type mapping: Shows=HIGH, Characters=MEDIUM, Actors=LOW
  - Dependency-aware processing order
  - Priority-based queue sorting and processing
  - Advanced batch operations with URL pattern batching and time-based windows

## Architecture

### Core Components

#### Scheduler Class (`php/_components/class-scheduler.php`)
- **Purpose**: Main scheduler interface and coordination
- **Key Methods**:
  - `queue_tmdb_batch()`: Queue TMDB API calls
  - `queue_cache_batch()`: Queue cache operations
  - `get_tmdb_batch_status()`: Get TMDB processing status
  - `get_cache_batch_status()`: Get cache processing status

#### Batch Task Classes
- **TMDB_Batch_Task**: Handles TMDB API rate limiting and batch processing
- **Cache_Batch_Task**: Handles cache invalidation batch processing with dependency-aware priorities
- **Missed_Schedule**: Handles missed schedule checks with Action Scheduler

#### Priority System
- **Dependency-Aware Processing**: Understands data relationships (Shows → Characters → Actors)
- **Priority Levels**: CRITICAL, HIGH, MEDIUM, LOW, CASCADE based on impact scope
- **Post Type Mapping**: Automatic priority assignment based on content type
- **Queue Sorting**: Processes items in priority order with timestamp fallback

#### WP-CLI Integration
- **Command**: `wp lwtv scheduler [type] [action]`
- **Types**: `missed`, `tmdb`, `cache`, `status`
- **Actions**: `status`, `trigger`, `clear` (varies by type)

### Data Flow

1. **Task Queuing**: Tasks are queued via transient-based systems
2. **Action Scheduler**: Background processing via Action Scheduler hooks
3. **Batch Processing**: Tasks processed in configurable batches
4. **Status Tracking**: Real-time status updates and monitoring
5. **Error Handling**: Automatic retry and error logging

## Configuration

### Action Scheduler Settings

```php
// TMDB Batch Task Configuration
const RATE_LIMIT_REQUESTS   = 40;        // Requests per window
const RATE_LIMIT_WINDOW     = 10;         // Window in seconds
const BATCH_SIZE            = 20;                // Posts per batch
const DELAY_BETWEEN_BATCHES = 3;      // Seconds between batches

// Cache Batch Task Configuration
const BATCH_SIZE            = 50;                // URLs per batch
const DELAY_BETWEEN_BATCHES = 2;      // Seconds between batches

// Priority System Configuration
const PRIORITY_LEVELS = array(
    'CRITICAL' => 1, // Homepage, stats pages, archive pages
    'HIGH'     => 2, // Shows (affects character counts, actor stats)
    'MEDIUM'   => 3, // Characters (affects show counts, actor stats)
    'LOW'      => 4, // Actors (affects character counts, show stats)
    'CASCADE'  => 5, // Shadow taxonomy updates, stat recalculations
);

const POST_TYPE_PRIORITIES = array(
    'post_type_shows'      => 'HIGH',
    'post_type_characters' => 'MEDIUM',
    'post_type_actors'     => 'LOW',
);
```

### Transient Keys

```php
// TMDB Batch Task
const QUEUE_TRANSIENT  = 'lwtv_tmdb_batch_queue';
const STATUS_TRANSIENT = 'lwtv_tmdb_batch_status';

// Cache Batch Task
const QUEUE_TRANSIENT  = 'lwtv_cache_batch_queue';
const STATUS_TRANSIENT = 'lwtv_cache_batch_status';
```

## Usage

### WP-CLI Commands

#### Check Overall Status
```bash
wp lwtv scheduler status
```

#### Missed Schedule Operations
```bash
wp lwtv scheduler missed status    # Check status
wp lwtv scheduler missed trigger    # Trigger check
wp lwtv scheduler missed           # Run check
```

#### TMDB Batch Operations
```bash
wp lwtv scheduler tmdb status       # Check status
wp lwtv scheduler tmdb trigger       # Trigger processing
```

#### Cache Batch Operations
```bash
wp lwtv scheduler cache status      # Check status
wp lwtv scheduler cache trigger     # Trigger processing
wp lwtv scheduler cache clear       # Clear queue
```



### Programmatic Usage

#### Queue TMDB Processing
```php
$success = lwtv_plugin()->queue_tmdb_batch( $post_id );
```

#### Queue Cache Processing
```php
$success = lwtv_plugin()->queue_cache_batch( $post_id );
```

#### Get Status Information
```php
$tmdb_status = lwtv_plugin()->get_tmdb_batch_status();
$cache_status = lwtv_plugin()->get_cache_batch_status();
```

#### Check Priority Groups
```php
$cache_status = lwtv_plugin()->get_cache_batch_status();
$priority_groups = $cache_status['priority_groups'];
// Returns: ['HIGH' => [123, 456], 'MEDIUM' => [789], 'LOW' => [101, 102]]
```



## Monitoring

### Health Checks
- **Missed Schedule**: Integrated with HealthChecks.io
- **Error Logging**: Comprehensive logging via `lwtv_plugin()->error_log()`
- **Status Tracking**: Real-time status via transients and Action Scheduler
- **Priority Monitoring**: Track processing by priority levels

### Key Metrics
- **Queue Length**: Number of items waiting for processing
- **Processing Rate**: Items processed per time period
- **Error Rate**: Failed operations and retry attempts
- **API Usage**: TMDB API rate limit tracking
- **Priority Distribution**: Items queued by priority level

## Troubleshooting

### Common Issues

#### Memory Exhaustion During Plugin Activation
- **Symptom**: `PHP Fatal error: Allowed memory size exhausted` when adding plugins
- **Solution**: The failsafes should prevent this automatically. Check error logs for memory usage warnings
- **Prevention**: Failsafes skip initialization during plugin activation/deactivation

#### Action Scheduler Not Available
- **Symptom**: Tasks fall back to WordPress cron
- **Solution**: Ensure WooCommerce or Action Scheduler plugin is active

#### Queue Not Processing
- **Symptom**: Items stuck in queue
- **Solution**: Check Action Scheduler status and trigger manual processing

#### Rate Limit Issues
- **Symptom**: TMDB API calls failing
- **Solution**: Monitor rate limit status and adjust batch sizes if needed

#### Priority Processing Issues
- **Symptom**: Low priority items never get processed
- **Solution**: Check queue size and adjust batch processing frequency

#### High Memory Usage
- **Symptom**: Tasks stop processing due to memory threshold
- **Solution**: Check error logs for memory usage warnings, consider increasing PHP memory limit
- **Prevention**: Tasks automatically stop when memory usage exceeds 90% of limit

### Debug Commands
```bash
# Check Action Scheduler availability
wp lwtv scheduler status

# Force process queues
wp lwtv scheduler tmdb trigger
wp lwtv scheduler cache trigger

# Clear stuck queues
wp lwtv scheduler cache clear

# Check priority distribution
wp eval "var_dump(lwtv_plugin()->get_cache_batch_status()['priority_groups']);"
```

## Performance Impact

### Benefits
- **Non-blocking Operations**: Page loads complete immediately
- **Reduced Server Load**: Background processing doesn't impact user experience
- **Better Resource Management**: Controlled batch sizes and delays
- **Improved Reliability**: Automatic retry and error handling
- **Dependency-Aware Processing**: Related content updated in correct order

### Considerations
- **Database Usage**: Action Scheduler tables store task data
- **Memory Usage**: Batch processing may use more memory
- **Processing Time**: Background tasks may take longer than immediate processing
- **Priority Overhead**: Priority sorting adds minimal processing overhead

## Future Roadmap

### Phase 3: Advanced Batching
- URL pattern-based batching
- Time-based processing windows
- Plugin-specific optimizations
- Dependency graph processing

### Phase 4: Enhanced Monitoring
- Performance metrics dashboard
- Advanced error tracking
- Predictive maintenance
- Priority-based analytics

### Phase 5: Smart Cache Invalidation
- Conditional cache clearing
- Pattern-based URL optimization
- Bulk cache operations
- Cache warming strategies

## Contributing

When adding new Action Scheduler integrations:

1. **Follow Patterns**: Use existing batch task classes as templates
2. **Maintain Compatibility**: Always provide backward compatibility
3. **Add Monitoring**: Include comprehensive status tracking
4. **Update Documentation**: Keep this README current
5. **Test Thoroughly**: Verify Action Scheduler availability and fallbacks
6. **Consider Dependencies**: Implement priority systems for related operations
