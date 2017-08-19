<?php

/*
 * This file is part of the EmharSonataChildAdminBundle bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\SonataChildAdminBundle\Controller;

use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Controller\HelperController as BaseHelperController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * {@inheritDoc}
 */
class HelperController extends BaseHelperController
{


    /**
     * @param Request $request
     *
     * @return Response
     * @throws \Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException
     * @throws \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    public function setObjectFieldValueAction(Request $request)
    {
        $field = $request->get('field');
        $code = $request->get('code');
        $objectId = $request->get('objectId');
        $value = $originalValue = $request->get('value');
        $context = $request->get('context');
        $parentId = $request->get('parentId');
        $parentCode = $request->get('parentCode');

        $admin = $this->pool->getInstance($code);
        /* @var $admin AdminInterface */
        $admin->setRequest($request);
        if ($parentId && $parentCode) {
            $parentAdmin = $this->pool->getInstance($parentCode);
            $parentAdmin->setSubject($parentAdmin->getObject($parentId));
            $admin->setParent($parentAdmin);
        }


        // alter should be done by using a post method
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse('Expected an XmlHttpRequest request header', 405);
        }

        if ($request->getMethod() != 'POST') {
            return new JsonResponse('Expected a POST Request', 405);
        }

        $rootObject = $object = $admin->getObject($objectId);

        if (!$object) {
            return new JsonResponse('Object does not exist', 404);
        }

        // check user permission
        if (false === $admin->hasAccess('edit', $object)) {
            return new JsonResponse('Invalid permissions', 403);
        }

        if ($context == 'list') {
            $fieldDescription = $admin->getListFieldDescription($field);
        } else {
            return new JsonResponse('Invalid context', 400);
        }

        if (!$fieldDescription) {
            return new JsonResponse('The field does not exist', 400);
        }

        if (!$fieldDescription->getOption('editable')) {
            return new JsonResponse('The field cannot be edited, editable option must be set to true', 400);
        }

        $propertyPath = new PropertyPath($field);

        // If property path has more than 1 element, take the last object in order to validate it
        if ($propertyPath->getLength() > 1) {
            $object = $this->pool->getPropertyAccessor()->getValue($object, $propertyPath->getParent());

            $elements = $propertyPath->getElements();
            $field = end($elements);
            $propertyPath = new PropertyPath($field);
        }

        // Handle date type has setter expect a DateTime object
        if ('' !== $value && $fieldDescription->getType() == 'date') {
            $value = new \DateTime($value);
        }

        // Handle boolean type transforming the value into a boolean
        if ('' !== $value && $fieldDescription->getType() == 'boolean') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        // Handle entity choice association type, transforming the value into entity
        if ('' !== $value && $fieldDescription->getType() == 'choice' && $fieldDescription->getOption('class')) {
            // Get existing associations for current object
            $associations = $admin->getModelManager()
                ->getEntityManager($admin->getClass())->getClassMetadata($admin->getClass())
                ->getAssociationNames();

            if (!in_array($field, $associations)) {
                return new JsonResponse(
                    sprintf(
                        'Unknown association "%s", association does not exist in entity "%s", available associations are "%s".',
                        $field,
                        $this->admin->getClass(),
                        implode(', ', $associations)),
                    404);
            }

            $value = $admin->getConfigurationPool()->getContainer()->get('doctrine')->getManager()
                ->getRepository($fieldDescription->getOption('class'))
                ->find($value);

            if (!$value) {
                return new JsonResponse(
                    sprintf(
                        'Edit failed, object with id "%s" not found in association "%s".',
                        $originalValue,
                        $field),
                    404);
            }
        }

        $this->pool->getPropertyAccessor()->setValue($object, $propertyPath, '' !== $value ? $value : null);

        $violations = $this->validator->validate($object);

        if (count($violations)) {
            $messages = array();

            foreach ($violations as $violation) {
                $messages[] = $violation->getMessage();
            }

            return new JsonResponse(implode("\n", $messages), 400);
        }

        $admin->update($object);

        // render the widget
        // todo : fix this, the twig environment variable is not set inside the extension ...
        $extension = $this->twig->getExtension('Sonata\AdminBundle\Twig\Extension\SonataAdminExtension');

        $content = $extension->renderListElement($this->twig, $rootObject, $fieldDescription);

        return new JsonResponse($content, 200);
    }

    /**
     * @throws NotFoundHttpException|\RuntimeException
     *
     * @param Request $request
     *
     * @return Response
     * @throws \InvalidArgumentException
     * @throws \Twig_Error_Syntax
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Loader
     */
    public function getShortObjectDescriptionAction(Request $request)
    {
        $code = $request->get('code');
        $objectId = $request->get('objectId');
        $parentId = $request->get('parentId');
        $uniqid = $request->get('uniqid');
        $linkParameters = $request->get('linkParameters', array());

        $admin = $this->pool->getAdminByAdminCode($code);

        if (!$admin) {
            throw new NotFoundHttpException();
        }

        if ($parentId && $admin->isChild()) {
            $parentAdmin = $admin->getParent();
            $parentAdmin->setSubject($parentAdmin->getObject($parentId));
        }

        $admin->setRequest($request);

        if ($uniqid) {
            $admin->setUniqid($uniqid);
        }

        if (!$objectId) {
            $objectId = null;
        }

        $object = $admin->getObject($objectId);

        if (!$object && 'html' == $request->get('_format')) {
            return new Response();
        }

        if ('json' == $request->get('_format')) {
            return new JsonResponse(array('result' => array(
                'id' => $admin->id($object),
                'label' => $admin->toString($object),
            )));
        }

        if ('html' == $request->get('_format')) {
            return new Response($this->twig->render($admin->getTemplate('short_object_description'), array(
                'admin' => $admin,
                'description' => $admin->toString($object),
                'object' => $object,
                'link_parameters' => $linkParameters,
            )));
        }
        throw new \RuntimeException('Invalid format');
    }
}