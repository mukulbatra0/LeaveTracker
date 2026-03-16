# Half Day Leave Feature Setup

## Overview
The leave application system now supports half-day leave functionality where users can apply for:
- First Half (Morning) leave
- Second Half (Afternoon) leave

## Database Migration

Before using the half-day leave feature, you need to run the database migration to add the required columns.

### Steps:

1. Open your browser and navigate to:
   ```
   http://your-domain.com/add-half-day-columns.php
   ```

2. The script will add two new columns to the `leave_applications` table:
   - `is_half_day` - TINYINT(1) to indicate if it's a half-day leave
   - `half_day_period` - ENUM('first_half', 'second_half') to store which half

3. After successful migration, you can delete the migration file for security:
   ```
   add-half-day-columns.php
   ```

## Features Added

### 1. Apply Leave Form (`modules/apply_leave.php`)
- Added "Apply for Half Day Leave" checkbox
- When checked:
  - Number of days automatically set to 0.5
  - End date becomes same as start date (disabled)
  - Shows radio buttons to select First Half or Second Half
  - Validates that half-day period is selected

### 2. My Leaves List (`modules/my_leaves.php`)
- Displays half-day indicator badge (1st Half / 2nd Half) below the days count

### 3. Leave Approvals (`modules/leave_approvals.php`)
- Shows half-day period information in the approval list
- Displays detailed half-day information in the view modal

### 4. View Leave Details (`modules/view_leave.php`)
- Shows half-day period badge in the duration section

## Usage

### For Employees:
1. Go to "Apply for Leave"
2. Check the "Apply for Half Day Leave" checkbox
3. Select the date
4. Choose either "First Half (Morning)" or "Second Half (Afternoon)"
5. Fill in other required fields and submit

### For Approvers:
- Half-day leave applications will show a badge indicating which half (1st Half / 2nd Half)
- Full details are visible in the view modal

## Validation Rules

- Half-day leave must be exactly 0.5 days
- Start date and end date must be the same for half-day leave
- User must select either first half or second half
- All other leave validation rules still apply (balance check, date validation, etc.)

## Notes

- Half-day leave counts as 0.5 days against the user's leave balance
- The approval workflow remains the same as full-day leave
- Users can still apply for regular full-day or multi-day leave as before
