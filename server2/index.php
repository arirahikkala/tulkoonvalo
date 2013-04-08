<?php // -*- PHP -*-

require 'slim/Slim.php';

$app = new Slim();

$app->get('/data/:id/', function($id) { getObjectData($id); });
$app->get('/lights/:ids/', function($ids) { getLights($ids); });
$app->get('/newlights/:ids/', function($ids) { newGetLights($ids); });
$app->get('/children/:id/', function($id) { getChildren($id); });
$app->get('/allchildren/:id/', function($id) { getAllChildren($id); });
$app->post('/savesliders', 'saveSliders');
$app->get('/poll/:ids/:values/:timers/:enableds', function($ids, $values, $timers, $enableds) { poll($ids, $values, $timers, $enableds); });
$app->get('/togglesliders/:ids/', function($ids) { toggleSliders($ids); });
//$app->post('/lights', 'addGroup');
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

function removeGroup($id) {
	$db = getConnection();

	// Get deleted group's children
	$sql = "select child_id from groups where parent_id=?";
	$children = dbExec($db, $sql, array($id), 0);
	
	$sql = "select * from groups";
	$groups = dbExec($db, $sql, array($id), 0);
	
	// Find out all children an remove them
	foreach ($children as $c)
		childLoopID($c, $groups, $db);
	
	// Remove the parent
	$sql = "delete from light_activations where id=?";
	dbExec($db, $sql, array($id));
	
	$sql = "delete from groups where child_id=?";
	dbExec($db, $sql, array($id));

	$sql = "delete from lights where permanent_id=? and isGroup=1";
	dbExec($db, $sql, array($id));
}

function childLoopID($c, $groups, $db) {
	foreach ($groups as $g) {
		if ($g->parent_id == $c->child_id)
			childLoopID($g, $groups, $db);
	}
	
	// This removes the children from the lower end
	$sql = "delete from light_activations where id=?";
	dbExec($db, $sql, array($c->child_id));

	$sql = "delete from groups where child_id=?";
	dbExec($db, $sql, array($c->child_id));

	$sql = "delete from lights where permanent_id=? and isGroup=1";
	dbExec($db, $sql, array($c->child_id));
}

function moveGroup($id) {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	$db = getConnection();
	
	// The parent must be group
	$newParent = $params->parent_id;
	$sql = "select isGroup from lights where permanent_id=?";
	$isGroup = dbExec($db, $sql, array($newParent), 1);
	if ($isGroup && !$isGroup->isGroup) {
		print(json_encode($params));
		return;
	}
	
	if ($newParent == -1) {
		$sql = "delete from groups where child_id=?";
		dbExec($db, $sql, array($id));
	}
	else {
		// Moved inside the tree, delete the original and insert a new one
		if ($params->only_move == 1) {
			$sql = "delete from groups where child_id=?";
			dbExec($db, $sql, array($id));
		
			$sql = "insert into groups values (?,?)";
			dbExec($db, $sql, array($id, $newParent));
		}
		else {
			$sql = "insert into groups values (?,?)";
			dbExec($db, $sql, array($id, $newParent));
		}
	}
}

function renameGroup($id) {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	$db = getConnection();
	
	// The renamed item must be a group
	$sql = "select isGroup from lights where permanent_id=?";
	$isGroup = dbExec($db, $sql, array($id), 1)->isGroup;
	
	if ($isGroup == 1) {
		$sql = "update lights set name=? where permanent_id=?";
		dbExec($db, $sql, array($params->name, $id));
	}
	else print(json_encode($params));
}

function addGroup() {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	$db = getConnection();
	
	// TODO: Allow random ID?
	$newID = rand();
	
	$sql = "insert into lights values (?,?,1,0)";
	dbExec($db, $sql, array($params->name, $newID));

	if ($params->parent_id != -1) {
		$sql = "insert into groups values (?,?)";
		dbExec($db, $sql, array($newID, $params->parent_id));
	}
	print($newID);
}

function getDetectorsTree($retJson=true) {
	$db = getConnection();
	
	$sql = "select permanent_id,name,detector_type from lights where isGroup=0 and detector_type!=0";
	$res = dbExec($db, $sql, null, 0);

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

function getLightsTree($retJson=true) {
	$db = getConnection();

	$sql = "select permanent_id,name from lights where isGroup=0 and detector_type=0";
	$res = dbExec($db, $sql, null, 0);

	$tree = array();
	foreach($res as $r) {
		$newNode = array("data"=>$r->name, "attr"=>array("id"=>$r->permanent_id, "rel"=>"light"));
		$tree[] = $newNode;
	}
	if ($retJson) print(json_encode($tree));
	else return($tree);
}

function getGroupsTree($onlyGroups=false) {
	$db = getConnection();
	$sql = "select name,permanent_id,parent_id,isGroup,detector_type from lights left join groups on (lights.permanent_id = groups.child_id)";
	$groups = dbExec($db, $sql, null, 0);	
	
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

// Drill down the groups tree and eventually return back up with all children
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

// Convenience function several DB statements
// TODO: Make everyone use this
function dbExec($db, $sql, $dbArray, $fetchType=-1) {
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

function deleteTime($id) {
	$db = getConnection();
	
	$sql = "delete from program_times where id=?";	
	dbExec($db, $sql, array($id));
}

function deleteLevel($id) {
	$db = getConnection();
	
	$sql = "delete from program_levels where id=?";	
	dbExec($db, $sql, array($id));
}

function deleteProgram($id) {
	$db = getConnection();
	
	$sql = "delete from programs where id=?";	
	dbExec($db, $sql, array($id));
	
	$sql = "delete from program_times where program_id=?";	
	dbExec($db, $sql, array($id));
	
	$sql = "delete from program_levels where program_id=?";	
	dbExec($db, $sql, array($id));

	deleteProgramsParse();
	$programs=getPrograms(false);
	foreach ($programs as $target){
		//$levels=$target->levels;
		$modifiedPrograms = checkProgramsOverlap($target, $target->id);
		$sql = "insert into programs_parse values (?,?,null,null,?,?,?,?,?,?,?)";
		foreach ($modifiedPrograms as $prog){		
			dbExec($db, $sql, array(
				$prog["programID"], $prog["target_id"], $prog["weekdays"], 
				$prog["time_start"], $prog["time_end"], $prog["light_detector"], 
				$prog["motion_detector"], $prog["light_level"], $prog["motion_level"])
			);
		}
	}
}

function updateProgram($id) {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	/*
	$paramsObj = new ArrayObject($params);
	$validParams = $paramsObj->getArrayCopy();
	print(json_encode($validParams));
	// Remove items to be removed from the checked list
	$counter = 0;
	foreach ($validParams->times as $time) {
		if ($time->allow_delete == 1) {
			unset($validParams->times[$counter]);
		}
		$counter++;
	}
	$counter = 0;
	foreach ($validParams->levels as $level) {
		if ($level->allow_delete == 1) {
			unset($validParams->levels[$counter]);
		}
		$counter++;
	}
	*/
	// If errors found in form return
	$retArray = programValidation($params);	
	if ($retArray) {
		Slim::getInstance()->response()->status(400);
		print(json_encode($retArray));
		return;
	}
	
	$db = getConnection();
	
	$sql = "update programs set name=? where id=?";	
	dbExec($db, $sql, array($params->name, $params->id));

	foreach ($params->times as $time) {	
		if ($time->allow_delete == 1)
			deleteTime($time->id);
		
		else if ($time->new_time) {
			$sql = "insert into program_times values (null,?,?,?,?,?,?)";	
			dbExec($db, $sql, array(
					$id, $time->date_start, 
					$time->date_end, $time->weekdays,
					$time->time_start, $time->time_end)
			);
		}
		else {
			$sql = "update program_times set date_start=?, date_end=?, weekdays=?, time_start=?, time_end=? where id=?";	
			dbExec($db, $sql, array(
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
			dbExec($db, $sql, array(
					$id, $level->target_id, 
					$level->light_detector, $level->motion_detector,
					$level->light_level, $level->motion_level)
			);
		}
		else {
			$sql = "update program_levels set target_id=?, light_detector=?, motion_detector=?, light_level=?, motion_level=? where id=?";	
			dbExec($db, $sql, array(
					$level->target_id, 
					$level->light_detector, $level->motion_detector,
					$level->light_level, $level->motion_level,
					$level->id)
			);
		}
	}
}

function programValidation($params) {
	$errorMain = array();
	$errorTimes = array();
	$errorLevels = array();
	$retArray = array();
	
	$times = $params->times; // Must not be empty
	$levels = $params->levels; // Must not be empty
	
	$nameLen = strlen($params->name);
	
	// TODO: Form needs testing with lengths etc.
	if ($nameLen == 0 || $nameLen > 32)
		$errorMain["name"] = 0;
		
	if (count($times) == 0)
		$errorMain["times"] = 3;
		
	if (count($levels) == 0)
		$errorMain["levels"] = 4;
		
	// TODO: Additional sanitation here for all values
	
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
			// Check each halve of times
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
		if (!$timeFormatError && strtotime($time->time_start) > strtotime($time->time_end))
			$errorTimes[$time->cid][] = 5;
	}
	// TODO: Check that it's a group used in a program
	// TODO: Level must be in the range of 0...100

	$usedLevels = array();
	foreach ($params->levels as $level) {
		if ($level->allow_delete) continue;
		$usedLevels[$level->target_id][] = $level->cid;
	}
	
	// Check that same group isn't there twice
	foreach ($usedLevels as $group) {
		if (count($group) > 1) {
			foreach ($group as $cid)
				$errorLevels[$cid][] = 6;
		}
	}
	
 	if (count($errorMain) || count($errorTimes) || count($errorLevels)) {
 		$retArray["main_errors"] = $errorMain;
  	$retArray["time_errors"] = $errorTimes;
 		$retArray["level_errors"] = $errorLevels;
 		return($retArray);
 	}
}

// TODO: Use this for editing. If ID is not null update value
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
	
	$db = getConnection();

	$sql = "insert into programs values (null,?)";
	dbExec($db, $sql, array($params->name));
	$programID = $db->lastInsertId();

	$modifiedPrograms = checkProgramsOverlap($params, $programID);
	$sql = "insert into programs_parse values (?,?,null,null,?,?,?,?,?,?,?)";
	foreach ($modifiedPrograms as $prog){		
		dbExec($db, $sql, array(
				$prog["programID"], $prog["target_id"], $prog["weekdays"], 
				$prog["time_start"], $prog["time_end"], $prog["light_detector"], 
				$prog["motion_detector"], $prog["light_level"], $prog["motion_level"])
		);
	}

	// TODO: These common statements could be put into their own functions
	$sql = "insert into program_times values (null,?,?,?,?,?,?)";
	foreach ($params->times as $time) {
		dbExec($db, $sql, array(
				$programID, $time->date_start, 
				$time->date_end, $time->weekdays,
				$time->time_start, $time->time_end)
		);
	}
	
	$sql = "insert into program_levels values (null,?,?,?,?,?,?)";	
	foreach ($params->levels as $level) {
		dbExec($db, $sql, array(
				$programID, $level->target_id, 
				$level->light_detector, $level->motion_detector,
				$level->light_level, $level->motion_level)
		);
	}
	print($programID);
	// TODO: Return to main page with a success message
	// TODO: Fix being unable to edit from other than mainpage tab (e.g. when reload page)
}

function getPrograms($retJson = true) {

	// Get the programs first
	$sql = "select * from programs";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$programs = $stmt->fetchAll (PDO::FETCH_OBJ);
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
	
	// TODO: Refactor this SQL mess?
	// Get times for each program
	$sql = "select * from program_times where program_id=?";
	foreach ($programs as $cProg) {
		$cid = $cProg->id;
		try {
			$stmt = $db->prepare($sql);
			$stmt->execute(array($cid));
			$times = $stmt->fetchAll (PDO::FETCH_OBJ);
			
			foreach ($times as $cTime) {
				$cTime->time_start = substr($cTime->time_start,0,5);
				$cTime->time_end = substr($cTime->time_end,0,5);
				$cTime->new_time = false;
				$cTime->allow_delete = false;
				unset($cTime->program_id);
			}
			
			$cProg->times = $times;
		}
		catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
	}
	
	$sql = "select * from program_levels where program_id=?";
	foreach ($programs as $cProg) {
		$cid = $cProg->id;
		try {
			$stmt = $db->prepare($sql);
			$stmt->execute(array($cid));
			$levels = $stmt->fetchAll (PDO::FETCH_OBJ);
			
			foreach ($levels as $cLevel) {
				$cLevel->new_level = false;
				$cLevel->allow_delete = false;
				unset($cLevel->program_id);
			}
			$cProg->levels = $levels;
		}
		catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
	}
	if ($retJson){
		print(json_encode($programs));
	}else{
		return($programs);
	}
}

function getProgramsParse() {
	$db=getConnection();
	$sql = "select * from programs_parse";
	$programs=dbExec($db, $sql, array(), 0);
	return($programs);
}

function deleteProgramsParse() {
	$db=getConnection();
	$sql = "delete from programs_parse";	
	dbExec($db, $sql, array());
}

/*function getGhost ($id) {
	$programs=getProgramsParse();

	$time = getdate();
	$todaysPrograms=array();

	$today=$time["wday"]-1;
	if ($today==-1)
		$today=6;
	
	foreach ($programs as $prog) {
		if ($prog->target_id==$id){
			$weekdays=$prog->weekdays;
			if (substr($weekdays, $today, 1)=="1"){
				if ((strtotime($prog->time_start) <= $time[0]) && 
				($time[0] < strtotime($prog->time_end))) {
					if ($prog->motion_detector==1)
						return ($prog->motion_level);
					else
						return ($prog->light_level);
				}
			}
		}					
	}
	return(0);
}*/

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
							print(json_encode($levels_array));print($prog->program_id);
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
		print(json_encode($modifiedTarget));
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

	print("  here1  ");print(json_encode($modifiedPrograms));
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

	print($levelToCompare);

		switch($type){
			case 1:
				if ($level_pair[0]->$levelToCompare >= $level_pair[1]->$levelToCompare){
					/*if (!$addedAlready){
						$brighter = array("programID" => $id1, "target_id" => $level_pair[0]->target_id, "times" => 
						array( "time_start" => $time1->time_start, "time_end" => $time1->time_end, "weekdays" => 
						$time1->weekdays, "light_detector" => $level_pair[0]->light_detector, "motion_detector"
						=> $level_pair[0]->motion_detector, "light_level"=>$level_pair[0]->light_level, "motion_level"=>$level_pair[0]->motion_level);
						$addedAlready=true;
						array_push($modifiedPrograms, $brighter);
					}*/
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
					/*if (!$addedAlready){
						$brighter = array("programID" => $id1, "target_id" => $level_pair[0]->target_id, "time_start" => $time1->time_start, 
						"time_end" => $time1->time_end, "weekdays" => $time1->weekdays, "light_detector" => $level_pair[0]->light_detector, "motion_detector" 
						=> $level_pair[0]->motion_detector, "light_level"=>$level_pair[0]->light_level, "motion_level"=>$level_pair[0]->motion_level);
						$addedAlready=true;
						array_push($modifiedPrograms, $brighter);
					}*/
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
					/*if (!$addedAlready){
						$brighter = array("programID" => $id1, "target_id" => $level_pair[0]->target_id, "time_start" => $time1->time_start, 
						"time_end" => $time1->time_end, "weekdays" => $time1->weekdays, "light_detector" => $level_pair[0]->light_detector, "motion_detector" 
						=> $level_pair[0]->motion_detector, "light_level"=>$level_pair[0]->light_level, "motion_level"=>$level_pair[0]->motion_level);
						$addedAlready=true;
						array_push($modifiedPrograms, $brighter);
					}*/
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
					/*if (!$addedAlready){
						$brighter = array("programID" => $id1, "target_id" => $level_pair[0]->target_id, "time_start" => $time1->time_start, 
						"time_end" => $time1->time_end, "weekdays" => $time1->weekdays, "light_detector" => $level_pair[0]->light_detector, "motion_detector" 
						=> $level_pair[0]->motion_detector, "light_level"=>$level_pair[0]->light_level, "motion_level"=>$level_pair[0]->motion_level);
						$addedAlready=true;
						array_push($modifiedPrograms, $brighter);
					}*/
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

/*
function addGroup () {
	$sql = "insert into lights (name, brightness, parent, isGroup) values (?, ?, ?, ?)";
	$requestBody = json_decode (Slim::getInstance()->request()->getBody());
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute(array ($requestBody->name, null, 1, true));
		
		print ("{id: '" + $db->lastInsertId('id') + "'}");
	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}
*/

function togglesliders($ids) {
	$ids_array = preg_split ("/,/", $ids);
	$sql = "delete from light_activations where id=?";
	
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		
		foreach ($ids_array as $cid) {
			$stmt->execute(array($cid));
		}
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

// TODO: Should send parameters fancier way?
function poll($ids, $values, $timers, $enableds) {
	$ids_array = preg_split ("/,/", $ids);
	$values_array = preg_split( "/,/", $values);
	$timers_array = preg_split ( "/,/", $timers);
	$enableds_array = preg_split ( "/,/", $enableds);
	
	// TODO: Proper check for values (in other places too...) no empty values "" etc. ?
	if ((count($ids_array) != count($values_array)) ||
	(count($values_array) != count($timers_array)) ||
	(count($timers_array) != count($enableds_array)) )
		return;
		
	// Create the array of original values
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
		$origLevels[$cid]["timer"] = $timer;
		
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
		$newLevels = getLevels($ids_array);
		$retArray = array();
		
		/*
			print(json_encode($origLevels));
			print("<-ORIG");
			print(json_encode($newLevels));
			print("NEW");
		*/
			// Original values doesn't match the new one, return new values
			foreach($newLevels as $id => $carray) {
				if ($newLevels[$id] != $origLevels[$id])
						$retArray[$id] = $carray;
			}
		if ($retArray) {
			print(json_encode($retArray));
			break;
		}
		usleep(1000000);
	}
}

function getLevels($ids_array) {
	// TODO: This belongs elsewhere
	date_default_timezone_set('Europe/Helsinki');
	
	// TODO: Implode ids
	$sql = "select * from light_activations where id=?";
	$retArray = array();
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		
		foreach ($ids_array as $cid) {
			$stmt->execute(array($cid));
			$level = $stmt->fetch (PDO::FETCH_OBJ);
			
			// There may not be DB results or the timer may be 0
			if ($level && strtotime($level->ends_at)-time() > 0) {
				$retArray[$level->id] = array();
					$retArray[$level->id]["timer"]  = strtotime($level->ends_at)-strtotime($level->activated_at);
					$retArray[$level->id]["current_level"] = $level->current_level;	
					$retArray[$level->id]["enabled"] = true;
					continue;
			}
			$retArray[$cid] = array();
			$retArray[$cid]["enabled"] = false;
			$retArray[$cid]["current_level"] = 0;
			$retArray[$cid]["timer"] = 0;
		}
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
	return($retArray);
}

// Save slider level
function saveSliders() {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	print(json_encode($params));
	$ids = $params->ids;
	$value = $params->value;
	$timer = $params->timer;
	
	$currentTime = time();
	
	// Check timer 24h and "negative" limits
	if ($timer < 0) // TODO: What happens if this is sent?
		$timer = 0;
	else if ($timer > 86400)
		$timer = 86400;
	$endTime = $currentTime + $timer;
	
	// TODO: This int convert good enough?
	// Check the slider value
	$value = (int)$value;
	if ($value < 0)
		$value = 0;
	else if ($value > 100)
		$value = 100;
		
	$ids_array = preg_split ("/,/", $ids);
	
	// TODO: Repeated SQL statements seem heavy. Implode IDs!

  // Insert the slider values into DB
	$sql = "insert into light_activations values (?,?,?,?) on duplicate key update current_level=?, activated_at=from_unixtime(?), ends_at=from_unixtime(?)";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		
		foreach ($ids_array as $cid)
			$stmt->execute(array($value,$currentTime,$endTime,$cid,$value,$currentTime,$endTime));
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

// Get light/group data and children
// TODO: Inexistent IDs
function getObjectData ($ids) {	
	$ids_array = preg_split ("/,/", $ids);
	$retArray = array();
	
	// TODO: This belongs elsewhere
	// Used in converting SQL datetime into timestamp
	date_default_timezone_set('Europe/Helsinki');
	
	foreach ($ids_array as $id) {
		$lights = newGetLights($id);
		if (count($lights) == 0) return;
		
		$lights = $lights[0]; // PHP version problem
		
		// Get the time remaining
		$timer = strtotime($lights -> ends_at)-time();
		if ($timer < 0)
			$timer = 0;
		$lights -> timer = $timer;
		
		$children = getChildren($id);
		$childrenIds = array();
		
		// TODO: Move id extract elsewhere
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

		//$lights -> ghost = getGhost($id);
		$retArray[] = $lights;
	}
	print(json_encode($retArray));
}

function newGetLights ($ids) {
	// TODO: Delimit ids by something safer
	$ids_array = preg_split ("/,/", $ids);

	// Get joined data from "lights" and "light_activations" tables
	$sql = "select * from lights left join light_activations on lights.permanent_id=light_activations.id where permanent_id=?";

	$lights=array();

	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);

		foreach ($ids_array as $x) {
			$stmt->execute(array($x));
			$lights = $stmt->fetchAll (PDO::FETCH_OBJ);
		}
		//TODO: If empty...
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

	return ($lights);
}

function getChildren ($id) {
	$sql = "select * from lights where permanent_id in (select child_id from groups where parent_id=?)";
	
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);

		$stmt->execute(array($id));
		$children = $stmt->fetchAll (PDO::FETCH_OBJ);
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

	return ($children);
}

function getAllChildren ($id) {
	$allChildren = array();
	$tempChildren[] = $id;

	// TODO: Should prevent endless cycle in group creation
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
