# Language Switcher

## Description

This module enables support for multiple languages within a REDCap project. You can switch between any number of configured languages on demand to view different elements in the selected language:
* Data entry forms
* Survey forms
* Additional survey text e.g. instructions, termination message
* All standard page text elements including validation check failure messages

## How It Works

Create a new project for each additional language you require. Set up this project with any or all of the following, as required:
* Data dictionary containing the variables/forms you require in the language (it does not need to contain all forms of the main project).
* Custom language file on the Edit Project Settings page in the Control Centre containing translations for any standard language elements you wish to view translated (e.g. validation check messages.)

Neither dictionary nor language file need be complete. The additional language projects need contain only language elements and field metadata that match those in the primary project you wish to have overridden. You can, for example:
* Have just one form (a participant-facing survey, for example) be translated.
* Alter just certain language elements like validation check failure messages or button labels ("Randomization" for "US English"; "Randomisation" for Australian English).

**Note**
* All data entry is done in the primary project. The additional projects are used only for language element configuration, and field and survey metadata changes and should never contain data.
* Because the page is reloaded when changing language, the switching option is present only on the first page of *public* surveys.

### Field Metadata

Field metadata can be overridden by language-specific versions by including corresponding variables in the language project.

|Primary Project|Language Project|Result|
|:---:|:---:|---|
|Y|Y|Override from language project|
|Y|N|Use metadata from primary project|
|N|Y|Field ignored|

The following field-level metadata are overridden in the primary project when matching elements are found in the selected additional language project.
* Field label
* Field note
* Section header
* Question number
* Labels for dropdown, radio, checkbox, yesno, truefalse and slider fields *for choice values that match values in the primary project*

The following field-level metadata are **NOT** overridden and always take their configuration from the primary project:
* Form name (see section regarding form names below)
* Field type
* Validation type and range
* Branching logic
* Identifier
* Required
* Alignment
* Matrix group name
* Annotation (action tags)

### Survey Metadata

The following survey-level settings (only) are overridden in the primary project when specified in the selected additional language project.
* Title
* Survey instructions
* Offline notification
* Acknowledgement
* Stop action acknowledgement
* Response limit custom text

Note that this list does not (currently) include ASI content.

### Event Names

Event names in additional language projects must match those in the primary project. This ensures that branching logic containing unique event names remains valid. To utilise alternative event names for additional languages, use the External Module settings (see below).

### Form Names

Form names in additional language projects must match those in the primary project. This ensures that form status field names match and you get translated text for the status field label and choices. To utilise alternative form names for additional languages, use the External Module settings (see below).

## Logging

Logging occurs in two ways:
1. An event is added to the project's event log when the user or survey respondent makes a selection in the language switcher dropdown list.
2. The project id of the language in use at the time a form or survey page is saved is written to any field on that form or survey page that has the `@LANGUAGE-SWITCHER` action tag.

## External Module Configuration

### Primary Language

For the primary language set:

* **Language label**
    Name of primary language as displayed to users in the language switcher select list.

### Additional Languages

Configure the following settings for each language:

* **Language label**
    Name of language as displayed to users in the language switcher select list.
    
* **Project**
    The corresponding project configured with data dictionary and/or language file for the language.

* **DAGs** (Repeating)
    [Optional] Data access groups for which this language will be the default. Applies to both logged-in users assigned to the DAG and survey respondents of records assigned to the DAG.
    
* **Instrument Names** (Repeating)
    [Optional] Alternative instrument names for this language. (Can't be set in the language project because form status field names would no longer match those in the primary project.)    

* **Event Names** (Repeating)
    [Optional] Alternative event names for this language. (Can't be set in the language project because event references required in branching logic would change and no longer match those in the primary project.)

## Demo

![language_switcher.gif](https://redcap.mcri.edu.au/surveys/index.php?pid=9033&__passthru=DataEntry%2Fimage_view.php&doc_id_hash=d6588550be1813c32781b35118433c74fef76d7b&id=1254303&instance=1&s=LN7MTPDCAR "Language Switcher Demo")
