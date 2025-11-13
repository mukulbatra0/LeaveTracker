# Leave Approval Workflow

## 📋 **Updated Leave Approval System**

### **🏢 Regular Staff Applications:**
```
Staff → Head of Department → Director → ✅ APPROVED
```
- **Step 1:** Staff applies for leave
- **Step 2:** Head of Department reviews and approves
- **Step 3:** Director gives final approval
- **Result:** Leave is fully approved and balance is updated

### **👨‍💼 Head of Department Applications:**
```
Head of Department → Director → ✅ APPROVED
```
- **Step 1:** Head of Department applies for leave
- **Step 2:** Director reviews and gives final approval (no HOD approval needed)
- **Result:** Leave is fully approved and balance is updated

### **🎯 Director Applications:**
```
Director → Admin/HR Admin → ✅ APPROVED
```
- **Step 1:** Director applies for leave
- **Step 2:** Admin or HR Admin reviews and gives final approval
- **Result:** Leave is fully approved and balance is updated

---

## 🔄 **Approval Process Details:**

### **For Regular Staff:**
1. **Application Created** → Notification sent to Head of Department
2. **HOD Approves** → Notification sent to Director
3. **Director Approves** → Application fully approved, balance updated

### **For Head of Department:**
1. **Application Created** → Notification sent directly to Director
2. **Director Approves** → Application fully approved, balance updated

### **For Director:**
1. **Application Created** → Notification sent to Admin/HR Admin
2. **Admin Approves** → Application fully approved, balance updated

---

## 👥 **Who Can Approve What:**

| Applicant Role | First Approver | Final Approver | Notes |
|---|---|---|---|
| **Staff** | Head of Department | Director | Standard 2-level approval |
| **Head of Department** | *(None)* | Director | Direct to Director |
| **Director** | *(None)* | Admin/HR Admin | Highest level approval |

---

## 🔐 **Permission Matrix:**

### **Head of Department Can:**
- ✅ Approve staff applications from their department
- ❌ Cannot approve their own applications
- ❌ Cannot approve other HOD applications
- ❌ Cannot approve Director applications

### **Director Can:**
- ✅ Approve staff applications (final approval after HOD)
- ✅ Approve Head of Department applications (direct approval)
- ❌ Cannot approve their own applications
- ❌ Cannot approve other Director applications

### **Admin/HR Admin Can:**
- ✅ Approve Director applications
- ✅ Emergency approval override for any application
- ✅ Full system access

---

## 📧 **Notification Flow:**

### **Staff Application:**
1. **Staff applies** → HOD gets notification
2. **HOD approves** → Director gets notification + Staff gets update
3. **Director approves** → Staff gets final approval notification

### **HOD Application:**
1. **HOD applies** → Director gets notification
2. **Director approves** → HOD gets final approval notification

### **Director Application:**
1. **Director applies** → Admin gets notification
2. **Admin approves** → Director gets final approval notification

---

## 🛡️ **Security Features:**

- **Self-Approval Prevention:** Users cannot approve their own applications
- **Role-Based Access:** Each role can only approve applications they have permission for
- **Audit Trail:** All approvals are logged with timestamps and comments
- **Email Notifications:** Automatic notifications keep everyone informed
- **Status Tracking:** Real-time status updates throughout the process

---

## 🔧 **Technical Implementation:**

### **Database Changes:**
- `leave_approvals` table tracks each approval step
- `approver_level` field indicates the type of approver
- `status` field tracks approval progress

### **Approval Levels:**
- `head_of_department` - For HOD approvals
- `director` - For Director approvals  
- `admin` - For Admin/HR Admin approvals

### **Workflow Logic:**
- System automatically determines approval path based on applicant role
- Prevents circular approvals (HOD approving their own application)
- Ensures proper hierarchy is maintained