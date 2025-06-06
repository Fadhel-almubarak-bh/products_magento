<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Config\PayPal;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use PayPal\Braintree\Model\Config\Source\Color;
use PayPal\Braintree\Model\Config\Source\CreditColor;
use PayPal\Braintree\Model\Config\Source\Shape;
use PayPal\Braintree\Model\Config\Source\Size;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\CcConfig;
use PayPal\Braintree\Model\StoreConfigResolver;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    public const KEY_ACTIVE = 'active';
    public const KEY_TITLE = 'title';
    public const KEY_SEND_CART_LINE_ITEMS = 'send_cart_line_items';
    public const KEY_DISPLAY_ON_SHOPPING_CART = 'display_on_shopping_cart';
    public const KEY_ALLOW_TO_EDIT_SHIPPING_ADDRESS = 'allow_shipping_address_override';
    public const KEY_MERCHANT_NAME_OVERRIDE = 'merchant_name_override';
    public const KEY_REQUIRE_BILLING_ADDRESS = 'require_billing_address';
    public const KEY_PAYPAL_DISABLED_FUNDING_CHECKOUT = 'disabled_funding_checkout';
    public const KEY_PAYPAL_DISABLED_FUNDING_CART = 'disabled_funding_cart';
    public const KEY_PAYPAL_DISABLED_FUNDING_PDP = 'disabled_funding_productpage';
    public const BUTTON_AREA_CART = 'cart';
    public const BUTTON_AREA_CHECKOUT = 'checkout';
    public const BUTTON_AREA_PDP = 'productpage';
    public const KEY_BUTTON_COLOR = 'color';
    public const KEY_BUTTON_SHAPE = 'shape';
    public const KEY_BUTTON_SIZE = 'size';
    public const KEY_BUTTON_LABEL = 'label';
    public const KEY_SKIP_ORDER_REVIEW_STEP = 'skip_order_review_step';
    public const KEY_SEND_PACKAGE_TRACKING = 'send_package_tracking';
    public const KEY_TRACKING_NOTIFY_PAYER = 'tracking_notify_payer';

    /**
     * @var CcConfig
     */
    private CcConfig $ccConfig;

    /**
     * @var array
     */
    private array $icon = [];

    /**
     * @var Size
     */
    private Size $sizeConfigSource;

    /**
     * @var Color
     */
    private Color $colorConfigSource;

    /**
     * @var Shape
     */
    private Shape $shapeConfigSource;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfigResolver;

    /**
     * @var CreditColor
     */
    private CreditColor $creditColorSource;

    /**
     * @var StoreConfigResolver
     */
    private StoreConfigResolver $storeConfigResolver;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param CcConfig $ccConfig
     * @param Size $sizeConfigSource
     * @param Color $colorConfigSource
     * @param Shape $shapeConfigSource
     * @param CreditColor $creditColorSource
     * @param StoreConfigResolver $storeConfigResolver
     * @param string|null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CcConfig $ccConfig,
        Size $sizeConfigSource,
        Color $colorConfigSource,
        Shape $shapeConfigSource,
        CreditColor $creditColorSource,
        StoreConfigResolver $storeConfigResolver,
        ?string $methodCode = null,
        string $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->scopeConfigResolver = $scopeConfig;
        $this->ccConfig = $ccConfig;
        $this->sizeConfigSource = $sizeConfigSource;
        $this->colorConfigSource = $colorConfigSource;
        $this->shapeConfigSource = $shapeConfigSource;
        $this->creditColorSource = $creditColorSource;
        $this->storeConfigResolver = $storeConfigResolver;
    }

    /**
     * Get Payment configuration status
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive(?int $storeId = null): bool
    {
        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }

    /**
     * Is button display on shopping cart
     *
     * @return bool
     */
    public function isDisplayShoppingCart(): bool
    {
        return (bool) $this->getValue(self::KEY_DISPLAY_ON_SHOPPING_CART);
    }

    /**
     * Is shipping address can be editable on PayPal side
     *
     * @return bool
     */
    public function isAllowToEditShippingAddress(): bool
    {
        return (bool) $this->getValue(self::KEY_ALLOW_TO_EDIT_SHIPPING_ADDRESS);
    }

    /**
     * Get merchant name to display in PayPal popup
     *
     * @return string|null
     */
    public function getMerchantName(): ?string
    {
        return $this->getValue(self::KEY_MERCHANT_NAME_OVERRIDE);
    }

    /**
     * Get Merchant country
     *
     * @return mixed|null
     */
    public function getMerchantCountry(): mixed
    {
        return $this->scopeConfigResolver->getValue(
            'paypal/general/merchant_country',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is billing address can be required
     *
     * @return string
     */
    public function isRequiredBillingAddress(): string
    {
        return $this->getValue(self::KEY_REQUIRE_BILLING_ADDRESS);
    }

    /**
     * Get title of payment
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->getValue(self::KEY_TITLE);
    }

    /**
     * Retrieve the button style config values
     *
     * @param string $area
     * @param string $style
     * @param string $type
     * @return mixed|null
     */
    private function getButtonStyle(string $area, string $style, string $type): mixed
    {
        return $this->getValue('button_location_' . $area . '_type_' . $type . '_' . $style);
    }

    /**
     * Get button color mapped to the value expected by the PayPal API
     *
     * @param string $area
     * @param string $type
     * @return string|null
     */
    public function getButtonColor(string $area = self::BUTTON_AREA_CART, string $type = 'paypal'): ?string
    {
        $value = $this->getButtonStyle($area, self::KEY_BUTTON_COLOR, $type);
        $options = $this->colorConfigSource->toRawValues();
        return $options[$value];
    }

    /**
     * Get credit button color mapped to the value expected by the PayPal Credit
     *
     * @param string $area
     * @param string $type
     * @return string|null
     */
    public function getCreditButtonColor(string $area = self::BUTTON_AREA_CART, string $type = 'credit'): ?string
    {
        $value = $this->getButtonStyle($area, self::KEY_BUTTON_COLOR, $type);
        $options = $this->creditColorSource->toRawValues();
        return $options[$value];
    }

    /**
     * Get button shape mapped to the value expected by the PayPal API
     *
     * @param string $area
     * @param string $type
     * @return string
     */
    public function getButtonShape(string $area = self::BUTTON_AREA_CART, string $type = 'paypal'): string
    {
        $value = $this->getButtonStyle($area, self::KEY_BUTTON_SHAPE, $type);
        $options = $this->shapeConfigSource->toRawValues();
        return $options[$value];
    }

    /**
     * Get button size mapped to the value expected by the PayPal API
     *
     * @param string $area
     * @param string $type
     * @return string
     * @deprecated as Size field is redundant
     * @see no alternatives
     */
    public function getButtonSize(string $area = self::BUTTON_AREA_CART, string $type = 'paypal'): string
    {
        $value = $this->getButtonStyle($area, self::KEY_BUTTON_SIZE, $type);
        $options = $this->sizeConfigSource->toRawValues();
        return $options[$value];
    }

    /**
     * Get button label mapped to the value expected by the PayPal API
     *
     * @param string $area
     * @param string $type
     * @return string|null
     */
    public function getButtonLabel(string $area = self::BUTTON_AREA_CART, string $type = 'paypal'): ?string
    {
        return $this->getButtonStyle($area, self::KEY_BUTTON_LABEL, $type);
    }

    /**
     * Get button layout mapped to the value expected by the PayPal API
     *
     * @param string $area
     * @param string $type
     * @param string $style
     * @return string|null
     */
    public function getMessagingStyle(
        string $area = self::BUTTON_AREA_CART,
        string $type = 'paypal',
        string $style = 'layout'
    ): ?string {
        return $this->getButtonStyle($area, $style, $type);
    }

    /**
     * Get PayPal icon
     *
     * @return array
     */
    public function getPayPalIcon(): array
    {
        if (empty($this->icon)) {
            $asset = $this->ccConfig->createAsset('PayPal_Braintree::images/paypal.png');
            list($width, $height) = getimagesizefromstring($asset->getSourceFile());
            $this->icon = [
                'url' => $asset->getUrl(),
                'width' => $width,
                'height' => $height
            ];
        }

        return $this->icon;
    }

    /**
     * Disabled PayPal funding options - Card
     *
     * @param string|null $area
     * @return bool
     */
    public function isFundingOptionCardDisabled(?string $area = null): bool
    {
        if (!$area) {
            $area = self::KEY_PAYPAL_DISABLED_FUNDING_CHECKOUT;
        }

        if ($value = $this->getValue($area)) {
            if (str_contains($value, 'card')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Disabled PayPal funding options - ELV
     *
     * @param string|null $area
     * @return bool
     */
    public function isFundingOptionElvDisabled(?string $area = null): bool
    {
        if (!$area) {
            $area = self::KEY_PAYPAL_DISABLED_FUNDING_CHECKOUT;
        }

        if ($value = $this->getValue($area)) {
            if (str_contains($value, 'elv')) {
                return true;
            }
        }

        return false;
    }

    /**
     * PayPal btn enabled product page
     *
     * @return bool
     */
    public function isProductPageButtonEnabled(): bool
    {
        return (bool) $this->getValue('button_location_productpage_type_paypal_show');
    }

    /**
     * Show PayPal button status
     *
     * @param string $type
     * @param string $location
     * @return bool
     */
    public function showPayPalButton(string $type, string $location): bool
    {
        $field = 'button_location_' . $location . '_type_' . $type . '_show';
        return (bool) $this->getValue($field);
    }

    /**
     * Can send line items for the PayPal transactions
     *
     * @return bool
     */
    public function canSendCartLineItemsForPayPal(): bool
    {
        return (bool) $this->getValue(self::KEY_SEND_CART_LINE_ITEMS);
    }

    /**
     * Can skip order review step
     *
     * @return bool
     */
    public function skipOrderReviewStep(): bool
    {
        return (bool) $this->getValue(self::KEY_SKIP_ORDER_REVIEW_STEP);
    }

    /**
     * Get button styling
     *
     * @param string $area
     * @return array
     */
    public function getMessageStyles(string $area): array
    {
        return [
            'layout' => $this->getMessagingStyle($area, 'messaging', 'layout'),
            'logo' => [
                'type' => $this->getMessagingStyle($area, 'messaging', 'logo'),
                'position' => $this->getMessagingStyle($area, 'messaging', 'logo_position')
            ],
            'text' => [
                'color' => $this->getMessagingStyle($area, 'messaging', 'text_color')
            ]
        ];
    }

    /**
     * Check if shipping tracking is enabled
     *
     * @param int|null $storeId
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function isShippingTrackingEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue(
            self::KEY_SEND_PACKAGE_TRACKING,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    /**
     * Check if notify payer functionality is enabled
     *
     * @param int|null $storeId
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function notifyPayer(?int $storeId = null): bool
    {
        return (bool) $this->getValue(
            self::KEY_TRACKING_NOTIFY_PAYER,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }
}
