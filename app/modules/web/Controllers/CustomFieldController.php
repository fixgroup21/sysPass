<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Modules\Web\Controllers;

use SP\Core\Acl\Acl;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\Core\Exceptions\ValidationException;
use SP\DataModel\CustomFieldDefinitionData;
use SP\Http\JsonResponse;
use SP\Modules\Web\Controllers\Helpers\Grid\CustomFieldGrid;
use SP\Modules\Web\Controllers\Traits\ItemTrait;
use SP\Modules\Web\Controllers\Traits\JsonTrait;
use SP\Modules\Web\Forms\CustomFieldDefForm;
use SP\Mvc\Controller\CrudControllerInterface;
use SP\Mvc\View\Components\SelectItemAdapter;
use SP\Services\CustomField\CustomFieldDefService;
use SP\Services\CustomField\CustomFieldTypeService;

/**
 * Class CustomFieldController
 *
 * @package SP\Modules\Web\Controllers
 */
final class CustomFieldController extends ControllerBase implements CrudControllerInterface
{
    use JsonTrait, ItemTrait;

    /**
     * @var CustomFieldDefService
     */
    protected $customFieldService;

    /**
     * Search action
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function searchAction()
    {
        if (!$this->acl->checkUserAccess(Acl::CUSTOMFIELD_SEARCH)) {
            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('No tiene permisos para realizar esta operación'));
        }

        $this->view->addTemplate('datagrid-table', 'grid');
        $this->view->assign('index', $this->request->analyzeInt('activetab', 0));
        $this->view->assign('data', $this->getSearchGrid());

        return $this->returnJsonResponseData(['html' => $this->render()]);
    }

    /**
     * getSearchGrid
     *
     * @return $this
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    protected function getSearchGrid()
    {
        $itemSearchData = $this->getSearchData($this->configData->getAccountCount(), $this->request);

        $customFieldGrid = $this->dic->get(CustomFieldGrid::class);

        return $customFieldGrid->updatePager($customFieldGrid->getGrid($this->customFieldService->search($itemSearchData)), $itemSearchData);
    }

    /**
     * Create action
     */
    public function createAction()
    {
        if (!$this->acl->checkUserAccess(Acl::CUSTOMFIELD_CREATE)) {
            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('No tiene permisos para realizar esta operación'));
        }

        $this->view->assign(__FUNCTION__, 1);
        $this->view->assign('header', __('Nuevo Campo'));
        $this->view->assign('isView', false);
        $this->view->assign('route', 'customField/saveCreate');

        try {
            $this->setViewData();

            $this->eventDispatcher->notifyEvent('show.customField.create', new Event($this));

            return $this->returnJsonResponseData(['html' => $this->render()]);
        } catch (\Exception $e) {
            processException($e);

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Sets view data for displaying custom field's data
     *
     * @param $customFieldId
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Repositories\NoSuchItemException
     */
    protected function setViewData($customFieldId = null)
    {
        $this->view->addTemplate('custom_field', 'itemshow');

        $customField = $customFieldId ? $this->customFieldService->getById($customFieldId) : new CustomFieldDefinitionData();

        $this->view->assign('field', $customField);
        $this->view->assign('types', SelectItemAdapter::factory(CustomFieldTypeService::getItemsBasic())->getItemsFromModelSelected([$customField->getTypeId()]));
        $this->view->assign('modules', SelectItemAdapter::factory(CustomFieldDefService::getFieldModules())->getItemsFromArraySelected([$customField->getModuleId()]));

        $this->view->assign('sk', $this->session->generateSecurityKey());
        $this->view->assign('nextAction', Acl::getActionRoute(Acl::ITEMS_MANAGE));

        if ($this->view->isView === true) {
            $this->view->assign('disabled', 'disabled');
            $this->view->assign('readonly', 'readonly');
        } else {
            $this->view->assign('disabled');
            $this->view->assign('readonly');
        }
    }

    /**
     * Edit action
     *
     * @param $id
     *
     * @return bool
     */
    public function editAction($id)
    {
        if (!$this->acl->checkUserAccess(Acl::CUSTOMFIELD_EDIT)) {
            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('No tiene permisos para realizar esta operación'));
        }

        $this->view->assign('header', __('Editar Campo'));
        $this->view->assign('isView', false);
        $this->view->assign('route', 'customField/saveEdit/' . $id);

        try {
            $this->setViewData($id);

            $this->eventDispatcher->notifyEvent('show.customField.edit', new Event($this));

            return $this->returnJsonResponseData(['html' => $this->render()]);
        } catch (\Exception $e) {
            processException($e);

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Delete action
     *
     * @param $id
     *
     * @return bool
     */
    public function deleteAction($id = null)
    {
        if (!$this->acl->checkUserAccess(Acl::CUSTOMFIELD_DELETE)) {
            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('No tiene permisos para realizar esta operación'));
        }

        try {
            if ($id === null) {
                $this->customFieldService->deleteByIdBatch($this->getItemsIdFromRequest($this->request));

                $this->eventDispatcher->notifyEvent('delete.customField.selection',
                    new Event($this, EventMessage::factory()->addDescription(__u('Campos eliminados')))
                );

                return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Campos eliminados'));
            } else {
                $this->customFieldService->delete($id);

                $this->eventDispatcher->notifyEvent('delete.customField', new Event($this));

                return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Campo eliminado'));
            }
        } catch (\Exception $e) {
            processException($e);

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Saves create action
     */
    public function saveCreateAction()
    {
        if (!$this->acl->checkUserAccess(Acl::CUSTOMFIELD_CREATE)) {
            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('No tiene permisos para realizar esta operación'));
        }

        try {
            $form = new CustomFieldDefForm($this->dic);
            $form->validate(Acl::CUSTOMFIELD_CREATE);

            $itemData = $form->getItemData();

            $this->customFieldService->create($itemData);

            $this->eventDispatcher->notifyEvent('create.customField',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Campo creado'))
                    ->addDetail(__u('Campo'), $itemData->getName()))
            );

            return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Campo creado'));
        } catch (ValidationException $e) {
            return $this->returnJsonResponseException($e);
        } catch (\Exception $e) {
            processException($e);

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Saves edit action
     *
     * @param $id
     *
     * @return bool
     */
    public function saveEditAction($id)
    {
        if (!$this->acl->checkUserAccess(Acl::CUSTOMFIELD_EDIT)) {
            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('No tiene permisos para realizar esta operación'));
        }

        try {
            $form = new CustomFieldDefForm($this->dic, $id);
            $form->validate(Acl::CUSTOMFIELD_EDIT);

            $itemData = $form->getItemData();

            $this->customFieldService->update($itemData);

            $this->eventDispatcher->notifyEvent('edit.customField',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Campo actualizado'))
                    ->addDetail(__u('Campo'), $itemData->getName()))
            );

            return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Campo actualizado'));
        } catch (ValidationException $e) {
            return $this->returnJsonResponseException($e);
        } catch (\Exception $e) {
            processException($e);

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * View action
     *
     * @param $id
     *
     * @return bool
     */
    public function viewAction($id)
    {
        if (!$this->acl->checkUserAccess(Acl::CUSTOMFIELD_VIEW)) {
            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('No tiene permisos para realizar esta operación'));
        }

        $this->view->assign('header', __('Ver Campo'));
        $this->view->assign('isView', true);

        try {
            $this->setViewData($id);

            $this->eventDispatcher->notifyEvent('show.customField', new Event($this));
        } catch (\Exception $e) {
            processException($e);

            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, $e->getMessage());
        }

        return $this->returnJsonResponseData(['html' => $this->render()]);
    }

    /**
     * Initialize class
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SP\Services\Auth\AuthException
     */
    protected function initialize()
    {
        $this->checkLoggedIn();

        $this->customFieldService = $this->dic->get(CustomFieldDefService::class);
    }

}