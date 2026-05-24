/**
 * Receipt Utilities
 * Shared functions for receipt generation and management
 */

/**
 * Downloads a receipt PDF securely
 * @param {number|string} receiptNoOrId - The Receipt Number (for students) or Payment ID (for staff)
 * @param {string} [feeComponent] - Optional fee component (for students)
 * @param {number|string} [studentId] - Optional student ID (for students)
 */
function downloadReceipt(receiptNoOrId, feeComponent = null, studentId = null) {
    if (typeof generateSecurePDF === 'function') {
        const url = (typeof PORTAL_URL !== 'undefined' ? PORTAL_URL : '../..') + '/modules/payments/receipt-print-pdf.php';
        
        let params = {};
        if (feeComponent) {
            // Student/Parent format
            params = {
                receipt_no: receiptNoOrId,
                fee_component: feeComponent,
                student_id: studentId || (typeof user_id !== 'undefined' ? user_id : '')
            };
        } else {
            // Administrative/Accountant format (internal ID)
            params = { id: receiptNoOrId };
        }
        
        generateSecurePDF(url, params);
    } else {
        console.error('generateSecurePDF is not defined. Ensure footer.php is included.');
        if (typeof showToast === 'function') {
            showToast('error', 'Error', 'PDF Generator not initialized.');
        } else {
            alert('PDF Generator not initialized.');
        }
    }
}

/**
 * Common logic for cancelling a receipt
 * Note: Individual pages may override this for custom reason lists
 */
function commonCancelReceipt(paymentId, receiptNo, onCancelled) {
    // This is a placeholder for common cancellation logic if needed
    console.log('Cancel requested for receipt:', receiptNo);
}
