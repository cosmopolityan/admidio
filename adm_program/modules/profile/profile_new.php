<?php
/**
 ***********************************************************************************************
 * Create or edit a user profile
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * user_uuid  : Uuid of the user who should be edited
 * copy       : true - The user of the user_id will be copied and the base for this new user
 * accept_registration : If set to true, another forward url to role assignment will be set.
 * lastname   : (Optional) Lastname could be set and will than be preassigned for new users
 * firstname  : (Optional) First name could be set and will than be preassigned for new users
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

try {
    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getCopy = admFuncVariableIsValid($_GET, 'copy', 'bool');
    $getAcceptRegistration = admFuncVariableIsValid($_GET, 'accept_registration', 'bool');

    $registrationOrgId = $gCurrentOrgId;

    // read user data
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);
    $userId = $user->getValue('usr_id');

    // set headline of the script
    if ($getCopy) {
        // if we want to copy the user than set id = 0
        $user->setValue('usr_id', 0);
        $userId = 0;
        $getUserUuid = '';
        $headline = $gL10n->get('SYS_COPY_VAR', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME')));
    } elseif ($getUserUuid === '' && $gValidLogin) {
        $headline = $gL10n->get('SYS_CREATE_MEMBER');
    } elseif ($getUserUuid === '' && !$gValidLogin) {
        $headline = $gL10n->get('SYS_REGISTRATION');
    } elseif ($userId === $gCurrentUserId) {
        $headline = $gL10n->get('SYS_EDIT_MY_PROFILE');
    } else {
        $headline = $gL10n->get('SYS_EDIT_PROFILE');
    }

    // check if module may be called
    if (!$gValidLogin) {
        // Registration disabled, so also lock this mode
        if (!$gSettingsManager->getBool('registration_enable_module')) {
            throw new AdmException('SYS_MODULE_DISABLED');
        }
    } else {
        if ($getUserUuid === '') {
            // checks if the user has the necessary rights to create new users
            if (!$gCurrentUser->editUsers()) {
                throw new AdmException('SYS_NO_RIGHTS');
            }

            if (isset($_GET['lastname']) && isset($_GET['firstname'])) {
                // If last name and first name are passed, then these are already preassigned
                $user->setValue('LAST_NAME', stripslashes($_GET['lastname']));
                $user->setValue('FIRST_NAME', stripslashes($_GET['firstname']));
            }
        } else {
            // checks if the user has the necessary rights to change the corresponding profile
            if (!$gCurrentUser->hasRightEditProfile($user)) {
                throw new AdmException('SYS_NO_RIGHTS');
            }
        }
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    // Form was called via "Back" function, so fill all fields with the previous values
    if (isset($_SESSION['profile_request'])) {
        $user->noValueCheck();

        foreach ($gProfileFields->getProfileFields() as $field) {
            $fieldName = 'usf-' . (int)$field->getValue('usf_id');
            if (isset($_SESSION['profile_request'][$fieldName])) {
                $user->setProfileFieldsValue($field->getValue('usf_name_intern'), SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['profile_request'][$fieldName])), false);
            }
        }

        if (isset($_SESSION['profile_request']['usr_login_name'])) {
            $user->setArray(array('usr_login_name' => SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['profile_request']['usr_login_name']))));
        }
        if (isset($_SESSION['profile_request']['reg_org_id'])) {
            $registrationOrgId = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['profile_request']['reg_org_id']));
        }

        unset($_SESSION['profile_request']);
    }

    // create html page object
    $page = new HtmlPage('admidio-profile-edit', $headline);
    $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/zxcvbn/dist/zxcvbn.js');

    // create html form
    $form = new HtmlForm('edit_profile_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_save.php', array('user_uuid' => $getUserUuid, 'accept_registration' => $getAcceptRegistration)), $page);

    // *******************************************************************************
    // Loop over all categories and profile fields
    // *******************************************************************************

    $category = '';

    foreach ($gProfileFields->getProfileFields() as $field) {
        $showField = false;

        // at registration check if the field is enabled for registration
        if (!$gValidLogin && $field->getValue('usf_registration') == 1) {
            $showField = true;
        } // check if the current user has the right to edit this profile field of the selected user
        elseif ($gValidLogin && $gCurrentUser->allowedEditProfileField($user, $field->getValue('usf_name_intern'))) {
            $showField = true;
        }

        // Show the category header when changing the category
        if ($category !== $field->getValue('cat_name') && $showField) {
            if ($category !== '') {
                // div-Container admGroupBoxBody und admGroupBox schliessen
                $form->closeGroupBox();
            }
            $category = $field->getValue('cat_name');

            $form->addHtml('<a id="cat-' . (int)$field->getValue('cat_id') . '"></a>');
            $form->openGroupBox('gb_category_' . $field->getValue('cat_name_intern'), $field->getValue('cat_name'));

            if ($field->getValue('cat_name_intern') === 'BASIC_DATA') {
                // edit login name if it's a new record or administrator or approval of new registration
                if (($userId > 0 && $gCurrentUser->isAdministrator()) || $getUserUuid === '') {
                    $fieldProperty = HtmlForm::FIELD_DEFAULT;

                    if (!$gValidLogin || $getAcceptRegistration) {
                        $fieldProperty = HtmlForm::FIELD_REQUIRED;
                    }

                    $form->addInput(
                        'usr_login_name',
                        $gL10n->get('SYS_USERNAME'),
                        $user->getValue('usr_login_name'),
                        array('maxLength' => 254, 'property' => $fieldProperty, 'helpTextId' => 'SYS_USERNAME_DESCRIPTION', 'class' => 'form-control-small')
                    );

                    if (!$gValidLogin) {
                        // at registration add password and password confirm to form
                        $form->addInput(
                            'usr_password',
                            $gL10n->get('SYS_PASSWORD'),
                            '',
                            array(
                                'type' => 'password',
                                'property' => HtmlForm::FIELD_REQUIRED,
                                'minLength' => PASSWORD_MIN_LENGTH,
                                'passwordStrength' => true,
                                'helpTextId' => 'SYS_PASSWORD_DESCRIPTION',
                                'class' => 'form-control-small'
                            )
                        );
                        $form->addInput(
                            'password_confirm',
                            $gL10n->get('SYS_CONFIRM_PASSWORD'),
                            '',
                            array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH, 'class' => 'form-control-small')
                        );

                        // show selectbox with all organizations of database
                        if ($gSettingsManager->getBool('system_organization_select')) {
                            $sql = 'SELECT org_id, org_longname
                                  FROM ' . TBL_ORGANIZATIONS . '
                              ORDER BY org_longname, org_shortname';
                            $form->addSelectBoxFromSql(
                                'reg_org_id',
                                $gL10n->get('SYS_ORGANIZATION'),
                                $gDb,
                                $sql,
                                array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $registrationOrgId)
                            );
                        }
                    }
                    $form->addLine();
                }
            }
        }

        // only show fields that are enabled for registration or the user has permission to edit that field
        if ($showField) {
            // add profile fields to form
            $fieldProperty = HtmlForm::FIELD_DEFAULT;
            $helpId = '';
            $usfNameIntern = $field->getValue('usf_name_intern');

            if ($gProfileFields->getProperty($usfNameIntern, 'usf_disabled') == 1
                && !$gCurrentUser->hasRightEditProfile($user, false) && $getUserUuid !== '') {
                // disable field if this is configured in profile field configuration
                $fieldProperty = HtmlForm::FIELD_DISABLED;
            } elseif ($gProfileFields->hasRequiredInput($usfNameIntern, $userId, ((!$gValidLogin || $getAcceptRegistration) ? true : false))) {
                $fieldProperty = HtmlForm::FIELD_REQUIRED;
            }

            if (strlen($gProfileFields->getProperty($usfNameIntern, 'usf_description')) > 0) {
                $helpId = $gProfileFields->getProperty($gProfileFields->getProperty($usfNameIntern, 'usf_name_intern'), 'usf_description');
                if (Language::isTranslationStringId($helpId)) {
                    $helpId = array($helpId, array($gProfileFields->getProperty($usfNameIntern, 'usf_name')));
                }
            }

            // code for different field types
            if ($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'CHECKBOX') {
                $form->addCheckbox(
                    'usf-' . $gProfileFields->getProperty($usfNameIntern, 'usf_id'),
                    $gProfileFields->getProperty($usfNameIntern, 'usf_name'),
                    (bool)$user->getValue($usfNameIntern),
                    array(
                        'property' => $fieldProperty,
                        'helpTextId' => $helpId,
                        'icon' => 'bi-' . $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                    )
                );
            } elseif ($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'DROPDOWN' || $usfNameIntern === 'COUNTRY') {
                // set array with values and set default value
                if ($usfNameIntern === 'COUNTRY') {
                    $arrListValues = $gL10n->getCountries();
                    $defaultValue = null;

                    if ((int)$user->getValue('usr_id') === 0 && strlen($gSettingsManager->getString('default_country')) > 0) {
                        $defaultValue = $gSettingsManager->getString('default_country');
                    } elseif ($user->getValue('usr_id') > 0 && strlen($user->getValue($usfNameIntern)) > 0) {
                        $defaultValue = $user->getValue($usfNameIntern, 'database');
                    }
                } else {
                    $arrListValues = $gProfileFields->getProperty($usfNameIntern, 'usf_value_list');
                    $defaultValue = $user->getValue($usfNameIntern, 'database');
                }

                $form->addSelectBox(
                    'usf-' . $gProfileFields->getProperty($usfNameIntern, 'usf_id'),
                    $gProfileFields->getProperty($usfNameIntern, 'usf_name'),
                    $arrListValues,
                    array(
                        'property' => $fieldProperty,
                        'defaultValue' => $defaultValue,
                        'helpTextId' => $helpId,
                        'icon' => 'bi-' . $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                    )
                );
            } elseif ($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'RADIO_BUTTON') {
                $showDummyRadioButton = false;

                if (!$gProfileFields->hasRequiredInput($usfNameIntern, $userId, ((!$gValidLogin || $getAcceptRegistration) ? true : false))) {
                    $showDummyRadioButton = true;
                }

                $form->addRadioButton(
                    'usf-' . $gProfileFields->getProperty($usfNameIntern, 'usf_id'),
                    $gProfileFields->getProperty($usfNameIntern, 'usf_name'),
                    $gProfileFields->getProperty($usfNameIntern, 'usf_value_list', 'html'),
                    array(
                        'property' => $fieldProperty,
                        'defaultValue' => (int)$user->getValue($usfNameIntern, 'database'),
                        'showNoValueButton' => $showDummyRadioButton,
                        'helpTextId' => $helpId,
                        'icon' => 'bi-' . $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                    )
                );
            } elseif ($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'TEXT_BIG') {
                $form->addMultilineTextInput(
                    'usf-' . $gProfileFields->getProperty($usfNameIntern, 'usf_id'),
                    $gProfileFields->getProperty($usfNameIntern, 'usf_name'),
                    $user->getValue($usfNameIntern),
                    3,
                    array(
                        'maxLength' => 4000,
                        'property' => $fieldProperty,
                        'helpTextId' => $helpId,
                        'icon' => 'bi-' . $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                    )
                );
            } else {
                $fieldType = 'text';

                if ($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'DATE') {
                    $fieldType = 'date';
                    $maxlength = '10';
                } elseif ($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'EMAIL') {
                    // email could not be longer than 254 characters
                    $fieldType = 'email';
                    $maxlength = '254';
                } elseif ($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'URL') {
                    // maximal browser compatible url length will be 2000 characters
                    $maxlength = '2000';
                } elseif ($gProfileFields->getProperty($usfNameIntern, 'usf_type') === 'NUMBER') {
                    $fieldType = 'number';
                    $maxlength = array(0, 9999999999, 1);
                } elseif ($gProfileFields->getProperty($usfNameIntern, 'cat_name_intern') === 'SOCIAL_NETWORKS') {
                    $maxlength = '255';
                } else {
                    $maxlength = '100';
                }

                $form->addInput(
                    'usf-' . $gProfileFields->getProperty($usfNameIntern, 'usf_id'),
                    $gProfileFields->getProperty($usfNameIntern, 'usf_name'),
                    $user->getValue($usfNameIntern),
                    array(
                        'type' => $fieldType,
                        'maxLength' => $maxlength,
                        'property' => $fieldProperty,
                        'helpTextId' => $helpId,
                        'icon' => 'bi-' . $gProfileFields->getProperty($usfNameIntern, 'usf_icon', 'database')
                    )
                );
            }
        }
    }

    // div-Container admGroupBoxBody und admGroupBox schliessen
    $form->closeGroupBox();

    // if captchas are enabled then visitors of the website must resolve this
    if (!$gValidLogin && $gSettingsManager->getBool('registration_enable_captcha')) {
        $form->openGroupBox('gb_confirmation_of_input', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
        $form->addCaptcha('captcha_code');
        $form->closeGroupBox();
    }

    if (!$gValidLogin) {
        // Registration
        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SEND'), array('icon' => 'bi-envelope-fill'));
    } else {
        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));
    }

    if ($getUserUuid !== '') {
        // show information about user who creates the recordset and changed it
        $form->addHtml(admFuncShowCreateChangeInfoById(
            (int)$user->getValue('usr_usr_id_create'),
            $user->getValue('usr_timestamp_create'),
            (int)$user->getValue('usr_usr_id_change'),
            $user->getValue('usr_timestamp_change')
        ));
    }

    $page->addHtml($form->show());
    $page->show();
} catch (AdmException|Exception $e) {
    $gMessage->show($e->getMessage());
}
