# Application Period Feature - Setup Guide

This document explains the Application Period feature that controls when users can submit applications to become collaborators.

## Overview

The Application Period feature allows administrators to set specific date ranges during which applications are accepted. Outside these periods, the registration form will display an appropriate message to users.

## Database Migration

Run the migration to create the `application_periods` table:

```bash
php artisan migrate
```

This creates a table with the following structure:
- `id` - Primary key
- `start_date` - Start date of the application period
- `end_date` - End date of the application period
- `is_active` - Boolean flag (only one period should be active at a time)
- `timestamps` - Created and updated timestamps

## API Endpoints

### Public Endpoints (No Authentication Required)

#### Get Current Application Period
```
GET /api/application-period
```

**Response (200 OK):**
```json
{
  "id": 1,
  "start_date": "2025-01-01",
  "end_date": "2025-01-31",
  "is_active": true,
  "created_at": "2025-10-16T00:00:00.000000Z",
  "updated_at": "2025-10-16T00:00:00.000000Z"
}
```

**Response (404 Not Found):**
```json
{
  "message": "No application period set"
}
```

#### Check Application Status
```
GET /api/application-period/status
```

**Response:**
```json
{
  "is_open": true,
  "message": "Applications are currently open.",
  "period": {
    "id": 1,
    "start_date": "2025-01-01",
    "end_date": "2025-01-31",
    "is_active": true
  }
}
```

### Protected Endpoints (Authentication Required)

#### Set Application Period
```
POST /api/application-period
```

**Request Body:**
```json
{
  "start_date": "2025-01-01",
  "end_date": "2025-01-31"
}
```

**Validation Rules:**
- `start_date`: Required, must be a valid date
- `end_date`: Required, must be a valid date, must be after or equal to start_date

**Response (201 Created):**
```json
{
  "id": 1,
  "start_date": "2025-01-01",
  "end_date": "2025-01-31",
  "is_active": true,
  "created_at": "2025-10-16T00:00:00.000000Z",
  "updated_at": "2025-10-16T00:00:00.000000Z"
}
```

**Response (422 Validation Error):**
```json
{
  "message": "Validation failed",
  "errors": {
    "end_date": ["The end date field must be a date after or equal to start date."]
  }
}
```

#### Delete Application Period
```
DELETE /api/application-period/{id}
```

**Response (200 OK):**
```json
{
  "message": "Application period deleted successfully"
}
```

## Model Methods

The `ApplicationPeriod` model provides several useful methods:

### Static Methods

- `ApplicationPeriod::getActive()` - Get the currently active period
- `ApplicationPeriod::isOpen()` - Check if applications are currently open

### Instance Methods

- `$period->isBeforeStart()` - Check if current date is before the start date
- `$period->isAfterEnd()` - Check if current date is after the end date
- `$period->isCurrentlyActive()` - Check if the period is currently active

## Frontend Integration

The frontend has been updated in two places:

### 1. Manage Applicants Screen
- Added "Set Period" button in the top right corner
- Opens a modal to set start and end dates
- Displays current application period below the header
- Located at: `Fishfront/app/screens/collab/manage-applicants/manage-applicants.web.js`

### 2. Registration Screen
- Checks application period on page load
- Shows error modal if:
  - Current date is before start date: "Application period has not yet started"
  - Current date is after end date: "Application period has ended"
- Allows normal registration if within the period or if no period is set
- Located at: `Fishfront/app/screens/user/registration/registration.web.js`

## Behavior

### When No Period is Set
- Applications are **allowed** (backward compatibility)
- No restrictions on registration

### When Period is Set
- **Before start date**: Registration blocked with "not yet started" message
- **Within period**: Registration allowed normally
- **After end date**: Registration blocked with "period ended" message

### Setting a New Period
- When a new period is created, all previous periods are automatically deactivated
- Only one period can be active at a time

## Usage Example

1. **Admin sets application period:**
   - Navigate to "Manage Applicants" screen
   - Click "Set Period" button
   - Select start date: January 1, 2025
   - Select end date: January 31, 2025
   - Click "Save"

2. **User tries to register:**
   - Before January 1: Sees "Application period has not yet started. Applications will open on January 1, 2025."
   - January 1-31: Can submit application normally
   - After January 31: Sees "Application period has ended. The deadline was January 31, 2025."

## Testing

### Test the API endpoints:

```bash
# Get current period (public)
curl http://localhost:8000/api/application-period

# Set a period (requires authentication)
curl -X POST http://localhost:8000/api/application-period \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "start_date": "2025-01-01",
    "end_date": "2025-01-31"
  }'

# Check status
curl http://localhost:8000/api/application-period/status
```

## Database Seeder (Optional)

If you want to seed a default application period, create a seeder:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApplicationPeriod;
use Carbon\Carbon;

class ApplicationPeriodSeeder extends Seeder
{
    public function run()
    {
        ApplicationPeriod::create([
            'start_date' => Carbon::now()->startOfMonth(),
            'end_date' => Carbon::now()->endOfMonth(),
            'is_active' => true,
        ]);
    }
}
```

Run the seeder:
```bash
php artisan db:seed --class=ApplicationPeriodSeeder
```

## Troubleshooting

### Issue: Frontend shows "Application period has not yet started" but it should be open

**Solution:** Check the server timezone and database dates. Ensure dates are stored in the correct format (YYYY-MM-DD).

### Issue: Multiple periods are active

**Solution:** Run this query to deactivate all but the most recent:
```sql
UPDATE application_periods 
SET is_active = false 
WHERE id NOT IN (SELECT MAX(id) FROM application_periods);
```

### Issue: Can't set period (validation error)

**Solution:** Ensure:
- Both dates are in YYYY-MM-DD format
- End date is not before start date
- You're authenticated as an admin

## Security Notes

- Only authenticated users can set/delete application periods
- Public users can only read the current period
- Consider adding role-based authorization (admin only) for period management
- The frontend checks are supplemented by backend validation

## Future Enhancements

Potential improvements:
- Multiple overlapping periods for different roles
- Email notifications when periods start/end
- Automatic period activation/deactivation
- Period templates for recurring schedules
- Analytics on applications per period
