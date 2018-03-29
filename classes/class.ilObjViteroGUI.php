<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2009 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


include_once("./Services/Repository/classes/class.ilObjectPluginGUI.php");

/**
* User Interface class for example repository object.
*
* User interface classes process GET and POST parameter and call
* application classes to fulfill certain tasks.
*
* @author Stefan Meyer <smeyer.ilias@gmx.de>
*
* $Id: class.ilObjViteroGUI.php 56608 2014-12-19 10:11:57Z fwolf $
*
* Integration into control structure:
* - The GUI class is called by ilRepositoryGUI
* - GUI classes used by this class are ilPermissionGUI (provides the rbac
*   screens) and ilInfoScreenGUI (handles the info screen).
*
* @ilCtrl_isCalledBy ilObjViteroGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
* @ilCtrl_Calls ilObjViteroGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilRepositorySearchGUI
* @ilCtrl_Calls ilObjViteroGUI: ilCommonActionDispatcherGUI
*
*/
class ilObjViteroGUI extends ilObjectPluginGUI
{
	/**
	* Initialisation
	*/
	protected function afterConstructor()
	{
		// anything needed after object has been constructed
		// - example: append my_id GET parameter to each request
		//   $ilCtrl->saveParameter($this, array("my_id"));
	}
	
	/**
	* Handles all commmands of this class, centralizes permission checks
	*/
	public function performCommand($cmd)
	{
		$next_class = $this->ctrl->getNextClass($this);
		switch($next_class)
		{
			case 'ilrepositorysearchgui':
				include_once('./Services/Search/classes/class.ilRepositorySearchGUI.php');
				$rep_search = new ilRepositorySearchGUI();
				$rep_search->setCallback($this,
					'addParticipants',
					array(
						ilObjVitero::MEMBER =>  ilViteroPlugin::getInstance()->txt('add_as_group_member'),
						ilObjVitero::ADMIN => ilViteroPlugin::getInstance()->txt('add_as_group_admin')
					));

				// Set tabs
				$this->tabs_gui->setTabActive('participants');
				$this->ctrl->setReturn($this,'participants');
				$ret = $this->ctrl->forwardCommand($rep_search);
				break;
		}

		switch ($cmd)
		{
			case "editProperties":		// list all commands that need write permission here
			case "updateProperties":
			case 'initViteroGroup':
			case 'participants':
			case 'confirmDeleteParticipants':
			case 'deleteParticipants':
			case 'sendMailToSelectedUsers':
			case 'confirmDeleteAppointment':
			case 'confirmDeleteAppointmentInSeries':
			case 'deleteBookingInSeries':
			case 'deleteBooking':
			case 'confirmDeleteBooking':
			case 'showAppointmentCreation':
			case 'createAppointment':
			case 'editBooking':
			case 'updateBooking':
			case 'unlockUsers':
			case 'lockUsers':
			case 'materials':
			//case "...":
				$this->checkPermission("write");
				$this->$cmd();
				break;

			case "showContent":			// list all commands that need read permission here
			case 'startSession':
			//case "...":
			//case "...":
				$this->checkPermission("read");
				$this->$cmd();
				break;
		}
	}

	/**
	* Get type.
	*/
	final function getType()
	{
		return "xvit";
	}

	/**
	 * Init creation froms
	 *
	 * this will create the default creation forms: new
	 *
	 * @param	string	$a_new_type
	 * @return	array
	 */
	protected function initCreationForms($a_new_type)
	{
		$forms = array(
			self::CFORM_NEW => $this->initCreateForm($a_new_type),
			self::CFORM_CLONE => $this->fillCloneTemplate(null, $a_new_type)
			);

		return $forms;
	}

	/**
	 * init create form
	 * @param  $a_new_type
	 */
	public function  initCreateForm($a_new_type)
	{
		$GLOBALS['ilLog']->logStack();

		// @todo: handle this in delete event
		ilObjVitero::handleDeletedGroups();

		$form = parent::initCreateForm($a_new_type);
		$settings = ilViteroSettings::getInstance();

		// show selection
		if($settings->isCafeEnabled() and $settings->isStandardRoomEnabled())
		{
			$type_select = new ilRadioGroupInputGUI(
				ilViteroPlugin::getInstance()->txt('app_type'),
				'atype'
			);
			$type_select->setValue(ilViteroRoom::TYPE_CAFE);

			// Cafe
			$cafe = new ilRadioOption(
				ilViteroPlugin::getInstance()->txt('app_type_cafe'),
				ilViteroRoom::TYPE_CAFE
			);
			$type_select->addOption($cafe);

			$this->initFormCafe($cafe);

			// Standard
			$std = new ilRadioOption(
				ilViteroPlugin::getInstance()->txt('app_type_standard'),
				ilViteroRoom::TYPE_STD
			);
			$type_select->addOption($std);

			$this->initFormStandardRoom($std);


			$form->addItem($type_select);
		}
		elseif($settings->isCafeEnabled())
		{
			$this->initFormCafe($form);
		}
		elseif($settings->isStandardRoomEnabled())
		{
			$this->initFormStandardRoom($form);
		}

		$this->initFormTimeBuffer($form);
		$this->initFormRoomSize($form);

		return $form;
	}

	protected function initFormCafe($parent,$a_create = true)
	{
		global $lng, $tpl;

		$lng->loadLanguageModule('dateplaner');
		$lng->loadLanguageModule('crs');

		$tpl->addJavaScript('./Services/Form/js/date_duration.js');
		include_once './Services/Form/classes/class.ilDateDurationInputGUI.php';
		$dur = new ilDateDurationInputGUI($lng->txt('cal_fullday'),'cafe_time');
		if(!$a_create)
		{
			$dur->setDisabled(true);
		}
		
		$dur->setStartText($this->getPlugin()->txt('event_start_date'));
		$dur->setEndText($this->getPlugin()->txt('event_end_date'));
		$dur->setShowTime(false);

		$start = new ilDate(time(),IL_CAL_UNIX);
		$end = clone $start;
		$end->increment(IL_CAL_MONTH,1);

		$dur->setStart($start);
		$dur->setEnd($end);

		if($parent instanceof ilPropertyFormGUI)
		{
			$parent->addItem($dur);
		}
		else
		{
			$parent->addSubItem($dur);
		}
	}

	public function initFormStandardRoom($parent,$a_create = true)
	{
		global $lng, $tpl;

		$lng->loadLanguageModule('dateplaner');
		$lng->loadLanguageModule('crs');

		$tpl->addJavaScript('./Services/Form/js/date_duration.js');
		include_once './Services/Form/classes/class.ilDateDurationInputGUI.php';
		$dur = new ilDateDurationInputGUI($lng->txt('cal_fullday'),'std_time');
		$dur->setMinuteStepSize(15);
		$dur->setStartText($this->getPlugin()->txt('event_start_date'));
		$dur->setEndText($this->getPlugin()->txt('event_end_date'));
		$dur->setShowTime(true);

		$start = new ilDate(time(),IL_CAL_UNIX);
		$end = clone $start;

		$dur->setStart($start);
		$dur->setEnd($end);

		if($parent instanceof ilPropertyFormGUI)
		{
			$parent->addItem($dur);
		}
		else
		{
			$parent->addSubItem($dur);
		}

		if($a_create)
		{
			$lng->loadLanguageModule('dateplaner');
			$rec = new ilViteroRecurrenceInputGUI($lng->txt('cal_recurrences'), 'rec');
			if($parent instanceof ilPropertyFormGUI)
			{
				$parent->addItem($rec);
			}
			else
			{
				$parent->addSubItem($rec);
			}
		}
	}

	protected function initFormTimeBuffer(ilPropertyFormGUI $form)
	{
		$tbuffer = new ilNonEditableValueGUI(
			ilViteroPlugin::getInstance()->txt('time_buffer'),
			'dummy'
		);

		// Buffer before
		$buffer_before = new ilSelectInputGUI(
			ilViteroPlugin::getInstance()->txt('time_buffer_before'),
			'buffer_before'
		);
		$buffer_before->setOptions(
			array(
				0 => '0 min',
				15 => '15 min',
				30 => '30 min',
				45 => '45 min',
				60 => '1 h'
			)
		);

		$buffer_before->setValue(ilViteroSettings::getInstance()->getStandardGracePeriodBefore());
		$tbuffer->addSubItem($buffer_before);

		// Buffer after
		$buffer_after = new ilSelectInputGUI(
			ilViteroPlugin::getInstance()->txt('time_buffer_after'),
			'buffer_after'
		);
		$buffer_after->setOptions(
			array(
				0 => '0 min',
				15 => '15 min',
				30 => '30 min',
				45 => '45 min',
				60 => '1 h'
			)
		);
		$buffer_after->setValue(ilViteroSettings::getInstance()->getStandardGracePeriodAfter());
		$tbuffer->addSubItem($buffer_after);

		$form->addItem($tbuffer);
		return true;
	}

	/**
	 * Ini form for room size
	 * @param ilPropertyFormGUI $form
	 * @return bool
	 */
	protected function initFormRoomSize(ilPropertyFormGUI $form,$a_create = true)
	{
		$room_size_list = ilViteroUtils::getRoomSizeList();

		if(!count($room_size_list))
		{
			return false;
		}
		
		$room_size = new ilSelectInputGUI(ilViteroPlugin::getInstance()->txt('room_size'), 'room_size');
		$room_size->setOptions($room_size_list);

		if(!$a_create)
		{
			$room_size->setDisabled(true);
		}

		$form->addItem($room_size);
		return true;
	}


	protected function loadCafeSettings($form,$room)
	{
		$room->enableCafe(true);
		$GLOBALS['ilLog']->write(__METHOD__.': '.$form->getItemByPostVar('cafe_time')->getStart());
		$room->setStart($form->getItemByPostVar('cafe_time')->getStart());

		$end = clone $form->getItemByPostVar('cafe_time')->getStart();
		$end->increment(ilDateTime::DAY,1);
		$room->setEnd($end);
		$room->setRepetitionEndDate($form->getItemByPostVar('cafe_time')->getEnd());
		return $room;
	}

	protected function loadStandardRoomSettings($form,$room)
	{
		$room->enableCafe(false);
		$room->setStart($form->getItemByPostVar('std_time')->getStart());
		$room->setEnd($form->getItemByPostVar('std_time')->getEnd());
		$room->setBufferBefore($form->getInput('buffer_before'));
		$room->setBufferAfter($form->getInput('buffer_after'));

		if($form->getItemByPostVar('rec'))
		{
			$room->setRepetition($form->getItemByPostVar('rec')->getFrequenceType());
			$room->setRepetitionEndDate($form->getItemByPostVar('rec')->getFrequenceUntilDate());
		}

		return $room;
	}

	/**
	 *
	 * @global <type> $ilCtrl
	 * @global <type> $ilUser
	 * @param ilObjVitero $newObj
	 */
	public function afterSave(ilObject $newObj)
	{
		global $ilCtrl, $ilUser;

		$settings = ilViteroSettings::getInstance();
		$form = $this->initCreateForm('xvit');
		$form->checkInput();

		$room = new ilViteroRoom();
		$room->setRoomSize($form->getInput('room_size'));
		if($settings->isCafeEnabled() and $settings->isStandardRoomEnabled())
		{
			if($form->getInput('atype') == ilViteroRoom::TYPE_CAFE)
			{
				$room = $this->loadCafeSettings($form, $room);
			}
			else
			{
				$room = $this->loadStandardRoomSettings($form, $room);
			}

			$room->isCafe($form->getInput('atype') == ilViteroRoom::TYPE_CAFE);
		}
		elseif($settings->isCafeEnabled())
		{
			$this->loadCafeSettings($form, $room);
		}
		else
		{
			$this->loadStandardRoomSettings($form, $room);
		}


		try {
			$newObj->initVitero($ilUser->getId());
			$newObj->initAppointment($room);
			ilUtil::sendSuccess(ilViteroPlugin::getInstance()->txt('created_vitero'), true);
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getViteroMessage(),true);
		}

		$newObj->addParticipants(array($ilUser->getId()), ilObjVitero::ADMIN);
		parent::afterSave($newObj);
	}




	/**
	* After object has been created -> jump to this command
	*/
	public function getAfterCreationCmd()
	{
		return "showContent";
	}

	/**
	* Get standard command
	*/
	public function getStandardCmd()
	{
		return "showContent";
	}
	
//
// DISPLAY TABS
//
	
	/**
	* Set tabs
	*/
	public function setTabs()
	{
		global $ilTabs, $ilCtrl, $ilAccess;
		

		// standard info screen tab
		$this->addInfoTab();

		// tab for the "show content" command
		
		
		if ($ilAccess->checkAccess("read", "", $this->object->getRefId()))
		{
			$ilTabs->addTab("content", $this->txt("app_tab"), $ilCtrl->getLinkTarget($this, "showContent"));
		}

		// a "properties" tab
		if ($ilAccess->checkAccess("write", "", $this->object->getRefId()))
		{
			$filesEnabled = ilViteroSettings::getInstance()->isContentAdministrationEnabled();
			if($filesEnabled)
			{
				$ilTabs->addTab('materials', $this->txt('materials'), $ilCtrl->getLinkTarget($this,'materials'));
			}
			$ilTabs->addTab("properties", $this->txt("properties"), $ilCtrl->getLinkTarget($this, "editProperties"));
		}

		if($ilAccess->checkAccess('write','',$this->object->getRefId()))
		{
			$ilTabs->addTab(
				'participants',
				$this->txt('members'),
				$ilCtrl->getLinkTarget($this,'participants')
			);
		}


		// standard epermission tab
		$this->addPermissionTab();
	}
	
	/**
	* Edit Properties. This commands uses the form class to display an input form.
	*/
	public function editProperties()
	{
		global $tpl, $ilTabs;

		$ilTabs->activateTab("properties");
		$this->initPropertiesForm();
		$this->getPropertiesValues();
		$tpl->setContent($this->form->getHTML());
	}
	
	/**
	* Init  form.
	*
	* @param        int        $a_mode        Edit Mode
	*/
	public function initPropertiesForm()
	{
		global $ilCtrl;
	
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form = new ilPropertyFormGUI();
	
		// title
		$ti = new ilTextInputGUI($this->txt("title"), "title");
		$ti->setRequired(true);
		$this->form->addItem($ti);
		
		// description
		$ta = new ilTextAreaInputGUI($this->txt("description"), "desc");
		$this->form->addItem($ta);
		
		$this->form->addCommandButton("updateProperties", $this->txt("save"));
	                
		$this->form->setTitle($this->txt("edit_properties"));
		$this->form->setFormAction($ilCtrl->getFormAction($this));
	}
	
	/**
	* Get values for edit properties form
	*/
	public function getPropertiesValues()
	{
		$values["title"] = $this->object->getTitle();
		$values["desc"] = $this->object->getDescription();
		$this->form->setValuesByArray($values);
	}
	
	/**
	* Update properties
	*/
	public function updateProperties()
	{
		global $tpl, $lng, $ilCtrl;
	
		$this->initPropertiesForm();
		if ($this->form->checkInput())
		{
			$this->object->setTitle($this->form->getInput("title"));
			$this->object->setDescription($this->form->getInput("desc"));
			$this->object->update();
			ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
			$ilCtrl->redirect($this, "editProperties");
		}

		$this->form->setValuesByPost();
		$tpl->setContent($this->form->getHtml());
	}
	
//
// Show content
//

	/**
	* Show content
	*/
	public function showContent()
	{
		global $tpl, $ilTabs, $ilAccess, $ilToolbar;

		$ilTabs->activateTab("content");

		// Show add appointment
		if($ilAccess->checkAccess('write','',$this->object->getRefId()))
		{
			// Edit appointment
			$ilToolbar->addButton(
				ilViteroPlugin::getInstance()->txt('tbbtn_add_appointment'),
				$this->ctrl->getLinkTarget($this,'showAppointmentCreation')
			);
		}

		$this->object->checkInit();

		$table = new ilViteroBookingTableGUI($this,'showContent');
		$table->setEditable((bool) $ilAccess->checkAccess('write','',$this->object->getRefId()));
		$table->init();
		
		$start = new ilDateTime(time(),IL_CAL_UNIX);
		$start->increment(ilDateTime::HOUR,-1);
		$end = clone $start;
		$end->increment(IL_CAL_YEAR,1);
		try {
			$table->parse(
				$this->object->getVGroupId(),
				$start,
				$end
			);
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getViteroMessage(),true);
			return false;
		}
		$tpl->setContent($table->getHTML());
	}



	/**
	 * Add info items
	 * @param ilInfoScreenGUI $info 
	 */
	public function addInfoItems($info)
	{
		global $ilCtrl, $ilUser;

		$access = true;
		if(ilViteroLockedUser::isLocked($ilUser->getId(), $this->object->getVGroupId()))
		{
			ilUtil::sendFailure(ilViteroPlugin::getInstance()->txt('user_locked_info'));
			$access = false;
		}

		$booking_id = ilViteroUtils::getOpenRoomBooking($this->object->getVGroupId());
		
		if($booking_id and $access)
		{
			$this->ctrl->setParameter($this,'bid',$booking_id);
			$info->setFormAction($ilCtrl->getFormAction($this),'_blank');
			$big_button = '<div class="il_ButtonGroup" style="margin:25px; text-align:center; font-size:25px;">'.
				'<input type="submit" class="submit" name="cmd[startSession]" value="'.ilViteroPlugin::getInstance()->txt('start_session').
				'" style="padding:10px;" /></div>';

			$info->addSection("");
			$info->addProperty("", $big_button);
		}
		
		$start = new ilDateTime(time(),IL_CAL_UNIX);
		$end = clone $start;
		$end->increment(IL_CAL_YEAR,1);

		$booking = ilViteroUtils::lookupNextBooking($start,$end,$this->object->getVGroupId());

		if(!$booking['start'] instanceof  ilDateTime)
		{
			return true;
		}

		ilDatePresentation::setUseRelativeDates(false);

		$info->addSection(ilViteroPlugin::getInstance()->txt('info_next_appointment'));
		$info->addProperty(
			ilViteroPlugin::getInstance()->txt('info_next_appointment_dt'),
			ilDatePresentation::formatPeriod(
				$booking['start'],
				$booking['end']
			)
		);


	}

	public function materials()
	{
		global $ilUser, $ilTabs, $ilAccess, $ilCtrl;

		$ilTabs->activateTab('materials');

		try {

			// @todo wrap the creation/update of users
			// Create update user
			$map = new ilViteroUserMapping();
			$vuid = $map->getVUserId($ilUser->getId());
			$ucon = new ilViteroUserSoapConnector();
			if(!$vuid)
			{
				$vuid = $ucon->createUser($ilUser);
				$map->map($ilUser->getId(), $vuid);
			}
			else
			{
				try {
					$ucon->updateUser($vuid,$ilUser);
				}
				catch(ilViteroConnectorException $e)
				{
					if($e->getCode() == 53)
					{
						$map->unmap($ilUser->getId());
						$vuid = $ucon->createUser($ilUser);
						$map->map($ilUser->getId(), $vuid);
					}
				}
			}

			// Assign user to vitero group
			$grp = new ilViteroGroupSoapConnector();
			$grp->addUserToGroup($this->object->getVGroupId(), $vuid);

			$grp->changeGroupRole(
				$this->object->getVGroupId(),
				$vuid,
				$ilAccess->checkAccess('write','',$this->object->getRefId()) ?
					ilViteroGroupSoapConnector::ADMIN_ROLE :
					ilViteroGroupSoapConnector::MEMBER_ROLE
			);

			$sc = new ilViteroSessionCodeSoapConnector();
			$dur = new ilDateTime(time(), IL_CAL_UNIX);
			$dur->increment(IL_CAL_HOUR,2);
			$code_vms = $sc->createVmsSessionCode($vuid, $this->object->getVGroupId(),$dur);
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getViteroMessage(),true);
			$ilCtrl->redirect($this,'infoScreen');
		}

		$tpl = ilViteroPlugin::getInstance()->getTemplate('tpl.materials.html');

		$tpl->setVariable(
			'FRAME_SRC',
			ilViteroSettings::getInstance()->getGroupFolderLink().
				'?fl=1&action=reload&topmargin=10&group_id='.$this->object->getVGroupId().'&'.
				'code='.$code_vms
		);
		$GLOBALS['tpl']->setContent($tpl->get());
	}

	/**
	 * start session
	 * @global <type> $ilDB
	 */
	public function startSession()
	{
		global $ilDB, $ilUser, $ilCtrl, $ilAccess;

		// Handle deleted accounts
		ilObjVitero::handleDeletedUsers();

		try {

			// Create update user
			$map = new ilViteroUserMapping();
			$vuid = $map->getVUserId($ilUser->getId());
			$ucon = new ilViteroUserSoapConnector();
			if(!$vuid)
			{
				$vuid = $ucon->createUser($ilUser);
				$map->map($ilUser->getId(), $vuid);
			}
			else
			{
				try {
					$ucon->updateUser($vuid,$ilUser);
				}
				catch(ilViteroConnectorException $e)
				{
					if($e->getCode() == 53)
					{
						$map->unmap($ilUser->getId());
						$vuid = $ucon->createUser($ilUser);
						$map->map($ilUser->getId(), $vuid);
					}
				}
			}
			// Store update image
			if(ilViteroSettings::getInstance()->isAvatarEnabled())
			{
				$usr_image_path = ilUtil::getWebspaceDir().'/usr_images/usr_'.$ilUser->getId().'.jpg';
				if(@file_exists($usr_image_path))
				{
					$ucon->storeAvatarUsingBase64(
							$vuid,
							array(
								'name' => 'usr_image.jpg',
								'type' => ilViteroAvatarSoapConnector::FILE_TYPE_NORMAL,
								'file' => $usr_image_path
							)
						);
				}
			}

			/*
			if(ilViteroSettings::getInstance()->isAvatarEnabled() and 0)
			{
				try {
					$avatar_service = new ilViteroAvatarSoapConnector();
					$usr_image_path = ilUtil::getWebspaceDir().'/usr_images/usr_'.$ilUser->getId().'.jpg';

					if(@file_exists($usr_image_path))
					{
						$avatar_service->storeAvatar(
							$vuid,
							array(
								'name' => 'usr_image.jpg',
								'type' => ilViteroAvatarSoapConnector::FILE_TYPE_NORMAL,
								'file' => $usr_image_path
							)
						);
					}
				}
				catch(ilViteroConnectorException $e)
				{
					// continue
				}
			}
		    */
			// Assign user to vitero group
			$grp = new ilViteroGroupSoapConnector();
			$grp->addUserToGroup($this->object->getVGroupId(), $vuid);

			$grp->changeGroupRole(
				$this->object->getVGroupId(),
				$vuid,
				$ilAccess->checkAccess('write','',$this->object->getRefId()) ? 
					ilViteroGroupSoapConnector::ADMIN_ROLE :
					ilViteroGroupSoapConnector::MEMBER_ROLE
			);

			$sc = new ilViteroSessionCodeSoapConnector();
			$dur = new ilDateTime(time(), IL_CAL_UNIX);
			$dur->increment(IL_CAL_HOUR,2);
			$code = $sc->createPersonalBookingSessionCode($vuid, (int) $_GET['bid'], $dur);

			$GLOBALS['ilLog']->write(__METHOD__.': '.ilViteroSettings::getInstance()->getWebstartUrl().'?code='.$code);
			ilUtil::redirect(ilViteroSettings::getInstance()->getWebstartUrl().'?sessionCode='.$code);
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getViteroMessage(),true);
			$ilCtrl->redirect($this,'infoScreen');
		}
	}


	/**
	 * Show participants
	 */
	protected function participants()
	{
		global $ilTabs, $rbacreview, $ilUser;

		$ilTabs->activateTab('participants');

		$this->addSearchToolbar();

		$tpl = ilViteroPlugin::getInstance()->getTemplate('tpl.edit_participants.html');

		$this->setShowHidePrefs();

		if($rbacreview->assignedUsers((int) $this->object->getDefaultAdminRole()))
		{
			if($ilUser->getPref('xvit_admin_hide'))
			{
				$table_gui = new ilViteroParticipantsTableGUI($this,ilObjVitero::ADMIN,false);
				$table_gui->setVGroupId($this->object->getVGroupId());
				$this->ctrl->setParameter($this,'admin_hide',0);
				$table_gui->addHeaderCommand($this->ctrl->getLinkTarget($this,'participants'),
					$this->lng->txt('show'));
				$this->ctrl->clearParameters($this);
			}
			else
			{
				$table_gui = new ilViteroParticipantsTableGUI($this,ilObjVitero::ADMIN,true);
				$table_gui->setVGroupId($this->object->getVGroupId());
				$this->ctrl->setParameter($this,'admin_hide',1);
				$table_gui->addHeaderCommand($this->ctrl->getLinkTarget($this,'participants'),
					$this->lng->txt('hide'));
				$this->ctrl->clearParameters($this);
			}
			$table_gui->setTitle(
				ilViteroPlugin::getInstance()->txt('admins'),
				'icon_usr.svg',$this->lng->txt('grp_admins'));
			$table_gui->parse($rbacreview->assignedUsers((int) $this->object->getDefaultAdminRole()));
			$tpl->setVariable('ADMINS',$table_gui->getHTML());
		}


		if($rbacreview->assignedUsers((int) $this->object->getDefaultMemberRole()))
		{
			if($ilUser->getPref('xvit_member_hide'))
			{
				$table_gui = new ilViteroParticipantsTableGUI($this,ilObjVitero::MEMBER,false);
				$table_gui->setVGroupId($this->object->getVGroupId());
				$this->ctrl->setParameter($this,'member_hide',0);
				$table_gui->addHeaderCommand($this->ctrl->getLinkTarget($this,'participants'),
					$this->lng->txt('show'));
				$this->ctrl->clearParameters($this);
			}
			else
			{
				$table_gui = new ilViteroParticipantsTableGUI($this,ilObjVitero::MEMBER,true);
				$table_gui->setVGroupId($this->object->getVGroupId());
				$this->ctrl->setParameter($this,'member_hide',1);
				$table_gui->addHeaderCommand($this->ctrl->getLinkTarget($this,'participants'),
					$this->lng->txt('hide'));
				$this->ctrl->clearParameters($this);
			}

			$table_gui->setTitle(
				ilViteroPlugin::getInstance()->txt('participants'),
				'icon_usr.svg',$this->lng->txt('grp_members'));
			$table_gui->parse($rbacreview->assignedUsers((int) $this->object->getDefaultMemberRole()));
			$tpl->setVariable('MEMBERS',$table_gui->getHTML());

		}
		$remove = ilSubmitButton::getInstance();
		$remove->setCommand("confirmDeleteParticipants");
		$remove->setCaption("remove",true);
		$tpl->setVariable('BTN_REMOVE',$remove->render());
		if(ilViteroLockedUser::hasLockedAccounts($this->object->getVGroupId()))
		{
		$unlock = ilSubmitButton::getInstance();
		$unlock->setCommand("unlockUsers");
		$unlock->setCaption(ilViteroPlugin::getInstance()->txt('btn_unlock'),false);
		$tpl->setVariable('BTN_UNLOCK',$unlock->render());
		}
		$lock = ilSubmitButton::getInstance();
		$lock->setCommand("lockUsers");
		$lock->setCaption(ilViteroPlugin::getInstance()->txt('btn_lock'),false);
		$tpl->setVariable('BTN_LOCK',$lock->render());
		$mail = ilSubmitButton::getInstance();
		$mail->setCommand("sendMailToSelectedUsers");
		$mail->setCaption("grp_mem_send_mail",true);
		$tpl->setVariable('BTN_MAIL',$mail->render());

		$tpl->setVariable('ARROW_DOWN',ilUtil::getImagePath('arrow_downright.svg'));
		$tpl->setVariable('FORMACTION',$this->ctrl->getFormAction($this));

		$GLOBALS['tpl']->setContent($tpl->get());
	}

	/**
	 * Unlock accounts
	 * @return bool
	 */
	protected function unlockUsers()
	{
		$this->tabs_gui->setTabActive('participants');

		$participants_to_unlock = (array) array_unique(array_merge((array) $_POST['admins'],(array) $_POST['members']));

		if(!count($participants_to_unlock))
		{
			ilUtil::sendFailure($this->lng->txt('no_checkbox'));
			$this->participants();
			return false;
		}

		foreach($participants_to_unlock as $part)
		{
			$unlock = new ilViteroLockedUser();
			$unlock->setUserId($part);
			$unlock->setVGroupId($this->object->getVGroupId());
			$unlock->setLocked(false);
			$unlock->update();
		}

		$grp = new ilViteroGroupSoapConnector();
		$grp->updateEnabledStatusForUsers($participants_to_unlock,$this->object->getVGroupId(),true);

		ilUtil::sendSuccess($GLOBALS['lng']->txt('settings_saved'),true);
		$GLOBALS['ilCtrl']->redirect($this,'participants');
	}

	/**
	 * Unlock accounts
	 * @return bool
	 */
	protected function lockUsers()
	{
		$this->tabs_gui->setTabActive('participants');

		$participants_to_unlock = (array) array_unique(array_merge((array) $_POST['admins'],(array) $_POST['members']));

		if(!count($participants_to_unlock))
		{
			ilUtil::sendFailure($this->lng->txt('no_checkbox'));
			$this->participants();
			return false;
		}

		foreach($participants_to_unlock as $part)
		{
			$unlock = new ilViteroLockedUser();
			$unlock->setUserId($part);
			$unlock->setVGroupId($this->object->getVGroupId());
			$unlock->setLocked(true);
			$unlock->update();
		}

		$grp = new ilViteroGroupSoapConnector();
		$grp->updateEnabledStatusForUsers($participants_to_unlock,$this->object->getVGroupId(),false);

		ilUtil::sendSuccess($GLOBALS['lng']->txt('settings_saved'),true);
		$GLOBALS['ilCtrl']->redirect($this,'participants');
	}

	protected function confirmDeleteParticipants()
	{
		$this->tabs_gui->setTabActive('participants');

		$participants_to_delete = (array) array_unique(array_merge((array) $_POST['admins'],(array) $_POST['members']));

		if(!count($participants_to_delete))
		{
			ilUtil::sendFailure($this->lng->txt('no_checkbox'));
			$this->participants();
			return false;
		}


		$this->lng->loadLanguageModule('grp');

		include_once('./Services/Utilities/classes/class.ilConfirmationGUI.php');
		$confirm = new ilConfirmationGUI();
		$confirm->setFormAction($this->ctrl->getFormAction($this,'deleteParticipants'));
		$confirm->setHeaderText($this->lng->txt('grp_dismiss_member'));
		$confirm->setConfirm($this->lng->txt('confirm'),'deleteParticipants');
		$confirm->setCancel($this->lng->txt('cancel'),'participants');

		foreach($participants_to_delete as $participant)
		{
			$names = ilObjUser::_lookupName($participant);



			$confirm->addItem('participants[]',
				$participant,
				$names['lastname'].', '.$names['firstname'].' ['.$names['login'].']',
				ilUtil::getImagePath('icon_usr.svg'));
		}
		$this->tpl->setContent($confirm->getHTML());
	}

	/**
	 * Delete participants
	 */
	protected function  deleteParticipants()
	{
		global $rbacadmin,$lng;

		if(!count($_POST['participants']))
		{
			ilUtil::sendFailure($this->lng->txt('no_checkbox'));
			$this->participants();
			return true;
		}

		foreach((array) $_POST['participants'] as $part)
		{
			$rbacadmin->deassignUser($this->object->getDefaultAdminRole(),$part);
			$rbacadmin->deassignUser($this->object->getDefaultMemberRole(),$part);

			$locked = new ilViteroLockedUser();
			$locked->setUserId($part);
			$locked->setVGroupId($this->object->getVGroupId());
			$locked->delete();
		}

		$lng->loadLanguageModule('grp');
		ilUtil::sendSuccess($this->lng->txt("grp_msg_membership_annulled"));
		$this->participants();
		return true;
	}

	protected function sendMailToSelectedUsers()
	{
		$_POST['participants'] = array_unique(array_merge((array) $_POST['admins'],(array) $_POST['members']));
		if (!count($_POST['participants']))
		{
			ilUtil::sendFailure($this->lng->txt("no_checkbox"));
			$this->participants();
			return false;
		}
		foreach($_POST['participants'] as $usr_id)
		{
			$rcps[] = ilObjUser::_lookupLogin($usr_id);
		}
        require_once 'Services/Mail/classes/class.ilMailFormCall.php';
		ilUtil::redirect(ilMailFormCall::getRedirectTarget(
			$this,
			'participants',
			array(),
			array('type' => 'new', 'rcp_to' => implode(',',$rcps))));
		return true;
	}


	/**
	 * set preferences (show/hide tabel content)
	 *
	 * @access public
	 * @return
	 */
	public function setShowHidePrefs()
	{
		global $ilUser;

		if(isset($_GET['admin_hide']))
		{
			$ilUser->writePref('xvit_admin_hide',(int) $_GET['admin_hide']);
		}
		if(isset($_GET['member_hide']))
		{
			$ilUser->writePref('xvit_member_hide',(int) $_GET['member_hide']);
		}
	}


	protected function addSearchToolbar()
	{
		global $ilToolbar,$lng;

		$lng->loadLanguageModule('crs');

		// add members
		include_once './Services/Search/classes/class.ilRepositorySearchGUI.php';
		ilRepositorySearchGUI::fillAutoCompleteToolbar(
			$this,
			$ilToolbar,
			array(
				'auto_complete_name'	=> $lng->txt('user'),
				'user_type'				=> array(
					ilObjVitero::MEMBER => ilViteroPlugin::getInstance()->txt('member'),
					ilObjVitero::ADMIN => ilViteroPlugin::getInstance()->txt('admin')
				),
				'submit_name'			=> $lng->txt('add')
			)
		);

		// spacer
		$ilToolbar->addSeparator();

		// search button
		$ilToolbar->addButton(
			$this->lng->txt("crs_search_users"),
			$this->ctrl->getLinkTargetByClass('ilRepositorySearchGUI','start')
		);
		return true;

	}

	/**
	 * Callback for ilRepositorySearchGUI
	 * @param array $a_user_ids
	 * @param int $a_type
	 */
	public function addParticipants($a_user_ids, $a_type)
	{
		try {
			$this->object->addParticipants($a_user_ids, $a_type);
		}
		catch(InvalidArgumentException $e)
		{
			ilUtil::sendFailure($e->getMessage());
			return false;
		}
		ilUtil::sendSuccess(ilViteroPlugin::getInstance()->txt('assigned_users'));
		$this->ctrl->redirect($this,'participants');
	}

	public function confirmDeleteBooking()
	{
		global $tpl, $ilTabs;

		$ilTabs->activateTab('content');

		try {

			$booking_service = new ilViteroBookingSoapConnector();
			$book = $booking_service->getBookingById($_REQUEST['bookid']);
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getMessage(), true);
			$GLOBALS['ilCtrl']->redirect($this,'showContent');
		}

		include_once './Services/Utilities/classes/class.ilConfirmationGUI.php';
		$confirm = new ilConfirmationGUI();
		$confirm->setFormAction($GLOBALS['ilCtrl']->getFormAction($this));
		$confirm->setHeaderText(ilViteroPlugin::getInstance()->txt('sure_delete_appointment_series'));

		$start = ilViteroUtils::parseSoapDate($book->booking->start);

		ilDatePresentation::setUseRelativeDates(false);

		$confirm->addItem(
			'bookid[]',
			(int) $_REQUEST['bookid'],
			sprintf(
				ilViteroPlugin::getInstance()->txt('confirm_delete_series_txt'),
				ilDatePresentation::formatDate($start),
				ilViteroUtils::recurrenceToString($book->booking->repetitionpattern)
			)
		);

		$confirm->setConfirm($GLOBALS['lng']->txt('delete'), 'deleteBooking');
		$confirm->setCancel($GLOBALS['lng']->txt('cancel'), 'showContent');
		$tpl->setContent($confirm->getHTML());
	}

	protected function deleteBooking()
	{
		foreach((array) $_REQUEST['bookid'] as $bookid)
		{
			try {
				$booking_service = new ilViteroBookingSoapConnector();
				$booking_service->deleteBooking($bookid);
			}
			catch(ilViteroConnectorException $e)
			{
				ilUtil::sendFailure($e->getMessage(),true);
				$GLOBALS['ilCtrl']->redirect($this,'showContent');
			}
		}
		ilUtil::sendSuccess(ilViteroPlugin::getInstance()->txt('deleted_booking'));
		$GLOBALS['ilCtrl']->redirect($this,'showContent');
	}

	protected function deleteBookingInSeries()
	{
		foreach((array) $_REQUEST['bookid'] as $bookid)
		{
			$excl = new ilViteroBookingReccurrenceExclusion();
			$excl->setDate(new ilDate($_REQUEST['atime'],IL_CAL_UNIX));
			$excl->setEntryId($bookid);
			$excl->save();
		}
		ilUtil::sendSuccess(ilViteroPlugin::getInstance()->txt('deleted_booking'));
		$GLOBALS['ilCtrl']->redirect($this,'showContent');
	}

	protected function confirmDeleteAppointment($inRecurrence = false)
	{
		global $tpl, $ilTabs;

		$ilTabs->activateTab('content');

		try {

			$booking_service = new ilViteroBookingSoapConnector();
			$book = $booking_service->getBookingById($_REQUEST['bookid']);
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getMessage(), true);
			$GLOBALS['ilCtrl']->redirect($this,'showContent');
		}

		include_once './Services/Utilities/classes/class.ilConfirmationGUI.php';
		$confirm = new ilConfirmationGUI();
		$confirm->setFormAction($GLOBALS['ilCtrl']->getFormAction($this));
		$confirm->setHeaderText(ilViteroPlugin::getInstance()->txt('sure_delete_appointment'));

		if($inRecurrence)
		{
			$start = new ilDateTime($_REQUEST['atime'],IL_CAL_UNIX);
			$confirm->setConfirm($GLOBALS['lng']->txt('delete'), 'deleteBookingInSeries');
		}
		else
		{
			$start = ilViteroUtils::parseSoapDate($book->booking->start);
			$confirm->setConfirm($GLOBALS['lng']->txt('delete'), 'deleteBooking');
		}

		ilDatePresentation::setUseRelativeDates(false);

		$confirm->addItem(
			'bookid[]',
			(int) $_REQUEST['bookid'],
			ilDatePresentation::formatDate($start)
		);

		if($inRecurrence)
		{
			$confirm->addHiddenItem('atime', $_REQUEST['atime']);
		}

		$confirm->setCancel($GLOBALS['lng']->txt('cancel'), 'showContent');

		$tpl->setContent($confirm->getHTML());
	}

	public function confirmDeleteAppointmentInSeries()
	{

		$this->confirmDeleteAppointment(true);

	}

	
	protected function initAppointmentCreationForm($a_create = true)
	{
		global $lng;

		include_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));


		if($a_create)
		{
			$form->setTitle(ilViteroPlugin::getInstance()->txt('tbl_add_appointment'));
			$form->addCommandButton(
				'createAppointment',
				ilViteroPlugin::getInstance()->txt('btn_add_appointment')
			);
		}
		else
		{
			$form->setTitle(ilViteroPlugin::getInstance()->txt('tbl_update_appointment'));
			$form->addCommandButton(
				'updateBooking',
				ilViteroPlugin::getInstance()->txt('save')
			);

		}

		$form->addCommandButton(
			'showContent',
			$GLOBALS['lng']->txt('cancel')
		);

		$settings = ilViteroSettings::getInstance();
		// show selection
		if($settings->isCafeEnabled() and $settings->isStandardRoomEnabled())
		{
			$type_select = new ilRadioGroupInputGUI(
				ilViteroPlugin::getInstance()->txt('app_type'),
				'atype'
			);

			if(!$a_create)
			{
				$type_select->setDisabled(true);
			}

			$type_select->setValue(ilViteroRoom::TYPE_CAFE);

			// Cafe
			$cafe = new ilRadioOption(
				ilViteroPlugin::getInstance()->txt('app_type_cafe'),
				ilViteroRoom::TYPE_CAFE
			);
			$type_select->addOption($cafe);

			$this->initFormCafe($cafe,$a_create);

			// Standard
			$std = new ilRadioOption(
				ilViteroPlugin::getInstance()->txt('app_type_standard'),
				ilViteroRoom::TYPE_STD
			);
			$type_select->addOption($std);

			$this->initFormStandardRoom($std,$a_create);


			$form->addItem($type_select);
		}
		elseif($settings->isCafeEnabled())
		{
			$this->initFormCafe($form,$a_create);
		}
		elseif($settings->isStandardRoomEnabled())
		{
			$this->initFormStandardRoom($form,$a_create);
		}

		$this->initFormTimeBuffer($form);
		$this->initFormRoomSize($form,$a_create);

		return $form;
	}

	protected function showAppointmentCreation()
	{
		global $ilTabs;

		$ilTabs->activateTab('content');

		$form = $this->initAppointmentCreationForm();

		$GLOBALS['tpl']->setContent($form->getHTML());
	}

	protected function createAppointment()
	{
		global $ilTabs;

		$form = $this->initAppointmentCreationForm();

		$ilTabs->activateTab('content');

		if(!$form->checkInput())
		{
			$form->setValuesByPost();
			ilUtil::sendFailure(
				$this->lng->txt('err_check_input')
			);
			$GLOBALS['tpl']->setContent($form->getHTML());
			return false;
		}


		// Save and create appointment
		$settings = ilViteroSettings::getInstance();

		$room = new ilViteroRoom();
		$room->setRoomSize($form->getInput('room_size'));

		if($settings->isCafeEnabled() and $settings->isStandardRoomEnabled())
		{
			if($form->getInput('atype') == ilViteroRoom::TYPE_CAFE)
			{
				$room = $this->loadCafeSettings($form, $room);
			}
			else
			{
				$room = $this->loadStandardRoomSettings($form, $room);
			}

			$room->isCafe($form->getInput('atype') == ilViteroRoom::TYPE_CAFE);
		}
		elseif($settings->isCafeEnabled())
		{
			$this->loadCafeSettings($form, $room);
		}
		else
		{
			$this->loadStandardRoomSettings($form, $room);
		}

		try {
			$this->object->initAppointment($room);
			ilUtil::sendSuccess(ilViteroPlugin::getInstance()->txt('created_vitero'), true);
			$this->ctrl->redirect($this,'showContent');
			return true;
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getViteroMessage(),true);
			$form->setValuesByPost();
			$GLOBALS['tpl']->setContent($form->getHTML());
		}
	}

	protected function editBooking()
	{
		global $ilTabs;

		$this->ctrl->setParameter($this,'bookid',(int) $_REQUEST['bookid']);

		$ilTabs->activateTab('content');

		try {

			$booking_service = new ilViteroBookingSoapConnector();
			$booking = $booking_service->getBookingById((int) $_REQUEST['bookid']);
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getMessage(),true);
			$this->ctrl->redirect($this,'showContent');
		}
		$form = $this->initUpdateBookingForm($booking);
		$GLOBALS['tpl']->setContent($form->getHTML());
	}


	protected function initUpdateBookingForm($booking)
	{
		global $lng;

		$lng->loadLanguageModule('dateplaner');
		$lng->loadLanguageModule('crs');

		include_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this,'showContent'));
		$form->setTitle(ilViteroPlugin::getInstance()->txt('tbl_update_appointment'));
		$form->addCommandButton('updateBooking', $GLOBALS['lng']->txt('save'));
		$form->addCommandButton('showContent', $GLOBALS['lng']->txt('cancel'));


		// Show only start if type is "cafe"
		if($booking->booking->cafe)
		{
			$start = new ilDateTimeInputGUI($this->getPlugin()->txt('event_start_date'), 'cstart');
			$start->setShowTime(false);
			$start->setDate(ilViteroUtils::parseSoapDate($booking->booking->start));
			$form->addItem($start);
		}
		else
		{
			include_once './Services/Form/classes/class.ilDateDurationInputGUI.php';
			$dt = new ilDateDurationInputGUI($lng->txt('cal_fullday'), 'roomduration');
			$dt->setMinuteStepSize(15);
			$dt->setStartText($this->getPlugin()->txt('event_start_date'));
			$dt->setEndText($this->getPlugin()->txt('event_end_date'));
			$dt->setShowTime(true);

			$dt->setStart(ilViteroUtils::parseSoapDate($booking->booking->start));
			$dt->setEnd(ilViteroUtils::parseSoapDate($booking->booking->end));

			$form->addItem($dt);
	
			$this->initFormTimeBuffer($form);

			$form->getItemByPostVar('buffer_before')->setValue($booking->booking->startbuffer);
			$form->getItemByPostVar('buffer_after')->setValue($booking->booking->endbuffer);
		}


		return $form;
	}

	protected function updateBooking()
	{
		global $ilTabs;

		$ilTabs->activateTab('content');
		$this->ctrl->setParameter($this,'bookid',(int) $_REQUEST['bookid']);

		try {
			$booking_service = new ilViteroBookingSoapConnector();
			$booking = $booking_service->getBookingById((int) $_REQUEST['bookid']);
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getMessage(),true);
			$this->ctrl->redirect($this,'showContent');
		}

		$form = $this->initUpdateBookingForm($booking);


		if(!$form->checkInput())
		{
			$form->setValuesByPost();
			ilUtil::sendFailure(
				$this->lng->txt('err_check_input')
			);
			$GLOBALS['tpl']->setContent($form->getHTML());
			return false;
		}

		$room = new ilViteroRoom();
		$room->setBookingId($booking->booking->bookingid);
		$room->setBufferBefore((int) $_POST['buffer_before']);
		$room->setBufferAfter((int) $_POST['buffer_after']);

		// Set end date for cafe room
		if($booking->booking->cafe)
		{
			$start = $form->getItemByPostVar('cstart')->getDate();
			$end = clone $start;
			$end->increment(IL_CAL_DAY,1);

			$room->setStart($start);
			$room->setEnd($end);
		}
		else
		{
			$start = $form->getItemByPostVar('roomduration')->getStart();
			$end = $form->getItemByPostVar('roomduration')->getEnd();

			$room->setStart($start);
			$room->setEnd($end);
		}

		try {

			$con = new ilViteroBookingSoapConnector();
			$con->updateBooking($room, $this->object->getVGroupId());
			ilUtil::sendSuccess($GLOBALS['lng']->txt('settings_saved'), true);
			$this->ctrl->redirect($this,'showContent');
			return true;
		}
		catch(ilViteroConnectorException $e)
		{
			ilUtil::sendFailure($e->getViteroMessage(),true);
			$form->setValuesByPost();
			$GLOBALS['tpl']->setContent($form->getHTML());
		}
	}
}
?>