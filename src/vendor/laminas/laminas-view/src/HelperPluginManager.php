<?php

declare(strict_types=1);

namespace Laminas\View;

use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\ConfigInterface;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Exception\InvalidHelperException;
use Laminas\View\Helper\HelperInterface;
use Psr\Container\ContainerInterface;

use function gettype;
use function is_callable;
use function is_object;
use function method_exists;
use function sprintf;

/**
 * Plugin manager implementation for view helpers
 *
 * Enforces that helpers retrieved are instances of
 * Helper\HelperInterface. Additionally, it registers a number of default
 * helpers.
 *
 * @template InstanceType of HelperInterface|callable
 * @extends AbstractPluginManager<HelperInterface|callable>
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @final
 */
class HelperPluginManager extends AbstractPluginManager
{
    /**
     * Default helper aliases
     *
     * Most of these are present for legacy purposes, as v2 of the service
     * manager normalized names when fetching services.
     *
     * @psalm-suppress DeprecatedClass, NonInvariantDocblockPropertyType
     * @var non-empty-array<string, class-string>
     */
    protected $aliases = [
        'asset'               => Helper\Asset::class,
        'Asset'               => Helper\Asset::class,
        'basePath'            => Helper\BasePath::class,
        'BasePath'            => Helper\BasePath::class,
        'basepath'            => Helper\BasePath::class,
        'Cycle'               => Helper\Cycle::class,
        'cycle'               => Helper\Cycle::class,
        'declareVars'         => Helper\DeclareVars::class,
        'DeclareVars'         => Helper\DeclareVars::class,
        'declarevars'         => Helper\DeclareVars::class,
        'Doctype'             => Helper\Doctype::class,
        'doctype'             => Helper\Doctype::class, // overridden by a factory in ViewHelperManagerFactory
        'escapeCss'           => Helper\EscapeCss::class,
        'EscapeCss'           => Helper\EscapeCss::class,
        'escapecss'           => Helper\EscapeCss::class,
        'escapeHtmlAttr'      => Helper\EscapeHtmlAttr::class,
        'EscapeHtmlAttr'      => Helper\EscapeHtmlAttr::class,
        'escapehtmlattr'      => Helper\EscapeHtmlAttr::class,
        'escapeHtml'          => Helper\EscapeHtml::class,
        'EscapeHtml'          => Helper\EscapeHtml::class,
        'escapehtml'          => Helper\EscapeHtml::class,
        'escapeJs'            => Helper\EscapeJs::class,
        'EscapeJs'            => Helper\EscapeJs::class,
        'escapejs'            => Helper\EscapeJs::class,
        'escapeUrl'           => Helper\EscapeUrl::class,
        'EscapeUrl'           => Helper\EscapeUrl::class,
        'escapeurl'           => Helper\EscapeUrl::class,
        'flashmessenger'      => Helper\FlashMessenger::class,
        'flashMessenger'      => Helper\FlashMessenger::class,
        'FlashMessenger'      => Helper\FlashMessenger::class,
        'Gravatar'            => Helper\Gravatar::class,
        'gravatar'            => Helper\Gravatar::class,
        'gravatarImage'       => Helper\GravatarImage::class,
        'headLink'            => Helper\HeadLink::class,
        'HeadLink'            => Helper\HeadLink::class,
        'headlink'            => Helper\HeadLink::class,
        'headMeta'            => Helper\HeadMeta::class,
        'HeadMeta'            => Helper\HeadMeta::class,
        'headmeta'            => Helper\HeadMeta::class,
        'headScript'          => Helper\HeadScript::class,
        'HeadScript'          => Helper\HeadScript::class,
        'headscript'          => Helper\HeadScript::class,
        'headStyle'           => Helper\HeadStyle::class,
        'HeadStyle'           => Helper\HeadStyle::class,
        'headstyle'           => Helper\HeadStyle::class,
        'headTitle'           => Helper\HeadTitle::class,
        'HeadTitle'           => Helper\HeadTitle::class,
        'headtitle'           => Helper\HeadTitle::class,
        'htmlattributes'      => Helper\HtmlAttributes::class,
        'htmlAttributes'      => Helper\HtmlAttributes::class,
        'HtmlAttributes'      => Helper\HtmlAttributes::class,
        'htmlflash'           => Helper\HtmlFlash::class,
        'htmlFlash'           => Helper\HtmlFlash::class,
        'HtmlFlash'           => Helper\HtmlFlash::class,
        'htmllist'            => Helper\HtmlList::class,
        'htmlList'            => Helper\HtmlList::class,
        'HtmlList'            => Helper\HtmlList::class,
        'htmlobject'          => Helper\HtmlObject::class,
        'htmlObject'          => Helper\HtmlObject::class,
        'HtmlObject'          => Helper\HtmlObject::class,
        'htmlpage'            => Helper\HtmlPage::class,
        'htmlPage'            => Helper\HtmlPage::class,
        'HtmlPage'            => Helper\HtmlPage::class,
        'htmlquicktime'       => Helper\HtmlQuicktime::class,
        'htmlQuicktime'       => Helper\HtmlQuicktime::class,
        'HtmlQuicktime'       => Helper\HtmlQuicktime::class,
        'htmltag'             => Helper\HtmlTag::class,
        'htmlTag'             => Helper\HtmlTag::class,
        'HtmlTag'             => Helper\HtmlTag::class,
        'identity'            => Helper\Identity::class,
        'Identity'            => Helper\Identity::class,
        'inlinescript'        => Helper\InlineScript::class,
        'inlineScript'        => Helper\InlineScript::class,
        'InlineScript'        => Helper\InlineScript::class,
        'json'                => Helper\Json::class,
        'Json'                => Helper\Json::class,
        'layout'              => Helper\Layout::class,
        'Layout'              => Helper\Layout::class,
        'paginationcontrol'   => Helper\PaginationControl::class,
        'paginationControl'   => Helper\PaginationControl::class,
        'PaginationControl'   => Helper\PaginationControl::class,
        'partial'             => Helper\Partial::class,
        'partialloop'         => Helper\PartialLoop::class,
        'partialLoop'         => Helper\PartialLoop::class,
        'PartialLoop'         => Helper\PartialLoop::class,
        'Partial'             => Helper\Partial::class,
        'placeholder'         => Helper\Placeholder::class,
        'Placeholder'         => Helper\Placeholder::class,
        'renderchildmodel'    => Helper\RenderChildModel::class,
        'renderChildModel'    => Helper\RenderChildModel::class,
        'RenderChildModel'    => Helper\RenderChildModel::class,
        'render_child_model'  => Helper\RenderChildModel::class,
        'rendertoplaceholder' => Helper\RenderToPlaceholder::class,
        'renderToPlaceholder' => Helper\RenderToPlaceholder::class,
        'RenderToPlaceholder' => Helper\RenderToPlaceholder::class,
        'serverurl'           => Helper\ServerUrl::class,
        'serverUrl'           => Helper\ServerUrl::class,
        'ServerUrl'           => Helper\ServerUrl::class,
        'url'                 => Helper\Url::class,
        'Url'                 => Helper\Url::class,
        'view_model'          => Helper\ViewModel::class,
        'viewmodel'           => Helper\ViewModel::class,
        'viewModel'           => Helper\ViewModel::class,
        'ViewModel'           => Helper\ViewModel::class,

        /**
         * Legacy Zend Framework aliases
         *
         * @psalm-suppress DeprecatedClass
         * @codingStandardsIgnoreStart
         **/
        'Zend\View\Helper\Asset' => Helper\Asset::class,
        'Zend\View\Helper\FlashMessenger' => Helper\FlashMessenger::class,
        'Zend\View\Helper\Identity' => Helper\Identity::class,
        'Zend\View\Helper\BasePath' => Helper\BasePath::class,
        'Zend\View\Helper\Cycle' => Helper\Cycle::class,
        'Zend\View\Helper\DeclareVars' => Helper\DeclareVars::class,
        'Zend\View\Helper\Doctype' => Helper\Doctype::class,
        'Zend\View\Helper\EscapeHtml' => Helper\EscapeHtml::class,
        'Zend\View\Helper\EscapeHtmlAttr' => Helper\EscapeHtmlAttr::class,
        'Zend\View\Helper\EscapeJs' => Helper\EscapeJs::class,
        'Zend\View\Helper\EscapeCss' => Helper\EscapeCss::class,
        'Zend\View\Helper\EscapeUrl' => Helper\EscapeUrl::class,
        'Zend\View\Helper\Gravatar' => Helper\Gravatar::class,
        'Zend\View\Helper\HtmlTag' => Helper\HtmlTag::class,
        'Zend\View\Helper\HeadLink' => Helper\HeadLink::class,
        'Zend\View\Helper\HeadMeta' => Helper\HeadMeta::class,
        'Zend\View\Helper\HeadScript' => Helper\HeadScript::class,
        'Zend\View\Helper\HeadStyle' => Helper\HeadStyle::class,
        'Zend\View\Helper\HeadTitle' => Helper\HeadTitle::class,
        'Zend\View\Helper\HtmlFlash' => Helper\HtmlFlash::class,
        'Zend\View\Helper\HtmlList' => Helper\HtmlList::class,
        'Zend\View\Helper\HtmlObject' => Helper\HtmlObject::class,
        'Zend\View\Helper\HtmlPage' => Helper\HtmlPage::class,
        'Zend\View\Helper\HtmlQuicktime' => Helper\HtmlQuicktime::class,
        'Zend\View\Helper\InlineScript' => Helper\InlineScript::class,
        'Zend\View\Helper\Json' => Helper\Json::class,
        'Zend\View\Helper\Layout' => Helper\Layout::class,
        'Zend\View\Helper\PaginationControl' => Helper\PaginationControl::class,
        'Zend\View\Helper\PartialLoop' => Helper\PartialLoop::class,
        'Zend\View\Helper\Partial' => Helper\Partial::class,
        'Zend\View\Helper\Placeholder' => Helper\Placeholder::class,
        'Zend\View\Helper\RenderChildModel' => Helper\RenderChildModel::class,
        'Zend\View\Helper\RenderToPlaceholder' => Helper\RenderToPlaceholder::class,
        'Zend\View\Helper\ServerUrl' => Helper\ServerUrl::class,
        'Zend\View\Helper\Url' => Helper\Url::class,
        'Zend\View\Helper\ViewModel' => Helper\ViewModel::class,
        // @codingStandardsIgnoreEnd

        // v2 normalized FQCNs
        'zendviewhelperasset'               => Helper\Asset::class,
        'zendviewhelperflashmessenger'      => Helper\FlashMessenger::class,
        'zendviewhelperidentity'            => Helper\Identity::class,
        'zendviewhelperbasepath'            => Helper\BasePath::class,
        'zendviewhelpercycle'               => Helper\Cycle::class,
        'zendviewhelperdeclarevars'         => Helper\DeclareVars::class,
        'zendviewhelperdoctype'             => Helper\Doctype::class,
        'zendviewhelperescapehtml'          => Helper\EscapeHtml::class,
        'zendviewhelperescapehtmlattr'      => Helper\EscapeHtmlAttr::class,
        'zendviewhelperescapejs'            => Helper\EscapeJs::class,
        'zendviewhelperescapecss'           => Helper\EscapeCss::class,
        'zendviewhelperescapeurl'           => Helper\EscapeUrl::class,
        'zendviewhelpergravatar'            => Helper\Gravatar::class,
        'zendviewhelperhtmltag'             => Helper\HtmlTag::class,
        'zendviewhelperheadlink'            => Helper\HeadLink::class,
        'zendviewhelperheadmeta'            => Helper\HeadMeta::class,
        'zendviewhelperheadscript'          => Helper\HeadScript::class,
        'zendviewhelperheadstyle'           => Helper\HeadStyle::class,
        'zendviewhelperheadtitle'           => Helper\HeadTitle::class,
        'zendviewhelperhtmlflash'           => Helper\HtmlFlash::class,
        'zendviewhelperhtmllist'            => Helper\HtmlList::class,
        'zendviewhelperhtmlobject'          => Helper\HtmlObject::class,
        'zendviewhelperhtmlpage'            => Helper\HtmlPage::class,
        'zendviewhelperhtmlquicktime'       => Helper\HtmlQuicktime::class,
        'zendviewhelperinlinescript'        => Helper\InlineScript::class,
        'zendviewhelperjson'                => Helper\Json::class,
        'zendviewhelperlayout'              => Helper\Layout::class,
        'zendviewhelperpaginationcontrol'   => Helper\PaginationControl::class,
        'zendviewhelperpartialloop'         => Helper\PartialLoop::class,
        'zendviewhelperpartial'             => Helper\Partial::class,
        'zendviewhelperplaceholder'         => Helper\Placeholder::class,
        'zendviewhelperrenderchildmodel'    => Helper\RenderChildModel::class,
        'zendviewhelperrendertoplaceholder' => Helper\RenderToPlaceholder::class,
        'zendviewhelperserverurl'           => Helper\ServerUrl::class,
        'zendviewhelperurl'                 => Helper\Url::class,
        'zendviewhelperviewmodel'           => Helper\ViewModel::class,
    ];

    /**
     * Default factories
     *
     * basepath, doctype, and url are set up as factories in the ViewHelperManagerFactory.
     * basepath and url are not very useful without their factories, however the doctype
     * helper works fine as an invokable. The factory for doctype simply checks for the
     * config value from the merged config.
     *
     * @psalm-suppress DeprecatedClass
     *
     * {@inheritDoc}
     */
    protected $factories = [
        Helper\Asset::class          => Helper\Service\AssetFactory::class,
        Helper\HtmlAttributes::class => Helper\Service\HtmlAttributesFactory::class,
        Helper\FlashMessenger::class => Helper\Service\FlashMessengerFactory::class,
        Helper\Identity::class       => Helper\Service\IdentityFactory::class,
        Helper\BasePath::class       => Helper\Service\BasePathFactory::class,
        Helper\Cycle::class          => InvokableFactory::class,
        Helper\DeclareVars::class    => InvokableFactory::class,
        // overridden in ViewHelperManagerFactory
        Helper\Doctype::class             => Helper\Service\DoctypeFactory::class,
        Helper\EscapeHtml::class          => InvokableFactory::class,
        Helper\EscapeHtmlAttr::class      => InvokableFactory::class,
        Helper\EscapeJs::class            => InvokableFactory::class,
        Helper\EscapeCss::class           => InvokableFactory::class,
        Helper\EscapeUrl::class           => InvokableFactory::class,
        Helper\Gravatar::class            => InvokableFactory::class,
        Helper\GravatarImage::class       => InvokableFactory::class,
        Helper\HtmlTag::class             => InvokableFactory::class,
        Helper\HeadLink::class            => InvokableFactory::class,
        Helper\HeadMeta::class            => InvokableFactory::class,
        Helper\HeadScript::class          => InvokableFactory::class,
        Helper\HeadStyle::class           => InvokableFactory::class,
        Helper\HeadTitle::class           => InvokableFactory::class,
        Helper\HtmlFlash::class           => InvokableFactory::class,
        Helper\HtmlList::class            => InvokableFactory::class,
        Helper\HtmlObject::class          => InvokableFactory::class,
        Helper\HtmlPage::class            => InvokableFactory::class,
        Helper\HtmlQuicktime::class       => InvokableFactory::class,
        Helper\InlineScript::class        => InvokableFactory::class,
        Helper\Json::class                => InvokableFactory::class,
        Helper\Layout::class              => InvokableFactory::class,
        Helper\PaginationControl::class   => InvokableFactory::class,
        Helper\PartialLoop::class         => InvokableFactory::class,
        Helper\Partial::class             => InvokableFactory::class,
        Helper\Placeholder::class         => InvokableFactory::class,
        Helper\RenderChildModel::class    => InvokableFactory::class,
        Helper\RenderToPlaceholder::class => InvokableFactory::class,
        Helper\ServerUrl::class           => InvokableFactory::class,
        Helper\Url::class                 => InvokableFactory::class,
        Helper\ViewModel::class           => InvokableFactory::class,

        // v2 canonical FQCNs
        'laminasviewhelperasset'               => Helper\Service\AssetFactory::class,
        'laminasviewhelperattributes'          => InvokableFactory::class,
        'laminasviewhelperflashmessenger'      => Helper\Service\FlashMessengerFactory::class,
        'laminasviewhelperidentity'            => Helper\Service\IdentityFactory::class,
        'laminasviewhelperbasepath'            => InvokableFactory::class,
        'laminasviewhelpercycle'               => InvokableFactory::class,
        'laminasviewhelperdeclarevars'         => InvokableFactory::class,
        'laminasviewhelperdoctype'             => InvokableFactory::class,
        'laminasviewhelperescapehtml'          => InvokableFactory::class,
        'laminasviewhelperescapehtmlattr'      => InvokableFactory::class,
        'laminasviewhelperescapejs'            => InvokableFactory::class,
        'laminasviewhelperescapecss'           => InvokableFactory::class,
        'laminasviewhelperescapeurl'           => InvokableFactory::class,
        'laminasviewhelpergravatar'            => InvokableFactory::class,
        'laminasviewhelperhtmltag'             => InvokableFactory::class,
        'laminasviewhelperheadlink'            => InvokableFactory::class,
        'laminasviewhelperheadmeta'            => InvokableFactory::class,
        'laminasviewhelperheadscript'          => InvokableFactory::class,
        'laminasviewhelperheadstyle'           => InvokableFactory::class,
        'laminasviewhelperheadtitle'           => InvokableFactory::class,
        'laminasviewhelperhtmlflash'           => InvokableFactory::class,
        'laminasviewhelperhtmllist'            => InvokableFactory::class,
        'laminasviewhelperhtmlobject'          => InvokableFactory::class,
        'laminasviewhelperhtmlpage'            => InvokableFactory::class,
        'laminasviewhelperhtmlquicktime'       => InvokableFactory::class,
        'laminasviewhelperinlinescript'        => InvokableFactory::class,
        'laminasviewhelperjson'                => InvokableFactory::class,
        'laminasviewhelperlayout'              => InvokableFactory::class,
        'laminasviewhelperpaginationcontrol'   => InvokableFactory::class,
        'laminasviewhelperpartialloop'         => InvokableFactory::class,
        'laminasviewhelperpartial'             => InvokableFactory::class,
        'laminasviewhelperplaceholder'         => InvokableFactory::class,
        'laminasviewhelperrenderchildmodel'    => InvokableFactory::class,
        'laminasviewhelperrendertoplaceholder' => InvokableFactory::class,
        'laminasviewhelperserverurl'           => InvokableFactory::class,
        'laminasviewhelperurl'                 => InvokableFactory::class,
        'laminasviewhelperviewmodel'           => InvokableFactory::class,
    ];

    /** @var Renderer\RendererInterface|null */
    protected $renderer;

    /**
     * Constructor
     *
     * Merges provided configuration with default configuration.
     *
     * Adds initializers to inject the attached renderer and translator, if
     * any, to the currently requested helper.
     *
     * @param null|ConfigInterface|ContainerInterface $configOrContainerInstance
     * @param array $v3config If $configOrContainerInstance is a container, this
     *     value will be passed to the parent constructor.
     * @psalm-param ServiceManagerConfiguration $v3config
     */
    public function __construct($configOrContainerInstance = null, array $v3config = [])
    {
        $this->initializers[] = [$this, 'injectRenderer'];
        $this->initializers[] = [$this, 'injectTranslator'];
        $this->initializers[] = [$this, 'injectEventManager'];

        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Set renderer
     *
     * @return HelperPluginManager
     */
    public function setRenderer(Renderer\RendererInterface $renderer)
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * Retrieve renderer instance
     *
     * @return null|Renderer\RendererInterface
     */
    public function getRenderer()
    {
        return $this->renderer;
    }

    /**
     * Inject a helper instance with the registered renderer
     *
     * @param ContainerInterface|HelperInterface $first helper instance
     *     under laminas-servicemanager v2, ContainerInterface under v3.
     * @param ContainerInterface|HelperInterface $second
     *     ContainerInterface under laminas-servicemanager v3, helper instance
     *     under v2. Ignored regardless.
     * @return void
     */
    public function injectRenderer($first, $second)
    {
        $helper = $first instanceof ContainerInterface
            ? $second
            : $first;

        if (! $helper instanceof Helper\HelperInterface) {
            return;
        }

        $renderer = $this->getRenderer();
        if (null === $renderer) {
            return;
        }
        $helper->setView($renderer);
    }

    /**
     * Inject a helper instance with the registered translator
     *
     * @deprecated Since 2.38.0 This method will be removed in 3.0 without replacement. If your view helper requires a
     *             translator, you should instead create a factory and inject the translator into the helper constructor
     *
     * @param ContainerInterface|HelperInterface $first helper instance
     *     under laminas-servicemanager v2, ContainerInterface under v3.
     * @param ContainerInterface|HelperInterface $second
     *     ContainerInterface under laminas-servicemanager v3, helper instance
     *     under v2. Ignored regardless.
     * @return void
     */
    public function injectTranslator($first, $second)
    {
        if ($first instanceof ContainerInterface) {
            // v3 usage
            $container = $first;
            $helper    = $second;
        } else {
            // v2 usage; grab the parent container
            $container = $second->getServiceLocator();
            $helper    = $first;
        }

        // Allow either direct implementation or duck-typing.
        if (
            ! $helper instanceof TranslatorAwareInterface
            && ! method_exists($helper, 'setTranslator')
        ) {
            return;
        }

        if (! $container instanceof ContainerInterface) {
            // Under laminas-navigation v2.5, the navigation PluginManager is
            // always lazy-loaded, which means it never has a parent
            // container.
            return;
        }

        if (method_exists($helper, 'hasTranslator') && $helper->hasTranslator() === true) {
            return;
        }

        if ($container->has('MvcTranslator')) {
            $helper->setTranslator($container->get('MvcTranslator'));
            return;
        }

        if ($container->has(TranslatorInterface::class)) {
            $helper->setTranslator($container->get(TranslatorInterface::class));
            return;
        }

        if ($container->has('Translator')) {
            $helper->setTranslator($container->get('Translator'));
            return;
        }
    }

    /**
     * Inject a helper instance with the registered event manager
     *
     * @param ContainerInterface|HelperInterface $first helper instance
     *     under laminas-servicemanager v2, ContainerInterface under v3.
     * @param ContainerInterface|HelperInterface $second
     *     ContainerInterface under laminas-servicemanager v3, helper instance
     *     under v2. Ignored regardless.
     * @return void
     */
    public function injectEventManager($first, $second)
    {
        if ($first instanceof ContainerInterface) {
            // v3 usage
            $container = $first;
            $helper    = $second;
        } else {
            // v2 usage; grab the parent container
            $container = $second->getServiceLocator();
            $helper    = $first;
        }

        if (! $container instanceof ContainerInterface) {
            // Under laminas-navigation v2.5, the navigation PluginManager is
            // always lazy-loaded, which means it never has a parent
            // container.
            return;
        }

        if (! $helper instanceof EventManagerAwareInterface) {
            return;
        }

        if (! $container->has('EventManager')) {
            // If the container doesn't have an EM service, do nothing.
            return;
        }

        $events = $helper->getEventManager();
        if (! $events || ! $events->getSharedManager() instanceof SharedEventManagerInterface) {
            $helper->setEventManager($container->get('EventManager'));
        }
    }

    /**
     * Validate the plugin is of the expected type (v3).
     *
     * Validates against callables and HelperInterface implementations.
     *
     * @param mixed $instance
     * @throws InvalidServiceException
     * @psalm-assert HelperInterface|callable $instance
     */
    public function validate($instance)
    {
        if (! is_callable($instance) && ! $instance instanceof HelperInterface) {
            throw new InvalidServiceException(
                sprintf(
                    '%s can only create instances of %s and/or callables; %s is invalid',
                    static::class,
                    HelperInterface::class,
                    is_object($instance) ? $instance::class : gettype($instance)
                )
            );
        }
    }

    /**
     * Validate the plugin is of the expected type (v2).
     *
     * Proxies to `validate()`.
     *
     * @deprecated Since 2.21.0 - This method will be removed in version 3.0. It provides BC with Service Manager v2
     *             which can no longer be installed with this component.
     *
     * @param mixed $instance
     * @return void
     * @throws InvalidHelperException
     * @psalm-assert HelperInterface|callable $instance
     */
    public function validatePlugin($instance)
    {
        try {
            $this->validate($instance);
        } catch (InvalidServiceException $e) {
            throw new InvalidHelperException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     * @template T
     * @param class-string<T>|string $name Service name of plugin to retrieve.
     * @param null|array<mixed> $options Options to use when creating the instance.
     * @return HelperInterface|callable
     * @psalm-return ($name is class-string ? T : HelperInterface|callable)
     */
    public function get($name, ?array $options = null)
    {
        return parent::get($name, $options);
    }
}
