<?php
/**
 * sysPass
 *
 * @author nuxsmin 
 * @link https://syspass.org
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

use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\Core\Exceptions\SPException;
use SP\Core\Exceptions\ValidationException;
use SP\Http\JsonResponse;
use SP\Http\Request;
use SP\Modules\Web\Controllers\Helpers\LayoutHelper;
use SP\Modules\Web\Controllers\Traits\JsonTrait;
use SP\Providers\Mail\MailProvider;
use SP\Repositories\Track\TrackRequest;
use SP\Services\Track\TrackService;
use SP\Services\User\UserService;
use SP\Services\UserPassRecover\UserPassRecoverService;
use SP\Util\ErrorUtil;

/**
 * Class PassresetController
 *
 * @package SP\Modules\Web\Controllers
 */
class UserPassResetController extends ControllerBase
{
    use JsonTrait;
    /**
     * @var TrackService
     */
    protected $trackService;
    /**
     * @var TrackRequest
     */
    protected $trackRequest;

    /**
     * Password reset action
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SP\Core\Dic\ContainerException
     */
    public function indexAction()
    {
        $this->dic->get(LayoutHelper::class)->getPublicLayout('request', 'passreset');

        if (!$this->configData->isMailEnabled()) {
            ErrorUtil::showErrorInView($this->view, self::ERR_UNAVAILABLE);
        }

        $this->view();
    }

    /**
     * requestAction
     */
    public function saveRequestAction()
    {
        try {
            $this->checkTracking();

            $login = Request::analyzeString('login');
            $email = Request::analyzeEmail('email');

            $userLoginResponse = $this->dic->get(UserService::class)->getByLogin($login);

            if ($userLoginResponse->getEmail() !== $email) {
                throw new SPException(__u('Datos incorrectos'), SPException::WARNING);
            }

            if ($userLoginResponse->getIsDisabled() || $userLoginResponse->getIsLdap()) {
                throw new SPException(__u('No es posible recuperar la clave'), SPException::WARNING, __u('Consulte con el administrador'));
            }

            $hash = $this->dic->get(UserPassRecoverService::class)->requestForUserId($userLoginResponse->getId());

            $this->eventDispatcher->notifyEvent('request.user.passReset',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Recuperación de Clave'))
                    ->addDetail(__u('Solicitado para'), sprintf('%s (%s)', $login, $email)))
            );

            $this->dic->get(MailProvider::class)->send(__('Cambio de Clave'), $email, UserPassRecoverService::getMailMessage($hash));

            $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Solicitud enviada'), [__u('En breve recibirá un correo para completar la solicitud.')]);
        } catch (\Exception $e) {
            processException($e);

            $this->addTracking();

            $this->returnJsonResponseException($e);
        }
    }

    /**
     * @throws SPException
     * @throws \Exception
     */
    protected function checkTracking()
    {
        if ($this->trackService->checkTracking($this->trackRequest)) {
            throw new SPException(__u('Intentos excedidos'), SPException::INFO);
        }
    }

    /**
     * Añadir un seguimiento
     */
    private function addTracking()
    {
        try {
            $this->trackService->add($this->trackRequest);
        } catch (\Exception $e) {
            processException($e);
        }
    }

    /**
     * @param null $hash
     * @throws \SP\Core\Dic\ContainerException
     */
    public function resetAction($hash = null)
    {
        $this->dic->get(LayoutHelper::class)->getPublicLayout('reset', 'passreset');

        if ($hash && $this->configData->isMailEnabled()) {
            $this->view->assign('hash', $hash);
        } else {
            ErrorUtil::showErrorInView($this->view, self::ERR_UNAVAILABLE);
        }

        $this->view();
    }

    /**
     * saveResetAction
     */
    public function saveResetAction()
    {
        try {
            $this->checkTracking();

            $pass = Request::analyzeEncrypted('pass');
            $passR = Request::analyzeEncrypted('passR');

            if (!$pass || !$passR) {
                throw new ValidationException(__u('La clave no puede estar en blanco'));
            }

            if ($pass !== $passR) {
                throw new ValidationException(__u('Las claves no coinciden'));
            }

            $hash = Request::analyzeString('hash');

            $userPassRecoverService = $this->dic->get(UserPassRecoverService::class);
            $userId = $userPassRecoverService->getUserIdForHash($hash);
            $userPassRecoverService->toggleUsedByHash($hash);

            $this->dic->get(UserService::class)->updatePass($userId, $pass);

            $this->eventDispatcher->notifyEvent('edit.user.password',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Clave actualizada'))
                    ->addDetail(__u('Usuario'), $userId))
            );

            $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Clave actualizada'));
        } catch (\Exception $e) {
            processException($e);

            $this->addTracking();

            $this->returnJsonResponseException($e);
        }
    }

    /**
     * @throws \SP\Core\Exceptions\InvalidArgumentException
     */
    protected function initialize()
    {
        $this->trackService = $this->dic->get(TrackService::class);
        $this->trackRequest = TrackService::getTrackRequest('userPassReset');
    }
}