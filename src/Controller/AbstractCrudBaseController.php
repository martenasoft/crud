<?php

namespace MartenaSoft\Crud\Controller;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\ORM\Query;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use MartenaSoft\Common\Controller\AbstractAdminBaseController;
use MartenaSoft\Common\Entity\CommonEntityInterface;
use MartenaSoft\Common\Event\CommonFormShowEvent;
use MartenaSoft\Common\Exception\CommonExceptionInterface;
use MartenaSoft\Common\Exception\ElementNotFoundException;
use MartenaSoft\Common\Library\CommonValues;
use MartenaSoft\Common\Library\EntityHelper;
use MartenaSoft\Crud\Event\CrudAfterDeleteEvent;
use MartenaSoft\Crud\Event\CrudAfterFindEvent;
use MartenaSoft\Crud\Event\CrudAfterSaveEvent;
use MartenaSoft\Crud\Event\CrudBeforeDeleteEvent;
use MartenaSoft\Crud\Event\CrudBeforeSaveEvent;
use MartenaSoft\Trash\Entity\TrashEntityInterface;
use MartenaSoft\Trash\Service\MoveToTrashServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractCrudBaseController extends AbstractAdminBaseController
{
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $itemQuery = $this->getItemsQuery();

        $pagination = $paginator->paginate(
            $itemQuery,
            $request->query->getInt('page', 1),
            CommonValues::ADMIN_PAGINATION_LIMIT,
            ['distinct' => false]
        );

        return $this->renderItems($pagination);
    }

    public function save(Request $request, ?int $id = null): ?Response
    {
        if ($id === null) {
            $className = $this->getEntityClassName();
            $entity = new $className();
        } else {
            $entity = $this->getRepository()->find($id);
        }

        $form = $this->createForm($this->getFormClassName(), $entity);
        $event = new CrudAfterFindEvent($form, $entity, $request);
        $isError = false;

        try {
            $this->getEventDispatcher()->dispatch($event, CrudAfterFindEvent::getEventName());
            if (($response = $event->getResponse()) instanceof Response) {
                return $response;
            }

            if (empty($entity)) {
                throw new ElementNotFoundException();
            }

            $eventShow = new CommonFormShowEvent($form, $entity, $request);
            $this->getEventDispatcher()->dispatch($eventShow, CommonFormShowEvent::getEventName());

            if (($response = $eventShow->getResponse()) instanceof Response) {
                return $response;
            }
        } catch (\Throwable $exception) {
            $isError = true;

            $this->getLogger()->error(
                CommonValues::ERROR_FORM_SAVE_LOGGER_MESSAGE,
                [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                ]
            );

            $this->addFlash(CommonValues::FLASH_ERROR_TYPE, $this->getErrorSaveMessage($exception));
        }

        $form->handleRequest($request);

        if (!$isError && $form->isSubmitted()) {

            if ($form->isValid()) {

                try {
                    $event = new CrudBeforeSaveEvent($form, $entity, $request);
                    $event->setRedirectUrl($this->getSaveTemplate());
                    $this->getEventDispatcher()->dispatch($event, CrudBeforeSaveEvent::getEventName());

                    if (($response = $event->getResponse()) instanceof Response) {
                        return $response;
                    }

                    $this->getEntityManager()->persist($entity);
                    $this->getEntityManager()->flush();

                    $event = new CrudAfterSaveEvent($form, $entity, $request);
                    $event->setRedirectUrl($this->getSaveTemplate());
                    $this->getEventDispatcher()->dispatch($event, CrudAfterSaveEvent::getEventName());

                    if (($response = $event->getResponse()) instanceof Response) {
                        return $response;
                    }
                    $this->addFlash(CommonValues::FLASH_SUCCESS_TYPE, $this->getSuccessSaveMessage());
                    return $this->redirect($this->getIndexPageUrl());
                } catch (\Throwable $exception) {

                    $this->getLogger()->error(
                        CommonValues::ERROR_FORM_SAVE_LOGGER_MESSAGE,
                        [
                            'file' => $exception->getFile(),
                            'line' => $exception->getLine(),
                            'message' => $exception->getMessage(),
                            'code' => $exception->getCode(),
                        ]
                    );

                    $this->addFlash(CommonValues::FLASH_ERROR_TYPE, $this->getErrorSaveMessage($exception));
                }

            } else {
                $this->addFlash(CommonValues::FLASH_ERROR_TYPE, $this->getErrorSaveMessage());
            }
        }

        return $this->render(
            $this->getSaveTemplate(),
            [
                'form' => $form->createView(),
                'h1' => $this->getH1(),
                'title' => $this->getTitle()
            ]
        );
    }

    public function delete(Request $request, MoveToTrashServiceInterface $trashService, int $id): Response
    {
        $entity = $this->getRepository()->find($id);
        if ($request->getMethod() != Request::METHOD_POST) {
            return $this->confirmDelete($request, $entity, $this->getRouteIndex());
        } else {
            $post = $request->request->get('confirm_delete_form');
            $isSafeDelete = !empty($post['isSafeDelete']);
            $redirectUrl = $this->generateUrl($this->getRouteIndex());

            try {
                $event = new CrudBeforeDeleteEvent($entity, $isSafeDelete);
                $event->setRedirectUrl($redirectUrl);
                $this->getEventDispatcher()->dispatch($event, CrudBeforeDeleteEvent::getEventName());

                if (($response = $event->getResponse()) instanceof Response) {
                    $this->addFlash(
                        CommonValues::FLASH_SUCCESS_TYPE,
                        CommonValues::FLUSH_SUCCESS_DELETE_MESSAGE
                    );
                    return $response;
                }

                $this->getEntityManager()->beginTransaction();
                $this->getEntityManager()->remove($entity);
                $this->getEntityManager()->flush();

                $event = new CrudAfterDeleteEvent($entity, $isSafeDelete);
                $event->setRedirectUrl($redirectUrl);


                $this->getEventDispatcher()->dispatch($event, CrudAfterDeleteEvent::getEventName());

                $this->getEntityManager()->commit();

                if (($response = $event->getResponse()) instanceof Response) {
                    $this->addFlash(
                        CommonValues::FLASH_SUCCESS_TYPE,
                        CommonValues::FLUSH_SUCCESS_DELETE_MESSAGE
                    );
                    return $response;
                }

                $this->addFlash(
                    CommonValues::FLASH_SUCCESS_TYPE,
                    CommonValues::FLUSH_SUCCESS_DELETE_MESSAGE
                );

            } catch (\Throwable $exception) {

                $this->getEntityManager()->rollback();
                $this->getLogger()->error(
                    CommonValues::ERROR_FORM_SAVE_LOGGER_MESSAGE,
                    [
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'message' => $exception->getMessage(),
                        'code' => $exception->getCode(),
                    ]
                );
                throw $exception;
            }
        }

        return $this->redirectToRoute($this->getRouteIndex());
    }

    protected function getSuccessSaveMessage(): string
    {
        return 'Saved success';
    }

    protected function getErrorSaveMessage(?\Throwable $exception = null): string
    {
        if ($exception instanceof CommonExceptionInterface && !empty($exception->getUserMessage())) {
            return $exception->getUserMessage();
        }

        return 'Saved error';
    }

    protected function renderItems(PaginationInterface $pagination): Response
    {
        $buttonRoutes = $this->getButtonRoutes();
        $buttonRoutes['pagination'] = $pagination;

        return $this->render(
            $this->getIndexTemplate(), $buttonRoutes);
    }

    protected function getButtonRoutes(): array
    {
        return [
            'h1' => $this->getH1(),
            'title' => $this->getTitle(),
            'createButtonUrl' => $this->getCreateButtonUrl(),
            'configButtonUrl' => $this->getConfigButtonUrl(),
            'itemHeader' => $this->getItemHeader(),
            'itemsFields' => $this->itemsFields(),
            'itemBody' => $this->getItemBody(),
            'itemFooter' => $this->getItemFooter(),
            'itemPagination' => $this->getPagination(),
            'itemActionButtons' => $this->getItemActionButtons(),
            'routeCreate' => $this->getRouteCreate(),
            'routeEdit' => $this->getRouteEdit(),
            'routeDelete' => $this->getRouteDelete(),
            'routeIndex' => $this->getRouteIndex(),
        ];
    }

    protected function getPagination(): string
    {
        return '@MartenaSoftCommon/admin/item_pagination.html.twig';
    }

    protected function getItemHeader(): string
    {
        return '@MartenaSoftCommon/admin/item_header.html.twig';
    }

    protected function getItemBody(): string
    {
        return '@MartenaSoftCommon/admin/item_body.html.twig';
    }

    protected function getItemActionButtons(): string
    {
        return '@MartenaSoftCommon/admin/action_buttons_items_list.html.twig';
    }

    protected function getItemFooter(): string
    {
        return '@MartenaSoftCrud/admin/item_footer.html.twig';
    }

    protected function getIndexTemplate(): string
    {
        return '@MartenaSoftCrud/admin/index.html.twig';
    }

    protected function getSaveTemplate(): string
    {
        return '@MartenaSoftCrud/admin/save.html.twig';
    }

    protected function getItemsQuery(): Query
    {
        $queryBuilder = $this
            ->getRepository()
            ->createQueryBuilder('t_')
            ->orderBy('t_.id', 'DESC');

        $entityClass = $this->getEntityClassName();
        $entity = new $entityClass();

        if ($entity instanceof TrashEntityInterface) {
            $queryBuilder
                ->andWhere('t_.isDeleted=:isDeleted')
                ->setParameter('isDeleted', false);
        }

        return $queryBuilder->getQuery();
    }

    protected function getRepository(): ServiceEntityRepositoryInterface
    {
        return $this->getEntityManager()->getRepository($this->getEntityClassName());
    }

    abstract protected function getRepositoryClassName(): string;

    abstract protected function getEntityClassName(): string;

    abstract protected function itemsFields(): array;

    abstract protected function getH1(): string;

    abstract protected function getTitle(): string;

    abstract protected function getIndexPageUrl(): string;

    abstract protected function getCreateButtonUrl(): string;

    abstract protected function getConfigButtonUrl(): string;

    abstract protected function getFormClassName(): string;

    abstract protected function getRouteIndex(): string;

    abstract protected function getRouteCreate(): string;

    abstract protected function getRouteEdit(): string;

    abstract protected function getRouteDelete(): string;
}