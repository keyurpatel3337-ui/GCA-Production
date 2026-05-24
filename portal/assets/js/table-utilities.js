/**
 * Table Utilities - Centralized functions for table operations
 * Replaces 1700+ exportToExcel(), 4100+ selectAll, 3900+ toggleDeleteButton duplicates
 * 
 * @author Refactoring Script
 * @version 1.0.0
 */

(function(window) {
    'use strict';

    const TableUtils = {
        /**
         * Export table to Excel or CSV file
         * @param {string} tableId - ID of the table element
         * @param {string} filename - Base filename for export (without extension)
         * @param {object} options - Optional configuration
         * @param {boolean} options.removeFirstColumn - Remove checkbox column (default: true)
         * @param {boolean} options.removeLastColumn - Remove actions column (default: true)
         * @param {boolean} options.showSuccess - Show success message (default: true)
         * @param {string} options.format - 'xlsx' or 'csv' (default: 'xlsx' if SheetJS exists, else 'csv')
         */
        exportToExcel: function(tableId, filename, options = {}) {
            const defaults = {
                removeFirstColumn: true,
                removeLastColumn: true,
                showSuccess: true,
                format: typeof XLSX !== 'undefined' ? 'xlsx' : 'csv'
            };
            const config = { ...defaults, ...options };
            
            const table = document.getElementById(tableId);
            if (!table) {
                console.group('TableUtils Error');
                console.error(`Table with ID "${tableId}" not found`);
                console.groupEnd();
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', `Table "${tableId}" not found`);
                }
                return false;
            }

            // Clone table to avoid modifying original
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = table.outerHTML;
            const tempTable = tempDiv.querySelector('table');

            // Remove columns if specified
            tempTable.querySelectorAll('tr').forEach(row => {
                // Remove first column (usually checkboxes)
                if (config.removeFirstColumn && row.cells.length > 0) {
                    row.deleteCell(0);
                }
                // Remove last column (usually actions)
                if (config.removeLastColumn && row.cells.length > 0) {
                    row.deleteCell(row.cells.length - 1);
                }
            });

            // Use SheetJS if available for real XLSX
            if (config.format === 'xlsx' && typeof XLSX !== 'undefined') {
                const wb = XLSX.utils.table_to_book(tempTable, { sheet: "Sheet1" });
                XLSX.writeFile(wb, `${filename || 'export'}_${new Date().toISOString().slice(0, 10)}.xlsx`);
            } else {
                // Fallback to CSV (much better than "fake .xls" HTML)
                const csvData = [];
                const rows = tempTable.querySelectorAll('tr');
                
                for (let i = 0; i < rows.length; i++) {
                    const row = [], cols = rows[i].querySelectorAll('td, th');
                    for (let j = 0; j < cols.length; j++) {
                        // Clean text: remove multiple spaces, newlines, and escape quotes
                        let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").replace(/"/g, '""').trim();
                        row.push('"' + data + '"');
                    }
                    csvData.push(row.join(","));
                }

                const csvString = csvData.join("\n");
                const blob = new Blob(['\ufeff', csvString], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const exportFilename = filename || 'export';
                const dateStr = new Date().toISOString().slice(0, 10);
                
                link.href = URL.createObjectURL(blob);
                link.download = `${exportFilename}_${dateStr}.csv`;
                link.click();
                URL.revokeObjectURL(link.href);
            }

            if (config.showSuccess && typeof showToast === 'function') {
                showToast('success', 'Success', `Data exported to ${config.format === 'xlsx' ? 'Excel' : 'CSV'} successfully!`);
            }

            return true;
        },

        /**
         * Initialize Select All checkbox functionality
         * @param {string} selectAllId - ID of the "Select All" checkbox (without #)
         * @param {string} rowCheckboxClass - Class of row checkboxes (without .)
         * @param {string} deleteButtonId - ID of delete button to toggle (without #)
         */
        initSelectAll: function(selectAllId, rowCheckboxClass, deleteButtonId) {
            const selectAllSelector = `#${selectAllId}`;
            const rowCheckboxSelector = `.${rowCheckboxClass}`;
            const deleteButtonSelector = deleteButtonId ? `#${deleteButtonId}` : null;

            // Use jQuery if available for better compatibility
            if (typeof jQuery !== 'undefined') {
                const $ = jQuery;

                // Select All checkbox handler
                $(selectAllSelector).off('change.tableUtils').on('change.tableUtils', function() {
                    $(rowCheckboxSelector).prop('checked', $(this).prop('checked'));
                    if (deleteButtonSelector) {
                        TableUtils.toggleDeleteButton(rowCheckboxClass, deleteButtonId);
                    }
                });

                // Individual row checkbox handler
                $(document).off('change.tableUtils', rowCheckboxSelector).on('change.tableUtils', rowCheckboxSelector, function() {
                    // Update selectAll state
                    const totalCheckboxes = $(rowCheckboxSelector).length;
                    const checkedCheckboxes = $(rowCheckboxSelector + ':checked').length;
                    $(selectAllSelector).prop('checked', totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0);
                    
                    if (deleteButtonSelector) {
                        TableUtils.toggleDeleteButton(rowCheckboxClass, deleteButtonId);
                    }
                });
            } else {
                // Vanilla JS fallback
                const selectAll = document.getElementById(selectAllId);
                if (!selectAll) return;

                selectAll.addEventListener('change', function() {
                    document.querySelectorAll(rowCheckboxSelector).forEach(cb => {
                        cb.checked = this.checked;
                    });
                    if (deleteButtonSelector) {
                        TableUtils.toggleDeleteButton(rowCheckboxClass, deleteButtonId);
                    }
                });

                document.addEventListener('change', function(e) {
                    if (e.target.matches(rowCheckboxSelector)) {
                        const all = document.querySelectorAll(rowCheckboxSelector);
                        const checked = document.querySelectorAll(rowCheckboxSelector + ':checked');
                        document.getElementById(selectAllId).checked = all.length === checked.length && all.length > 0;
                        
                        if (deleteButtonSelector) {
                            TableUtils.toggleDeleteButton(rowCheckboxClass, deleteButtonId);
                        }
                    }
                });
            }
        },

        /**
         * Toggle delete button visibility based on checkbox selections
         * @param {string} rowCheckboxClass - Class of row checkboxes (without .)
         * @param {string} deleteButtonId - ID of delete button (without #)
         */
        toggleDeleteButton: function(rowCheckboxClass, deleteButtonId) {
            const rowCheckboxSelector = `.${rowCheckboxClass}`;
            const deleteButton = document.getElementById(deleteButtonId);
            
            if (!deleteButton) return;

            if (typeof jQuery !== 'undefined') {
                const checkedCount = jQuery(`${rowCheckboxSelector}:checked`).length;
                jQuery(`#${deleteButtonId}`).toggle(checkedCount > 0);
            } else {
                const checkedCount = document.querySelectorAll(`${rowCheckboxSelector}:checked`).length;
                deleteButton.style.display = checkedCount > 0 ? '' : 'none';
            }
        },

        /**
         * Get selected row values
         * @param {string} rowCheckboxClass - Class of row checkboxes (without .)
         * @returns {Array} Array of selected checkbox values
         */
        getSelectedIds: function(rowCheckboxClass) {
            const rowCheckboxSelector = `.${rowCheckboxClass}`;
            const ids = [];

            if (typeof jQuery !== 'undefined') {
                jQuery(`${rowCheckboxSelector}:checked`).each(function() {
                    ids.push(jQuery(this).val());
                });
            } else {
                document.querySelectorAll(`${rowCheckboxSelector}:checked`).forEach(cb => {
                    ids.push(cb.value);
                });
            }

            return ids;
        },

        /**
         * Reset all checkboxes in table
         * @param {string} selectAllId - ID of "Select All" checkbox (without #)
         * @param {string} rowCheckboxClass - Class of row checkboxes (without .)
         * @param {string} deleteButtonId - ID of delete button (without #)
         */
        resetSelection: function(selectAllId, rowCheckboxClass, deleteButtonId) {
            if (typeof jQuery !== 'undefined') {
                jQuery(`#${selectAllId}`).prop('checked', false);
                jQuery(`.${rowCheckboxClass}`).prop('checked', false);
                if (deleteButtonId) {
                    jQuery(`#${deleteButtonId}`).hide();
                }
            } else {
                const selectAll = document.getElementById(selectAllId);
                if (selectAll) selectAll.checked = false;
                
                document.querySelectorAll(`.${rowCheckboxClass}`).forEach(cb => {
                    cb.checked = false;
                });
                
                if (deleteButtonId) {
                    const btn = document.getElementById(deleteButtonId);
                    if (btn) btn.style.display = 'none';
                }
            }
        }
    };

    // Expose to global scope
    window.TableUtils = TableUtils;

    // jQuery plugin wrapper for convenience
    if (typeof jQuery !== 'undefined') {
        jQuery.tableUtils = TableUtils;
    }

})(window);
