<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Permissions
 */

namespace Zend\Permissions\Rbac;

use RecursiveIteratorIterator;

/**
 * @category   Zend
 * @package    Zend_Permissions
 * @subpackage Rbac
 */
class Rbac extends AbstractIterator
{
    /**
     * flag: whether or not to create roles automatically if
     * they do not exist.
     *
     * @var bool
     */
    protected $createMissingRoles = false;

    /**
     * @param  boolean                     $createMissingRoles
     * @return \Zend\Permissions\Rbac\Rbac
     */
    public function setCreateMissingRoles($createMissingRoles)
    {
        $this->createMissingRoles = $createMissingRoles;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getCreateMissingRoles()
    {
        return $this->createMissingRoles;
    }

    /**
     * Add a child.
     *
     * @param  string|AbstractRole                $child
     * @return AbstractRole
     * @throws Exception\InvalidArgumentException
     */
    public function addRole($child, $parents = null)
    {
        if (is_string($child)) {
            $child = new Role($child);
        }
        if (!$child instanceof AbstractRole) {
            throw new Exception\InvalidArgumentException(
                'Child must be a string or instance of Zend\Permissions\Rbac\AbstractRole'
            );
        }

        if ($parents) {
            if (!is_array($parents)) {
                $parents = array($parents);
            }
            foreach ($parents as $parent) {
                if ($this->createMissingRoles && !$this->hasRole($parent)) {
                    $this->addRole($parent);
                }
                $this->getRole($parent)->addChild($child);
            }
        }

        $this->children[] = $child;

        return $this;
    }

    /**
     * Is a child with $name registered?
     *
     * @param  \Zend\Permissions\Rbac\AbstractRole|string $objectOrName
     * @return bool
     */
    public function hasRole($objectOrName)
    {
        try {
            $this->getRole($objectOrName);

            return true;
        } catch (Exception\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Get a child.
     *
     * @param  \Zend\Permissions\Rbac\AbstractRole|string $objectOrName
     * @return AbstractRole
     * @throws Exception\InvalidArgumentException
     */
    public function getRole($objectOrName)
    {
        if (!is_string($objectOrName) && !$objectOrName instanceof AbstractRole) {
            throw new Exception\InvalidArgumentException(
                'Expected string or instance of \Zend\Permissions\Rbac\AbstractRole'
            );
        }

        $it = new RecursiveIteratorIterator($this, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $leaf) {
            if ((is_string($objectOrName) && $leaf->getName() == $objectOrName) || $leaf == $objectOrName) {
                return $leaf;
            }
        }

        throw new Exception\InvalidArgumentException(sprintf(
            'No child with name "%s" could be found',
            is_object($objectOrName) ? $objectOrName->getName() : $objectOrName
        ));
    }

    /**
     * Determines if access is granted by checking the role and child roles for permission.
     *
     * @param string                                                  $permission
     * @param \Zend\Permissions\Rbac\AssertionInterface|Callable|null $assert
     */
    public function isGranted($role, $permission, $assert = null)
    {
        if ($assert) {
            if ($assert instanceof AssertionInterface) {
                if (!$assert->assert($this)) {
                    return false;
                }
            } elseif (is_callable($assert)) {
                if (!$assert($this)) {
                    return false;
                }
            } else {
                throw new Exception\InvalidArgumentException(
                    'Assertions must be a Callable or an instance of Zend\Permissions\Rbac\AssertionInterface'
                );
            }
        }

        if ($this->getRole($role)->hasPermission($permission)) {
            return true;
        }

        return false;
    }
}