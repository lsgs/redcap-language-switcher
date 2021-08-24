<?php
/**
 * REDCap External Module: Language Switcher
 * @author Luke Stevens lukestevens@hotmail.com https://github.com/lsgs/ 
 */
error_reporting(0);
if (is_null($module) || !($module instanceof MCRI\LanguageSwitcher\LanguageSwitcher)) { exit(); }
echo $module->setUserLanguage($project_id, true);
