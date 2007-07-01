<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Oliver Klee <typo3-coding@oliverklee.de>
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

/**
 * Class that adds the wizard icon.
 *
 * @package		TYPO3
 * @subpackage	tx_onetimeaccount
 * @author		Oliver Klee <typo3-coding@oliverklee.de>
 */
class tx_onetimeaccount_pi1_wizicon {
	/**
	 * Processes the wizard items array.
	 *
	 * @param	array		the wizard items, may be empty, may not be null
	 *
	 * @return	array		modified array with wizard items
	 *
	 * @access	public
	 */
	function proc($wizardItems)	{
		global $LANG;

		$LL = $this->includeLocalLang();

		$wizardItems['plugins_tx_onetimeaccount_pi1'] = array(
			'icon' => t3lib_extMgm::extRelPath('onetimeaccount').'pi1/ce_wiz.gif',
			'title' => $LANG->getLLL('pi1_title', $LL),
			'description' => '',
			'params' => '&defVals[tt_content][CType]=list'
				.'&defVals[tt_content][list_type]=onetimeaccount_pi1'
		);

		return $wizardItems;
	}

	/**
	 * Reads the [extDir]/locallang.xml and returns the $LOCAL_LANG array found
	 * in that file.
	 *
	 * @return	array		the found language labels
	 *
	 * @access	public
	 */
	function includeLocalLang() {
		return t3lib_div::readLLXMLfile(
			t3lib_extMgm::extPath('onetimeaccount').'locallang.xml',
			$GLOBALS['LANG']->lang
		);
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/onetimeaccount/pi1/class.tx_onetimeaccount_pi1_wizicon.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/onetimeaccount/pi1/class.tx_onetimeaccount_pi1_wizicon.php']);
}

?>
