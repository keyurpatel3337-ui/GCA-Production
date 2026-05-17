# 📘 Post-Admission Discount - User Guide

## How to Add/Update Post-Admission Discount

### 🔐 Access Requirements
- **Role:** Principal or Super Admin only
- **Location:** Portal → Fee Management → Post-Admission Discount

---

## ✅ Step-by-Step Process

### Step 1: Login & Navigate
1. Login as **Principal** or **Super Admin**
2. Click on **Fee Management** in sidebar
3. Click on **Post-Admission Discount**

---

### Step 2: Select Student
1. Click on **"Search & Select Student"** dropdown
2. Type student name, enrollment number, or mobile number
3. Select the student from dropdown
4. Student details will automatically appear

**Student Details Shown:**
- Enrollment Number
- Full Name
- Mobile Number
- School & Course
- **Fee Summary:**
  - Net Fees
  - Fees Paid
  - Fees Pending
  - Existing Discounts (if any)

---

### Step 3: Choose Discount Type

#### Option A: Fixed Amount (₹)
```
Example: Give ₹5,000 discount
1. Select "Fixed Amount (₹)" from dropdown
2. Enter: 5000
3. Calculated discount will show: ₹5,000
```

#### Option B: Percentage (%)
```
Example: Give 10% discount
1. Select "Percentage (%)" from dropdown  
2. Enter: 10
3. If pending fee is ₹50,000
   Calculated discount will show: ₹5,000 (10% of 50,000)
```

---

### Step 4: Enter Reason/Remarks
**⚠️ MANDATORY FIELD**

Enter clear reason for discount:
- ✅ "Merit scholarship - Board topper"
- ✅ "Financial assistance - Family hardship"
- ✅ "Staff ward benefit - 20% concession"
- ✅ "Sibling discount policy"
- ❌ "discount" (too vague)
- ❌ "ok" (not acceptable)

**Note:** Reason is permanently logged and cannot be changed later.

---

### Step 5: Preview Discount

**Discount Preview Box Shows:**
```
Current Pending:     ₹50,000.00
- Discount Amount:   ₹5,000.00
─────────────────────────────────
New Pending:         ₹45,000.00
```

**Verify:**
- ✅ Discount amount is correct
- ✅ New pending amount looks right
- ✅ Reason is clear and professional

---

### Step 6: Apply Discount

1. Click **"Apply Discount"** button
2. Confirmation popup appears
3. Review all details
4. Click **"Yes, Apply Discount"**

**Success Message:**
```
✅ Discount of ₹5,000.00 applied successfully! 
   New pending amount: ₹45,000.00
```

---

## 📊 What Happens After Applying?

### 1. Database Updates
- ✅ Student's `fees_pending` reduced by discount amount
- ✅ Discount recorded in `tbl_post_admission_discounts`
- ✅ Enrollment record updated with total discount

### 2. Audit Trail Created
**System Logs:**
- Who applied discount (User ID & Name)
- When (Date & Time)
- Student details
- Discount type, value, amount
- Reason/remarks
- Previous & new pending amounts
- IP address

### 3. History Tracking
- All discounts are permanently logged
- ❌ Cannot delete discount records
- ❌ Cannot edit applied discounts
- ✅ Can apply additional discounts later

---

## ⚠️ Important Rules & Validations

### ✅ Allowed:
- Apply multiple discounts to same student
- Give both percentage and fixed discounts
- Discount up to 100% of pending fees
- Discount partially paid students

### ❌ Not Allowed:
- Discount more than pending amount
- Negative discount values
- Delete or edit applied discounts
- Apply discount to inactive students
- Discount without proper reason

---

## 💡 Common Use Cases

### 1. Merit Scholarship (20%)
```
Type: Percentage
Value: 20
Reason: "Merit scholarship - 90% marks in previous exam"
```

### 2. Financial Hardship (Fixed)
```
Type: Fixed Amount
Value: 10000
Reason: "Financial assistance due to family emergency"
```

### 3. Staff Ward Benefit
```
Type: Percentage
Value: 50
Reason: "Staff ward benefit - Teacher's child policy"
```

### 4. Sibling Discount
```
Type: Percentage
Value: 10
Reason: "Sibling discount - Second child enrolled"
```

### 5. Partial Fee Waiver
```
Type: Fixed Amount
Value: 5000
Reason: "Partial fee waiver approved by management committee"
```

---

## 🔍 View Discount History

### On Post-Admission Discount Page:
**Discount History Table Shows:**
- Student Name & Enrollment Number
- Discount Type (% or Fixed)
- Discount Value Entered
- Actual Discount Amount (₹)
- Previous Pending Amount
- New Pending Amount
- Reason/Remarks
- Applied By (User name)
- Applied On (Date & Time)
- Status

### Filter & Search:
- Search by student name
- Filter by discount type
- Sort by date/amount
- Export to Excel

---

## 📝 Best Practices

### DO ✅
1. **Always verify student details** before applying discount
2. **Double-check calculations** in preview
3. **Write clear, specific reasons** for audit purposes
4. **Coordinate with accounts team** for payment tracking
5. **Keep discount policy consistent** across students
6. **Document special cases** in remarks

### DON'T ❌
1. ❌ Apply discount without proper approval
2. ❌ Use vague reasons like "discount", "ok", "approved"
3. ❌ Give excessive discounts without justification
4. ❌ Forget to inform student about discount
5. ❌ Apply discount to wrong student (no undo!)

---

## 🛠️ Troubleshooting

### Issue: Student not appearing in dropdown
**Solutions:**
- Check if student is enrolled (not just registered)
- Verify token fee is paid
- Ensure enrollment status is "active"
- Try searching by enrollment number directly

### Issue: Discount amount seems wrong
**Check:**
- Correct discount type selected (% vs ₹)
- Pending fees amount is current
- Calculations in preview are as expected
- No existing discounts affecting calculation

### Issue: Cannot apply discount
**Verify:**
- You have Principal or Super Admin role
- Reason field is filled
- Discount value is valid (positive, within limits)
- Student has pending fees to discount

### Issue: ₹ symbol showing as ?
**Solution:**
- Already fixed with UTF-8 headers
- Clear browser cache (Ctrl + Shift + Delete)
- Refresh page (Ctrl + F5)

---

## 📞 Support & Questions

### Need Help?
1. Contact Super Admin
2. Check system logs: `portal/common/logs/`
3. Review error messages displayed on screen
4. Refer to this guide

### Approval Process:
- **Principal:** Can apply discounts directly
- **Super Admin:** Can apply discounts directly
- **Others:** Request Principal/Super Admin to apply

---

## 📊 Reports & Monitoring

### View Discount Reports:
- Go to **Reports** section
- Select "Discount Report"
- Filter by date range, type, or student
- Export to Excel for analysis

### Monthly Review:
- Total discounts given
- Students benefited
- Discount types distribution
- Average discount amount
- Policy compliance check

---

## 🔒 Security & Compliance

### Audit Trail:
- All discounts permanently logged
- Cannot be deleted or modified
- User identification tracked
- IP address recorded
- Timestamp preserved

### Management Review:
- Monthly discount summary
- Policy adherence verification
- Unusual pattern detection
- Financial impact analysis

---

## ✅ Quick Checklist

Before applying discount:
- [ ] Student details verified
- [ ] Pending fees amount confirmed
- [ ] Discount type selected correctly
- [ ] Discount value entered accurately
- [ ] Clear reason documented
- [ ] Preview calculations checked
- [ ] Proper approval obtained (if required)
- [ ] Student/parent informed

After applying discount:
- [ ] Success message confirmed
- [ ] New pending amount noted
- [ ] Discount visible in history
- [ ] Accounts team notified
- [ ] Student records updated
- [ ] Receipt/acknowledgment issued

---

## 📱 Contact Information

**For Technical Issues:**
- System Administrator
- IT Support Team

**For Policy Questions:**
- Principal's Office
- Accounts Department
- Management Committee

---

**Last Updated:** January 17, 2026
**Version:** 2.0
**Prepared By:** System Documentation Team

---

## 🎯 Remember:

> **"With great power comes great responsibility"**
> 
> Post-admission discounts directly impact student fees and school finances.
> Always apply discounts thoughtfully, document properly, and maintain transparency.

✅ **Start applying discounts responsibly!**
