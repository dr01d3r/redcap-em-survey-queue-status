<?php
// Set the namespace defined in your config file
namespace ORCA\SurveyQueueStatus;

// The next 2 lines should always be included and be the same in every module
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

require_once 'vendor/autoload.php';
require_once 'traits/REDCapUtils.php';
require_once 'traits/ModuleUtils.php';
/**
 * Class SurveyQueueStatus
 * @package ORCA\SurveyQueueStatus
 */
class SurveyQueueStatus extends AbstractExternalModule {
    use \ORCA\SurveyQueueStatus\REDCapUtils;
    use \ORCA\SurveyQueueStatus\ModuleUtils;

    private static $smarty;
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
    public function initializeSmarty() {
        self::$smarty = new \Smarty();
        self::$smarty->setTemplateDir($this->_module_path . 'templates');
        self::$smarty->setCompileDir($this->_module_path . 'templates_c');
        self::$smarty->setConfigDir($this->_module_path . 'configs');
        self::$smarty->setCacheDir($this->_module_path . 'cache');
    }

    public function setTemplateVariable($key, $value) {
        self::$smarty->assign($key, $value);
    }

    public function displayTemplate($template) {
        self::$smarty->display($template);
    }
}