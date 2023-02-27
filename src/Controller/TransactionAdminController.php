<?php
// src/Controller/TransactionAdminController.php

namespace App\Controller;

use App\Admin\TransactionAdmin;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;

use Sonata\AdminBundle\Exception\LockException;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Sonata\AdminBundle\Exception\ModelManagerThrowable;


class TransactionAdminController extends CRUDController
{
    /**
     * @param $id
     */
    public function cloneAction($id): Response
    {
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id: %s', $id));
        }

        // Be careful, you may need to overload the __clone method of your object
        // to set its id to null !
        $clonedObject = clone $object;

        $clonedObject->setName($object->getName().' (Clone)');

        $this->admin->create($clonedObject);

        $this->addFlash('sonata_flash_success', 'Cloned successfully');

        return new RedirectResponse($this->admin->generateUrl('list'));
    }

    public string $customizePath = "customize";


    public function customizeAction(Request $request): Response
    {
        $existingObject = $this->assertObjectExists($request, true);
        \assert(null !== $existingObject);

        $this->checkParentChildAssociation($request, $existingObject);

        $this->admin->checkAccess('customize', $existingObject);

        $preResponse = $this->preEdit($request, $existingObject);
        if (null !== $preResponse) {
            return $preResponse;
        }

        $this->admin->setSubject($existingObject);
        $objectId = $this->admin->getNormalizedIdentifier($existingObject);
        \assert(null !== $objectId);


        $id = $request->get('id');
        $admin = $this->admin;
        //$this->createForm()
        if($admin instanceof TransactionAdmin)
        {
            $form = $admin->getEditCustomForm();
        }


        $form->setData($existingObject);

        $formView = $form->createView();

        $form->handleRequest($request);
        if ($form->isSubmitted()) {

            $isFormValid = $form->isValid();

            // persist if the form was valid and if in preview mode the preview was approved
            if ($isFormValid && (!$this->isInPreviewMode($request) || $this->isPreviewApproved($request))) {
                /** @phpstan-var T $submittedObject */
                $submittedObject = $form->getData();
                $this->admin->setSubject($submittedObject);

                try {
                    $existingObject = $this->admin->update($submittedObject);

                    if ($this->isXmlHttpRequest($request)) {
                        return $this->handleXmlHttpRequestSuccessResponse($request, $existingObject);
                    }

                    $this->addFlash(
                        'sonata_flash_success',
                        $this->trans(
                            'flash_edit_success',
                            ['%name%' => $this->escapeHtml($this->admin->toString($existingObject))],
                            'SonataAdminBundle'
                        )
                    );

                    // redirect to edit mode
                    return $this->redirectTo($request, $existingObject);
                } catch (ModelManagerException $e) {
                    // NEXT_MAJOR: Remove this catch.
                    $this->handleModelManagerException($e);

                    $isFormValid = false;
                } catch (ModelManagerThrowable $e) {
                    $errorMessage = $this->handleModelManagerThrowable($e);

                    $isFormValid = false;
                } catch (LockException $e) {
                    $this->addFlash('sonata_flash_error', $this->trans('flash_lock_error', [
                        '%name%' => $this->escapeHtml($this->admin->toString($existingObject)),
                        '%link_start%' => sprintf('<a href="%s">', $this->admin->generateObjectUrl('customize', $existingObject)),
                        '%link_end%' => '</a>',
                    ], 'SonataAdminBundle'));
                }
            }

            // show an error message if the form failed validation
            if (!$isFormValid) {
                if ($this->isXmlHttpRequest($request) && null !== ($response = $this->handleXmlHttpRequestErrorResponse($request, $form))) {
                    return $response;
                }

                $this->addFlash(
                    'sonata_flash_error',
                    $errorMessage ?? $this->trans(
                    'flash_edit_error',
                    ['%name%' => $this->escapeHtml($this->admin->toString($existingObject))],
                    'SonataAdminBundle'
                )
                );
            } elseif ($this->isPreviewRequested($request)) {
                // enable the preview template if the form was valid and preview was requested
                $templateKey = 'preview';
                $this->admin->getShow();
            }
        }



        // set the theme for the current Admin Form
        $this->setFormTheme($formView, $this->admin->getFormTheme());

        $this->templateR = $this->admin->getTemplateRegistry();
        if(!$this->templateR){
            $this->templateR = $this->admin->getTemplateRegistry();
        }

        $template = "CRUD/edit__action_customize.html.twig";

        return $this->renderWithExtraParams($template, [
            'action' => 'customize',
            'form' => $formView,
            'object' => $existingObject,
            'objectId' => $id,
        ]);
   }

   protected function redirectTo(Request $request, object $object): RedirectResponse
   {
       if (null !== $request->get('btn_update_and_list')) {
           return $this->redirectToList();
       }
       if (null !== $request->get('btn_create_and_list')) {
           return $this->redirectToList();
       }
       if(null !== $request->get('btn_customize_and_list')){
           return $this->redirectToList();
       }

       if (null !== $request->get('btn_create_and_create')) {
           $params = [];
           if ($this->admin->hasActiveSubClass()) {
               $params['subclass'] = $request->get('subclass');
           }

           return new RedirectResponse($this->admin->generateUrl('create', $params));
       }

       if (null !== $request->get('btn_delete')) {
           return $this->redirectToList();
       }
        //dd($object);
       $pathInfo = $request->getPathInfo();
       $routes= explode("/",$pathInfo);
       //dd($routes);
       if($routes>1)
       {
          $lastUri = $routes[sizeof($routes)-1];
       }
       //dd($lastUri);
       //$lastUri = $request->getPathInfo();
       if($lastUri){
           if ($this->admin->hasRoute($lastUri) && $this->admin->hasAccess($lastUri, $object)) {
               $url = $this->admin->generateObjectUrl(
                   $lastUri,
                   $object,
                   $this->getSelectedTab($request)
               );
               return new RedirectResponse($url);
           }
       }

       foreach (['edit', 'show'] as $route) {
           if ($this->admin->hasRoute($route) && $this->admin->hasAccess($route, $object)) {
               $url = $this->admin->generateObjectUrl(
                   $route,
                   $object,
                   $this->getSelectedTab($request)
               );

               return new RedirectResponse($url);
           }
       }

       return $this->redirectToList();
   }
}