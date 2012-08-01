<?php

require 'slim/Slim.php';

$app = new Slim();

$app->get('/programs', 'getPrograms');
$app->get('/sliders', 'getSliders');
$app->run();

function getPrograms() {
	$sql = "select name from programs";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$programs = $stmt->fetchAll (PDO::FETCH_OBJ);

		echo json_encode ($programs);
	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

}

function getSliders() {
	$sql = "select name, brightness, id from sliders";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$programs = $stmt->fetchAll (PDO::FETCH_OBJ);

		echo json_encode ($programs);
	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

}


function getConnection() {
	$dbhost="127.0.0.1";
	$dbuser="ari";
	$dbpass="foo";
	$dbname="webdali";
	$dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);	
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbh;
}

?>