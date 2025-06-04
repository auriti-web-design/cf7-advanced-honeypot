/**
 * CF7 Advanced Honeypot - Admin JavaScript
 * Version: 1.3.0
 *
 * Handles all admin interface interactions including:
 * - Custom question management
 * - Form validation
 * - Dynamic UI elements
 * - Bulk action management
 * - Country selection
 * - Tooltips and notifications
 */

(function ($) {
    'use strict';

    /**
     * Main admin functionality object
     * Handles all administrative interface interactions
     */
    const CF7HoneypotAdmin = {
        /**
         * Initialize all admin functionalities
         * This is the main entry point of the admin interface
         */
        init() {
            this.initTabs();
            this.initCustomQuestions();
            this.initFormValidation();
            this.initTooltips();
            this.initDynamicToggles();
            this.initCountrySelect();
            this.initBulkActions();
            this.initBlockedIps();
        },

        /**
         * Initialize Select2 for country selection
         * Enhances the country selection dropdown with search and multi-select capabilities
         */
        initCountrySelect() {
            $('.country-select').select2({
                placeholder: 'Select countries to block...',
                closeOnSelect: false,
                width: '100%',
                templateResult: function (state) {
                    if (!state.id) return state.text;
                    return $('<span>' + state.text + '</span>');
                }
            });
        },

        /**
         * Initialize tab navigation functionality
         * Handles tab switching and URL hash management
         */
        initTabs() {
            const $tabs = $('.nav-tab');
            const $contents = $('.tab-content');

            // Handle tab click events
            $tabs.on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');

                $tabs.removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                $contents.hide();
                $(target).fadeIn(300);

                // Update URL without page reload
                history.pushState({}, '', target);
            });

            // Handle direct URL access with hash
            if (window.location.hash) {
                $(`a[href="${window.location.hash}"]`).trigger('click');
            }
        },

        /**
         * Initialize custom questions management
         * Handles adding and removing custom honeypot questions
         */
        initCustomQuestions() {
            const container = $('#custom-questions-container');
            const template = this.getQuestionTemplate();

            // Handle add question button
            $('#add-question').on('click', () => {
                const newRow = $(template(container.children().length));
                container.append(newRow);
                newRow.hide().slideDown(300);
            });

            // Handle remove question button
            container.on('click', '.remove-question', function (e) {
                const row = $(this).closest('.question-row');
                row.slideUp(300, () => row.remove());
            });
        },

        /**
         * Get the HTML template for a new question row
         * @returns {Function} Template function that takes an index parameter
         */
        getQuestionTemplate() {
            return (index) => `
                <div class="question-row">
                    <input type="text"
                           name="cf7_honeypot_settings[custom_questions][${index}][question]"
                           placeholder="Question"
                           class="question-input">
                    <input type="text"
                           name="cf7_honeypot_settings[custom_questions][${index}][answer]"
                           placeholder="Answer"
                           class="answer-input">
                    <button type="button" class="button remove-question">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `;
        },

        /**
         * Initialize form validation
         * Handles real-time and submission validation of settings forms
         */
        initFormValidation() {
            const form = $('.settings-form');

            form.on('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    this.showValidationErrors();
                }
            });

            // Real-time field validation
            form.on('input', 'input, textarea', function () {
                const field = $(this);
                this.validateField(field);
            }.bind(this));
        },

        /**
         * Validate entire form
         * @param {jQuery} form - The form jQuery object to validate
         * @returns {boolean} True if form is valid, false otherwise
         */
        validateForm(form) {
            let isValid = true;

            // Validate required fields
            form.find('[required]').each((i, field) => {
                if (!this.validateField($(field))) {
                    isValid = false;
                }
            });

            // Validate email fields
            form.find('input[type="email"]').each((i, field) => {
                if (field.value && !this.isValidEmail(field.value)) {
                    isValid = false;
                    this.addError($(field), 'Invalid email format');
                }
            });

            return isValid;
        },

        /**
         * Validate individual form field
         * @param {jQuery} $field - Field jQuery object to validate
         * @returns {boolean} True if field is valid, false otherwise
         */
        validateField($field) {
            const value = $field.val().trim();

            if ($field.prop('required') && !value) {
                this.addError($field, 'This field is required');
                return false;
            }

            this.removeError($field);
            return true;
        },

        /**
         * Add error message to a field
         * @param {jQuery} $field - Field to add error to
         * @param {string} message - Error message to display
         */
        addError($field, message) {
            if (!$field.next('.error-message').length) {
                $field
                    .addClass('has-error')
                    .after(`<span class="error-message">${message}</span>`);
            }
        },

        /**
         * Remove error message from a field
         * @param {jQuery} $field - Field to remove error from
         */
        removeError($field) {
            $field
                .removeClass('has-error')
                .next('.error-message')
                .remove();
        },

        /**
         * Initialize tooltips functionality
         * Handles showing/hiding of tooltip content
         */
        initTooltips() {
            $('.cf7-honeypot-tooltip').each(function () {
                const $tooltip = $(this);
                const $content = $tooltip.find('.tooltip-content');

                $tooltip
                    .on('mouseenter focus', () => {
                        $content.css({
                            opacity: 1,
                            visibility: 'visible',
                            transform: 'translateX(-50%) translateY(-8px)'
                        });
                    })
                    .on('mouseleave blur', () => {
                        $content.css({
                            opacity: 0,
                            visibility: 'hidden',
                            transform: 'translateX(-50%) translateY(0)'
                        });
                    });
            });
        },

        /**
         * Initialize dynamic UI toggles
         * Handles advanced options and protection level toggles
         */
        initDynamicToggles() {
            // Advanced options toggle
            $('[data-toggle]').on('change', function () {
                const target = $(this).data('toggle');
                $(target).slideToggle(300);
            });

            // Protection level handling
            $('.protection-level-select').on('change', function () {
                const level = $(this).val();
                const row = $(this).closest('.form-setting-row');

                row.find('.protection-options')
                    .removeClass('low medium high')
                    .addClass(level);
            });
        },

        /**
         * Initialize bulk actions functionality
         * Handles bulk selection and deletion of records
         */
        initBulkActions() {

            $('#doaction').on('click', (e) => {
                e.preventDefault();
                const action = $('#bulk-action-selector-bottom').val();
                if (action !== 'delete') return;

                const selectedIds = $('input[name="bulk-delete[]"]:checked')
                    .map(function () {
                        return $(this).val();
                    }).get();

                if (selectedIds.length === 0) {
                    alert('Please select at least one record to delete');
                    return;
                }

                if (confirm('Are you sure you want to delete the selected records?')) {
                    this.deleteSelectedRecords(selectedIds);
                }
            });

            // Gestisci selezione "Select All"
            $('#cb-select-all-1').on('change', function () {
                const isChecked = $(this).prop('checked');
                $('input[name="bulk-delete[]"]').prop('checked', isChecked);
            });
        },


        /**
         * Update bulk action button state
         * Enables/disables based on selection state
         */
        updateBulkActionButton() {
            const checkedCount = $('input[name="bulk-delete[]"]:checked').length;
            $('#doaction').prop('disabled', checkedCount === 0);
        },

        /**
         * Update select all checkbox state
         * Handles indeterminate state for partial selections
         */
        updateSelectAllCheckbox() {
            const totalCheckboxes = $('input[name="bulk-delete[]"]').length;
            const checkedCheckboxes = $('input[name="bulk-delete[]"]:checked').length;
            $('#cb-select-all-1').prop({
                'checked': checkedCheckboxes === totalCheckboxes,
                'indeterminate': checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes
            });
        },

        /**
         * Initialize actions for blocked IPs management page
         */
        initBlockedIps() {
            $('.unblock-ip').on('click', function () {
                const ip = $(this).data('ip');
                if (!confirm('Unblock ' + ip + '?')) return;
                $.post(ajaxurl, {
                    action: 'cf7_honeypot_unblock_ip',
                    ip: ip,
                    nonce: cf7HoneypotAdmin.unblockNonce
                }, function (response) {
                    if (response.success) {
                        $("tr[data-ip='" + ip + "']").fadeOut(300, function () { $(this).remove(); });
                    } else {
                        alert('Error');
                    }
                });
            });
        },

        /**
         * Delete selected records via AJAX
         * @param {Array} ids - Array of record IDs to delete
         */
        deleteSelectedRecords(ids) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cf7_honeypot_delete_records',
                    ids: ids,
                    nonce: cf7HoneypotAdmin.deleteNonce
                },
                beforeSend: () => {
                    $('#doaction').prop('disabled', true).addClass('updating-message');
                },
                success: (response) => {
                    if (response.success) {
                        // Remove deleted rows from the table
                        ids.forEach(id => {
                            $(`input[value="${id}"]`).closest('tr').next('.details-row').remove();
                            $(`input[value="${id}"]`).closest('tr').remove();
                        });

                        // Show success notification
                        const notice = $('<div class="notice notice-success is-dismissible"><p></p></div>')
                            .find('p')
                            .text(response.data.message)
                            .end();
                        $('.cf7-honeypot-stats').prepend(notice);
                    }
                },
                complete: () => {
                    $('#doaction').prop('disabled', false).removeClass('updating-message');
                    this.updateSelectAllCheckbox();
                    this.updateBulkActionButton();
                }
            });
        },

        /**
         * Validate email format
         * @param {string} email - Email address to validate
         * @returns {boolean} True if email is valid, false otherwise
         */
        isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        /**
         * Show validation errors
         * Scrolls to first error and highlights it
         */
        showValidationErrors() {
            const firstError = $('.has-error').first();
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            }
        }
    };

    // Initialize admin functionality when document is ready
    $(document).ready(() => CF7HoneypotAdmin.init());

})(jQuery);
