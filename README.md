# Sonata Child Admin, Symfony Bundle

This bundle provides some useful functionality for child admin in sonata admin.

## Security, child admin role

Use case :

An association supervisor can edit his association.
It can not edit other associations.
In the association, we have members.

The supervisor can modify the members of his association.
It can not edit members of other associations.

Role Admin Chain :

We have two admin : Association and Members

In association admin you must implements a custom method to check grant :
```php
    /**
     * {@inheritDoc}
     */
    public function isGranted($name, $object = null)
    {
        if (parent::isGranted($name, $object)) {
            return true;
        }
        $token = $this->tokenStorage->getToken();
        if ($token) {
            $user = $token->getUser();
            if ($user instanceof AssociationSupervisor) {
                return $name === 'EDIT' && $user->getAssociation() === $object;
            }
        }
        return false;
    }
```

Supervisor must have this role to have a full access on his members :
```
ROLE_ADMIN_ASSOCIATION_ADMIN_MEMBER_ALL
```

or this role to have only list access for example
```
ROLE_ADMIN_ASSOCIATION_ADMIN_MEMBER_LIST
```

(under the hood : we check if user have edit access on parent object, in this case we check role to allow action on child)

## List all Action

In some case we need to embed a list with all objects, not paginated.
(diplay all member in association show page for example.)

The list all action is added to do this.

## Allow child management on some sonata functionality

* x-editable
* sonata_type_model_list
* link to show page, edit page 