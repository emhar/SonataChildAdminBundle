<?php

/*
 * This file is part of the EmharSonataChildAdminBundle bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\SonataChildAdminBundle\Security\Handler;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Security\Handler\RoleSecurityHandler as BaseRoleSecurityHandler;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * {@inheritDoc}
 */
class RoleSecurityHandler extends BaseRoleSecurityHandler
{
    /**
     * {@inheritdoc}
     * @throws \Symfony\Component\PropertyAccess\Exception\AccessException
     * @throws \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @throws \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function isGranted(AdminInterface $admin, $attributes, $object = null)
    {
        if (!is_array($attributes)) {
            $attributes = array($attributes);
        }
        /* @var array $attributes */
        foreach ($attributes as $pos => $attribute) {
            $attributes[$pos] = sprintf($this->getBaseRole($admin), $attribute);
            if ($admin instanceof AbstractAdmin && $admin->isChild()) {
                $parentAdmin = $admin->getParent();
                if ($parentAdmin->isGranted('EDIT', $parentAdmin->getSubject())) {
                    if (!$admin->getSubject()) {
                        //Can access if child admin doesn't have subject
                        $attributes[] = sprintf($this->getBaseParentRole($admin), $attribute);
                    } elseif ($parentAdmin instanceof AbstractAdmin) {
                        //Check if parent object if truly the parent of child object
                        //Sonata doesn't check this, parent is just fetched with url params
                        $accessor = PropertyAccess::createPropertyAccessor();
                        $childParent = $accessor->getValue($admin->getSubject(), $admin->getParentAssociationMapping());
                        if ($parentAdmin->id($childParent) === $parentAdmin->id($parentAdmin->getSubject())) {
                            $attributes[] = sprintf($this->getBaseParentRole($admin), $attribute);
                        }
                    }
                }
            }
        }

        $allRoles = array();
        $allRoles[] = sprintf($this->getBaseRole($admin), 'ALL');
        if ($admin->isChild()) {
            $parentAdmin = $admin->getParent();
            if ($parentAdmin->isGranted('EDIT', $parentAdmin->getSubject())) {
                if (!$admin->getSubject()) {
                    //Can access if child admin doesn't have subject
                    $allRoles[] = sprintf($this->getBaseParentRole($admin), 'ALL');
                } elseif ($parentAdmin instanceof AbstractAdmin) {
                    $accessor = PropertyAccess::createPropertyAccessor();
                    $childParent = $accessor->getValue($admin->getSubject(), $admin->getParentAssociationMapping());
                    if ($parentAdmin->id($childParent) === $parentAdmin->id($parentAdmin->getSubject())) {
                        $allRoles[] = sprintf($this->getBaseParentRole($admin), 'ALL');
                    }
                }
            }
        }

        try {
            return $this->authorizationChecker->isGranted($this->superAdminRoles)
                || $this->authorizationChecker->isGranted($attributes, $object)
                || $this->authorizationChecker->isGranted($allRoles, $object);
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
        }
    }

    /**
     * @param AdminInterface $admin
     * @return string
     */
    public function getBaseParentRole(AdminInterface $admin)
    {
        $role = str_replace('.', '_', strtoupper($admin->getCode())) . '_%s';
        while($parentAdmin = $admin->getParent()){
            $role = str_replace('.', '_', strtoupper($parentAdmin->getCode())) . '_' . $role;
            $admin = $parentAdmin;
        }
        return 'ROLE_' . $role;
    }
}