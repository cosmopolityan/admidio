<?php
/**
 ***********************************************************************************************
 * Create and edit categories
 *
 * @copyright The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * menu_uuid: UUID of the menu entry that should be edited
 *
 ****************************************************************************/
use Admidio\UserInterface\Form;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getMenuUuid = admFuncVariableIsValid($_GET, 'menu_uuid', 'uuid');

    // check rights
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    /**
     * @param array<int,string> $menuList
     * @param int $level
     * @param int $menId
     * @param int|null $parentId
     */
    function subMenu(array &$menuList, int $level, int $menId, int $parentId = null)
    {
        global $gDb;

        $sqlConditionParentId = '';
        $queryParams = array($menId);

        // Erfassen des auszugebenden Menu
        if ($parentId > 0) {
            $sqlConditionParentId .= ' AND men_men_id_parent = ? -- $parentId';
            $queryParams[] = $parentId;
        } else {
            $sqlConditionParentId .= ' AND men_men_id_parent IS NULL';
        }

        $sql = 'SELECT *
              FROM ' . TBL_MENU . '
             WHERE men_node = true
               AND men_id  <> ? -- $menu->getValue(\'men_id\')
                   ' . $sqlConditionParentId;
        $childStatement = $gDb->queryPrepared($sql, $queryParams);

        $parentMenu = new TableMenu($gDb);
        $einschub = str_repeat('&nbsp;', $level * 3) . '&#151;&nbsp;';

        while ($menuEntry = $childStatement->fetch()) {
            $parentMenu->clear();
            $parentMenu->setArray($menuEntry);

            // add entry to array of all menus
            $menuList[(int)$parentMenu->getValue('men_id')] = $einschub . $parentMenu->getValue('men_name');

            subMenu($menuList, ++$level, $menId, (int)$parentMenu->getValue('men_id'));
        }
    }

    // create menu object
    $menu = new TableMenu($gDb);

    // system categories should not be renamed
    $roleViewSet[] = 0;

    if ($getMenuUuid !== '') {
        $headline = $gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_MENU')));

        $menu->readDataByUuid($getMenuUuid);

        // Read current roles rights of the menu
        $display = new RolesRights($gDb, 'menu_view', $menu->getValue('men_id'));
        $roleViewSet = $display->getRolesIds();
    } else {
        $headline = $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_MENU')));
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage('admidio-menu-edit', $headline);

    // alle aus der DB aus lesen
    $sqlRoles = 'SELECT rol_id, rol_name, org_shortname, cat_name
               FROM ' . TBL_ROLES . '
         INNER JOIN ' . TBL_CATEGORIES . '
                 ON cat_id = rol_cat_id
         INNER JOIN ' . TBL_ORGANIZATIONS . '
                 ON org_id = cat_org_id
              WHERE rol_valid  = true
                AND rol_system = false
                AND cat_name_intern <> \'EVENTS\'
           ORDER BY cat_name, rol_name';
    $rolesViewStatement = $gDb->queryPrepared($sqlRoles);

    $parentRoleViewSet = array();
    while ($rowViewRoles = $rolesViewStatement->fetch()) {
        // Each role is now added to this array
        $parentRoleViewSet[] = array(
            $rowViewRoles['rol_id'],
            $rowViewRoles['rol_name'] . ' (' . $rowViewRoles['org_shortname'] . ')',
            $rowViewRoles['cat_name']
        );
    }

    // show form
    $form = new Form(
        'menu_edit_form',
        'modules/menu.edit.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_function.php', array('uuid' => $getMenuUuid, 'mode' => 'edit')),
        $page
    );

    $fieldRequired = Form::FIELD_REQUIRED;
    $fieldDefault = Form::FIELD_DEFAULT;

    if ($menu->getValue('men_standard')) {
        $fieldRequired = Form::FIELD_DISABLED;
        $fieldDefault = Form::FIELD_DISABLED;
    }

    $menuList = array();
    subMenu($menuList, 1, (int)$menu->getValue('men_id'));

    $form->addInput(
        'men_name',
        $gL10n->get('SYS_NAME'),
        htmlentities($menu->getValue('men_name', 'database'), ENT_QUOTES),
        array('maxLength' => 100, 'property' => Form::FIELD_REQUIRED, 'helpTextId' => 'SYS_MENU_NAME_DESC')
    );

    if ($getMenuUuid !== '') {
        $form->addInput(
            'men_name_intern',
            $gL10n->get('SYS_INTERNAL_NAME'),
            $menu->getValue('men_name_intern'),
            array('maxLength' => 100, 'property' => Form::FIELD_DISABLED, 'helpTextId' => 'SYS_INTERNAL_NAME_DESC')
        );
    }

    $form->addMultilineTextInput(
        'men_description',
        $gL10n->get('SYS_DESCRIPTION'),
        $menu->getValue('men_description'),
        2,
        array('maxLength' => 4000)
    );
    $form->addSelectBox(
        'men_men_id_parent',
        $gL10n->get('SYS_MENU_LEVEL'),
        $menuList,
        array(
            'property' => Form::FIELD_REQUIRED,
            'defaultValue' => (int)$menu->getValue('men_men_id_parent')
        )
    );

    if (!$menu->getValue('men_standard')) {
        $sql = 'SELECT com_id, com_name
          FROM ' . TBL_COMPONENTS . '
      ORDER BY com_name';
        $form->addSelectBoxFromSql(
            'men_com_id',
            $gL10n->get('SYS_MODULE_RIGHTS'),
            $gDb,
            $sql,
            array(
                'property' => $fieldDefault,
                'defaultValue' => (int)$menu->getValue('men_com_id'),
                'helpTextId' => 'SYS_MENU_MODULE_RIGHTS_DESC'
            )
        );
        $form->addSelectBox(
            'menu_view',
            $gL10n->get('SYS_VISIBLE_FOR'),
            $parentRoleViewSet,
            array(
                'property' => $fieldDefault,
                'defaultValue' => $roleViewSet,
                'multiselect' => true,
                'helpTextId' => 'SYS_MENU_RESTRICT_VISIBILITY'
            )
        );
    }

    if ((bool)$menu->getValue('men_node') === false) {
        $form->addInput(
            'men_url',
            $gL10n->get('SYS_URL'),
            $menu->getValue('men_url'),
            array('maxLength' => 2000, 'property' => $fieldRequired)
        );
    }

    $form->addInput(
        'men_icon',
        $gL10n->get('SYS_ICON'),
        $menu->getValue('men_icon'),
        array(
            'maxLength' => 100,
            'helpTextId' => $gL10n->get('SYS_ICON_FONT_DESC', array('<a href="https://icons.getbootstrap.com/" target="_blank">', '</a>')),
            'class' => 'form-control-small'
        )
    );
    $form->addSubmitButton(
        'btn_save',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
    );

    $page->assignSmartyVariable('nameUserCreated', $menu->getNameOfCreatingUser());
    $page->assignSmartyVariable('timestampUserCreated', $menu->getValue('men_timestamp_create'));
    $page->assignSmartyVariable('nameLastUserEdited', $menu->getNameOfLastEditingUser());
    $page->assignSmartyVariable('timestampLastUserEdited', $menu->getValue('men_timestamp_change'));
    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);
    $page->show();
} catch (AdmException|Exception $e) {
    $gMessage->show($e->getMessage());
}
