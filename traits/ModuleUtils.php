<?php
/** @var \ORCA\SurveyQueueStatus\SurveyQueueStatus $this */
namespace ORCA\SurveyQueueStatus;

trait ModuleUtils {
    function updateSurveyQueueStatus() {
        global $Proj;
        $this->addTime();
        // getting project data
        $data = \REDCap::getData([
            "project_id" => $Proj->project_id,
            "fields" => [
                "record_id",
                "email",
                "survey_queue_link",
                "survey_queue_incomplete"
            ]]);
        $this->addTime("GET DATA");
        // aggregate survey queue status for each record
        $results = [];
        $survey_dataset = [];
        foreach ($data as $record_id => $r) {
            $results[$record_id] =  \Survey::getSurveyQueueForRecord($record_id);
            $incomplete_surveys_number =  count(array_filter($results[$record_id], function($row) {return $row["completed"] == "0"; }));
            $survey_dataset[$record_id][$Proj->firstEventId]["incomplete_surveys_number"] = $incomplete_surveys_number;
            $survey_dataset[$record_id][$Proj->firstEventId]["total_surveys_number"] = count($results[$record_id]);
            if($incomplete_surveys_number > 0 ) {
                $survey_dataset[$record_id][$Proj->firstEventId]["incomplete_surveys"] = "Yes";
            }
            else{
                $survey_dataset[$record_id][$Proj->firstEventId]["incomplete_surveys"] = "No";
            }
            $survey_queue_link = \REDCap::getSurveyQueueLink($record_id,$Proj->project_id);
            $survey_queue_link_html = '<a href="' . $survey_queue_link . '" >' . "Survey Queue Link" . '</a>';
            $survey_dataset[$record_id][$Proj->firstEventId]["survey_queue_link"] = $survey_queue_link_html;
        }
        //save data
        if(!empty($survey_dataset)) {
            try {
                // chunk the data to more manageable sizes for the saveData process
                $chunk_size = 500;
                $id_offset = 0;
                $results_m = [];
                do {
                    // slice the data by size and offset
                    $this_data_m = array_slice($survey_dataset, $id_offset, $chunk_size, true);
                    // save the sliced data chunk
                    $save_result_m = \REDCap::saveData($Proj->project_id, "array", $this_data_m, "normal");
                    // add any errors to result
                    if (!empty($save_result_m["errors"])) {
                        $results_m["errors"][] = $save_result_m["errors"];
                    }
                    // increase the offset based on chunk size
                    $id_offset += $chunk_size;

                } while ($id_offset < count($survey_dataset));

                if (!empty($save_result_m["errors"])) {
                    \REDCap::logEvent($this->PREFIX, "Survey queue save error" . $results_m["errors"], "", null, null, $Proj->project_id);
                }
            } catch (\Exception $ex) {
                \REDCap::logEvent($this->PREFIX, "Failed to save data for Survey queue" . $ex->getMessage(), "", null, null, $Proj->project_id);
            }
        }
        $email_setting = $this->getProjectSetting("survey_email_enabled");
        if($email_setting == "enabled") {
            foreach ($data as $recId => $rec) {
                $participant_email = $rec[$Proj->firstEventId]["email"];
                $incomplete_surveys_number = $rec[$Proj->firstEventId]["incomplete_surveys_number"];

                $email_start_date = $this->getProjectSetting("email-start-date");
                $email_end_date = $this->getProjectSetting("email-end-date");
                $send_email = false;

                if (!empty($participant_email) && !empty($email_start_date) && $incomplete_surveys_number > 0 && $email_setting == "enabled") {
                    if (!empty($email_end_date)) {
                        if (date_create($email_start_date) < date_create(date('Y/m/d')) && date_create(date('Y/m/d')) < date_create($email_end_date)) {
                            if ($this->dateDifference($email_start_date, date('Y/m/d')) % $this->getProjectSetting("reminder_email_frequency") == 0) {
                                $send_email = true;
                            }
                        }
                    } else {
                        if (date_create($email_start_date) < date_create(date('Y/m/d'))) {
                            if ($this->dateDifference($email_start_date, date('Y/m/d')) % $this->getProjectSetting("reminder_email_frequency") == 0) {
                                $send_email = true;
                            }

                        }
                    }
                }
                if ($send_email) {
                    $email_body = $this->getProjectSetting("survey_email_body");
                    $email_body_with_link = \Piping::replaceVariablesInLabel($email_body, $recId, $Proj->firstEventId, 1, null, true, $Proj->project_id);
                    // push it out as an attachment
                    $email_sent = \REDCap::email(
                        $participant_email,
                        "wiscstudy@marshfieldresearch.org",
                        "Incomplete surveys",
                        $email_body_with_link,
                        null,
                        null,
                        null,
                        null
                    );
                    if ($email_sent) {
                        \REDCap::logEvent($this->getModuleName() . " - Email Success for '$recId' ", "Success", "", null, null, $Proj->project_id);
                    } else {
                        \REDCap::logEvent($this->getModuleName() . " - Email Failed", error_get_last(), "", null, null, $Proj->project_id);
                    }
                }
            }
        }
        $this->addTime("Processing");
        return $survey_dataset;
    }

    function dateDifference($date_1 , $date_2 , $differenceFormat = '%a'){
        $datetime1 = date_create($date_1);
        $datetime2 = date_create($date_2);
        $interval = date_diff($datetime1, $datetime2);
        return $interval->format($differenceFormat);
    }
}