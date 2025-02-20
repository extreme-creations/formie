<template>
    <div class="fui-field-block fui-submit-block" :class="{ 'has-errors': false }" @click.prevent="editField">
        <div class="fui-edit-overlay" @click.prevent="editField"></div>

        <div class="flex" :style="{ 'justify-content': cssAlignment }">
            <div v-if="!isFirstButton && settings.showBackButton">
                <a href="#" class="btn submit">{{ settings.backButtonLabel }}</a>
            </div>

            <a href="#" class="btn submit">{{ settings.submitButtonLabel }}</a>
        </div>

        <field-edit-modal
            v-if="modalActive"
            ref="editFieldModal"
            :visible="modalVisible"
            :field-ref="this"
            :field="field"
            :can-delete="false"
            :fields-schema="fieldsSchema"
            :tabs-schema="tabsSchema"
            :show-field-type="false"
            @close="onModalClose"
            @cancel="onModalCancel"
        />
    </div>
</template>

<script>
import { mapState } from 'vuex';

import FieldEditModal from './FieldEditModal.vue';

export default {
    name: 'SubmitButtons',

    components: {
        FieldEditModal,
    },

    props: {
        pageId: {
            type: [String, Number],
            default: '',
        },

        pageIndex: {
            type: Number,
            default: 0,
        },
    },

    data() {
        return {
            id: Math.random(),
            modalActive: false,
            modalVisible: false,
            originalField: null,
            submitButton: true,
            field: {},
        };
    },

    computed: {
        ...mapState({
            pages: state => state.form.pages,
            form: state => state.form,
        }),

        settings() {
            return this.$store.getters['form/pageSettings'](this.pageId);
        },

        isFirstButton() {
            return this.pageIndex === 0;
        },

        tabsSchema() {
            return [
                {
                    label: this.$options.filters.t('General', 'formie'),
                    fields: [
                        'submitButtonLabel',
                        'showBackButton',
                        'backButtonLabel',
                    ],
                },
                {
                    label: this.$options.filters.t('Appearance', 'formie'),
                    fields: [
                        'buttonsPosition',
                        'cssClasses',
                    ],
                },
                {
                    label: this.$options.filters.t('Conditions', 'formie'),
                    fields: [
                        'enableConditions',
                        'conditions',
                    ],
                },
                {
                    label: this.$options.filters.t('Advanced', 'formie'),
                    fields: [
                        'enableJsEvents',
                        'jsEvents',
                    ],
                },
            ];
        },

        fieldsSchema() {
            var fields = [];

            if (!this.isFirstButton) {
                fields = [
                    {
                        type: 'lightswitch',
                        label: this.$options.filters.t('Show Back Button', 'formie'),
                        help: this.$options.filters.t('Whether to show the back button, to go back to a previous page.', 'formie'),
                        name: 'showBackButton',
                    },
                    {
                        component: 'toggle-group',
                        conditional: 'settings.showBackButton',
                        children: [
                            {
                                type: 'text',
                                class: 'text fullwidth',
                                autocomplete: 'off',
                                label: this.$options.filters.t('Back Button Label', 'formie'),
                                help: this.$options.filters.t('The label for the back submit button.', 'formie'),
                                name: 'backButtonLabel',
                                validation: 'requiredIf:showBackButton',
                                validationName: this.$options.filters.t('Back Button Label', 'formie'),
                                required: true,
                            },
                        ],
                    },
                ];
            }

            var fieldsSchema = [
                {
                    component: 'tab-panel',
                    'data-tab-panel': 'General',
                    children: [
                        {
                            type: 'text',
                            class: 'text fullwidth',
                            autocomplete: 'off',
                            label: this.$options.filters.t('Button Label', 'formie'),
                            help: this.$options.filters.t('The label for the submit button.', 'formie'),
                            name: 'submitButtonLabel',
                            validation: 'required',
                            validationName: this.$options.filters.t('Button Label', 'formie'),
                            required: true,
                        },
                        ...fields,
                    ],
                },
                {
                    component: 'tab-panel',
                    'data-tab-panel': 'General',
                    children: [
                        {
                            type: 'select',
                            label: this.$options.filters.t('Button Positions', 'formie'),
                            help: this.$options.filters.t('How the buttons should be positioned.', 'formie'),
                            name: 'buttonsPosition',
                            options: this.buttonsPosition,
                        },
                        {
                            type: 'text',
                            class: 'text fullwidth',
                            autocomplete: 'off',
                            label: this.$options.filters.t('CSS Classes', 'formie'),
                            help: this.$options.filters.t('Add classes that will be output on submit button container.', 'formie'),
                            name: 'cssClasses',
                        },
                        {
                            component: 'table-block',
                            label: this.$options.filters.t('Container Attributes', 'formie'),
                            help: this.$options.filters.t('Add attributes to be outputted on this submit button’s container.', 'formie'),
                            validation: 'min:0',
                            generateValue: false,
                            name: 'containerAttributes',
                            newRowDefaults: {
                                label: '',
                                value: '',
                            },
                            columns: [
                                {
                                    type: 'label',
                                    label: 'Name',
                                    class: 'singleline-cell textual',
                                },
                                {
                                    type: 'value',
                                    label: 'Value',
                                    class: 'singleline-cell textual',
                                },
                            ],
                        },
                        {
                            component: 'table-block',
                            label: this.$options.filters.t('Input Attributes', 'formie'),
                            help: this.$options.filters.t('Add attributes to be outputted on this submit button’s input.', 'formie'),
                            validation: 'min:0',
                            generateValue: false,
                            name: 'inputAttributes',
                            newRowDefaults: {
                                label: '',
                                value: '',
                            },
                            columns: [
                                {
                                    type: 'label',
                                    label: 'Name',
                                    class: 'singleline-cell textual',
                                },
                                {
                                    type: 'value',
                                    label: 'Value',
                                    class: 'singleline-cell textual',
                                },
                            ],
                        },
                    ],
                },
                {
                    component: 'tab-panel',
                    'data-tab-panel': 'General',
                    children: [
                        {
                            type: 'lightswitch',
                            labelPosition: 'before',
                            label: this.$options.filters.t('Enable Conditions', 'formie'),
                            help: this.$options.filters.t('Whether to enable conditional logic to control how the next button is shown.', 'formie'),
                            name: 'enableNextButtonConditions',
                        },
                        {
                            component: 'toggle-group',
                            conditional: 'settings.enableNextButtonConditions',
                            children: [
                                {
                                    type: 'fieldConditions',
                                    name: 'nextButtonConditions',
                                    descriptionText: 'the next button if',
                                },
                            ],
                        },
                    ],
                },
                {
                    component: 'tab-panel',
                    'data-tab-panel': 'General',
                    children: [
                        {
                            type: 'lightswitch',
                            labelPosition: 'before',
                            label: this.$options.filters.t('Enable JavaScript Events', 'formie'),
                            help: this.$options.filters.t('Whether to enable management of JavaScript events when this button is pressed.', 'formie'),
                            name: 'enableJsEvents',
                        },
                        {
                            component: 'toggle-group',
                            conditional: 'settings.enableJsEvents',
                            children: [
                                {
                                    component: 'table-block',
                                    label: this.$options.filters.t('Google Tag Manager Event Data', 'formie'),
                                    help: this.$options.filters.t('Add event data to be sent to Google Tag Manager.', 'formie'),
                                    validation: 'min:0',
                                    generateValue: false,
                                    name: 'jsGtmEventOptions',
                                    newRowDefaults: {
                                        label: '',
                                        value: '',
                                    },
                                    value: [{
                                        label: 'event',
                                        value: 'formPageSubmission',
                                    },{
                                        label: 'formId',
                                        value: this.form.handle,
                                    },{
                                        label: 'pageId',
                                        value: this.pageId,
                                    },{
                                        label: 'pageIndex',
                                        value: this.pageIndex,
                                    }],
                                    columns: [
                                        {
                                            type: 'label',
                                            label: 'Option',
                                            class: 'singleline-cell textual',
                                        },
                                        {
                                            type: 'value',
                                            label: 'Value',
                                            class: 'singleline-cell textual',
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            ];

            return [{
                component: 'tab-panels',
                class: 'fui-modal-content',
                children: fieldsSchema,
            }];
        },

        buttonsPosition() {
            var positions = [
                { label: Craft.t('formie', 'Left'), value: 'left' },
                { label: Craft.t('formie', 'Right'), value: 'right' },
                { label: Craft.t('formie', 'Center'), value: 'center' },
            ];

            if (this.settings.showBackButton) {
                positions.push({ label: Craft.t('formie', 'Left & Right'), value: 'left-right' });
            }

            return positions;
        },

        cssAlignment() {
            if (this.settings.buttonsPosition === 'right') {
                return 'flex-end';
            }

            if (this.settings.buttonsPosition === 'center') {
                return 'center';
            }

            if (this.settings.buttonsPosition === 'left-right') {
                return 'space-between';
            }

            return 'normal';
        },
    },

    created() {
        // Store this so we can cancel changes.
        this.originalField = clone(this.settings);

        // Create a mock field to make editing easier
        this.field = this.pages[this.pageIndex];
    },

    methods: {
        editField() {
            this.modalActive = true;
            this.modalVisible = true;
        },

        onModalClose() {
            this.modalActive = false;
            this.modalVisible = false;
        },

        markAsSaved() {
            // Required for save callback in FieldEditModal
        },

        onModalCancel() {
            // Restore original state and exit
            Object.assign(this.settings, this.originalField);
        },
    },

};

</script>
