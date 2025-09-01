# Missed Schedule Migration to Action Scheduler

## Overview

The Missed Schedule system has been migrated to use Action Scheduler when available, while maintaining full backward compatibility with the existing transient-based approach.

## What Changed

### Before (Transient-Based)
- Used `lwtv_missed_schedule` transient for 15-minute locking
- Required manual execution via `wp lwtv generate cron hourly`
- No automatic retry mechanism
- Limited to 10 posts per run

### After (Action Scheduler)
- Automatically scheduled every hour when Action Scheduler is available
- No locking issues or race conditions
- Built-in retry mechanism for failed actions
- Better error handling and logging
- Integration with health checks system

## How It Works

### Action Scheduler Available
1. **Automatic Scheduling**: Recurring action scheduled every hour
2. **No Locking**: Action Scheduler handles concurrency
3. **Health Checks**: Automatically pings health.ipstenu.com after each run
4. **Error Handling**: Built-in retry logic for failed actions

### Action Scheduler Not Available
1. **Fallback**: Uses original transient-based approach
2. **Backward Compatibility**: All existing functionality preserved
3. **Manual Execution**: Still works via WP-CLI commands

## Usage

### Check Status
```bash
wp lwtv generate missed-schedule status
```

### Trigger Manual Check
```bash
wp lwtv generate missed-schedule trigger
```

### Run Check Immediately
```bash
wp lwtv generate missed-schedule
```

### Legacy Commands (Still Work)
```bash
wp lwtv generate cron hourly
```

## Health Checks Integration

When Action Scheduler is available and `HEALTHCHECKS_API_KEY` is defined:

- **Check Name**: `{domain}-missed-schedule-check`
- **Schedule**: Every hour
- **Monitoring**: Automatic ping after each successful run
- **Failure Tracking**: Health checks will alert if runs fail

## Migration Benefits

1. **Reliability**: Action Scheduler is more reliable than WordPress cron
2. **Performance**: No transient-based locking overhead
3. **Monitoring**: Integration with existing health checks system
4. **Scalability**: Can handle larger batches of posts
5. **Maintenance**: Automatic scheduling reduces manual intervention

## Configuration

No additional configuration required. The system automatically detects Action Scheduler availability and uses the best available method.

## Troubleshooting

### Check Action Scheduler Status
```bash
wp lwtv generate missed-schedule status
```

### View Action Scheduler Queue
```bash
wp action-scheduler list-actions --hook=lwtv_missed_schedule_check
```

### Manual Health Check Creation
If health checks aren't automatically created, you can create one manually at health.ipstenu.com with:
- **Name**: Missed Schedule Check
- **Schedule**: Every hour
- **Timeout**: 5 minutes
- **Grace Period**: 10 minutes
