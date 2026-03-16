# Approval Flow Quick Reference Guide

## ✅ Implementation Complete!

Your approval chain is now fully configurable from System Configuration.

---

## Current Setup (For Testing)

### Setting: "Staff → HOD"
**Use this during department-level testing**

| Who Applies | Approval Flow | Final Approver |
|-------------|---------------|----------------|
| Regular Staff | Staff → HOD | HOD |
| HOD | HOD → Admin | Admin |
| Director | Director → Admin | Admin |

**What happens:**
- Staff submits leave → HOD approves → ✅ APPROVED (done!)
- No Director approval needed
- Perfect for testing at department level

---

## Production Setup (After Testing)

### Setting: "Staff → HOD → Director"
**Switch to this when ready for full deployment**

| Who Applies | Approval Flow | Final Approver |
|-------------|---------------|----------------|
| Regular Staff | Staff → HOD → Director | Director |
| HOD | HOD → Director | Director |
| Director | Director → Admin | Admin |

**What happens:**
- Staff submits leave → HOD approves → Director approves → ✅ APPROVED
- Two-level approval for better control
- Full organizational hierarchy

---

## How to Switch Between Modes

1. Login as Admin
2. Go to **Admin Dashboard**
3. Click **System Configuration**
4. Select **Leave Settings** tab
5. Find **Default Approval Chain** dropdown
6. Choose your option:
   - **Staff → HOD** (testing mode)
   - **Staff → HOD → Director** (production mode)
7. Click **Save Leave Settings**
8. Done! The change takes effect immediately for new applications

---

## Testing Steps

### Phase 1: Test Single-Level Approval
1. ✅ Set to "Staff → HOD"
2. ✅ Staff member applies for leave
3. ✅ HOD logs in and approves
4. ✅ Check: Application status = APPROVED
5. ✅ Check: Leave balance deducted
6. ✅ Check: Staff receives approval notification

### Phase 2: Test HOD Leave
1. ✅ HOD applies for leave
2. ✅ Check: Goes to Admin for approval
3. ✅ Admin approves
4. ✅ Check: Application status = APPROVED

### Phase 3: Switch to Two-Level
1. ✅ Change setting to "Staff → HOD → Director"
2. ✅ Staff member applies for leave
3. ✅ HOD approves
4. ✅ Check: Application status = PENDING (not approved yet!)
5. ✅ Check: Director receives notification
6. ✅ Director approves
7. ✅ Check: Application status = APPROVED
8. ✅ Check: Leave balance deducted

---

## Important Notes

⚠️ **The setting only affects NEW applications**
- Applications already in progress follow their original workflow
- Only new applications submitted after changing the setting will use the new flow

✅ **No code changes needed**
- Everything is configuration-driven
- Switch between modes anytime without touching code

✅ **Backward compatible**
- If setting is missing, defaults to "Staff → HOD → Director"
- Existing applications continue to work

---

## Troubleshooting

### Issue: HOD approval doesn't finalize the application
**Solution:** Check that setting is "Staff → HOD" (not "Staff → HOD → Director")

### Issue: Director doesn't receive notification
**Solution:** 
1. Check that setting is "Staff → HOD → Director"
2. Verify Director user exists and is active
3. Check notifications table in database

### Issue: Setting doesn't seem to work
**Solution:**
1. Check `system_settings` table has `default_approval_chain` record
2. Value should be either `hod` or `hod,director`
3. Try clearing browser cache and re-login

---

## Database Check

To verify the setting in database:

```sql
SELECT * FROM system_settings WHERE setting_key = 'default_approval_chain';
```

Expected values:
- `hod` = Single-level (Staff → HOD)
- `hod,director` = Two-level (Staff → HOD → Director)

To manually set:

```sql
-- For testing (single-level)
UPDATE system_settings SET setting_value = 'hod' WHERE setting_key = 'default_approval_chain';

-- For production (two-level)
UPDATE system_settings SET setting_value = 'hod,director' WHERE setting_key = 'default_approval_chain';
```

---

## Summary

✅ **Files Modified:**
- `modules/apply_leave.php` - Reads setting and creates appropriate approvals
- `modules/process_approval.php` - Checks setting to determine final approval
- `admin/system_config.php` - Updated dropdown options

✅ **Ready for Testing:**
- Set to "Staff → HOD" and test department-level approvals

✅ **Ready for Production:**
- Switch to "Staff → HOD → Director" when ready for full deployment

🎉 **Your approval chain is now fully configurable!**
