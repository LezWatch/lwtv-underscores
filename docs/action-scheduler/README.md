# Action Scheduler Implementation

This document describes the Action Scheduler integration and migration work for the LezWatch.TV plugin.

## Overview

The Action Scheduler implementation replaces less reliable WordPress cron-based systems with WooCommerce's Action Scheduler library, providing:

- **Reliable Background Processing**: Tasks run reliably regardless of site traffic
- **Automatic Retry Logic**: Failed tasks are automatically retried
- **Better Monitoring**: Complete visibility into task execution and status
- **Scalability**: Can handle high volumes of background operations
- **Performance**: Non-blocking operations that don't slow down page loads

## Migration Status

### ✅ Completed Migrations

#### 1. Missed Schedule Checks
- **File**: `php/features/class-missed-schedule.php`
- **Status**: ✅ **COMPLETE**
- **Changes**:
  - Added Action Scheduler recurring action for hourly checks
  - Maintained backward compatibility with WP-CLI commands
  - Integrated health checks for monitoring
  - Added comprehensive status reporting

#### 2. TMDB API Calls
- **File**: `php/schedulers/class-tmdb-batch-task.php`
- **Status**: ✅ **COMPLETE**
- **Changes**:
  - Implemented batch processing to respect API rate limits
  - Added exponential backoff for rate-limited requests
  - Queue-based system for efficient processing
  - Comprehensive monitoring and status tracking

#### 3. Cache Queue Processing
- **File**: `php/schedulers/class-cache-batch-task.php`
- **Status**: ✅ **COMPLETE**
- **Changes**:
  - Replaced shutdown-based processing with Action Scheduler
  - Implemented batch processing for URL clearing
  - Added rate limiting between batches
  - Maintained backward compatibility

#### 4. WP-CLI Command Reorganization
- **File**: `php/wp-cli/cli-scheduler.php`
- **Status**: ✅ **COMPLETE**
- **Changes**:
  - Created dedicated `wp lwtv scheduler` command
  - Organized subcommands: `missed`, `tmdb`, `cache`, `status`
  - Added comprehensive status reporting
  - Improved command structure and usability

### 🔄 In Progress

#### 5. Transient Management Enhancement
- **Status**: 🔄 **PLANNED**
- **Planned Changes**:
  - Add Action Scheduler-based transient cleanup
  - Implement transient health monitoring
  - Create batch transient operations

### 📋 Future Enhancements

#### 6. Priority-Based Queue System
- **Status**: 📋 **PLANNED**
- **Planned Changes**:
  - Implement priority levels (high/medium/low)
  - Add priority-based processing logic
  - Create priority escalation mechanisms

#### 7. Advanced Batch Operations
- **Status**: 📋 **PLANNED**
- **Planned Changes**:
  - URL pattern-based batching
  - Time-based batching windows
  - Plugin-specific batch operations
  - Performance optimization

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
- **Cache_Batch_Task**: Handles cache invalidation batch processing
- **Missed_Schedule**: Handles missed schedule checks with Action Scheduler

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

## Monitoring

### Health Checks
- **Missed Schedule**: Integrated with HealthChecks.io
- **Error Logging**: Comprehensive logging via `lwtv_plugin()->error_log()`
- **Status Tracking**: Real-time status via transients and Action Scheduler

### Key Metrics
- **Queue Length**: Number of items waiting for processing
- **Processing Rate**: Items processed per time period
- **Error Rate**: Failed operations and retry attempts
- **API Usage**: TMDB API rate limit tracking

## Troubleshooting

### Common Issues

#### Action Scheduler Not Available
- **Symptom**: Tasks fall back to WordPress cron
- **Solution**: Ensure WooCommerce or Action Scheduler plugin is active

#### Queue Not Processing
- **Symptom**: Items stuck in queue
- **Solution**: Check Action Scheduler status and trigger manual processing

#### Rate Limit Issues
- **Symptom**: TMDB API calls failing
- **Solution**: Monitor rate limit status and adjust batch sizes if needed

### Debug Commands
```bash
# Check Action Scheduler availability
wp lwtv scheduler status

# Force process queues
wp lwtv scheduler tmdb trigger
wp lwtv scheduler cache trigger

# Clear stuck queues
wp lwtv scheduler cache clear
```

## Performance Impact

### Benefits
- **Non-blocking Operations**: Page loads complete immediately
- **Reduced Server Load**: Background processing doesn't impact user experience
- **Better Resource Management**: Controlled batch sizes and delays
- **Improved Reliability**: Automatic retry and error handling

### Considerations
- **Database Usage**: Action Scheduler tables store task data
- **Memory Usage**: Batch processing may use more memory
- **Processing Time**: Background tasks may take longer than immediate processing

## Future Roadmap

### Phase 2: Priority System
- Implement priority-based queue processing
- Add priority escalation mechanisms
- Create priority-based monitoring

### Phase 3: Advanced Batching
- URL pattern-based batching
- Time-based processing windows
- Plugin-specific optimizations

### Phase 4: Enhanced Monitoring
- Performance metrics dashboard
- Advanced error tracking
- Predictive maintenance

## Contributing

When adding new Action Scheduler integrations:

1. **Follow Patterns**: Use existing batch task classes as templates
2. **Maintain Compatibility**: Always provide backward compatibility
3. **Add Monitoring**: Include comprehensive status tracking
4. **Update Documentation**: Keep this README current
5. **Test Thoroughly**: Verify Action Scheduler availability and fallbacks
