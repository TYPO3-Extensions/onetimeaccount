<?php
/***************************************************************
* Copyright notice
*
* (c) 2007-2014 Oliver Klee (typo3-coding@oliverklee.de)
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * This class checks this extension's configuration for basic sanity.
 *
 * @package TYPO3
 * @subpackage tx_onetimeaccount
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class tx_onetimeaccount_configcheck extends tx_oelib_configcheck {
	/**
	 * Checks the configuration for tx_onetimeaccount_pi1.
	 *
	 * @return void
	 */
	protected function check_tx_onetimeaccount_pi1() {
		$this->checkStaticIncluded();
		$this->checkTemplateFile(TRUE);
		$this->checkCssFileFromConstants();
		$this->checkSalutationMode();

		$this->checkFeUserFieldsToDisplay();
		$this->checkRequiredFeUserFields();
		$this->checkSystemFolderForNewFeUserRecords();
		$this->checkGroupForNewFeUsers();
		$this->checkUserNameSource();
	}

	/**
	 * Checks the setting of the configuration value feUserFieldsToDisplay.
	 *
	 * @return void
	 */
	private function checkFeUserFieldsToDisplay() {
		$this->checkIfMultiInSetNotEmpty(
			'feUserFieldsToDisplay',
			TRUE,
			's_general',
			'This value specifies which form fields will be displayed. ' .
				'Incorrect values will cause those fields to not get displayed.',
			$this->getAvailableFields()
		);
	}

	/**
	 * Checks the setting of the configuration value requiredFeUserFields.
	 *
	 * @return void
	 */
	private function checkRequiredFeUserFields() {
		$this->checkIfMultiInSetOrEmpty(
			'requiredFeUserFields',
			TRUE,
			's_general',
			'This value specifies which form fields are required to be filled in. ' .
				'Incorrect values will cause those fields to not get ' .
				'validated correctly.',
			$this->getAvailableFields(
				array(
					'gender', 'usergroup', 'module_sys_dmail_newsletter',
					'module_sys_dmail_html',
				)
			)
		);

		$this->checkIfMultiInSetOrEmpty(
			'requiredFeUserFields',
			TRUE,
			's_general',
			'This value specifies which form fields are required to be filled ' .
				'in. Incorrect values will cause the user not to be able to ' .
				'send the registration form.',
			t3lib_div::trimExplode(
				',',
				$this->objectToCheck->getConfValueString(
					'feUserFieldsToDisplay', 's_general'
				),
				TRUE
			)
		);
	}

	/**
	 * Checks the setting of the configuration value systemFolderForNewFeUserRecords.
	 *
	 * @return void
	 */
	private function checkSystemFolderForNewFeUserRecords() {
		$this->checkIfSingleSysFolderNotEmpty(
			'systemFolderForNewFeUserRecords',
			TRUE,
			's_general',
			'This value specifies the system folder in which new FE user' .
				'records will be stored.' .
				'If this value is not set correctly, the records will be ' .
				'stored in the wrong page.'
		);
	}

	/**
	 * Checks the setting of the configuration value groupForNewFeUsers.
	 *
	 * @return void
	 */
	private function checkGroupForNewFeUsers() {
		$this->checkIfPidListNotEmpty(
			'groupForNewFeUsers',
			TRUE,
			's_general',
			'This value specifies the FE user groups to which new FE user records ' .
				'will be assigned. If this value is not set correctly, the ' .
				'users will not be placed in one of those groups.'
		);
		if ($this->getRawMessage() != '') {
			return;
		}

		$valueToCheck = $this->objectToCheck->getConfValueString(
			'groupForNewFeUsers',
			's_general'
		);
		$groupCounter = tx_oelib_db::selectSingle(
			'COUNT(*) AS number',
			'fe_groups',
			'uid IN (' . $valueToCheck . ')' .
				tx_oelib_db::enableFields('fe_groups')
		);
		$elementsInValueToCheck = count(
			$this->objectToCheck->getUncheckedUidsOfAllowedUserGroups()
		);
		if ($groupCounter['number'] != $elementsInValueToCheck) {
			$this->setErrorMessageAndRequestCorrection(
				'groupForNewFeUsers',
				TRUE,
				'The TS setup variable <strong>' .
					$this->getTSSetupPath() . 'groupForNewFeUsers</strong> ' .
					'contains the value ' . $valueToCheck . ' which isn\'t valid. ' .
					'This value specifies the FE user groups to which new ' .
					'FE user records will be assigned. ' .
					'If this value is not set correctly, the users will not ' .
					'be placed in one of those groups.'
			);
		}
	}

	/**
	 * Checks the setting of the configuration value userNameSource.
	 *
	 * @return void
	 */
	private function checkUserNameSource() {
		$this->checkIfMultiInSetNotEmpty(
			'userNameSource',
			TRUE,
			's_general',
			'This value specifies how to generate the user name.' .
				'An incorrect value might cause the generated user names look ' .
				'different than intended.',
			array('email', 'name')
		);
	}


	/**
	 * Returns an array of field names that are provided in the form AND that
	 * actually exist in the DB (some fields need to be provided by
	 * sr_feuser_register).
	 *
	 * @param array $excludeFields
	 *        fields which should be excluded from the list of available fields,
	 *        may be empty
	 *
	 * @return array list of available field names, will not be empty
	 */
	private function getAvailableFields(array $excludeFields = array()) {
		$providedFields = array(
			'company',
			'gender',
			'title',
			'name',
			'first_name',
			'last_name',
			'address',
			'zip',
			'city',
			'zone',
			'country',
			'static_info_country',
			'email',
			'www',
			'telephone',
			'fax',
			'date_of_birth',
			'status',
			'module_sys_dmail_newsletter',
			'module_sys_dmail_html',
			'usergroup',
			'comments',
		);
		$formFields = array_diff($providedFields, $excludeFields);
		$fieldsFromFeUsers = $this->getDbColumnNames('fe_users');

		// Makes sure that only fields are allowed that are actually available.
		// (Some fields don't come with the vanilla TYPO3 installation and are
		// provided by the sr_feusers_register extension.)
		return array_intersect($formFields, $fieldsFromFeUsers);
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/onetimeaccount/class.tx_onetimeaccount_configcheck.php']) {
	require_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/onetimeaccount/class.tx_onetimeaccount_configcheck.php']);
}