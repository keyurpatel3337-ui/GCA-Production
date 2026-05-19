/**
 * Frontend API Client for Backend Communication
 * JavaScript/ES6 implementation for AJAX requests
 */

if (typeof CounsellingAPI === 'undefined') {
  class CounsellingAPI {
    constructor() {
      // Use BACKEND_URL from environment config (defined in header.php)
      this.baseURL =
        typeof BACKEND_URL !== 'undefined'
          ? BACKEND_URL
          : 'http://localhost/counselling/counselling-backend';
    }

    /**
     * Make an API request
     * @param {string} route - API route (e.g., 'dashboard/admin')
     * @param {object} options - Request options
     * @returns {Promise<object>}
     */
    async request(route, options = {}) {
      const url = `${this.baseURL}/index.php?route=${route}`;

      const config = {
        method: options.method || 'GET',
        headers: {
          Accept: 'application/json',
          ...options.headers,
        },
        credentials: 'include', // Important for session cookies
      };

      // Add request body for POST requests
      if (options.body) {
        if (options.body instanceof FormData) {
          config.body = options.body;
          // Note: Don't set Content-Type header for FormData - browser will set it with boundary
        } else {
          config.headers['Content-Type'] = 'application/json';
          config.body = typeof options.body === 'string' ? options.body : JSON.stringify(options.body);
        }
      }

      // Build URL with query parameters for GET requests
      let finalUrl = url;
      if (options.params) {
        const queryString = new URLSearchParams(options.params).toString();
        finalUrl = `${url}&${queryString}`;
      }

      try {
        const response = await fetch(finalUrl, config);
        const data = await response.json();

        if (!response.ok) {
          throw new Error(
            data.error || `HTTP ${response.status}: ${response.statusText}`
          );
        }

        return data;
      } catch (error) {
        console.error('API Error:', error);
        throw error;
      }
    }

    /**
     * GET request helper
     */
    get(route, params = {}) {
      return this.request(route, { method: 'GET', params });
    }

    /**
     * POST request helper
     */
    post(route, body = {}) {
      return this.request(route, { method: 'POST', body });
    }

    // ==================== Dashboard APIs ====================

    getAdminDashboard() {
      return this.get('dashboard/admin');
    }

    getPrincipalDashboard() {
      return this.get('dashboard/principle');
    }

    getCounsellorDashboard() {
      return this.get('dashboard/counsellor');
    }

    getAccountantDashboard() {
      return this.get('dashboard/accountant');
    }

    getStudentDashboard() {
      return this.get('dashboard/student');
    }

    // ==================== Students APIs ====================

    getStudentsList(filters = {}) {
      return this.get('students/list', filters);
    }

    getStudentDetails(studentId) {
      return this.get('students/details', { id: studentId });
    }

    saveStudent(studentData) {
      return this.post('students/save', studentData);
    }

    updateStudent(studentData) {
      return this.post('students/update', studentData);
    }

    getEnrolledStudents(filters = {}) {
      return this.get('students/enrolled', filters);
    }

    getRegisteredStudents(filters = {}) {
      return this.get('students/registered', filters);
    }

    confirmAdmission(data) {
      return this.post('students/admission-confirm-save', data);
    }

    deleteStudents(studentIds) {
      return this.post('students/delete-multiple', { student_ids: studentIds });
    }

    // ==================== Counsellor Assignment APIs ====================

    /**
     * Get all active counsellors
     */
    getCounsellors() {
      return this.localPost(
        'modules/students/api.php?action=get-counsellors',
        {}
      );
    }

    /**
     * Assign counsellor to a student
     */
    assignCounsellor(studentId, counsellorId, action = 'assign') {
      return this.localPost(
        'modules/students/api.php?action=counsellor-assign',
        {
          student_id: studentId,
          counsellor_id: counsellorId,
          assign_action: action,
        }
      );
    }

    /**
     * Bulk assign counsellor to multiple students
     */
    bulkAssignCounsellor(counsellorId, studentIds) {
      return this.localPost(
        'modules/students/api.php?action=counsellor-bulk-assign',
        {
          counsellor_id: counsellorId,
          student_ids: studentIds,
        }
      );
    }

    /**
     * Auto assign students to counsellors
     */
    autoAssignCounsellors(studentsPerCounsellor) {
      return this.localPost(
        'modules/students/api.php?action=counsellor-auto-assign',
        {
          students_per_counsellor: studentsPerCounsellor,
        }
      );
    }

    /**
     * Preview students by mobile numbers
     */
    previewStudentsByMobile(mobileNumbers) {
      return this.localPost(
        'modules/students/api.php?action=preview-by-mobile',
        {
          mobile_numbers: mobileNumbers,
        }
      );
    }

    /**
     * Make a local POST request (to portal endpoints)
     */
    async localPost(endpoint, data = {}) {
      const url = `${window.location.origin}/counselling/portal/${endpoint}`;

      try {
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify(data),
          credentials: 'include',
        });
        const result = await response.json();
        return result;
      } catch (error) {
        console.error('API Error:', error);
        throw error;
      }
    }

    /**
     * Upload FormData (for file uploads)
     * @param {string} route - API route
     * @param {FormData} formData - FormData object with file and other fields
     * @returns {Promise<object>}
     */
    async uploadFormData(route, formData) {
      const url = `${this.baseURL}/index.php?route=${route}`;

      try {
        const response = await fetch(url, {
          method: 'POST',
          body: formData,
          credentials: 'include',
          // Note: Don't set Content-Type header - browser will set it with boundary
        });
        const data = await response.json();

        if (!response.ok) {
          throw new Error(
            data.error || `HTTP ${response.status}: ${response.statusText}`
          );
        }

        return data;
      } catch (error) {
        console.error('API Upload Error:', error);
        throw error;
      }
    }

    /**
     * Bulk upload students via CSV
     * @param {FormData} formData - FormData with csv_file and other fields
     * @returns {Promise<object>}
     */
    bulkUploadStudents(formData) {
      return this.uploadFormData('students/bulk-upload', formData);
    }

    // ==================== Payments APIs ====================

    initiatePayment(paymentData) {
      return this.post('payments/initiate', paymentData);
    }

    getPaymentHistory(studentId) {
      return this.get('payments/history', { student_id: studentId });
    }

    getPaymentReceipt(paymentId) {
      return this.get('payments/receipt', { payment_id: paymentId });
    }

    // ==================== Fees APIs ====================

    getFeeConfig() {
      return this.get('fees/config');
    }

    getFeeStructure(schoolId, courseId) {
      return this.get('fees/structure', {
        school_id: schoolId,
        course_id: courseId,
      });
    }

    getFeeList(filters = {}) {
      return this.get('fees/list', filters);
    }

    // ==================== Settings APIs ====================

    getSettings(type) {
      return this.get(`settings/${type}`);
    }

    saveSettings(type, data) {
      return this.post(`settings/${type}`, data);
    }

    getAcademicYears() {
      return this.get('settings/academic-years');
    }

    getBoards() {
      return this.get('settings/boards');
    }

    getCourses() {
      return this.get('settings/courses');
    }

    getGroups() {
      return this.get('settings/groups');
    }

    getMediums() {
      return this.get('settings/mediums');
    }

    getSchools() {
      return this.get('settings/schools');
    }

    // ==================== Profile APIs ====================

    getProfile() {
      return this.get('profile/view');
    }

    updateProfile(profileData) {
      return this.post('profile/update', profileData);
    }

    // ==================== Scholarships APIs ====================

    getScholarships() {
      return this.get('scholarships/list');
    }

    addScholarship(scholarshipData) {
      return this.post('scholarships/add', scholarshipData);
    }

    // ==================== Hostel APIs ====================

    getHostelList() {
      return this.get('hostel/list');
    }

    manageHostel(data) {
      return this.post('hostel/manage', data);
    }
  }

  // Create global instance
  const api = new CounsellingAPI();

  // jQuery-based helpers for backward compatibility
  if (typeof jQuery !== 'undefined') {
    (function ($) {
      const backendUrl =
        typeof BACKEND_URL !== 'undefined'
          ? BACKEND_URL
          : 'http://localhost/counselling/counselling-backend';

      $.api = {
        get: function (route, params) {
          return $.ajax({
            url: `${backendUrl}/index.php?route=${route}`,
            type: 'GET',
            data: params,
            dataType: 'json',
            xhrFields: {
              withCredentials: true,
            },
          });
        },
        post: function (route, data, options = {}) {
          // Check if data is FormData (for file uploads)
          if (data instanceof FormData) {
            return $.ajax({
              url: `${backendUrl}/index.php?route=${route}`,
              type: 'POST',
              data: data,
              processData: false,
              contentType: false,
              dataType: 'json',
              xhrFields: {
                withCredentials: true,
              },
            });
          }

          let ajaxOptions = {
            url: `${backendUrl}/index.php?route=${route}`,
            type: 'POST',
            dataType: 'json',
            xhrFields: {
              withCredentials: true,
            },
          };

          if (typeof data === 'string') {
            // If it's a string, assume it's already serialized (like from $(form).serialize())
            ajaxOptions.data = data;
            ajaxOptions.contentType = 'application/x-www-form-urlencoded';
          } else {
            // Otherwise stringify it as JSON
            ajaxOptions.data = JSON.stringify(data);
            ajaxOptions.contentType = 'application/json';
          }

          return $.ajax(ajaxOptions);
        },
        // Dedicated method for FormData uploads
        postFormData: function (route, formData) {
          return $.ajax({
            url: `${backendUrl}/index.php?route=${route}`,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            xhrFields: {
              withCredentials: true,
            },
          });
        },
      };
    })(jQuery);
  }

  // Expose CounsellingAPI globally
  window.CounsellingAPI = CounsellingAPI;
} // End of CounsellingAPI class wrapper

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
  module.exports = CounsellingAPI;
}
