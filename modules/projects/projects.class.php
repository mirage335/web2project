<?php /* $Id$ $URL$ */
if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}

/**
 *	@package web2Project
 *	@subpackage modules
 *	@version $Revision$
 */
require_once ($AppUI->getSystemClass('libmail'));
require_once ($AppUI->getSystemClass('w2p'));
require_once ($AppUI->getLibraryClass('PEAR/Date'));
require_once ($AppUI->getModuleClass('tasks'));
require_once ($AppUI->getModuleClass('companies'));
require_once ($AppUI->getModuleClass('departments'));
require_once ($AppUI->getModuleClass('files'));

// project statii
$pstatus = w2PgetSysVal('ProjectStatus');
$ptype = w2PgetSysVal('ProjectType');

$ppriority_name = w2PgetSysVal('ProjectPriority');
$ppriority_color = w2PgetSysVal('ProjectPriorityColor');

$priority = array();
foreach ($ppriority_name as $key => $val) {
	$priority[$key]['name'] = $val;
}
foreach ($ppriority_color as $key => $val) {
	$priority[$key]['color'] = $val;
}

/*
// kept for reference
$priority = array(
-1 => array(
'name' => 'low',
'color' => '#E5F7FF'
),
0 => array(
'name' => 'normal',
'color' => ''//#CCFFCA
),
1 => array(
'name' => 'high',
'color' => '#FFDCB3'
),
2 => array(
'name' => 'immediate',
'color' => '#FF887C'
)
);
*/

/**
 * The Project Class
 */
class CProject extends CW2pObject {
	var $project_id = null;
	var $project_company = null;
	var $project_department = null;
	var $project_name = null;
	var $project_short_name = null;
	var $project_owner = null;
	var $project_url = null;
	var $project_demo_url = null;
	var $project_start_date = null;
	var $project_end_date = null;
	var $project_actual_end_date = null;
	var $project_status = null;
	var $project_percent_complete = null;
	var $project_color_identifier = null;
	var $project_description = null;
	var $project_target_budget = null;
	var $project_actual_budget = null;
	var $project_creator = null;
	var $project_active = null;
	var $project_private = null;
	var $project_departments = null;
	var $project_contacts = null;
	var $project_priority = null;
	var $project_type = null;
	var $project_parent = null;
	var $project_original_parent = null;
	var $project_location = null;

	function CProject() {
		$this->CW2pObject('projects', 'project_id');
	}

	function check() {
		// ensure changes of state in checkboxes is captured
		$this->project_active = intval($this->project_active);
		$this->project_private = intval($this->project_private);

		$this->project_target_budget = $this->project_target_budget ? $this->project_target_budget : 0.00;
		$this->project_actual_budget = $this->project_actual_budget ? $this->project_actual_budget : 0.00;

		// Make sure project_short_name is the right size (issue for languages with encoded characters)
		if (strlen($this->project_short_name) > 10) {
			$this->project_short_name = substr($this->project_short_name, 0, 10);
		}
		if (empty($this->project_end_date)) {
			$this->project_end_date = null;
		}
		return null; // object is ok
	}

	function load($oid = null, $strip = true) {
		$result = parent::load($oid, $strip);
		if ($result && $oid) {
			$working_hours = (w2PgetConfig('daily_working_hours') ? w2PgetConfig('daily_working_hours') : 8);

			$q = new DBQuery;
			$q->addTable('projects');
			$q->addQuery('SUM(t1.task_duration * t1.task_percent_complete * IF(t1.task_duration_type = 24, ' . $working_hours . ', t1.task_duration_type)) / SUM(t1.task_duration * IF(t1.task_duration_type = 24, ' . $working_hours . ', t1.task_duration_type)) AS project_percent_complete');
			$q->addJoin('tasks', 't1', 'projects.project_id = t1.task_project', 'inner');
			$q->addWhere('project_id = ' . $oid . ' AND t1.task_id = t1.task_parent');
			$this->project_percent_complete = $q->loadResult();
		}
		return $result;
	}
	// overload canDelete
	function canDelete(&$msg, $oid = null) {
		// TODO: check if user permissions are considered when deleting a project
		global $AppUI;
		$perms = &$AppUI->acl();

		return $perms->checkModuleItem('projects', 'delete', $oid);

		// NOTE: I uncommented the dependencies check since it is
		// very anoying having to delete all tasks before being able
		// to delete a project.

		/*
		$tables[] = array( 'label' => 'Tasks', 'name' => 'tasks', 'idfield' => 'task_id', 'joinfield' => 'task_project' );
		// call the parent class method to assign the oid
		return CW2pObject::canDelete( $msg, $oid, $tables );
		*/
	}

	function delete() {
		$this->load($this->project_id);
		addHistory('projects', $this->project_id, 'delete', $this->project_name, $this->project_id);
		$q = new DBQuery;
		$q->addTable('tasks');
		$q->addQuery('task_id');
		$q->addWhere('task_project = ' . (int)$this->project_id);
		$tasks_to_delete = $q->loadColumn();
		$q->clear();
		foreach ($tasks_to_delete as $task_id) {
			$q->setDelete('user_tasks');
			$q->addWhere('task_id =' . $task_id);
			$q->exec();
			$q->clear();
			$q->setDelete('task_dependencies');
			$q->addWhere('dependencies_req_task_id =' . (int)$task_id);
			$q->exec();
			$q->clear();
		}
		$q->setDelete('tasks');
		$q->addWhere('task_project =' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		$q = new DBQuery;
		$q->addTable('files');
		$q->addQuery('file_id');
		$q->addWhere('file_project = ' . (int)$this->project_id);
		$files_to_delete = $q->loadColumn();
		$q->clear();
		foreach ($files_to_delete as $file_id) {
			$file = new CFile();
			$file->file_id = $file_id;
			$file->file_project = (int)$this->project_id;
			$file->delete();
		}
		// remove the project-contacts and project-departments map
		$q->setDelete('project_contacts');
		$q->addWhere('project_id =' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		$q->setDelete('project_departments');
		$q->addWhere('project_id =' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		$q->setDelete('projects');
		$q->addWhere('project_id =' . (int)$this->project_id);

		if (!$q->exec()) {
			$result = db_error();
		} else {
			$result = null;
		}
		$q->clear();
		return $result;
	}

	/**	Import tasks from another project
	 *
	 *	@param	int		Project ID of the tasks come from.
	 *	@return	bool	
	 **/
	function importTasks($from_project_id) {

		// Load the original
		$origProject = new CProject();
		$origProject->load($from_project_id);
		$q = new DBQuery;
		$q->addTable('tasks');
		$q->addQuery('task_id');
		$q->addWhere('task_project =' . (int)$from_project_id);
		$tasks = array_flip($q->loadColumn());
		$q->clear();

		$origDate = new CDate($origProject->project_start_date);

		$destDate = new CDate($this->project_start_date);

		$timeOffset = $origDate->dateDiff($destDate);
		if ($origDate->compare($origDate, $destDate) > 0) {
			$timeOffset = -1 * $timeOffset;
		}

		// Dependencies array
		$deps = array();

		// Copy each task into this project and get their deps
		foreach ($tasks as $orig => $void) {
			$objTask = new CTask();
			$objTask->load($orig);
			$destTask = $objTask->copy($this->project_id);
			$tasks[$orig] = $destTask;
			$deps[$orig] = $objTask->getDependencies();
		}

		// Fix record integrity
		foreach ($tasks as $old_id => $newTask) {

			// Fix parent Task
			// This task had a parent task, adjust it to new parent task_id
			if ($newTask->task_id != $newTask->task_parent)
				$newTask->task_parent = $tasks[$newTask->task_parent]->task_id;

			// Fix task start date from project start date offset
			$origDate->setDate($newTask->task_start_date);
			//$destDate->setDate ($origDate->getTime() + $timeOffset , DATE_FORMAT_UNIXTIME );
			$origDate->addDays($timeOffset);
			$destDate = $origDate;
			//$destDate = $destDate->next_working_day( );
			$newTask->task_start_date = $destDate->format(FMT_DATETIME_MYSQL);

			// Fix task end date from start date + work duration
			//$newTask->calc_task_end_date();
			if (!empty($newTask->task_end_date) && $newTask->task_end_date != '0000-00-00 00:00:00') {
				$origDate->setDate($newTask->task_end_date);
				//$destDate->setDate ($origDate->getTime() + $timeOffset , DATE_FORMAT_UNIXTIME );
				$origDate->addDays($timeOffset);
				$destDate = $origDate;
				//$destDate = $destDate->next_working_day();
				$newTask->task_end_date = $destDate->format(FMT_DATETIME_MYSQL);
			}

			// Dependencies
			if (!empty($deps[$old_id])) {
				$oldDeps = explode(',', $deps[$old_id]);
				// New dependencies array
				$newDeps = array();
				foreach ($oldDeps as $dep) {
					$newDeps[] = $tasks[$dep]->task_id;
				}

				// Update the new task dependencies
				$csList = implode(',', $newDeps);
				$newTask->updateDependencies($csList);
			} // end of update dependencies
			$newTask->store();
		} // end Fix record integrity

	} // end of importTasks

	/**
	 **	Overload of the w2PObject::getAllowedRecords 
	 **	to ensure that the allowed projects are owned by allowed companies.
	 **
	 **	@author	handco <handco@sourceforge.net>
	 **	@see	w2PObject::getAllowedRecords
	 **/

	function getAllowedRecords($uid, $fields = '*', $orderby = '', $index = null, $extra = null, $table_alias = '') {
		$oCpy = new CCompany();

		$aCpies = $oCpy->getAllowedRecords($uid, 'company_id, company_name');
		if (count($aCpies)) {
			$buffer = '(project_company IN (' . implode(',', array_keys($aCpies)) . '))';

			if (!$extra['from'] && !$extra['join']) {
				$extra['join'] = 'project_departments';
				$extra['on'] = 'projects.project_id = project_departments.project_id';
			} elseif ($extra['from'] != 'project_departments' && !$extra['join']) {
				$extra['join'] = 'project_departments';
				$extra['on'] = 'projects.project_id = project_departments.project_id';
			}
			//Department permissions
			$oDpt = new CDepartment();
			$aDpts = $oDpt->getAllowedRecords($uid, 'dept_id, dept_name');
			if (count($aDpts)) {
				$dpt_buffer = '(department_id IN (' . implode(',', array_keys($aDpts)) . ') OR department_id IS NULL)';
			} else {
				// There are no allowed departments, so allow projects with no department.
				$dpt_buffer = '(department_id IS NULL)';
			}

			if ($extra['where'] != '') {
				$extra['where'] = $extra['where'] . ' AND ' . $buffer . ' AND ' . $dpt_buffer;
			} else {
				$extra['where'] = $buffer . ' AND ' . $dpt_buffer;
			}
		} else {
			// There are no allowed companies, so don't allow projects.
			if ($extra['where'] != '') {
				$extra['where'] = $extra['where'] . ' AND 1 = 0 ';
			} else {
				$extra['where'] = '1 = 0';
			}
		}
		return parent::getAllowedRecords($uid, $fields, $orderby, $index, $extra, $table_alias);

	}

	function getAllowedSQL($uid, $index = null) {
		$oCpy = new CCompany();
		$where = $oCpy->getAllowedSQL($uid, 'project_company');

		$oDpt = new CDepartment();
		$where += $oDpt->getAllowedSQL($uid, 'dept_id');

		$project_where = parent::getAllowedSQL($uid, $index);
		return array_merge($where, $project_where);
	}

	function setAllowedSQL($uid, &$query, $index = null, $key = null) {
		$oCpy = new CCompany;
		parent::setAllowedSQL($uid, $query, $index, $key);
		$oCpy->setAllowedSQL($uid, $query, ($key ? $key . '.' : '').'project_company');
		//Department permissions
		$oDpt = new CDepartment();
		$query->leftJoin('project_departments', '', 'pr.project_id = project_departments.project_id');
		$oDpt->setAllowedSQL($uid, $query, 'project_departments.department_id');
	}

	/**
	 *	Overload of the w2PObject::getDeniedRecords 
	 *	to ensure that the projects owned by denied companies are denied.
	 *
	 *	@author	handco <handco@sourceforge.net>
	 *	@see	w2PObject::getAllowedRecords
	 */
	function getDeniedRecords($uid) {
		$aBuf1 = parent::getDeniedRecords($uid);

		$oCpy = new CCompany();
		// Retrieve which projects are allowed due to the company rules
		$aCpiesAllowed = $oCpy->getAllowedRecords($uid, 'company_id,company_name');

		//Department permissions
		$oDpt = new CDepartment();
		$aDptsAllowed = $oDpt->getAllowedRecords($uid, 'dept_id,dept_name');

		$q = new DBQuery;
		$q->addTable('projects');
		$q->addQuery('projects.project_id');
		$q->addJoin('project_departments', 'pd', 'pd.project_id = projects.project_id');

		if (count($aCpiesAllowed)) {
			if ((array_search('0', $aCpiesAllowed)) === false) {
				//If 0 (All Items of a module) are not permited then just add the allowed items only
				$q->addWhere('NOT (project_company IN (' . implode(',', array_keys($aCpiesAllowed)) . '))');
			} else {
				//If 0 (All Items of a module) are permited then don't add a where clause so the user is permitted to see all
			}
		} else {
			//if the user is not allowed any company then lets shut him off
			$q->addWhere('0=1');
		}

		if (count($aDptsAllowed)) {
			if ((array_search('0', $aDptsAllowed)) === false) {
				//If 0 (All Items of a module) are not permited then just add the allowed items only
				$q->addWhere('NOT (department_id IN (' . implode(',', array_keys($aDptsAllowed)) . '))');
			} else {
				//If 0 (All Items of a module) are permited then don't add a where clause so the user is permitted to see all
				$q->addWhere('NOT (department_id IS NULL)');
			}
		} else {
			//If 0 (All Items of a module) are permited then don't add a where clause so the user is permitted to see all
			$q->addWhere('NOT (department_id IS NULL)');
		}

		/*If (count($aCpiesAllowed)) {
		$q->addWhere('NOT (project_company IN (' . implode (',', array_keys($aCpiesAllowed)) . '))');
		}*/
		//Department permissions
		/*If (count($aDptsAllowed)) {
		$q->addWhere('NOT (department_id IN (' . implode (',', array_keys($aDptsAllowed)) . '))');
		} else {
		$q->addWhere('NOT (department_id IS NULL)');
		}*/
		$aBuf2 = $q->loadColumn();
		$q->clear();

		return array_merge($aBuf1, $aBuf2);

	}
	function getAllowedProjectsInRows($userId) {
		$q = new DBQuery;
		$q->addQuery('pr.project_id, project_status, project_name, project_description, project_short_name');
		$q->addTable('projects', 'pr');
		$q->addOrder('project_short_name');
		$this->setAllowedSQL($userId, $q, null, 'pr');
		$allowedProjectRows = $q->exec();

		return $allowedProjectRows;
	}
	function getAssignedProjectsInRows($userId) {
		$q = new DBQuery;

		$q->addQuery('pr.project_id, project_status, project_name, project_description, project_short_name');
		$q->addTable('projects');
		$q->addJoin('tasks', 't', 't.task_project = pr.project_id');
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = t.task_id');
		$q->addWhere('ut.user_id = ' . (int)$userId);
		$q->addGroup('pr.project_id');
		$q->addOrder('project_name');
		$this->setAllowedSQL($userId, $q, null, 'pr');
		$allowedProjectRows = $q->exec();

		return $allowedProjectRows;
	}

	/** Retrieve tasks with latest task_end_dates within given project
	 * @param int Project_id
	 * @param int SQL-limit to limit the number of returned tasks
	 * @return array List of criticalTasks
	 */
	function getCriticalTasks($project_id = null, $limit = 1) {
		$project_id = !empty($project_id) ? $project_id : $this->project_id;
		$q = new DBQuery;
		$q->addTable('tasks');
		$q->addWhere('task_project = ' . (int)$project_id . ' AND task_end_date IS NOT NULL AND task_end_date <>  \'0000-00-00 00:00:00\'');
		$q->addOrder('task_end_date DESC');
		$q->setLimit($limit);

		return $q->loadList();
	}

	function store() {

		$this->w2PTrimAll();

		$msg = $this->check();
		if ($msg) {
			return get_class($this) . '::store-check failed - ' . $msg;
		}

		if ($this->project_id) {
			$q = new DBQuery;
			$ret = $q->updateObject('projects', $this, 'project_id', false);
			$q->clear();
			addHistory('projects', $this->project_id, 'update', $this->project_name, $this->project_id);
		} else {
			$q = new DBQuery;
			$ret = $q->insertObject('projects', $this, 'project_id');
			$q->clear();
			addHistory('projects', $this->project_id, 'add', $this->project_name, $this->project_id);
		}

		//split out related departments and store them seperatly.
		$q = new DBQuery;
		$q->setDelete('project_departments');
		$q->addWhere('project_id=' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		if ($this->project_departments) {
			$departments = explode(',', $this->project_departments);
			foreach ($departments as $department) {
				$q->addTable('project_departments');
				$q->addInsert('project_id', $this->project_id);
				$q->addInsert('department_id', $department);
				$q->exec();
				$q->clear();
			}
		}

		//split out related contacts and store them seperatly.
		$q->setDelete('project_contacts');
		$q->addWhere('project_id=' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		if ($this->project_contacts) {
			$contacts = explode(',', $this->project_contacts);
			foreach ($contacts as $contact) {
				if ($contact) {
					$q->addTable('project_contacts');
					$q->addInsert('project_id', $this->project_id);
					$q->addInsert('contact_id', $contact);
					$q->exec();
					$q->clear();
				}
			}
		}

		if (!$ret) {
			return get_class($this) . '::store failed ' . db_error();
		} else {
			return null;
		}

	}

	function notifyOwner($isNotNew) {
		global $AppUI, $w2Pconfig, $locale_char_set;

		$mail = new Mail;

		if (intval($isNotNew)) {
			$mail->Subject("Project Updated: $this->project_name ", $locale_char_set);
		} else {
			$mail->Subject("Project Submitted: $this->project_name ", $locale_char_set);
		}

		$q = new DBQuery;
		$q->addTable('projects', 'p');
		$q->addQuery('p.project_id');
		$q->addQuery('oc.contact_email as owner_email, oc.contact_first_name as owner_first_name, oc.contact_last_name as owner_last_name');
		$q->leftJoin('users', 'o', 'o.user_id = p.project_owner');
		$q->leftJoin('contacts', 'oc', 'oc.contact_id = o.user_contact');
		$q->addWhere('p.project_id = ' . (int)$this->project_id);
		$users = $q->loadList();
		$q->clear();

		if (count($users)) {
			if (intval($isNotNew)) {
				$body = $AppUI->_('Project') . ": $this->project_name Has Been Updated Via Project Manager. You can view the Project by clicking: ";
			} else {
				$body = $AppUI->_('Project') . ": $this->project_name Has Been Submitted Via Project Manager. You can view the Project by clicking: ";
			}
			$body .= "\n" . $AppUI->_('URL') . ':     ' . w2PgetConfig('base_url') . '/index.php?m=projects&a=view&project_id=' . $this->project_id;
			$body .= "\n\n(You are receiving this email because you are the owner to this project)";
			$body .= "\n\n" . $AppUI->_('Description') . ':' . "\n$this->project_description";
			if (intval($isNotNew)) {
				$body .= "\n\n" . $AppUI->_('Updater') . ': ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			} else {
				$body .= "\n\n" . $AppUI->_('Creator') . ': ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			}

			if ($this->_message == 'deleted') {
				$body .= "\n\nProject " . $this->project_name . ' was ' . $this->_message . ' by ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			}

			$mail->Body($body, isset($GLOBALS['locale_char_set']) ? $GLOBALS['locale_char_set'] : '');
		}
		if ($mail->ValidEmail($users[0]['owner_email'])) {
			$mail->To($users[0]['owner_email'], true);
			$mail->Send();
		}

		return '';
	}

	function notifyContacts($isNotNew) {
		global $AppUI, $w2Pconfig, $locale_char_set;

		$mail = new Mail;

		if (intval($isNotNew)) {
			$mail->Subject("Project Updated: $this->project_name ", $locale_char_set);
		} else {
			$mail->Subject("Project Submitted: $this->project_name ", $locale_char_set);
		}

		$q = new DBQuery;
		$q->addTable('project_contacts', 'pc');
		$q->addQuery('pc.project_id, pc.contact_id');
		$q->addQuery('c.contact_email as contact_email, c.contact_first_name as contact_first_name, c.contact_last_name as contact_last_name');
		$q->addJoin('contacts', 'c', 'c.contact_id = pc.contact_id', 'inner');
		$q->addWhere('pc.project_id = ' . (int)$this->project_id);
		$users = $q->loadList();
		$q->clear();

		if (count($users)) {
			if (intval($isNotNew)) {
				$body = $AppUI->_('Project') . ": $this->project_name Has Been Updated Via Project Manager. You can view the Project by clicking: ";
			} else {
				$body = $AppUI->_('Project') . ": $this->project_name Has Been Submitted Via Project Manager. You can view the Project by clicking: ";
			}
			$body .= "\n" . $AppUI->_('URL') . ':     ' . w2PgetConfig('base_url') . '/index.php?m=projects&a=view&project_id=' . $this->project_id;
			$body .= "\n\n(You are receiving this message because you are a contact or assignee for this Project)";
			$body .= "\n\n" . $AppUI->_('Description') . ':' . "\n$this->project_description";
			if (intval($isNotNew)) {
				$body .= "\n\n" . $AppUI->_('Updater') . ': ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			} else {
				$body .= "\n\n" . $AppUI->_('Creator') . ': ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			}

			if ($this->_message == 'deleted') {
				$body .= "\n\nProject " . $this->project_name . ' was ' . $this->_message . ' by ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			}

			$mail->Body($body, isset($GLOBALS['locale_char_set']) ? $GLOBALS['locale_char_set'] : '');
		}
		foreach ($users as $row) {
			if ($mail->ValidEmail($row['contact_email'])) {
				$mail->To($row['contact_email'], true);
				$mail->Send();
			}
		}
		return '';
	}
}

/* The next lines of code have resided in projects/index.php before
** and have been moved into this 'encapsulated' function
** for reusability of that central code.
**
** @date 20060225
** @responsible gregorerhardt
**
** E.g. this code is used as well in a tab for the admin/viewuser site
**
** @mixed user_id 	userId as filter for tasks/projects that are shown, if nothing is specified, 
current viewing user $AppUI->user_id is used.
*/

function projects_list_data($user_id = false) {
	global $AppUI, $addPwOiD, $buffer, $company, $company_id, $company_prefix, $deny, $department, $dept_ids, $w2Pconfig, $orderby, $orderdir, $projects, $tasks_critical, $tasks_problems, $tasks_sum, $tasks_summy, $tasks_total, $owner, $projectTypeId, $search_text;

	$addProjectsWithAssignedTasks = $AppUI->getState('addProjWithTasks') ? $AppUI->getState('addProjWithTasks') : 0;

	// get any records denied from viewing
	$obj = new CProject();
	$deny = $obj->getDeniedRecords($AppUI->user_id);

	// Let's delete temproary tables
	$q = new DBQuery;
	// Let's delete support tables data
	$q->setDelete('tasks_sum');
	$q->exec();
	$q->clear();

	$q->setDelete('tasks_total');
	$q->exec();
	$q->clear();

	$q->setDelete('tasks_summy');
	$q->exec();
	$q->clear();

	$q->setDelete('tasks_critical');
	$q->exec();
	$q->clear();

	$q->setDelete('tasks_problems');
	$q->exec();
	$q->clear();

	$q->setDelete('tasks_users');
	$q->exec();
	$q->clear();

	// support task sum table
	// by Pablo Roca (pabloroca@mvps.org)
	// 16 August 2003
	$working_hours = ($w2Pconfig['daily_working_hours'] ? $w2Pconfig['daily_working_hours'] : 8);

	// GJB: Note that we have to special case duration type 24 and this refers to the hours in a day, NOT 24 hours
	$q->addInsertSelect('tasks_sum');
	$q->addTable('tasks');
	$q->addQuery('task_project, COUNT(distinct tasks.task_id) AS total_tasks, 
			SUM(task_duration * task_percent_complete * IF(task_duration_type = 24, ' . $working_hours . ', task_duration_type))/
			SUM(task_duration * IF(task_duration_type = 24, ' . $working_hours . ', task_duration_type)) AS project_percent_complete, SUM(task_duration * IF(task_duration_type = 24, ' . $working_hours . ', task_duration_type)) AS project_duration');
	if ($user_id) {
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = tasks.task_id');
		$q->addWhere('ut.user_id = ' . (int)$user_id);
	}
	$q->addWhere('tasks.task_id = tasks.task_parent');
	$q->addGroup('task_project');
	$tasks_sum = $q->exec();
	$q->clear();

	// support task total table
	$q->addInsertSelect('tasks_total');
	$q->addTable('tasks');
	$q->addQuery('task_project, COUNT(distinct tasks.task_id) AS total_tasks');
	if ($user_id) {
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = tasks.task_id');
		$q->addWhere('ut.user_id = ' . (int)$user_id);
	}
	$q->addGroup('task_project');
	$tasks_total = $q->exec();
	$q->clear();

	// support My Tasks
	$q->addInsertSelect('tasks_summy');
	$q->addTable('tasks');
	$q->addQuery('task_project, COUNT(distinct task_id) AS my_tasks');
	if ($user_id) {
		$q->addWhere('task_owner = ' . (int)$user_id);
	} else {
		$q->addWhere('task_owner = ' . (int)$AppUI->user_id);
	}
	$q->addGroup('task_project');
	$tasks_summy = $q->exec();
	$q->clear();

	// support critical tasks
	$q->addInsertSelect('tasks_critical');
	$q->addTable('tasks', 't');
	$q->addQuery('task_project, task_id AS critical_task, task_end_date AS project_actual_end_date');
	$sq = new DBQuery;
	$sq->addTable('tasks', 'st');
	$sq->addQuery('MAX(task_end_date)');
	$sq->addWhere('st.task_project = t.task_project');
	$q->addWhere('task_end_date = (' . $sq->prepare() . ')');
	$q->addGroup('task_project');
	$tasks_critical = $q->exec();
	$q->clear();

	// support task problem logs
	$q->addInsertSelect('tasks_problems');
	$q->addTable('tasks');
	$q->addQuery('task_project, task_log_problem');
	$q->addJoin('task_log', 'tl', 'tl.task_log_task = task_id', 'inner');
	$q->addWhere('task_log_problem = 1');
	$q->addGroup('task_project');
	$tasks_problems = $q->exec();
	$q->clear();

	if ($addProjectsWithAssignedTasks) {
		// support users tasks
		$q->addInsertSelect('tasks_users');
		$q->addTable('tasks');
		$q->addQuery('task_project');
		$q->addQuery('ut.user_id');
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = tasks.task_id');
		if ($user_id) {
			$q->addWhere('ut.user_id = ' . (int)$user_id);
		}
		$q->addOrder('task_end_date DESC');
		$q->addGroup('task_project');
		$tasks_users = $q->exec();
		$q->clear();
	}

	// add Projects where the Project Owner is in the given department
	if ($addPwOiD && isset($department)) {
		$owner_ids = array();
		$q->addTable('users');
		$q->addQuery('user_id');
		$q->addJoin('contacts', 'c', 'c.contact_id = user_contact', 'inner');
		$q->addWhere('c.contact_department = ' . (int)$department);
		$owner_ids = $q->loadColumn();
		$q->clear();
	}

	if (isset($department)) {
		//If a department is specified, we want to display projects from the department, and all departments under that, so we need to build that list of departments
		$dept_ids = array();
		$q->addTable('departments');
		$q->addQuery('dept_id, dept_parent');
		$q->addOrder('dept_parent,dept_name');
		$rows = $q->loadList();
		addDeptId($rows, $department);
		$dept_ids[] = $department;
	}
	$q->clear();

	// retrieve list of records
	// modified for speed
	// by Pablo Roca (pabloroca@mvps.org)
	// 16 August 2003
	// get the list of permitted companies
	$obj = new CCompany();
	$companies = $obj->getAllowedRecords($AppUI->user_id, 'companies.company_id,companies.company_name', 'companies.company_name');
	if (count($companies) == 0) {
		$companies = array();
	}

	$q->addTable('projects', 'pr');
	$q->addQuery('pr.project_id, project_status, project_color_identifier, project_type, project_name, project_description, project_duration, project_parent, project_original_parent, 
		project_start_date, project_end_date, project_color_identifier, project_company, company_name, company_description, project_status,
		project_priority, tc.critical_task, tc.project_actual_end_date, tp.task_log_problem, tt.total_tasks, tsy.my_tasks,
		ts.project_percent_complete, user_username, project_active');
	$q->addQuery('CONCAT(ct.contact_first_name, \' \', ct.contact_last_name) AS owner_name');
//	$q->addJoin('companies', 'com', 'projects.project_company = com.company_id');
//	$q->addJoin('project_departments', 'pd', 'pd.project_id = projects.project_id');
//	$q->addJoin('departments', 'dep', 'pd.department_id = dep.dept_id');
	$q->addJoin('users', 'u', 'pr.project_owner = u.user_id');
	$q->addJoin('contacts', 'ct', 'ct.contact_id = u.user_contact');
	$q->addJoin('tasks_critical', 'tc', 'pr.project_id = tc.task_project');
	$q->addJoin('tasks_problems', 'tp', 'pr.project_id = tp.task_project');
	$q->addJoin('tasks_sum', 'ts', 'pr.project_id = ts.task_project');
	$q->addJoin('tasks_total', 'tt', 'pr.project_id = tt.task_project');
	$q->addJoin('tasks_summy', 'tsy', 'pr.project_id = tsy.task_project');
	if ($addProjectsWithAssignedTasks)
		$q->addJoin('tasks_users', 'tu', 'pr.project_id = tu.task_project');
	// DO we have to include the above DENY WHERE restriction, too?
	//$q->addJoin('', '', '');
	//if (isset($department)) {
	//	$q->addJoin('project_departments', 'pd', 'pd.project_id = projects.project_id');
	//}
	if (!isset($department) && $company_id && !$addPwOiD) {
		$q->addWhere('pr.project_company = ' . (int)$company_id);
	}
	if ($projectTypeId > -1) {
		$q->addWhere('pr.project_type = ' . (int)$projectTypeId);
	}
	if (isset($department) && !$addPwOiD) {
		$q->addWhere('project_departments.department_id in ( ' . implode(',', $dept_ids) . ' )');
	}
	if ($user_id && $addProjectsWithAssignedTasks) {
		$q->addWhere('(tu.user_id = ' . (int)$user_id . ' OR pr.project_owner = ' . (int)$user_id . ' )');
	} elseif ($user_id) {
		$q->addWhere('pr.project_owner = ' . (int)$user_id);
	}
	if ($owner > 0) {
		$q->addWhere('pr.project_owner = ' . (int)$owner);
	}
	if (trim($search_text)) {
		$q->addWhere('pr.project_name LIKE \'%' . $search_text . '%\' OR pr.project_description LIKE \'%' . $search_text . '%\'');
	}
	// Show Projects where the Project Owner is in the given department
	if ($addPwOiD && !empty($owner_ids)) {
		$q->addWhere('pr.project_owner IN (' . implode(',', $owner_ids) . ')');
	}

	$q->addGroup('pr.project_id');
	$q->addOrder($orderby . ' ' .$orderdir);
//	$obj->setAllowedSQL($AppUI->user_id, $q, null, 'co');
	$prj = new CProject();
	$prj->setAllowedSQL($AppUI->user_id, $q, null, 'pr');
	$dpt = new CDepartment();
//	$dpt->setAllowedSQL($AppUI->user_id, $q);
	$projects = $q->loadList();

	// get the list of permitted companies
	$companies = arrayMerge(array('0' => $AppUI->_('All')), $companies);
	$company_array = $companies;

	//get list of all departments, filtered by the list of permitted companies.
	$q->clear();
	$q->addTable('companies');
	$q->addQuery('company_id, company_name, dep.*');
	$q->addJoin('departments', 'dep', 'companies.company_id = dep.dept_company');
	$q->addOrder('company_name,dept_parent,dept_name');
	$obj->setAllowedSQL($AppUI->user_id, $q);
	$dpt->setAllowedSQL($AppUI->user_id, $q);
	$rows = $q->loadList();

	//display the select list
	$buffer = '<select name="department" id="department" onChange="document.pickCompany.submit()" class="text" style="width: 200px;">';
	$company = '';

	foreach ($company_array as $key => $c_name) {
		$buffer .= '<option value="' . $company_prefix . $key . '" style="font-weight:bold;"' . ($company_id == $key ? 'selected="selected"' : '') . '>' . $c_name . '</option>' . "\n";
		foreach ($rows as $row) {
			if ($row['dept_parent'] == 0) {
				if ($key == $row['company_id']) {
					if ($row['dept_parent'] != null) {
						showchilddept($row);
						findchilddept($rows, $row['dept_id']);
					}
				}
			}
		}
	}
	$buffer .= '</select>';

}

function shownavbar_links_prj($xpg_totalrecs, $xpg_pagesize, $xpg_total_pages, $page) {

	global $AppUI, $m, $tab;
	$xpg_break = false;
	$xpg_prev_page = $xpg_next_page = 0;

	$s = '<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>';

	if ($xpg_totalrecs > $xpg_pagesize) {
		$xpg_prev_page = $page - 1;
		$xpg_next_page = $page + 1;
		// left buttoms
		if ($xpg_prev_page > 0) {
			$s .= '<td align="left" width="15%"><a href="./index.php?m=' . $m . '&amp;tab=' . $tab . '&amp;page=1"><img src="' . w2PfindImage('navfirst.gif') . '" border="0" Alt="First Page"></a>&nbsp;&nbsp;';
			$s .= '<a href="./index.php?m=' . $m . '&amp;tab=' . $tab . '&amp;page=' . $xpg_prev_page . '"><img src="' . w2PfindImage('navleft.gif') . '" border="0" Alt="Previous page (' . $xpg_prev_page . ')"></a></td>';
		} else {
			$s .= '<td width="15%">&nbsp;</td>';
		}

		// central text (files, total pages, ...)
		$s .= '<td align="center" width="70%">';
		$s .= $xpg_totalrecs . ' ' . $AppUI->_('Record(s)') . ' ' . $xpg_total_pages . ' ' . $AppUI->_('Page(s)');

		// Page numbered list, up to 30 pages
		$s .= ' [ ';

		for ($n = $page > 16 ? $page - 16 : 1; $n <= $xpg_total_pages; $n++) {
			if ($n == $page) {
				$s .= '<b>' . $n . '</b></a>';
			} else {
				$s .= '<a href="./index.php?m=' . $m . '&amp;tab=' . $tab . '&amp;page=' . $n . '">' . $n . '</a>';
			}
			if ($n >= 30 + $page - 15) {
				$xpg_break = true;
				break;
			} elseif ($n < $xpg_total_pages) {
				$s .= ' | ';
			}
		}

		if (!isset($xpg_break)) { // are we supposed to break ?
			if ($n == $page) {
				$s .= '<' . $n . '</a>';
			} else {
				$s .= '<a href="./index.php?m=' . $m . '&amp;tab=' . $tab . '&amp;page=' . $xpg_total_pages . '">' . $n . '</a>';
			}
		}
		$s .= ' ] ';
		$s .= '</td>';
		// right buttoms
		if ($xpg_next_page <= $xpg_total_pages) {
			$s .= '<td align="right" width="15%"><a href="./index.php?m=' . $m . '&amp;tab=' . $tab . '&amp;page=' . $xpg_next_page . '"><img src="' . w2PfindImage('navright.gif') . '" border="0" Alt="Next Page (' . $xpg_next_page . ')"></a>&nbsp;&nbsp;';
			$s .= '<a href="./index.php?m=' . $m . '&amp;tab=' . $tab . '&amp;page=' . $xpg_total_pages . '"><img src="' . w2PfindImage('navlast.gif') . '" border="0" Alt="Last Page"></a></td>';
		} else {
			$s .= '<td width="15%">&nbsp;</td></tr>';
		}
	} else { // or we dont have any files..
		$s .= '<td align="center">';
		if ($xpg_next_page > $xpg_total_pages) {
			$s .= $xpg_sqlrecs . ' ' . $m . ' ';
		}
		$s .= '</td></tr>';
	}
	$s .= '</table>';
	echo $s;
}

function getProjects() {
	global $AppUI;
	$st_projects = array(0 => '');
	$q = new DBQuery();
	$q->addTable('projects');
	$q->addQuery('project_id, project_name, project_parent');
	$q->addOrder('project_name');
	$st_projects = $q->loadHashList('project_id');
	reset_project_parents($st_projects);
	return $st_projects;
}

function reset_project_parents(&$projects) {
	foreach ($projects as $key => $project) {
		if ($project['project_id'] == $project['project_parent'])
			$projects[$key][2] = '';
	}
}

//This kludgy function echos children projects as threads
function show_st_project(&$a, $level = 0) {
	global $st_projects_arr;
	$st_projects_arr[] = array($a, $level);
}

function find_proj_child(&$tarr, $parent, $level = 0) {
	$level = $level + 1;
	$n = count($tarr);
	for ($x = 0; $x < $n; $x++) {
		if ($tarr[$x]['project_parent'] == $parent && $tarr[$x]['project_parent'] != $tarr[$x]['project_id']) {
			show_st_project($tarr[$x], $level);
			find_proj_child($tarr, $tarr[$x]['project_id'], $level);
		}
	}
}

function getStructuredProjects($original_project_id = 0, $project_status = -1, $active_only = false) {
	global $AppUI, $st_projects_arr;
	$st_projects = array(0 => '');
	$q = new DBQuery();
	$q->addTable('projects');
	$q->addJoin('companies', '', 'projects.project_company = company_id', 'inner');
	$q->addJoin('project_departments', 'pd', 'pd.project_id = projects.project_id');
	$q->addJoin('departments', 'dep', 'pd.department_id = dep.dept_id');
	$q->addQuery('projects.project_id, project_name, project_parent');
	if ($original_project_id) {
		$q->addWhere('project_original_parent = ' . (int)$original_project_id);
	}
	if ($project_status >= 0) {
		$q->addWhere('project_status = ' . (int)$project_status);
	}
	if ($active_only) {
		$q->addWhere('project_active = 1');
	}
	$q->addOrder('project_name');

	$obj = new CCompany();
	$obj->setAllowedSQL($AppUI->user_id, $q);
	$dpt = new CDepartment();
	$dpt->setAllowedSQL($AppUI->user_id, $q);

	$st_projects = $q->loadList();
	$tnums = count($st_projects);
	for ($i = 0; $i < $tnums; $i++) {
		$st_project = $st_projects[$i];
		if (($st_project['project_parent'] == $st_project['project_id'])) {
			show_st_project($st_project);
			find_proj_child($st_projects, $st_project['project_id']);
		}
	}
}

/**
 * getProjectIndex() gets the key nr of a project record within an array of projects finding its primary key within the records so that you can call that array record to get the projects data
 *
 * @param mixed $arraylist array list of project elements to search
 * @param mixed $project_id project id to search for
 * @return int returns the array key of the project record in the array list or false if not found 
 */
function getProjectIndex($arraylist, $project_id) {
	$result = false;
	foreach ($arraylist as $key => $data) {
		if ($data['project_id'] == $project_id) {
			return $key;
		}
	}
	return $result;
}
?>