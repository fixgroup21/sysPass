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

namespace SP\Modules\Web\Controllers\Helpers;

use SP\Bootstrap;
use SP\Core\Acl\Acl;
use SP\Core\Acl\ActionsInterface;
use SP\Core\Crypt\CryptPKI;
use SP\Core\Exceptions\SPException;
use SP\Core\Language;
use SP\Core\UI\Theme;
use SP\Core\UI\ThemeInterface;
use SP\Html\DataGrid\DataGridAction;
use SP\Http\Uri;
use SP\Plugin\PluginManager;
use SP\Services\Install\Installer;
use SP\Util\Util;
use SP\Util\Version;

/**
 * Class LayoutHelper
 *
 * @package SP\Modules\Web\Controllers\Helpers
 */
final class LayoutHelper extends HelperBase
{
    /**
     * @var  bool
     */
    protected $loggedIn;
    /**
     * @var ThemeInterface
     */
    protected $theme;

    /**
     * Sets a full layout page
     *
     * @param string $page Page/view name
     * @param Acl    $acl
     *
     * @return LayoutHelper
     */
    public function getFullLayout($page, Acl $acl = null)
    {
        $this->view->addTemplate('main', '_layouts');
        $this->view->assign('useFixedHeader');

        $this->setPage($page);
        $this->initBody();

        if ($this->loggedIn) {
            $this->getSessionBar();
        }

        if ($acl !== null) {
            $this->getMenu($acl);
        }

        return $this;
    }

    /**
     * Establecer la variable de página de la vista
     *
     * @param $page
     */
    public function setPage($page)
    {
        $this->view->assign('page', $page);
    }

    /**
     * Inicializar las variables para la vista principal de la aplicación
     */
    public function initBody()
    {
        $this->view->assign('startTime', microtime());

        $this->view->assign('isInstalled', $this->configData->isInstalled());
        $this->view->assign('sk', $this->loggedIn ? $this->context->generateSecurityKey() : '');
        $this->view->assign('appInfo', Util::getAppInfo());
        $this->view->assign('appVersion', Installer::VERSION_TEXT);
        $this->view->assign('isDemoMode', $this->configData->isDemoEnabled());
        $this->view->assign('icons', $this->theme->getIcons());
        $this->view->assign('logoIcon', Bootstrap::$WEBURI . '/public/images/logo_icon.png');
        $this->view->assign('logoNoText', Bootstrap::$WEBURI . '/public/images/logo_icon.svg');
        $this->view->assign('logo', Bootstrap::$WEBURI . '/public/images/logo_full_bg.png');
        $this->view->assign('logonobg', Bootstrap::$WEBURI . '/public/images/logo_full_nobg.png');
        $this->view->assign('httpsEnabled', $this->request->isHttps());
        $this->view->assign('homeRoute', Acl::getActionRoute(ActionsInterface::ACCOUNT));

        $this->loggedIn = $this->context->isLoggedIn();

        $this->view->assign('loggedIn', $this->loggedIn);
        $this->view->assign('lang', $this->loggedIn ? Language::$userLang : substr(Language::$globalLang, 0, 2));
        $this->view->assign('loadApp', $this->context->getAuthCompleted());


        try {
            // Cargar la clave pública en la sesión
            $this->context->setPublicKey($this->dic->get(CryptPKI::class)->getPublicKey());
        } catch (SPException $e) {
            processException($e);
        }

        $this->getResourcesLinks();
        $this->setResponseHeaders();
    }

    /**
     * Obtener los datos para la cabcera de la página
     */
    protected function getResourcesLinks()
    {
        $version = Version::getVersionStringNormalized();

        $jsUri = new Uri(Bootstrap::$WEBURI . '/index.php');
        $jsUri->addParam('_r', 'resource/js');
        $jsUri->addParam('_v', md5($version));

        $this->view->append('jsLinks', $jsUri->getUriSigned($this->configData->getPasswordSalt()));

        $jsUri->resetParams()
            ->addParam('g', 1);

        $this->view->append('jsLinks', $jsUri->getUriSigned($this->configData->getPasswordSalt()));

        $themeInfo = $this->theme->getThemeInfo();

        if (isset($themeInfo['js'])) {
            $jsUri->resetParams()
                ->addParam('b', $this->theme->getThemePath() . DIRECTORY_SEPARATOR . 'js')
                ->addParam('f', implode(',', $themeInfo['js']));

            $this->view->append('jsLinks', $jsUri->getUriSigned($this->configData->getPasswordSalt()));
        }

        $userPreferences = $this->context->getUserData()->getPreferences();

        if ($this->loggedIn && $userPreferences->getUserId() > 0) {
            $resultsAsCards = $userPreferences->isResultsAsCards();
        } else {
            $resultsAsCards = $this->configData->isResultsAsCards();
        }

        $cssUri = new Uri(Bootstrap::$WEBURI . '/index.php');
        $cssUri->addParam('_r', 'resource/css');
        $cssUri->addParam('_v', md5($version . $resultsAsCards));

        $this->view->append('cssLinks', $cssUri->getUriSigned($this->configData->getPasswordSalt()));

        if (isset($themeInfo['css'])) {
            $themeInfo['css'][] = $resultsAsCards ? 'search-card.min.css' : 'search-grid.min.css';

            if ($this->configData->isDokuwikiEnabled()) {
                $themeInfo['css'][] = 'styles-wiki.min.css';
            }

            $cssUri->resetParams()
                ->addParam('b', $this->theme->getThemePath() . DIRECTORY_SEPARATOR . 'css')
                ->addParam('f', implode(',', $themeInfo['css']));

            $this->view->append('cssLinks', $cssUri->getUriSigned($this->configData->getPasswordSalt()));
        }

        // Cargar los recursos de los plugins
        foreach ($this->dic->get(PluginManager::class)->getLoadedPlugins() as $plugin) {
            $base = str_replace(BASE_PATH, '', $plugin->getBase());
            $jsResources = $plugin->getJsResources();
            $cssResources = $plugin->getCssResources();

            if (count($jsResources) > 0) {
                $jsUri->resetParams()
                    ->addParam('b', $base . DIRECTORY_SEPARATOR . 'js')
                    ->addParam('f', implode(',', $jsResources));

                $this->view->append('jsLinks', $jsUri->getUriSigned($this->configData->getPasswordSalt()));
            }

            if (count($cssResources) > 0) {
                $cssUri->resetParams()
                    ->addParam('b', $base . DIRECTORY_SEPARATOR . 'css')
                    ->addParam('f', implode(',', $cssResources));

                $this->view->append('cssLinks', $cssUri->getUriSigned($this->configData->getPasswordSalt()));
            }
        }
    }

    /**
     * Establecer las cabeceras HTTP
     */
    private function setResponseHeaders()
    {
        // UTF8 Headers
        header('Content-Type: text/html; charset=UTF-8');

        // Cache Control
        header('Cache-Control: public, no-cache, max-age=0, must-revalidate');
        header('Pragma: public; max-age=0');
    }

    /**
     * Obtener los datos para la mostrar la barra de sesión
     */
    public function getSessionBar()
    {
        $userType = null;

        $userData = $this->context->getUserData();
        $icons = $this->theme->getIcons();

        if ($userData->getIsAdminApp()) {
            $userType = $icons->getIconAppAdmin();
        } elseif ($userData->getIsAdminAcc()) {
            $userType = $icons->getIconAccAdmin();
        }

        $this->view->assign('context_userType', $userType);
        $this->view->assign('context_userId', $userData->getId());
        $this->view->assign('context_userLogin', mb_strtoupper($userData->getLogin()));
        $this->view->assign('context_userName', $userData->getName() ?: mb_strtoupper($this->view->userLogin));
        $this->view->assign('context_userGroup', $userData->getUserGroupName());
        $this->view->assign('showPassIcon', !($this->configData->isLdapEnabled() && $userData->getIsLdap()));
        $this->view->assign('notifications', 0);
    }

    /**
     * Obtener los datos para mostrar el menú de acciones
     *
     * @param Acl $acl
     */
    public function getMenu(Acl $acl)
    {
        $icons = $this->theme->getIcons();
        $actions = [];

        $actionSearch = new DataGridAction();
        $actionSearch->setId(ActionsInterface::ACCOUNT);
        $actionSearch->setTitle(__('Buscar'));
        $actionSearch->setIcon($icons->getIconSearch());
        $actionSearch->setData([
            'historyReset' => 1,
            'view' => 'search',
            'route' => Acl::getActionRoute(ActionsInterface::ACCOUNT)
        ]);

        $actions[] = $actionSearch;

        if ($acl->checkUserAccess(ActionsInterface::ACCOUNT_CREATE)) {
            $actionNewAccount = new DataGridAction();
            $actionNewAccount->setId(ActionsInterface::ACCOUNT_CREATE);
            $actionNewAccount->setTitle(__('Nueva Cuenta'));
            $actionNewAccount->setIcon($icons->getIconAdd());
            $actionNewAccount->setData([
                'historyReset' => 0,
                'view' => 'account',
                'route' => Acl::getActionRoute(ActionsInterface::ACCOUNT_CREATE)
            ]);

            $actions[] = $actionNewAccount;
        }

        if ($acl->checkUserAccess(ActionsInterface::ACCESS_MANAGE)) {
            $actionAccessManager = new DataGridAction();
            $actionAccessManager->setId(ActionsInterface::ACCESS_MANAGE);
            $actionAccessManager->setTitle(__('Usuarios y Accesos'));
            $actionAccessManager->setIcon($icons->getIconAccount());
            $actionAccessManager->setData([
                'historyReset' => 0,
                'view' => 'datatabs',
                'route' => Acl::getActionRoute(ActionsInterface::ACCESS_MANAGE)
            ]);

            $actions[] = $actionAccessManager;
        }

        if ($acl->checkUserAccess(ActionsInterface::ITEMS_MANAGE)) {
            $actionItemManager = new DataGridAction();
            $actionItemManager->setId(ActionsInterface::ITEMS_MANAGE);
            $actionItemManager->setTitle(__('Elementos y Personalización'));
            $actionItemManager->setIcon($icons->getIconGroup());
            $actionItemManager->setData([
                'historyReset' => 0,
                'view' => 'datatabs',
                'route' => Acl::getActionRoute(ActionsInterface::ITEMS_MANAGE)
            ]);

            $actions[] = $actionItemManager;
        }

        if ($acl->checkUserAccess(ActionsInterface::EVENTLOG) && $this->configData->isLogEnabled()) {
            $actionEventlog = new DataGridAction();
            $actionEventlog->setId(ActionsInterface::EVENTLOG);
            $actionEventlog->setTitle(__('Registro de Eventos'));
            $actionEventlog->setIcon($icons->getIconHeadline());
            $actionEventlog->setData([
                'historyReset' => 1,
                'view' => 'eventlog',
                'route' => Acl::getActionRoute(ActionsInterface::EVENTLOG)
            ]);

            $actions[] = $actionEventlog;
        }

        if ($acl->checkUserAccess(ActionsInterface::PLUGIN)) {
            $actionPlugins = new DataGridAction();
            $actionPlugins->setId(ActionsInterface::PLUGIN);
            $actionPlugins->setTitle(__('Plugins'));
            $actionPlugins->setIcon($icons->getIconByName('extension'));
            $actionPlugins->setData([
                'historyReset' => 1,
                'view' => 'plugin',
                'route' => Acl::getActionRoute(ActionsInterface::PLUGIN)
            ]);

            $actions[] =  $actionPlugins;
        }

        if ($acl->checkUserAccess(ActionsInterface::CONFIG)) {
            $actionConfigManager = new DataGridAction();
            $actionConfigManager->setId('config');
            $actionConfigManager->setTitle(__('Configuración'));
            $actionConfigManager->setIcon($icons->getIconSettings());
            $actionConfigManager->setData([
                'historyReset' => 1,
                'view' => 'config',
                'route' => Acl::getActionRoute(ActionsInterface::CONFIG)
            ]);

            $actions[] = $actionConfigManager;
        }

        $this->view->assign('actions', $actions);
        $this->view->assign('useMenu', true);
    }

    /**
     * Sets a full layout page
     *
     * @param string $template
     * @param string $page Page/view name
     *
     * @return LayoutHelper
     */
    public function getPublicLayout($template, $page = '')
    {
        $this->view->addTemplate('main', '_layouts');
        $this->view->addContentTemplate($template);
        $this->view->assign('useFixedHeader');

        $this->setPage($page);
        $this->initBody();

        return $this;
    }

    /**
     * Sets a custom layout page
     *
     * @param string $template
     * @param string $page Page/view name
     *
     * @return LayoutHelper
     */
    public function getCustomLayout($template, $page = '')
    {
        $this->view->addTemplate('main', '_layouts');
        $this->view->addContentTemplate($template);

        $this->setPage($page);
        $this->initBody();

        return $this;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initialize()
    {
        $this->theme = $this->dic->get(Theme::class);

        $this->loggedIn = $this->context->isLoggedIn();

        $this->view->assign('loggedIn', $this->loggedIn);
    }
}