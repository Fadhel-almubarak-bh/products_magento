<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace Magento\PaymentServicesPaypal\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\Framework\App\Request\Http;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as Address;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;
use Magento\PaymentServicesPaypal\Helper\TextSanitiser;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderService
{
    private const PAYPAL_ORDER = 'paypal-order';
    private const PAYPAL_ORDER_UPDATE = 'paypal-order-update';

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * @var ServiceClientInterface
     */
    private ServiceClientInterface $httpClient;

    /**
     * @var ScopeHeadersBuilder
     */
    private ScopeHeadersBuilder $scopeHeaderBuilder;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var BaseConfig
     */
    private BaseConfig $baseConfig;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var TextSanitiser
     */
    private TextSanitiser $textSanitiser;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param ServiceClientInterface $httpClient
     * @param ScopeHeadersBuilder $scopeHeaderBuilder
     * @param Config $config
     * @param BaseConfig $baseConfig
     * @param LoggerInterface $logger
     * @param TextSanitiser $textSanitiser
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        ServiceClientInterface $httpClient,
        ScopeHeadersBuilder $scopeHeaderBuilder,
        Config $config,
        BaseConfig $baseConfig,
        LoggerInterface $logger,
        TextSanitiser $textSanitiser
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->httpClient = $httpClient;
        $this->scopeHeaderBuilder = $scopeHeaderBuilder;
        $this->config = $config;
        $this->baseConfig = $baseConfig;
        $this->logger = $logger;
        $this->textSanitiser = $textSanitiser;
    }

    /**
     * Map DTO fields and send the order creation request to the backend service
     *
     * @param StoreInterface $store
     * @param array $data
     * @return array
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function create(StoreInterface $store, array $data) : array
    {
        $order = [
            self::PAYPAL_ORDER => [
                'amount' => [
                    'currency_code' => $data['currency_code'],
                    'value' => $data['amount'] ?? 0.00
                ],
                'is_digital' => !!$data['is_digital'] ?? false,
                'website_id' => $store->getWebsiteId(),
                'store_id' => $store->getStoreGroupId(),
                'storeview_id' => $store->getId(),
                'payment_source' => $this->resolvePaymentSource($data),
                'vault' => $data['vault'] ?? false,
                'three_ds_mode' => $this->resolveThreeDSMode($data),
            ]
        ];
        $order[self::PAYPAL_ORDER]['shipping-address'] = $data['shipping_address'] ?? null;
        $order[self::PAYPAL_ORDER]['billing-address'] = $data['billing_address'] ?? null;
        $order[self::PAYPAL_ORDER]['payer'] = $data['payer'] ?? null;
        if ($data['quote_id'] !== null) {
            $order[self::PAYPAL_ORDER]['intent'] = $this->getPaymentIntent($data['quote_id']);
        }
        if (!empty($data['order_increment_id'])) {
            $order[self::PAYPAL_ORDER]['order_increment_id'] = $data['order_increment_id'];
        }
        $softDescriptor = $this->config->getSoftDescriptor($data['storeview_code'] ?? null);
        if ($softDescriptor) {
            $order[self::PAYPAL_ORDER]['soft_descriptor'] = $softDescriptor;
        }

        $order = $this->applyL2Data($order, $data);
        $order = $this->applyL3Data($order, $data);
        $order = $this->applyLineItems($order, $data);
        $order = $this->applyAmountBreakdown($order, $data, self::PAYPAL_ORDER);
        $order = $this->applyCheckoutLocation($order, $data);

        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($store)
        );
        if (isset($data['vault']) && $data['vault']) {
            $headers['x-commerce-customer-id'] = $data['payer']['customer_id'];
        }
        if (isset($data['quote_id']) && $data['quote_id']) {
            $headers['x-commerce-quote-id'] = $data['quote_id'];
        }

        $path = '/' . $this->config->getMerchantId() . '/payment/paypal/order';
        $body = json_encode($order);

        if (!$body) {
            $this->logger->error('Error encoding body for order creation request', $order);
            throw new HttpException('Error encoding body for order creation request');
        }

        $response = $this->httpClient->request(
            $headers,
            $path,
            Http::METHOD_POST,
            $body,
            'json',
            $this->baseConfig->getEnvironmentType($data['storeview_code'] ?? null)
        );

        $this->logger->debug(
            var_export(
                [
                    'request' => [
                        $path,
                        $headers,
                        Http::METHOD_POST,
                        $body
                    ],
                    'response' => $response
                ],
                true
            )
        );

        return $response;
    }

    /**
     * Update the PayPal order with selective params
     *
     * @param string $storeId
     * @param string $orderId
     * @param array $data
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function update(string $storeId, string $orderId, array $data) : void
    {
        $order = [
            self::PAYPAL_ORDER_UPDATE => [
                'reference_id' => 'default',
                'amount' => [
                    'operation' => 'REPLACE',
                    'value' => [
                        'currency_code' => $data['currency_code'],
                        'value' => $data['amount']
                    ]
                ]
            ]
        ];

        $order = $this->applyLineItemsOperation($order, $data);
        $order = $this->applyAmountBreakdown($order, $data, self::PAYPAL_ORDER_UPDATE);

        $path = '/' . $this->config->getMerchantId() . '/payment/paypal/order/' . $orderId;
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($storeId)
        );
        $body = json_encode($order);

        if (!$body) {
            $this->logger->error(
                sprintf('Error encoding body for order update request for order id %s', $orderId),
                $order
            );
            throw new HttpException('Error encoding body for order update request');
        }

        $response = $this->httpClient->request(
            $headers,
            $path,
            Http::METHOD_PATCH,
            $body
        );

        $this->logger->debug(
            var_export(
                [
                    'request' => [
                        $path,
                        $headers,
                        Http::METHOD_PATCH,
                        $body
                    ],
                    'response' => $response
                ],
                true
            )
        );

        if (!isset($response['is_successful']) || !$response['is_successful']) {
            throw new HttpException('Failed to update an order.');
        }
    }

    /**
     * Add tracking information for a Paypal Order
     *
     * @param StoreInterface $store
     * @param string $orderId
     * @param array $data
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function track(StoreInterface $store, string $orderId, array $data) : void
    {
        $path = sprintf('/%s/payment/paypal/order/%s/tracking-info', $this->config->getMerchantId(), $orderId);
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($store)
        );
        $body = json_encode($data);

        if (!$body) {
            $this->logger->error(
                sprintf('Error encoding body for tracking info request for order id %s', $orderId),
                $data
            );
            throw new HttpException('Error encoding body for tracking info request');
        }

        $response = $this->httpClient->request(
            $headers,
            $path,
            Http::METHOD_POST,
            $body
        );

        $this->logger->debug(
            var_export(
                [
                    'request' => [
                        $path,
                        $headers,
                        Http::METHOD_POST,
                        $body
                    ],
                    'response' => $response
                ],
                true
            )
        );

        if (!isset($response['is_successful']) || !$response['is_successful']) {
            throw new HttpException(sprintf('Failed to create tracking information for order id %s', $orderId));
        }
    }

    /**
     * Get the Order object from PayPal
     *
     * @param string $storeId
     * @param string $orderId
     * @return array
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function get(string $storeId, string $orderId) : array
    {
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($storeId)
        );
        $response = $this->httpClient->request(
            $headers,
            '/' . $this->config->getMerchantId() . '/payment/paypal/order/' . $orderId,
            Http::METHOD_GET,
        );
        if (!$response['is_successful']) {
            throw new HttpException('Failed to retrieve an order.');
        }
        return $response;
    }

    /**
     * Map Commerce address fields to DTO
     *
     * @param Address $address
     * @return array|null
     */
    public function mapAddress(Address $address) :? array
    {
        if ($address->getCountry() === null) {
            return null;
        }
        return [
            'full_name' => $address->getFirstname() . ' ' . $address->getLastname(),
            'address_line_1' => $address->getStreet()[0],
            'address_line_2' => $address->getStreet()[1] ?? null,
            'admin_area_1' => $address->getRegionCode(),
            'admin_area_2' => $address->getCity(),
            'postal_code' => $address->getPostcode(),
            'country_code' => $address->getCountry()
        ];
    }

    /**
     * Build the Payer object for PayPal order creation
     *
     * @param Quote $quote
     * @param String $customerId
     * @return array
     */
    public function buildPayer(Quote $quote, String $customerId) : array
    {
        $billingAddress = $quote->getBillingAddress();

        return [
            'name' => [
                'given_name' => $quote->getCustomerFirstname(),
                'surname' => $quote->getCustomerLastname()
            ],
            'email' => $quote->getCustomerEmail(),
            'phone_number' => $billingAddress->getTelephone() ?? null,
            'customer_id' => $customerId
        ];
    }

    /**
     * Build Guest Payer object for PayPal order creation
     *
     * @param Quote $quote
     * @return array
     */
    public function buildGuestPayer(Quote $quote) : array
    {
        $billingAddress = $quote->getBillingAddress();

        return [
            'name' => [
                'given_name' => $billingAddress->getFirstname(),
                'surname' => $billingAddress->getLastname()
            ],
            'email' => $billingAddress->getEmail(),
            'phone_number' => $billingAddress->getTelephone() ?? null
        ];
    }

    /**
     * Get the payment intent (authorize/capture) of the quote
     *
     * @param string $quoteId
     * @return string
     * @throws NoSuchEntityException
     */
    private function getPaymentIntent(string $quoteId): string
    {
        $quote = $this->quoteRepository->get($quoteId);
        $paymentMethod = $quote->getPayment()->getMethod();
        $storeId = $quote->getStoreId();
        if ($paymentMethod === HostedFieldsConfigProvider::CC_VAULT_CODE) {
            return $this->config->getPaymentIntent(HostedFieldsConfigProvider::CODE, $storeId);
        }
        return $this->config->getPaymentIntent($paymentMethod, $storeId);
    }

    /**
     * Apply L2 data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyL2Data(array $order, array $data) : array
    {
        if (empty($data['l2_data'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['l2_data'] = $data['l2_data'];
        return $order;
    }

    /**
     * Apply L3 data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyL3Data(array $order, array $data) : array
    {
        if (empty($data['l3_data'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['l3_data'] = $data['l3_data'];
        return $order;
    }

    /**
     * Apply Line items data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyLineItems(array $order, array $data) : array
    {
        if (empty($data['line_items'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['line_items'] = $data['line_items'];
        return $order;
    }

    /**
     * Apply Line items operation data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyLineItemsOperation(array $order, array $data) : array
    {
        if (empty($data['line_items'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER_UPDATE]['line_items'] = [
            'operation' => 'ADD',
            'value' => $data['line_items']
        ];

        return $order;
    }

    /**
     * Apply Amount Breakdown data to the order
     *
     * @param array $order
     * @param array $data
     * @param string $key
     * @return array
     */
    private function applyAmountBreakdown(array $order, array $data, string $key) : array
    {
        if (empty($data['amount_breakdown'])) {
            return $order;
        }

        $order[$key]['amount_breakdown'] = $data['amount_breakdown'];
        return $order;
    }

    /**
     * Apply checkout location data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    public function applyCheckoutLocation(array $order, array $data): array
    {
        if (empty($data['location']) || !in_array($data['location'], Config::CHECKOUT_LOCATIONS)) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['location'] = $data['location'];
        return $order;
    }

    /**
     * Get and sanitise the payment source
     *
     * @param array $data
     * @return string
     */
    public function resolvePaymentSource(array $data): string
    {
        return empty($data['payment_source']) ? '' : $this->textSanitiser->textOnly($data['payment_source']);
    }

    /**
     * Get and sanitise the 3DS mode
     *
     * @param array $data
     * @return string|null
     */
    public function resolveThreeDSMode(array $data): ?string
    {
        return empty($data['three_ds_mode']) ? null : $this->textSanitiser->textOnly($data['three_ds_mode']);
    }
}
