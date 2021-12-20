<?php
/** @var \ORCA\SurveyQueueStatus\SurveyQueueStatus $module */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$module->initializeSmarty();
$sq_cron_enabled = $module->getProjectSetting("sq_cron_enabled");

if(!empty($_POST["action"]) && $sq_cron_enabled == "enabled"){
    $results = $module->updateSurveyQueueStatus(true);
}

$module->setTemplateVariable("results", $results);
$module->displayTemplate('survey_queue_status.tpl');

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';