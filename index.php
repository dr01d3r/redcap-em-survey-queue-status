<?php
/** @var \ORCA\SurveyQueueStatus\SurveyQueueStatus $module */
// set flags through user action or module config
$ignore_cutoff = false;
$debugging = $module->isDebugging();
$config = $module->getProjectConfiguration($project_id);
// process if action was taken
$results = [];
if(!empty($_POST["action"])){
    try
    {
        $ignore_cutoff = isset($_POST["ignore_cutoff"]);
        \REDCap::logEvent($module->PREFIX, "Executing Job Manually", "", null, null, $project_id);
        $results = $module->updateSurveyQueueStatus($project_id, 100, $ignore_cutoff, $debugging);
        \REDCap::logEvent($module->PREFIX, "Manual Job Execution Completed", "", null, null, $project_id);
    } catch (Exception $ex) {
        \REDCap::logEvent($module->PREFIX, "Manual Job Execution FAILED - " . $ex->getMessage(), "", null, null, $project_id);
    }
}
?>
    <div class="projhdr"><i class="fas fa-poll text-dark"></i> Survey Queue Status</div>
<?php if ($debugging) { ?>
    <div class='alert alert-warning'>
        <strong class="text-uppercase">Debugging Mode Enabled!</strong> The module will process like normal, but no data will be saved.  Module-initiated emails will <strong>not</strong> be generated, but the debugging output will 'simulate' that they were sent.
    </div>
<?php } ?>
    <form id="api_runner" action="" enctype="multipart/form-data" method="post">
        <div class="card mb-2">
            <div class="card-header">
                <h4 class="font-weight-lighter mt-2">This will manually execute the cron job for this module.</h4>
                <hr/>
                <h5 class="font-weight-lighter">Cutoff Date/Time</h5>
                <div class="my-2">
                    The module only checks records if they have not been processed within the <strong>24 hour</strong> cutoff period.  This ensures the performance of the module is maintained, as well as avoiding excessive processing of records which could turn into excess communication with participants, depending on this project's configuration.
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="ignore_cutoff" id="ignore_cutoff" value="true">
                    <label class="form-check-label" for="ignore_cutoff">Ignore this cutoff, and process <strong>all records</strong> in the project</label>
                </div>
            </div>
            <div class="card-body">
                <button class="btn btn-primary col-auto" id="btnsubmit" type="submit" name="action" value="save" tabindex="-1">Execute Cron Job</button>
            </div>
        </div>
    </form>
    <script type="text/javascript">
        // disable form resubmit on refresh/back
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
<?php
// output errors if any
if (!empty($results["errors"])) {
    echo "<div class='alert alert-danger'><h5 class='alert-heading'>The following errors occurred during job execution</h5><hr class='my-2'><ul class='m-0 px-3'><li>" . implode("</li><li>", $results["errors"]) . "</li></ul></div>";
}
// output results if any
if (!empty($results["log"])) {
    echo "<div class='alert alert-info'><h5 class='alert-heading'>Processing Output</h5><hr class='my-2'><ul class='m-0 px-3'><li>" . implode("</li><li>", $results["log"]) . "</li></ul></div>";
}
if ($debugging === true) {
    $module->preout($module->outputTimerInfo(true, true));
    // output debug if any
    if (!empty($results["debug"])) {
        echo "<div class='alert alert-warning'><h5 class='alert-heading'>Debugging Output</h5><hr class='my-2'><ul class='m-0 px-3'><li>" . implode("</li><li>", $results["debug"]) . "</li></ul></div>";
    }
} else {
    $module->outputTimerInfo(false, false);
}