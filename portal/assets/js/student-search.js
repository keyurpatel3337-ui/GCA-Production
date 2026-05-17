/**
 * Student Search Component
 * Provides search functionality by ID or Mobile Number with auto-complete
 */

class StudentSearchComponent {
  constructor(config) {
    this.config = {
      inputId: config.inputId || 'student_search',
      hiddenInputId: config.hiddenInputId || 'student_id',
      resultsContainerId: config.resultsContainerId || 'student_search_results',
      detailsContainerId: config.detailsContainerId || 'student_details',
      apiUrl: config.apiUrl || this.getDefaultApiUrl(),
      useBackendApi: config.useBackendApi !== false, // Use backend API by default
      minChars: config.minChars || 2,
      onSelect: config.onSelect || null,
    };

    this.selectedStudent = null;
    this.searchTimeout = null;
    this.init();
  }

  getDefaultApiUrl() {
    // Determine the backend URL from environment or use relative path
    if (typeof BACKEND_URL !== 'undefined') {
      return BACKEND_URL + '/index.php?route=students/search';
    }
    // Fallback to relative backend path
    const depth = window.location.pathname.split('/').filter((p) => p).length;
    const backPath = '../'.repeat(Math.max(depth - 2, 1));
    return backPath + 'backend/index.php?route=students/search';
  }

  init() {
    this.createElements();
    this.attachEvents();
  }

  createElements() {
    // Find or create input element
    this.searchInput = document.getElementById(this.config.inputId);
    if (!this.searchInput) {
      console.error(`Search input with ID ${this.config.inputId} not found`);
      return;
    }

    // Find or create hidden input for student ID
    this.hiddenInput = document.getElementById(this.config.hiddenInputId);
    if (!this.hiddenInput) {
      this.hiddenInput = document.createElement('input');
      this.hiddenInput.type = 'hidden';
      this.hiddenInput.id = this.config.hiddenInputId;
      this.hiddenInput.name = this.config.hiddenInputId;
      this.searchInput.parentNode.appendChild(this.hiddenInput);
    }

    // Find or create results container
    this.resultsContainer = document.getElementById(
      this.config.resultsContainerId
    );
    if (!this.resultsContainer) {
      this.resultsContainer = document.createElement('div');
      this.resultsContainer.id = this.config.resultsContainerId;
      this.resultsContainer.className = 'student-search-results';
      this.searchInput.parentNode.appendChild(this.resultsContainer);
    }

    // Find details container
    this.detailsContainer = document.getElementById(
      this.config.detailsContainerId
    );
  }

  attachEvents() {
    // Search input event
    this.searchInput.addEventListener('input', (e) => {
      const searchTerm = e.target.value.trim();

      // Clear previous timeout
      clearTimeout(this.searchTimeout);

      // Clear selection if input is cleared
      if (searchTerm.length === 0) {
        this.clearSelection();
        this.hideResults();
        return;
      }

      // Wait before searching
      if (searchTerm.length >= this.config.minChars) {
        this.searchTimeout = setTimeout(() => {
          this.search(searchTerm);
        }, 300);
      } else {
        this.hideResults();
      }
    });

    // Close results on outside click
    document.addEventListener('click', (e) => {
      if (
        !this.searchInput.contains(e.target) &&
        !this.resultsContainer.contains(e.target)
      ) {
        this.hideResults();
      }
    });

    // Focus on search input
    this.searchInput.addEventListener('focus', () => {
      if (this.resultsContainer.children.length > 0) {
        this.resultsContainer.style.display = 'block';
      }
    });
  }

  async search(searchTerm) {
    try {
      let url;
      if (
        this.config.useBackendApi &&
        !this.config.apiUrl.includes('common/search-student.php')
      ) {
        // Using backend API - add search parameter
        url = this.config.apiUrl.includes('?')
          ? `${this.config.apiUrl}&search=${encodeURIComponent(searchTerm)}`
          : `${this.config.apiUrl}?search=${encodeURIComponent(searchTerm)}`;
      } else {
        // Using legacy frontend API
        url = `${this.config.apiUrl}?search=${encodeURIComponent(searchTerm)}`;
      }

      const response = await fetch(url);

      // Check if response is OK (status 200-299)
      if (!response.ok) {
        const errorText = await response.text();
        console.error('HTTP Error Response:', errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      // Check if response is JSON
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const responseText = await response.text();
        console.error('Expected JSON but got:', contentType);
        console.error('Response:', responseText.substring(0, 200));
        throw new Error(
          'Server returned non-JSON response. Please check if you are logged in.'
        );
      }

      const data = await response.json();

      if (data.success && data.students && data.students.length > 0) {
        this.displayResults(data.students);
      } else {
        this.showNoResults(data.message || 'No student found');
      }
    } catch (error) {
      console.error('Search error:', error);
      this.showError(error.message || 'Failed to search students');
    }
  }

  displayResults(students) {
    this.resultsContainer.innerHTML = '';
    this.resultsContainer.style.display = 'block';

    students.forEach((student) => {
      const resultItem = document.createElement('div');
      resultItem.className = 'student-search-result-item';
      const fullName = [
        student.surname,
        student.student_name,
        student.fathers_name,
      ]
        .filter(Boolean)
        .join(' ');
      resultItem.innerHTML = `
                <div class="student-name">${this.escapeHtml(fullName)}</div>
                <div class="student-info">
                    <span class="badge badge-info">ID: ${student.id}</span>
                    <span class="badge badge-secondary">Mobile: ${this.escapeHtml(
                      student.mob
                    )}</span>
                    ${
                      student.aadhaar
                        ? `<span class="badge badge-light">Aadhaar: ${this.escapeHtml(
                            student.aadhaar
                          )}</span>`
                        : ''
                    }
                </div>
            `;

      resultItem.addEventListener('click', () => {
        this.selectStudent(student);
      });

      this.resultsContainer.appendChild(resultItem);
    });
  }

  showNoResults(message) {
    this.resultsContainer.innerHTML = `<div class="student-search-no-results">${this.escapeHtml(
      message
    )}</div>`;
    this.resultsContainer.style.display = 'block';
  }

  showError(message) {
    this.resultsContainer.innerHTML = `<div class="student-search-error">${this.escapeHtml(
      message
    )}</div>`;
    this.resultsContainer.style.display = 'block';
  }

  selectStudent(student) {
    this.selectedStudent = student;
    const fullName = [student.surname, student.student_name, student.fathers_name]
      .filter(Boolean)
      .join(' ');
    this.searchInput.value = `${fullName} (${student.mob})`;
    this.hiddenInput.value = student.id;
    this.hideResults();
    this.displayStudentDetails(student);

    // Callback
    if (typeof this.config.onSelect === 'function') {
      this.config.onSelect(student);
    }

    // Trigger change event
    this.hiddenInput.dispatchEvent(new Event('change'));
  }

  displayStudentDetails(student) {
    if (!this.detailsContainer) return;

    this.detailsContainer.innerHTML = `
            <div class="card">
                <div class="card-header bg-primary-custom text-white">
                    <h5 class="mb-0"><i class="fas fa-user"></i> Student Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> ${this.escapeHtml(
                                [student.surname, student.student_name, student.fathers_name]
                                  .filter(Boolean)
                                  .join(' ')
                            )}</p>
                            <p><strong>ID:</strong> ${student.id}</p>
                            <p><strong>Mobile:</strong> ${this.escapeHtml(
                              student.mob
                            )}</p>
                        </div>
                        <div class="col-md-6">
                            ${
                              student.fathers_name
                                ? `<p><strong>Father's Name:</strong> ${this.escapeHtml(
                                    student.fathers_name
                                  )}</p>`
                                : ''
                            }
                            ${
                              student.aadhaar
                                ? `<p><strong>Aadhaar:</strong> ${this.escapeHtml(
                                    student.aadhaar
                                  )}</p>`
                                : ''
                            }
                            ${
                              student.dob
                                ? `<p><strong>DOB:</strong> ${student.dob}</p>`
                                : ''
                            }
                        </div>
                    </div>
                    ${
                      student.addr
                        ? `<p><strong>Address:</strong> ${this.escapeHtml(
                            student.addr
                          )}</p>`
                        : ''
                    }
                </div>
            </div>
        `;
    this.detailsContainer.style.display = 'block';
  }

  clearSelection() {
    this.selectedStudent = null;
    this.hiddenInput.value = '';
    if (this.detailsContainer) {
      this.detailsContainer.innerHTML = '';
      this.detailsContainer.style.display = 'none';
    }
  }

  hideResults() {
    this.resultsContainer.style.display = 'none';
  }

  escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    };
    return String(text).replace(/[&<>"']/g, (m) => map[m]);
  }

  reset() {
    this.searchInput.value = '';
    this.clearSelection();
    this.hideResults();
  }

  getSelectedStudent() {
    return this.selectedStudent;
  }
}

// jQuery plugin wrapper for backward compatibility
if (typeof jQuery !== 'undefined') {
  (function ($) {
    $.fn.studentSearch = function (options) {
      return this.each(function () {
        const config = {
          inputId: $(this).attr('id'),
          hiddenInputId: options.hiddenInputId || 'student_id',
          resultsContainerId:
            options.resultsContainerId || $(this).attr('id') + '_results',
          detailsContainerId: options.detailsContainerId,
          apiUrl: options.apiUrl,
          useBackendApi: options.useBackendApi !== false,
          minChars: options.minChars || 2,
          onSelect: options.onSelect,
        };

        $(this).data('studentSearch', new StudentSearchComponent(config));
      });
    };
  })(jQuery);
}
