/**
 * Delete Handler - Centralized functions for delete operations
 * Replaces 18+ deleteItem() duplicates across modules
 * 
 * @author Refactoring Script
 * @version 1.0.0
 */

(function(window) {
    'use strict';

    const DeleteHandler = {
        /**
         * Delete single item with confirmation
         * @param {number|string} id - Item ID to delete
         * @param {string} apiEndpoint - Backend API route (e.g., 'settings/course-delete')
         * @param {string} itemName - Human-readable item name for messages (e.g., 'course')
         * @param {object} options - Optional configuration
         * @param {function} options.onSuccess - Callback on successful delete
         * @param {function} options.onError - Callback on error
         * @param {boolean} options.reload - Reload page after delete (default: true)
         */
        deleteItem: function(id, apiEndpoint, itemName, options = {}) {
            const defaults = {
                onSuccess: null,
                onError: null,
                reload: true,
                confirmText: `Are you sure you want to delete this ${itemName || 'item'}?`,
                successTitle: 'Deleted!',
                successText: `${itemName || 'Item'} deleted successfully.`
            };
            const config = { ...defaults, ...options };

            const performDelete = () => {
                // Make API call
                if (typeof jQuery !== 'undefined' && typeof jQuery.api !== 'undefined') {
                    jQuery.api.post(apiEndpoint, { id: id })
                        .then(response => {
                            if (response.success) {
                                if (typeof showToast === 'function') {
                                    showToast('success', config.successTitle, response.message || config.successText);
                                }
                                
                                setTimeout(() => {
                                    if (config.onSuccess) {
                                        config.onSuccess(response);
                                    } else if (config.reload) {
                                        location.reload();
                                    }
                                }, 1500);
                            } else {
                                if (typeof showToast === 'function') {
                                    showToast('error', 'Error!', response.message || 'Failed to delete');
                                } else {
                                    alert(response.message || 'Failed to delete');
                                }
                                if (config.onError) config.onError(response);
                            }
                        })
                        .catch(error => {
                            console.error('Delete Error:', error);
                            if (typeof showToast === 'function') {
                                showToast('error', 'Error!', error.message || 'An error occurred');
                            } else {
                                alert(error.message || 'An error occurred');
                            }
                            if (config.onError) config.onError(error);
                        });
                } else {
                    console.error('DeleteHandler: jQuery.api is required');
                }
            };

            if (typeof showConfirm === 'function') {
                showConfirm({
                    title: 'Delete ' + (itemName ? itemName.charAt(0).toUpperCase() + itemName.slice(1) : 'Item'),
                    message: config.confirmText,
                    confirmText: 'Yes, Delete',
                    confirmButtonClass: 'btn-danger',
                    onConfirm: performDelete
                });
            } else if (confirm(config.confirmText)) {
                performDelete();
            }
        },

        /**
         * Delete multiple selected items with confirmation
         * @param {string} rowCheckboxClass - Class of row checkboxes (without .)
         * @param {string} apiEndpoint - Backend API route (e.g., 'settings/courses-delete-multiple')
         * @param {string} itemName - Human-readable item name (e.g., 'course')
         * @param {object} options - Optional configuration
         */
        deleteSelected: function(rowCheckboxClass, apiEndpoint, itemName, options = {}) {
            const defaults = {
                onSuccess: null,
                onError: null,
                reload: true,
                idField: 'ids'
            };
            const config = { ...defaults, ...options };

            // Get selected IDs
            let selectedIds = [];
            if (typeof window.TableUtils !== 'undefined') {
                selectedIds = window.TableUtils.getSelectedIds(rowCheckboxClass);
            } else if (typeof jQuery !== 'undefined') {
                jQuery(`.${rowCheckboxClass}:checked`).each(function() {
                    selectedIds.push(jQuery(this).val());
                });
            }

            if (selectedIds.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('warning', 'Warning', `Please select at least one ${itemName || 'item'} to delete`);
                } else {
                    alert(`Please select at least one ${itemName || 'item'} to delete`);
                }
                return;
            }

            const performDelete = () => {
                const payload = {};
                payload[config.idField] = selectedIds;

                if (typeof jQuery !== 'undefined' && typeof jQuery.api !== 'undefined') {
                    jQuery.api.post(apiEndpoint, payload)
                        .then(response => {
                            if (response.success) {
                                if (typeof showToast === 'function') {
                                    showToast('success', 'Deleted!', response.message || `${selectedIds.length} ${itemName}(s) deleted successfully.`);
                                }
                                
                                setTimeout(() => {
                                    if (config.onSuccess) {
                                        config.onSuccess(response);
                                    } else if (config.reload) {
                                        location.reload();
                                    }
                                }, 1500);
                            } else {
                                if (typeof showToast === 'function') {
                                    showToast('error', 'Error!', response.message || 'Failed to delete');
                                } else {
                                    alert(response.message || 'Failed to delete');
                                }
                                if (config.onError) config.onError(response);
                            }
                        })
                        .catch(error => {
                            console.error('Delete Error:', error);
                            if (typeof showToast === 'function') {
                                showToast('error', 'Error!', error.message || 'An error occurred');
                            } else {
                                alert(error.message || 'An error occurred');
                            }
                            if (config.onError) config.onError(error);
                        });
                }
            };

            const confirmMsg = `Are you sure you want to delete ${selectedIds.length} selected ${itemName || 'item'}(s)?`;
            if (typeof showConfirm === 'function') {
                showConfirm({
                    title: 'Delete Selected Items',
                    message: confirmMsg,
                    confirmText: 'Yes, Delete',
                    confirmButtonClass: 'btn-danger',
                    onConfirm: performDelete
                });
            } else if (confirm(confirmMsg)) {
                performDelete();
            }
        },

        /**
         * Simple confirm and redirect delete (for non-AJAX deletes)
         * @param {number|string} id - Item ID
         * @param {string} redirectUrl - URL to redirect to (with placeholder :id)
         * @param {string} itemName - Human-readable item name
         */
        deleteWithRedirect: function(id, redirectUrl, itemName) {
            const confirmMsg = `Are you sure you want to delete this ${itemName || 'item'}? This action cannot be undone.`;
            if (typeof showConfirm === 'function') {
                showConfirm({
                    title: 'Delete ' + (itemName ? itemName.charAt(0).toUpperCase() + itemName.slice(1) : 'Item'),
                    message: confirmMsg,
                    confirmText: 'Yes, Delete',
                    confirmButtonClass: 'btn-danger',
                    onConfirm: function() {
                         window.location.href = redirectUrl.replace(':id', id);
                    }
                });
            } else if (confirm(confirmMsg)) {
                window.location.href = redirectUrl.replace(':id', id);
            }
        }
    };

    // Expose to global scope
    window.DeleteHandler = DeleteHandler;

    // jQuery plugin wrapper for convenience
    if (typeof jQuery !== 'undefined') {
        jQuery.deleteHandler = DeleteHandler;
    }

})(window);
