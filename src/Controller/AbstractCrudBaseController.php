<?php

namespace MartenaSoft\Crud\Controller;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\ORM\Query;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use MartenaSoft\Common\Controller\AbstractAdminBaseController;
use MartenaSoft\Common\Entity\CommonEntityInterface;
use MartenaSoft\Common\Event\CommonFormAfterDeleteEvent;
use MartenaSoft\Common\Event\CommonFormAfterSaveEvent;
use MartenaSoft\Common\Event\CommonFormBeforeDeleteEvent;
use MartenaSoft\Common\Event\CommonFormBeforeSaveEvent;
use MartenaSoft\Common\Exception\ElementNotFoundException;
use MartenaSoft\Common\Library\CommonValues;
use MartenaSoft\Menu\Entity\BaseMenuInterface;
use MartenaSoft\Menu\Entity\MenuInterface;
use MartenaSoft\Menu\Event\DeleteMenuEvent;
use MartenaSoft\Menu\Event\SaveMenuEvent;
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
            CommonValues::ADMIN_PAGINATION_LIMIT
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

        if (empty($entity)) {
            throw new ElementNotFoundException();
        }

        $form = $this->createForm($this->getFormClassName(), $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->getEventDispatcher(new CommonFormBeforeSaveEvent($form));
            if ($form->isValid()) {
                if ($entity instanceof BaseMenuInterface) {
                    $menuEntity = $form->getData()->getMenu();

                    if (!empty($menuEntity)) {
                        $this->getEventDispatcher()
                            ->dispatch(
                                new SaveMenuEvent(
                                    $form->getData()->getMenu(),
                                    $entity
                                ),
                                SaveMenuEvent::getEventName()
                            );

                    }
                }

                $this->getEntityManager()->persist($entity);
                $this->getEntityManager()->flush();
                $this->addFlash(CommonValues::FLASH_SUCCESS_TYPE, $this->getSuccessSaveMessage());
                $this->getEventDispatcher(new CommonFormAfterSaveEvent($form));
                return $this->redirect($this->getIndexPageUrl());
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
            try {
                $this->deleteItem($entity, $trashService, $isSafeDelete);
            } catch (\Throwable $exception) {
                $this->getLogger()->error(
                    CommonValues::ERROR_FORM_SAVE_LOGGER_MESSAGE,
                    [
                        'file' => __CLASS__,
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

    protected function deleteItem(
        CommonEntityInterface $entity,
        MoveToTrashServiceInterface $trashService,
        bool $isSafeDelete
    ): void {
        try {
            $this->getEntityManager()->beginTransaction();

            $commonFormBeforeDeleteEvent = new CommonFormBeforeDeleteEvent($entity);


            $this->getEventDispatcher()->dispatch(
                $commonFormBeforeDeleteEvent,
                CommonFormBeforeDeleteEvent::getEventName()
            );

            if ($entity instanceof MenuInterface) {

                $menuEntity = $entity->getMenu();

                $deleteMenuEvent = new DeleteMenuEvent($entity, $menuEntity);
                if ($deleteMenuEvent instanceof TrashEntityInterface) {
                    $deleteMenuEvent->setIsDeleted($isSafeDelete);

                    if ($trashService instanceof  MoveToTrashServiceInterface) {
                        $trashService->move($menuEntity);
                    }
                }

                $this
                    ->getEventDispatcher()
                    ->dispatch(
                        $deleteMenuEvent,
                        DeleteMenuEvent::getEventName()
                    );
            }

            $this->getEventDispatcher()->dispatch(
                new CommonFormAfterDeleteEvent($entity),
                CommonFormAfterDeleteEvent::getEventName()
            );

            if ($trashService instanceof  MoveToTrashServiceInterface) {
                $trashService->move($entity);
            }

            $this->getEntityManager()->commit();
            $this->addFlash(
                CommonValues::FLASH_SUCCESS_TYPE,
                CommonValues::FLUSH_SUCCESS_DELETE_MESSAGE
            );
        } catch (\Throwable $exception) {
            $this->getEntityManager()->rollback();
            throw $exception;
        }
    }

    protected function getSuccessSaveMessage(): string
    {
        return 'Saved success';
    }

    protected function getErrorSaveMessage(): string
    {
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
        return '@MartenaSoftCommon/admin/item_footer.html.twig';
    }

    protected function getIndexTemplate(): string
    {
        return '@MartenaSoftCommon/admin/index.html.twig';
    }

    protected function getSaveTemplate(): string
    {
        return '@MartenaSoftCommon/admin/save.html.twig';
    }

    protected function getItemsQuery(): Query
    {
        return $this
            ->getRepository()
            ->createQueryBuilder('t_')
            ->getQuery();
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