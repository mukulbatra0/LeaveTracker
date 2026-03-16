# Leave Application Form Enhancements

## Overview
The leave application system has been enhanced with additional fields to match the physical leave form requirements:

### New Features Added:

1. **Half-Day Leave Support**
   - Apply for 0.5 day leave
   - Select First Half (Morning) or Second Half (Afternoon)

2. **Mode of Transport for Official Work**
   - Optional field to specify transport mode if traveling for official work during leave
   - Examples: Personal vehicle, Public transport, Flight, Train, etc.

3. **Work Adjustment During Leave Period**
   - Optional field to describe work arrangements during absence
   - Can include handover details, coverage plans, or delegation information

## Database Migration

### Quick Setup (Recommended)
Run the comprehensive migration script that adds all columns at once:

```
http://your-domain.com/add-all-new-leave-columns.php
```

This script will:
- Check for existing columns
- Add only missing columns
- Provide detailed feedback
- Can be run multiple times safely

### Individual Migration Scripts (Alternative)
If you prefer to run migrations separately:

1. Half-day columns:
   ```
   http://your-domain.com/add-half-day-columns.php
   ```

2. Transport and work adjustment columns:
   ```
   http://your-domain.com/add-transport-work-adjustment-columns.php
   ```

### After Migration
Delete the migration files for security:
- `add-all-new-leave-columns.php`
- `add-half-day-columns.php` (if used)
- `add-transport-work-adjustment-columns.php` (if used)

## Database Schema Changes

The following columns are added to the `leave_applications` table:

```sql
-- Half-day leave support
is_half_day TINYINT(1) DEFAULT 0
half_day_period ENUM('first_half', 'second_half') NULL

-- Additional information fields
mode_of_transport VARCHAR(255) NULL
work_adjustment TEXT NULL
```

## Form Fields

### Applicant Information (Auto-filled)
- Name
- Designation (Role)
- Department
- Application Date

### Leave Details
- Type of Leave Required (dropdown)
- From Date
- To Date
- Number of Days (auto-calculated)
- Half Day Leave (checkbox)
  - First Half (Morning) - radio button
  - Second Half (Afternoon) - radio button
- Reason for Leave (required)
- Mode of Transport for Official Work (optional)
- Work Adjustment During Leave Period (optional)
- Supporting Document (if required by leave type)

## Features

### 1. Apply Leave Form (`modules/apply_leave.php`)

**Half-Day Leave:**
- Checkbox to enable half-day mode
- When checked:
  - Days automatically set to 0.5
  - End date becomes same as start date (disabled)
  - Radio buttons appear to select period
  - Validation ensures proper selection

**Mode of Transport:**
- Text input field
- Optional (only fill if applicable)
- Useful for official work during leave
- Displayed in approval views

**Work Adjustment:**
- Textarea for detailed description
- Optional field
- Can include handover details
- Visible to approvers

### 2. My Leaves (`modules/my_leaves.php`)
- Shows half-day badge (1st Half / 2nd Half)
- All leave information in list view

### 3. Leave Approvals (`modules/leave_approvals.php`)
- Half-day indicator in list
- Full details in view modal including:
  - Mode of transport (if provided)
  - Work adjustment (if provided)

### 4. View Leave Details (`modules/view_leave.php`)
- Complete leave information display
- Shows all optional fields when filled

### 5. View Application (`modules/view_application.php`)
- Detailed view with all fields
- Formatted display for better readability

### 6. Director Leave Approvals (`admin/director_leave_approvals.php`)
- Updated to fetch and display new fields

## Validation Rules

### Half-Day Leave:
- Must be exactly 0.5 days
- Start date = End date
- Must select first half or second half
- All other validations still apply

### Optional Fields:
- Mode of transport: No validation (optional)
- Work adjustment: No validation (optional)
- Both fields are stored as NULL if not provided

### Required Fields:
- Leave type
- From date
- To date
- Number of days
- Reason for leave
- Supporting document (if required by leave type)

## Usage Examples

### Example 1: Half-Day Leave
```
Employee: John Doe
Leave Type: Casual Leave
Date: March 20, 2026
Half Day: ✓ First Half (Morning)
Days: 0.5
Reason: Personal appointment
```

### Example 2: Full Day with Transport
```
Employee: Jane Smith
Leave Type: Official Duty
From: March 25, 2026
To: March 27, 2026
Days: 3
Mode of Transport: Flight to Delhi
Reason: Conference attendance
```

### Example 3: Leave with Work Adjustment
```
Employee: Mike Johnson
Leave Type: Earned Leave
From: April 1, 2026
To: April 5, 2026
Days: 5
Work Adjustment: Tasks delegated to Sarah. Urgent matters to be handled by Team Lead.
Reason: Family vacation
```

## Display Locations

All new fields are displayed in:
- ✓ Leave application form
- ✓ My leaves list
- ✓ Leave approvals list
- ✓ Leave approval modal (detailed view)
- ✓ View leave details page
- ✓ View application page
- ✓ Director leave approvals

## Benefits

1. **Complete Information**: Matches physical form requirements
2. **Better Planning**: Work adjustment helps team prepare
3. **Transparency**: Transport mode clarifies official work
4. **Flexibility**: Half-day option for short absences
5. **Efficiency**: Auto-filled applicant information saves time

## Notes

- All new fields are backward compatible
- Existing leave applications remain unaffected
- Optional fields don't impact approval workflow
- Half-day leave counts as 0.5 against balance
- Migration script is idempotent (safe to run multiple times)

## Support

If you encounter any issues:
1. Check database migration completed successfully
2. Verify all files are updated
3. Clear browser cache
4. Check PHP error logs for details
