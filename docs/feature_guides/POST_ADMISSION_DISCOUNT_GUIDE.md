# Post-Admission Discount (Enrolled Student Discount) Guide

## 📋 Overview
Post-Admission Discount system allows authorized users to apply discounts to enrolled students **after** their admission has been confirmed.

---

## 👥 Who Can Manage Post-Admission Discounts?

### ✅ Authorized Roles:
1. **Principal** (ROLE_PRINCIPLE)
2. **Super Admin** (ROLE_SUPER_ADMIN)

### ❌ Cannot Access:
- Counsellors
- Accountants
- Other staff members

---

## 🔐 Access & Security

### File Location:
```
portal/modules/students/post-admission-discount.php
portal/modules/students/post-admission-discount-save.php
```

### Permission Check (Line 16-20):
```php
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', "Access denied. Only Principal and Super Admin can apply post-admission discounts.");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
```

---

## 📍 How to Access (Sidebar Menu)

### Navigation Path:
```
Portal → Student Management → Post-Admission Discount
```

### Menu Icon:
- **Icon:** fa-percentage (%)
- **Label:** Post-Admission Discount
- **Visibility:** Only visible to Principal & Super Admin

### Sidebar Location:
```
Student Management
├── Enrolled Students
├── Post-Admission Discount ⭐ (NEW - Principal & Super Admin only)
├── Pending Token Fee
└── All Students
```

---

## 💰 How to Apply Discount

### Step 1: Access the Page
1. Login as **Principal** or **Super Admin**
2. Navigate to: **Student Management → Post-Admission Discount**

### Step 2: Select Student
1. Use the search/filter to find the enrolled student
2. View student details:
   - Enrollment Number
   - Name
   - Current Fee Status
   - Net Fees
   - Fees Paid
   - Fees Pending

### Step 3: Choose Discount Type
Two options available:

#### Option A: Percentage Discount (%)
- Enter percentage (e.g., 10 for 10%)
- System automatically calculates amount
- Applied on pending fees

#### Option B: Fixed Amount (₹)
- Enter exact rupee amount
- Direct deduction from pending fees

### Step 4: Add Details
1. **Discount Value:** Enter % or ₹ amount
2. **Reason/Remarks:** Mandatory field explaining why discount is given
   - Example: "Merit scholarship"
   - Example: "Financial hardship"
   - Example: "Staff ward benefit"

### Step 5: Review & Confirm
1. Preview shows:
   - Current Pending Amount
   - Discount Amount (calculated)
   - New Pending Amount (after discount)
2. Click **"Apply Discount"** button

### Step 6: Confirmation
- Success message appears
- Discount is logged in system
- Student's pending fees updated
- Audit trail created with:
  - Who applied discount (user ID)
  - When (timestamp)
  - Amount
  - Reason

---

## 📊 Features

### ✨ Key Features:
1. **Real-time Calculation** - Discount preview before applying
2. **Audit Trail** - All discounts are logged with user details
3. **Validation** - Cannot apply discount > pending amount
4. **History** - View all previously applied discounts
5. **Search & Filter** - Easy student lookup
6. **Safety** - Confirmation before applying
7. **UTF-8 Support** - Properly displays ₹ symbol

---

## 🔍 Viewing Discount History

### On Post-Admission Discount Page:
- View table of all applied discounts
- Columns show:
  - Student Name & Enrollment
  - Discount Type (% or Fixed)
  - Discount Amount
  - Applied By (User name)
  - Applied On (Date & Time)
  - Reason/Remarks
  - Status

### On Enrolled Students Page:
- View individual student's discount history
- Total discounts applied to that student

---

## ⚠️ Important Notes

### Restrictions:
1. ❌ Cannot apply discount > pending fees
2. ❌ Cannot apply negative discounts
3. ✅ Can apply multiple discounts to same student
4. ✅ Each discount requires a reason
5. ✅ Cannot delete/edit after applying (audit integrity)

### Best Practices:
1. Always provide clear reason for discount
2. Verify student details before applying
3. Review preview calculations carefully
4. Keep discount reasons professional and specific
5. Coordinate with accounts team for payment tracking

---

## 🛠️ Technical Details

### Database Tables:
- `tbl_post_admission_discounts` - Stores all discount records
- `tbl_enrolled_students` - Updated with new pending amount

### Fields Updated:
- `fees_pending` - Reduced by discount amount
- Discount history maintained separately

### Audit Trail Includes:
- `student_id` - Which student
- `discount_type` - percentage or fixed
- `discount_value` - % or ₹ value entered
- `discount_amount` - Actual rupee amount deducted
- `created_by` - User ID who applied
- `created_at` - Timestamp
- `remarks` - Reason for discount

---

## 🔧 Troubleshooting

### Issue: Menu option not visible
**Solution:** 
- Check your user role
- Only Principal & Super Admin can see this option
- Contact Super Admin to verify your role assignment

### Issue: "Access Denied" error
**Solution:**
- You don't have required permissions
- Ask Principal or Super Admin to apply discount
- Or request role upgrade from Super Admin

### Issue: ₹ symbol shows as ?
**Solution:**
- Browser cache cleared (Ctrl + Shift + Delete)
- UTF-8 headers now added to file
- Refresh page (Ctrl + F5)

### Issue: Cannot find student
**Solution:**
- Student must be **enrolled** (not just registered)
- Check if token fee is paid
- Search by enrollment number or name
- Use filters to narrow down results

---

## 📞 Support

For any issues or questions:
1. Contact Super Admin
2. Check system logs in `portal/common/logs/`
3. Review error messages displayed on screen

---

## ✅ Recent Updates

### January 17, 2026:
1. ✅ Added UTF-8 encoding header for proper ₹ display
2. ✅ Added sidebar menu option for Principal & Super Admin
3. ✅ Menu appears in Student Management section
4. ✅ Fixed all currency symbol encoding issues

---

## 📝 Summary

**Post-Admission Discount** is a powerful tool for **Principal** and **Super Admin** to:
- Apply discounts to enrolled students
- Maintain complete audit trail
- Reduce pending fees transparently
- Document reasons for each discount

Access it from: **Portal → Student Management → Post-Admission Discount** 🎯
