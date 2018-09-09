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

use SP\DataModel\DataModelInterface;
use SP\Http\Json;
use SP\Http\JsonResponse;
use SP\Mvc\View\Components\SelectItemAdapter;
use SP\Services\Account\AccountService;
use SP\Services\Category\CategoryService;
use SP\Services\Client\ClientService;
use SP\Services\Notification\NotificationService;

/**
 * Class ItemsController
 *
 * @package SP\Modules\Web\Controllers
 */
final class ItemsController extends SimpleControllerBase
{
    /**
     * Devolver las cuentas visibles por el usuario
     *
     * @param int $accountId
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function accountsUserAction($accountId = null)
    {
        $outItems = [];

        foreach ($this->dic->get(AccountService::class)->getForUser($accountId) as $account) {
            $obj = new \stdClass();
            $obj->id = $account->id;
            $obj->name = $account->clientName . ' - ' . $account->name;

            $outItems[] = $obj;
        }

        $jsonResponse = new JsonResponse();
        $jsonResponse->setStatus(0);
        $jsonResponse->setData($outItems);
        $jsonResponse->setCsrf($this->session->getSecurityKey());

        Json::fromDic()->returnJson($jsonResponse);
    }

    /**
     * @throws \SP\Core\Exceptions\SPException
     */
    public function clientsAction()
    {
        Json::factory($this->router->response())
            ->returnRawJson(SelectItemAdapter::factory($this->dic->get(ClientService::class)->getAllForUser())->getJsonItemsFromModel());
    }

    /**
     * @throws \SP\Core\Exceptions\SPException
     */
    public function categoriesAction()
    {
        Json::factory($this->router->response())
            ->returnRawJson(SelectItemAdapter::factory($this->dic->get(CategoryService::class)->getAllBasic())->getJsonItemsFromModel());
    }

    /**
     * @throws \SP\Core\Exceptions\SPException
     */
    public function notificationsAction()
    {
        Json::factory($this->router->response())
            ->returnRawJson(Json::getJson($this->dic->get(NotificationService::class)->getAllActiveForUserId($this->session->getUserData()->getId())));
    }

    /**
     * ItemsController constructor.
     */
    protected function initialize()
    {
        $this->checks();
    }

    /**
     * Preparar los elementos para devolverlos
     *
     * @param array $items
     *
     * @return array
     */
    protected function prepareItems(array $items)
    {
        $outItems = [];

        /** @var DataModelInterface $item */
        foreach ($items as $item) {
            $obj = new \stdClass();
            $obj->id = $item->getId();
            $obj->name = $item->getName();

            $outItems[] = $obj;
        }

        return $outItems;
    }
}