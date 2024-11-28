/**
 * CF7 Advanced Honeypot - Admin Settings JavaScript
 *
 * Gestisce tutte le interazioni dell'interfaccia di amministrazione
 * inclusa la gestione dinamica delle domande personalizzate e
 * le impostazioni specifiche per form.
 */

(function ($) {
    'use strict';

    const CF7HoneypotAdmin = {
        init() {
            this.initTabs();
            this.initCustomQuestions();
            this.initFormValidation();
            this.initTooltips();
            this.initDynamicToggles();
            this.initCountrySelect();
        },

        initCountrySelect: function () {
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

        initTabs() {
            const $tabs = $('.nav-tab');
            const $contents = $('.tab-content');

            $tabs.on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');

                $tabs.removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                $contents.hide();
                $(target).fadeIn(300);

                // Aggiorna URL senza reload
                history.pushState({}, '', target);
            });

            // Gestione hash URL
            if (window.location.hash) {
                $(`a[href="${window.location.hash}"]`).trigger('click');
            }
        },

        initCustomQuestions() {
            const container = $('#custom-questions-container');
            const template = this.getQuestionTemplate();

            $('#add-question').on('click', () => {
                const newRow = $(template(container.children().length));
                container.append(newRow);
                newRow.hide().slideDown(300);
            });

            container.on('click', '.remove-question', function (e) {
                const row = $(this).closest('.question-row');
                row.slideUp(300, () => row.remove());
            });
        },

        getQuestionTemplate() {
            return (index) => `
                <div class="question-row">
                    <input type="text"
                           name="cf7_honeypot_settings[custom_questions][${index}][question]"
                           placeholder="Domanda"
                           class="question-input">
                    <input type="text"
                           name="cf7_honeypot_settings[custom_questions][${index}][answer]"
                           placeholder="Risposta"
                           class="answer-input">
                    <button type="button" class="button remove-question">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `;
        },

        initFormValidation() {
            const form = $('.settings-form');

            form.on('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    this.showValidationErrors();
                }
            });

            // Validazione real-time
            form.on('input', 'input, textarea', function () {
                const field = $(this);
                this.validateField(field);
            }.bind(this));
        },

        validateForm(form) {
            let isValid = true;

            // Valida campi obbligatori
            form.find('[required]').each((i, field) => {
                if (!this.validateField($(field))) {
                    isValid = false;
                }
            });

            // Valida email
            form.find('input[type="email"]').each((i, field) => {
                if (field.value && !this.isValidEmail(field.value)) {
                    isValid = false;
                    this.addError($(field), 'Email non valida');
                }
            });

            return isValid;
        },

        validateField($field) {
            const value = $field.val().trim();

            if ($field.prop('required') && !value) {
                this.addError($field, 'Campo obbligatorio');
                return false;
            }

            this.removeError($field);
            return true;
        },

        addError($field, message) {
            if (!$field.next('.error-message').length) {
                $field
                    .addClass('has-error')
                    .after(`<span class="error-message">${message}</span>`);
            }
        },

        removeError($field) {
            $field
                .removeClass('has-error')
                .next('.error-message')
                .remove();
        },

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

        initDynamicToggles() {
            // Toggle opzioni avanzate
            $('[data-toggle]').on('change', function () {
                const target = $(this).data('toggle');
                $(target).slideToggle(300);
            });

            // Gestione livelli di protezione
            $('.protection-level-select').on('change', function () {
                const level = $(this).val();
                const row = $(this).closest('.form-setting-row');

                row.find('.protection-options')
                    .removeClass('low medium high')
                    .addClass(level);
            });
        },

        isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        showValidationErrors() {
            const firstError = $('.has-error').first();
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            }
        }
    };

    $(document).ready(() => CF7HoneypotAdmin.init());

})(jQuery);