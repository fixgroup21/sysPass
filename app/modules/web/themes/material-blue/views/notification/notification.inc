<?php
/** @var  \SP\DataModel\NotificationData $notification */
/** @var  \SP\Core\UI\ThemeIcons $icons */
?>
<div id="box-popup">
    <h2 class="center"><?php echo $header; ?><i class="btn-popup-close material-icons">close</i></h2>

    <form method="post" name="frmNotices" id="frmNotices" class="form-action"
          data-onsubmit="notification/save"
          data-route="<?php echo isset($route) ? $route : ''; ?>"
          data-action-next="<?php echo $nextAction; ?>"
          data-hash="">
        <table class="popup-data">
            <tbody>
            <tr>
                <td class="descField"><?php echo __('Tipo'); ?></td>
                <td class="valField">
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input id="notification_type" name="notification_type" type="text"
                               class="mdl-textfield__input mdl-color-text--indigo-400"
                               value="<?php echo $notification->getType(); ?>" <?php echo $disabled; ?>>
                        <label class="mdl-textfield__label"
                               for="notification_type"><?php echo __('Tipo'); ?></label>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="descField"><?php echo __('Componente'); ?></td>
                <td class="valField">
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input id="notification_component" name="notification_component" type="text"
                               class="mdl-textfield__input mdl-color-text--indigo-400"
                               value="<?php echo $notification->getComponent(); ?>" <?php echo $disabled; ?>>
                        <label class="mdl-textfield__label"
                               for="notification_component"><?php echo __('Componente'); ?></label>
                    </div>
                </td>
            </tr>
            <?php if ($isView): ?>
                <tr>
                    <td class="descField"><?php echo __('Fecha'); ?></td>
                    <td class="valField">
                        <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                            <input id="notification_date" name="notification_date" type="text"
                                   class="mdl-textfield__input mdl-color-text--indigo-400"
                                   value="<?php echo \SP\Util\DateUtil::getDateFromUnix($notification->getDate()); ?>" <?php echo $disabled; ?>>
                            <label class="mdl-textfield__label"
                                   for="notification_date"><?php echo __('Fecha'); ?></label>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <tr>
                <td class="descField"><?php echo __('Descripción'); ?></td>
                <td class="valField">
                    <div class="mdl-textfield mdl-js-textfield">
                        <textarea class="mdl-textfield__input mdl-color-text--indigo-400" type="text" rows="3"
                                  id="notification_description"
                                  name="notification_description" <?php echo $readonly; ?>><?php echo $notification->getDescription(); ?></textarea>
                        <label class="mdl-textfield__label"
                               for="notification_description"><?php echo __('Descripción'); ?></label>
                    </div>
                </td>
            </tr>

            <?php if (!$isView && $userIsAdminApp): ?>
                <tr>
                    <td class="descField"><?php echo __('Usuario'); ?></td>
                    <td class="valField">
                        <div class="lowres-title"><?php echo __('Usuario'); ?></div>

                        <select id="notification_user" name="notification_user"
                                class="select-box select-box-deselect" <?php echo $disabled; ?>>
                            <option value=""><?php echo __('Seleccionar Usuario'); ?></option>
                            <?php /** @var \SP\Mvc\View\Components\SelectItem[] $users */
                            foreach ($users as $user): ?>
                                <option
                                        value="<?php echo $user->getId(); ?>" <?php echo $user->isSelected() ? 'selected' : ''; ?>><?php echo $user->getName(); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <td class="descField"><?php echo __('Opciones'); ?></td>
                    <td class="valField checkbox">
                        <label class="mdl-switch mdl-js-switch mdl-js-ripple-effect" for="notification_sticky"
                               title="<?php echo __('Notificación global'); ?>">
                            <input type="checkbox" id="notification_sticky"
                                   class="mdl-switch__input mdl-color-text--indigo-400"
                                   name="notification_sticky" <?php echo $notification->isSticky() ? 'checked' : ' '; ?> <?php echo $disabled; ?>/>
                            <span class="mdl-switch__label"><?php echo __('Global'); ?></span>
                        </label>

                        <label class="mdl-switch mdl-js-switch mdl-js-ripple-effect" for="notification_onlyadmin"
                               title="<?php echo __('Sólo para administradores de la aplicación'); ?>">
                            <input type="checkbox" id="notification_onlyadmin"
                                   class="mdl-switch__input mdl-color-text--indigo-400"
                                   name="notification_onlyadmin" <?php echo $notification->isOnlyAdmin() ? 'checked' : ' '; ?> <?php echo $disabled; ?>/>
                            <span class="mdl-switch__label"><?php echo __('Solo Admins'); ?></span>
                        </label>

                        <label class="mdl-switch mdl-js-switch mdl-js-ripple-effect" for="notification_checkout"
                               title="<?php echo __('Leída'); ?>">
                            <input type="checkbox" id="notification_checkout"
                                   class="mdl-switch__input mdl-color-text--indigo-400"
                                   name="notification_checkout" <?php echo $notification->isChecked() ? 'checked' : ' '; ?> <?php echo $disabled; ?>/>
                            <span class="mdl-switch__label"><?php echo __('Leída'); ?></span>
                        </label>
                    </td>
                </tr>
            <?php endif; ?>

            </tbody>
        </table>
    </form>
    <?php if (!$isView): ?>
        <div class="action-in-box">
            <button
                    class="mdl-button mdl-js-button mdl-button--fab mdl-button--mini-fab mdl-button--colored <?php echo $icons->getIconSave()->getClassButton(); ?>"
                    form="frmNotices" title="<?php echo $icons->getIconSave()->getTitle(); ?>">
                <i class="material-icons"><?php echo $icons->getIconSave()->getIcon(); ?></i>
            </button>
        </div>
    <?php endif; ?>
</div>