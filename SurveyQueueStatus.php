<?php
// Set the namespace defined in your config file
namespace ORCA\SurveyQueueStatus;

use Exception;
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
    private $_debugging = null;
    private $_cutoff_datetime = null;

    public function __construct()
    {
        parent::__construct();
        $this->_module_path = $this->getModulePath();
    }

    public function isDebugging() {
        if ($this->_debugging === null) {
            $this->_debugging = $this->getProjectSetting("debugging_enabled") ?? false;
        }
        return $this->_debugging;
    }

    public function getCutoffDatetime() {
        if ($this->_cutoff_datetime === null) {
            $this->_cutoff_datetime = strtotime("now -24 hours");
        }
        return $this->_cutoff_datetime;
    }

    public function getCutoffDatetimeFormatted() {
        return date("Y-m-d H:i:s", $this->getCutoffDatetime());
    }

    function cronEntryPoint()
    {
        $projects = $this->getProjectsWithModuleEnabled();
        if (count($projects) > 0) {
            foreach ($projects as $k => $project_id) {
                try {
                    // first ensure the module config has this as enabled
                    if ($this->getProjectSetting("cron-enabled", $project_id) === "enabled") {
                        \REDCap::logEvent($this->PREFIX, "Executing Cron Job", "", null, null, $project_id);
                        $this->updateSurveyQueueStatus($project_id);
                        \REDCap::logEvent($this->PREFIX, "Cron Job Execution Completed", "", null, null, $project_id);
                    } else {
                        \REDCap::logEvent($this->PREFIX, "Cron Job is DISABLED in the module config.", "", null, null, $project_id);
                    }
                } catch (Exception $ex) {
                    \REDCap::logEvent($this->PREFIX, "Cron job for " . $this->PREFIX . " failed! See module logs for more details", "", null, null, $project_id);
                    $this->log($ex->getMessage(), [ "project_id" => $project_id ]);
                    $this->log($ex->getTraceAsString(), [ "project_id" => $project_id ]);
                }
            }
        } else {
            \REDCap::logEvent("Cannot run cron job for {$this->PREFIX} because the module has not been enabled for any projects");
        }
    }
}