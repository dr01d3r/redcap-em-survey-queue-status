<?php
/** @var \ORCA\SurveyQueueStatus\SurveyQueueStatus $module */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$sq_cron_enabled = $module->getProjectSetting("sq_cron_enabled");

if(!empty($_POST["action"])){
    if ($sq_cron_enabled == "enabled") {
        $results = $module->updateSurveyQueueStatus();
    } else {
        echo "<div class='alert alert-warning'>Did not execute because the cron is not enabled in the module configuration.</div>";
    }
}
?>
    <div class="card">
        <div class="card-header">
            <form id="api_runner" action="" enctype="multipart/form-data" method="post">
                <h5 class="lead mt-2">
                    Survey Queue Status
                    <br />
                    <small class="text-secondary">This will manually execute the cron job for this module.  To run, it must be enabled in the module configuration page for this project.</small>
                </h5>
                <div class="mt-3">
                    <button class="btn btn-primary col-auto" id="btnsubmit" type="submit" name="action" value="save" tabindex="-1">Cron Job</button>
                </div>
            </form>
        </div>
    </div>
<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';