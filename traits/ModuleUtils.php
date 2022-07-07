<?php
/** @var \ORCA\SurveyQueueStatus\SurveyQueueStatus $this */
namespace ORCA\SurveyQueueStatus;

trait ModuleUtils {
    function getProjectConfiguration($project_id) {
        global $homepage_contact_email;
        $config = [
            "cron_enabled" => $this->getProjectSetting("cron-enabled", $project_id) === "enabled",
            "survey_email_enabled" => $this->getProjectSetting("survey_email_enabled", $project_id) === "enabled",
            "survey_email_from" => $this->getProjectSetting("survey_email_from", $project_id),
            "survey_email_subj" => $this->getProjectSetting("survey_email_subj", $project_id),
            "survey_email_body" => $this->getProjectSetting("survey_email_body", $project_id),
            "reminder_email_frequency" => $this->getProjectSetting("reminder_email_frequency", $project_id),
            "email_start_date" => $this->getProjectSetting("email-start-date", $project_id),
            "email_end_date" => $this->getProjectSetting("email-end-date", $project_id),
        ];
        if ($config["survey_email_enabled"]) {
            if (empty($config["survey_email_from"])) {
                $config["survey_email_from"] = $homepage_contact_email;
            }
            if (empty($config["survey_email_subj"])) {
                $config["survey_email_subj"] = "You have surveys to complete";
            }
        }
        return $config;
    }

    function updateSurveyQueueStatus($project_id, $batch_size = 100, $ignore_cutoff = false, $debugging = false) {
        // initialize project or obtain from cache
        $Proj = new \Project($project_id);
        $config = $this->getProjectConfiguration($project_id);
        $this->addTime("init");
        // set the cutoff time
        $last_processed_datetime_cutoff = $this->getCutoffDatetime();
        $last_processed_datetime_cutoff_formatted = $this->getCutoffDatetimeFormatted();

        // initialize a result response
        $results = [
            "log" => [],
            "errors" => [],
            "debug" => [
                "last_processed_datetime_cutoff" => $last_processed_datetime_cutoff_formatted
            ]
        ];

        if ($ignore_cutoff === true) {
            $results["log"][] = "Cutoff ignored - all records will be checked.";
        } else {
            $results["log"][] = "Cutoff used - $last_processed_datetime_cutoff_formatted";
        }

        // getting project data
        $data = \REDCap::getData([
            "project_id" => $Proj->project_id,
            "fields" => [
                "record_id",
                "email",
                "survey_queue_link",
                "last_processed_datetime"
            ]]);
        $this->addTime("getData complete");

        $records_to_process = [];
        // use all data if cutoff is being ignored
        if ($ignore_cutoff === true) {
            $records_to_process = $data;
        } else {
            // otherwise, filter out records if they are WITHIN the cutoff
            // we only want to process records that have not been touched in the last X hrs
            foreach ($data as $record_id => $record) {
                if (!empty($record[$Proj->firstEventId]["last_processed_datetime"]) && strtotime($record[$Proj->firstEventId]["last_processed_datetime"]) > $last_processed_datetime_cutoff) continue;
                $records_to_process[$record_id] = $record;
            }
        }
        $this->addTime("records filtered");
        $results["log"][] = "Cutoff filtered records down from " . count($data) . " to " . count($records_to_process);
        unset($data);

        // chunk the data to more manageable sizes for the saveData process
        $id_offset = 0;
        do {
            try {
                // slice the data by size and offset
                $this_data = array_slice($records_to_process, $id_offset, $batch_size, true);
                // process the batch
                $process_result = $this->processRecordBatch($project_id, $config, $this_data, $debugging);
                // append any log info
                if (!empty($process_result["log"])) {
                    array_push($results["log"], ...$process_result["log"]);
                }
                // append any error info
                if (!empty($process_result["errors"])) {
                    array_push($results["errors"], ...$process_result["errors"]);
                } else {
                    $results["log"][] = "Processed batch without errors.";
                }
                // append any debug info
                if (!empty($process_result["debug"])) {
                    array_push($results["debug"], ...$process_result["debug"]);
                }
            } catch (\Exception $ex) {
                $err = $ex->getMessage();
                $log_msg = ($debugging ? "[DEBUG]" : "") . $err;
                $results["errors"][] = $log_msg;
                $this->log($log_msg, [ "project_id" => $project_id ]);
            } finally {
                // increase the offset based on chunk size
                $id_offset += $batch_size;
                $this->addTime("batch completed");
            }
        } while ($id_offset < count($records_to_process));

        return $results;
    }

    function processRecordBatch($project_id, $config, $records, $debugging = false) {
        // initialize project or obtain from cache
        $Proj = new \Project($project_id);
        // use the same timestamp for all records saved in this batch
        // simpler and can aid in troubleshooting queries later on
        $last_processed_datetime = date("Y-m-d H:i:s");
        // prep result response
        $results = [
            "debug" => [],
            "errors" => []
        ];
        // process each record in the batch
        $survey_dataset = [];
        foreach ($records as $record_id => $r) {
            $sq =  \Survey::getSurveyQueueForRecord($record_id, false, $Proj->project_id);
            $incomplete_surveys_number = count(array_filter($sq, function($row) { return $row["completed"] == "0"; }));
            $survey_dataset[$record_id][$Proj->firstEventId]["incomplete_surveys_number"] = $incomplete_surveys_number;
            $survey_dataset[$record_id][$Proj->firstEventId]["total_surveys_number"] = count($sq);
            if ($incomplete_surveys_number > 0) {
                $survey_dataset[$record_id][$Proj->firstEventId]["incomplete_surveys"] = "Yes";
            } else {
                $survey_dataset[$record_id][$Proj->firstEventId]["incomplete_surveys"] = "No";
            }
            $survey_dataset[$record_id][$Proj->firstEventId]["email"] = $r[$Proj->firstEventId]["email"];
            $survey_dataset[$record_id][$Proj->firstEventId]["last_processed_datetime"] = $last_processed_datetime;
            // only get a survey queue link if one doesn't already exist!
            if (empty($r[$Proj->firstEventId]["survey_queue_link"])) {
                $survey_queue_link = \REDCap::getSurveyQueueLink($record_id, $Proj->project_id);
                $survey_queue_link_html = '<a href="' . $survey_queue_link . '" >' . "Survey Queue Link" . '</a>';
                $survey_dataset[$record_id][$Proj->firstEventId]["survey_queue_link"] = $survey_queue_link_html;
            }
        }
        // continue processing if there are records to process
        if(!empty($survey_dataset)) {
            // save data
            try {
                $save_result = \REDCap::saveData($Proj->project_id, "array", $survey_dataset, "normal",
                    // provide default values leading up to the 'commitData' parameter
                    'YMD', 'flat', null, true, true, !$debugging
                );
                // add any errors to result
                if (!empty($save_result["errors"])) {
                    array_push($results["errors"], ...$save_result["errors"]);
                    $err = print_r($save_result["errors"], true);
                    $log_msg = ($debugging ? "[DEBUG]" : "") . $err;
                    $this->log($log_msg, [ "project_id" => $project_id ]);
                }
            } catch (\Exception $ex) {
                $err = $ex->getMessage();
                $results["errors"][] = $err;
                $log_msg = ($debugging ? "[DEBUG]" : "") . $err;
                $this->log($log_msg, [ "project_id" => $project_id ]);
            }
            // handle emailing if enabled
            if($config["survey_email_enabled"] === true) {
                $email_sent_count = 0;
                $email_fail_count = 0;
                foreach ($survey_dataset as $recId => $rec) {
                    $participant_email = $rec[$Proj->firstEventId]["email"];
                    $incomplete_surveys = $rec[$Proj->firstEventId]["incomplete_surveys"];
                    $send_email = false;

                    if (!empty($participant_email) && !empty($config["email_start_date"]) && $incomplete_surveys === "Yes") {
                        if (!empty($config["email_end_date"])) {
                            if (date_create($config["email_start_date"]) <= date_create(date('Y/m/d')) && date_create(date('Y/m/d')) <= date_create($config["email_end_date"])) {
                                if ($this->dateDifference($config["email_start_date"], date('Y/m/d')) % $config["reminder_email_frequency"] == 0) {
                                    $send_email = true;
                                }
                            }
                        } else {
                            if (date_create($config["email_start_date"]) <= date_create(date('Y/m/d'))) {
                                if ($this->dateDifference($config["email_start_date"], date('Y/m/d')) % $config["reminder_email_frequency"] == 0) {
                                    $send_email = true;
                                }
                            }
                        }
                    }
                    if ($send_email) {
                        $email_body = \Piping::replaceVariablesInLabel($config["survey_email_body"], $recId, $Proj->firstEventId, 1, null, true, $Proj->project_id);
                        // send out the email if we aren't debugging
                        if ($debugging === true) {
                            // fake the count for debugging purposes
                            $email_sent_count++;
                        } else {
                            $email_sent = \REDCap::email(
                                $participant_email,
                                $config["survey_email_from"],
                                $config["survey_email_subj"],
                                $email_body,
                                null,
                                null,
                                null,
                                null
                            );
                            if ($email_sent) {
                                $email_sent_count++;
                            } else {
                                $email_fail_count++;
                                $log_msg = ($debugging ? "[DEBUG]" : "") . "[Email Failed]" . error_get_last();
                                $this->log($log_msg, [ "project_id" => $project_id ]);
                            }
                        }
                    }
                }
                // always log email sent counts
                $log_msg = ($debugging ? "[DEBUG]" : "") . "Successfully sent $email_sent_count email(s).";
                $results["log"][] = $log_msg;
                $this->log($log_msg, [ "project_id" => $project_id ]);
                // log email fail counts if any exist
                if ($email_fail_count > 0) {
                    $log_msg = "Failed to send $email_fail_count email(s).  See the Module Logs for more details.";
                    $results["errors"][] = $log_msg;
                    $this->log($log_msg, [ "project_id" => $project_id ]);
                }
            }
        }
        return $results;
    }

    function dateDifference($date_1 , $date_2 , $differenceFormat = '%a'){
        $datetime1 = date_create($date_1);
        $datetime2 = date_create($date_2);
        $interval = date_diff($datetime1, $datetime2);
        return $interval->format($differenceFormat);
    }
}