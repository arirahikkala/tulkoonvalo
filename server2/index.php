<?php // -*- PHP -*-
// Used in converting SQL datetime into timestamp
date_default_timezone_set('Europe/Helsinki');

require 'slim/Slim.php';
$app = new Slim();

$app->get('/data/:id/', function($id) { getObjectData($id); });
$app->get('/lights/:ids/', function($ids) { getLights($ids); });
$app->get('/children/:id/', function($id) { getChildren($id); });
$app->get('/allchildren/:id/', function($id) { getAllChildren($id); });
$app->post('/savesliders', 'saveSliders');
$app->post('/poll/', 'poll');
$app->get('/togglesliders/:ids/', function($ids) { toggleSliders($ids); });
$app->get('/programs/', 'getPrograms');
$app->post('/programs/', 'savePrograms');
$app->put('/programs/:id', function($id) { updateProgram($id); });
$app->delete('/programs/:id', function($id) { deleteProgram($id); });
$app->get('/groupsTree/:onlyGroups', function($onlyGroups) { getGroupsTree($onlyGroups); });
$app->get('/detectorsTree/', 'getDetectorsTree');
$app->get('/lightsTree/', 'getLightsTree');
$app->post('/groups', 'addGroup');
$app->post('/groups/:id/name', function($id) { renameGroup($id); });
$app->post('/groups/:id/parent', function($id) { moveGroup($id); });
$app->delete('/groups/:id', function($id) { removeGroup($id); });

$app->run();

// Remove groups from the admin view
function removeGroup($id) {
	// Get deleted group's children
	$sql = "select child_id from groups where parent_id=?";
	$children = dbExec($sql, array($id), 0);
	
	$sql = "select * from groups";
	$groups = dbExec($sql, array($id), 0);
	
	// Find out all children an remove them
	foreach ($children as $c) childrenRemove($c, $groups, $db);
	
	// Finally remove the parent
	$sql = "delete from light_activations where id=?";
	dbExec($sql, array($id));
	
	$sql = "delete from groups where child_id=?";
	dbExec($sql, array($id));

	$sql = "delete from lights where permanent_id=? and isGroup=1";
	dbExec($sql, array($id));
}

// Drill down to find children and remove them
function childrenRemove($c, $groups, $db) {
	foreach ($groups as $g) {
		if ($g->parent_id == $c->child_id)
			childrenRemove($g, $groups, $db);
	}
	
	// This removes the children from the lower end
	$sql = "delete from light_activations where id=?";
	dbExec($sql, array($c->child_id));

	$sql = "delete from groups where child_id=?";
	dbExec($sql, array($c->child_id));

	$sql = "delete from lights where permanent_id=? and isGroup=1";
	dbExec($sql, array($c->child_id));
}

// Move groups around in the admin view
function moveGroup($id) {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	
	// The parent must be group
	$newParent = $params->parent_id;
	$sql = "select isGroup from lights where permanent_id=?";
	$isGroup = dbExec($sql, array($newParent), 1);
	if ($isGroup && !$isGroup->isGroup) {
		print(json_encode($params));
		return;
	}
	// The group can be moved to the root level
	if ($newParent == -1) {
		$sql = "delete from groups where child_id=?";
		dbExec($sql, array($id));
	}
	else {
		// Group is moved inside a tree
		if ($params->only_move == 1) {
			$sql = "delete from groups where child_id=?";
			dbExec($sql, array($id));
		
			$sql = "insert into groups values (?,?)";
			dbExec($sql, array($id, $newParent));
		}
		else {
			$sql = "insert into groups values (?,?)";
			dbExec($sql, array($id, $newParent));
		}
	}
}

// Rename groups in the admin view
function renameGroup($id) {
	$params = json_decode(Slim::getInstance()->request()->getBody());

	// The renamed item must be a group
	$sql = "select isGroup from lights where permanent_id=?";
	$isGroup = dbExec($sql, array($id), 1)->isGroup;
	
	if ($isGroup == 1) {
		$sql = "update lights set name=? where permanent_id=?";
		dbExec($sql, array($params->name, $id));
	}
	else print(json_encode($params));
}

// Add new groups from the admin view
function addGroup() {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	
	$newID = rand();
	
	$sql = "insert into lights values (?,?,1,0)";
	dbExec($sql, array($params->name, $newID));

	if ($params->parent_id != -1) {
		$sql = "insert into groups values (?,?)";
		dbExec($sql, array($newID, $params->parent_id));
	}
	print($newID);
}

// Get one-level tree of detectors to the admin view
function getDetectorsTree($retJson=true) {
	$sql = "select permanent_id,name,detector_type from lights where isGroup=0 and detector_type!=0";
	$res = dbExec($sql, null, 0);

	$tree = array();
	foreach($res as $r) {
		if ($r->detector_type == 1) $dType = "detector_light";
		else $dType = "detector_motion";
	
		$newNode = array("data"=>$r->name, "attr"=>array("id"=>$r->permanent_id, "rel"=>$dType));
		$tree[] = $newNode;
	}
	if ($retJson) print(json_encode($tree));
	else return($tree);
}

// Get one-level tree of lights to the admin view
function getLightsTree($retJson=true) {
	$sql = "select permanent_id,name from lights where isGroup=0 and detector_type=0";
	$res = dbExec($sql, null, 0);

	$tree = array();
	foreach($res as $r) {
		$newNode = array("data"=>$r->name, "attr"=>array("id"=>$r->permanent_id, "rel"=>"light"));
		$tree[] = $newNode;
	}
	if ($retJson) print(json_encode($tree));
	else return($tree);
}

// Get the whole tree with groups, lights and detectors to the admin view
function getGroupsTree($onlyGroups=false) {
	$sql = "select name,permanent_id,parent_id,isGroup,detector_type from lights left join groups on (lights.permanent_id = groups.child_id)";
	$groups = dbExec($sql, null, 0);	
	
	// Loop only those items with no parents here
	$tree = array();
	foreach ($groups as $g) {
		if (!$g->parent_id && $g->isGroup) {
			$newChildren = childLoop($g, $groups, $onlyGroups);
			$tree[] = $newChildren;
		}
	}
	print(json_encode( array("data"=>"Ryhmät", "attr"=>array("id"=>-1, "rel"=>"root"), "children"=>$tree) ));
}

// Drill down the groups tree and get all the children
function childLoop($cGroup, $groups, $onlyGroups=false) {
	$newChild = array("data"=>$cGroup->name, "attr"=>array("id"=>$cGroup->permanent_id), "children"=>array());
	
	// Set the item type
	if ($cGroup->isGroup == 1) $childType = "group";//$newChild["attr"]["type"] = 0;
	else {
		switch ($cGroup->detector_type) {
			case 0: $childType = "light";
            	break;
			case 1: $childType = "detector_light";
            	break;
			case 2: $childType = "detector_motion";
            	break;
		}
	}
	$newChild["attr"]["rel"] = $childType;
	
	foreach ($groups as $g) {
		if ($g->parent_id == $cGroup->permanent_id) {
			$subChildren = childLoop($g, $groups);
			
			if ((!$onlyGroups) || ($onlyGroups && $g->isGroup == 1))
				array_push($newChild["children"], $subChildren);
		}
	}
	if (count($newChild["children"]) == 0)
		unset($newChild["children"]);
	
	return($newChild);
}

// Convenience function for all used DB statements
function dbExec($sql, $dbArray, $fetchType) {
    $db = getConnection();
	try {
		$stmt = $db->prepare($sql);
		$stmt->execute($dbArray);
		if ($fetchType == 0)
			return($stmt->fetchAll(PDO::FETCH_OBJ));
		else if ($fetchType == 1)
			return($stmt->fetch(PDO::FETCH_OBJ));
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
		return($e->getMessage());
	}
}

// Delete times from a program in the admin view
function deleteTime($id) {
	$sql = "delete from program_times where id=?";	
	dbExec($sql, array($id));
}

// Remove program level settings in the admin view
function deleteLevel($id) {
	$sql = "delete from program_levels where id=?";	
	dbExec($sql, array($id));
}

// Delete programs from the admin view
function deleteProgram($id) {
	$sql = "delete from programs where id=?";	
	dbExec($sql, array($id));
	
	$sql = "delete from program_times where program_id=?";	
	dbExec($sql, array($id));
	
	$sql = "delete from program_levels where program_id=?";	
	dbExec($sql, array($id));

	deleteProgramsParse();
	$programs=getPrograms(false);
	foreach ($programs as $target){
		$modifiedPrograms = checkProgramsOverlap($target, $target->id);
		$sql = "insert into programs_parse values (?,?,null,null,?,?,?,?,?,?,?)";
		foreach ($modifiedPrograms as $prog){		
			dbExec($sql, array(
				$prog["programID"], $prog["target_id"], $prog["weekdays"], 
				$prog["time_start"], $prog["time_end"], $prog["light_detector"], 
				$prog["motion_detector"], $prog["light_level"], $prog["motion_level"])
			);
		}
	}
}

// Update programs from the admin view
function updateProgram($id) {
	$params = json_decode(Slim::getInstance()->request()->getBody());

	// If errors found in form return
	$retArray = programValidation($params);	
	if ($retArray) {
		Slim::getInstance()->response()->status(400);
		print(json_encode($retArray));
		return;
	}
	
	$sql = "update programs set name=? where id=?";	
	dbExec($sql, array($params->name, $params->id));

	foreach ($params->times as $time) {	
		if ($time->allow_delete == 1)
			deleteTime($time->id);
		
		else if ($time->new_time) {
			$sql = "insert into program_times values (null,?,?,?,?,?,?)";	
			dbExec($sql, array(
					$id, $time->date_start, 
					$time->date_end, $time->weekdays,
					$time->time_start, $time->time_end)
			);
		}
		else {
			$sql = "update program_times set date_start=?, date_end=?, weekdays=?, time_start=?, time_end=? where id=?";	
			dbExec($sql, array(
					$time->date_start, 
					$time->date_end, $time->weekdays,
					$time->time_start, $time->time_end,
					$time->id)
			);
		}
	}
	
	foreach ($params->levels as $level) {
		if ($level->allow_delete == 1) {
			deleteLevel($level->id);}
			
		else if ($level->new_level) {
			$sql = "insert into program_levels values (null,?,?,?,?,?,?)";	
			dbExec($sql, array(
					$id, $level->target_id, 
					$level->light_detector, $level->motion_detector,
					$level->light_level, $level->motion_level)
			);
		}
		else {
			$sql = "update program_levels set target_id=?, light_detector=?, motion_detector=?, light_level=?, motion_level=? where id=?";	
			dbExec($sql, array(
					$level->target_id, 
					$level->light_detector, $level->motion_detector,
					$level->light_level, $level->motion_level,
					$level->id)
			);
		}
	}
    deleteProgramsParse();
    $programs=getPrograms(false);
	foreach ($programs as $target){
		$modifiedPrograms = checkProgramsOverlap($target, $target->id);
		$sql = "insert into programs_parse values (?,?,null,null,?,?,?,?,?,?,?)";
		foreach ($modifiedPrograms as $prog){		
			dbExec($sql, array(
				$prog["programID"], $prog["target_id"], $prog["weekdays"], 
				$prog["time_start"], $prog["time_end"], $prog["light_detector"], 
				$prog["motion_detector"], $prog["light_level"], $prog["motion_level"])
			);
		}
	}
}

// Validate values in programs being created or updated
function programValidation($params) {
	$errorMain = array();
	$errorTimes = array();
	$errorLevels = array();
	$retArray = array();
	
	$times = $params->times;
	$levels = $params->levels;
	
	$nameLen = strlen($params->name);
	
	// Generic errors with name and lack of time or level items
	if ($nameLen == 0 || $nameLen > 32)
		$errorMain["name"] = 0;
		
	if (count($times) == 0)
		$errorMain["times"] = 3;
		
	if (count($levels) == 0)
		$errorMain["levels"] = 4;
		
	// Check time values
	foreach ($params->times as $time) {
		if ($time->allow_delete) continue;
		
		if ($time->weekdays == "0000000")
			$errorTimes[$time->cid][] = 1;
			
		$timeFormatError = false;
		
		// Check time format
		foreach(array($time->time_start, $time->time_end) as $t) {
			// Check that the time format is correct
			if (strlen($t) != 5 || (int)substr($t,2,1) != ':') {
				$errorTimes[$time->cid][] = 2;
				break;
			}
			// Check each halve that the time range is correct
			$hours = true;
			foreach (array((int)substr($t,0,2), (int)substr($t,3,4)) as $tHalf) {
				if (!$hours && ($tHalf > 59 || $tHalf < 0)) {
					$errorTimes[$time->cid][] = 2;
					break;
				}
				else if ($hours && ($tHalf > 23 || $tHalf < 0)) {
					$errorTimes[$time->cid][] = 2;
					break;
				}
				$hours = false;
			}
		}
		// Start time must be before end time
		if (!$timeFormatError && strtotime($time->time_start) > strtotime($time->time_end))
			$errorTimes[$time->cid][] = 5;
	}

	// Check level settings
	$usedLevels = array();
	foreach ($params->levels as $level) {
		if ($level->allow_delete) continue;
		$usedLevels[$level->target_id][] = $level->cid;
	}
	
	// Check that same group isn't in the program twice
	foreach ($usedLevels as $group) {
		if (count($group) > 1) {
			foreach ($group as $cid)
				$errorLevels[$cid][] = 6;
		}
	}
	
	// Finally show errors if they exist
 	if (count($errorMain) || count($errorTimes) || count($errorLevels)) {
 		$retArray["main_errors"] = $errorMain;
  	$retArray["time_errors"] = $errorTimes;
 		$retArray["level_errors"] = $errorLevels;
 		return($retArray);
 	}
}

// Create new programs in the admin view
function savePrograms() {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	
	// If errors found in form return
	$retArray = programValidation($params);	
	if ($retArray) {
		Slim::getInstance()->response()->status(400);
		print(json_encode($retArray));
		return;
	}

	$times = $params->times;
	$levels = $params->levels;

	$sql = "insert into programs values (null,?)";
	dbExec($sql, array($params->name));
	$programID = $db->lastInsertId();

	$modifiedPrograms = checkProgramsOverlap($params, $programID);
	$sql = "insert into programs_parse values (?,?,null,null,?,?,?,?,?,?,?)";
	foreach ($modifiedPrograms as $prog){		
		dbExec($sql, array(
				$prog["programID"], $prog["target_id"], $prog["weekdays"], 
				$prog["time_start"], $prog["time_end"], $prog["light_detector"], 
				$prog["motion_detector"], $prog["light_level"], $prog["motion_level"])
		);
	}

	$sql = "insert into program_times values (null,?,?,?,?,?,?)";
	foreach ($params->times as $time) {
		dbExec($sql, array(
				$programID, $time->date_start, 
				$time->date_end, $time->weekdays,
				$time->time_start, $time->time_end)
		);
	}
	
	$sql = "insert into program_levels values (null,?,?,?,?,?,?)";	
	foreach ($params->levels as $level) {
		dbExec($sql, array(
				$programID, $level->target_id, 
				$level->light_detector, $level->motion_detector,
				$level->light_level, $level->motion_level)
		);
	}
	print($programID);
}

function getPrograms($retJson = true) {
	// Get plain programs
	$sql = "select * from programs";
	$programs = dbExec($sql, null, 0);
	
	// Get time items for each program
	$sql = "select * from program_times where program_id=?";
	
	foreach ($programs as $cProg) {
		$cid = $cProg->id;
		$times = dbExec($sql, array($cid), 0);

		foreach ($times as $cTime) {
            $cTime->time_start = substr($cTime->time_start,0,5);
            $cTime->time_end = substr($cTime->time_end,0,5);
            $cTime->new_time = false;
            $cTime->allow_delete = false;
            unset($cTime->program_id);
        }
        
        $cProg->times = $times;
	}
	
	// Get level items for each program
	$sql = "select program_levels.*,lights.name from program_levels
					left join lights on lights.permanent_id=program_levels.target_id
					where program_id=?";
	
	foreach ($programs as $cProg) {
		$cid = $cProg->id;
		$levels = dbExec($sql, array($cid), 0);

        foreach ($levels as $cLevel) {
            $cLevel->new_level = false;
            $cLevel->allow_delete = false;
            unset($cLevel->program_id);
        }
        $cProg->levels = $levels;
	}
	
	// We may or may not want JSON return
	if ($retJson) print(json_encode($programs));
	else return($programs);
}

// TODO: Commendaros del codos
function getProgramsParse() {
	$db=getConnection();
	$sql = "select * from programs_parse";
	$programs=dbExec($sql, array(), 0);
	return($programs);
}

// TODO: Commendaros del codos
function deleteProgramsParse() {
	$db=getConnection();
	$sql = "delete from programs_parse";	
	dbExec($sql, array());
}

// TODO: Commendaros del codos
function checkProgramsOverlap ($target, $targetID) {
	$programs2=getPrograms(false);
	$modifiedPrograms=array();
	$modifiedTarget=array(array("times" => $target->times, "levels" => $target->levels));

	if (count($programs2)==1 || getProgramsParse()==false){
		foreach ($target->times as $tTime){
			foreach ($target->levels as $level){
				array_push($modifiedPrograms, array("programID" => $targetID, 
				"target_id" => $level->target_id, "time_start" => $tTime->time_start, 
				"time_end" => $tTime->time_end, "weekdays" => $tTime->weekdays, 
				"light_detector" => $level->light_detector, "motion_detector" 
				=> $level->motion_detector, "light_level" => $level->light_level, 
				"motion_level" => $level->motion_level));
			}
		}
		return($modifiedPrograms);
	}

	$programs=getProgramsParse();
	deleteProgramsParse();

	foreach ($programs as $prog){
		foreach($modifiedTarget as $target){
			foreach($target["times"] as $tTime){
				$weekdays=$tTime->weekdays + $prog->weekdays + 30000000; //3 for not losing zeros at the beginning, removed later
				if(stripos($weekdays, "2")!==false) {
					if ((strtotime($prog->time_start) <= strtotime($tTime->time_start)) && 
					(strtotime($tTime->time_start) < strtotime($prog->time_end))) {
						if(strtotime($tTime->time_end) <= strtotime($prog->time_end)) {
							$levels_array=checkLights ($target["levels"], $prog);
							if (!empty($levels_array)){
								$allMods = modifyProgram($tTime, $prog, $weekdays, $levels_array, 1, $targetID, $prog->program_id);
								$targetMods = $allMods[count($allMods)-1];
								array_pop($allMods);
								$progMods = $allMods;
								foreach($progMods as $progMod)
									array_push($modifiedPrograms, $progMod);
								if(!empty($targetMods))
									array_push($modifiedTarget, $targetMods);
							}
						}else{
							$levels_array=checkLights ($target["levels"], $prog);
							if (!empty($levels_array)){
								$allMods = modifyProgram($tTime, $prog, $weekdays, $levels_array, 2, $targetID, $prog->program_id);
								$targetMods = $allMods[count($allMods)-1];
								array_pop($allMods);
								$progMods = $allMods;
								foreach($progMods as $progMod)
									array_push($modifiedPrograms, $progMod);
								if(!empty($targetMods))
									array_push($modifiedTarget, $targetMods);
							}
						}
					}else if((strtotime($prog->time_start) < strtotime($tTime->time_end)) && 
					(strtotime($tTime->time_end) <= strtotime($prog->time_end))) {
						$levels_array=checkLights ($target["levels"], $prog);
						if (!empty($levels_array)){
							$allMods = modifyProgram($tTime, $prog, $weekdays, $levels_array, 3, $targetID, $prog->program_id);
							$targetMods = $allMods[count($allMods)-1];
							array_pop($allMods);
							$progMods = $allMods;
							foreach($progMods as $progMod)
								array_push($modifiedPrograms, $progMod);
							if(!empty($targetMods))
								array_push($modifiedTarget, $targetMods);
						}
					}else if((strtotime($tTime->time_start) < strtotime($prog->time_start)) && 
					(strtotime($prog->time_end) < strtotime($tTime->time_end))) {
						$levels_array=checkLights ($target["levels"], $prog);
						if (!empty($levels_array)){
							$allMods = modifyProgram($tTime, $prog, $weekdays, $levels_array, 4, $targetID, $prog->program_id);
							$targetMods = $allMods[count($allMods)-1];
							array_pop($allMods);
							$progMods = $allMods;
							foreach($progMods as $progMod)
								array_push($modifiedPrograms, $progMod);
							if(!empty($targetMods))
								array_push($modifiedTarget, $targetMods);
						}
					}else{
						array_push($modifiedPrograms, array("programID" => $prog->program_id, 
						"target_id" => $prog->target_id, "time_start" => $prog->time_start, 
						"time_end" => $prog->time_end, "weekdays" => $prog->weekdays, 
						"light_detector" => $prog->light_detector, "motion_detector" 
						=> $prog->motion_detector, "light_level" => $prog->light_level, 
						"motion_level" => $prog->motion_level));
					}
				}else{
					array_push($modifiedPrograms, array("programID" => $prog->program_id, 
					"target_id" => $prog->target_id, "time_start" => $prog->time_start, 
					"time_end" => $prog->time_end, "weekdays" => $prog->weekdays, 
					"light_detector" => $prog->light_detector, "motion_detector" 
					=> $prog->motion_detector, "light_level" => $prog->light_level, 
					"motion_level" => $prog->motion_level));
				}
			}
		}
	}
	
	if (count($modifiedTarget)==1){
		foreach ($modifiedTarget[0]["times"] as $time){
			foreach ($modifiedTarget[0]["levels"] as $level){
				array_push($modifiedPrograms, array("programID" => $targetID, "target_id" => $level->target_id, "time_start" => $time->time_start, 
				"time_end" => $time->time_end, "weekdays" => $time->weekdays, "light_detector" => $level->light_detector, "motion_detector" 
				=> $level->motion_detector, "light_level" => $level->light_level, "motion_level" => $level->motion_level));
			}
		}
	}else{
		for ($i=1; $i<count($modifiedTarget); $i++){	//Don't use the original in $i=0, if there is any changes in modifiedTarget
			foreach($modifiedTarget[$i] as $targetMod){
				$time=$targetMod["times"];
				$level=$targetMod["levels"];
				array_push($modifiedPrograms, array("programID" => $targetMod["programID"], "target_id" => $level["target_id"], "time_start" => $time["time_start"], 
				"time_end" => $time["time_end"], "weekdays" => $time["weekdays"], "light_detector" => $level["light_detector"], "motion_detector" 
				=> $level["motion_detector"], "light_level" => $level["light_level"], "motion_level" => $level["motion_level"]));
			}
		}
	}

	return($modifiedPrograms);
}

function checkLights ($targetLevels, $prog) {
	$levels_array=array();

	foreach ($targetLevels as $level) {
		$target_id=$level->target_id;
		if ($prog->target_id==$target_id) {
			$matching_levels=array($level, $prog);
			array_push($levels_array, $matching_levels);
		}	
	} 
	return ($levels_array);
}

function modifyProgram ($time1, $prog, $weekdays, $levels_array, $type, $id1, $id2) {
	$modifiedPrograms=array();
	$modifiedTarget=array();
	$time2=array("time_start" => $prog->time_start, "time_end" => $prog->time_end, "weekdays" => $prog->weekdays);
	
	$days_together = array("1" , "2", "3");
	$days_overlap = array("0", "1", "");	//remove previously added 3
	$days_others = array("1", "0", "");
	$overlapDays = str_replace($days_together, $days_overlap, $weekdays);
	$otherDays1=str_replace($days_together, $days_others, ($time1->weekdays + $overlapDays + 30000000));
	$otherDays2=str_replace($days_together, $days_others, ($time2["weekdays"] + $overlapDays + 30000000));
	$levelToCompare="light_level";
	//$addedAlready=false;
	

	foreach ($levels_array as $level_pair){
		//If there's motion detector on in one of these two, we'll compare that.
		if (($level_pair[0]->motion_detector === true) || 
		($level_pair[1]->motion_detector === true))
			$levelToCompare="motion_level";

		switch($type){
			case 1:
				if ($level_pair[0]->$levelToCompare >= $level_pair[1]->$levelToCompare){
					$dimmer1 = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
					"time_end" => $time1->time_start, "weekdays" => $overlapDays, "light_detector" => $level_pair[1]->light_detector, "motion_detector" => 
					$level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>$level_pair[1]->motion_level);
					$dimmer2 = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time1->time_end, 
					"time_end" => $time2["time_end"], "weekdays" => $overlapDays, "light_detector" => $level_pair[1]->light_detector, "motion_detector" => 
					$level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>$level_pair[1]->motion_level);
					array_push($modifiedPrograms, $dimmer1, $dimmer2);
					if ($otherDays2 !== "0000000"){
						$dimmer3 = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
						"time_end" => $time2["time_end"], "weekdays" => $otherDays2, "light_detector" => $level_pair[1]->light_detector, 
						"motion_detector" => $level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>
						$level_pair[1]->motion_level);
						array_push($modifiedPrograms, $dimmer3);
					} 
				}else{
					$brighter = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
					"time_end" => $time2["time_end"], "weekdays" => $time2["weekdays"], "light_detector" => $level_pair[1]->light_detector, "motion_detector" 
					=> $level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>$level_pair[1]->motion_level);
					array_push($modifiedPrograms, $brighter);
					if ($otherDays1 !== "0000000"){
						$dimmer1 = array("programID" => $id1, "times" => array("time_start" => $time1->time_start, "time_end" => $time1->time_end, 
						"weekdays" => $otherDays1), "levels" => array("target_id" => $level_pair[0]->target_id, "light_detector" => 
						$level_pair[0]->light_detector, "motion_detector" => $level_pair[0]->motion_detector, "light_level"=> 
						$level_pair[0]->light_level, "motion_level"=> $level_pair[0]->motion_level));
						array_push($modifiedTarget, $dimmer1);
					}
				}
				break;
			case 2: 
				if ($level_pair[0]->$levelToCompare >= $level_pair[1]->$levelToCompare){
					$dimmer1 = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
					"time_end" => $time1->time_start, "weekdays" => $overlapDays, "light_detector" => $level_pair[1]->light_detector, "motion_detector" => 
					$level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>$level_pair[1]->motion_level);
					array_push($modifiedPrograms, $dimmer1);
					if ($otherDays2 !== "0000000"){
						$dimmer2 = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
						"time_end" => $time2["time_end"], "weekdays" => $otherDays2, "light_detector" => $level_pair[1]->light_detector, 
						"motion_detector" => $level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>
						$level_pair[1]->motion_level);
						array_push($modifiedPrograms, $dimmer2);
						}
				}else{
					$brighter = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
					"time_end" => $time2["time_end"], "weekdays" => $time2["weekdays"], "light_detector" => $level_pair[1]->light_detector, "motion_detector" 
					=> $level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>$level_pair[1]->motion_level);
					array_push($modifiedPrograms, $brighter);
					$dimmer1 = array("programID" => $id1, "times" => array("time_start" => $time2["time_end"], 
					"time_end" => $time1->time_end, "weekdays" => $overlapDays), "levels" => array("target_id" => $level_pair[0]->target_id, 
					"light_detector" => $level_pair[0]->light_detector, "motion_detector" => $level_pair[0]->motion_detector, "light_level"=> 
					$level_pair[0]->light_level, "motion_level"=>$level_pair[0]->motion_level));
					array_push($modifiedTarget, $dimmer1);
					if ($otherDays1 !== "0000000"){
						$dimmer2 = array("programID" => $id1, "times" => array("time_start" => $time1->time_start, 
						"time_end" => $time1->time_end, "weekdays" => $otherDays1), "levels" => array("target_id" => 
						$level_pair[0]->target_id, "light_detector" => $level_pair[0]->light_detector, "motion_detector" => 
						$level_pair[0]->motion_detector, "light_level"=>$level_pair[0]->light_level, "motion_level"=> $level_pair[0]->motion_level));
						array_push($modifiedTarget, $dimmer2);
					}
				}
				break;
			case 3: 
				if ($level_pair[0]->$levelToCompare >= $level_pair[1]->$levelToCompare){
					$dimmer1 = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time1->time_end, 
					"time_end" => $time2["time_end"], "weekdays" => $overlapDays, "light_detector" => $level_pair[1]->light_detector, "motion_detector" => 
					$level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>$level_pair[1]->motion_level);
					array_push($modifiedPrograms, $dimmer1);
					if ($otherDays2 !== "0000000"){
						$dimmer2 = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
						"time_end" => $time2["time_end"], "weekdays" => $otherDays2, "light_detector" => $level_pair[1]->light_detector, 
						"motion_detector" => $level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>
						$level_pair[1]->motion_level);
						array_push($modifiedPrograms, $dimmer2);
					}
				}else{
					$brighter = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
					"time_end" => $time2["time_end"], "weekdays" => $time2["weekdays"], "light_detector" => $level_pair[1]->light_detector, "motion_detector" 
					=> $level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>$level_pair[1]->motion_level);
					array_push($modifiedPrograms, $brighter);
					$dimmer1 = array("programID" => $id1, "times" => array("time_start" => $time1->time_start, 
					"time_end" => $time2["time_start"], "weekdays" => $overlapDays), "levels" => array("target_id" => $level_pair[0]->target_id, 
					"light_detector" => $level_pair[0]->light_detector, "motion_detector" => $level_pair[0]->motion_detector, 
					"light_level"=>$level_pair[0]->light_level, "motion_level"=>$level_pair[0]->motion_level));
					array_push($modifiedTarget, $dimmer1);
					if ($otherDays1 !== "0000000"){
						$dimmer2 = array("programID" => $id1, "times" => array("time_start" => $time1->time_start, 
						"time_end" => $time1->time_end, "weekdays" => $otherDays1), "levels" => array("target_id" => 
						$level_pair[0]->target_id,"light_detector" => $level_pair[0]->light_detector, "motion_detector" => 
						$level_pair[0]->motion_detector, "light_level"=>$level_pair[0]->light_level, "motion_level" => $level_pair[0]->motion_level));
						array_push($modifiedTarget, $dimmer2);
					}
				}
				break;

			case 4:
				if ($level_pair[0]->$levelToCompare >= $level_pair[1]->$levelToCompare){
					if ($otherDays2 !== "0000000")
						$dimmer1 = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
						"time_end" => $time2["time_end"], "weekdays" => $otherDays2, "light_detector" => $level_pair[1]->light_detector, 
						"motion_detector" => $level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>
						$level_pair[1]->motion_level);
					array_push($modifiedPrograms, $dimmer1);
				}else{
					$brighter = array("programID" => $id2, "target_id" => $level_pair[1]->target_id, "time_start" => $time2["time_start"], 
					"time_end" => $time2["time_end"], "weekdays" => $time2["weekdays"], "light_detector" => $level_pair[1]->light_detector, "motion_detector" 
					=> $level_pair[1]->motion_detector, "light_level"=>$level_pair[1]->light_level, "motion_level"=>$level_pair[1]->motion_level);
					$dimmer1 = array("programID" => $id1, "times" => array("time_start" => $time1->time_start, 
					"time_end" => $time2["time_start"], "weekdays" => $overlapDays), "levels" => array("target_id" => $level_pair[0]->target_id, 
					"light_detector" => $level_pair[0]->light_detector, "motion_detector" => $level_pair[0]->motion_detector, "light_level" => 
					$level_pair[0]->light_level, "motion_level"=>$level_pair[0]->motion_level));
					$dimmer2 = array("programID" => $id1, "times" => array("time_start" => $time2["time_end"], "time_end" => $time1->time_end, 
					"weekdays" => $overlapDays), "levels" => array("target_id" => $level_pair[0]->target_id, "light_detector" => 
					$level_pair[0]->light_detector, "motion_detector" => $level_pair[0]->motion_detector, "light_level"=>$level_pair[0]->light_level, 
					"motion_level"=>$level_pair[0]->motion_level));
					array_push($modifiedPrograms, $brighter);
					array_push($modifiedTarget, $dimmer1, $dimmer2);
					if ($otherDays1 !== "0000000"){
						$dimmer3 = array("programID" => $id1, "times" => array("time_start" => $time1->time_start, 
						"time_end" => $time1->time_end, "weekdays" => $otherDays1), "levels" => array("target_id" => $level_pair[0]->target_id, 
						"light_detector" => $level_pair[0]->light_detector, "motion_detector" => $level_pair[0]->motion_detector, "light_level"=>
						$level_pair[0]->light_level, "motion_level"=> $level_pair[0]->motion_level));
						array_push($modifiedTarget, $dimmer3);
					}
				}
				break;
		}
	}
	array_push($modifiedPrograms, $modifiedTarget);
	return($modifiedPrograms);
}

// Long polling used by the sliders
function poll() {
    $params = json_decode(Slim::getInstance()->request()->getBody());
    $ids_array = $params->ids;
    $values_array = $params->values;
    $timers_array = $params->timers;
    $enableds_array = $params->enableds;
	
	// TODO: All below to while loop could be prettified
	if ((count($ids_array) != count($values_array)) ||
	(count($values_array) != count($timers_array)) ||
	(count($timers_array) != count($enableds_array)) )
		return;
		
	// Create an array from the given values
	$origLevels = array();
	$counter = 0;
	foreach ($ids_array as $cid) {
		$origLevels[$cid] = array();
		
		$value = (int)$values_array[$counter];
		if ($value < 0)
			$value = 0;
		else if ($value > 100)
			$value = 100;
		$origLevels[$cid]["current_level"] = $value;
		
		$timer = (int)$timers_array[$counter];
		if ($timer < 0)
			$timer = 0;
		else if ($timer > 86400)
			$timer = 86400;
		$origLevels[$cid]["timer_last"] = $timer;
		
		$isEnabled = $enableds_array[$counter];
		if ($isEnabled == "false")
			$isEnabled = false;
		else
			$isEnabled = true;	
		$origLevels[$cid]["enabled"] = $isEnabled;
		
		$counter++;
	}
	$time = time();
	
	// Loop for 60 seconds at a time with small pauses in between
	while((time() - $time) < 60) {
		$getLevels = getLevels($ids_array);
		$newLevels = $getLevels[0];
		$timerArray = $getLevels[1];
		$retArray = array();
		
		// If original values don't match with values in DB, return new values
		foreach($newLevels as $id => $carray) {
			if ($newLevels[$id] != $origLevels[$id]) {
					$retArray[$id] = $carray;
					$retArray[$id]["timer"] = $timerArray[$id];
			}
		}
		if ($retArray) {
			print(json_encode($retArray));
			break;
		}
		usleep(1000000);
	}
}

// Get new values from the DB for long polling
function getLevels($ids_array) {
	// TODO: Implode ids
	$sql = "select * from light_activations where id=?";
	$retArray = array();
	$timerArray = array();
	try {
		foreach ($ids_array as $cid) {
			$level = dbExec($sql, array($cid), 1);
			
			// There may not be DB results or the timer may be 0
			if ($level && strtotime($level->ends_at)-time() > 0) {
				$retArray[$level->id] = array();
					$retArray[$level->id]["timer_last"] = strtotime($level->ends_at)-strtotime($level->activated_at);
					$retArray[$level->id]["current_level"] = $level->current_level;	
					$retArray[$level->id]["enabled"] = true;
					$timerArray[$level->id] = strtotime($level->ends_at)-time();
					continue;
			}
			$retArray[$cid] = array();
			$retArray[$cid]["enabled"] = false;
			$retArray[$cid]["current_level"] = 0;
			$retArray[$cid]["timer_last"] = 0;
			$timerArray[$cid] = strtotime($level->ends_at)-time();
		}
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
	return(array($retArray, $timerArray));
}

// Save sliders when user does something
function saveSliders() {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	print(json_encode($params));
	$ids = $params->ids;
	$value = $params->value;
	$timer = $params->timer;
	
	$currentTime = time();
	
	// Check timer 24h and "negative" limits
	if ($timer < 0)
		$timer = 0;
	else if ($timer > 86400)
		$timer = 86400;
	$endTime = $currentTime + $timer;
	
	// Check the slider value
	$value = (int)$value;
	if ($value < 0)
		$value = 0;
	else if ($value > 100)
		$value = 100;
		
	$ids_array = preg_split ("/,/", $ids);
	
    // Insert the slider values into DB
	$sql = "insert into light_activations values (?,?,?,?) on duplicate key update current_level=?, activated_at=from_unixtime(?), ends_at=from_unixtime(?)";
	
	foreach ($ids_array as $cid)
	    dbExec($sql, array($value,$currentTime,$endTime,$cid,$value,$currentTime,$endTime), 1);
}

// Get light/group data and children to create new sliders
function getObjectData ($ids) {	
	$ids_array = preg_split ("/,/", $ids);
	$retArray = array();

	foreach ($ids_array as $id) {		
		$sql = "select * from lights left join light_activations on lights.permanent_id=light_activations.id where permanent_id=?";
		$lights = dbExec($sql, array($id), 0);
		
		if (count($lights) == 0) return;
		$lights = $lights[0];
		
		// Get time remaining
		$timer = strtotime($lights -> ends_at)-time();
		if ($timer < 0)
			$timer = 0;
		$lights -> timer = $timer;
		
		$children = getChildren($id);
		$childrenIds = array();
		
		if (count($children)) {
			// Extract children IDs
			foreach ($children as $child) {
				$childrenIds[] = $child->permanent_id;
			}
		}
		$lights -> children = $childrenIds;
		$lights -> all_children = getAllChildren($id);
		
		$timerFull = strtotime($lights->ends_at)-time();
		if ($timerFull <= 0) $timerFull = 0;
		$lights -> timer_full = $timerFull;
		
		$retArray[] = $lights;
	}
	print(json_encode($retArray));
}

// Get children for a light
function getChildren ($id) {
	$sql = "select * from lights where permanent_id in (select child_id from groups where parent_id=?) and detector_type=0";
	$children = dbExec($sql, array($id), 0);
	return ($children);
}

// Get all children in all levels for a light
function getAllChildren ($id) {
	$allChildren = array();
	$tempChildren[] = $id;

	while (count($tempChildren) > 0) {
		$cid = array_pop($tempChildren);
		$cChildren = getChildren($cid);
		
		foreach ($cChildren as $c) {
			$allChildren[] = $c -> permanent_id;
			$tempChildren[] = $c -> permanent_id;
		}
	}
	return($allChildren);
}

function getConnection() {
	require ("config.php");
	$dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);	
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbh;
}

?>
