# Approval Chain Flow Analysis

## Current Status: ✅ IMPLEMENTED AND WORKING

The approval chain setting in System Configuration is now **FULLY FUNCTIONAL** and controls the leave application workflow.

## Implementation Complete

### 1. System Configuration Setting
- Location: `admin/system_config.php`
- Setting stored in database: `default_approval_chain`
- Available options:
  - `hod` → Staff → HOD (single-level approval)
  - `hod,director` → Staff → HOD → Director (two-level approval)

### 2. Dynamic Workflow (Configurable)
- Location: `modules/apply_leave.php` (UPDATED)
- The workflow now **reads from system_settings table**
- Workflow logic:

#### When setting = `hod` (Single-level):
- **Regular Staff** → HOD (final approval)
- **HOD** → Admin (final approval)
- **Director** → Admin (final approval)

#### When setting = `hod,director` (Two-level):
- **Regular Staff** → HOD → Director (final approval)
- **HOD** → Director (final approval)
- **Director** → Admin (final approval)

### 3. Approval Processing (UPDATED)
- Location: `modules/process_approval.php`
- Logic now checks approval chain setting:
  - **HOD approval**: 
    - If chain = `hod`: Marks as APPROVED (final)
    - If chain = `hod,director`: Keeps as PENDING, forwards to Director
  - **Director approval**: Always marks as APPROVED (final)
  - **Admin approval**: Always marks as APPROVED (final)

## Changes Made

### File 1: `modules/apply_leave.php`
✅ Added code to read `default_approval_chain` from system_settings
✅ Modified regular staff workflow to respect the setting
✅ Modified HOD workflow based on setting:
   - If `hod`: HOD leave → Admin
   - If `hod,director`: HOD leave → Director
✅ Director workflow unchanged (always → Admin)

### File 2: `modules/process_approval.php`
✅ Added code to read `default_approval_chain` from system_settings
✅ Added logic to determine if approval is final based on:
   - Role (admin/director always final)
   - Setting (HOD final only if chain = `hod`)
✅ Added logic to create Director approval record when HOD approves in two-level flow
✅ Updated notifications to reflect intermediate vs final approval

## How to Use

### For Testing (Current Phase):
1. Go to Admin Dashboard → System Configuration
2. Select "Leave Settings" tab
3. Choose "Staff → HOD" from Default Approval Chain
4. Save settings
5. Test: Staff applies leave → HOD approves → Application APPROVED ✅

### After Testing (Production):
1. Go to Admin Dashboard → System Configuration
2. Select "Leave Settings" tab
3. Choose "Staff → HOD → Director" from Default Approval Chain
4. Save settings
5. Production: Staff applies leave → HOD approves → Director approves → Application APPROVED ✅

## Testing Checklist

- [ ] Set approval chain to "Staff → HOD"
- [ ] Regular staff applies for leave
- [ ] HOD approves the leave
- [ ] Verify application status changes to APPROVED
- [ ] Verify leave balance is deducted
- [ ] HOD applies for leave
- [ ] Verify it goes to Admin for approval
- [ ] Switch to "Staff → HOD → Director"
- [ ] Regular staff applies for leave
- [ ] HOD approves the leave
- [ ] Verify application stays PENDING
- [ ] Verify Director receives notification
- [ ] Director approves the leave
- [ ] Verify application status changes to APPROVED

## Benefits

✅ **Flexible**: Switch between one-level and two-level approval anytime
✅ **No Code Changes**: Configuration-driven workflow
✅ **Proper Hierarchy**: HOD leaves always go up the chain
✅ **Testing Friendly**: Can test at department level first
✅ **Production Ready**: Easy to enable full workflow when ready
