<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2009 Oliver Klee <typo3-coding@oliverklee.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(PATH_formidableapi);

require_once(t3lib_extMgm::extPath('oelib') . 'class.tx_oelib_Autoloader.php');

require_once(t3lib_extMgm::extPath('static_info_tables').'pi1/class.tx_staticinfotables_pi1.php');

/**
 * Plugin 'One-time FE account creator' for the 'onetimeaccount' extension.
 *
 * @package TYPO3
 * @subpackage tx_onetimeaccount
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_onetimeaccount_pi1 extends tx_oelib_templatehelper {
	/**
	 * @var string same as class name
	 */
	public $prefixId = 'tx_onetimeaccount_pi1';
	/**
	 * @var string path to this script relative to the extension dir
	 */
	public $scriptRelPath = 'pi1/class.tx_onetimeaccount_pi1.php';
	/**
	 * @var string the extension key
	 */
	public $extKey = 'onetimeaccount';

	/**
	 * @var tx_ameosformidable FORMidable object that creates the edit form
	 */
	protected $form = null;

	/**
	 * @var array names of the form fields to show
	 */
	private $formFieldsToShow = array();

	/**
	 * @var array names of the form fields that are required to be filled in
	 */
	private $requiredFormFields = array();

	/**
	 * @var tx_staticinfotables_pi1
	 */
	private $staticInfo = null;

	/**
	 * @var array the fields available in the form
	 */
	private static $availableFormFields = array(
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
		'module_sys_dmail_html',
		'usergroup',
		'comments',
	);

	/**
	 * Frees as much memory that has been used by this object as possible.
	 */
	public function __destruct() {
		unset($this->form, $this->staticInfo);
		parent::__destruct();
	}

	/**
	 * Creates the plugin output.
	 *
	 * @param string (ignored)
	 * @param array the plug-in configuration
	 *
	 * @return string HTML output of the plug-in
	 */
	public function main($content, array $configuration) {
		$this->init($configuration);
		$this->pi_initPIflexForm();

		// disables caching
		$this->pi_USER_INT_obj = 1;

		$this->initializeFormFields();
		$this->initializeForm();

		$result = $this->renderForm();
		$result .= $this->checkConfiguration();

		return $this->pi_wrapInBaseClass($result);
	}

	/**
	 * Creates and initializes the FORMidable object.
	 */
	protected function initializeForm() {
		$this->form = t3lib_div::makeInstance('tx_ameosformidable');

		$this->form->initFromTs(
			$this,
			$this->conf['form.'],
			false
		);
	}

	/**
	 * Initializes which form fields should be shown and which are required.
	 */
	private function initializeFormFields() {
		$this->setFormFieldsToShow();
		$this->setRequiredFormFields();
		$this->setRequiredFieldLabels();
	}

	/**
	 * Reads the list of form fields to show from the configuration and stores
	 * it in $this->formFieldsToShow.
	 */
	private function setFormFieldsToShow() {
		$this->formFieldsToShow = t3lib_div::trimExplode(
			',',
			$this->getConfValueString('feUserFieldsToDisplay', 's_general')
		);
	}

	/**
	 * Reads the list of required form fields from the configuration and stores
	 * it in $this->requiredFormFields.
	 */
	private function setRequiredFormFields() {
		$this->requiredFormFields = t3lib_div::trimExplode(
			',',
			$this->getConfValueString('requiredFeUserFields', 's_general')
		);
	}

	/**
	 * Gets the path to the HTML template as set in the TS setup or flexforms.
	 * The returned path will always be an absolute path in the file system;
	 * EXT: references will automatically get resolved.
	 *
	 * @return string the path to the HTML template as an absolute path in
	 *                the file system, will not be empty in a correct
	 *                configuration
	 */
	public function getTemplatePath() {
		return t3lib_div::getFileAbsFileName(
			$this->getConfValueString('templateFile', 's_template_special', true)
		);
	}

	/**
	 * Creates the HTML output of the form.
	 *
	 * @return string HTML of the form
	 */
	private function renderForm() {
		$rawForm = $this->form->_render();

		$this->processTemplate($rawForm);
		$this->setLabels();
		$this->hideUnusedFormFields();

		return $this->getSubpart();
	}

	/**
	 * Hides form fields that are disabled via TS setup from the templating
	 * process.
	 */
	private function hideUnusedFormFields() {
		$formFieldsToHide = array_diff(
			self::$availableFormFields,
			$this->formFieldsToShow
		);

		$this->setUsergroupSubpartVisibility($formFieldsToHide);
		$this->setZipSubpartVisibility($formFieldsToHide);
		$this->setAllNamesSubpartVisibility($formFieldsToHide);

		$this->hideSubpartsArray($formFieldsToHide, 'wrapper');
	}

	/**
	 * Checks whether a form field should be displayed (and evaluated) at all.
	 * This is specified via TS setup (or flexforms) using the
	 * "feUserFieldsToDisplay" variable.
	 * Radiobuttons to choose user groups are only shown if there is more than
	 * one value to display.
	 *
	 * @param array the contents of the "params" child of the userobj
	 *              node as key/value pairs (used for retrieving the current
	 *              form field name)
	 *
	 * @return boolean true if the current form field should be displayed,
	 *                 false otherwise
	 */
	public function isFormFieldEnabled(array $parameters) {
		$key = $parameters['elementName'];
		$result = in_array($key, $this->formFieldsToShow);
		if ($key == 'usergroup') {
			$result = $result && $this->hasAtLeastTwoUserGroups();

		}
		return $result;
	}

	/**
	 * Provides a localized list of localized country names from static_tables.
	 *
	 * If $parameters['alpha3'] is set, the alpha3 codes will be used as form
	 * values. Otherwise, the localized country names will be used as values.
	 *
	 * @param mixed (unused)
	 * @param array contents of the "params" XML child of the userobj
	 *              node (needs to contain an element with the key "key")
	 *
	 * @return array a list of localized country names from static_tables
	 *               as an array with the keys "caption" (for the
	 *               localized title) and "value" (either the country's
	 *               alpha3 code or the localized name)
	 */
	public function populateListCountries($unused, array $parameters) {
		$this->initStaticInfo();
		$allCountries = $this->staticInfo->initCountries(
			'ALL', $this->staticInfo->getCurrentLanguage(), true
		);

		$result = array();
		// Add an empty item at the top so we won't have Afghanistan (the first
		// entry) pre-selected for empty values.
		$result[] = array(
			'caption' => '&nbsp;',
			'value' => ''
		);

		foreach ($allCountries as $alpha3Code => $currentCountryName) {
			$result[] = array(
				'caption' => $currentCountryName,
				'value' => (isset($parameters['alpha3']))
					? $alpha3Code : $currentCountryName
			);
		}

		return $result;
	}

	/**
	 * Returns the default country as alpha3 code or localized string.
	 *
	 * If $parameters['alpha3'] is set, the alpha3 code will be used as return
	 * value. Otherwise, the localized country name will be used as return value.
	 *
	 * @param mixed (unused)
	 * @param array contents of the "params" XML child of the userobj
	 *              node (needs to contain an element with the key "key")
	 *
	 * @return string the default country (either the country's alpha3
	 *                code or the localized name)
	 */
	public function getDefaultCountry($unused, array $parameters) {
		$this->initStaticInfo();
		$typoScriptPluginSetup = $GLOBALS['TSFE']->tmpl->setup['plugin.'];
		$staticInfoSetup = $typoScriptPluginSetup['tx_staticinfotables_pi1.'];
		$defaultCountryCode = $staticInfoSetup['countryCode'];

		if ($parameters['alpha3']) {
			$result = $defaultCountryCode;
		} else {
			$result = tx_staticinfotables_div::getTitleFromIsoCode(
				'static_countries', $defaultCountryCode,
				$this->staticInfo->getCurrentLanguage(), true
			);
		}

		return $result;
	}

	/**
	 * Creates and initializes $this->staticInfo (if that hasn't been done yet).
	 */
	private function initStaticInfo() {
		if (!$this->staticInfo) {
			$this->staticInfo
				= t3lib_div::makeInstance('tx_staticinfotables_pi1');
			$this->staticInfo->init();
		}
	}

	/**
	 * Gets the PID of the system folder in which new FE user records will be
	 * stored.
	 *
	 * @return integer the PID of the page where FE-created events will be stored
	 */
	public function getPidForNewUserRecords() {
		return $this->getConfValueInteger(
			'systemFolderForNewFeUserRecords',
			's_general'
		);
	}

	/**
	 * Returns the URL that has been set via the GET parameter "redirect_url".
	 *
	 * If this parameter has not been set or is empty, an empty string will be
	 * returned.
	 *
	 * In addition, the entered FE user will be automatically logged in, and
	 * the key "onetimeaccount" with the value "1" will be written to the FE
	 * user session.
	 *
	 * @return string the URL set as GET parameter or an empty string if there
	 *                is no such GET parameter
	 */
	public function getRedirectUrlAndLoginUser() {
		$result = (string) t3lib_div::_GP('redirect_url');

		if ($result == '') {
			// Redirects to the current page if no redirect URL is provided.
			$result = t3lib_div::getIndpEnv('TYPO3_REQUEST_URL');
		}

		$_POST['user'] = $this->getFormData('username');

		if ($this->usesMd5Passwords()) {
			$challenge = $this->createChallenge();
			$_POST['challenge'] = $challenge;
			$_POST['pass'] = $this->createMd5Password(
				array(
					'username' => $this->getFormData('username'),
					'password' => $this->getFormData('password'),
					'challenge' => $challenge,
				)
			);
		} else {
			$_POST['pass'] = $this->getFormData('password');
		}
		$_POST['logintype'] = 'login';
		$_POST['pid'] = $this->getPidForNewUserRecords();

		$GLOBALS['TSFE']->initFEuser();

		tx_oelib_Session::getInstance(tx_oelib_Session::TYPE_USER)
			->setAsBoolean($this->extKey, true);

		return $result;
	}

	/**
	 * Gets the entered form data for the field $key.
	 *
	 * @param string key of the field to retrieve, must not be empty and
	 *               must refer to an existing form field
	 *
	 * @return mixed data for the requested form element
	 */
	protected function getFormData($key) {
		return $this->form->oDataHandler->_getThisFormData($key);
	}

	/**
	 * Creates a unique FE user name. It consists of the entered e-mail address.
	 * If a user with that user name already exists, a number will be appended.
	 *
	 * @return string a user name, will not be empty
	 */
	public function getUserName() {
		$enteredEmail = $this->getFormData('email') ;
		$nonEmptyUsername = ($enteredEmail != '') ? $enteredEmail : 'user';
		$numberToAppend = 1;
		$result = $nonEmptyUsername;

		// Modify the user name until we have a unique user name.
		while ($GLOBALS['TSFE']->fe_user->getRawUserByName($result)) {
			$result = $nonEmptyUsername . '-' . $numberToAppend;
			$numberToAppend++;
		}

		return $result;
	}

	/**
	 * Creates a random 8-character password, consisting of digits, uppercase
	 * and lowercase characters and some special chars.
	 *
	 * @return string a random 8 character password
	 */
	public function getPassword() {
		$result = '';

		$availableCharacters
			= 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
				.'0123456789!§$%&/()=?*+#,;.:-_<>';
		$indexOfLastCharacter = strlen($availableCharacters) - 1;

		for ($i = 0; $i < 8; $i++) {
			$result .= substr(
				$availableCharacters,
				mt_rand(0, $indexOfLastCharacter),
				1
			);
		}

		return $result;
	}

	/**
	 * Makes some preprocessing which is necessary to insert the user into the
	 * DB.
	 *
	 * @param array entered form data, may be empty
	 *
	 * @return array processed form data, will not be empty
	 */
	public function preprocessFormData(array $formData) {
		$result = $formData;
		$result = $this->setCurrentUserGroup($formData);
		if ($this->usesMd5Passwords()) {
			$result['password'] = md5($formData['password']);
		}

		return $result;
	}

	/**
	 * Gets the form data and adds the user group(s) from the BE configuration
	 * if the form field to choose a user group in the FE is disabled.
	 *
	 * @param array entered form data, may be empty
	 *
	 * @return array returns form data: If choosing user groups in in FE
	 *               is disabled, the user group(s) of groupForNewFeUsers
	 *               are added to the form data, otherwise it is returned
	 *               without modifications.
	 */
	public function setCurrentUserGroup(array $formData) {
		$result = $formData;

		if (!$this->isFormFieldEnabled(array('elementname' => 'usergroup'))) {
			$result['usergroup'] = $this->getConfValueString(
				'groupForNewFeUsers',
				's_general');
		}

		return $result;
	}

	/**
	 * Returns the UID of the first user group shown in the FE. If there are no
	 * user groups, the result will be zero.
	 *
	 * @return integer UID of the first user group
	 */
	 public function getUidOfFirstUserGroup() {
	 	$userGroups = $this->getUncheckedUidsOfAllowedUserGroups();

	 	return intval($userGroups[0]);
	 }

	/**
	 * Returns an array of user groups choosable in the FE, will not be empty if
	 * configured correctly.
	 *
	 * @return array lists user groups choosable in the FE, will not be
	 *               empty if configured correctly
	 */
	public function listUserGroups() {
		$result = array();
		$listOfUserGroupUids = $this->getConfValueString(
			'groupForNewFeUsers',
			's_general'
		);

		if (preg_match('/^([0-9]+(,( *)[0-9]+)*)?$/', $listOfUserGroupUids)
			&& ($listOfUserGroupUids != '')
		) {
			$allUserGroups = array();
			$userGroupUids = $this->getUncheckedUidsOfAllowedUserGroups();
			$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'uid, title',
				'fe_groups',
				'uid IN(' . $listOfUserGroupUids . ')' .
					tx_oelib_db::enableFields('fe_groups')
			);
			if (!$dbResult) {
				throw new Exception(DATABASE_QUERY_ERROR);
			}

			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult)) {
				$allUserGroups[$row['uid']] = $row['title'];
			}
			foreach ($userGroupUids as $currentUid) {
				$result[] = array(
					'caption' => $allUserGroups[$currentUid].'<br />',
					'value' => $currentUid
				);
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($dbResult);
		};

		return $result;
	}

	/**
	 * Gets an array of the value for groupForNewFeUsers from flexforms or TS setup.
	 * The array will contain the UIDs of FE user groups, at least an empty string.
	 *
	 * @return array array of the flexforms or TS setup entry for
	 *               groupForNewFeUsers
	 */
	 public function getUncheckedUidsOfAllowedUserGroups() {
		 return t3lib_div::trimExplode(
			',',
		 	$this->getConfValueString('groupForNewFeUsers', 's_general'),
		 	true
		);
	 }

	/**
	 * Checks whether a radiobutton in a radiobutton group is selected.
	 *
	 * @param array the currently selected value in an associative array
	 *              with the key 'value'
	 *
	 * @return boolean true if a radiobutton is selected or if the form
	 *                 field is hidden, false if none is selected although
	 *                 the field is visible
	 */
	public function isRadiobuttonSelected(array $radiogroupValue) {
		if (!$this->isFormFieldEnabled(array('elementname' => 'usergroup'))) {
			return true;
		}

		$allowedValues = $this->getUncheckedUidsOfAllowedUserGroups();

		return in_array($radiogroupValue['value'], $allowedValues);
	}

	/**
	 * Checks whether we have at least two allowed user groups.
	 *
	 * @return boolean true if we have at least two allowed user groups,
	 *                 false otherwise
	 */
	private function hasAtLeastTwoUserGroups() {
		return (count($this->listUserGroups()) > 1);
	}

	/**
	 * Adds a class 'required' to the label of a field if it is required.
	 */
	private function setRequiredFieldLabels() {
		$formFieldsToCheck = array_diff(
			self::$availableFormFields,
			array('usergroup', 'gender', 'module_sys_dmail_html')
		);
		foreach ($formFieldsToCheck as $formField) {
			$this->setMarker(
				$formField . '_required',
				(in_array($formField, $this->requiredFormFields))
					? ' class="required"'
					: ''
			);
		}
	}

	/**
	 * Checks whether the content of a given field is non-empty or not required.
	 *
	 * @param array associative array containing the current value, with the key
	 *              'value' and the name, with the key 'elementName', of the
	 *              form field to check, must not be empty
	 *
	 * @return boolean true if this field is not empty or not required, false
	 *                 otherwise
	 */
	public function validateStringField(array $formData) {
		if ($this->checkPremisses($formData)) {
			return true;
		}

		return (trim($formData['value']) != '');
	}

	/**
	 * Checks whether the content of a given field is non-zero or not required.
	 *
	 * @param array associative array containing the current value, with the key
	 *              'value' and the name, with the key 'elementName', of the
	 *              form field to check, must not be empty
	 *
	 * @return boolean true if this field is not zero or not required, false
	 *                 otherwise
	 */
	public function validateIntegerField(array $formData) {
		if ($this->checkPremisses($formData)) {
			return true;
		}

		return (intval($formData['value']) != 0);
	}

	/**
	 * Checks if the form field data is not empty and if it is required.
	 *
	 * @throws Exception if the element name is empty
	 *
	 * @param array associative array containing the current value, with the key
	 *              'value' and the name, with the key 'elementName', of the
	 *              form field to check, must not be empty
	 *
	 * @return boolean true if the element was not required, false otherwise
	 */
	private function checkPremisses(array $formData) {
		if ($formData['elementName'] == '') {
			throw new Exception('The given field name was empty.');
		}

		if (empty($this->requiredFormFields)) {
			$this->setRequiredFormFields();
		}

		return !in_array($formData['elementName'], $this->requiredFormFields);
	}

	/**
	 * Checks if the usergroup subpart can be hidden.
	 *
	 * The "usergroup" field is a special case because it might also be
	 * hidden if there are less than two user groups available
	 *
	 * If the subpart is hidden it will be added to formFieldsToHide
	 *
	 * @param array the form fields which should be hidden, may be empty
	 */
	protected function setUsergroupSubpartVisibility(array &$formFieldsToHide) {
		if (!$this->hasAtLeastTwoUserGroups()) {
			$formFieldsToHide[] = 'usergroup';
		}
	}

	/**
	 * Checks if the zip_only subpart must be shown.
	 *
	 * The zip_only subpart must be shown if the zip is visible but the city
	 * is not.
	 *
	 * If the subpart is hidden it will be added to formFieldsToHide
	 *
	 * @param array the form fields which should be hidden, may be empty
	 */
	protected function setZipSubpartVisibility(array &$formFieldsToHide) {
		if (!in_array('city', $formFieldsToHide)
			|| in_array('zip', $formFieldsToHide)
		) {
			$formFieldsToHide[] = 'zip_only';
		}
	}

	/**
	 * Checks if the 'all_names' subpart containing the names label and
	 * the name related fields must be hidden.
	 *
	 * The all_names subpart will be hidden if all name related fields are
	 * hidden. These are: 'title', 'name', 'first_name', 'last_name' and
	 * 'gender'.
	 *
	 * If the subpart is hidden it will be added to formFieldsToHide
	 *
	 *
	 * @param array the form fields which should be hidden, may be empty
	 */
	protected function setAllNamesSubpartVisibility(array &$formFieldsToHide) {
		$hideAllNamesSubpart = true;
		$nameRelatedFields
			= array('title', 'name', 'first_name', 'last_name', 'gender');

		$visibleNameFields = array_diff(
			$nameRelatedFields,
			array_intersect($formFieldsToHide, $nameRelatedFields)
		);

		if (empty($visibleNameFields)) {
			$formFieldsToHide[] = 'all_names';
		}
	}

	/**
	 * Generates the challenge for the MD5 passwords.
	 *
	 * Before calling this function, it must be ensured that sr_feuser_register
	 * is loaded.
	 *
	 * @return string the challenge value to insert, will be empty if neither
	 *                kb_md5password is loaded nor felogin is set to use MD5
	 *                passwords
	 */
	protected function createChallenge() {
		require_once(
			t3lib_extMgm::extPath('sr_feuser_register') .
				'lib/class.tx_srfeuserregister_passwordmd5.php'
		);
		$srFeUserRegister = new tx_srfeuserregister_passwordmd5();

		$emptyArray = array();
		$srFeUserRegister->generateChallenge($emptyArray);

		return $srFeUserRegister->getChallenge();
	}

	/**
	 * Checks whether the extension sr_feuser_register is loaded and whether an
	 * MD5 password should be used.
	 *
	 * @return boolean true if sr_feuser_register is loaded and MD5 passwords
	 *                 are used, false otherwise
	 */
	private function usesMd5Passwords() {
		if (!t3lib_extMgm::isLoaded('sr_feuser_register')) {
			return false;
		}

		return t3lib_extMgm::isLoaded('kb_md5fepw')
			&& tx_oelib_db::existsTable('tx_kbmd5fepw_challenge');
	}

	/**
	 * Modifies the password to create an kb_md5pw style password.
	 *
	 * @param array the form data sent, must contain username, password and
	 *              challenge
	 *
	 * @return string the password conform to kb_md5pw, will not be empty
	 */
	private function createMd5Password($formData) {
		return md5($formData['username'] . ':' .  md5($formData['password']) .
			':' . $formData['challenge']);
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/onetimeaccount/pi1/class.tx_onetimeaccount_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/onetimeaccount/pi1/class.tx_onetimeaccount_pi1.php']);
}
?>