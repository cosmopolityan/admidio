<?php
/**
 ***********************************************************************************************
 * Show and manage all members of the organization
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * members - true : (Default) Show only active members of the current organization
 *           false  : Show active and inactive members of all organizations in database
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/../../system/login_valid.php');

unset($_SESSION['import_request']);

// Initialize and check the parameters
$getMembers = admFuncVariableIsValid($_GET, 'members', 'bool', array('defaultValue' => true));

// if only active members should be shown then set parameter
if (!$gSettingsManager->getBool('members_show_all_users')) {
    $getMembers = true;
}

// set headline of the script
$headline = $gL10n->get('SYS_CONTACTS');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-address-card');

$membersListConfig = new ListConfiguration($gDb, $gSettingsManager->getInt('members_list_configuration'));
$_SESSION['members_list_config'] = $membersListConfig;

// Link mit dem alle Benutzer oder nur Mitglieder angezeigt werden setzen
$flagShowMembers = !$getMembers;

// create html page object
$page = new HtmlPage('admidio-members', $headline);

if ($gCurrentUser->editUsers()) {
    $page->addJavascript('
        $("#menu_item_members_create_user").attr("href", "javascript:void(0);");
        $("#menu_item_members_create_user").attr("data-href", "'.ADMIDIO_URL.FOLDER_MODULES.'/contacts/contacts_new.php");
        $("#menu_item_members_create_user").attr("class", "nav-link btn btn-secondary openPopup");

        // change mode of users that should be shown
        $("#mem_show_all").click(function() {
            window.location.replace("'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/contacts/contacts.php', array('members' => $flagShowMembers)).'");
        });', true);

    $page->addPageFunctionsMenuItem(
        'menu_item_members_create_user',
        $gL10n->get('SYS_CREATE_CONTACT'),
        ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_new.php',
        'fa-plus-circle'
    );

    if ($gSettingsManager->getBool('profile_log_edit_fields')) {
        // show link to view profile field change history
        $page->addPageFunctionsMenuItem(
            'menu_item_members_change_history',
            $gL10n->get('SYS_CHANGE_HISTORY'),
            ADMIDIO_URL.FOLDER_MODULES.'/contacts/profile_field_history.php',
            'fa-history'
        );
    }

    // show checkbox to select all users or only active members
    if ($gSettingsManager->getBool('members_show_all_users')) {
        // create filter menu with elements for category
        $filterNavbar = new HtmlNavbar('navbar_filter', null, null, 'filter');
        $form = new HtmlForm('navbar_filter_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
        $form->addCheckbox('mem_show_all', $gL10n->get('SYS_SHOW_ALL'), $flagShowMembers, array('helpTextIdLabel' => 'SYS_SHOW_ALL_DESC'));
        $filterNavbar->addForm($form->show());
        $page->addHtml($filterNavbar->show());
    }

    // show link to import users
    $page->addPageFunctionsMenuItem(
        'menu_item_members_import_users',
        $gL10n->get('SYS_IMPORT_CONTACTS'),
        ADMIDIO_URL.FOLDER_MODULES.'/contacts/import.php',
        'fa-upload'
    );
}

if ($gCurrentUser->isAdministrator()) {
    // show link to maintain profile fields
    $page->addPageFunctionsMenuItem(
        'menu_item_members_profile_fields',
        $gL10n->get('SYS_EDIT_PROFILE_FIELDS'),
        ADMIDIO_URL.FOLDER_MODULES.'/profile-fields/profile_fields.php',
        'fa-th-list'
    );
}

$orgName = $gCurrentOrganization->getValue('org_longname');

// Create table object
$membersTable = new HtmlTable('tbl_members', $page, true, true, 'table table-condensed');

// create array with all column heading values
$columnHeading = $membersListConfig->getColumnNames();
array_unshift(
    $columnHeading,
    $gL10n->get('SYS_ABR_NO'),
    '<i class="fas fa-user" data-toggle="tooltip" title="' . $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($orgName)) . '"></i>'
);
$columnHeading[] = '&nbsp;';

$columnAlignment = $membersListConfig->getColumnAlignments();
array_unshift($columnAlignment, 'left', 'left');
$columnAlignment[] = 'right';

$membersTable->setServerSideProcessing(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/contacts/contacts_data.php', array('members' => $getMembers)));
$membersTable->setColumnAlignByArray($columnAlignment);
$membersTable->disableDatatablesColumnsSort(array(1, count($columnHeading))); // disable sort in last column
$membersTable->setDatatablesColumnsNotHideResponsive(array(count($columnHeading)));
$membersTable->addRowHeadingByArray($columnHeading);
$membersTable->setDatatablesRowsPerPage($gSettingsManager->getInt('members_users_per_page'));
$membersTable->setMessageIfNoRowsFound('SYS_NO_ENTRIES');

$page->addHtml($membersTable->show());

// show html of complete page
$page->show();