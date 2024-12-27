<?php
use Phalcon\Mvc\Micro;
try{
    require_once '../env.php';
	$application = new Micro($di);
	$collectionList = KugaApp\Application::initHandlerCollection();
	foreach($collectionList as $collection){
		$application->mount($collection);
	}
	$application->handle($_SERVER['REQUEST_URI']);
} catch (\Phalcon\Mvc\Dispatcher\Exception $e) {
    header("HTTP/1.0 404 Not Found");
} catch (Phalcon\Exception $e) {
	echo $e->getMessage();
} catch (PDOException $e){
	echo $e->getMessage();
}catch (Exception $e){
	echo $e->getMessage();
}
?>
