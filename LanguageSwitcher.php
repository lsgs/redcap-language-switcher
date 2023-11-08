<?php
/**
 * REDCap External Module: Language Switcher
 * @author Luke Stevens lukestevens@hotmail.com https://github.com/lsgs/ 
 */

namespace MCRI\LanguageSwitcher;

use ExternalModules\ExternalModules;

class LanguageSwitcher extends \ExternalModules\AbstractExternalModule {
    protected const DESIGN_INSTRUCTION = 'Design for the primary language on this page. Configure field labels etc. in other languages in the project for each language.';
    protected const SURVEY_COOKIE_NAME = 'LanguageSwitcher';
    protected const ACTION_TAG_REGEXP = '/@LANGUAGE-?SWITCHER/';
    protected $userlangproj = null;
    protected $record = null;
    protected static $SurveyConfigFields = array('title','instructions','offline_instructions','acknowledgement','stop_action_acknowledgement','response_limit_custom_text');
    protected static $DesignLinkPages = array('Design/online_designer.php','Design/data_dictionary_upload.php','Design/data_dictionary_codebook.php');
    
    /**
     * getUserLanguage()
     * Get language for user or survey respondent:
     * - if survey, is there a cookie?
     * - if logged-in user
     * -- is User Setting set?
     * -- is there a DAG default?
     * @return string project id for set language project, or empty
     */
    protected function getUserLanguage() {
        $langproj = $this->getDAGLanguage();
        if (PAGE==='surveys/index.php') {
            if(isset($_COOKIE[static::SURVEY_COOKIE_NAME])) {
                $langproj = $this->escape($_COOKIE[static::SURVEY_COOKIE_NAME]);
            }
        } else {
            $userPref = $this->getUserSetting('UserLangProj');
            if (!empty($userPref)) $langproj = $userPref;
        }
        return $langproj;
    }

    protected function getDAGLanguage() {
        global $participant_id, $user_rights;
        $daglangproj = '';
        $settings = $this->getSubSettings('language-config');
        $dagLang = array();
        foreach ($settings as $langSettingIdx => $langSettings) {
            foreach ($langSettings['language-dag'] as $thisdag) {
                $dagLang[$thisdag] = $langSettings['language-project']; // last if dag entered in multiple lang 
            }
        }

        if (PAGE==='surveys/index.php') {
            $redcap_data = method_exists('\REDCap', 'getDataTable') ? \REDCap::getDataTable($this->getProjectId()) : "redcap_data"; 
            $sql = "select d.record, value
                    from redcap_surveys_participants p
                    inner join redcap_surveys_response r on p.participant_id=r.participant_id
                    inner join redcap_surveys s on p.survey_id=s.survey_id
                    inner join $redcap_data d on s.project_id=d.project_id and r.record=d.record
                    where p.participant_id=? and field_name='__GROUPID__' limit 1";
            $q = $this->query($sql, [$participant_id]);
            while ($row = $q->fetch_assoc()) {
                $this->record = $this->escape($row['record']);
                $dag = $row['value'];
            }
        } else {
            $this->record = (isset($_GET['id'])) ? $this->escape($_GET['id']) : '';
            $dag = $user_rights['group_id'];
        }

        return (isset($dagLang[$dag])) ? $dagLang[$dag] : '';
    }

	function redcap_every_page_before_render($project_id) {
        if (empty($project_id)) return;

        global $lang, $Proj;

        $this->userlangproj = $this->getUserLanguage();
        if (empty($this->userlangproj)) $this->userlangproj = $project_id;

        // is saved user language project still valid for this project
        $settings = $this->getSubSettings('language-config');

        $found=false;
        foreach($settings as $thislang) {
            $langProject = $thislang['language-project'];
            $langDisplay = $thislang['language-label'];
            $langForms = $thislang['language-forms'];
            $langEvents = $thislang['language-events'];
            if ($langProject==$this->userlangproj) {
                $found=true;
                break;
            }
        }
        if (!$found) $this->userlangproj = $project_id;

        // is this a save of a form/survey page containing a field with @LANGUAGE_SWITCHER?
        if ($_SERVER['REQUEST_METHOD']==='POST' && 
            (PAGE==='surveys/index.php' || PAGE==='DataEntry/index.php')) {
            $this->addLangToPOST($this->userlangproj);
        }

        if ($this->userlangproj==$project_id) return; // using primary language - no overriding of elements required

        // read redcap_project for which lang file to use
        $r = self::query('select project_language from redcap_projects where project_id = ?', $this->userlangproj);
		$userlang = @$r->fetch_assoc()['project_language'];

        $lang2 = \Language::callLanguageFile($userlang, false);
        $lang = array_merge($lang, $lang2);

        // override specification in current project from language project
        $langProj = new \Project($this->userlangproj);

        
        // Do not adjust field/event metadata for Design pages primary project (only use alternative language file)
        if (0===strpos(PAGE, "Design/")) return;

        // form names
        $langFormNames = array();
        foreach ($langForms as $lf) {
            $langFormNames[$lf['language-form-main']] = $lf['language-form-lang'];
            $Proj->forms[$lf['language-form-main']]['menu'] = $lf['language-form-lang'];
        }
        if (array_key_exists($Proj->firstForm, $langFormNames)) {
            $Proj->firstFormMenu = $langFormNames[$Proj->firstForm];
        }

        // event names
        $langEventNames = array();
        foreach ($langEvents as $le) {
            $langEventNames[$le['language-event-main']] = $le['language-event-lang'];
            $Proj->eventInfo[$le['language-event-main']]['name'] = $le['language-event-lang'];
            $Proj->eventInfo[$le['language-event-main']]['name_ext'] = $le['language-event-lang'];
        }
        foreach ($Proj->events as $arm_num => $arm) {
            foreach(array_keys($arm['events']) as $evtId) {
                if (array_key_exists($evtId, $langEventNames)) {
                    $Proj->events[$arm_num]['events'][$evtId]['descrip'] = $langEventNames[$evtId];
                }
            }
        }
        if (array_key_exists($Proj->firstEventId, $langEventNames)) {
            $Proj->firstEventName = $langEventNames[$Proj->firstEventId];
        }

        // variable metadata
        foreach (array_keys($Proj->metadata) as $fieldName) {
            if (!array_key_exists($fieldName, $langProj->metadata)) continue;
            // field label, field note, choices, section header
            $langConfig = $langProj->metadata[$fieldName];
            $Proj->metadata[$fieldName]['element_label'] = $langConfig['element_label'];
            $Proj->metadata[$fieldName]['element_preceding_header'] = $langConfig['element_preceding_header'];
            $Proj->metadata[$fieldName]['element_note'] = $langConfig['element_note'];
            $Proj->metadata[$fieldName]['question_num'] = $langConfig['question_num'];

            if (!is_null($Proj->metadata[$fieldName]['form_menu_description']) && array_key_exists($Proj->metadata[$fieldName]['form_name'], $langFormNames)) {
                $Proj->metadata[$fieldName]['form_menu_description'] = $langFormNames[$Proj->metadata[$fieldName]['form_name']];
            }

            if (preg_match('/0, Incomplete.+1, Unverified.+2, Complete/', $Proj->metadata[$fieldName]['element_enum'])) {
                $Proj->metadata[$fieldName]['element_enum'] = "0, ".$lang['global_92']." \n 1, ".$lang['global_93']." \n 2, ".$lang['survey_28'];
            } else if ($Proj->metadata[$fieldName]['element_type']=='select' || 
                $Proj->metadata[$fieldName]['element_type']=='radio' || 
                $Proj->metadata[$fieldName]['element_type']=='checkbox' || 
                $Proj->metadata[$fieldName]['element_type']=='slider' 
               ) {
                $choicesProj = \parseEnum($Proj->metadata[$fieldName]['element_enum']);
                $choicesLang = \parseEnum($langConfig['element_enum']);
                $langEnum = '';
                foreach (array_keys($choicesProj) as $choiceValue) {
                    if (array_key_exists($choiceValue, $choicesLang)) {
                        // choice exists in lang project -> replace label
                        $langEnum .= "$choiceValue, ".$choicesLang[$choiceValue]."\n";
                    } else {
                        // choice not present in lang project -> use default label
                        $langEnum .= "$choiceValue, ".$choicesProj[$choiceValue]." \n ";
                    }
                }
                $Proj->metadata[$fieldName]['element_enum'] = trim($langEnum, " \n ");
            }
        }

        // - survey settings (if survey page)
        if (PAGE==='surveys/index.php') {
            global $form_name;
            $matched = false;
            foreach ($Proj->surveys as $primarySurveyId => $primarySurveyConfig) {
                foreach ($langProj->surveys as $langSurveyId => $langSurveyConfig) {
                    if ($primarySurveyConfig['form_name'] === $langSurveyConfig['form_name']) {
                        $matched = true;
                        break;
                    }
                }
                if ($matched) {
                    foreach (static::$SurveyConfigFields as $surveyParam) {
                        $surveyParam = $this->escape($surveyParam);
                        global $$surveyParam;
                        $$surveyParam = $Proj->surveys[$primarySurveyId][$surveyParam] = $langProj->surveys[$langSurveyId][$surveyParam];
                    }
                }
            }
        }
    }

    function redcap_every_page_top($project_id) {
        if (empty($project_id)) return;

        $defaultLangName = $this->getProjectSetting('language-default');
        $settings = $this->getSubSettings('language-config');

        if (!count($settings)) return; // no additional languages

        if (empty($this->userlangproj)) $this->userlangproj = $project_id;

        $languages = array($project_id => $defaultLangName);
        foreach($settings as $lang) {
            $languages[$lang['language-project']] = $lang['language-label'];
        }

        $designContent = $this->makeDesignContent($languages);
        $showDesignContent = ($designContent==='')?0:1;

        $this->initializeJavascriptModuleObject();
        $jsObjectName = $this->framework->getJavascriptModuleObjectName();

        $langSelect = \RCView::select(array(
            'class'=>'x-form-text x-form-field fs11 ml-1',
            'onchange' => "$jsObjectName.langSelect(this);"
        ), $languages, $this->userlangproj);

        if (PAGE==="surveys/index.php") {
            $setLangPath = $this->getUrl('survey_lang_ajax.php', true);
            $switcherStyle = "display:none; margin:10px 0 0 10px;";

            // do not show switcher option if past page 1 on public survey link because page reload restarts survey
            global $public_survey;
            $showLangSwitcher = ($public_survey && $this->escape($_GET['__page__']) > 1) ? 0 : 1; 
        } else {
            $setLangPath = $this->getUrl('user_lang_ajax.php');
            $switcherStyle = "display:none;";
            $showLangSwitcher = 1;
        }
        ?>
        <div id="LanguageSwitcher" class=""><i class="fas fa-language fs16" style="vertical-align:middle;"></i><?=$langSelect?></div>
        <div id="LanguageSwitcherDesign" class="blue"><?=$designContent?></div>
        <style type="text/css">
            #LanguageSwitcher { <?=$switcherStyle?> }
            #LanguageSwitcherDesign { display: none; clear: both; max-width: 800px; margin: 1em 0; }
        </style>
        <script type="text/javascript">
            <?=$jsObjectName?>.langSelect = function(sel) {
                showProgress(1);
                var langName = $(sel).find('option:selected').text();
                $.ajax({
                    url: "<?=$setLangPath?>",
                    type: 'POST',
                    dataType: 'json',
                    data: { UserLangProj: $(sel).val(), record: '<?=$this->record?>' },
                    success: function(data) {
                        if (isNaN(data)) {
                            showProgress(0,0);
                            <?=$jsObjectName?>.message(data);
                        } else {
                            window.location.reload(true);
                        }
                    },
                    error: function(data) {
                        showProgress(0,0);
                        <?=$jsObjectName?>.message(data);
                    }
                });
            };
            <?=$jsObjectName?>.message = function(msg) {
                simpleDialog(msg);
            }
            $(document).ready(function() {
                if (<?=$showLangSwitcher?>==1) {
                    if (page==='surveys/index.php') {
                        $('#LanguageSwitcher').prependTo('#surveytitlelogo').show();
                    } else {
                        $('#LanguageSwitcher').appendTo('#menu-div > .menubox:first').show();
                    }
                }
                if ((<?=$showDesignContent?>)) {
                    $('#LanguageSwitcherDesign').insertAfter('#sub-nav').show();
                }
            });
        </script>
        <?php
	}

    public function setUserLanguage($project_id, $isSurvey=false) {
        $userLangProj = $this->escape($_POST['UserLangProj']);
        $record = $this->escape($_POST['record']);
        try {
            $defaultLangName = $this->getProjectSetting('language-default');
            $languages = array($project_id => $defaultLangName);
            $settings = $this->getSubSettings('language-config');
            foreach($settings as $lang) {
                $languages[$lang['language-project']] = $lang['language-label'];
            }
        
            if (!array_key_exists($userLangProj, $languages)) {
                throw new \Exception("Language project id $userLangProj is not valid.");
            }
        
        
            if ($isSurvey) {
                // survey - set cookie
                setcookie(static::SURVEY_COOKIE_NAME, $userLangProj, time()+60*60*24*30, '/'); // remember for 30 days
                if (empty($record)) {
                    $logMsg = $languages[$userLangProj].' selected (public survey)';
                } else {
                    $logMsg = $languages[$userLangProj].' selected by survey respondent';
                }
            } else {
                // logged-in user - set user setting
                $this->setUserSetting('UserLangProj', $userLangProj);
                $logMsg = $languages[$userLangProj].' selected by user';
            }
            \REDCap::logEvent('Language Switcher', $logMsg, '', $record);
            $rtn = $userLangProj;
        } catch (\Exception $ex) {
            $rtn = "Language Switcher failed to set user language (project=$userLangProj)\n".$ex->getMessage();
            \REDCap::logEvent('Language Switcher', $rtn, '', $record);
        }
        header("Content-Type: application/json");
        echo json_encode($rtn);
    }

    /**
     * addLangToPOST($langproj)
     * Add selected language of user/survey respondent to any field tagged with @LANGUAGE_SWITCHER
     */
    protected function addLangToPOST($langproj) {
        global $Proj;
        foreach (array_keys($_POST) as $postField) {
            if (!array_key_exists($postField, $Proj->metadata)) continue;
            if (preg_match(static::ACTION_TAG_REGEXP, $Proj->metadata[$postField]['misc'])) {
                $_POST[$postField] = $langproj;
            }
        }
    }

    /**
     * makeDesignContent($languages)
     * On design pages (OD/DD/Cb), give links to projects where alternative language elements are specified.
     */
    protected function makeDesignContent($languages) {
        if (!in_array(PAGE, static::$DesignLinkPages)) return '';
        $designContent = '<div class="fs16"><i class="fas fa-language mr-1 mb-1" style="vertical-align:middle;"></i>Language Switcher<i class="fas fa-cube ml-1"></i></div><div><?=$designContent?></div>';
        $instruction = $this->getSystemSetting('design--instruction');
        $designContent .= ($instruction=='') ? static::DESIGN_INSTRUCTION : $instruction;

        $designContent .= '<div class="text-center my-1">';
        foreach ($languages as $langProjId => $langLabel) {
            if (PROJECT_ID==$langProjId) continue;
            $href = str_replace('pid='.PROJECT_ID, 'pid='.$langProjId, $_SERVER["REQUEST_URI"]);
            $designContent .= "<a class='btn btn-primaryrc btn-sm mx-2' style='color:white;' target='_blank' href='$href'>$langLabel<i class='fas fa-external-link-alt ml-1'></i></a>";
        }
        $designContent .= '</div>';

        return $designContent;
    }
}