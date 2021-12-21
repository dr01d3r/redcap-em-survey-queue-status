<?php
// Set the namespace defined in your config file
namespace ORCA\SurveyQueueStatus;

// The next 2 lines should always be included and be the same in every module
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

require_once 'traits/REDCapUtils.php';
require_once 'traits/ModuleUtils.php';
/**
 * Class SurveyQueueStatus
 * @package ORCA\SurveyQueueStatus
 */
class SurveyQueueStatus extends AbstractExternalModule {
    use \ORCA\SurveyQueueStatus\REDCapUtils;
    use \ORCA\SurveyQueueStatus\ModuleUtils;

    public $_module_path = null;

    public function __construct()
    {
        parent::__construct();
        $this->_module_path = $this->getModulePath();
    }

    function cronEntryPoint() {
        global $Proj;
        try {
            // first ensure the module config has this as enabled
            if ($this->getProjectSetting("cron-enabled", $Proj->project_id) === "enabled") {
                \REDCap::logEvent($this->PREFIX, "Executing Cron Job", "", null, null, $Proj->project_id);
                $this->updateSurveyQueueStatus();
            } else {
                \REDCap::logEvent($this->PREFIX, "Cron Job is DISABLED in the module config.", "", null, null, $Proj->project_id);
            }
        } catch (Exception $ex) {

            \REDCap::logEvent($this->PREFIX, "Caught exception in " . $this->PREFIX . ": " . $ex->getMessage(), "", null, null, $Proj->project_id);
        }
        \REDCap::logEvent($this->PREFIX, "Cron Job Execution Completed", "", null, null, $Proj->project_id);

    }
}