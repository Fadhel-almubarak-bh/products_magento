/**
 * Copyright 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
define(
    [
        'underscore',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/model/quote',
        'PayPal_Braintree/js/view/payment/adapter',
        'mage/translate',
        'PayPal_Braintree/js/validator',
        'PayPal_Braintree/js/view/payment/validator-handler',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (
        _,
        $,
        Component,
        quote,
        braintree,
        $t,
        validator,
        validatorManager,
        fullScreenLoader
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                active: false,
                braintreeClient: null,
                braintreeDeviceData: null,
                paymentMethodNonce: null,
                lastBillingAddress: null,
                validatorManager: validatorManager,
                code: 'braintree',
                isProcessing: false,
                creditCardBin: null,

                /**
                 * Additional payment data
                 *
                 * {Object}
                 */
                additionalData: {},

                /**
                 * Braintree client configuration
                 *
                 * {Object}
                 */
                clientConfig: {
                    onReady: function (context) {
                        context.setupHostedFields();
                    },

                    /**
                     * Triggers on payment nonce receive
                     * @param {Object} response
                     */
                    onPaymentMethodReceived: function (response) {
                        this.handleNonce(response);
                        this.isProcessing = false;
                    },

                    /**
                     * Allow a new nonce to be generated
                     */
                    onPaymentMethodError: function () {
                        this.isProcessing = false;
                    },

                    /**
                     * Device data initialization
                     * @param {String} deviceData
                     */
                    onDeviceDataReceived: function (deviceData) {
                        if (this.additionalData === undefined) {
                            this.additionalData = {};
                        }
                        this.additionalData['device_data'] = deviceData;
                    },

                    /**
                     * After Braintree instance initialization
                     */
                    onInstanceReady: function () {},

                    /**
                     * Triggers on any Braintree error
                     * @param {Object} response
                     */
                    onError: function (response) {
                        this.isProcessing = false;
                        braintree.showError($t('Payment ' + this.getTitle() + ' can\'t be initialized'));
                        throw response.message;
                    },

                    /**
                     * Triggers when customer click "Cancel"
                     */
                    onCancelled: function () {
                        this.paymentMethodNonce = null;
                        this.isProcessing = false;
                    }
                },
                imports: {
                    onActiveChange: 'active'
                }
            },

            /**
             * Set list of observable attributes
             *
             * @returns {exports.initObservable}
             */
            initObservable: function () {
                validator.setConfig(window.checkoutConfig.payment[this.getCode()]);
                this._super()
                    .observe(['active']);
                this.validatorManager.initialize();
                this.initClientConfig();

                return this;
            },

            /**
             * Store the CC message container so it can be switched if required later on.
             *
             * @returns {Object}
             */
            initialize: function () {
                this._super();
                this.ccMessageContainer = this.messageContainer;
            },

            /**
             * Get payment name
             *
             * @returns {String}
             */
            getCode: function () {
                return this.code;
            },

            /**
             * Check if payment is active
             *
             * @returns {Boolean}
             */
            isActive: function () {
                let active = this.getCode() === this.isChecked();

                this.active(active);

                return active;
            },

            /**
             * Triggers when payment method change
             * @param {Boolean} isActive
             */
            onActiveChange: function (isActive) {
                if (!isActive) {
                    return;
                }

                this.initBraintree();
            },

            /**
             * Init config
             */
            initClientConfig: function () {
                _.each(this.clientConfig, function (fn, name) {
                    if (typeof fn === 'function') {
                        this.clientConfig[name] = fn.bind(this);
                    }
                }, this);
            },

            /**
             * Init Braintree configuration
             */
            initBraintree: function () {
                let intervalId = setInterval(function () {
                    // stop loader when frame will be loaded
                    if ($('#braintree-hosted-field-number').length) {
                        clearInterval(intervalId);
                        fullScreenLoader.stopLoader(true);
                    }
                }, 500);

                if (braintree.checkout) {
                    braintree.checkout.teardown(function () {
                        braintree.checkout = null;
                    });
                }

                fullScreenLoader.startLoader();
                braintree.setConfig(this.clientConfig);
                braintree.setup();
            },

            /**
             * Get full selector name
             *
             * @param {String} field
             * @returns {String}
             */
            getSelector: function (field) {
                return '#' + this.getCode() + '_' + field;
            },

            /**
             * Get list of available CC types
             *
             * @returns {Object}
             */
            getCcAvailableTypes: function () {
                let availableTypes = validator.getAvailableCardTypes(),
                    billingAddress = quote.billingAddress(),
                    billingCountryId;

                this.lastBillingAddress = quote.shippingAddress();

                if (!billingAddress) {
                    billingAddress = this.lastBillingAddress;
                }

                billingCountryId = billingAddress.countryId;

                if (billingCountryId && validator.getCountrySpecificCardTypes(billingCountryId)) {
                    return validator.collectTypes(
                        availableTypes,
                        validator.getCountrySpecificCardTypes(billingCountryId)
                    );
                }

                return availableTypes;
            },

            /**
             * @returns {String}
             */
            getEnvironment: function () {
                return window.checkoutConfig.payment[this.getCode()].environment;
            },

            /**
             * Get data
             *
             * @returns {Object}
             */
            getData: function () {
                let data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'payment_method_nonce': this.paymentMethodNonce,
                        'g-recaptcha-response' : $('#token-grecaptcha-braintree').val()
                    }
                };

                data['additional_data'] = _.extend(data['additional_data'], this.additionalData);

                return data;
            },

            /**
             * Set payment nonce
             * @param {String} paymentMethodNonce
             */
            setPaymentMethodNonce: function (paymentMethodNonce) {
                this.paymentMethodNonce = paymentMethodNonce;
            },

            /**
             * Set credit card bin
             * @param creditCardBin
             */
            setCreditCardBin: function (creditCardBin) {
                this.creditCardBin = creditCardBin;
            },

            /**
             * Prepare payload to place order
             * @param {Object} payload
             */
            handleNonce: function (payload) {
                let self = this;

                this.setPaymentMethodNonce(payload.nonce);
                this.setCreditCardBin(payload.details.bin);
                this.messageContainer = this.ccMessageContainer;

                // place order on success validation
                self.validatorManager.validate(self, function () {
                    return self.placeOrder('parent');
                }, function () {
                    self.isProcessing = false;
                    self.paymentMethodNonce = null;
                    self.creditCardBin = null;
                });
            },

            /**
             * Action to place order
             * @param {String} key
             */
            placeOrder: function (key) {
                if (key) {
                    return this._super();
                }

                if (this.isProcessing) {
                    return false;
                }
                this.isProcessing = true;


                braintree.tokenizeHostedFields();
                return false;
            },

            /**
             * Get payment icons
             * @param {String} type
             * @returns {Boolean}
             */
            getIcons: function (type) {
                return window.checkoutConfig.payment.braintree.icons.hasOwnProperty(type) ?
                    window.checkoutConfig.payment.braintree.icons[type]
                    : false;
            }
        });
    }
);
