<?php

declare(strict_types=1);

namespace Laminas\View\Helper\Navigation;

use Laminas\Navigation;
use Laminas\Permissions\Acl;
use Laminas\View\Exception\ExceptionInterface;
use Laminas\View\Helper\HelperInterface as BaseHelperInterface;

/**
 * Interface for navigational helpers
 *
 * @deprecated This class has been moved to the `Laminas\Navigation` component and will be removed in 3.0
 */
interface HelperInterface extends BaseHelperInterface
{
    /**
     * Magic overload: Should proxy to {@link render()}.
     *
     * @return string
     */
    public function __toString();

    /**
     * Renders helper
     *
     * @param  string|Navigation\AbstractContainer $container [optional] container to render.
     *                                         Default is null, which indicates
     *                                         that the helper should render
     *                                         the container returned by {@link
     *                                         getContainer()}.
     * @return string helper output
     * @throws ExceptionInterface
     */
    public function render($container = null);

    /**
     * Sets ACL to use when iterating pages
     *
     * @param  Acl\AclInterface $acl|null [optional] ACL instance
     * @return HelperInterface
     */
    public function setAcl(?Acl\AclInterface $acl = null);

    /**
     * Returns ACL or null if it isn't set using {@link setAcl()} or
     * {@link setDefaultAcl()}
     *
     * @return Acl\AclInterface|null
     */
    public function getAcl();

    /**
     * Checks if the helper has an ACL instance
     *
     * @return bool
     */
    public function hasAcl();

    /**
     * Sets navigation container the helper should operate on by default
     *
     * @param  string|Navigation\AbstractContainer $container [optional] container to operate
     *                                         on. Default is null, which
     *                                         indicates that the container
     *                                         should be reset.
     * @return HelperInterface
     */
    public function setContainer($container = null);

    /**
     * Returns the navigation container the helper operates on by default
     *
     * @return Navigation\AbstractContainer  navigation container
     */
    public function getContainer();

    /**
     * Checks if the helper has a container
     *
     * @return bool
     */
    public function hasContainer();

    /**
     * Render invisible items?
     *
     * @param  bool $renderInvisible [optional] boolean flag
     * @return HelperInterface
     */
    public function setRenderInvisible($renderInvisible = true);

    /**
     * Return renderInvisible flag
     *
     * @return bool
     */
    public function getRenderInvisible();

    /**
     * Sets ACL role to use when iterating pages
     *
     * @param  mixed $role [optional] role to set.  Expects a string, an
     *                     instance of type {@link Acl\Role}, or null. Default
     *                     is null.
     * @throws ExceptionInterface If $role is invalid.
     * @return HelperInterface
     */
    public function setRole($role = null);

    /**
     * Returns ACL role to use when iterating pages, or null if it isn't set
     *
     * @return string|Acl\Role\RoleInterface|null
     */
    public function getRole();

    /**
     * Checks if the helper has an ACL role
     *
     * @return bool
     */
    public function hasRole();

    /**
     * Sets whether ACL should be used
     *
     * @param  bool $useAcl [optional] whether ACL should be used. Default is true.
     * @return HelperInterface
     */
    public function setUseAcl($useAcl = true);

    /**
     * Returns whether ACL should be used
     *
     * @return bool
     */
    public function getUseAcl();
}
